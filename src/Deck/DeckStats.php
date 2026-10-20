<?php

namespace App\Deck;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Enum\CardCategory;
use App\Enum\Color;
use App\Enum\DeckZone;

/**
 * Statistiques d'un deck (cartes jouées : commandant + deck principal).
 */
final readonly class DeckStats
{
    /** Dernière colonne de la courbe de mana : « 7+ ». */
    public const int CURVE_MAX = 7;

    /**
     * @param array<int, int>    $curve      nombre de sorts (hors terrains) par valeur de mana, 0 à 7+
     * @param array<string, int> $pips       nombre de symboles de mana colorés par couleur (W, U, B, R, G)
     * @param array<string, int> $categories nombre de cartes par libellé de catégorie, dans l'ordre d'affichage
     */
    public function __construct(
        public int $cardCount,
        public int $landCount,
        public array $curve,
        public array $pips,
        public array $categories,
        public ?float $averageCmc,
        public float $price,
    ) {
    }

    public static function fromDeck(Deck $deck): self
    {
        $curve = array_fill(0, self::CURVE_MAX + 1, 0);
        $pips = array_fill_keys(array_map(static fn (Color $c) => $c->value, Color::cases()), 0);
        $categories = array_fill_keys(array_map(static fn (CardCategory $c) => $c->label(), CardCategory::cases()), 0);
        $cardCount = $landCount = 0;
        $cmcTotal = 0.0;
        $price = 0.0;

        /** @var DeckCard $deckCard */
        foreach ($deck->getCardsIn(DeckZone::Commander, DeckZone::Main) as $deckCard) {
            $card = $deckCard->getOracleCard();
            $quantity = $deckCard->getQuantity();
            $cardCount += $quantity;
            $categories[$card->getCategory()->label()] += $quantity;
            $price += ($deckCard->getUnitPrice() ?? 0) * $quantity;

            if ($card->isLand()) {
                $landCount += $quantity;
            } else {
                $curve[min(self::CURVE_MAX, (int) floor($card->getCmc()))] += $quantity;
                $cmcTotal += $card->getCmc() * $quantity;
            }

            // Symboles colorés du coût, hybrides compris ({W/U} compte pour W et U)
            preg_match_all('/\{([^}]+)\}/', $card->getManaCost() ?? '', $symbols);
            foreach ($symbols[1] as $symbol) {
                foreach (array_unique(str_split(preg_replace('/[^WUBRG]/', '', $symbol))) as $color) {
                    $pips[$color] += $quantity;
                }
            }
        }

        $spells = $cardCount - $landCount;

        return new self(
            cardCount: $cardCount,
            landCount: $landCount,
            curve: $curve,
            pips: $pips,
            categories: array_filter($categories),
            averageCmc: $spells > 0 ? round($cmcTotal / $spells, 2) : null,
            price: round($price, 2),
        );
    }

    public function curvePeak(): int
    {
        return max(1, ...$this->curve);
    }

    public function totalPips(): int
    {
        return array_sum($this->pips);
    }
}
