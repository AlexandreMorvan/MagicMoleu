<?php

namespace App\Collection;

use App\Entity\CardPrinting;
use App\Entity\Deck;
use App\Entity\OracleCard;
use App\Entity\PreconDeck;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Repository\CardCollectionRepository;

/**
 * Compare une liste de cartes aux collections d'un utilisateur : ce qu'il possède, ce qui lui manque et à quel prix.
 * Toutes impressions confondues : un exemplaire de n'importe quelle édition fait l'affaire.
 */
class OwnershipChecker
{
    /** @var array<int, array<string, int>> exemplaires possédés, par utilisateur (une requête par requête HTTP) */
    private array $owned = [];

    public function __construct(private readonly CardCollectionRepository $collections)
    {
    }

    /**
     * Cartes jouées du deck (commandant, deck principal, réserve), sans la zone « peut-être ».
     */
    public function forDeck(Deck $deck, User $user): Ownership
    {
        $lines = [];
        foreach ($deck->getCardsIn(DeckZone::Commander, DeckZone::Main, DeckZone::Side) as $line) {
            $lines[] = [$line->getOracleCard(), $line->getQuantity(), $line->getUnitPrice()];
        }

        return $this->check($lines, $user);
    }

    public function forPrecon(PreconDeck $precon, User $user): Ownership
    {
        $lines = [];
        foreach ($precon->getCards() as $line) {
            $lines[] = [$line->getOracleCard(), $line->getQuantity(), self::price($line->getPrinting())];
        }

        return $this->check($lines, $user);
    }

    /**
     * Pourcentage possédé de plusieurs listes à la fois, sans charger les entités (vignettes du catalogue).
     *
     * @param array<int, list<array{card: string, quantity: int, basic: bool}>> $lists identifiant => lignes
     *
     * @return array<int, int> identifiant => pourcentage
     */
    public function percentages(array $lists, User $user): array
    {
        $owned = $this->owned($user);
        $percentages = [];
        foreach ($lists as $id => $lines) {
            $needed = [];
            foreach ($lines as $line) {
                if (!$line['basic']) {
                    $needed[$line['card']] = ($needed[$line['card']] ?? 0) + $line['quantity'];
                }
            }
            $total = array_sum($needed);
            $have = 0;
            foreach ($needed as $card => $quantity) {
                $have += min($quantity, $owned[$card] ?? 0);
            }
            $percentages[$id] = $total > 0 ? (int) floor($have * 100 / $total) : 100;
        }

        return $percentages;
    }

    /**
     * @param list<array{OracleCard, int, ?float}> $lines carte, quantité, prix unitaire
     */
    private function check(array $lines, User $user): Ownership
    {
        $owned = $this->owned($user);

        // Une même carte peut figurer dans plusieurs zones : on raisonne sur la quantité totale nécessaire
        $needed = [];
        foreach ($lines as [$card, $quantity, $price]) {
            if ($card->isBasicLand()) {
                continue;
            }
            $key = $card->getId()->toRfc4122();
            $needed[$key] ??= ['card' => $card, 'quantity' => 0, 'price' => $price];
            $needed[$key]['quantity'] += $quantity;
            $needed[$key]['price'] ??= $price;
        }

        $total = $have = 0;
        $missing = [];
        foreach ($needed as $key => $line) {
            $total += $line['quantity'];
            $available = min($line['quantity'], $owned[$key] ?? 0);
            $have += $available;
            if ($available < $line['quantity']) {
                $missing[] = new MissingCard($line['card'], $line['quantity'] - $available, $line['price']);
            }
        }

        usort($missing, static fn (MissingCard $a, MissingCard $b) => [$b->unitPrice ?? -1, $a->card->getName()] <=> [$a->unitPrice ?? -1, $b->card->getName()]);

        return new Ownership($total, $have, $missing);
    }

    /**
     * @return array<string, int>
     */
    private function owned(User $user): array
    {
        return $this->owned[$user->getId()] ??= $this->collections->countOwnedByCard($user);
    }

    private static function price(?CardPrinting $printing): ?float
    {
        $prices = $printing?->getPrices() ?? [];
        $price = $prices['eur'] ?? $prices['usd'] ?? null;

        return null !== $price ? (float) $price : null;
    }
}
