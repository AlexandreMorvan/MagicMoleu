<?php

namespace App\Collection;

use App\Entity\CardCollection;
use App\Entity\CollectionCard;
use App\Enum\ColorGroup;
use App\Enum\Rarity;

/**
 * Statistiques d'une collection : nombre de cartes, valeur, répartition par rareté, couleur et édition.
 */
final readonly class CollectionStats
{
    /** Nombre de cartes les plus chères et d'éditions les plus représentées affichées. */
    public const int TOP = 8;

    /**
     * @param array<string, array{rarity: Rarity, count: int, value: float}>         $rarities   par rareté (ordre de l'enum), raretés absentes exclues
     * @param array<string, array{group: ColorGroup, count: int}>                     $colors     par groupe de couleur (ordre de l'enum), groupes absents exclus
     * @param list<array{name: string, code: string, iconSvgUri: ?string, count: int}> $sets       éditions les plus représentées
     * @param list<CollectionCard>                                                    $mostValuable lignes de plus grande valeur unitaire
     * @param ?float                                                                  $purchaseValue          prix d'achat total (en euros) des cartes dont il est connu
     * @param ?float                                                                  $purchasedCurrentValue  valeur actuelle de ces mêmes cartes
     * @param array<string, int>                                                      $languages  nombre de cartes par langue, de la plus représentée à la moins représentée
     */
    public function __construct(
        public int $cardCount,
        public int $uniqueCount,
        public int $foilCount,
        public float $value,
        public int $unpricedCount,
        public array $rarities,
        public array $colors,
        public array $sets,
        public array $mostValuable,
        public ?float $purchaseValue = null,
        public ?float $purchasedCurrentValue = null,
        public array $languages = [],
    ) {
    }

    public static function fromCollection(CardCollection $collection): self
    {
        $rarities = [];
        foreach (Rarity::cases() as $rarity) {
            $rarities[$rarity->value] = ['rarity' => $rarity, 'count' => 0, 'value' => 0.0];
        }
        $colors = [];
        foreach (ColorGroup::cases() as $group) {
            $colors[$group->value] = ['group' => $group, 'count' => 0];
        }
        $sets = [];
        $oracleIds = [];
        $cardCount = $foilCount = $unpricedCount = 0;
        $value = 0.0;
        $purchaseValue = $purchasedCurrentValue = null;
        $languages = [];

        /** @var CollectionCard $line */
        foreach ($collection->getCards() as $line) {
            $printing = $line->getPrinting();
            $quantity = $line->getQuantity();
            $cardCount += $quantity;
            $foilCount += $line->isFoil() ? $quantity : 0;
            $oracleIds[(string) $printing->getOracleCard()->getId()] = true;

            if (null === $line->getUnitPrice()) {
                $unpricedCount += $quantity;
            }
            $value += $line->getTotalPrice();

            if (null !== $paid = $line->getTotalPurchasePriceEur()) {
                $purchaseValue = ($purchaseValue ?? 0) + $paid;
                $purchasedCurrentValue = ($purchasedCurrentValue ?? 0) + $line->getTotalPrice();
            }
            $languages[$line->getLang()] = ($languages[$line->getLang()] ?? 0) + $quantity;

            $rarities[$printing->getRarity()->value]['count'] += $quantity;
            $rarities[$printing->getRarity()->value]['value'] += $line->getTotalPrice();
            $colors[ColorGroup::of($printing->getOracleCard())->value]['count'] += $quantity;

            $set = $printing->getSet();
            $sets[$set->getCode()] ??= ['name' => $set->getName(), 'code' => $set->getCode(), 'iconSvgUri' => $set->getIconSvgUri(), 'count' => 0];
            $sets[$set->getCode()]['count'] += $quantity;
        }

        arsort($languages);
        usort($sets, static fn (array $a, array $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        $priced = array_filter($collection->getCards()->toArray(), static fn (CollectionCard $c) => null !== $c->getUnitPrice());
        usort($priced, static fn (CollectionCard $a, CollectionCard $b) => $b->getUnitPrice() <=> $a->getUnitPrice());

        return new self(
            cardCount: $cardCount,
            uniqueCount: \count($oracleIds),
            foilCount: $foilCount,
            value: round($value, 2),
            unpricedCount: $unpricedCount,
            rarities: array_filter($rarities, static fn (array $r) => $r['count'] > 0),
            colors: array_filter($colors, static fn (array $c) => $c['count'] > 0),
            sets: \array_slice($sets, 0, self::TOP),
            mostValuable: \array_slice($priced, 0, self::TOP),
            purchaseValue: null !== $purchaseValue ? round($purchaseValue, 2) : null,
            purchasedCurrentValue: null !== $purchasedCurrentValue ? round($purchasedCurrentValue, 2) : null,
            languages: $languages,
        );
    }

    /**
     * Plus-value (ou moins-value) des cartes dont le prix d'achat est connu : valeur actuelle moins prix d'achat.
     */
    public function gain(): ?float
    {
        return null !== $this->purchaseValue ? round($this->purchasedCurrentValue - $this->purchaseValue, 2) : null;
    }

    /**
     * Prix moyen des cartes dont le prix est connu.
     */
    public function averageValue(): ?float
    {
        $priced = $this->cardCount - $this->unpricedCount;

        return $priced > 0 ? round($this->value / $priced, 2) : null;
    }
}
