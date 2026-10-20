<?php

namespace App\Tests\Deck;

use App\Deck\DeckListLine;
use App\Deck\DeckListParser;
use App\Enum\DeckZone;
use PHPUnit\Framework\TestCase;

class DeckListParserTest extends TestCase
{
    public function testParsesCommonFormats(): void
    {
        $lines = (new DeckListParser())->parse(<<<'TXT'
            Commander
            1 Atraxa, Praetors' Voice

            Deck
            4 Lightning Bolt (M10) 146
            4x Counterspell
            Fire // Ice
            // un commentaire
            SB: 2 Pyroblast
            Sideboard:
            2 Duress [M19] *F*
            Maybeboard
            1 Sol Ring
            TXT);

        self::assertSame(
            [
                [1, 'Atraxa, Praetors\' Voice', DeckZone::Commander, null, null],
                [4, 'Lightning Bolt', DeckZone::Main, 'M10', '146'],
                [4, 'Counterspell', DeckZone::Main, null, null],
                [1, 'Fire // Ice', DeckZone::Main, null, null],
                [2, 'Pyroblast', DeckZone::Side, null, null],
                [2, 'Duress', DeckZone::Side, 'M19', null],
                [1, 'Sol Ring', DeckZone::Maybe, null, null],
            ],
            array_map(static fn (DeckListLine $l) => [$l->quantity, $l->name, $l->zone, $l->setCode, $l->collectorNumber], $lines),
        );
    }
}
