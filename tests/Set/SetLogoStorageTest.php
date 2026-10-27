<?php

namespace App\Tests\Set;

use App\Entity\MtgSet;
use App\Set\SetLogoException;
use App\Set\SetLogoStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

class SetLogoStorageTest extends TestCase
{
    /** Adresse IP publique littérale : la protection contre le réseau privé n'a pas de DNS à résoudre. */
    private const string URL = 'https://8.8.8.8/logos/ecl.png';

    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/set-logos-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->publicDir);
    }

    public function testDownloadsAndReplacesTheLogo(): void
    {
        $set = $this->set();

        $this->storage(self::png(10))->download($set, self::URL);
        $first = $set->getLogoPath();
        self::assertMatchesRegularExpression('#^uploads/sets/ecl-[0-9a-f]{10}\.png$#', $first);
        self::assertFileExists($this->publicDir.'/'.$first);
        self::assertSame(self::URL, $set->getLogoSourceUrl());

        // Un nouveau logo remplace l'ancien fichier
        $this->storage(self::png(20))->download($set, 'https://8.8.8.8/logos/ecl-v2.png');
        self::assertNotSame($first, $set->getLogoPath());
        self::assertFileDoesNotExist($this->publicDir.'/'.$first);
        self::assertFileExists($this->publicDir.'/'.$set->getLogoPath());

        $path = $set->getLogoPath();
        $this->storage(self::png(10))->remove($set);
        self::assertNull($set->getLogoPath());
        self::assertNull($set->getLogoSourceUrl());
        self::assertFileDoesNotExist($this->publicDir.'/'.$path);
    }

    public function testStoresAnUploadedFile(): void
    {
        $set = $this->set()->setLogoSourceUrl('https://8.8.8.8/ancien.png');
        $file = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($file, self::png(16));

        $this->storage('')->upload($set, new \SplFileInfo($file));
        unlink($file);

        self::assertMatchesRegularExpression('#^uploads/sets/ecl-[0-9a-f]{10}\.png$#', $set->getLogoPath());
        self::assertFileExists($this->publicDir.'/'.$set->getLogoPath());
        self::assertNull($set->getLogoSourceUrl(), 'le logo ne vient plus de l\'ancienne URL');
    }

    public function testRejectsSvgEvenWhenAnnouncedAsPng(): void
    {
        $this->expectException(SetLogoException::class);
        $this->expectExceptionMessage('PNG, WebP ou JPEG');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->storage($svg)->download($this->set(), self::URL);
    }

    public function testRejectsNonHttpsUrls(): void
    {
        $this->expectException(SetLogoException::class);
        $this->expectExceptionMessage('https://');

        $this->storage(self::png(10))->download($this->set(), 'http://8.8.8.8/logo.png');
    }

    public function testRejectsPrivateNetworkUrls(): void
    {
        $this->expectException(SetLogoException::class);

        $this->storage(self::png(10))->download($this->set(), 'https://127.0.0.1/logo.png');
    }

    public function testRejectsHttpErrors(): void
    {
        $this->expectException(SetLogoException::class);
        $this->expectExceptionMessage('404');

        $storage = new SetLogoStorage(new MockHttpClient(new MockResponse('', ['http_code' => 404])), new Filesystem(), $this->publicDir);
        $storage->download($this->set(), self::URL);
    }

    private function storage(string $body): SetLogoStorage
    {
        return new SetLogoStorage(new MockHttpClient(new MockResponse($body, ['response_headers' => ['content-type' => 'image/png']])), new Filesystem(), $this->publicDir);
    }

    private function set(): MtgSet
    {
        return new MtgSet(Uuid::v4(), 'ecl', 'Lorwyn Eclipsed', 'expansion');
    }

    private static function png(int $width): string
    {
        $image = imagecreatetruecolor($width, 4);
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
