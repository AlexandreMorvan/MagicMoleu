<?php

namespace App\Controller;

use App\Collection\CollectionBuilder;
use App\Entity\CardCollection;
use App\Entity\User;
use App\Form\CardCollectionType;
use App\Repository\CardCollectionRepository;
use App\Repository\DeckRepository;
use App\Security\CardCollectionVoter;
use App\Security\DeckVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/collections')]
#[IsGranted('ROLE_USER')]
class CollectionController extends AbstractController
{
    public function __construct(
        private readonly CardCollectionRepository $collections,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_collection_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('collection/index.html.twig', [
            'collections' => $this->collections->findByOwnerWithCards($user),
        ]);
    }

    #[Route('/nouvelle', name: 'app_collection_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $collection = new CardCollection($user);
        $form = $this->createForm(CardCollectionType::class, $collection)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($collection);
            $this->em->flush();

            return $this->redirectToRoute('app_collection_show', ['id' => $collection->getId()]);
        }

        return $this->render('collection/form.html.twig', ['form' => $form, 'collection' => null]);
    }

    /**
     * Les filtres, vues et quantités sont gérés par le composant live {@see \App\Twig\Components\CollectionBrowser}.
     */
    #[Route('/{id}', name: 'app_collection_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(int $id): Response
    {
        return $this->render('collection/show.html.twig', ['collection' => $this->findCollection($id)]);
    }

    #[Route('/{id}/parametres', name: 'app_collection_settings', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    public function settings(int $id, Request $request): Response
    {
        $collection = $this->findCollection($id);
        $form = $this->createForm(CardCollectionType::class, $collection)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $collection->touch();
            $this->em->flush();
            $this->addFlash('success', 'Collection mise à jour.');

            return $this->redirectToRoute('app_collection_show', ['id' => $collection->getId()]);
        }

        return $this->render('collection/form.html.twig', ['form' => $form, 'collection' => $collection]);
    }

    #[Route('/{id}/supprimer', name: 'app_collection_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $collection = $this->findCollection($id);

        if ($this->isCsrfTokenValid('delete-collection-'.$collection->getId(), $request->getPayload()->getString('_token'))) {
            $this->em->remove($collection);
            $this->em->flush();
            $this->addFlash('success', \sprintf('La collection « %s » a été supprimée.', $collection->getName()));
        }

        return $this->redirectToRoute('app_collection_index');
    }

    /**
     * Ajoute les cartes d'un deck (le sien ou un deck public) à l'une de ses collections, ou à une nouvelle.
     */
    #[Route('/ajouter-deck/{deckId}', name: 'app_collection_add_deck', requirements: ['deckId' => Requirement::DIGITS], methods: ['POST'])]
    #[IsCsrfTokenValid('add-deck-to-collection')]
    public function addDeck(
        int $deckId,
        #[CurrentUser] User $user,
        DeckRepository $decks,
        CollectionBuilder $builder,
        #[MapRequestPayload] AddDeckToCollection $payload = new AddDeckToCollection(),
    ): Response {
        $deck = $decks->findWithCards($deckId) ?? throw $this->createNotFoundException('Deck introuvable.');
        $this->denyAccessUnlessGranted(DeckVoter::VIEW, $deck);

        if ($payload->collection) {
            $collection = $this->findCollection($payload->collection);
        } else {
            $collection = (new CardCollection($user))->setName(mb_substr($deck->getName(), 0, 100));
            $this->em->persist($collection);
        }

        $added = $builder->addDeck($collection, $deck);
        $this->em->flush();
        $this->addFlash('success', \sprintf('%d carte%s ajoutée%2$s à la collection « %s ».', $added, $added > 1 ? 's' : '', $collection->getName()));

        return $this->redirectToRoute('app_collection_show', ['id' => $collection->getId()]);
    }

    private function findCollection(int $id): CardCollection
    {
        $collection = $this->collections->findWithCards($id) ?? throw $this->createNotFoundException('Collection introuvable.');
        $this->denyAccessUnlessGranted(CardCollectionVoter::MANAGE, $collection);

        return $collection;
    }
}
