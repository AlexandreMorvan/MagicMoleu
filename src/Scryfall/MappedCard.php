<?php

namespace App\Scryfall;

/**
 * Une carte Scryfall découpée en lignes prêtes à écrire, une propriété par table.
 */
final readonly class MappedCard
{
    /**
     * @param array<string, scalar|null>       $set
     * @param array<string, scalar|null>       $oracle
     * @param list<array<string, scalar|null>> $faces
     * @param array<string, scalar|null>       $printing
     * @param list<array<string, scalar|null>> $legalities
     */
    public function __construct(
        public array $set,
        public array $oracle,
        public array $faces,
        public array $printing,
        public array $legalities,
    ) {
    }
}
