<?php

namespace App\Precon;

use App\Entity\CardPrinting;
use App\Entity\OracleCard;
use App\Entity\PreconDeck;
use App\Entity\PreconDeckCard;
use App\Enum\DeckZone;
use App\Repository\CardPrintingRepository;
use App\Repository\MtgSetRepository;
use App\Repository\OracleCardRepository;
use App\Repository\PreconDeckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe le catalogue des decks préconstruits depuis MTGJSON : l'index `DeckList.json`,
 * puis un fichier par deck. Chaque carte y porte son identifiant Scryfall, qui est celui de nos impressions.
 *
 * Seuls les nouveaux decks et ceux dont des cartes manquaient (cartes pas encore importées de Scryfall)
 * sont téléchargés, sauf avec $force.
 *
 * @see https://mtgjson.com/data-models/deck/
 */
class PreconImporter
{
    /** Types de decks MTGJSON importés */
    public const array TYPES = ['Commander Deck'];

    private const array ZONES = [
        'commander' => DeckZone::Commander,
        'mainBoard' => DeckZone::Main,
        'sideBoard' => DeckZone::Side,
    ];

    public function __construct(
        #[Target('mtgjson.client')]
        private readonly HttpClientInterface $client,
        private readonly EntityManagerInterface $em,
        private readonly PreconDeckRepository $decks,
        private readonly CardPrintingRepository $printings,
        private readonly OracleCardRepository $oracleCards,
        private readonly MtgSetRepository $sets,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param (callable(string): void)|null $onDeck appelé avec le nom de chaque deck téléchargé
     *
     * @throws ExceptionInterface si l'index MTGJSON est inaccessible
     */
    public function import(bool $force = false, ?callable $onDeck = null): PreconImportResult
    {
        $known = $this->decks->findUnresolvedCounts();
        $imported = $skipped = $failed = $unresolvedCards = 0;

        foreach ($this->client->request('GET', 'DeckList.json')->toArray()['data'] as $entry) {
            if (!\in_array($entry['type'], self::TYPES, true)) {
                continue;
            }
            if (!$force && 0 === ($known[$entry['fileName']] ?? null)) {
                ++$skipped;
                continue;
            }

            $onDeck && $onDeck($entry['name']);
            try {
                $data = $this->client->request('GET', 'decks/'.rawurlencode($entry['fileName']).'.json')->toArray()['data'];
            } catch (ExceptionInterface $e) {
                $this->logger->warning('Deck MTGJSON « {deck} » inaccessible : {message}', ['deck' => $entry['fileName'], 'message' => $e->getMessage()]);
                ++$failed;
                continue;
            }

            $unresolvedCards += $this->save($entry['fileName'], $data);
            ++$imported;
        }

        return new PreconImportResult($imported, $skipped, $failed, $unresolvedCards);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int nombre de cartes introuvables
     */
    private function save(string $code, array $data): int
    {
        $deck = $this->decks->findOneBy(['code' => $code]) ?? new PreconDeck($code);
        $setCode = mb_strtolower($data['code']);
        $deck->setName(mb_substr($data['name'], 0, 150))
            ->setSetCode(mb_substr($setCode, 0, 10))
            ->setSet($this->sets->findOneBy(['code' => $setCode]))
            ->setReleasedAt(new \DateTimeImmutable($data['releaseDate'] ?? 'today'));

        $printings = $this->findPrintings($data);
        $lines = [];
        $unresolved = 0;
        foreach (self::ZONES as $board => $zone) {
            foreach ($data[$board] ?? [] as $card) {
                $printing = $printings[$card['identifiers']['scryfallId'] ?? ''] ?? null;
                $oracle = $printing?->getOracleCard() ?? $this->oracleCards->findOneByDeckListName($card['name']);
                if (null === $oracle) {
                    $unresolved += $card['count'];
                    continue;
                }

                // Une même carte peut figurer deux fois (version foil et non foil)
                $key = $zone->value.'|'.$oracle->getId().'|'.$printing?->getId();
                if (isset($lines[$key])) {
                    $lines[$key]->addQuantity($card['count']);
                } else {
                    $lines[$key] = new PreconDeckCard($deck, $oracle, $zone, $card['count'], $printing);
                }
            }
        }

        if ($unresolved) {
            $this->logger->warning('Deck préconstruit « {deck} » : {count} cartes introuvables.', ['deck' => $code, 'count' => $unresolved]);
        }

        $deck->replaceCards(array_values($lines));
        $deck->setUnresolvedCount($unresolved);
        $this->em->persist($deck);
        $this->em->flush();
        $this->em->clear();

        return $unresolved;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, CardPrinting> impressions du deck indexées par identifiant Scryfall
     */
    private function findPrintings(array $data): array
    {
        $ids = [];
        foreach (array_keys(self::ZONES) as $board) {
            foreach ($data[$board] ?? [] as $card) {
                $id = $card['identifiers']['scryfallId'] ?? '';
                if (Uuid::isValid($id)) {
                    $ids[$id] = Uuid::fromString($id);
                }
            }
        }

        $printings = [];
        foreach ($ids ? $this->printings->findBy(['id' => array_values($ids)]) : [] as $printing) {
            $printings[$printing->getId()->toRfc4122()] = $printing;
        }

        return $printings;
    }
}
