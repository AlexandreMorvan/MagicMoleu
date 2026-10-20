<?php

namespace App\Scryfall;

/**
 * Lit un fichier JSON Lines compressé (format des bulk data Scryfall), un objet par ligne,
 * sans jamais charger le fichier entier en mémoire.
 */
final class JsonlReader
{
    /**
     * @param (callable(string): bool)|null $prefilter test rapide sur la ligne brute avant décodage
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public static function read(string $path, ?callable $prefilter = null): \Generator
    {
        $handle = gzopen($path, 'r') ?: throw new \RuntimeException(\sprintf('Impossible d\'ouvrir "%s".', $path));
        try {
            while (false !== $line = gzgets($handle)) {
                $line = trim($line);
                if ('' === $line || ($prefilter && !$prefilter($line))) {
                    continue;
                }
                yield json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            }
        } finally {
            gzclose($handle);
        }
    }
}
