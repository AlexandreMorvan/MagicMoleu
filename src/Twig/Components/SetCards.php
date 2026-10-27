<?php

namespace App\Twig\Components;

use App\Entity\CardPrinting;
use App\Entity\MtgSet;
use App\Enum\Color;
use App\Enum\Rarity;
use App\Repository\CardPrintingRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Cartes d'une édition, filtrables par couleur et rareté.
 */
#[AsLiveComponent]
class SetCards
{
    use DefaultActionTrait;

    private const int PAGE_SIZE = 120;

    #[LiveProp]
    public MtgSet $set;

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public array $colors = [];

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public array $rarities = [];

    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public string $sort = 'number';

    /** Afficher les extras (variantes d'illustration, traitements, promos). */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public bool $extras = false;

    #[LiveProp]
    public int $limit = self::PAGE_SIZE;

    /** @var array{main: int, extra: int}|null */
    private ?array $counts = null;

    /** @var list<CardPrinting>|null */
    private ?array $cards = null;

    public function __construct(private readonly CardPrintingRepository $printings)
    {
    }

    public function resetLimit(): void
    {
        $this->limit = self::PAGE_SIZE;
    }

    #[LiveAction]
    public function more(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * @return list<CardPrinting>
     */
    public function getCards(): array
    {
        // Une carte de plus que la limite est chargée pour savoir s'il en reste
        $this->cards ??= $this->printings->findBySet(
            $this->set,
            array_values(array_filter($this->rarities, static fn (string $r) => null !== Rarity::tryFrom($r))),
            Color::maskFrom($this->colors),
            \in_array('C', $this->colors, true),
            $this->sort,
            $this->limit + 1,
            // Une édition faite uniquement d'extras (Secret Lair, promos…) les montre toujours
            $this->extras || !$this->hasMainCards(),
        );

        return \array_slice($this->cards, 0, $this->limit);
    }

    public function hasMore(): bool
    {
        $this->getCards();

        return \count($this->cards) > $this->limit;
    }

    /**
     * @return array{main: int, extra: int}
     */
    public function getCounts(): array
    {
        return $this->counts ??= $this->printings->countMainAndExtrasBySet($this->set);
    }

    public function hasMainCards(): bool
    {
        return $this->getCounts()['main'] > 0;
    }

    /**
     * La case « extras » n'a de sens que si l'édition a à la fois une liste principale et des extras.
     */
    public function canToggleExtras(): bool
    {
        return $this->hasMainCards() && $this->getCounts()['extra'] > 0;
    }

    /**
     * @return list<Color|'C'> couleurs, puis « C » pour incolore
     */
    #[ExposeInTemplate]
    public function getColorChoices(): array
    {
        return [...Color::cases(), 'C'];
    }

    /**
     * @return list<Rarity>
     */
    #[ExposeInTemplate]
    public function getRarityChoices(): array
    {
        return [Rarity::Common, Rarity::Uncommon, Rarity::Rare, Rarity::Mythic];
    }
}
