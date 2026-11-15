<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Proprietes;

use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Hasard;
use PHPUnit\Framework\TestCase;

/**
 * Propriétés de la section 12, sur 200 tirages au moins, avec une clé tirée au sort.
 */
final class ProprietesTest extends TestCase
{
    private const TIRAGES = 200;
    private const ADRESSE_CANONIQUE = '/\A(0|[1-9a-z][0-9a-z]*)-w[1-4]-s[1-5]-v([1-9]|[12][0-9]|3[0-2]):([1-9][0-9]{0,2})\z/';

    public function testAllerRetourDepuisUneAdresse(): void
    {
        $moteur = Hasard::moteur();
        for ($i = 0; $i < self::TIRAGES; $i++) {
            $numero = Hasard::numero();
            $adresse = Adresse::ecrire($numero);
            self::assertSame(0, gmp_cmp($numero, Adresse::analyser($adresse)));

            $page = $moteur->lire($adresse);
            self::assertSame(Moteur::LONGUEUR_PAGE, strlen($page));
            self::assertSame(Moteur::LONGUEUR_PAGE, strspn($page, Moteur::ALPHABET));
            self::assertSame($adresse, $moteur->chercher($page, 'page'));
        }
    }

    public function testAllerRetourDepuisUnePage(): void
    {
        $moteur = Hasard::moteur();
        for ($i = 0; $i < self::TIRAGES; $i++) {
            $page = Hasard::page();
            $adresse = $moteur->chercher($page, 'page');
            self::assertMatchesRegularExpression(self::ADRESSE_CANONIQUE, $adresse);
            self::assertLessThanOrEqual(3018, strlen($adresse));
            self::assertSame($page, $moteur->lire($adresse));
        }
    }

    public function testModesExactEtBruit(): void
    {
        $moteur = Hasard::moteur();
        for ($i = 0; $i < self::TIRAGES; $i++) {
            $longueur = random_int(1, $i % 10 === 0 ? Moteur::LONGUEUR_PAGE : 60);
            $texte = substr(Hasard::page(), 0, $longueur);

            $exacte = $moteur->chercher($texte, 'exact');
            self::assertSame($exacte, $moteur->chercher($texte, 'exact'));
            self::assertSame(str_pad($texte, Moteur::LONGUEUR_PAGE, ' '), $moteur->lire($exacte));

            $bruitee = $moteur->chercher($texte, 'bruit');
            self::assertSame($bruitee, $moteur->chercher($texte, 'bruit'));
            self::assertStringContainsString($texte, $moteur->lire($bruitee));
        }
    }

    public function testUnTexteDe3200CaracteresDonneLaMemePageDansLesTroisModes(): void
    {
        $moteur = Hasard::moteur();
        $page = Hasard::page();
        $adresse = $moteur->chercher($page, 'page');
        self::assertSame($adresse, $moteur->chercher($page, 'exact'));
        self::assertSame($adresse, $moteur->chercher($page, 'bruit'));
    }

    public function testDeuxAdressesVoisinesDonnentDesPagesSansRapport(): void
    {
        $moteur = Hasard::moteur();
        $n = Constantes::get()->n;
        for ($i = 0; $i < self::TIRAGES; $i++) {
            $numero = Hasard::numero();
            $voisin = gmp_cmp(gmp_add($numero, 1), $n) < 0 ? gmp_add($numero, 1) : gmp_sub($numero, 1);
            $a = $moteur->lire(Adresse::ecrire($numero));
            $b = $moteur->lire(Adresse::ecrire($voisin));
            $identiques = Moteur::LONGUEUR_PAGE - strlen(str_replace("\0", '', $a ^ $b));
            self::assertLessThan(0.10 * Moteur::LONGUEUR_PAGE, $identiques);
        }
    }

    public function testDeuxClesDifferentesDonnentDesResultatsDifferents(): void
    {
        for ($i = 0; $i < self::TIRAGES; $i++) {
            $a = Hasard::moteur();
            $b = Hasard::moteur();
            $adresse = Adresse::ecrire(Hasard::numero());
            self::assertNotSame($a->empreinte(), $b->empreinte());
            self::assertNotSame($a->lire($adresse), $b->lire($adresse));
            if ($i % 20 === 0) {
                self::assertNotSame($a->chercher('la bibliotheque', 'exact'), $b->chercher('la bibliotheque', 'exact'));
                self::assertNotSame($a->chercher('la bibliotheque', 'bruit'), $b->chercher('la bibliotheque', 'bruit'));
            }
        }
    }

    public function testBornesDeLaBibliotheque(): void
    {
        $moteur = Hasard::moteur();
        $hmax = Constantes::get()->hexagoneMax;
        $hmaxTexte = gmp_strval($hmax, 36);

        self::assertSame(Moteur::LONGUEUR_PAGE, strlen($moteur->lire('0-w1-s1-v1:1')));
        self::assertSame(Moteur::LONGUEUR_PAGE, strlen($moteur->lire($hmaxTexte . '-w1-s1-v1:1')));

        foreach ([$hmaxTexte . '-w1-s1-v1:2' => 'ERR_OUT_OF_BOUNDS (adresse)', gmp_strval(gmp_add($hmax, 1), 36) . '-w1-s1-v1:1' => 'ERR_OUT_OF_BOUNDS (hexagone)'] as $adresse => $message) {
            try {
                $moteur->lire($adresse);
                self::fail('adresse acceptée');
            } catch (ErreurMoteur $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function testUnMoteurEstReutilisableEtSansEtat(): void
    {
        $cle = random_bytes(32);
        $a = new Moteur($cle);
        $adresse = '1x-w2-s3-v4:5';
        $page = $a->lire($adresse);
        $a->chercher('autre chose', 'bruit');
        try {
            $a->lire('mauvaise adresse');
        } catch (ErreurMoteur) {
        }
        self::assertSame($page, $a->lire($adresse));
        self::assertSame($page, (new Moteur($cle))->lire($adresse));
        self::assertSame($page, (clone $a)->lire($adresse));
    }
}
