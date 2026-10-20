<?php

namespace App\Command;

use App\Scryfall\SpoilerSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:scryfall:sync-spoilers',
    description: 'Synchronise les éditions et les cartes récentes ou à venir via l\'API Scryfall (spoilers)',
)]
class SyncSpoilersCommand
{
    public function __construct(
        private readonly SpoilerSynchronizer $synchronizer,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $lock = $this->lockFactory->createLock('scryfall-import');
        if (!$lock->acquire()) {
            $io->warning('Un import Scryfall est déjà en cours.');

            return Command::FAILURE;
        }

        try {
            $result = $this->synchronizer->synchronize();
            $io->success(\sprintf('%d éditions et %d cartes récentes synchronisées.', $result['sets'], $result['cards']));

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
