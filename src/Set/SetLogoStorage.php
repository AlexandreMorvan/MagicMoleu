<?php

namespace App\Set;

use App\Entity\MtgSet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Logo d'une édition (titre stylisé) : téléchargé depuis une URL (ex. page produit Wizards) ou envoyé depuis l'admin,
 * puis copié dans public/uploads/sets : le site ne dépend pas du site d'origine, dont les URL peuvent changer.
 */
class SetLogoStorage
{
    /** Taille maximale d'un logo (octets). */
    public const int MAX_SIZE = 2_000_000;

    /** Formats acceptés et extension du fichier enregistré ; pas de SVG, qui peut contenir du script. */
    private const array TYPES = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'];

    private const string PUBLIC_DIR = 'uploads/sets';

    private readonly HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
        // L'URL est saisie dans l'admin : pas de requête vers le réseau interne du serveur
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    /**
     * Télécharge l'image et la définit comme logo de l'édition (l'ancien fichier est supprimé).
     *
     * @throws SetLogoException si l'URL ou l'image ne conviennent pas
     */
    public function download(MtgSet $set, string $url): void
    {
        if ('https' !== parse_url($url, \PHP_URL_SCHEME)) {
            throw new SetLogoException('L\'URL du logo doit commencer par https://.');
        }

        try {
            $response = $this->client->request('GET', $url, ['timeout' => 15, 'max_redirects' => 3]);
            if (200 !== $response->getStatusCode()) {
                throw new SetLogoException(\sprintf('Le serveur a répondu %d.', $response->getStatusCode()));
            }
            if ((int) ($response->getHeaders()['content-length'][0] ?? 0) > self::MAX_SIZE) {
                throw new SetLogoException('L\'image dépasse 2 Mo.');
            }
            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new SetLogoException('Téléchargement impossible : '.$e->getMessage(), previous: $e);
        }

        $this->store($set, $content, $url);
    }

    /**
     * Définit comme logo une image envoyée depuis l'admin (enregistrée depuis n'importe quel site).
     *
     * @throws SetLogoException si le fichier n'est pas une image acceptée
     */
    public function upload(MtgSet $set, \SplFileInfo $file): void
    {
        if ($file->getSize() > self::MAX_SIZE) {
            throw new SetLogoException('L\'image dépasse 2 Mo.');
        }

        $this->store($set, (string) file_get_contents($file->getPathname()), null);
    }

    public function remove(MtgSet $set): void
    {
        $this->deleteFile($set);
        $set->setLogoPath(null)->setLogoSourceUrl(null);
    }

    private function store(MtgSet $set, string $content, ?string $sourceUrl): void
    {
        if (\strlen($content) > self::MAX_SIZE) {
            throw new SetLogoException('L\'image dépasse 2 Mo.');
        }
        // Le type est lu dans le contenu, pas dans l'en-tête ni l'extension annoncés
        $mime = getimagesizefromstring($content)['mime'] ?? null;
        $extension = self::TYPES[$mime] ?? throw new SetLogoException('Le fichier n\'est pas une image PNG, WebP ou JPEG.');

        $path = \sprintf('%s/%s-%s.%s', self::PUBLIC_DIR, $set->getCode(), substr(sha1($content), 0, 10), $extension);
        $this->filesystem->dumpFile($this->publicDir.'/'.$path, $content);
        if ($set->getLogoPath() !== $path) {
            $this->deleteFile($set);
        }

        $set->setLogoPath($path)->setLogoSourceUrl($sourceUrl);
    }

    private function deleteFile(MtgSet $set): void
    {
        if ($set->getLogoPath()) {
            $this->filesystem->remove($this->publicDir.'/'.$set->getLogoPath());
        }
    }
}
