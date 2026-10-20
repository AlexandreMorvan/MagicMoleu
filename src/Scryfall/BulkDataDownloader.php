<?php

namespace App\Scryfall;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Gère le cache local des fichiers « bulk data » de Scryfall (JSONL compressé, mis à jour une fois par jour).
 *
 * Deux marqueurs par type dans var/scryfall/ :
 * - {type}.downloaded : date Scryfall de la version téléchargée ;
 * - {type}.imported : date Scryfall de la dernière version importée avec succès.
 */
class BulkDataDownloader
{
    public function __construct(
        private readonly ScryfallClient $client,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/scryfall')]
        private readonly string $directory,
    ) {
    }

    /**
     * Renvoie le fichier local à jour, en ne le téléchargeant que si Scryfall en a publié une nouvelle version.
     */
    public function fetch(string $type, ?callable $onDownload = null): BulkFile
    {
        $this->filesystem->mkdir($this->directory);

        $info = $this->client->getBulkData($type);
        $updatedAt = new \DateTimeImmutable($info['updated_at']);
        $path = \sprintf('%s/%s.jsonl.gz', $this->directory, $type);

        if (!is_file($path) || $this->readMarker($type, 'downloaded') != $updatedAt) {
            if ($onDownload) {
                $onDownload($info);
            }
            $this->client->download($info['jsonl_download_uri'], $path);
            $this->writeMarker($type, 'downloaded', $updatedAt);
        }

        return new BulkFile($type, $path, $updatedAt);
    }

    public function isImported(BulkFile $file): bool
    {
        return $this->readMarker($file->type, 'imported') == $file->updatedAt;
    }

    public function markImported(BulkFile $file): void
    {
        $this->writeMarker($file->type, 'imported', $file->updatedAt);
    }

    private function readMarker(string $type, string $marker): ?\DateTimeImmutable
    {
        $path = \sprintf('%s/%s.%s', $this->directory, $type, $marker);

        return is_file($path) ? new \DateTimeImmutable(trim(file_get_contents($path))) : null;
    }

    private function writeMarker(string $type, string $marker, \DateTimeImmutable $date): void
    {
        $this->filesystem->dumpFile(\sprintf('%s/%s.%s', $this->directory, $type, $marker), $date->format(\DATE_ATOM));
    }
}
