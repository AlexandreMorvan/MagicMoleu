<?php

namespace App\Enum;

enum DeckZone: string
{
    case Commander = 'commander';
    case Main = 'main';
    case Side = 'side';
    case Maybe = 'maybe';

    public function label(): string
    {
        return match ($this) {
            self::Commander => 'Commandant',
            self::Main => 'Deck',
            self::Side => 'Réserve',
            self::Maybe => 'Peut-être',
        };
    }

    /**
     * @return list<self>
     */
    public static function forFormat(Format $format): array
    {
        return Format::Commander === $format
            ? [self::Commander, self::Main, self::Maybe]
            : [self::Main, self::Side, self::Maybe];
    }
}
