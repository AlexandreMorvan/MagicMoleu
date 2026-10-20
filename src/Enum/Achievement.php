<?php

namespace App\Enum;

use App\Achievement\UserStats;

/**
 * Catalogue des succès. Pour en ajouter un : un nouveau case, ses textes et sa condition dans isEarned().
 */
enum Achievement: string
{
    case Welcome = 'welcome';
    case FirstDeck = 'first_deck';
    case FiveDecks = 'decks_5';
    case TenDecks = 'decks_10';
    case TwentyFiveDecks = 'decks_25';
    case FirstValidDeck = 'first_valid_deck';
    case ValidCommanderDeck = 'valid_commander_deck';
    case PublicDeck = 'public_deck';
    case FiveColors = 'five_colors';
    case BigBudget = 'big_budget';
    case OneYear = 'anniversary_1';
    case TwoYears = 'anniversary_2';
    case FiveYears = 'anniversary_5';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Bienvenue, planeswalker',
            self::FirstDeck => 'Premier sort',
            self::FiveDecks => 'Bibliothécaire',
            self::TenDecks => 'Archiviste',
            self::TwentyFiveDecks => 'Maître de la forge',
            self::FirstValidDeck => 'Légal et létal',
            self::ValidCommanderDeck => 'Héraut du Commandeur',
            self::PublicDeck => 'Cartes sur table',
            self::FiveColors => 'Arc-en-ciel',
            self::BigBudget => 'Trésor de dragon',
            self::OneYear => 'Un an d\'aventures',
            self::TwoYears => 'Vétéran du multivers',
            self::FiveYears => 'Légende vivante',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Welcome => 'Créer ton compte.',
            self::FirstDeck => 'Créer ton premier deck.',
            self::FiveDecks => 'Créer 5 decks.',
            self::TenDecks => 'Créer 10 decks.',
            self::TwentyFiveDecks => 'Créer 25 decks.',
            self::FirstValidDeck => 'Construire un deck entièrement valide.',
            self::ValidCommanderDeck => 'Construire un deck Commander valide de 100 cartes.',
            self::PublicDeck => 'Rendre un deck public.',
            self::FiveColors => 'Construire un deck Commander aux cinq couleurs.',
            self::BigBudget => 'Construire un deck valant plus de 500 €.',
            self::OneYear => 'Fêter ta première année parmi nous.',
            self::TwoYears => 'Fêter tes deux ans d\'inscription.',
            self::FiveYears => 'Fêter tes cinq ans d\'inscription.',
        };
    }

    /**
     * Rareté du succès, pour sa couleur d'affichage.
     */
    public function rarity(): Rarity
    {
        return match ($this) {
            self::Welcome, self::FirstDeck, self::PublicDeck => Rarity::Common,
            self::FiveDecks, self::FirstValidDeck, self::OneYear => Rarity::Uncommon,
            self::TenDecks, self::ValidCommanderDeck, self::FiveColors, self::TwoYears => Rarity::Rare,
            self::TwentyFiveDecks, self::BigBudget, self::FiveYears => Rarity::Mythic,
        };
    }

    /**
     * Symbole Scryfall servant d'icône (rendu par le filtre mana_symbols).
     */
    public function symbol(): string
    {
        return match ($this) {
            self::Welcome => '{PW}',
            self::FirstDeck => '{1}',
            self::FiveDecks => '{5}',
            self::TenDecks => '{10}',
            self::TwentyFiveDecks => '{20}',
            self::FirstValidDeck => '{T}',
            self::ValidCommanderDeck => '{Q}',
            self::PublicDeck => '{E}',
            self::FiveColors => '{W}{U}{B}{R}{G}',
            self::BigBudget => '{TK}',
            self::OneYear, self::TwoYears, self::FiveYears => '{S}',
        };
    }

    public function isEarned(UserStats $stats): bool
    {
        return match ($this) {
            self::Welcome => true,
            self::FirstDeck => $stats->deckCount() >= 1,
            self::FiveDecks => $stats->deckCount() >= 5,
            self::TenDecks => $stats->deckCount() >= 10,
            self::TwentyFiveDecks => $stats->deckCount() >= 25,
            self::FirstValidDeck => $stats->hasValidDeck(),
            self::ValidCommanderDeck => $stats->hasValidDeck(Format::Commander),
            self::PublicDeck => $stats->publicDeckCount() >= 1,
            self::FiveColors => $stats->hasFiveColorCommanderDeck(),
            self::BigBudget => $stats->highestDeckPrice() > 500,
            self::OneYear => $stats->accountAgeInYears() >= 1,
            self::TwoYears => $stats->accountAgeInYears() >= 2,
            self::FiveYears => $stats->accountAgeInYears() >= 5,
        };
    }
}
