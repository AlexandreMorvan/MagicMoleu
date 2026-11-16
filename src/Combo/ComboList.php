<?php

namespace App\Combo;

/**
 * Les combos d'une carte, du plus joué au moins joué.
 */
final readonly class ComboList
{
    /**
     * @param list<Combo> $combos
     */
    public function __construct(
        public array $combos,
        /** Commander Spellbook en connaît d'autres que ceux affichés */
        public bool $hasMore,
        public string $searchUrl,
    ) {
    }
}
