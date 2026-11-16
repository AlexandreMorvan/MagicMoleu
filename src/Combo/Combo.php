<?php

namespace App\Combo;

/**
 * Un combo (une « variante » Commander Spellbook).
 */
final readonly class Combo
{
    /**
     * @param list<ComboCard> $cards
     * @param list<string>    $results ex. « Infinite mana », « Win the game »
     * @param list<string>    $steps   déroulé du combo, une étape par ligne
     */
    public function __construct(
        public string $id,
        public array $cards,
        public array $results,
        public array $steps,
        public string $prerequisites,
        public string $manaNeeded,
        /** Identité couleur, ex. « UB » ou « C » */
        public string $identity,
        public int $popularity,
    ) {
    }

    public function getUrl(): string
    {
        return 'https://commanderspellbook.com/combo/'.rawurlencode($this->id).'/';
    }
}
