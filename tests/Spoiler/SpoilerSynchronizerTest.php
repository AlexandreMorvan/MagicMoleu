<?php

namespace App\Tests\Spoiler;

use App\Entity\CardPrinting;
use App\Scryfall\BulkUpserter;
use App\Scryfall\CardImporter;
use App\Scryfall\ScryfallClient;
use App\Scryfall\SetCoverUpdater;
use App\Scryfall\SetImporter;
use App\Scryfall\SpoilerSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Uid\Uuid;

class SpoilerSynchronizerTest extends KernelTestCase
{
    use UpcomingSetTrait;

    /** @var list<string> */
    private array $requestedUrls = [];

    public function testImportsSetsThenPaginatedSearchResults(): void
    {
        [$bolt, $solRing, $counterspell] = self::upcomingCards();
        $synchronizer = $this->synchronizer([
            '/sets' => new JsonMockResponse(['data' => [self::upcomingSet()], 'has_more' => false]),
            '/cards/search' => new JsonMockResponse(['data' => [$bolt, $solRing], 'has_more' => true, 'next_page' => 'https://api.scryfall.com/cards/search?page=2']),
            'page=2' => new JsonMockResponse(['data' => [$counterspell], 'has_more' => false]),
        ]);

        $result = $synchronizer->synchronize(new \DateTimeImmutable('2026-10-20'));

        self::assertSame(['sets' => 1, 'cards' => 3], $result);
        self::assertStringContainsString('q=date%3E%3D2026-09-20', $this->requestedUrls[1], 'fenêtre de 30 jours');
        self::assertStringContainsString('order=spoiled', $this->requestedUrls[1]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $boltPrinting = $this->printing($bolt['id']);
        self::assertSame($bolt['preview']['previewed_at'], $boltPrinting->getRevealedAt()->format('Y-m-d'));
        self::assertSame('Wizards of the Coast', $boltPrinting->getPreviewSource());
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $this->printing($solRing['id'])->getRevealedAt()->format('Y-m-d'), 'sans aperçu : date de première apparition');
        self::assertSame('Test Set', $boltPrinting->getSet()->getName());
    }

    public function testPreviewDateReplacesFirstSeenDate(): void
    {
        [, $solRing] = self::upcomingCards();
        $importer = self::getContainer()->get(CardImporter::class);
        $importer->import([$solRing]);

        $solRing['preview'] = ['source' => 'Mashable', 'source_uri' => 'https://example.com', 'previewed_at' => '2026-10-01'];
        $importer->import([$solRing]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('2026-10-01', $this->printing($solRing['id'])->getRevealedAt()->format('Y-m-d'));
    }

    public function testNoResultIsNotAnError(): void
    {
        $synchronizer = $this->synchronizer([
            '/sets' => new JsonMockResponse(['data' => [], 'has_more' => false]),
            '/cards/search' => new JsonMockResponse(['object' => 'error', 'status' => 404, 'details' => 'Your query didn’t match any cards.'], ['http_code' => 404]),
        ]);

        self::assertSame(['sets' => 0, 'cards' => 0], $synchronizer->synchronize());
    }

    /**
     * @param array<string, JsonMockResponse> $responses réponse par fragment d'URL (le plus spécifique en dernier)
     */
    private function synchronizer(array $responses): SpoilerSynchronizer
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($responses) {
            $this->requestedUrls[] = $url;
            foreach (array_reverse($responses, true) as $fragment => $response) {
                if (str_contains($url, $fragment)) {
                    return $response;
                }
            }
            self::fail('Requête inattendue : '.$url);
        }, 'https://api.scryfall.com');

        $client = new ScryfallClient($http, new RateLimiterFactory(['id' => 'test', 'policy' => 'no_limit'], new InMemoryStorage()));
        $container = self::getContainer();

        return new SpoilerSynchronizer(
            $client,
            new SetImporter($client, $container->get(BulkUpserter::class)),
            $container->get(CardImporter::class),
            $container->get(SetCoverUpdater::class),
        );
    }

    private function printing(string $id): CardPrinting
    {
        return self::getContainer()->get(EntityManagerInterface::class)->find(CardPrinting::class, Uuid::fromString($id));
    }
}
