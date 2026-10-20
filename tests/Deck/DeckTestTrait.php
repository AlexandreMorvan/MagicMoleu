<?php

namespace App\Tests\Deck;

use App\Entity\Deck;
use App\Entity\OracleCard;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\OracleCardRepository;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Scryfall\LocalizationImporter;

/**
 * Importe les cartes de test et construit des decks en mémoire.
 */
trait DeckTestTrait
{
    protected static function importDeckFixtures(): void
    {
        $importer = static::getContainer()->get(CardImporter::class);
        $importer->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
        $importer->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_deck_cards.jsonl'));
        static::getContainer()->get(LocalizationImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards_fr.jsonl'), 'fr');
    }

    protected static function oracle(string $name): OracleCard
    {
        return static::getContainer()->get(OracleCardRepository::class)->findOneByDeckListName($name)
            ?? throw new \RuntimeException(\sprintf('Carte de test "%s" introuvable.', $name));
    }

    /**
     * @param array<string, int>|array<string, array{int, DeckZone}> $cards nom => quantité (zone principale) ou [quantité, zone]
     */
    protected static function buildDeck(Format $format, array $cards, ?User $owner = null): Deck
    {
        $deck = (new Deck($owner ?? (new User())->setUsername('test')->setEmail('test@example.com')))->setName('Test')->setFormat($format);
        foreach ($cards as $name => $spec) {
            [$quantity, $zone] = \is_array($spec) ? $spec : [$spec, DeckZone::Main];
            $deck->addCard(self::oracle($name), $zone, $quantity);
        }

        return $deck;
    }
}
