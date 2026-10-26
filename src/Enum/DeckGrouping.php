<?php

namespace App\Enum;

/**
 * Regroupement des cartes d'un deck à l'intérieur de chaque zone.
 */
enum DeckGrouping: string
{
    case Type = 'type';
    case Color = 'couleur';
    case ManaValue = 'ccm';

    public function label(): string
    {
        return match ($this) {
            self::Type => 'Type',
            self::Color => 'Couleur',
            self::ManaValue => 'CCM',
        };
    }
}
