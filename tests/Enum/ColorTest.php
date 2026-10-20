<?php

namespace App\Tests\Enum;

use App\Enum\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function testMaskFromScryfallSymbols(): void
    {
        self::assertSame(0, Color::maskFrom([]));
        self::assertSame(8, Color::maskFrom(['R']));
        self::assertSame(31, Color::maskFrom(['W', 'U', 'B', 'R', 'G']));
        self::assertSame(Color::maskFrom(['G', 'U']), Color::maskFrom(['U', 'G']));
        self::assertSame(2, Color::maskFrom(['U', 'U', 'C']), 'doublons et incolore ignorés');
    }

    public function testFromMaskKeepsWubrgOrder(): void
    {
        self::assertSame([Color::Blue, Color::Red], Color::fromMask(Color::maskFrom(['R', 'U'])));
        self::assertSame([], Color::fromMask(0));
    }

    public function testColorIdentitySubsetCheck(): void
    {
        // Même calcul que la requête SQL de validation Commander : identité & ~commandant = 0
        $commander = Color::maskFrom(['B', 'G']);

        self::assertSame(0, Color::maskFrom(['G']) & ~$commander);
        self::assertSame(0, Color::maskFrom([]) & ~$commander);
        self::assertNotSame(0, Color::maskFrom(['G', 'U']) & ~$commander);
    }
}
