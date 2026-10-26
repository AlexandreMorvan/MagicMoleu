<?php

namespace App\Controller\Admin;

/**
 * Options du formulaire de lancement d'une synchronisation Scryfall.
 */
final readonly class SyncOptions
{
    public function __construct(
        public bool $force = false,
    ) {
    }
}
