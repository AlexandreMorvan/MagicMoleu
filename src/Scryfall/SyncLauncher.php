<?php

namespace App\Scryfall;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Lance une synchronisation Scryfall en arrière-plan : la commande est envoyée au transport « async »
 * (les imports bulk durent plusieurs minutes, trop longtemps pour une requête HTTP).
 */
class SyncLauncher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * Un import tient le verrou partagé par les commandes app:scryfall:* (sauf import-sets, qui est rapide).
     */
    public function isImportRunning(): bool
    {
        $lock = $this->lockFactory->createLock('scryfall-import');
        if (!$lock->acquire()) {
            return true;
        }
        $lock->release();

        return false;
    }

    public function launch(SyncTask $task, bool $force = false): void
    {
        $this->bus->dispatch(new RunCommandMessage($task->command($force)));
    }
}
