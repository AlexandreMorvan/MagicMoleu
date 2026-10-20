<?php

namespace App\Tests\Deck;

use App\Deck\DeckExporter;
use App\Deck\DeckImporter;
use App\Entity\Deck;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DeckImportExportTest extends KernelTestCase
{
    use DeckTestTrait;

    public function testImportResolvesNamesAndExportRoundTrips(): void
    {
        self::importDeckFixtures();
        $container = self::getContainer();
        $deck = (new Deck((new User())->setUsername('t')->setEmail('t@example.com')))->setFormat(Format::Modern);

        $unresolved = $container->get(DeckImporter::class)->import($deck, <<<'TXT'
            Deck
            4 Lightning Bolt (M10) 146
            2 Foudre
            1 Delver of Secrets
            1 Fire // Ice
            3 Carte Imaginaire
            Sideboard
            2 Counterspell
            TXT);

        self::assertSame(['3 Carte Imaginaire'], $unresolved);
        self::assertSame(8, $deck->countIn(DeckZone::Main));
        self::assertSame(6, $deck->findCard(self::oracle('Lightning Bolt'), DeckZone::Main)->getQuantity(), 'Lightning Bolt et Foudre sont la même carte');
        self::assertSame(2, $deck->countIn(DeckZone::Side));
        $bolt = $deck->findCard(self::oracle('Lightning Bolt'), DeckZone::Main);
        self::assertSame('m10', $bolt->getPrinting()->getSet()->getCode(), 'impression demandée');
        self::assertNotNull($deck->findCard(self::oracle('Counterspell'), DeckZone::Side)->getPrinting(), 'impression par défaut');

        $export = $container->get(DeckExporter::class)->export($deck);
        self::assertStringContainsString("Deck\n1 Delver of Secrets (ISD)", $export);
        self::assertStringContainsString("1 Fire // Ice (MH2)", $export);
        self::assertStringContainsString("6 Lightning Bolt (M10) 146", $export);
        self::assertStringContainsString("Sideboard\n2 Counterspell", $export);

        $copy = (new Deck($deck->getOwner()))->setFormat(Format::Modern);
        self::assertSame([], $container->get(DeckImporter::class)->import($copy, $export));
        self::assertSame($export, $container->get(DeckExporter::class)->export($copy));
    }
}
