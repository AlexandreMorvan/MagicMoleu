<?php

namespace App\Command;

use App\Scryfall\SetImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:scryfall:import-sets', description: 'Importe toutes les éditions depuis Scryfall')]
class ImportSetsCommand
{
    public function __construct(private readonly SetImporter $importer)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = $this->importer->import();
        $io->success(\sprintf('%d éditions importées.', $count));

        return Command::SUCCESS;
    }
}
