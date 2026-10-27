<?php

namespace App\Controller;

use App\Collection\CollectionBuilder;
use App\Collection\CsvImportException;
use App\Collection\ManaboxCsvImporter;
use App\Entity\CardCollection;
use App\Entity\User;
use App\Form\CardCollectionType;
use App\Form\CollectionImportType;
use App\Repository\CardCollectionRepository;
use App\Repository\DeckRepository;
use App\Security\CardCollectionVoter;
use App\Security\DeckVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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
     * Import d'un export CSV ManaBox, dans une collection existante (?collection=id pour la présélectionner) ou nouvelle.
     */
    #[Route('/importer', name: 'app_collection_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        #[CurrentUser] User $user,
        ManaboxCsvImporter $importer,
        #[MapQueryParameter] ?int $collection = null,
    ): Response {
        $preselected = $collection ? $this->collections->findOneBy(['id' => $collection, 'owner' => $user]) : null;
        $form = $this->createForm(CollectionImportType::class, ['collection' => $preselected], ['owner' => $user])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $target = $data['collection'] ? $this->findCollection($data['collection']->getId()) : null;
            if (null === $target) {
                $name = trim((string) $data['name']) ?: 'Import ManaBox du '.date('d/m/Y');
                $target = (new CardCollection($user))->setName($name);
                $this->em->persist($target);
            }

            try {
                $result = $importer->import($target, $data['file']->getPathname(), (bool) $data['replace']);
            } catch (CsvImportException $e) {
                $form->get('file')->addError(new FormError($e->getMessage()));

                return $this->render('collection/import.html.twig', ['form' => $form], new Response(status: 422));
            }
            $this->em->flush();

            $this->addFlash('success', \sprintf('%d carte%s importée%2$s (%d ligne%s) dans « %s ».', $result->copies, $result->copies > 1 ? 's' : '', $result->lines, $result->lines > 1 ? 's' : '', $target->getName()));
            if ($result->approximated) {
                $this->addFlash('warning', \sprintf('Impression introuvable, carte rattachée à une autre : %s.', self::summarize($result->approximated)));
            }
            if ($result->notFound) {
                $this->addFlash('error', \sprintf('Carte%s introuvable%1$s, non importée%1$s : %s.', \count($result->notFound) > 1 ? 's' : '', self::summarize($result->notFound)));
            }
            if ($result->proxies) {
                $this->addFlash('warning', \sprintf('%d proxy ignoré%s.', $result->proxies, $result->proxies > 1 ? 's' : ''));
            }

            return $this->redirectToRoute('app_collection_show', ['id' => $target->getId()]);
        }

        return $this->render('collection/import.html.twig', ['form' => $form]);
    }

    /**
     * @param list<string> $items
     */
    private static function summarize(array $items): string
    {
        return implode(' · ', \array_slice($items, 0, 15)).(\count($items) > 15 ? \sprintf(' … et %d autre(s)', \count($items) - 15) : '');
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
