<?php

namespace App\Tests\Controller;

use App\Entity\PreconDeck;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\DeckRepository;
use App\Repository\PreconDeckRepository;
use App\Tests\Deck\DeckTestTrait;
use App\Tests\Precon\PreconTestTrait;
use App\Twig\Components\PreconBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class PreconDeckControllerTest extends WebTestCase
{
    use DeckTestTrait;
    use InteractsWithLiveComponents;
    use PreconTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::importDeckFixtures();
        $this->importPrecons();
    }

    public function testNewDeckPageLinksToThePrecons(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/decks/nouveau');

        self::assertSelectorExists('a[href="/decks/preconstruits"]');
    }

    public function testListsPreconsFromNewestToOldest(): void
    {
        $crawler = $this->client->request('GET', '/decks/preconstruits');

        self::assertResponseIsSuccessful();
        self::assertSame(['Party Time', 'Breed Lethality'], $crawler->filter('ul a p.font-display')->each(fn ($p) => $p->text()));
        self::assertSelectorTextContains('main', 'Thrasios, Triton Hero & Tymna the Weaver');
    }

    public function testSearchByDeckCommanderOrSet(): void
    {
        $component = $this->createLiveComponent(PreconBrowser::class, client: $this->client);

        foreach (['lethality' => 'Breed Lethality', 'wilson' => 'Party Time', 'clb' => 'Party Time'] as $query => $expected) {
            $html = $component->set('q', $query)->render()->toString();
            self::assertStringContainsString($expected, $html, $query);
            self::assertSame(1, substr_count($html, 'href="/decks/preconstruits/'), $query);
        }

        self::assertStringContainsString('Aucun deck préconstruit', $component->set('q', 'zzz')->render()->toString());
    }

    public function testDecksAreGroupedByYear(): void
    {
        $crawler = $this->client->request('GET', '/decks/preconstruits');

        self::assertSame(['2022 (1)', '2016 (1)'], $crawler->filter('section h2')->each(fn ($h) => $h->text()));
        self::assertSame(['Toutes les années', '2022', '2016'], $crawler->filter('select[data-model="year"] option')->each(fn ($o) => $o->text()));
    }

    public function testFilterByYear(): void
    {
        $component = $this->createLiveComponent(PreconBrowser::class, client: $this->client);

        $html = $component->set('year', 2016)->render()->toString();
        self::assertStringContainsString('Breed Lethality', $html);
        self::assertStringNotContainsString('Party Time', $html);

        self::assertStringContainsString('Aucun deck préconstruit', $component->set('q', 'wilson')->render()->toString(), 'année et recherche se combinent');

        // L'année est reflétée dans l'URL
        $this->client->request('GET', '/decks/preconstruits?year=2022');
        self::assertSelectorTextContains('section h2', '2022');
        self::assertSelectorNotExists('a[href="/decks/preconstruits/'.$this->precon('BreedLethality_C16')->getId().'"]');
    }

    public function testShowListsTheCards(): void
    {
        $crawler = $this->client->request('GET', '/decks/preconstruits/'.$this->precon('BreedLethality_C16')->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Breed Lethality');
        self::assertSelectorTextContains('main', 'Commandants (2)');
        self::assertSelectorTextContains('main', 'Deck (97)');
        self::assertSelectorTextContains('main', '1 carte de ce deck n\'est pas encore dans notre base');
        self::assertSelectorTextContains('main', '×96');
        self::assertCount(4, $crawler->filter('main a[data-turbo-frame="card_modal"]'));
        self::assertSelectorTextContains('main header', 'Connecte-toi pour l\'ajouter');
    }

    public function testAddingAPreconRequiresLogin(): void
    {
        $this->client->request('POST', '/decks/preconstruits/'.$this->precon('PartyTime_CLB')->getId().'/ajouter');

        self::assertResponseRedirects('/connexion');
    }

    public function testAddCopiesThePreconIntoTheUserDecks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $precon = $this->precon('PartyTime_CLB');

        $this->client->request('GET', '/decks/preconstruits/'.$precon->getId());
        $this->client->submitForm('+ Ajouter à mes decks');

        $deck = self::getContainer()->get(DeckRepository::class)->findOneBy(['owner' => $user]);
        self::assertResponseRedirects('/decks/'.$deck->getId().'/modifier');
        self::assertSame('Party Time', $deck->getName());
        self::assertSame(Format::Commander, $deck->getFormat());
        self::assertSame($precon->getId(), $deck->getBasedOn()?->getId());
        self::assertSame(100, $deck->getCardCount());
        $commander = $deck->getCardsIn(DeckZone::Commander);
        self::assertSame('Wilson, Refined Grizzly', $commander[0]->getOracleCard()->getName());
        self::assertSame(self::WILSON, $commander[0]->getPrinting()?->getId()->toRfc4122(), 'l\'impression du préconstruit est conservée');

        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '« Party Time » a été ajouté à tes decks.');

        $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertSelectorTextContains('main header', 'Basé sur le préconstruit Party Time (2022)');
    }

    public function testAddRequiresCsrfToken(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/decks/preconstruits/'.$this->precon('PartyTime_CLB')->getId().'/ajouter', ['_token' => 'invalide']);

        // Jeton invalide : refus géré par le pare-feu (redirection), aucun deck n'est créé
        self::assertResponseRedirects();
        self::assertNull(self::getContainer()->get(DeckRepository::class)->findOneBy(['owner' => $user]));
    }

    private function precon(string $code): PreconDeck
    {
        return self::getContainer()->get(PreconDeckRepository::class)->findOneBy(['code' => $code]);
    }

    private function createUser(): User
    {
        $user = (new User())->setUsername('Nissa')->setEmail('nissa@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
