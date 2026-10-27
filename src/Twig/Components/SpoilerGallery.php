<?php

namespace App\Twig\Components;

use App\Entity\CardPrinting;
use App\Enum\Color;
use App\Enum\Rarity;
use App\Repository\CardPrintingRepository;
use App\Scryfall\SpoilerSynchronizer;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Galerie des dernières cartes révélées, filtrable par édition, rareté et couleur.
 */
#[AsLiveComponent]
class SpoilerGallery
{
    use DefaultActionTrait;

    private const int PAGE_SIZE = 60;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public string $set = '';

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public array $rarities = [];

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public array $colors = [];

    #[LiveProp]
    public int $limit = self::PAGE_SIZE;

    /** @var list<CardPrinting>|null */
    private ?array $spoilers = null;

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

    #[LiveAction]
    public function selectSet(#[LiveArg] string $code): void
    {
        $this->set = $this->set === $code ? '' : $code;
        $this->resetLimit();
    }

    /**
     * Cartes à afficher (une de plus que la limite est chargée pour savoir s'il en reste).
     *
     * @return list<CardPrinting>
     */
    public function getSpoilers(): array
    {
        $this->spoilers ??= $this->printings->findSpoilers(
            $this->since(),
            $this->set ?: null,
            array_values(array_filter($this->rarities, static fn (string $r) => null !== Rarity::tryFrom($r))),
            Color::maskFrom($this->colors),
            \in_array('C', $this->colors, true),
            $this->limit + 1,
        );

        return \array_slice($this->spoilers, 0, $this->limit);
    }

    public function hasMore(): bool
    {
        $this->getSpoilers();

        return \count($this->spoilers) > $this->limit;
    }

    /**
     * Cartes regroupées par jour de révélation.
     *
     * @return array<string, list<CardPrinting>> date Y-m-d => cartes
     */
    public function getSpoilersByDay(): array
    {
        $days = [];
        foreach ($this->getSpoilers() as $printing) {
            $days[$printing->getRevealedAt()->format('Y-m-d')][] = $printing;
        }

        return $days;
    }

    /**
     * @return list<array{code: string, name: string, releasedAt: ?\DateTimeImmutable, iconSvgUri: ?string, cards: int}>
     */
    #[ExposeInTemplate]
    public function getSets(): array
    {
        return $this->printings->findSpoilerSets($this->since());
    }

    /**
     * @return list<Rarity>
     */
    #[ExposeInTemplate]
    public function getRarityChoices(): array
    {
        return [Rarity::Common, Rarity::Uncommon, Rarity::Rare, Rarity::Mythic];
    }

    /**
     * @return list<Color|'C'> couleurs, puis « C » pour incolore
     */
    #[ExposeInTemplate]
    public function getColorChoices(): array
    {
        return [...Color::cases(), 'C'];
    }

    private function since(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('today -%d days', SpoilerSynchronizer::WINDOW_DAYS));
    }
}
