<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Laravel;

use Babel\Moteur\Laravel\MoteurServiceProvider;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

abstract class CasLaravel extends TestCase
{
    /** Dossier temporaire qui sert de racine au fichier .env pendant le test. */
    protected string $dossierEnv = '';

    /**
     * @param Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MoteurServiceProvider::class];
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('babel.cle', Vecteurs::donnees()['cles'][0]['babel_key']);

        $this->dossierEnv = sys_get_temp_dir() . '/babel-moteur-' . bin2hex(random_bytes(6));
        mkdir($this->dossierEnv);
        $app->useEnvironmentPath($this->dossierEnv);
    }

    protected function tearDown(): void
    {
        if ($this->dossierEnv !== '' && is_dir($this->dossierEnv)) {
            foreach (scandir($this->dossierEnv) ?: [] as $nom) {
                if (is_file($this->dossierEnv . '/' . $nom)) {
                    unlink($this->dossierEnv . '/' . $nom);
                }
            }
            rmdir($this->dossierEnv);
        }
        parent::tearDown();
    }

    protected function application(): Application
    {
        self::assertNotNull($this->app);

        return $this->app;
    }

    protected function configurer(string $cle, mixed $valeur): void
    {
        $this->application()->make(Repository::class)->set($cle, $valeur);
    }

    protected function moteur(): Moteur
    {
        return $this->application()->make(Moteur::class);
    }

    protected function fichierEnv(): string
    {
        return $this->dossierEnv . '/.env';
    }
}
