<?php

namespace App\Twig\Components;

use App\Enum\Color;
use App\Enum\Format;
use App\Enum\Rarity;
use App\Search\CardSearch as CardSearchService;
use App\Search\CardSearchCriteria;
use App\Search\CardSearchResult;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Recherche avancée de cartes. Chaque filtre est reflété dans l'URL pour pouvoir partager une recherche.
 */
#[AsLiveComponent]
class CardSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $q = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $text = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $type = '';

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public array $colors = [];

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $colorMode = CardSearchCriteria::COLORS_INCLUDE;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public bool $identity = false;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?float $cmcMin = null;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?float $cmcMax = null;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?int $powMin = null;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?int $powMax = null;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?int $touMin = null;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?int $touMax = null;

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public array $rarities = [];

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $set = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $format = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $legality = 'legal';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $keyword = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $sort = 'name';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $dir = 'asc';

    #[LiveProp(writable: true, url: true)]
    public string $view = 'grid';

    #[LiveProp(url: true)]
    public int $page = 1;

    private ?CardSearchResult $result = null;

    public function __construct(private readonly CardSearchService $search)
    {
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    #[LiveAction]
    public function toggleDirection(): void
    {
        $this->dir = 'asc' === $this->dir ? 'desc' : 'asc';
        $this->resetPage();
    }

    #[LiveAction]
    public function setView(#[LiveArg] string $view): void
    {
        $this->view = 'list' === $view ? 'list' : 'grid';
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        foreach ((new \ReflectionClass($this))->getProperties() as $property) {
            if ($property->getAttributes(LiveProp::class) && 'view' !== $property->getName()) {
                $property->setValue($this, $property->getDefaultValue());
            }
        }
    }

    public function getResult(): CardSearchResult
    {
        return $this->result ??= $this->search->search($this->criteria(), $this->page);
    }

    public function hasFilters(): bool
    {
        return $this->criteria() != new CardSearchCriteria(sort: $this->sort, direction: $this->dir);
    }

    #[ExposeInTemplate]
    public function getColorChoices(): array
    {
        return [...Color::cases(), 'C'];
    }

    #[ExposeInTemplate]
    public function getRarityChoices(): array
    {
        return [Rarity::Common, Rarity::Uncommon, Rarity::Rare, Rarity::Mythic];
    }

    #[ExposeInTemplate]
    public function getFormatChoices(): array
    {
        return Format::cases();
    }

    private function criteria(): CardSearchCriteria
    {
        return new CardSearchCriteria(
            name: $this->q,
            text: $this->text,
            type: $this->type,
            colors: $this->colors,
            colorMode: $this->colorMode,
            useColorIdentity: $this->identity,
            cmcMin: $this->cmcMin,
            cmcMax: $this->cmcMax,
            powerMin: $this->powMin,
            powerMax: $this->powMax,
            toughnessMin: $this->touMin,
            toughnessMax: $this->touMax,
            rarities: $this->rarities,
            set: $this->set,
            format: $this->format,
            legality: $this->legality,
            keyword: $this->keyword,
            sort: $this->sort,
            direction: $this->dir,
        );
    }
}
