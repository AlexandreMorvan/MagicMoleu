<?php

namespace App\Combo;

use App\Entity\Deck;
use App\Entity\OracleCard;
use App\Enum\DeckZone;
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
    public const int ALMOST_LIMIT = 15;
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

    /**
     * Combos contenus dans un deck (commandant, deck principal et réserve), et ceux à une carte près.
     *
     * @throws ComboSourceUnavailableException
     */
    public function findDeckCombos(Deck $deck): DeckCombos
    {
        $commanders = $main = [];
        foreach ($deck->getCardsIn(DeckZone::Commander, DeckZone::Main, DeckZone::Side) as $line) {
            $entry = ['card' => $line->getOracleCard()->getName(), 'quantity' => $line->getQuantity()];
            if (DeckZone::Commander === $line->getZone()) {
                $commanders[] = $entry;
            } else {
                $main[] = $entry;
            }
        }
        $names = array_values(array_unique(array_column([...$commanders, ...$main], 'card')));
        if (!$names) {
            return new DeckCombos([], [], 0, []);
        }

        // Clé de cache : la liste triée, indépendante de l'ordre et du deck (deux decks identiques partagent la réponse)
        $key = [$commanders, $main];
        array_walk($key, static fn (array &$list) => usort($list, static fn (array $a, array $b) => $a['card'] <=> $b['card']));

        return $this->cache->get('deck-combos.'.hash('xxh128', serialize($key)), function (ItemInterface $item) use ($commanders, $main, $names): DeckCombos {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $data = $this->client->request('POST', '/find-my-combos', ['json' => ['commanders' => $commanders, 'main' => $main]])->toArray();
            } catch (ExceptionInterface $e) {
                $this->logger->warning('Commander Spellbook indisponible : {message}', ['message' => $e->getMessage(), 'exception' => $e]);

                throw new ComboSourceUnavailableException('Commander Spellbook est indisponible.', previous: $e);
            }

            $results = $data['results'] ?? null;
            if (!\is_array($results) || !\is_array($results['included'] ?? null)) {
                throw new ComboSourceUnavailableException('Réponse inattendue de Commander Spellbook.');
            }

            $byPopularity = static fn (Combo $a, Combo $b) => $b->popularity <=> $a->popularity;
            $included = array_map($this->mapCombo(...), $results['included']);
            usort($included, $byPopularity);
            $almost = array_map($this->mapCombo(...), $results['almostIncluded'] ?? []);
            usort($almost, $byPopularity);

            return new DeckCombos($included, \array_slice($almost, 0, self::ALMOST_LIMIT), \count($almost), $names);
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
