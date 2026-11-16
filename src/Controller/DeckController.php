<?php

namespace App\Controller;

use App\Achievement\AchievementChecker;
use App\Collection\OwnershipChecker;
use App\Combo\ComboCardLinker;
use App\Combo\ComboSourceUnavailableException;
use App\Combo\CommanderSpellbook;
use App\Deck\DeckExporter;
use App\Deck\DeckImporter;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\User;
use App\Enum\DeckGrouping;
use App\Enum\DeckView;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Form\DeckType;
use App\Repository\CardCollectionRepository;
use App\Repository\DeckRepository;
use App\Security\DeckVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
        // Format préféré du compte, s'il fait partie des formats de deck
        if (\in_array($user->getPreferredFormat(), Format::deckFormats(), true)) {
            $deck->setFormat($user->getPreferredFormat());
        }
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
    public function show(
        int $id,
        #[CurrentUser] ?User $user,
        CardCollectionRepository $collections,
        OwnershipChecker $ownership,
        #[MapQueryParameter] ?DeckView $vue = null,
        #[MapQueryParameter] DeckGrouping $groupe = DeckGrouping::Type,
    ): Response {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::VIEW, $deck);

        return $this->render('deck/show.html.twig', [
            'deck' => $deck,
            // Sans choix dans l'URL : la vue par défaut du compte
            'view' => $vue ?? $user?->getDefaultDeckView() ?? DeckView::List,
            'grouping' => $groupe,
            // Pour « ajouter toutes les cartes à une collection »
            'collections' => $user ? $collections->findByOwner($user) : [],
            // Ce que l'utilisateur possède déjà de ce deck (le sien ou celui d'un autre)
            'ownership' => $user && $deck->getCards()->count() ? $ownership->forDeck($deck, $user) : null,
        ]);
    }

    /**
     * Main de départ d'un deck Commander : mélange, mulligan et pioche, côté navigateur (contrôleur Stimulus « goldfish »).
     */
    #[Route('/{id}/main-de-depart', name: 'app_deck_goldfish', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function goldfish(int $id): Response
    {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::VIEW, $deck);
        if (Format::Commander !== $deck->getFormat()) {
            throw $this->createNotFoundException('La main de départ est réservée aux decks Commander.');
        }

        $card = static fn (DeckCard $line): array => [
            'name' => $line->getOracleCard()->getName(),
            'image' => $line->getImageUri(),
            'land' => $line->getOracleCard()->isLand(),
        ];
        $library = [];
        foreach ($deck->getCardsIn(DeckZone::Main) as $line) {
            array_push($library, ...array_fill(0, $line->getQuantity(), $card($line)));
        }

        return $this->render('deck/goldfish.html.twig', [
            'deck' => $deck,
            'library' => $library,
            'commanders' => array_map($card, $deck->getCardsIn(DeckZone::Commander)),
        ]);
    }

    /**
     * Combos du deck (Commander Spellbook), chargés en différé dans la fiche du deck (Turbo Frame).
     */
    #[Route('/{id}/combos', name: 'app_deck_combos', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function combos(
        int $id,
        Request $request,
        CommanderSpellbook $spellbook,
        ComboCardLinker $linker,
        #[Target('combos')]
        RateLimiterFactoryInterface $combosLimiter,
    ): Response {
        $deck = $this->findDeck($id);
        $this->denyAccessUnlessGranted(DeckVoter::VIEW, $deck);

        $combos = null;
        if ($combosLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            try {
                $combos = $spellbook->findDeckCombos($deck);
            } catch (ComboSourceUnavailableException) {
            }
        }

        // Toujours 200 : la frame affiche le message d'indisponibilité au lieu d'une erreur
        return $this->render('deck/_combos.html.twig', [
            'deck' => $deck,
            'combos' => $combos,
            'local_cards' => $combos ? $linker->link($combos->all()) : [],
        ]);
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

    /**
     * @param FormInterface<Deck> $form
     */
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
