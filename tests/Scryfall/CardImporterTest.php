<?php

namespace App\Tests\Scryfall;

use App\Entity\CardFace;
use App\Entity\CardLegality;
use App\Entity\CardPrinting;
use App\Entity\MtgSet;
use App\Entity\OracleCard;
use App\Enum\Color;
use App\Enum\Format;
use App\Enum\LegalityStatus;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CardImporterTest extends KernelTestCase
{
    private const string FIXTURE = __DIR__.'/../fixtures/scryfall_cards.jsonl';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $count = self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(self::FIXTURE));

        self::assertSame(10, $count);
    }

    public function testFlagsExtraPrintings(): void
    {
        $extra = fn (string $name) => $this->em->getRepository(CardPrinting::class)->createQueryBuilder('p')
            ->join('p.oracleCard', 'o')->where('o.name LIKE :name')->setParameter('name', $name.'%')
            ->getQuery()->getSingleResult()->isExtra();

        self::assertTrue($extra('Adrix and Nev'), 'Secret Lair sans bordure, poster');
        self::assertFalse($extra('Lightning Bolt'));
        self::assertFalse($extra('Badgermole Cub'), 'le type de promo « universesbeyond » n\'est pas un traitement');
    }

    public function testImportsOneRowPerTable(): void
    {
        self::assertSame(10, $this->countRows(OracleCard::class));
        self::assertSame(10, $this->countRows(CardPrinting::class));
        // transform x2, split, adventure, modal_dfc ; la carte reversible n'a pas de faces oracle
        self::assertSame(10, $this->countRows(CardFace::class));
        self::assertGreaterThan(0, $this->countRows(MtgSet::class));
    }

    public function testSimpleCard(): void
    {
        $bolt = $this->oracle('Lightning Bolt');

        self::assertSame('{R}', $bolt->getManaCost());
        self::assertSame(1.0, $bolt->getCmc());
        self::assertSame(Color::Red->bit(), $bolt->getColors());
        self::assertSame([Color::Red], $bolt->getColorIdentityList());
        self::assertStringContainsString('3 damage', $bolt->getOracleText());
        self::assertNotNull($bolt->getImageUri());

        $printing = $bolt->getPrintings()->first();
        self::assertSame('m10', $printing->getSet()->getCode());
        self::assertSame('en', $printing->getLang());
        self::assertSame($bolt->getImageUri(), $printing->getImageUri());
    }

    public function testMultiFaceCards(): void
    {
        $delver = $this->oracle('Delver of Secrets // Insectile Aberration');
        self::assertSame('transform', $delver->getLayout());
        self::assertSame('{U}', $delver->getManaCost());
        self::assertSame(['Delver of Secrets', 'Insectile Aberration'], $delver->getFaces()->map(fn (CardFace $f) => $f->getName())->toArray());
        self::assertNotNull($delver->getPrintings()->first()->getImageUri('normal', 1), 'image du verso');

        $fireIce = $this->oracle('Fire // Ice');
        self::assertSame(Color::maskFrom(['R', 'U']), $fireIce->getColors());
        self::assertSame([[Color::Red], [Color::Blue]], $fireIce->getFaces()->map(fn (CardFace $f) => $f->getColorList())->toArray());

        $reversible = $this->em->getRepository(OracleCard::class)->findOneBy(['layout' => 'normal', 'name' => 'Adrix and Nev, Twincasters']);
        self::assertNotNull($reversible, 'une carte reversible est rattachée à la carte oracle de sa face');
    }

    public function testLegalities(): void
    {
        $bolt = $this->oracle('Lightning Bolt');
        self::assertSame(LegalityStatus::Legal, $this->legality($bolt, Format::Modern));
        self::assertSame(LegalityStatus::NotLegal, $this->legality($bolt, Format::Standard));

        self::assertSame(LegalityStatus::Banned, $this->legality($this->oracle('Badgermole Cub'), Format::Standard));
    }

    public function testReimportIsIdempotent(): void
    {
        self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(self::FIXTURE));

        self::assertSame(10, $this->countRows(OracleCard::class));
        self::assertSame(10, $this->countRows(CardPrinting::class));
        self::assertSame(10, $this->countRows(CardFace::class));
    }

    /**
     * Scryfall renumérote parfois une édition : deux impressions peuvent échanger leurs numéros
     * d'un import à l'autre, ce qui violait temporairement l'unicité (édition, numéro, langue).
     */
    public function testReimportHandlesSwappedCollectorNumbers(): void
    {
        [$bolt, $copy] = $this->boltAndCopyInSameSet('999');
        $importer = self::getContainer()->get(CardImporter::class);
        $importer->import([$copy]);

        [$bolt['collector_number'], $copy['collector_number']] = [$copy['collector_number'], $bolt['collector_number']];
        $importer->import([$bolt, $copy]);

        self::assertSame('999', $this->printing($bolt['id'])->getCollectorNumber());
        self::assertSame($copy['collector_number'], $this->printing($copy['id'])->getCollectorNumber());
    }

    /**
     * Une nouvelle impression prend le numéro d'une impression que l'import ne contient plus :
     * l'ancienne est conservée (elle peut être en collection) sous un numéro provisoire.
     */
    public function testNewPrintingTakesTheNumberOfAPrintingAbsentFromTheImport(): void
    {
        [$bolt, $copy] = $this->boltAndCopyInSameSet($this->firstBolt()['collector_number']);

        self::getContainer()->get(CardImporter::class)->import([$copy]);

        self::assertSame($bolt['collector_number'], $this->printing($copy['id'])->getCollectorNumber());
        self::assertStringStartsWith('~', $this->printing($bolt['id'])->getCollectorNumber());
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>} Lightning Bolt et une autre impression de la même édition
     */
    private function boltAndCopyInSameSet(string $copyNumber): array
    {
        $bolt = $this->firstBolt();
        $copy = ['id' => '0f0f0f0f-0000-4000-8000-000000000001', 'collector_number' => $copyNumber] + $bolt;

        return [$bolt, $copy];
    }

    /**
     * @return array<string, mixed>
     */
    private function firstBolt(): array
    {
        foreach (JsonlReader::read(self::FIXTURE) as $card) {
            if ('Lightning Bolt' === $card['name']) {
                return $card;
            }
        }

        throw new \LogicException('Lightning Bolt absent de la fixture');
    }

    private function printing(string $id): CardPrinting
    {
        $this->em->clear();

        return $this->em->find(CardPrinting::class, $id);
    }

    private function oracle(string $name): OracleCard
    {
        $this->em->clear();

        return $this->em->getRepository(OracleCard::class)->findOneBy(['name' => $name])
            ?? self::fail(\sprintf('Carte "%s" introuvable.', $name));
    }

    private function legality(OracleCard $card, Format $format): ?LegalityStatus
    {
        return $this->em->getRepository(CardLegality::class)->findOneBy(['oracleCard' => $card, 'format' => $format])?->getStatus();
    }

    /** @param class-string $class */
    private function countRows(string $class): int
    {
        return $this->em->getRepository($class)->count();
    }
}
