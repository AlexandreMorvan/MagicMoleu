<?php

namespace App\Tests\Collection;

use App\Collection\CollectionStats;
use App\Entity\CardCollection;
use App\Entity\CardPrinting;
use App\Entity\Deck;
use App\Entity\User;
use App\Enum\ColorGroup;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Enum\Rarity;
use App\Repository\CardCollectionRepository;
use App\Repository\CardPrintingRepository;
use App\Tests\Deck\DeckTestTrait;
use App\Twig\Components\CollectionAdder;
use App\Twig\Components\CollectionBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class CollectionTest extends WebTestCase
{
    use DeckTestTrait;
    use InteractsWithLiveComponents;

    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::importDeckFixtures();
        $this->owner = $this->createUser('Liliana');
    }

    public function testCollectionsRequireLogin(): void
    {
        $this->client->request('GET', '/collections');

        self::assertResponseRedirects('/connexion');
    }

    public function testCreateCollectionAndBrowseIt(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/collections');
        self::assertSelectorTextContains('nav', 'Collection');

        $this->client->clickLink('+ Nouvelle collection');
        $this->client->submitForm('Créer la collection', ['card_collection[name]' => 'Classeur rouge']);

        $collection = self::getContainer()->get(CardCollectionRepository::class)->findOneBy(['name' => 'Classeur rouge']);
        self::assertResponseRedirects('/collections/'.$collection->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Classeur rouge');
        self::assertSelectorTextContains('main', 'Cette collection est vide');
    }

    public function testCollectionIsPrivate(): void
    {
        $collection = $this->persistCollection(['Lightning Bolt' => 1]);

        $this->client->loginUser($this->createUser('Intrus'));
        $this->client->request('GET', '/collections/'.$collection->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCardModalOffersAddingToACollection(): void
    {
        $bolt = self::oracle('Lightning Bolt');
        $version = self::getContainer()->getParameter('app.build_version');
        $this->client->request('GET', '/cartes/'.$bolt->getId().'?v='.$version, server: ['HTTP_TURBO_FRAME' => 'card_modal']);

        self::assertResponseIsSuccessful();
        // Rendu différé : la fiche, en cache navigateur, ne contient rien de propre à l'utilisateur
        self::assertSelectorExists('[data-live-name-value="CollectionAdder"]');
        self::assertResponseHeaderSame('Cache-Control', 'max-age=86400, private');
    }

    public function testAnonymousVisitorIsInvitedToLogIn(): void
    {
        $html = $this->createLiveComponent(CollectionAdder::class, ['printingId' => (string) $this->printing('Lightning Bolt')->getId()], $this->client)
            ->render()->toString();

        self::assertStringContainsString('Connecte-toi', $html);
    }

    public function testAddPrintingFromCardModal(): void
    {
        $this->client->loginUser($this->owner);
        $bolt = $this->printing('Lightning Bolt');
        $component = $this->createLiveComponent(CollectionAdder::class, ['printingId' => (string) $bolt->getId()], $this->client)
            ->actingAs($this->owner);

        // Sans collection, la première est créée à la volée
        self::assertStringContainsString('Nouvelle collection', $component->render()->toString());
        $component->set('newName', 'Brûlure')->set('quantity', 3)->call('add');
        $component->set('foil', true)->set('quantity', 1)->call('add');
        $component->set('foil', false)->set('quantity', 1)->call('add');

        $html = $component->render()->toString();
        self::assertStringContainsString('ajouté à « Brûlure »', $html);
        self::assertStringContainsString('Déjà possédée', $html);
        self::assertStringContainsString('5 × Brûlure', $html);

        $collection = $this->reload(self::getContainer()->get(CardCollectionRepository::class)->findOneBy(['name' => 'Brûlure']));
        self::assertSame(5, $collection->getCardCount());
        self::assertSame(4, $collection->findCard($this->printing('Lightning Bolt'), false)->getQuantity());
        self::assertSame(1, $collection->findCard($this->printing('Lightning Bolt'), true)->getQuantity());
    }

    public function testFoilOnlyPrintingIsAlwaysAddedAsFoil(): void
    {
        $collection = $this->persistCollection([]);
        $this->client->loginUser($this->owner);
        $component = $this->createLiveComponent(CollectionAdder::class, ['printingId' => (string) $this->printing('Thrasios, Triton Hero')->getId()], $this->client)
            ->actingAs($this->owner);

        self::assertStringContainsString('Foil uniquement', $component->render()->toString());
        $component->call('add');

        $collection = $this->reload($collection);
        self::assertTrue($collection->getCards()->first()->isFoil());
        self::assertSame(19.34, $collection->getCards()->first()->getUnitPrice());
    }

    public function testCannotAddToSomeoneElsesCollection(): void
    {
        $collection = $this->persistCollection([]);
        $intruder = $this->createUser('Intrus');
        $this->client->loginUser($intruder);
        $component = $this->createLiveComponent(CollectionAdder::class, ['printingId' => (string) $this->printing('Lightning Bolt')->getId()], $this->client)
            ->actingAs($intruder);

        $this->client->catchExceptions(true);
        $component->set('collectionId', $collection->getId())->call('add');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->reload($collection)->getCardCount());
    }

    public function testAddWholeDeckToANewCollection(): void
    {
        $deck = self::buildDeck(Format::Commander, [
            'Thrasios, Triton Hero' => [1, DeckZone::Commander],
            'Forest' => 30,
            'Counterspell' => [1, DeckZone::Maybe],
        ], $this->owner)->setName('Simic');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($deck);
        $em->flush();

        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/decks/'.$deck->getId());
        $this->client->submitForm('Ajouter les 31 cartes', ['collection' => '']);

        $collection = self::getContainer()->get(CardCollectionRepository::class)->findOneBy(['name' => 'Simic']);
        self::assertResponseRedirects('/collections/'.$collection->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '31 cartes ajoutées à la collection « Simic »');

        $collection = $this->reload($collection);
        self::assertSame(31, $collection->getCardCount(), 'les « peut-être » ne sont pas ajoutés');
        self::assertTrue($collection->findCard($this->printing('Thrasios, Triton Hero'), true)?->isFoil());

        // Une seconde fois dans la même collection : les quantités se cumulent
        $this->client->request('GET', '/decks/'.$deck->getId());
        $this->client->submitForm('Ajouter les 31 cartes', ['collection' => (string) $collection->getId()]);
        self::assertSame(62, $this->reload($collection)->getCardCount());
    }

    public function testCannotAddDeckWithoutCsrfToken(): void
    {
        $deck = self::buildDeck(Format::Modern, ['Lightning Bolt' => 4], $this->owner)->setName('Burn');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($deck);
        $em->flush();

        $this->client->loginUser($this->owner);
        $this->client->request('POST', '/collections/ajouter-deck/'.$deck->getId(), ['_token' => 'invalide']);

        self::assertNull(self::getContainer()->get(CardCollectionRepository::class)->findOneBy(['name' => 'Burn']));
    }

    public function testBrowserFiltersViewsAndQuantities(): void
    {
        $collection = $this->persistCollection(['Lightning Bolt' => 4, 'Counterspell' => 2, 'Jace, the Mind Sculptor' => 1]);
        $this->client->loginUser($this->owner);
        $component = $this->createLiveComponent(CollectionBrowser::class, ['collectionId' => $collection->getId()], $this->client)
            ->actingAs($this->owner);

        $html = $component->render()->toString();
        self::assertStringContainsString('Jace, the Mind Sculptor', $html);
        self::assertStringContainsString('Cartes les plus chères', $html);

        $html = $component->set('color', 'U')->set('rarity', 'uncommon')->render()->toString();
        self::assertStringContainsString('Counterspell', $html);
        self::assertStringNotContainsString('alt="Lightning Bolt"', $html);
        self::assertStringNotContainsString('alt="Jace, the Mind Sculptor"', $html);

        $component->set('color', '')->set('rarity', '')->call('changeView', ['view' => 'list']);
        self::assertStringContainsString('<table', $component->render()->toString());

        $bolt = $this->reload($collection)->findCard($this->printing('Lightning Bolt'), false);
        $component->call('changeQuantity', ['lineId' => $bolt->getId(), 'delta' => -1]);
        self::assertSame(3, $this->reload($collection)->findCard($this->printing('Lightning Bolt'), false)->getQuantity());

        $component->call('remove', ['lineId' => $bolt->getId()]);
        self::assertSame(3, $this->reload($collection)->getCardCount());
    }

    public function testBrowserGroupsBySetAndPriceRange(): void
    {
        $collection = $this->persistCollection(['Lightning Bolt' => 4, 'Counterspell' => 2, 'Jace, the Mind Sculptor' => 1, 'Forest' => 10]);
        $this->client->loginUser($this->owner);
        $component = $this->createLiveComponent(CollectionBrowser::class, ['collectionId' => $collection->getId()], $this->client)
            ->actingAs($this->owner);
        $headers = static fn ($crawler) => $crawler->filter('section > div.border-b')->each(static fn ($h) => trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $h->text())));

        // Par édition (de la plus récente à la plus ancienne ; sans date de sortie dans les données de test, par nom),
        // avec nombre de cartes et valeur
        self::assertSame([
            'Duskmourn: House of Horror Commander DSC 2 cartes · 8,14 €',
            'Foundations FDN 10 cartes · 1,20 €',
            'Magic 2010 M10 4 cartes · 4,68 €',
            'Masters 25 A25 1 carte · 16,10 €',
        ], $headers($component->set('sort', 'set')->render()->crawler()));

        $ranges = $headers($component->set('sort', 'price')->render()->crawler());
        self::assertSame(['10 € – 20 € 1 carte · 16,10 €', '2 € – 5 € 2 cartes · 8,14 €', '1 € – 2 € 4 cartes · 4,68 €', 'Moins de 0,50 € 10 cartes · 1,20 €'], $ranges);

        // En liste, les en-têtes sont des lignes du tableau
        $component->call('changeView', ['view' => 'list']);
        self::assertCount(4, $component->render()->crawler()->filter('tbody th[scope="rowgroup"]'));

        // Classement par nom : pas d'en-tête
        self::assertCount(0, $component->set('sort', 'name')->render()->crawler()->filter('tbody th[scope="rowgroup"]'));
    }

    public function testLargeCollectionIsPaginated(): void
    {
        $collection = $this->persistCollection(['Lightning Bolt' => 1]);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $bolt = $this->printing('Lightning Bolt');
        // 130 lignes distinctes : même impression, langues différentes
        foreach (range(1, 129) as $i) {
            $collection->addCard($bolt, 1, false, 'x'.$i);
        }
        $em->flush();

        $this->client->loginUser($this->owner);
        $component = $this->createLiveComponent(CollectionBrowser::class, ['collectionId' => $collection->getId()], $this->client)
            ->actingAs($this->owner);

        $crawler = $component->render()->crawler();
        self::assertCount(120, $crawler->filter('li.group'));
        self::assertStringContainsString('120 sur 130 lignes', $crawler->filter('button[data-live-action-param="more"]')->text());

        $crawler = $component->call('more')->render()->crawler();
        self::assertCount(130, $crawler->filter('li.group'));
        self::assertCount(0, $crawler->filter('button[data-live-action-param="more"]'));
    }

    public function testDeleteCollectionFromItsPage(): void
    {
        $collection = $this->persistCollection(['Lightning Bolt' => 4]);
        $id = $collection->getId();
        $this->client->loginUser($this->owner);

        $crawler = $this->client->request('GET', '/collections/'.$id);
        $form = $crawler->filter('header form[action$="/supprimer"]');
        // La confirmation (SweetAlert, côté navigateur) est déclenchée par data-turbo-confirm
        self::assertSame('Supprimer la collection « Classeur » ?', $form->attr('data-turbo-confirm'));
        self::assertStringContainsString('4 cartes seront retirées avec elle', $form->attr('data-confirm-text'));

        $this->client->submit($form->form());
        self::assertResponseRedirects('/collections');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'La collection « Classeur » a été supprimée.');
        self::assertNull(self::getContainer()->get(CardCollectionRepository::class)->find($id));
    }

    public function testCannotDeleteSomeoneElsesCollection(): void
    {
        $collection = $this->persistCollection([]);
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/collections/'.$collection->getId());
        $token = $crawler->filter('header form[action$="/supprimer"] input[name="_token"]')->attr('value');

        $this->client->loginUser($this->createUser('Intrus'));
        $this->client->request('POST', '/collections/'.$collection->getId().'/supprimer', ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(self::getContainer()->get(CardCollectionRepository::class)->find($collection->getId()));
    }

    public function testStats(): void
    {
        $collection = $this->persistCollection(['Lightning Bolt' => 4, 'Counterspell' => 2, 'Jace, the Mind Sculptor' => 1, 'Forest' => 10]);
        $collection->addCard($this->printing('Lightning Bolt'), 1, foil: true);

        $stats = CollectionStats::fromCollection($collection);

        self::assertSame(18, $stats->cardCount);
        self::assertSame(4, $stats->uniqueCount);
        self::assertSame(1, $stats->foilCount);
        // 4 × 1,17 + 5,47 (foil) + 2 × 4,07 + 16,10 + 10 × 0,12
        self::assertSame(35.59, $stats->value);
        self::assertSame(15, $stats->rarities[Rarity::Common->value]['count']);
        self::assertSame(1, $stats->rarities[Rarity::Mythic->value]['count']);
        self::assertSame(5, $stats->colors[ColorGroup::Red->value]['count']);
        self::assertSame(10, $stats->colors[ColorGroup::Land->value]['count']);
        self::assertSame('Jace, the Mind Sculptor', $stats->mostValuable[0]->getOracleCard()->getName());
    }

    /**
     * @param array<string, int> $cards nom => quantité (impression par défaut, non foil)
     */
    private function persistCollection(array $cards): CardCollection
    {
        $collection = (new CardCollection($this->owner))->setName('Classeur');
        foreach ($cards as $name => $quantity) {
            $collection->addCard($this->printing($name), $quantity);
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($collection);
        $em->flush();

        return $collection;
    }

    private function printing(string $name): CardPrinting
    {
        return self::getContainer()->get(CardPrintingRepository::class)->findDefaultFor(self::oracle($name));
    }

    private function reload(CardCollection $collection): CardCollection
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(CardCollectionRepository::class)->findWithCards($collection->getId());
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
