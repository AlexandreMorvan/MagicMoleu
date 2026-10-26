<?php

namespace App\Twig;

use App\Deck\DeckStats;
use App\Deck\DeckValidation;
use App\Deck\DeckValidator;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\OracleCard;
use App\Enum\CardCategory;
use App\Enum\ColorGroup;
use App\Enum\DeckGrouping;
use App\Enum\DeckZone;
use Twig\Attribute\AsTwigFunction;

class DeckExtension
{
    /** @var \WeakMap<Deck, DeckValidation> */
    private \WeakMap $validations;

    public function __construct(private readonly DeckValidator $validator)
    {
        $this->validations = new \WeakMap();
    }

    #[AsTwigFunction('deck_validation')]
    public function validation(Deck $deck): DeckValidation
    {
        return $this->validations[$deck] ??= $this->validator->validate($deck);
    }

    #[AsTwigFunction('deck_stats')]
    public function stats(Deck $deck): DeckStats
    {
        return DeckStats::fromDeck($deck);
    }

    /**
     * Cartes d'une zone regroupées par type (créatures, éphémères…), couleur ou valeur de mana,
     * triées par valeur de mana puis par nom.
     *
     * @return list<array{key: string, label: string, symbol: ?string, count: int, cards: list<DeckCard>}>
     */
    #[AsTwigFunction('deck_groups')]
    public function groups(Deck $deck, DeckZone $zone, DeckGrouping $grouping = DeckGrouping::Type): array
    {
        $cards = $deck->getCardsIn($zone);
        usort($cards, static fn (DeckCard $a, DeckCard $b) => [$a->getOracleCard()->getCmc(), $a->getOracleCard()->getName()]
            <=> [$b->getOracleCard()->getCmc(), $b->getOracleCard()->getName()]);

        // Groupes dans l'ordre d'affichage : clé => [libellé, symbole de mana]
        $definitions = match ($grouping) {
            DeckGrouping::Type => array_combine(
                array_map(static fn (CardCategory $c) => $c->value, CardCategory::cases()),
                array_map(static fn (CardCategory $c) => [$c->label(), null], CardCategory::cases()),
            ),
            DeckGrouping::Color => array_combine(
                array_map(static fn (ColorGroup $g) => $g->value, ColorGroup::cases()),
                array_map(static fn (ColorGroup $g) => [$g->label(), $g->symbol()], ColorGroup::cases()),
            ),
            DeckGrouping::ManaValue => self::manaValueGroups(),
        };
        $keyOf = match ($grouping) {
            DeckGrouping::Type => static fn (OracleCard $card) => $card->getCategory()->value,
            DeckGrouping::Color => static fn (OracleCard $card) => ColorGroup::of($card)->value,
            // Les terrains n'ont pas de coût : ils ont leur propre groupe plutôt que de gonfler le « 0 »
            DeckGrouping::ManaValue => static fn (OracleCard $card) => $card->isLand() ? 'land' : (string) min(DeckStats::CURVE_MAX, (int) floor($card->getCmc())),
        };

        $byKey = [];
        foreach ($cards as $deckCard) {
            $byKey[$keyOf($deckCard->getOracleCard())][] = $deckCard;
        }

        $groups = [];
        foreach ($definitions as $key => [$label, $symbol]) {
            if (isset($byKey[$key])) {
                $groups[] = [
                    'key' => (string) $key,
                    'label' => $label,
                    'symbol' => $symbol,
                    'count' => array_sum(array_map(static fn (DeckCard $c) => $c->getQuantity(), $byKey[$key])),
                    'cards' => $byKey[$key],
                ];
            }
        }

        return $groups;
    }

    /**
     * @return array<string, array{string, ?string}> groupes « 0 » à « 7+ » puis terrains
     */
    private static function manaValueGroups(): array
    {
        $groups = [];
        foreach (range(0, DeckStats::CURVE_MAX) as $cmc) {
            $groups[(string) $cmc] = [DeckStats::CURVE_MAX === $cmc ? $cmc.'+' : (string) $cmc, null];
        }
        $groups['land'] = [ColorGroup::Land->label(), null];

        return $groups;
    }
}
