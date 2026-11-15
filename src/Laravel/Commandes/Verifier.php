<?php

declare(strict_types=1);

namespace Babel\Moteur\Laravel\Commandes;

use Babel\Moteur\Erreur\CleInvalide;
use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Laravel\MoteurServiceProvider;
use Babel\Moteur\Moteur;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * php artisan babel:verifier : la commande à lancer après l'installation.
 *
 * Vérifie l'extension gmp, la présence et la forme de la clé, rejoue les vecteurs de référence avec les clés
 * de test, fait un aller-retour avec la vraie clé, puis affiche l'empreinte et le temps moyen d'une lecture.
 * Code de sortie 0 si tout va bien, 1 sinon. La clé n'est jamais affichée.
 */
#[AsCommand(name: 'babel:verifier', description: 'Vérifie que le moteur de la Bibliothèque de Babel est correctement installé')]
final class Verifier extends Command
{
    /** @var string */
    protected $signature = 'babel:verifier';

    /** @var string */
    protected $description = 'Vérifie que le moteur de la Bibliothèque de Babel est correctement installé';

    private const VERIFICATION = __DIR__ . '/../../../resources/verification.json';
    private const LECTURES_CHRONOMETREES = 50;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Moteur de la Bibliothèque de Babel</> (' . Moteur::ALGORITHME . ')');
        $this->newLine();

        if (!extension_loaded('gmp')) {
            $this->echec('Extension gmp', 'Activez l\'extension gmp de PHP (voir le README), pour la ligne de commande et pour le serveur web.');

            return self::FAILURE;
        }
        $this->reussite('Extension gmp', 'GMP ' . GMP_VERSION);

        try {
            $moteur = Moteur::depuisConfiguration(MoteurServiceProvider::valeurDeLaCle($this->laravel));
        } catch (CleInvalide $e) {
            $this->echec('Clé BABEL_KEY', $e->getMessage());

            return self::FAILURE;
        }
        $this->reussite('Clé BABEL_KEY', 'présente et bien formée');

        try {
            $nombre = self::rejouerLesVecteurs();
        } catch (\Throwable $e) {
            $this->echec('Vecteurs de référence', 'Résultat différent de la référence (' . $e->getMessage() . '). Vérifiez la version de PHP et de l\'extension gmp, puis réinstallez le paquet.');

            return self::FAILURE;
        }
        $this->reussite('Vecteurs de référence (clés de test A et B)', $nombre . '/' . $nombre);

        try {
            self::allerRetour($moteur);
        } catch (\Throwable $e) {
            $this->echec('Aller-retour avec la clé de la bibliothèque', $e->getMessage());

            return self::FAILURE;
        }
        $this->reussite('Aller-retour avec la clé de la bibliothèque', 'lire ⇄ chercher');

        $debut = hrtime(true);
        for ($i = 1; $i <= self::LECTURES_CHRONOMETREES; $i++) {
            $moteur->lire('babel-w1-s1-v1:' . $i);
        }
        $millisecondes = (hrtime(true) - $debut) / 1e6 / self::LECTURES_CHRONOMETREES;

        $this->newLine();
        $this->components->twoColumnDetail('Empreinte de la clé', $moteur->empreinte());
        $this->components->twoColumnDetail('Lecture moyenne', sprintf('%.2f ms (sur %d lectures)', $millisecondes, self::LECTURES_CHRONOMETREES));
        $this->newLine();
        $this->components->info('Tout est prêt : le moteur est utilisable.');

        return self::SUCCESS;
    }

    private function reussite(string $etape, string $detail): void
    {
        $this->components->twoColumnDetail($etape, '<fg=gray>' . $detail . '</> <fg=green;options=bold>OK</>');
    }

    private function echec(string $etape, string $explication): void
    {
        $this->components->twoColumnDetail($etape, '<fg=red;options=bold>ÉCHEC</>');
        $this->newLine();
        $this->components->error($explication);
    }

    /**
     * Rejoue resources/verification.json (tiré de reference/vecteurs.json) et renvoie le nombre de contrôles.
     */
    private static function rejouerLesVecteurs(): int
    {
        $json = file_get_contents(self::VERIFICATION);
        if ($json === false) {
            throw new \UnexpectedValueException('fichier resources/verification.json illisible');
        }
        $donnees = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $nombre = 0;
        foreach (self::tableaux($donnees, 'cles') as $bloc) {
            $nom = self::chaine($bloc, 'nom');
            $moteur = Moteur::depuisConfiguration(self::chaine($bloc, 'babel_key'));
            self::attendre($moteur->empreinte() === self::chaine($bloc, 'empreinte'), 'empreinte de la clé ' . $nom);
            $nombre++;
            foreach (self::tableaux($bloc, 'lire') as $i => $cas) {
                $page = $moteur->lire(self::chaine($cas, 'adresse'));
                self::attendre(hash('sha256', $page) === self::chaine($cas, 'page_sha256'), sprintf('lecture %d, clé %s', $i, $nom));
                $nombre++;
            }
            foreach (self::tableaux($bloc, 'chercher') as $i => $cas) {
                $adresse = $moteur->chercher(self::chaine($cas, 'texte'), self::chaine($cas, 'mode'));
                self::attendre(hash('sha256', $adresse) === self::chaine($cas, 'adresse_sha256'), sprintf('recherche %d, clé %s', $i, $nom));
                $nombre++;
            }
        }

        return $nombre;
    }

    private static function allerRetour(Moteur $moteur): void
    {
        $derniere = Adresse::ecrire(gmp_sub(Constantes::get()->n, 1));
        foreach (['0-w1-s1-v1:1', '1x-w2-s3-v4:5', 'babel-w4-s5-v32:410', $derniere] as $adresse) {
            $page = $moteur->lire($adresse);
            self::attendre(strlen($page) === Moteur::LONGUEUR_PAGE, 'une page lue ne fait pas 3 200 caractères');
            self::attendre($moteur->chercher($page, 'page') === $adresse, 'chercher(lire(adresse), "page") ne redonne pas l\'adresse');
        }
        $texte = 'la bibliotheque de babel';
        self::attendre($moteur->lire($moteur->chercher($texte, 'exact')) === str_pad($texte, Moteur::LONGUEUR_PAGE, ' '), 'le mode exact ne redonne pas le texte');
        self::attendre(str_contains($moteur->lire($moteur->chercher($texte, 'bruit')), $texte), 'le mode bruit ne redonne pas le texte');
    }

    private static function attendre(bool $condition, string $quoi): void
    {
        if (!$condition) {
            throw new \UnexpectedValueException($quoi);
        }
    }

    /**
     * @return array<int, array<mixed>>
     */
    private static function tableaux(mixed $parent, string $champ): array
    {
        $valeur = is_array($parent) ? ($parent[$champ] ?? null) : null;
        if (!is_array($valeur) || !array_is_list($valeur)) {
            throw new \UnexpectedValueException('champ « ' . $champ . ' » absent');
        }

        return array_filter($valeur, 'is_array');
    }

    /**
     * @param array<mixed> $parent
     */
    private static function chaine(array $parent, string $champ): string
    {
        $valeur = $parent[$champ] ?? null;
        if (!is_string($valeur)) {
            throw new \UnexpectedValueException('champ « ' . $champ . ' » absent');
        }

        return $valeur;
    }
}
