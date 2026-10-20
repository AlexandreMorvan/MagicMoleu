<?php

namespace App\Command;

use App\Scryfall\BulkDataDownloader;
use App\Scryfall\BulkFile;
use App\Scryfall\JsonlReader;
use App\Scryfall\LocalizationImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:scryfall:import-localized',
    description: 'Importe les noms et textes traduits depuis le bulk « all-cards » (fichier lourd, à lancer après import-cards)',
)]
class ImportLocalizedCommand
{
    public function __construct(
        private readonly BulkDataDownloader $downloader,
        private readonly LocalizationImporter $importer,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Code langue Scryfall (fr, de, it, es, ja…)')]
        string $lang = 'fr',
        #[Option(description: 'Réimporte même si le fichier Scryfall n\'a pas changé')]
        bool $force = false,
    ): int {
        $lock = $this->lockFactory->createLock('scryfall-import');
        if (!$lock->acquire()) {
            $io->warning('Un import Scryfall est déjà en cours.');

            return Command::FAILURE;
        }

        try {
            $file = $this->downloader->fetch('all-cards', static fn (array $info) => $io->comment(
                \sprintf('Téléchargement de %s (%d Mo)…', $info['jsonl_download_uri'], $info['compressed_size'] / 1e6),
            ));

            // Le marqueur d'import est propre à chaque langue
            $marker = new BulkFile('all-cards-'.$lang, $file->path, $file->updatedAt);
            if (!$force && $this->downloader->isImported($marker)) {
                $io->info('Les traductions sont déjà à jour.');

                return Command::SUCCESS;
            }

            $progress = $io->createProgressBar();
            $progress->setFormat('%current% traductions  [%elapsed:6s%]  %memory:6s%');
            $needle = \sprintf('"lang":"%s"', $lang);
            $count = $this->importer->import(
                // Filtre textuel avant décodage : évite de décoder les ~500 000 objets des autres langues
                JsonlReader::read($file->path, static fn (string $line) => str_contains($line, $needle)),
                $lang,
                static fn (int $done) => $progress->setProgress($done),
            );
            $progress->finish();
            $io->newLine(2);

            $this->downloader->markImported($marker);
            $io->success(\sprintf('%d cartes traduites (%s).', $count, $lang));

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
