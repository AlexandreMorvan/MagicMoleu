<?php

namespace App\Controller;

use App\Achievement\AchievementChecker;
use App\Collection\OwnershipChecker;
use App\Entity\PreconDeck;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Precon\PreconDeckCopier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogue des decks préconstruits, et copie d'un préconstruit dans les decks de l'utilisateur.
 */
#[Route('/decks/preconstruits')]
class PreconDeckController extends AbstractController
{
    /**
     * La recherche est gérée par le composant live {@see \App\Twig\Components\PreconBrowser}.
     */
    #[Route('', name: 'app_precon_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('precon/index.html.twig');
    }

    #[Route('/{id}', name: 'app_precon_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(
        #[MapEntity(expr: 'repository.findWithCards(id)')] PreconDeck $precon,
        #[CurrentUser] ?User $user,
        OwnershipChecker $ownership,
    ): Response {
        return $this->render('precon/show.html.twig', [
            'precon' => $precon,
            'ownership' => $user ? $ownership->forPrecon($precon, $user) : null,
            'commanders' => $precon->getCardsIn(DeckZone::Commander),
            'main' => $precon->getCardsIn(DeckZone::Main),
        ]);
    }

    #[Route('/{id}/ajouter', name: 'app_precon_add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsCsrfTokenValid('add-precon')]
    public function add(
        PreconDeck $precon,
        #[CurrentUser] User $user,
        PreconDeckCopier $copier,
        EntityManagerInterface $em,
        AchievementChecker $achievements,
    ): Response {
        $deck = $copier->copy($precon, $user);
        $em->persist($deck);
        $em->flush();
        $achievements->check($user);

        $this->addFlash('success', \sprintf('« %s » a été ajouté à tes decks.', $deck->getName()));

        return $this->redirectToRoute('app_deck_edit', ['id' => $deck->getId()]);
    }
}
