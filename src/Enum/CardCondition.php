<?php

namespace App\Enum;

/**
 * État d'un exemplaire, avec les valeurs exportées par ManaBox. L'ordre des cases va du meilleur au pire.
 */
enum CardCondition: string
{
    case Mint = 'mint';
    case NearMint = 'near_mint';
    case Excellent = 'excellent';
    case Good = 'good';
    case LightPlayed = 'light_played';
    case Played = 'played';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Mint => 'Mint',
            self::NearMint => 'Near Mint',
            self::Excellent => 'Excellent',
            self::Good => 'Good',
            self::LightPlayed => 'Light Played',
            self::Played => 'Played',
            self::Poor => 'Poor',
        };
    }

    /**
     * Abréviation usuelle (MT, NM, EX…), pour les listes.
     */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Mint => 'MT',
            self::NearMint => 'NM',
            self::Excellent => 'EX',
            self::Good => 'GD',
            self::LightPlayed => 'LP',
            self::Played => 'PL',
            self::Poor => 'PO',
        };
    }
}
