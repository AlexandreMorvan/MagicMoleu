<?php

namespace App\Deck;

use App\Enum\DeckZone;

final readonly class DeckListLine
{
    public function __construct(
        public int $quantity,
        public string $name,
        public DeckZone $zone,
        public ?string $setCode,
        public ?string $collectorNumber,
        public string $raw,
    ) {
    }
}
