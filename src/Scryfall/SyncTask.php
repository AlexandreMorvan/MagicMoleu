<?php

namespace App\Scryfall;

/**
 * Synchronisations Scryfall déclenchables depuis le back-office ; chacune correspond à une commande app:scryfall:*.
 */
enum SyncTask: string
{
    case Spoilers = 'spoilers';
    case Sets = 'sets';
    case Cards = 'cards';
    case Localized = 'localized';

    public function label(): string
    {
        return match ($this) {
            self::Spoilers => 'Spoilers',
            self::Sets => 'Éditions',
            self::Cards => 'Base des cartes',
            self::Localized => 'Traductions françaises',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Spoilers => 'Éditions et cartes des 30 derniers jours ou à venir, via l\'API (quelques secondes).',
            self::Sets => 'Liste complète des éditions, via l\'API (quelques secondes).',
            self::Cards => 'Bulk « default-cards » (~80 Mo, ~1 min 30), ignoré si la version est déjà importée.',
            self::Localized => 'Bulk « all-cards » (~2 Go, plusieurs minutes), ignoré si la version est déjà importée.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Spoilers => 'fa fa-eye',
            self::Sets => 'fa fa-box-archive',
            self::Cards => 'fa fa-clone',
            self::Localized => 'fa fa-language',
        };
    }

    /**
     * Seuls les imports bulk sont sautés quand le fichier n'a pas changé : eux seuls ont une option --force.
     */
    public function supportsForce(): bool
    {
        return \in_array($this, [self::Cards, self::Localized], true);
    }

    public function command(bool $force = false): string
    {
        $command = match ($this) {
            self::Spoilers => 'app:scryfall:sync-spoilers',
            self::Sets => 'app:scryfall:import-sets',
            self::Cards => 'app:scryfall:import-cards',
            self::Localized => 'app:scryfall:import-localized --lang=fr',
        };

        return $force && $this->supportsForce() ? $command.' --force' : $command;
    }
}
