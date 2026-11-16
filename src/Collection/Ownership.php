<?php

namespace App\Collection;

/**
 * Part d'une liste de cartes (deck, préconstruit) déjà présente dans les collections d'un utilisateur.
 * Les terrains de base ne sont pas comptés : tout le monde en a.
 */
final readonly class Ownership
{
    /**
     * @param list<MissingCard> $missing du plus cher au moins cher
     */
    public function __construct(
        public int $total,
        public int $owned,
        public array $missing,
    ) {
    }

    public function getPercent(): int
    {
        return $this->total > 0 ? (int) floor($this->owned * 100 / $this->total) : 100;
    }

    public function getMissingCount(): int
    {
        return $this->total - $this->owned;
    }

    /**
     * Coût des cartes manquantes dont le prix est connu.
     */
    public function getMissingCost(): float
    {
        return array_sum(array_map(static fn (MissingCard $m) => $m->quantity * ($m->unitPrice ?? 0), $this->missing));
    }

    public function isComplete(): bool
    {
        return $this->owned >= $this->total;
    }
}
