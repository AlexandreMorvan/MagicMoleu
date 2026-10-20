<?php

namespace App\Achievement;

use App\Deck\DeckStats;
use App\Deck\DeckValidator;
use App\Entity\Deck;
use App\Entity\User;
use App\Enum\Color;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\DeckRepository;

/**
 * Statistiques d'un utilisateur utiles aux succès, calculées seulement si une condition les demande.
 */
final class UserStats
{
    /** @var list<Deck>|null */
    private ?array $decks = null;

    /** @var array<int, bool> validité par identifiant de deck */
    private array $validity = [];

    public function __construct(
        private readonly User $user,
        private readonly DeckRepository $deckRepository,
        private readonly DeckValidator $validator,
        private readonly \DateTimeImmutable $now = new \DateTimeImmutable(),
    ) {
    }

    public function deckCount(): int
    {
        return \count($this->decks());
    }

    public function publicDeckCount(): int
    {
        return \count(array_filter($this->decks(), static fn (Deck $d) => $d->isPublic()));
    }

    public function hasValidDeck(?Format $format = null): bool
    {
        foreach ($this->decks() as $deck) {
            if ((null === $format || $deck->getFormat() === $format) && $this->isValid($deck)) {
                return true;
            }
        }

        return false;
    }

    public function hasFiveColorCommanderDeck(): bool
    {
        foreach ($this->decks() as $deck) {
            if (Format::Commander !== $deck->getFormat()) {
                continue;
            }
            $identity = array_reduce($deck->getCardsIn(DeckZone::Commander), static fn (int $mask, $c) => $mask | $c->getOracleCard()->getColorIdentity(), 0);
            if (Color::maskFrom(Color::cases()) === $identity && $this->isValid($deck)) {
                return true;
            }
        }

        return false;
    }

    public function highestDeckPrice(): float
    {
        return array_reduce($this->decks(), static fn (float $max, Deck $d) => max($max, DeckStats::fromDeck($d)->price), 0.0);
    }

    public function accountAgeInYears(): int
    {
        return $this->user->getCreatedAt()->diff($this->now)->y;
    }

    /**
     * @return list<Deck>
     */
    private function decks(): array
    {
        return $this->decks ??= array_map(
            fn (Deck $deck) => $this->deckRepository->findWithCards($deck->getId()),
            $this->deckRepository->findByOwner($this->user),
        );
    }

    private function isValid(Deck $deck): bool
    {
        return $this->validity[$deck->getId()] ??= $this->validator->validate($deck)->isValid();
    }
}
