<?php

namespace App\Collection;

/**
 * Bilan d'un import CSV dans une collection.
 */
final readonly class CsvImportResult
{
    /**
     * @param list<string> $approximated cartes rattachées à une autre impression que celle du fichier (édition introuvable…)
     * @param list<string> $notFound     lignes ignorées faute de carte correspondante
     */
    public function __construct(
        public int $lines,
        public int $copies,
        public array $approximated,
        public array $notFound,
        public int $proxies,
    ) {
    }
}
