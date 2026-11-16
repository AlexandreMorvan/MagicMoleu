<?php

namespace App\Combo;

use Symfony\Component\Uid\Uuid;

/**
 * Une carte utilisée par un combo Commander Spellbook.
 */
final readonly class ComboCard
{
    public function __construct(
        public string $name,
        /** Identifiant oracle Scryfall, qui est aussi celui de {@see \App\Entity\OracleCard}. */
        public ?Uuid $oracleId,
        public ?string $imageUri,
    ) {
    }
}
