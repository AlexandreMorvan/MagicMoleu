<?php

namespace App\Tests\Collection;

use App\Collection\CsvImportException;
use App\Collection\ManaboxCsvImporter;
use App\Entity\CardCollection;
use App\Entity\User;
use App\Enum\CardCondition;
use App\Repository\CardCollectionRepository;
use App\Repository\CardPrintingRepository;
use App\Tests\Deck\DeckTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CollectionImportTest extends WebTestCase
{
    use DeckTestTrait;

    private const string CSV = __DIR__.'/../fixtures/manabox_collection.csv';

    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::importDeckFixtures();
        $this->owner = (new User())->setUsername('Teferi')->setEmail('teferi@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($this->owner);
        $em->flush();
    }

    public function testImportsManaboxExport(): void
    {
        $collection = $this->persistCollection();

        $result = self::getContainer()->get(ManaboxCsvImporter::class)->import($collection, self::CSV);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame(6, $result->lines);
        self::assertSame(10, $result->copies);
        self::assertSame(1, $result->proxies, 'les proxies ne sont pas importés');
        self::assertSame(['ligne 7 : Red Elemental Blast (FWB 171)', 'ligne 9 : Carte Imaginaire (XXX 1)'], $result->notFound);
        self::assertCount(2, $result->approximated, 'Counterspell retrouvé par édition et numéro, Sol Ring par son nom');
        self::assertStringStartsWith('Counterspell (DSC 114) → ', $result->approximated[0]);

        $collection = $this->reload($collection);
        $bolt = $this->printing('Lightning Bolt');

        // Deux lignes identiques (français, near mint) cumulées, prix d'achat moyen pondéré, date la plus ancienne
        $french = $collection->findCard($bolt, false, 'fr');
        self::assertSame(4, $french->getQuantity());
        self::assertSame(1.5, $french->getPurchasePrice());
        self::assertSame('EUR', $french->getPurchaseCurrency());
        self::assertSame('2024-01-21', $french->getAddedAt()->format('Y-m-d'));

        $foil = $collection->findCard($bolt, true, 'en', CardCondition::Excellent);
        self::assertSame(1, $foil->getQuantity());

        $jace = $collection->findCard($this->printing('Jace, the Mind Sculptor'), false, 'ja', CardCondition::Played);
        self::assertSame(12.0, $jace->getPurchasePrice());
        self::assertNotNull($collection->findCard($this->printing('Sol Ring'), false, 'de', CardCondition::Good));
        self::assertSame(10, $collection->getCardCount());
    }

    public function testRejectsFilesThatAreNotManaboxExports(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, "Card,Count\nLightning Bolt,4\n");

        try {
            $this->expectException(CsvImportException::class);
            $this->expectExceptionMessage('colonnes Quantity, Name introuvables');
            self::getContainer()->get(ManaboxCsvImporter::class)->import($this->persistCollection(), $file);
        } finally {
            unlink($file);
        }
    }

    public function testUploadIntoNewCollectionThenReplace(): void
    {
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/collections');
        $this->client->click($crawler->selectLink('Importer un CSV ManaBox')->link());
        self::assertSelectorTextContains('main', 'ManaBox');

        $form = $this->client->getCrawler()->selectButton('Importer')->form();
        self::upload($form, self::CSV);
        $form['collection_import[name]'] = 'Mon classeur';
        $this->client->submit($form);

        $collection = self::getContainer()->get(CardCollectionRepository::class)->findOneBy(['name' => 'Mon classeur']);
        self::assertResponseRedirects('/collections/'.$collection->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '10 cartes importées (6 lignes) dans « Mon classeur »');
        self::assertSelectorTextContains('main', 'Cartes introuvables, non importées : ligne 7 : Red Elemental Blast (FWB 171)');
        self::assertSelectorTextContains('main', '1 proxy ignoré');
        self::assertSelectorTextContains('aside', 'Plus-value');

        // Réimport du même export dans la même collection, en remplaçant : pas de doublons
        $crawler = $this->client->request('GET', '/collections/importer?collection='.$collection->getId());
        $form = $crawler->selectButton('Importer')->form();
        self::assertSame((string) $collection->getId(), $form['collection_import[collection]']->getValue(), 'collection présélectionnée');
        self::upload($form, self::CSV);
        $replace = $form['collection_import[replace]'];
        self::assertInstanceOf(ChoiceFormField::class, $replace);
        $replace->tick();
        $this->client->submit($form);

        self::assertSame(10, $this->reload($collection)->getCardCount());
    }

    public function testUploadRequiresACsvFile(): void
    {
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/collections/importer');
        $this->client->submit($crawler->selectButton('Importer')->form());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Choisis le fichier CSV à importer.');
    }

    public function testCannotImportIntoSomeoneElsesCollection(): void
    {
        $intruder = (new User())->setUsername('Intrus')->setEmail('intrus@example.com')->setPassword('x');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($intruder);
        $em->flush();
        $collection = $this->persistCollection();

        $this->client->loginUser($intruder);
        $crawler = $this->client->request('GET', '/collections/importer?collection='.$collection->getId());
        $form = $crawler->selectButton('Importer')->form();

        self::assertSame('', $form['collection_import[collection]']->getValue(), 'la collection d\'un autre n\'est pas proposée');
        self::assertStringNotContainsString('Classeur', $this->client->getResponse()->getContent());
    }

    private static function upload(Form $form, string $path): void
    {
        $field = $form['collection_import[file]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($path);
    }

    private function persistCollection(): CardCollection
    {
        $collection = (new CardCollection($this->owner))->setName('Classeur');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($collection);
        $em->flush();

        return $collection;
    }

    private function printing(string $name): \App\Entity\CardPrinting
    {
        return self::getContainer()->get(CardPrintingRepository::class)->findDefaultFor(self::oracle($name));
    }

    private function reload(CardCollection $collection): CardCollection
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(CardCollectionRepository::class)->findWithCards($collection->getId());
    }
}
