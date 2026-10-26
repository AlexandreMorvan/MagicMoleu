<?php

namespace App\Controller\Admin;

use App\Scryfall\BulkDataDownloader;
use App\Scryfall\SyncLauncher;
use App\Scryfall\SyncTask;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lancement manuel des synchronisations Scryfall (en plus de la planification de src/Schedule.php).
 */
#[IsGranted('ROLE_ADMIN')]
#[AdminRoute('/scryfall', name: 'scryfall')]
class ScryfallSyncController extends AbstractController
{
    public function __construct(
        private readonly SyncLauncher $launcher,
        private readonly BulkDataDownloader $bulkData,
    ) {
    }

    #[AdminRoute('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/scryfall_sync.html.twig', [
            'tasks' => SyncTask::cases(),
            'running' => $this->launcher->isImportRunning(),
            'imported' => [
                SyncTask::Cards->value => $this->bulkData->lastImportedVersion('default-cards'),
                SyncTask::Localized->value => $this->bulkData->lastImportedVersion('all-cards-fr'),
            ],
        ]);
    }

    #[AdminRoute('/{task}', name: 'launch', options: ['methods' => ['POST'], 'requirements' => ['task' => new EnumRequirement(SyncTask::class)]])]
    #[IsCsrfTokenValid('scryfall-sync')]
    public function launch(SyncTask $task, #[MapRequestPayload] SyncOptions $options = new SyncOptions()): Response
    {
        // import-sets ne prend pas le verrou : il peut tourner pendant un import bulk
        if (SyncTask::Sets !== $task && $this->launcher->isImportRunning()) {
            $this->addFlash('warning', 'Un import Scryfall est déjà en cours, réessayez quand il sera terminé.');
        } else {
            $this->launcher->launch($task, $options->force);
            $this->addFlash('success', \sprintf('Synchronisation « %s » lancée en arrière-plan.', $task->label()));
        }

        return $this->redirectToRoute('admin_scryfall_index');
    }
}
