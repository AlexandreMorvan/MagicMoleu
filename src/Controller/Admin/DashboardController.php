<?php

namespace App\Controller\Admin;

use App\Entity\CardPrinting;
use App\Entity\Deck;
use App\Entity\MtgSet;
use App\Entity\Notification;
use App\Entity\OracleCard;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\DeckRepository;
use App\Repository\UserRepository;
use App\Scryfall\BulkDataDownloader;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly DeckRepository $decks,
        private readonly BulkDataDownloader $bulkData,
        #[Autowire(param: 'app.site_name')]
        private readonly string $siteName,
    ) {
    }

    public function index(): Response
    {
        $count = $this->count(...);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                ['label' => 'Utilisateurs', 'value' => $count(User::class), 'icon' => 'fa fa-users'],
                ['label' => 'Decks', 'value' => $count(Deck::class), 'icon' => 'fa fa-layer-group', 'detail' => $count(Deck::class, ['public' => true]).' publics'],
                ['label' => 'Succès débloqués', 'value' => $count(UserAchievement::class), 'icon' => 'fa fa-trophy'],
                ['label' => 'Cartes uniques', 'value' => $count(OracleCard::class), 'icon' => 'fa fa-clone', 'detail' => $count(CardPrinting::class).' impressions'],
                ['label' => 'Éditions', 'value' => $count(MtgSet::class), 'icon' => 'fa fa-box-archive'],
                ['label' => 'Notifications non lues', 'value' => $count(Notification::class, ['readAt' => null]), 'icon' => 'fa fa-bell'],
            ],
            'imports' => [
                'Cartes (default-cards)' => $this->bulkData->lastImportedVersion('default-cards'),
                'Traductions FR (all-cards)' => $this->bulkData->lastImportedVersion('all-cards-fr'),
            ],
            'latest_users' => $this->users->findBy([], ['createdAt' => 'DESC'], 5),
            'latest_decks' => $this->decks->findBy([], ['updatedAt' => 'DESC'], 5),
        ]);
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $criteria
     */
    private function count(string $class, array $criteria = []): int
    {
        return $this->em->getRepository($class)->count($criteria);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle(\sprintf('<span style="font-weight:700;letter-spacing:.15em">%s</span> <small style="opacity:.6">admin</small>', htmlspecialchars(mb_strtoupper($this->siteName))))
            ->setDefaultColorScheme('dark')
            ->setLocales(['fr']);
    }

    public function configureAssets(): Assets
    {
        // Le back-office n'utilise pas le CSS du site : taille des symboles de mana (filtre mana_symbols)
        return Assets::new()->addHtmlContentToHead(
            '<style>.mana-symbol{display:inline-block;width:1.15em;height:1.15em;vertical-align:-.2em;margin:0 1px}</style>',
        );
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-gauge');

        yield MenuItem::section('Communauté');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');
        yield MenuItem::linkTo(DeckCrudController::class, 'Decks', 'fa fa-layer-group');
        yield MenuItem::linkTo(UserAchievementCrudController::class, 'Succès débloqués', 'fa fa-trophy');
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');

        yield MenuItem::section('Données Scryfall');
        yield MenuItem::linkTo(OracleCardCrudController::class, 'Cartes', 'fa fa-clone');
        yield MenuItem::linkTo(MtgSetCrudController::class, 'Éditions', 'fa fa-box-archive');
        yield MenuItem::linkToRoute('Synchronisation', 'fa fa-rotate', 'admin_scryfall_index');

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-arrow-left', 'app_home');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-right-from-bracket');
    }
}
