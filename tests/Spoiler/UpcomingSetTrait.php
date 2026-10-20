<?php

namespace App\Tests\Spoiler;

use App\Scryfall\JsonlReader;

/**
 * Transforme les cartes de test en cartes d'une édition fictive « TST » à paraître.
 */
trait UpcomingSetTrait
{
    private const string SET_ID = '00000000-0000-4000-8000-000000000001';

    /**
     * @return array<string, mixed> objet « set » Scryfall
     */
    private static function upcomingSet(): array
    {
        return [
            'id' => self::SET_ID, 'code' => 'tst', 'name' => 'Test Set', 'set_type' => 'expansion',
            'released_at' => (new \DateTimeImmutable('+20 days'))->format('Y-m-d'), 'card_count' => 3,
            'icon_svg_uri' => 'https://svgs.scryfall.io/sets/default.svg', 'digital' => false,
        ];
    }

    /**
     * Trois cartes de l'édition fictive : Lightning Bolt (rare, aperçu il y a 2 jours),
     * Sol Ring (commune, sans aperçu) et Counterspell (mythique, aperçu aujourd'hui).
     *
     * @return list<array<string, mixed>>
     */
    private static function upcomingCards(): array
    {
        $cards = [];
        foreach ([...JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'), ...JsonlReader::read(__DIR__.'/../fixtures/scryfall_deck_cards.jsonl')] as $card) {
            $cards[$card['name']] = $card;
        }

        $make = static function (array $card, int $number, string $rarity, ?string $previewedAt): array {
            $card = array_merge($card, [
                'id' => \sprintf('00000000-0000-4000-9000-%012d', $number),
                'set_id' => self::SET_ID, 'set' => 'tst', 'set_name' => 'Test Set', 'set_type' => 'expansion',
                'collector_number' => (string) $number, 'rarity' => $rarity, 'lang' => 'en', 'digital' => false,
                'released_at' => self::upcomingSet()['released_at'],
            ]);
            unset($card['preview']);
            if ($previewedAt) {
                $card['preview'] = ['source' => 'Wizards of the Coast', 'source_uri' => 'https://example.com/preview', 'previewed_at' => $previewedAt];
            }

            return $card;
        };

        return [
            $make($cards['Lightning Bolt'], 1, 'rare', (new \DateTimeImmutable('-2 days'))->format('Y-m-d')),
            $make($cards['Sol Ring'], 2, 'common', null),
            $make($cards['Counterspell'], 10, 'mythic', (new \DateTimeImmutable('today'))->format('Y-m-d')),
        ];
    }
}
