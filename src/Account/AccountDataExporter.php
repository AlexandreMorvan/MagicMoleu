<?php

namespace App\Account;

use App\Entity\CardCollection;
use App\Entity\CollectionCard;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\User;
use App\Enum\Color;
use App\Repository\CardCollectionRepository;
use App\Repository\DeckRepository;
use App\Repository\UserAchievementRepository;

/**
 * Export des données d'un compte (droit à la portabilité) : profil, decks, collections et succès.
 */
class AccountDataExporter
{
    public function __construct(
        private readonly DeckRepository $decks,
        private readonly CardCollectionRepository $collections,
        private readonly UserAchievementRepository $achievements,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exportedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'account' => [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
                'bio' => $user->getBio(),
                'favoriteColors' => array_map(static fn (Color $c) => $c->value, $user->getFavoriteColorList()),
                'preferredFormat' => $user->getPreferredFormat()?->value,
                'avatar' => $user->getAvatarCard()?->getName(),
            ],
            'decks' => array_map($this->deck(...), array_map(
                fn (Deck $deck) => $this->decks->findWithCards((int) $deck->getId()) ?? $deck,
                $this->decks->findByOwner($user),
            )),
            'collections' => array_map($this->collection(...), $this->collections->findByOwnerWithCards($user)),
            'achievements' => array_values(array_map(static fn ($a) => [
                'achievement' => $a->getAchievement()->value,
                'label' => $a->getAchievement()->label(),
                'unlockedAt' => $a->getUnlockedAt()->format(\DATE_ATOM),
            ], $this->achievements->findUnlockedBy($user))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deck(Deck $deck): array
    {
        return [
            'name' => $deck->getName(),
            'format' => $deck->getFormat()->value,
            'public' => $deck->isPublic(),
            'description' => $deck->getDescription(),
            'updatedAt' => $deck->getUpdatedAt()->format(\DATE_ATOM),
            'cards' => array_values(array_map(static fn (DeckCard $c) => [
                'zone' => $c->getZone()->value,
                'quantity' => $c->getQuantity(),
                'name' => $c->getOracleCard()->getName(),
                'set' => $c->getPrinting()?->getSet()->getCode(),
                'collectorNumber' => $c->getPrinting()?->getCollectorNumber(),
            ], $deck->getCards()->toArray())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collection(CardCollection $collection): array
    {
        return [
            'name' => $collection->getName(),
            'description' => $collection->getDescription(),
            'updatedAt' => $collection->getUpdatedAt()->format(\DATE_ATOM),
            'cards' => array_values(array_map(static fn (CollectionCard $c) => [
                'quantity' => $c->getQuantity(),
                'name' => $c->getOracleCard()->getName(),
                'set' => $c->getPrinting()->getSet()->getCode(),
                'collectorNumber' => $c->getPrinting()->getCollectorNumber(),
                'scryfallId' => $c->getPrinting()->getId()->toRfc4122(),
                'foil' => $c->isFoil(),
                'language' => $c->getLang(),
                'condition' => $c->getCondition()->value,
                'purchasePrice' => $c->getPurchasePrice(),
                'purchaseCurrency' => $c->getPurchaseCurrency(),
                'addedAt' => $c->getAddedAt()->format(\DATE_ATOM),
            ], $collection->getCards()->toArray())),
        ];
    }
}
