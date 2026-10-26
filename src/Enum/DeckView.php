<?php

namespace App\Enum;

/**
 * Affichage des cartes d'un deck.
 */
enum DeckView: string
{
    case List = 'liste';
    case Visual = 'visuelle';
    case Detailed = 'detaillee';

    public function label(): string
    {
        return match ($this) {
            self::List => 'Liste',
            self::Visual => 'Visuelle',
            self::Detailed => 'Détaillée',
        };
    }
}
