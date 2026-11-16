<?php

namespace App\Command;

use App\Precon\PreconImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:mtgjson:import-precons', description: 'Importe les decks préconstruits Commander depuis MTGJSON')]
class ImportPreconsCommand
{
    public function __construct(
        private readonly PreconImporter $importer,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Retélécharge aussi les decks déjà complets')]
        bool $force = false,
    ): int {
        // Même verrou que les imports Scryfall : les decks référencent les impressions que ceux-ci écrivent
        $lock = $this->lockFactory->createLock('scryfall-import');
        if (!$lock->acquire()) {
            $io->warning('Un import est déjà en cours.');

            return Command::FAILURE;
        }

        try {
            $result = $this->importer->import($force, static fn (string $deck) => $io->writeln(' · '.$deck, SymfonyStyle::VERBOSITY_VERBOSE));
        } finally {
            $lock->release();
        }

        $io->success(\sprintf('%d decks importés, %d déjà à jour.', $result->imported, $result->skipped));
        if ($result->unresolvedCards) {
            $io->warning(\sprintf('%d cartes introuvables : les decks concernés seront retentés au prochain import.', $result->unresolvedCards));
        }
        if ($result->failed) {
            $io->error(\sprintf('%d decks n\'ont pas pu être téléchargés.', $result->failed));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
