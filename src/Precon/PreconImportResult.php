<?php

namespace App\Precon;

final readonly class PreconImportResult
{
    public function __construct(
        /** Decks créés ou mis à jour */
        public int $imported,
        /** Decks déjà complets, non retéléchargés */
        public int $skipped,
        /** Decks dont le téléchargement a échoué */
        public int $failed,
        /** Cartes introuvables dans notre base, tous decks importés confondus */
        public int $unresolvedCards,
    ) {
    }
}
