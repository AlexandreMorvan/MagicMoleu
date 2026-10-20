<?php

namespace App\Command;

use App\Scryfall\BulkDataDownloader;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Scryfall\SetCoverUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:scryfall:import-cards', description: 'Importe toutes les cartes depuis le bulk « default-cards » de Scryfall')]
class ImportCardsCommand
{
    public function __construct(
        private readonly BulkDataDownloader $downloader,
        private readonly CardImporter $importer,
        private readonly SetCoverUpdater $coverUpdater,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Réimporte même si le fichier Scryfall n\'a pas changé')]
        bool $force = false,
    ): int {
        $lock = $this->lockFactory->createLock('scryfall-import');
        if (!$lock->acquire()) {
            $io->warning('Un import Scryfall est déjà en cours.');

            return Command::FAILURE;
        }

        try {
            $file = $this->downloader->fetch('default-cards', static fn (array $info) => $io->comment(
                \sprintf('Téléchargement de %s (%d Mo)…', $info['jsonl_download_uri'], $info['compressed_size'] / 1e6),
            ));

            if (!$force && $this->downloader->isImported($file)) {
                $io->info(\sprintf('Les cartes sont déjà à jour (version du %s).', $file->updatedAt->format('d/m/Y H:i')));

                return Command::SUCCESS;
            }

            $progress = $io->createProgressBar();
            $progress->setFormat('%current% cartes  [%elapsed:6s%]  %memory:6s%');
            $count = $this->importer->import(
                JsonlReader::read($file->path),
                static fn (int $done) => $progress->setProgress($done),
            );
            $progress->finish();
            $io->newLine(2);

            $this->downloader->markImported($file);
            $this->coverUpdater->update();
            $io->success(\sprintf('%d impressions importées.', $count));

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
