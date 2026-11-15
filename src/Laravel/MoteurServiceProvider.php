<?php

declare(strict_types=1);

namespace Babel\Moteur\Laravel;

use Babel\Moteur\Erreur\CleInvalide;
use Babel\Moteur\Laravel\Commandes\GenererCle;
use Babel\Moteur\Laravel\Commandes\Verifier;
use Babel\Moteur\Moteur;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Intègre le moteur à Laravel. Découvert automatiquement (extra.laravel.providers).
 *
 * - fusionne config/babel.php (publiable : php artisan vendor:publish --tag=babel-config) ;
 * - déclare Moteur en singleton, construit à la première injection depuis config('babel.cle') ;
 * - enregistre les commandes babel:cle et babel:verifier.
 */
final class MoteurServiceProvider extends ServiceProvider
{
    private const CONFIGURATION = __DIR__ . '/../../config/babel.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIGURATION, 'babel');

        $this->app->singleton(Moteur::class, static function (Container $app): Moteur {
            return Moteur::depuisConfiguration(self::valeurDeLaCle($app));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIGURATION => $this->app->configPath('babel.php')], 'babel-config');
            $this->commands([GenererCle::class, Verifier::class]);
        }
    }

    /**
     * La valeur de BABEL_KEY, lue par config() (jamais par env(), qui renvoie null après config:cache).
     *
     * @throws CleInvalide si la clé est absente
     */
    public static function valeurDeLaCle(Container $app): string
    {
        $valeur = $app->make(Repository::class)->get('babel.cle');
        if (!is_string($valeur) || $valeur === '') {
            throw CleInvalide::absente();
        }

        return $valeur;
    }
}
