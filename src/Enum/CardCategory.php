<?php

namespace App\Enum;

/**
 * Catégorie d'affichage d'une carte dans un deck, d'après le type de sa face principale.
 * L'ordre des cases est l'ordre d'affichage.
 */
enum CardCategory: string
{
    case Creature = 'creature';
    case Planeswalker = 'planeswalker';
    case Instant = 'instant';
    case Sorcery = 'sorcery';
    case Artifact = 'artifact';
    case Enchantment = 'enchantment';
    case Battle = 'battle';
    case Land = 'land';
    case Other = 'other';

    public static function fromTypeLine(string $typeLine): self
    {
        // Ordre de priorité : une créature-artefact est une créature, un terrain-artefact un terrain
        foreach ([self::Creature, self::Planeswalker, self::Battle, self::Land, self::Instant, self::Sorcery, self::Artifact, self::Enchantment] as $category) {
            if (str_contains($typeLine, ucfirst($category->value))) {
                return $category;
            }
        }

        return self::Other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Creature => 'Créatures',
            self::Planeswalker => 'Planeswalkers',
            self::Instant => 'Éphémères',
            self::Sorcery => 'Rituels',
            self::Artifact => 'Artefacts',
            self::Enchantment => 'Enchantements',
            self::Battle => 'Batailles',
            self::Land => 'Terrains',
            self::Other => 'Autres',
        };
    }
}
