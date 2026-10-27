<?php

namespace App\Enum;

/**
 * Couleurs de Magic encodées en bitmask (WUBRG) pour filtrer en SQL
 * sans jointure, ex. identité couleur : `color_identity & ~:mask = 0`.
 */
enum Color: string
{
    case White = 'W';
    case Blue = 'U';
    case Black = 'B';
    case Red = 'R';
    case Green = 'G';

    public function bit(): int
    {
        return match ($this) {
            self::White => 1,
            self::Blue => 2,
            self::Black => 4,
            self::Red => 8,
            self::Green => 16,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::White => 'Blanc',
            self::Blue => 'Bleu',
            self::Black => 'Noir',
            self::Red => 'Rouge',
            self::Green => 'Vert',
        };
    }

    /**
     * @param iterable<string|self> $colors symboles Scryfall ("W", "U"...) ou cases
     */
    public static function maskFrom(iterable $colors): int
    {
        $mask = 0;
        foreach ($colors as $color) {
            $color = $color instanceof self ? $color : self::tryFrom($color);
            $mask |= $color?->bit() ?? 0;
        }

        return $mask;
    }

    /** Noms des combinaisons de deux et trois couleurs (guildes de Ravnica, fragments d'Alara, clans de Tarkir). */
    private const array COMBINATIONS = [
        3 => 'Azorius', 6 => 'Dimir', 12 => 'Rakdos', 24 => 'Gruul', 17 => 'Selesnya',
        5 => 'Orzhov', 10 => 'Izzet', 20 => 'Golgari', 9 => 'Boros', 18 => 'Simic',
        19 => 'Bant', 7 => 'Esper', 14 => 'Grixis', 28 => 'Jund', 25 => 'Naya',
        21 => 'Abzan', 11 => 'Jeskai', 22 => 'Sultai', 13 => 'Mardu', 26 => 'Temur',
    ];

    /**
     * Nom d'une combinaison de couleurs : « Rouge », « Izzet », « Esper », « Quatre couleurs »… null sans couleur.
     */
    public static function combinationName(int $mask): ?string
    {
        $colors = self::fromMask($mask);

        return match (\count($colors)) {
            0 => null,
            1 => $colors[0]->label(),
            4 => 'Quatre couleurs',
            5 => 'Cinq couleurs',
            default => self::COMBINATIONS[$mask] ?? null,
        };
    }

    /**
     * @return list<self> dans l'ordre WUBRG
     */
    public static function fromMask(int $mask): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c) => ($mask & $c->bit()) !== 0));
    }
}
