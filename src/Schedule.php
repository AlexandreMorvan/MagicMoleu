<?php

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tâches planifiées, exécutées par le worker : `bin/console messenger:consume scheduler_default`.
 *
 * Les heures sont en UTC : Scryfall publie ses fichiers bulk vers 9 h UTC.
 * Les trois commandes partagent un verrou, elles sont donc décalées pour ne pas se chevaucher.
 */
#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $utc = new \DateTimeZone('UTC');

        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // Spoilers : éditions et cartes récentes ou à venir, toutes les heures
            ->add(RecurringMessage::cron('5 * * * *', new RunCommandMessage('app:scryfall:sync-spoilers'), $utc))
            // Base complète des cartes, une fois par jour après la publication du bulk
            ->add(RecurringMessage::cron('15 10 * * *', new RunCommandMessage('app:scryfall:import-cards'), $utc))
            // Traductions françaises (fichier de ~2 Go), une fois par semaine
            ->add(RecurringMessage::cron('30 11 * * 1', new RunCommandMessage('app:scryfall:import-localized --lang=fr'), $utc))
        ;
    }
}
