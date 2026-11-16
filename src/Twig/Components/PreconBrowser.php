<?php

namespace App\Twig\Components;

use App\Entity\PreconDeck;
use App\Repository\PreconDeckRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Catalogue des decks préconstruits, du plus récent au plus ancien, avec recherche et « Voir plus ».
 */
#[AsLiveComponent]
class PreconBrowser
{
    use DefaultActionTrait;

    public const int PER_PAGE = 24;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $q = '';

    #[LiveProp]
    public int $page = 1;

    /** @var list<PreconDeck>|null */
    private ?array $decks = null;

    public function __construct(private readonly PreconDeckRepository $repository)
    {
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    #[LiveAction]
    public function more(): void
    {
        ++$this->page;
    }

    /**
     * @return list<PreconDeck>
     */
    public function getDecks(): array
    {
        $this->decks ??= $this->repository->search($this->q, $this->page * self::PER_PAGE + 1);

        return \array_slice($this->decks, 0, $this->page * self::PER_PAGE);
    }

    public function hasMore(): bool
    {
        $this->getDecks();

        return \count($this->decks) > $this->page * self::PER_PAGE;
    }
}
