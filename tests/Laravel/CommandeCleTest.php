<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Laravel;

use Babel\Moteur\Moteur;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\BufferedOutput;

final class CommandeCleTest extends CasLaravel
{
    private const LIGNE = '/^BABEL_KEY=(base64:[A-Za-z0-9+\/]{43}=)$/m';

    public function testCreeLaCleApresAppKey(): void
    {
        file_put_contents($this->fichierEnv(), "APP_NAME=Babel\nAPP_KEY=base64:abc=\nAPP_DEBUG=true\n");

        [$code, $sortie] = $this->lancer();

        self::assertSame(0, $code);
        $contenu = (string) file_get_contents($this->fichierEnv());
        self::assertSame(1, preg_match(self::LIGNE, $contenu, $m));
        self::assertStringStartsWith("APP_NAME=Babel\nAPP_KEY=base64:abc=\nBABEL_KEY=base64:", $contenu);
        self::assertStringEndsWith("\nAPP_DEBUG=true\n", $contenu);
        self::assertStringContainsString(Moteur::depuisConfiguration($m[1])->empreinte(), $sortie);
        self::assertStringNotContainsString($m[1], $sortie);
        // L'application en cours voit tout de suite la nouvelle clé.
        self::assertSame($m[1], $this->application()->make(\Illuminate\Contracts\Config\Repository::class)->get('babel.cle'));
        self::assertSame(Moteur::depuisConfiguration($m[1])->empreinte(), $this->moteur()->empreinte());
    }

    public function testRemplitUneLigneVide(): void
    {
        file_put_contents($this->fichierEnv(), "APP_KEY=\nBABEL_KEY=\nDB_CONNECTION=sqlite");

        [$code] = $this->lancer();

        self::assertSame(0, $code);
        $contenu = (string) file_get_contents($this->fichierEnv());
        self::assertSame(1, preg_match_all('/^BABEL_KEY=/m', $contenu));
        self::assertSame(1, preg_match(self::LIGNE, $contenu));
        self::assertStringEndsWith("\nDB_CONNECTION=sqlite", $contenu);
    }

    public function testAjouteALaFinSansAppKey(): void
    {
        file_put_contents($this->fichierEnv(), 'APP_NAME=Babel');

        [$code] = $this->lancer();

        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/\AAPP_NAME=Babel\nBABEL_KEY=base64:[A-Za-z0-9+\/]{43}=\n\z/', (string) file_get_contents($this->fichierEnv()));
    }

    public function testRefuseDEcraserUneCleExistante(): void
    {
        $avant = "APP_KEY=base64:abc=\nBABEL_KEY=\"base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=\"\n";
        file_put_contents($this->fichierEnv(), $avant);

        [$code, $sortie] = $this->lancer();

        self::assertSame(1, $code);
        self::assertSame($avant, file_get_contents($this->fichierEnv()));
        self::assertStringContainsString('existe déjà', $sortie);
        self::assertStringContainsString('--force', $sortie);
    }

    public function testForceRemplaceLaCleAvecUnAvertissement(): void
    {
        $ancienne = 'base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
        file_put_contents($this->fichierEnv(), "BABEL_KEY={$ancienne}\nAPP_ENV=local\n");

        [$code, $sortie] = $this->lancer(['--force' => true]);

        self::assertSame(0, $code);
        $contenu = (string) file_get_contents($this->fichierEnv());
        self::assertStringNotContainsString($ancienne, $contenu);
        self::assertSame(1, preg_match(self::LIGNE, $contenu));
        self::assertStringEndsWith("\nAPP_ENV=local\n", $contenu);
        self::assertStringContainsString('toutes les adresses', $sortie);
    }

    public function testRefuseDEcraserUneCleExistanteEnCrlf(): void
    {
        $avant = "APP_KEY=base64:abc=\r\nBABEL_KEY=\"base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=\"\r\n";
        file_put_contents($this->fichierEnv(), $avant);

        [$code] = $this->lancer();

        self::assertSame(1, $code);
        self::assertSame($avant, file_get_contents($this->fichierEnv()));
    }

    public function testForceRemplaceLaCleEnCrlf(): void
    {
        $ancienne = 'base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
        file_put_contents($this->fichierEnv(), "BABEL_KEY={$ancienne}\r\nAPP_ENV=local\r\n");

        [$code] = $this->lancer(['--force' => true]);

        self::assertSame(0, $code);
        $contenu = (string) file_get_contents($this->fichierEnv());
        self::assertStringNotContainsString($ancienne, $contenu);
        self::assertSame(1, preg_match_all('/^BABEL_KEY=/m', $contenu));
        self::assertMatchesRegularExpression('/\ABABEL_KEY=base64:[A-Za-z0-9+\/]{43}=\r\nAPP_ENV=local\r\n\z/', $contenu);
    }

    public function testCreeLaCleApresAppKeyEnCrlf(): void
    {
        file_put_contents($this->fichierEnv(), "APP_NAME=Babel\r\nAPP_KEY=base64:abc=\r\nAPP_DEBUG=true\r\n");

        [$code] = $this->lancer();

        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/\AAPP_NAME=Babel\r\nAPP_KEY=base64:abc=\r\nBABEL_KEY=base64:[A-Za-z0-9+\/]{43}=\r\nAPP_DEBUG=true\r\n\z/', (string) file_get_contents($this->fichierEnv()));
    }

    public function testRemplitUneLigneVideEnCrlf(): void
    {
        file_put_contents($this->fichierEnv(), "APP_KEY=\r\nBABEL_KEY=\r\nDB_CONNECTION=sqlite\r\n");

        [$code] = $this->lancer();

        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/\AAPP_KEY=\r\nBABEL_KEY=base64:[A-Za-z0-9+\/]{43}=\r\nDB_CONNECTION=sqlite\r\n\z/', (string) file_get_contents($this->fichierEnv()));
    }

    public function testAjouteALaFinSansAppKeyEnCrlf(): void
    {
        file_put_contents($this->fichierEnv(), "APP_NAME=Babel\r\nAPP_ENV=local");

        [$code] = $this->lancer();

        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/\AAPP_NAME=Babel\r\nAPP_ENV=local\r\nBABEL_KEY=base64:[A-Za-z0-9+\/]{43}=\r\n\z/', (string) file_get_contents($this->fichierEnv()));
    }

    public function testAfficherNeTouchePasAuFichier(): void
    {
        $avant = "APP_KEY=base64:abc=\n";
        file_put_contents($this->fichierEnv(), $avant);

        [$code, $sortie] = $this->lancer(['--afficher' => true]);

        self::assertSame(0, $code);
        self::assertSame($avant, file_get_contents($this->fichierEnv()));
        self::assertMatchesRegularExpression(self::LIGNE, trim($sortie));
    }

    public function testDeuxClesTireesSontDifferentes(): void
    {
        [, $a] = $this->lancer(['--afficher' => true]);
        [, $b] = $this->lancer(['--afficher' => true]);
        self::assertNotSame($a, $b);
    }

    public function testSansFichierEnv(): void
    {
        [$code, $sortie] = $this->lancer();

        self::assertSame(1, $code);
        self::assertStringContainsString('.env introuvable', $sortie);
        self::assertFileDoesNotExist($this->fichierEnv());
    }

    /**
     * @param array<string, bool> $options
     *
     * @return array{int, string}
     */
    private function lancer(array $options = []): array
    {
        $sortie = new BufferedOutput();
        $code = $this->application()->make(Kernel::class)->call('babel:cle', $options, $sortie);

        return [$code, $sortie->fetch()];
    }
}
