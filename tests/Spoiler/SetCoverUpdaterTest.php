<?php

namespace App\Tests\Spoiler;

use App\Entity\MtgSet;
use App\Scryfall\BulkUpserter;
use App\Scryfall\CardImporter;
use App\Scryfall\SetCoverUpdater;
use App\Scryfall\SetImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SetCoverUpdaterTest extends KernelTestCase
{
    use UpcomingSetTrait;

    public function testPicksMythicBeforeRareAndCommon(): void
    {
        $container = self::getContainer();
        $container->get(BulkUpserter::class)->upsert('mtg_set', [$container->get(SetImporter::class)->map(self::upcomingSet())], ['id']);
        // Lightning Bolt (rare), Sol Ring (commune), Counterspell (mythique)
        $cards = self::upcomingCards();
        $container->get(CardImporter::class)->import($cards);

        self::assertGreaterThanOrEqual(1, $container->get(SetCoverUpdater::class)->update());

        $set = $container->get(EntityManagerInterface::class)->getRepository(MtgSet::class)->findOneBy(['code' => 'tst']);
        self::assertSame('Counterspell', $set->getCoverCardName());
        self::assertSame($cards[2]['image_uris']['art_crop'], $set->getCoverArtUri());
    }
}
