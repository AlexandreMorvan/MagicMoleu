<?php

namespace App\Twig;

use App\Deck\DeckStats;
use App\Deck\DeckValidation;
use App\Deck\DeckValidator;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Enum\CardCategory;
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
     * Cartes d'une zone regroupées par catégorie (créatures, éphémères…), triées par valeur de mana puis par nom.
     *
     * @return list<array{category: CardCategory, count: int, cards: list<DeckCard>}>
     */
    #[AsTwigFunction('deck_groups')]
    public function groups(Deck $deck, DeckZone $zone): array
    {
        $cards = $deck->getCardsIn($zone);
        usort($cards, static fn (DeckCard $a, DeckCard $b) => [$a->getOracleCard()->getCmc(), $a->getOracleCard()->getName()]
            <=> [$b->getOracleCard()->getCmc(), $b->getOracleCard()->getName()]);

        $groups = [];
        foreach (CardCategory::cases() as $category) {
            $inCategory = array_values(array_filter($cards, static fn (DeckCard $c) => $c->getOracleCard()->getCategory() === $category));
            if ($inCategory) {
                $groups[] = [
                    'category' => $category,
                    'count' => array_sum(array_map(static fn (DeckCard $c) => $c->getQuantity(), $inCategory)),
                    'cards' => $inCategory,
                ];
            }
        }

        return $groups;
    }
}
