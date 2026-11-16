<?php

namespace App\Twig\Components;

use App\Collection\OwnershipChecker;
use App\Entity\PreconDeck;
use App\Entity\User;
use App\Repository\PreconDeckRepository;
use Symfony\Bundle\SecurityBundle\Security;
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

    public const int PER_PAGE = 30;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $q = '';

    /** Année de sortie ; null = toutes */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public ?int $year = null;

    #[LiveProp]
    public int $page = 1;

    /** @var list<PreconDeck>|null */
    private ?array $decks = null;

    /** @var array<int, int>|null */
    private ?array $percentages = null;

    public function __construct(
        private readonly PreconDeckRepository $repository,
        private readonly OwnershipChecker $ownership,
        private readonly Security $security,
    ) {
    }

    /**
     * Part de chaque deck affiché déjà possédée par l'utilisateur connecté (vide si personne n'est connecté).
     *
     * @return array<int, int> identifiant du deck => pourcentage
     */
    public function getOwnedPercentages(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->percentages ??= $this->ownership->percentages(
            $this->repository->findCardLines(array_map(static fn (PreconDeck $d) => (int) $d->getId(), $this->getDecks())),
            $user,
        );
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
        $this->decks ??= $this->repository->search($this->q, $this->page * self::PER_PAGE + 1, year: $this->year);

        return \array_slice($this->decks, 0, $this->page * self::PER_PAGE);
    }

    /**
     * Decks chargés regroupés par année de sortie, de la plus récente à la plus ancienne.
     *
     * @return array<int, list<PreconDeck>>
     */
    public function getDecksByYear(): array
    {
        $groups = [];
        foreach ($this->getDecks() as $deck) {
            $groups[(int) $deck->getReleasedAt()->format('Y')][] = $deck;
        }

        return $groups;
    }

    /**
     * @return list<int>
     */
    public function getYears(): array
    {
        return $this->repository->findYears();
    }

    public function hasMore(): bool
    {
        $this->getDecks();

        return \count($this->decks) > $this->page * self::PER_PAGE;
    }
}
