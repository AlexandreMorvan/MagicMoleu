<?php

namespace App\Tests\Game;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Tests\Deck\DeckTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages de la main de départ et du suivi de partie. Leur logique (côté navigateur) est testée par
 * tests/js/*.test.js : `node --test tests/js/`.
 */
class GameToolsTest extends WebTestCase
{
    use DeckTestTrait;

    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::importDeckFixtures();
        $this->owner = (new User())->setUsername('Nissa')->setEmail('nissa@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($this->owner);
        $em->flush();
    }

    public function testCommanderDeckLinksToTheOpeningHandAndTheGameTracker(): void
    {
        $deck = $this->persistDeck(Format::Commander);

        $this->client->request('GET', '/decks/'.$deck->getId());

        self::assertSelectorExists(\sprintf('a[href="/decks/%d/main-de-depart"]', $deck->getId()));
        self::assertSelectorExists(\sprintf('a[href="/partie?deck=%d"]', $deck->getId()));
    }

    public function testOpeningHandReceivesTheLibraryWithoutTheCommanders(): void
    {
        $deck = $this->persistDeck(Format::Commander);

        $crawler = $this->client->request('GET', '/decks/'.$deck->getId().'/main-de-depart');

        self::assertResponseIsSuccessful();
        $library = json_decode($crawler->filter('[data-controller="goldfish"]')->attr('data-goldfish-deck-value'), true);
        self::assertCount(98, $library, 'deck principal seulement : ni le commandant (zone de commandement) ni la zone « peut-être »');
        self::assertCount(97, array_filter($library, static fn (array $c) => $c['land']), '97 forêts');
        self::assertSame('Sol Ring', array_values(array_filter($library, static fn (array $c) => !$c['land']))[0]['name']);
        self::assertSelectorExists('aside img[alt="Wilson, Refined Grizzly"]');
    }

    public function testOpeningHandIsReservedToCommanderDecks(): void
    {
        $deck = $this->persistDeck(Format::Modern);

        $this->client->request('GET', '/decks/'.$deck->getId().'/main-de-depart');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertSelectorNotExists('a[href$="/main-de-depart"]');
    }

    public function testPrivateDeckOpeningHandIsHiddenFromOthers(): void
    {
        $deck = $this->persistDeck(Format::Commander, public: false);
        $other = (new User())->setUsername('Jace')->setEmail('jace@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($other);
        $em->flush();
        $this->client->loginUser($other);

        $this->client->request('GET', '/decks/'.$deck->getId().'/main-de-depart');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGameTrackerIsPublicAndOffersTwoToSixPlayers(): void
    {
        $crawler = $this->client->request('GET', '/partie');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="game-tracker"]');
        self::assertSame(['2', '3', '4', '5', '6'], $crawler->filter('input[name="players"]')->each(fn ($o) => $o->attr('value')));
        self::assertSame('4', $crawler->filter('input[name="players"][checked]')->attr('value'));
        self::assertSame('40', $crawler->filter('#game-life')->attr('value'));
        self::assertCount(6, $crawler->filter('[data-game-tracker-target="playerRow"]'));
    }

    public function testGameStartedFromADeckPrefillsTheFirstPlayer(): void
    {
        $deck = $this->persistDeck(Format::Commander);
        $this->client->loginUser($this->owner);

        $crawler = $this->client->request('GET', '/partie?deck='.$deck->getId());

        $first = $crawler->filter('[data-game-tracker-target="playerRow"]')->first();
        self::assertSame('Nissa', $first->filter('[data-field="name"]')->attr('value'));
        self::assertSame('Wilson, Refined Grizzly', $first->filter('[data-field="commander"]')->attr('value'));
    }

    public function testGameTrackerIgnoresADeckTheVisitorCannotSee(): void
    {
        $deck = $this->persistDeck(Format::Commander, public: false);

        $crawler = $this->client->request('GET', '/partie?deck='.$deck->getId());

        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->filter('[data-field="commander"]')->first()->attr('value'), 'le commandant d\'un deck privé ne fuite pas');
    }

    public function testCommanderArtsForThePlayerPanels(): void
    {
        $this->client->request('GET', '/partie/illustrations', ['noms' => ['Wilson, Refined Grizzly', 'Inconnu', '']]);

        self::assertResponseIsSuccessful();
        $arts = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['Wilson, Refined Grizzly', 'Inconnu'], array_keys($arts));
        self::assertStringContainsString('art_crop', (string) $arts['Wilson, Refined Grizzly']);
        self::assertNull($arts['Inconnu']);
    }

    private function persistDeck(Format $format, bool $public = true): Deck
    {
        $cards = Format::Commander === $format
            ? ['Wilson, Refined Grizzly' => [1, DeckZone::Commander], 'Sol Ring' => 1, 'Forest' => 97, 'Counterspell' => [1, DeckZone::Maybe]]
            : ['Lightning Bolt' => 4, 'Forest' => 56];
        $deck = self::buildDeck($format, $cards, $this->owner)->setName('Test')->setPublic($public);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($deck);
        $em->flush();

        return $deck;
    }
}
