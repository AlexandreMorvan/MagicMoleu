<?php

namespace App\Deck;

use App\Enum\DeckZone;

/**
 * Lit une liste de deck au format texte usuel (MTG Arena, Moxfield, MTGO…) :
 *
 *     Commander
 *     1 Atraxa, Praetors' Voice
 *     Deck
 *     4 Lightning Bolt (M10) 146
 *     4x Counterspell
 *     SB: 2 Pyroblast
 *     Sideboard
 *     2 Duress
 */
final class DeckListParser
{
    private const array SECTION_HEADERS = [
        'commander' => DeckZone::Commander, 'commandant' => DeckZone::Commander, 'commanders' => DeckZone::Commander,
        'deck' => DeckZone::Main, 'main' => DeckZone::Main, 'mainboard' => DeckZone::Main,
        'sideboard' => DeckZone::Side, 'side' => DeckZone::Side, 'réserve' => DeckZone::Side, 'companion' => DeckZone::Side,
        'maybeboard' => DeckZone::Maybe, 'maybe' => DeckZone::Maybe, 'considering' => DeckZone::Maybe,
    ];

    private const string LINE_PATTERN = '/^(?:(?<sb>SB:)\s*)?(?:(?<qty>\d+)\s*x?\s+)?(?<name>.+?)(?:\s+[\(\[](?<set>[A-Za-z0-9]{2,6})[\)\]](?:\s+(?<number>[^\s*]+))?)?(?:\s+\*[A-Za-z]+\*)*\s*$/u';

    /**
     * @return list<DeckListLine>
     */
    public function parse(string $text): array
    {
        $zone = DeckZone::Main;
        $lines = [];

        foreach (preg_split('/\R/u', $text) as $raw) {
            $line = trim($raw);
            if ('' === $line) {
                continue;
            }

            $header = mb_strtolower(rtrim(ltrim($line, '/# '), ':'));
            if (isset(self::SECTION_HEADERS[$header])) {
                $zone = self::SECTION_HEADERS[$header];
                continue;
            }
            if (str_starts_with($line, '#') || str_starts_with($line, '//') || !preg_match(self::LINE_PATTERN, $line, $m)) {
                continue;
            }

            $lines[] = new DeckListLine(
                quantity: max(1, (int) ($m['qty'] ?: 1)),
                name: trim($m['name']),
                zone: '' !== $m['sb'] ? DeckZone::Side : $zone,
                setCode: ($m['set'] ?? '') ?: null,
                collectorNumber: ($m['number'] ?? '') ?: null,
                raw: $line,
            );
        }

        return $lines;
    }
}
