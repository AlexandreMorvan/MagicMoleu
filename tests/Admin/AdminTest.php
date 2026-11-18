<?php

namespace App\Tests\Admin;

use App\Controller\Admin\DeckCrudController;
use App\Controller\Admin\MtgSetCrudController;
use App\Controller\Admin\NotificationCrudController;
use App\Controller\Admin\OracleCardCrudController;
use App\Controller\Admin\UserAchievementCrudController;
use App\Controller\Admin\UserCrudController;
use App\Entity\MtgSet;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Scryfall\SetCoverUpdater;
use App\Set\SetLogoStorage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $admin;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
        $this->admin = $this->createUser('Urza', ['ROLE_ADMIN']);
    }

    public function testRegularUsersCannotAccessAdmin(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/connexion');

        $this->client->loginUser($this->createUser('Mishra'));
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDashboard(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Cartes uniques');
        self::assertSelectorTextContains('body', 'LE GRENIER À MANA');
    }

    #[DataProvider('crudControllers')]
    public function testCrudIndexPages(string $controller): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', $this->adminUrl($controller));

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function crudControllers(): iterable
    {
        foreach ([UserCrudController::class, DeckCrudController::class, UserAchievementCrudController::class, NotificationCrudController::class, OracleCardCrudController::class, MtgSetCrudController::class] as $controller) {
            yield (new \ReflectionClass($controller))->getShortName() => [$controller];
        }
    }

    public function testCardDetailIsReadOnly(): void
    {
        $this->client->loginUser($this->admin);
        $card = self::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\OracleCard::class)->findOneBy(['name' => 'Lightning Bolt']);

        $this->client->request('GET', $this->adminUrl(OracleCardCrudController::class, 'detail', (string) $card->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Lightning Bolt deals 3 damage');
        self::assertSelectorNotExists('a.action-edit');
    }

    public function testManualSetCoverIsLockedAgainstAutomaticUpdate(): void
    {
        $this->client->loginUser($this->admin);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $set = $em->getRepository(MtgSet::class)->findOneBy(['code' => 'm10']);

        $this->client->request('GET', $this->adminUrl(MtgSetCrudController::class, 'edit', (string) $set->getId()));
        $this->client->submitForm('Sauvegarder les modifications', [
            'MtgSet[coverArtUri]' => 'https://cards.scryfall.io/art_crop/front/x/y/custom.jpg',
            'MtgSet[coverCardName]' => 'Mon choix',
        ]);
        self::assertResponseRedirects();

        self::getContainer()->get(SetCoverUpdater::class)->update();
        $em->clear();
        $set = $em->getRepository(MtgSet::class)->findOneBy(['code' => 'm10']);
        self::assertTrue($set->isCoverLocked());
        self::assertSame('Mon choix', $set->getCoverCardName());
    }

    public function testSetLogoFromUrlOrUploadedFile(): void
    {
        $publicDir = sys_get_temp_dir().'/set-logos-'.bin2hex(random_bytes(4));
        $image = imagecreatetruecolor(12, 4);
        ob_start();
        imagepng($image);
        $storage = new SetLogoStorage(new MockHttpClient(new MockResponse(ob_get_clean())), new Filesystem(), $publicDir);

        $this->client->disableReboot();
        self::getContainer()->set(SetLogoStorage::class, $storage);
        $this->client->loginUser($this->admin);
        $set = self::getContainer()->get(EntityManagerInterface::class)->getRepository(MtgSet::class)->findOneBy(['code' => 'm10']);

        $this->client->request('GET', $this->adminUrl(MtgSetCrudController::class, 'edit', (string) $set->getId()));
        $this->client->submitForm('Sauvegarder les modifications', ['MtgSet[logoSourceUrl]' => 'https://8.8.8.8/m10-logo.png']);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/editions/m10');
        $logo = $crawler->filter('h1 img');
        self::assertSame('Magic 2010', $logo->attr('alt'));
        self::assertMatchesRegularExpression('#/uploads/sets/m10-[0-9a-f]{10}\.png$#', $logo->attr('src'));

        // Une URL refusée garde le logo actuel et prévient l'administrateur
        $this->client->request('GET', $this->adminUrl(MtgSetCrudController::class, 'edit', (string) $set->getId()));
        $this->client->submitForm('Sauvegarder les modifications', ['MtgSet[logoSourceUrl]' => 'http://8.8.8.8/autre.png']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Logo non enregistré');
        self::assertSelectorExists('img[src*="/uploads/sets/m10-"]');

        // Envoi d'un fichier (enregistré depuis un site protégé contre les robots, par exemple)
        $file = tempnam(sys_get_temp_dir(), 'logo');
        $image = imagecreatetruecolor(30, 8);
        imagepng($image, $file);
        $this->client->request('GET', $this->adminUrl(MtgSetCrudController::class, 'edit', (string) $set->getId()));
        $form = $this->client->getCrawler()->selectButton('Sauvegarder les modifications')->form();
        $field = $form['MtgSet[logoFile]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($file);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->request('GET', '/editions/m10');
        self::assertSelectorExists('h1 img[src*="/uploads/sets/m10-"]');
        $uploaded = $this->client->getCrawler()->filter('h1 img')->attr('src');
        self::assertNotSame($logo->attr('src'), $uploaded, 'le fichier envoyé remplace le logo téléchargé');

        // Un fichier qui n'est pas une image est refusé par le formulaire
        file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->client->request('GET', $this->adminUrl(MtgSetCrudController::class, 'edit', (string) $set->getId()));
        $form = $this->client->getCrawler()->selectButton('Sauvegarder les modifications')->form();
        $field = $form['MtgSet[logoFile]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($file);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Envoie une image PNG, WebP ou JPEG.');

        // La case « Supprimer le logo »
        $this->client->request('GET', $this->adminUrl(MtgSetCrudController::class, 'edit', (string) $set->getId()));
        $this->client->submitForm('Sauvegarder les modifications', ['MtgSet[removeLogo]' => '1']);
        $this->client->request('GET', '/editions/m10');
        self::assertSelectorNotExists('h1 img');
        self::assertSelectorTextContains('h1', 'Magic 2010');

        unlink($file);

        (new Filesystem())->remove($publicDir);
    }

    public function testAdminCanSendNotification(): void
    {
        $this->client->loginUser($this->admin);
        $recipient = $this->createUser('Karn');

        $crawler = $this->client->request('GET', $this->adminUrl(NotificationCrudController::class, 'new'));
        // Le champ destinataire est en autocomplétion : ses options sont chargées en Ajax, d'où disableValidation()
        $form = $crawler->selectButton('Créer')->form()->disableValidation();
        $this->client->submit($form, [
            'Notification[user][autocomplete]' => $recipient->getId(),
            'Notification[title]' => 'Tournoi samedi',
            'Notification[message]' => 'Inscriptions ouvertes !',
        ]);

        self::assertResponseRedirects();
        self::assertSame(1, self::getContainer()->get(NotificationRepository::class)->countUnreadFor($recipient));
    }

    public function testAdminCanLaunchScryfallSync(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin');
        $this->client->clickLink('Synchronisation');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Synchroniser');
        self::assertResponseRedirects('/admin/scryfall');

        // À lire avant la requête suivante, qui redémarre le noyau et vide le transport en mémoire
        $sent = $this->asyncTransport()->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(RunCommandMessage::class, $sent[0]->getMessage());
        // Premier formulaire de la page : les spoilers
        self::assertSame('app:scryfall:sync-spoilers', $sent[0]->getMessage()->input);

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'lancée en arrière-plan');
    }

    public function testForcedBulkImportIsQueuedWithForceOption(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/scryfall');
        $form = $crawler->filter('form[action$="/cards"]')->form(['force' => '1']);
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertSame('app:scryfall:import-cards --force', $this->asyncTransport()->getSent()[0]->getMessage()->input);
    }

    public function testScryfallSyncIsRefusedWhileAnImportIsRunning(): void
    {
        $lock = self::getContainer()->get(LockFactory::class)->createLock('scryfall-import');
        $lock->acquire();

        try {
            $this->client->loginUser($this->admin);
            $crawler = $this->client->request('GET', '/admin/scryfall');
            self::assertSelectorTextContains('.alert-info', 'import Scryfall est en cours');

            $this->client->submit($crawler->filter('form[action$="/cards"]')->form());
            self::assertCount(0, $this->asyncTransport()->getSent());

            $this->client->followRedirect();
            self::assertSelectorTextContains('body', 'déjà en cours');
        } finally {
            $lock->release();
        }
    }

    public function testScryfallSyncRequiresCsrfToken(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('POST', '/admin/scryfall/cards', ['_token' => 'invalide']);

        // Jeton invalide : refus géré par le pare-feu (redirection), rien n'est envoyé au worker
        self::assertResponseRedirects();
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport, 'transport « async » en mémoire en environnement de test');

        return $transport;
    }

    private function adminUrl(string $controller, string $action = 'index', ?string $entityId = null): string
    {
        $generator = self::getContainer()->get(AdminUrlGenerator::class)->setController($controller)->setAction($action);
        if ($entityId) {
            $generator->setEntityId($entityId);
        }

        return $generator->generateUrl();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, array $roles = []): User
    {
        $user = (new User())->setUsername($username)->setEmail(strtolower($username).'@example.com')->setPassword('x')->setRoles($roles);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
