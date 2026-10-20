<?php

namespace App\Scryfall;

use Symfony\Component\HttpClient\Exception\ClientException;

/**
 * Récupère depuis l'API Scryfall les cartes des éditions récentes et à venir,
 * sans attendre le bulk quotidien : les spoilers apparaissent dans l'heure.
 */
class SpoilerSynchronizer
{
    /** Fenêtre couverte : les éditions sorties depuis ce nombre de jours, et toutes celles à venir. */
    public const int WINDOW_DAYS = 30;

    public function __construct(
        private readonly ScryfallClient $client,
        private readonly SetImporter $setImporter,
        private readonly CardImporter $cardImporter,
        private readonly SetCoverUpdater $coverUpdater,
    ) {
    }

    /**
     * @return array{sets: int, cards: int}
     */
    public function synchronize(?\DateTimeImmutable $today = null): array
    {
        // Les nouvelles éditions doivent exister avant leurs cartes (icône, date de sortie…)
        $sets = $this->setImporter->import();

        $since = ($today ?? new \DateTimeImmutable('today'))->modify(\sprintf('-%d days', self::WINDOW_DAYS));
        $cards = $this->cardImporter->import($this->search(\sprintf('date>=%s', $since->format('Y-m-d'))));
        if ($cards > 0) {
            $this->coverUpdater->update();
        }

        return ['sets' => $sets, 'cards' => $cards];
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function search(string $query): \Generator
    {
        try {
            yield from $this->client->paginate('/cards/search', ['q' => $query, 'unique' => 'prints', 'order' => 'spoiled']);
        } catch (ClientException $e) {
            // Scryfall répond 404 quand la recherche ne trouve aucune carte
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
        }
    }
}
