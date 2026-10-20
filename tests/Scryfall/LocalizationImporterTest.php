<?php

namespace App\Tests\Scryfall;

use App\Entity\CardLocalization;
use App\Entity\OracleCard;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Scryfall\LocalizationImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LocalizationImporterTest extends KernelTestCase
{
    public function testImportsFrenchNamesForKnownCards(): void
    {
        $container = self::getContainer();
        $container->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));

        $count = $container->get(LocalizationImporter::class)->import(
            JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards_fr.jsonl'),
            'fr',
        );

        // Deux impressions françaises de Foudre → une seule traduction ; la carte anglaise est ignorée
        self::assertSame(1, $count);

        $em = $container->get(EntityManagerInterface::class);
        $bolt = $em->getRepository(OracleCard::class)->findOneBy(['name' => 'Lightning Bolt']);
        $fr = $em->getRepository(CardLocalization::class)->findOneBy(['oracleCard' => $bolt, 'lang' => 'fr']);

        self::assertSame('Foudre', $fr->getPrintedName());
        self::assertStringContainsString('3 blessures', $fr->getPrintedText());
    }
}
