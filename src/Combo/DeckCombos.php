<?php

namespace App\Combo;

/**
 * Combos d'un deck : ceux qu'il contient déjà et ceux auxquels il ne manque qu'une carte.
 */
final readonly class DeckCombos
{
    /**
     * @param list<Combo>  $included
     * @param list<Combo>  $almostIncluded du plus joué au moins joué, limité à {@see CommanderSpellbook::ALMOST_LIMIT}
     * @param list<string> $deckCardNames  noms des cartes du deck, pour repérer celles qui manquent à un combo
     */
    public function __construct(
        public array $included,
        public array $almostIncluded,
        public int $almostIncludedTotal,
        public array $deckCardNames,
    ) {
    }

    /**
     * @return list<string> noms des cartes du combo absentes du deck
     */
    public function missingCards(Combo $combo): array
    {
        return array_values(array_diff(array_map(static fn (ComboCard $c) => $c->name, $combo->cards), $this->deckCardNames));
    }

    /**
     * @return list<Combo>
     */
    public function all(): array
    {
        return [...$this->included, ...$this->almostIncluded];
    }
}
