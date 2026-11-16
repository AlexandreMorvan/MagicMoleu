<?php

namespace App\Tests\Combo;

use App\Entity\Deck;
use App\Entity\OracleCard;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Tests\Deck\DeckTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class DeckCombosTest extends WebTestCase
{
    use DeckTestTrait;

    private KernelBrowser $client;
    private User $owner;

    /** @var list<array{string, string, array<string, mixed>}> méthode, URL, corps JSON */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        self::importDeckFixtures();
        self::getContainer()->get('cache.app')->clear();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->owner = (new User())->setUsername('Nissa')->setEmail('nissa@example.com')->setPassword('x');
        $em->persist($this->owner);
        $em->flush();
    }

    public function testDeckPageLoadsTheCombosLazily(): void
    {
        $deck = $this->persistDeck(public: true);

        $this->client->request('GET', '/decks/'.$deck->getId());

        self::assertSelectorExists(\sprintf('turbo-frame#deck-combos[src="/decks/%d/combos"][loading="lazy"]', $deck->getId()));
    }

    public function testListsIncludedAndAlmostIncludedCombos(): void
    {
        $deck = $this->persistDeck(public: true);
        $this->mockApi(new JsonMockResponse(['results' => [
            'included' => [self::variant('1-2', ['Thrasios, Triton Hero', 'Sol Ring'], 10, self::oracle('Sol Ring'))],
            'almostIncluded' => [
                self::variant('1-3', ['Thrasios, Triton Hero', 'Kiki-Jiki, Mirror Breaker'], 50),
                self::variant('2-3', ['Tymna the Weaver', 'Basalt Monolith'], 500),
            ],
        ]]));

        $crawler = $this->client->request('GET', '/decks/'.$deck->getId().'/combos');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#deck-combos');
        self::assertSelectorTextContains('#combos-dans-le-deck', 'Dans le deck (1)');
        self::assertSelectorTextContains('#combos-a-une-carte-pres', 'À une carte près (2)');
        // Une ligne repliée par combo, les plus joués d'abord, avec la carte à ajouter dans le résumé
        self::assertSame(['combo-1-2', 'combo-2-3', 'combo-1-3'], $crawler->filter('#combos li[id^="combo-"]')->each(fn ($li) => $li->attr('id')));
        self::assertCount(3, $crawler->filter('#combos details.panel:not([open])'));
        self::assertSelectorTextContains('#combo-2-3 summary', 'Ajouter Basalt Monolith');
        self::assertSelectorTextContains('#combo-1-2 summary', 'Thrasios, Triton Hero + Sol Ring');
        self::assertSame(['Basalt Monolith'], $crawler->filter('#combo-2-3 .badge')->reduce(fn ($b) => 'À ajouter' === $b->text())->each(fn ($b) => $b->closest('.card-tile')->filter('img')->attr('alt')));
        self::assertCount(0, $crawler->filter('#combo-1-2 .badge')->reduce(fn ($b) => 'À ajouter' === $b->text()));
        // Les cartes présentes en base ouvrent la modale
        self::assertSelectorExists('#combo-1-2 a[data-turbo-frame="card_modal"]');

        // Le deck est envoyé à Commander Spellbook en séparant les commandants, sans la zone « peut-être »
        [$method, $url, $body] = $this->requests[0];
        self::assertSame('POST', $method);
        self::assertStringEndsWith('/find-my-combos', $url);
        self::assertSame([['card' => 'Thrasios, Triton Hero', 'quantity' => 1], ['card' => 'Tymna the Weaver', 'quantity' => 1]], $body['commanders']);
        self::assertEqualsCanonicalizing(['Forest', 'Sol Ring'], array_column($body['main'], 'card'));
    }

    public function testOnlyTheFirstCombosAreShownTheRestIsFolded(): void
    {
        $deck = $this->persistDeck(public: true);
        $almost = [];
        for ($i = 1; $i <= 15; ++$i) {
            $almost[] = self::variant("9-$i", ['Thrasios, Triton Hero', "Carte $i"], 100 - $i);
        }
        $this->mockApi(new JsonMockResponse(['results' => ['included' => [], 'almostIncluded' => [...$almost, self::variant('9-99', ['Tymna the Weaver', 'Rare'], 1)]]]));

        $crawler = $this->client->request('GET', '/decks/'.$deck->getId().'/combos');

        self::assertSelectorTextContains('#combos-a-une-carte-pres', 'À une carte près (16)');
        self::assertCount(5, $crawler->filter('#combos > ul > li'), '5 combos visibles');
        self::assertSelectorTextContains('#combos', 'Voir les 10 autres');
        self::assertSelectorTextContains('#combos', 'Seuls les 15 plus joués sont affichés.');
        self::assertSelectorNotExists('#combo-9-99');
    }

    public function testResultsAreCachedForTheSameList(): void
    {
        $deck = $this->persistDeck(public: true);
        $this->mockApi(new JsonMockResponse(['results' => ['included' => [], 'almostIncluded' => []]]));

        $this->client->request('GET', '/decks/'.$deck->getId().'/combos');
        $this->client->request('GET', '/decks/'.$deck->getId().'/combos');

        self::assertSelectorTextContains('#combos', 'Aucun combo connu dans ce deck');
        self::assertCount(1, $this->requests);
    }

    public function testApiFailureIsShownInTheFrame(): void
    {
        $deck = $this->persistDeck(public: true);
        $this->mockApi(new MockResponse('', ['http_code' => 502]));

        $this->client->request('GET', '/decks/'.$deck->getId().'/combos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#combos', 'momentanément indisponible');
    }

    public function testPrivateDeckCombosAreHiddenFromOthers(): void
    {
        $deck = $this->persistDeck(public: false);

        $this->client->request('GET', '/decks/'.$deck->getId().'/combos');
        self::assertResponseRedirects('/connexion', message: 'un visiteur anonyme est invité à se connecter');

        $other = (new User())->setUsername('Jace')->setEmail('jace@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($other);
        $em->flush();
        $this->client->loginUser($other);
        $this->client->request('GET', '/decks/'.$deck->getId().'/combos');
        self::assertResponseStatusCodeSame(403);
        self::assertEmpty($this->requests, 'Commander Spellbook n\'est pas interrogé');
    }

    private function persistDeck(bool $public): Deck
    {
        $deck = self::buildDeck(Format::Commander, [
            'Thrasios, Triton Hero' => [1, DeckZone::Commander],
            'Tymna the Weaver' => [1, DeckZone::Commander],
            'Sol Ring' => 1,
            'Forest' => 97,
            'Counterspell' => [1, DeckZone::Maybe],
        ], $this->owner)->setName('Partenaires')->setPublic($public);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($deck);
        $em->flush();

        return $deck;
    }

    private function mockApi(MockResponse ...$responses): void
    {
        self::getContainer()->set('commander_spellbook.client', new MockHttpClient(
            function (string $method, string $url, array $options) use (&$responses): MockResponse {
                $this->requests[] = [$method, $url, json_decode($options['body'] ?? 'null', true)];

                return array_shift($responses);
            },
            'https://backend.commanderspellbook.com',
        ));
    }

    /**
     * @param list<string> $cardNames
     *
     * @return array<string, mixed>
     */
    private static function variant(string $id, array $cardNames, int $popularity, ?OracleCard $local = null): array
    {
        return [
            'id' => $id,
            'uses' => array_map(static fn (string $name) => ['card' => [
                'name' => $name,
                'oracleId' => $local && $local->getName() === $name ? $local->getId()->toRfc4122() : null,
                'imageUriFrontNormal' => 'https://cards.scryfall.io/'.rawurlencode($name).'.jpg',
            ]], $cardNames),
            'produces' => [['feature' => ['name' => 'Infinite mana']]],
            'description' => "Étape 1.\nÉtape 2.",
            'identity' => 'UB',
            'popularity' => $popularity,
        ];
    }
}
