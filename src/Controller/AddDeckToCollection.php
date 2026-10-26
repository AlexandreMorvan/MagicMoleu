<?php

namespace App\Controller;

/**
 * Formulaire « ajouter toutes les cartes du deck à une collection » : sans collection choisie, une nouvelle est créée.
 */
final readonly class AddDeckToCollection
{
    public function __construct(
        public ?int $collection = null,
    ) {
    }
}
