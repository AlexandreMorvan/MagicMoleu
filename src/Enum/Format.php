<?php

namespace App\Enum;

/**
 * Formats tels que nommés dans le champ `legalities` de Scryfall.
 */
enum Format: string
{
    case Standard = 'standard';
    case Pioneer = 'pioneer';
    case Modern = 'modern';
    case Legacy = 'legacy';
    case Vintage = 'vintage';
    case Pauper = 'pauper';
    case Commander = 'commander';
    case PauperCommander = 'paupercommander';
    case Oathbreaker = 'oathbreaker';
    case Brawl = 'brawl';
    case StandardBrawl = 'standardbrawl';
    case Historic = 'historic';
    case Timeless = 'timeless';
    case Alchemy = 'alchemy';
    case Gladiator = 'gladiator';
    case Penny = 'penny';
    case Duel = 'duel';
    case Premodern = 'premodern';
    case Oldschool = 'oldschool';
    case Predh = 'predh';
    case Future = 'future';

    public function label(): string
    {
        return match ($this) {
            self::PauperCommander => 'Pauper Commander',
            self::StandardBrawl => 'Standard Brawl',
            self::Penny => 'Penny Dreadful',
            self::Duel => 'Duel Commander',
            self::Oldschool => 'Old School',
            self::Predh => 'PreDH',
            default => ucfirst($this->value),
        };
    }
}
