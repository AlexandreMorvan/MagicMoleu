<?php

namespace App\Combo;

use App\Entity\OracleCard;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Combos connus pour une carte, d'après l'API publique de Commander Spellbook.
 * Les réponses sont gardées en cache une journée ; une erreur n'est jamais mise en cache.
 *
 * @see https://backend.commanderspellbook.com/
 */
class CommanderSpellbook
{
    public const int LIMIT = 50;
    private const int CACHE_TTL = 86400;

    public function __construct(
        #[Target('commander_spellbook.client')]
        private readonly HttpClientInterface $client,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ComboSourceUnavailableException
     */
    public function findCombos(OracleCard $card): ComboList
    {
        return $this->cache->get('combos.'.$card->getId()->toRfc4122(), function (ItemInterface $item) use ($card): ComboList {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->fetch($card->getName());
        });
    }

    private function fetch(string $cardName): ComboList
    {
        $query = \sprintf('card="%s"', str_replace('"', '', $cardName));

        try {
            $data = $this->client->request('GET', '/variants/', ['query' => [
                'q' => $query,
                'ordering' => '-popularity',
                'limit' => self::LIMIT,
            ]])->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Commander Spellbook indisponible : {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            throw new ComboSourceUnavailableException('Commander Spellbook est indisponible.', previous: $e);
        }

        if (!\is_array($data['results'] ?? null)) {
            throw new ComboSourceUnavailableException('Réponse inattendue de Commander Spellbook.');
        }

        return new ComboList(
            array_map($this->mapCombo(...), $data['results']),
            null !== ($data['next'] ?? null),
            'https://commanderspellbook.com/search/?q='.rawurlencode($query),
        );
    }

    /**
     * @param array<string, mixed> $variant
     */
    private function mapCombo(array $variant): Combo
    {
        $cards = array_map(static fn (array $use) => new ComboCard(
            $use['card']['name'],
            Uuid::isValid($use['card']['oracleId'] ?? '') ? Uuid::fromString($use['card']['oracleId']) : null,
            $use['card']['imageUriFrontNormal'] ?? null,
        ), $variant['uses'] ?? []);

        $results = array_map(static fn (array $produce) => $produce['feature']['name'], $variant['produces'] ?? []);

        $prerequisites = trim(implode("\n", array_filter([
            $variant['easyPrerequisites'] ?? '',
            $variant['notablePrerequisites'] ?? '',
        ])));

        return new Combo(
            (string) $variant['id'],
            $cards,
            $results,
            array_values(array_filter(array_map(trim(...), explode("\n", $variant['description'] ?? '')))),
            $prerequisites,
            $variant['manaNeeded'] ?? '',
            $variant['identity'] ?? 'C',
            (int) ($variant['popularity'] ?? 0),
        );
    }
}
