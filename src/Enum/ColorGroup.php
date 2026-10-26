<?php

namespace App\Enum;

use App\Entity\OracleCard;

/**
 * Groupe de couleur d'une carte pour l'affichage (deck groupé par couleur, statistiques de collection).
 * L'ordre des cases est l'ordre d'affichage.
 */
enum ColorGroup: string
{
    case White = 'W';
    case Blue = 'U';
    case Black = 'B';
    case Red = 'R';
    case Green = 'G';
    case Multicolor = 'multi';
    case Colorless = 'colorless';
    case Land = 'land';

    /**
     * Les terrains forment leur propre groupe, même colorés (Dryad Arbor) : c'est ainsi qu'on lit une liste.
     */
    public static function of(OracleCard $card): self
    {
        if ($card->isLand()) {
            return self::Land;
        }

        $colors = $card->getColorList();

        return match (\count($colors)) {
            0 => self::Colorless,
            1 => self::from($colors[0]->value),
            default => self::Multicolor,
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
            self::Multicolor => 'Multicolore',
            self::Colorless => 'Incolore',
            self::Land => 'Terrains',
        };
    }

    /**
     * Symbole de mana illustrant le groupe ({W}, {C}…), null pour les terrains et les multicolores.
     */
    public function symbol(): ?string
    {
        return match ($this) {
            self::Multicolor, self::Land => null,
            self::Colorless => '{C}',
            default => '{'.$this->value.'}',
        };
    }

    /**
     * Couleur CSS (classes bg-mana-*) pour les barres de statistiques.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Multicolor => 'bg-amber-400',
            self::Colorless => 'bg-mana-c',
            self::Land => 'bg-ink-500',
            default => 'bg-mana-'.strtolower($this->value),
        };
    }
}
