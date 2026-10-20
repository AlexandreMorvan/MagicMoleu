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

    /**
     * @return list<self> dans l'ordre WUBRG
     */
    public static function fromMask(int $mask): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c) => ($mask & $c->bit()) !== 0));
    }
}
