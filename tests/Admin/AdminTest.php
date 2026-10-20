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
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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
    }

    #[DataProvider('crudControllers')]
    public function testCrudIndexPages(string $controller): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', $this->adminUrl($controller));

        self::assertResponseIsSuccessful();
    }

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
