<?php

declare(strict_types=1);

namespace Babel\Moteur\Laravel\Commandes;

use Babel\Moteur\Moteur;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * php artisan babel:cle : tire une clé et écrit BABEL_KEY=base64:… dans .env.
 */
#[AsCommand(name: 'babel:cle', description: 'Tire une clé BABEL_KEY et l\'écrit dans le fichier .env')]
final class GenererCle extends Command
{
    /** @var string */
    protected $signature = 'babel:cle
        {--force : Remplace la clé existante (toutes les adresses déjà partagées changeront de texte)}
        {--afficher : Affiche la ligne BABEL_KEY sans modifier le fichier .env}';

    /** @var string */
    protected $description = 'Tire une clé BABEL_KEY et l\'écrit dans le fichier .env';

    private const LIGNE = '/^BABEL_KEY=([^\r\n]*)(?=\r?$)/m';

    public function handle(): int
    {
        $valeur = 'base64:' . base64_encode(random_bytes(32));

        if ($this->option('afficher') === true) {
            $this->line('BABEL_KEY=' . $valeur);

            return self::SUCCESS;
        }

        $fichier = $this->fichierEnv();
        $contenu = is_file($fichier) ? file_get_contents($fichier) : false;
        if ($contenu === false) {
            $this->components->error('Fichier .env introuvable. Créez-le (cp .env.example .env), ou utilisez --afficher pour copier la ligne vous-même.');

            return self::FAILURE;
        }

        if (self::cleExistante($contenu)) {
            if ($this->option('force') !== true) {
                $this->components->error('Une clé BABEL_KEY existe déjà dans .env : elle n\'a pas été modifiée.');
                $this->line('  La remplacer changerait le texte de toutes les adresses déjà partagées et l\'empreinte de la bibliothèque.');
                $this->line('  Si c\'est vraiment ce que vous voulez : php artisan babel:cle --force');

                return self::FAILURE;
            }
            $this->components->warn('Clé BABEL_KEY remplacée : toutes les adresses déjà partagées désignent désormais un autre texte, et l\'empreinte de la bibliothèque change.');
        }

        if (file_put_contents($fichier, self::ecrire($contenu, 'BABEL_KEY=' . $valeur)) === false) {
            $this->components->error('Impossible d\'écrire dans le fichier .env (droits ?).');

            return self::FAILURE;
        }

        // L'application en cours voit tout de suite la nouvelle clé.
        $moteur = Moteur::depuisConfiguration($valeur);
        $this->laravel->make(Repository::class)->set('babel.cle', $valeur);
        $this->laravel->instance(Moteur::class, $moteur);

        $this->components->info('Clé BABEL_KEY écrite dans .env. Empreinte : ' . $moteur->empreinte());
        if ($this->laravel instanceof CachesConfiguration && $this->laravel->configurationIsCached()) {
            $this->components->warn('La configuration est en cache : relancez « php artisan config:cache » pour que l\'application voie la nouvelle clé.');
        }
        $this->line('  Vérifiez l\'installation avec : php artisan babel:verifier');

        return self::SUCCESS;
    }

    private function fichierEnv(): string
    {
        $chemin = method_exists($this->laravel, 'environmentFilePath') ? $this->laravel->environmentFilePath() : null;

        return is_string($chemin) ? $chemin : $this->laravel->basePath('.env');
    }

    /**
     * Une ligne BABEL_KEY avec une valeur non vide (guillemets et espaces ignorés) ?
     */
    private static function cleExistante(string $contenu): bool
    {
        if (preg_match_all(self::LIGNE, $contenu, $lignes) < 1) {
            return false;
        }
        foreach ($lignes[1] as $valeur) {
            if (trim($valeur, " \t\"'") !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Remplace la ligne BABEL_KEY, ou l'ajoute après APP_KEY (à défaut, à la fin du fichier).
     */
    private static function ecrire(string $contenu, string $ligne): string
    {
        if (preg_match(self::LIGNE, $contenu) === 1) {
            return (string) preg_replace_callback(self::LIGNE, static fn (): string => $ligne, $contenu);
        }
        $finDeLigne = str_contains($contenu, "\r\n") ? "\r\n" : "\n";
        if (preg_match('/^APP_KEY=[^\r\n]*(?=\r?$)/m', $contenu, $appKey, PREG_OFFSET_CAPTURE) === 1) {
            $fin = $appKey[0][1] + strlen($appKey[0][0]);

            return substr($contenu, 0, $fin) . $finDeLigne . $ligne . substr($contenu, $fin);
        }
        $separateur = $contenu === '' || str_ends_with($contenu, "\n") ? '' : $finDeLigne;

        return $contenu . $separateur . $ligne . $finDeLigne;
    }
}
