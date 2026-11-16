<?php

namespace App\Tests\Controller;

use App\Entity\OracleCard;
use App\Repository\OracleCardRepository;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class CardCombosTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var list<string> */
    private array $requestedUrls = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Garde le client HTTP simulé d'une requête à l'autre
        $this->client->disableReboot();
        self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
        self::getContainer()->get('cache.app')->clear();
    }

    public function testListsCombosUsingTheCard(): void
    {
        $solRing = $this->card('Sol Ring');
        $this->mockApi(new JsonMockResponse([
            'next' => 'https://backend.commanderspellbook.com/variants/?offset=50',
            'results' => [self::variant($solRing)],
        ]));

        $crawler = $this->client->request('GET', '/cartes/'.$solRing->getId().'/combos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sol Ring');
        self::assertSelectorTextContains('header.panel', 'Les 1 combos les plus joués');
        self::assertStringContainsString('q=card%3D%22Sol%20Ring%22', $this->requestedUrls[0]);
        self::assertStringContainsString('ordering=-popularity', $this->requestedUrls[0]);

        $combo = $crawler->filter('#combo-1-2');
        self::assertSame(['Infinite colorless mana', 'Win the game'], $combo->filter('[aria-label="Résultats"] li')->each(fn ($li) => $li->text()));
        self::assertCount(2, $combo->filter('ol li'), 'une étape par ligne de la description');
        self::assertStringContainsString('Untap Sol Ring.', $combo->filter('ol')->text());

        // Sol Ring est en base : sa vignette ouvre la modale ; l'autre carte est affichée sans lien
        self::assertCount(1, $combo->filter('a[data-turbo-frame="card_modal"]'));
        self::assertSame('/cartes/'.$solRing->getId(), $combo->filter('a[data-turbo-frame="card_modal"]')->attr('href'));
        self::assertSelectorExists('#combo-1-2 img[alt="Unknown Untapper"]');
        self::assertSelectorExists('#combo-1-2 a[href="https://commanderspellbook.com/combo/1-2/"]');
    }

    public function testResponsesAreCached(): void
    {
        $solRing = $this->card('Sol Ring');
        $this->mockApi(new JsonMockResponse(['next' => null, 'results' => []]));

        $this->client->request('GET', '/cartes/'.$solRing->getId().'/combos');
        $this->client->request('GET', '/cartes/'.$solRing->getId().'/combos');

        self::assertSelectorTextContains('body', 'Aucun combo connu');
        self::assertCount(1, $this->requestedUrls);
    }

    public function testApiFailureShowsAMessageAndIsNotCached(): void
    {
        $solRing = $this->card('Sol Ring');
        $this->mockApi(new MockResponse('', ['http_code' => 500]), new JsonMockResponse(['next' => null, 'results' => []]));

        $this->client->request('GET', '/cartes/'.$solRing->getId().'/combos');
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('body', 'momentanément indisponible');

        $this->client->request('GET', '/cartes/'.$solRing->getId().'/combos');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->requestedUrls);
    }

    public function testCardPageLinksToCombos(): void
    {
        $solRing = $this->card('Sol Ring');

        $this->client->request('GET', '/cartes/'.$solRing->getId());

        self::assertSelectorExists(\sprintf('a[href="/cartes/%s/combos"]', $solRing->getId()));
    }

    private function mockApi(MockResponse ...$responses): void
    {
        $responses = array_values($responses);
        self::getContainer()->set('commander_spellbook.client', new MockHttpClient(
            function (string $method, string $url) use (&$responses): MockResponse {
                $this->requestedUrls[] = $url;

                return array_shift($responses);
            },
            'https://backend.commanderspellbook.com',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function variant(OracleCard $card): array
    {
        return [
            'id' => '1-2',
            'uses' => [
                ['card' => ['name' => $card->getName(), 'oracleId' => $card->getId()->toRfc4122(), 'imageUriFrontNormal' => 'https://cards.scryfall.io/sol.jpg']],
                ['card' => ['name' => 'Unknown Untapper', 'oracleId' => '11111111-1111-1111-1111-111111111111', 'imageUriFrontNormal' => 'https://cards.scryfall.io/x.jpg']],
            ],
            'produces' => [
                ['feature' => ['name' => 'Infinite colorless mana']],
                ['feature' => ['name' => 'Win the game']],
            ],
            'description' => "Tap Sol Ring for {C}{C}.\nUntap Sol Ring.",
            'easyPrerequisites' => 'All permanents on the battlefield.',
            'notablePrerequisites' => '',
            'manaNeeded' => '',
            'identity' => 'C',
            'popularity' => 1234,
        ];
    }

    private function card(string $name): OracleCard
    {
        return self::getContainer()->get(OracleCardRepository::class)->findOneBy(['name' => $name]);
    }
}
