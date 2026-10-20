<?php

namespace App\Controller;

use App\Achievement\AchievementChecker;
use App\Deck\DeckExporter;
use App\Deck\DeckImporter;
use App\Entity\Deck;
use App\Entity\User;
use App\Form\DeckType;
use App\Repository\DeckRepository;
use App\Security\DeckVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/decks')]
class DeckController extends AbstractController
{
    public function __construct(
        private readonly DeckRepository $decks,
        private readonly EntityManagerInterface $em,
        private readonly AchievementChecker $achievements,
    ) {
    }

    #[Route('', name: 'app_deck_index', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user): Response
    {
        return $this->render('deck/index.html.twig', [
            'my_decks' => $user ? $this->decks->findByOwner($user) : [],
            'public_decks' => $this->decks->findLatestPublic(12),
        ]);
    }

    #[Route('/nouveau', name: 'app_deck_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, #[CurrentUser] User $user, DeckImporter $importer): Response
    {
        $deck = new Deck($user);
        $form = $this->createForm(DeckType::class, $deck)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->importList($form, $deck, $importer);
            $this->em->persist($deck);
            $this->em->flush();
            $this->achievements->check($user);

            return $this->redirectToRoute('app_deck_edit', ['id' => $deck->getId()]);
        }

        return $this->render('deck/form.html.twig', ['form' => $form, 'deck' => null]);
    }

    #[Route('/{id}', name: 'app_deck_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(int $id): Response
    {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::VIEW, $deck);

        return $this->render('deck/show.html.twig', ['deck' => $deck]);
    }

    /**
     * L'édition des cartes est gérée par le composant live {@see \App\Twig\Components\DeckEditor}.
     */
    #[Route('/{id}/modifier', name: 'app_deck_edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function edit(int $id): Response
    {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::EDIT, $deck);

        return $this->render('deck/edit.html.twig', ['deck' => $deck]);
    }

    #[Route('/{id}/parametres', name: 'app_deck_settings', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    public function settings(int $id, Request $request, DeckImporter $importer): Response
    {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::EDIT, $deck);

        $form = $this->createForm(DeckType::class, $deck, ['list_label' => 'Ajouter des cartes depuis une liste'])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->importList($form, $deck, $importer);
            $deck->touch();
            $this->em->flush();
            $this->achievements->check($deck->getOwner());
            $this->addFlash('success', 'Deck mis à jour.');

            return $this->redirectToRoute('app_deck_edit', ['id' => $deck->getId()]);
        }

        return $this->render('deck/form.html.twig', ['form' => $form, 'deck' => $deck]);
    }

    #[Route('/{id}/supprimer', name: 'app_deck_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::EDIT, $deck);

        if ($this->isCsrfTokenValid('delete-deck-'.$deck->getId(), $request->getPayload()->getString('_token'))) {
            $this->em->remove($deck);
            $this->em->flush();
            $this->addFlash('success', \sprintf('Le deck « %s » a été supprimé.', $deck->getName()));
        }

        return $this->redirectToRoute('app_deck_index');
    }

    #[Route('/{id}/export', name: 'app_deck_export', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function export(int $id, DeckExporter $exporter): Response
    {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::VIEW, $deck);

        $filename = (new AsciiSlugger())->slug($deck->getName())->lower().'.txt';

        return new Response($exporter->export($deck), headers: [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => \sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function findDeck(int $id): Deck
    {
        return $this->decks->findWithCards($id) ?? throw $this->createNotFoundException('Deck introuvable.');
    }

    private function importList(FormInterface $form, Deck $deck, DeckImporter $importer): void
    {
        $list = trim((string) $form->get('list')->getData());
        if ('' === $list) {
            return;
        }

        $unresolved = $importer->import($deck, $list);
        if ($unresolved) {
            $this->addFlash('error', 'Cartes introuvables : '.implode(' · ', \array_slice($unresolved, 0, 20)).(\count($unresolved) > 20 ? '…' : ''));
        }
    }
}
