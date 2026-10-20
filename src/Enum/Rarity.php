<?php

namespace App\Enum;

enum Rarity: string
{
    case Common = 'common';
    case Uncommon = 'uncommon';
    case Rare = 'rare';
    case Mythic = 'mythic';
    case Special = 'special';
    case Bonus = 'bonus';

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Commune',
            self::Uncommon => 'Peu commune',
            self::Rare => 'Rare',
            self::Mythic => 'Mythique',
            self::Special => 'Spéciale',
            self::Bonus => 'Bonus',
        };
    }
}
