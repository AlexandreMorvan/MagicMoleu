<?php

namespace App\Twig\Components;

use App\Deck\DeckBuilder;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\OracleCard;
use App\Enum\DeckZone;
use App\Repository\DeckRepository;
use App\Repository\OracleCardRepository;
use App\Security\DeckVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Éditeur de deck : recherche de cartes à ajouter, quantités, zones. Chaque action est enregistrée immédiatement.
 */
#[AsLiveComponent]
class DeckEditor extends AbstractController
{
    use DefaultActionTrait;

    /** Identifiant du deck : non modifiable côté client (signé par LiveComponent). */
    #[LiveProp]
    public int $deckId;

    #[LiveProp(writable: true)]
    public string $query = '';

    private ?Deck $deck = null;

    public function __construct(
        private readonly DeckRepository $decks,
        private readonly OracleCardRepository $cards,
        private readonly DeckBuilder $builder,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Charge le deck et vérifie les droits : appelé à chaque rendu et chaque action, donc à chaque requête live.
     */
    public function getDeck(): Deck
    {
        if (null === $this->deck) {
            $this->deck = $this->decks->findWithCards($this->deckId) ?? throw new NotFoundHttpException();
            $this->denyAccessUnlessGranted(DeckVoter::EDIT, $this->deck);
        }

        return $this->deck;
    }

    /**
     * @return list<OracleCard>
     */
    public function getSuggestions(): array
    {
        $query = trim($this->query);

        return mb_strlen($query) >= 2 ? $this->cards->searchByName($query, 8) : [];
    }

    /**
     * @return list<DeckZone>
     */
    public function getZones(): array
    {
        return DeckZone::forFormat($this->getDeck()->getFormat());
    }

    #[LiveAction]
    public function add(#[LiveArg] string $cardId, #[LiveArg] string $zone = 'main'): void
    {
        $card = Uuid::isValid($cardId) ? $this->cards->find(Uuid::fromString($cardId)) : null;
        if (null === $card) {
            return;
        }

        $this->builder->add($this->getDeck(), $card, DeckZone::tryFrom($zone) ?? DeckZone::Main);
        $this->em->flush();
    }

    #[LiveAction]
    public function changeQuantity(#[LiveArg] int $deckCardId, #[LiveArg] int $delta): void
    {
        $deckCard = $this->findDeckCard($deckCardId);
        if ($deckCard->getQuantity() + $delta <= 0) {
            $this->getDeck()->removeCard($deckCard);
        } else {
            $deckCard->setQuantity($deckCard->getQuantity() + $delta);
            $this->getDeck()->touch();
        }
        $this->em->flush();
    }

    #[LiveAction]
    public function remove(#[LiveArg] int $deckCardId): void
    {
        $this->getDeck()->removeCard($this->findDeckCard($deckCardId));
        $this->em->flush();
    }

    /**
     * Déplace tous les exemplaires d'une ligne vers une autre zone (fusionnés avec ceux déjà présents).
     */
    #[LiveAction]
    public function move(#[LiveArg] int $deckCardId, #[LiveArg] string $zone): void
    {
        $deckCard = $this->findDeckCard($deckCardId);
        $target = DeckZone::tryFrom($zone);
        if (null === $target || $target === $deckCard->getZone()) {
            return;
        }

        $deck = $this->getDeck();
        $deck->removeCard($deckCard);
        // Le retrait doit être écrit avant l'ajout : (deck, carte, zone) est unique
        $this->em->flush();
        $this->builder->add($deck, $deckCard->getOracleCard(), $target, $deckCard->getQuantity(), $deckCard->getPrinting());
        $this->em->flush();
    }

    private function findDeckCard(int $id): DeckCard
    {
        return $this->getDeck()->getCards()->findFirst(static fn (int $k, DeckCard $c) => $c->getId() === $id)
            ?? throw new NotFoundHttpException('Carte absente du deck.');
    }
}
