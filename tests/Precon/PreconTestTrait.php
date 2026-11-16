<?php

namespace App\Tests\Precon;

use App\Precon\PreconImporter;
use App\Precon\PreconImportResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Simule l'API MTGJSON avec deux decks Commander construits sur les cartes de test
 * (voir {@see \App\Tests\Deck\DeckTestTrait::importDeckFixtures()}) et un deck d'un autre type.
 */
trait PreconTestTrait
{
    private const string THRASIOS = '21e27b91-c7f1-4709-aa0d-8b5d81b22a0a';
    private const string TYMNA = 'bc7cbe9b-324e-42b8-94e2-36e91cb32163';
    private const string FOREST = 'd232fcc2-12f6-401a-b1aa-ddff11cb9378';
    private const string WILSON = 'dfe0daf8-43da-484b-baa1-76f8313c5a0c';
    private const string RAISED_BY_GIANTS = '11c93414-935e-462b-ad89-27ca21d01bf9';

    /** @var list<string> */
    private array $mtgjsonRequests = [];

    private bool $mtgjsonMocked = false;

    /** @var array<string, MockResponse> réponses imposées pour certains fichiers de deck */
    private array $mtgjsonOverrides = [];

    private function importPrecons(bool $force = false): PreconImportResult
    {
        $this->mockMtgjson();

        return static::getContainer()->get(PreconImporter::class)->import($force);
    }

    private function mockMtgjson(): void
    {
        if ($this->mtgjsonMocked) {
            return;
        }
        $this->mtgjsonMocked = true;

        static::getContainer()->set('mtgjson.client', new MockHttpClient(function (string $method, string $url): MockResponse {
            $file = substr($url, \strlen('https://mtgjson.com/api/v5/'));
            $this->mtgjsonRequests[] = $file;

            return $this->mtgjsonOverrides[$file] ?? match ($file) {
                'DeckList.json' => new JsonMockResponse(['data' => [
                    ['fileName' => 'BreedLethality_C16', 'name' => 'Breed Lethality', 'code' => 'C16', 'releaseDate' => '2016-11-11', 'type' => 'Commander Deck'],
                    ['fileName' => 'OldTheme_TMP', 'name' => 'Old Theme', 'code' => 'TMP', 'releaseDate' => '1997-10-14', 'type' => 'Theme Deck'],
                    ['fileName' => 'PartyTime_CLB', 'name' => 'Party Time', 'code' => 'CLB', 'releaseDate' => '2022-06-10', 'type' => 'Commander Deck'],
                ]]),
                'decks/BreedLethality_C16.json' => new JsonMockResponse(['data' => [
                    'name' => 'Breed Lethality',
                    'code' => 'C16',
                    'releaseDate' => '2016-11-11',
                    'commander' => [self::card('Thrasios, Triton Hero', self::THRASIOS), self::card('Tymna the Weaver', self::TYMNA)],
                    'mainBoard' => [
                        self::card('Forest', self::FOREST, 50),
                        // La même impression en foil : regroupée avec la précédente
                        self::card('Forest', self::FOREST, 46),
                        // Impression absente de notre base : retrouvée par son nom
                        self::card('Counterspell', '99999999-9999-4999-8999-999999999999'),
                        self::card('Carte Inconnue', '88888888-8888-4888-8888-888888888888'),
                    ],
                ]]),
                'decks/PartyTime_CLB.json' => new JsonMockResponse(['data' => [
                    'name' => 'Party Time',
                    'code' => 'CLB',
                    'releaseDate' => '2022-06-10',
                    'commander' => [self::card('Wilson, Refined Grizzly', self::WILSON)],
                    'mainBoard' => [self::card('Raised by Giants', self::RAISED_BY_GIANTS), self::card('Forest', self::FOREST, 98)],
                ]]),
                default => new MockResponse('', ['http_code' => 404]),
            };
        }, 'https://mtgjson.com/api/v5/'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(string $name, string $scryfallId, int $count = 1): array
    {
        return ['name' => $name, 'count' => $count, 'identifiers' => ['scryfallId' => $scryfallId]];
    }
}
