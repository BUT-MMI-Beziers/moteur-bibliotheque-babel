<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Laravel;

use Babel\Moteur\Erreur\CleInvalide;
use Babel\Moteur\Laravel\MoteurServiceProvider;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use Illuminate\Support\ServiceProvider;

final class FournisseurTest extends CasLaravel
{
    public function testLeMoteurEstUnSingletonConstruitDepuisLaConfiguration(): void
    {
        $moteur = $this->moteur();

        self::assertSame($moteur, $this->moteur());
        self::assertSame(Vecteurs::donnees()['cles'][0]['empreinte'], $moteur->empreinte());
        self::assertStringStartsWith('tnznd.olonj xxqvbefublhf', $moteur->lire('0-w1-s1-v1:1'));
    }

    public function testLeMoteurSInjecteDansUneMethode(): void
    {
        $texte = $this->application()->call(static fn (Moteur $moteur): string => $moteur->lire('0-w1-s1-v1:1'));
        self::assertIsString($texte);
        self::assertSame(3200, strlen($texte));
    }

    public function testMessageClairSansCle(): void
    {
        $this->configurer('babel.cle', null);
        $this->application()->forgetInstance(Moteur::class);

        $this->expectException(CleInvalide::class);
        $this->expectExceptionMessage('php artisan babel:cle');
        $this->moteur();
    }

    public function testCleMalFormee(): void
    {
        $this->configurer('babel.cle', 'base64:pas-une-cle');
        $this->application()->forgetInstance(Moteur::class);

        try {
            $this->moteur();
            self::fail('clé acceptée');
        } catch (CleInvalide $e) {
            self::assertStringNotContainsString('pas-une-cle', $e->getMessage());
        }
    }

    public function testLaConfigurationParDefautLitBabelKey(): void
    {
        $configuration = require __DIR__ . '/../../config/babel.php';
        self::assertIsArray($configuration);
        self::assertArrayHasKey('cle', $configuration);
    }

    public function testLaConfigurationEstPubliable(): void
    {
        $chemins = ServiceProvider::pathsToPublish(MoteurServiceProvider::class, 'babel-config');
        self::assertCount(1, $chemins);
        self::assertSame($this->application()->configPath('babel.php'), array_values($chemins)[0]);
        self::assertFileExists((string) array_key_first($chemins));
    }

    public function testLeFournisseurEstDecouvertAutomatiquement(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertIsArray($composer['extra'] ?? null);
        self::assertIsArray($composer['extra']['laravel'] ?? null);
        self::assertSame([MoteurServiceProvider::class], $composer['extra']['laravel']['providers'] ?? null);
    }

    /**
     * Après « php artisan config:cache », Laravel ne lit plus .env : env('BABEL_KEY') renvoie null et seule
     * config('babel.cle') compte. (Testbench recharge toujours ses fichiers de configuration et ignore le cache :
     * on vérifie donc les deux conditions séparément ; l'essai réel se fait dans un Laravel neuf, voir MAINTENANCE.md.)
     */
    public function testFonctionneApresConfigCache(): void
    {
        // 1. Le fichier de configuration se met en cache (var_export, sans fermeture) et lit BABEL_KEY.
        $cle = 'base64:' . base64_encode(random_bytes(32));
        $_ENV['BABEL_KEY'] = $_SERVER['BABEL_KEY'] = $cle;
        putenv('BABEL_KEY=' . $cle);
        try {
            $configuration = require __DIR__ . '/../../config/babel.php';
            self::assertSame(['cle' => $cle], eval('return ' . var_export($configuration, true) . ';'));
        } finally {
            unset($_ENV['BABEL_KEY'], $_SERVER['BABEL_KEY']);
            putenv('BABEL_KEY');
        }

        // 2. Sans variable d'environnement, le moteur se construit depuis config() seule.
        self::assertNull(env('BABEL_KEY'));
        $this->configurer('babel.cle', $cle);
        $this->application()->forgetInstance(Moteur::class);
        self::assertSame(Moteur::depuisConfiguration($cle)->empreinte(), $this->moteur()->empreinte());

        // 3. Le code du paquet n'appelle jamais env() (hors config/babel.php).
        $sources = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src', \FilesystemIterator::SKIP_DOTS));
        foreach ($sources as $fichier) {
            self::assertInstanceOf(\SplFileInfo::class, $fichier);
            $jetons = array_values(array_filter(
                token_get_all((string) file_get_contents($fichier->getPathname())),
                static fn (array|string $jeton): bool => !is_array($jeton) || !in_array($jeton[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
            ));
            foreach ($jetons as $i => $jeton) {
                $appelEnv = is_array($jeton) && in_array($jeton[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                    && ltrim($jeton[1], '\\') === 'env' && ($jetons[$i + 1] ?? null) === '(';
                self::assertFalse($appelEnv, 'appel à env() dans ' . $fichier->getPathname());
            }
        }
    }
}
