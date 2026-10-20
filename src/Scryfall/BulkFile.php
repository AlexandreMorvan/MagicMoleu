<?php

namespace App\Scryfall;

final readonly class BulkFile
{
    public function __construct(
        public string $type,
        public string $path,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
