<?php

namespace App\Scryfall;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Accès à l'API Scryfall en respectant sa limite de débit.
 *
 * @see https://scryfall.com/docs/api
 */
class ScryfallClient
{
    public function __construct(
        #[Target('scryfall.client')]
        private readonly HttpClientInterface $client,
        #[Target('scryfall')]
        private readonly RateLimiterFactoryInterface $limiterFactory,
    ) {
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $this->limiterFactory->create('api')->reserve()->wait();

        return $this->client->request('GET', $path, ['query' => $query])->toArray();
    }

    /**
     * Parcourt une liste paginée Scryfall (`has_more` / `next_page`) et en renvoie chaque élément.
     *
     * @param array<string, scalar> $query
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function paginate(string $path, array $query = []): \Generator
    {
        $page = $this->get($path, $query);
        while (true) {
            yield from $page['data'];

            if (!($page['has_more'] ?? false)) {
                return;
            }
            // next_page est une URL absolue qui contient déjà les paramètres
            $page = $this->get($page['next_page']);
        }
    }

    /**
     * @param string $type ex. « default-cards », « all-cards »
     *
     * @return array{jsonl_download_uri: string, updated_at: string, compressed_size: int}
     */
    public function getBulkData(string $type): array
    {
        return $this->get('/bulk-data/'.$type);
    }

    /**
     * Télécharge un fichier (bulk data) en streaming, sans le charger en mémoire.
     */
    public function download(string $uri, string $targetPath): void
    {
        $tmpPath = $targetPath.'.part';
        $handle = fopen($tmpPath, 'w');
        try {
            $response = $this->client->request('GET', $uri, ['timeout' => 120]);
            foreach ($this->client->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }

        rename($tmpPath, $targetPath);
    }
}
