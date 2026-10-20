<?php

namespace App\Tests\Controller;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Repository\DeckRepository;
use App\Tests\Deck\DeckTestTrait;
use App\Twig\Components\DeckEditor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class DeckControllerTest extends WebTestCase
{
    use DeckTestTrait;
    use InteractsWithLiveComponents;

    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::importDeckFixtures();
        $this->owner = $this->createUser('Nissa');
    }

    public function testCreateDeckWithImportedList(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/decks/nouveau');
        $this->client->submitForm('Créer le deck', [
            'deck[name]' => 'Simic partners',
            'deck[format]' => 'commander',
            'deck[list]' => "Commander\n1 Thrasios, Triton Hero\n1 Tymna the Weaver\nDeck\n98 Forest\n1 Carte Inconnue",
        ]);

        $deck = self::getContainer()->get(DeckRepository::class)->findOneBy(['name' => 'Simic partners']);
        self::assertResponseRedirects('/decks/'.$deck->getId().'/modifier');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Cartes introuvables : 1 Carte Inconnue');
        self::assertSelectorTextContains('aside', 'Deck valide');
        self::assertSame(100, $deck->countIn(DeckZone::Commander, DeckZone::Main));
    }

    public function testNewDeckRequiresLogin(): void
    {
        $this->client->request('GET', '/decks/nouveau');

        self::assertResponseRedirects('/connexion');
    }

    public function testPrivateDeckIsOnlyVisibleToItsOwner(): void
    {
        $deck = $this->persistDeck(public: false);

        $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertResponseRedirects('/connexion', message: 'un visiteur anonyme est invité à se connecter');

        $this->client->loginUser($this->createUser('Intrus'));
        $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/decks/'.$deck->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Burn');
        self::assertSelectorTextContains('aside', 'Le deck principal doit contenir au moins 60 cartes (4 actuellement).');
    }

    public function testPublicDeckIsListedAndExportable(): void
    {
        $deck = $this->persistDeck(public: true);

        $this->client->request('GET', '/decks');
        self::assertSelectorTextContains('main', 'Burn');

        $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href$="/modifier"]');

        $this->client->request('GET', '/decks/'.$deck->getId().'/export');
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('4 Lightning Bolt', $this->client->getResponse()->getContent());
    }

    public function testEditorActions(): void
    {
        $deck = $this->persistDeck(public: false);
        $this->client->loginUser($this->owner);

        $component = $this->createLiveComponent(DeckEditor::class, ['deckId' => $deck->getId()], $this->client)
            ->actingAs($this->owner);

        $html = $component->set('query', 'counters')->render()->toString();
        self::assertStringContainsString('Counterspell', $html);

        $counterspell = self::oracle('Counterspell');
        $component->call('add', ['cardId' => (string) $counterspell->getId(), 'zone' => 'main']);
        $component->call('add', ['cardId' => (string) $counterspell->getId(), 'zone' => 'main']);

        $deck = $this->reload($deck);
        $line = $deck->findCard(self::oracle('Counterspell'), DeckZone::Main);
        self::assertSame(2, $line->getQuantity());
        self::assertNotNull($line->getPrinting());

        $component->call('changeQuantity', ['deckCardId' => $line->getId(), 'delta' => -1]);
        $component->call('move', ['deckCardId' => $line->getId(), 'zone' => 'side']);
        $deck = $this->reload($deck);
        self::assertNull($deck->findCard(self::oracle('Counterspell'), DeckZone::Main));
        self::assertSame(1, $deck->findCard(self::oracle('Counterspell'), DeckZone::Side)->getQuantity());

        $component->call('remove', ['deckCardId' => $deck->findCard(self::oracle('Counterspell'), DeckZone::Side)->getId()]);
        self::assertSame(4, $this->reload($deck)->countIn(DeckZone::Main, DeckZone::Side));
    }

    public function testEditorActionsAreForbiddenToOtherUsers(): void
    {
        $deck = $this->persistDeck(public: true);
        $this->client->loginUser($this->owner);
        $component = $this->createLiveComponent(DeckEditor::class, ['deckId' => $deck->getId()], $this->client);
        $component->render(); // premier rendu en tant que propriétaire
        $lineId = $deck->findCard(self::oracle('Lightning Bolt'), DeckZone::Main)->getId();

        // Requête live rejouée par un autre utilisateur
        $this->client->catchExceptions(true);
        $component->actingAs($this->createUser('Intrus'));
        $component->call('remove', ['deckCardId' => $lineId]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(4, $this->reload($deck)->countIn(DeckZone::Main));
    }

    public function testDeleteDeck(): void
    {
        $deck = $this->persistDeck(public: false);
        $this->client->loginUser($this->owner);

        $crawler = $this->client->request('GET', '/decks/'.$deck->getId().'/parametres');
        $this->client->submit($crawler->selectButton('Supprimer ce deck')->form());

        self::assertResponseRedirects('/decks');
        self::assertNull(self::getContainer()->get(DeckRepository::class)->find($deck->getId()));
    }

    private function persistDeck(bool $public): Deck
    {
        $deck = self::buildDeck(\App\Enum\Format::Modern, ['Lightning Bolt' => 4], $this->owner)->setName('Burn')->setPublic($public);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($deck);
        $em->flush();

        return $deck;
    }

    private function reload(Deck $deck): Deck
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return self::getContainer()->get(DeckRepository::class)->findWithCards($deck->getId());
    }

    private function createUser(string $username): User
    {
        $user = (new User())->setUsername($username)->setEmail(strtolower($username).'@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
