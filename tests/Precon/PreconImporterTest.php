<?php

namespace App\Tests\Precon;

use App\Entity\PreconDeck;
use App\Enum\DeckZone;
use App\Repository\PreconDeckRepository;
use App\Tests\Deck\DeckTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

class PreconImporterTest extends KernelTestCase
{
    use DeckTestTrait;
    use PreconTestTrait;

    protected function setUp(): void
    {
        self::importDeckFixtures();
    }

    public function testImportsOnlyCommanderDecks(): void
    {
        $result = $this->importPrecons();

        self::assertSame(2, $result->imported);
        self::assertSame(1, $result->unresolvedCards);
        self::assertSame(['DeckList.json', 'decks/BreedLethality_C16.json', 'decks/PartyTime_CLB.json'], $this->mtgjsonRequests);
        self::assertSame(['Party Time', 'Breed Lethality'], array_map(static fn (PreconDeck $d) => $d->getName(), $this->repository()->search('', 10)));
    }

    public function testResolvesExactPrintingsAndGroupsDuplicates(): void
    {
        $this->importPrecons();
        $deck = $this->deck('BreedLethality_C16');

        self::assertSame('c16', $deck->getSetCode());
        self::assertSame('c16', $deck->getSet()?->getCode());
        self::assertSame('2016-11-11', $deck->getReleasedAt()->format('Y-m-d'));
        self::assertSame('Thrasios, Triton Hero & Tymna the Weaver', $deck->getCommanderNames());
        self::assertNotNull($deck->getCoverUri());
        self::assertSame(99, $deck->getCardCount(), '100 cartes moins la carte introuvable');
        self::assertSame(1, $deck->getUnresolvedCount());

        $commanders = $deck->getCardsIn(DeckZone::Commander);
        self::assertCount(2, $commanders);
        self::assertSame(self::THRASIOS, $commanders[0]->getPrinting()?->getId()->toRfc4122());

        $main = [];
        foreach ($deck->getCardsIn(DeckZone::Main) as $line) {
            $main[$line->getOracleCard()->getName()] = [$line->getQuantity(), $line->getPrinting()?->getId()->toRfc4122()];
        }
        self::assertSame(['Forest' => [96, self::FOREST], 'Counterspell' => [1, null]], $main);
    }

    public function testOnlyIncompleteDecksAreDownloadedAgain(): void
    {
        $this->importPrecons();
        $this->mtgjsonRequests = [];

        $result = $this->importPrecons();

        self::assertSame(['DeckList.json', 'decks/BreedLethality_C16.json'], $this->mtgjsonRequests);
        self::assertSame(1, $result->skipped);
        self::assertSame(99, $this->deck('BreedLethality_C16')->getCardCount(), 'la liste est remplacée, pas ajoutée');

        $this->mtgjsonRequests = [];
        self::assertSame(2, $this->importPrecons(force: true)->imported);
    }

    public function testFailedDeckDownloadDoesNotStopTheImport(): void
    {
        $this->mtgjsonOverrides['decks/BreedLethality_C16.json'] = new MockResponse('', ['http_code' => 503]);

        $result = $this->importPrecons();

        self::assertSame(1, $result->failed);
        self::assertSame(1, $result->imported);
        self::assertNull($this->repository()->findOneBy(['code' => 'BreedLethality_C16']));
    }

    private function deck(string $code): PreconDeck
    {
        return $this->repository()->findOneBy(['code' => $code]);
    }

    private function repository(): PreconDeckRepository
    {
        return self::getContainer()->get(PreconDeckRepository::class);
    }
}
