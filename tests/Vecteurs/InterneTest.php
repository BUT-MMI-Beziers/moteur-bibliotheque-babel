<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Vecteurs;

use Babel\Moteur\Interne\Cle;
use Babel\Moteur\Interne\Composition;
use Babel\Moteur\Interne\Permutation;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rubrique cles[].interne : F, P, P_inverse et le flux du mode bruit, plus les empreintes.
 */
final class InterneTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function blocs(): iterable
    {
        foreach (Vecteurs::cles() as $bloc) {
            yield 'clé ' . $bloc['nom'] => [$bloc['nom'], $bloc['babel_key']];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function noms(): iterable
    {
        foreach (Vecteurs::cles() as $bloc) {
            yield 'clé ' . $bloc['nom'] => [$bloc['nom']];
        }
    }

    #[DataProvider('blocs')]
    public function testEmpreinte(string $nom, string $babelKey): void
    {
        $bloc = self::bloc($nom);
        self::assertSame($bloc['empreinte'], Moteur::depuisConfiguration($babelKey)->empreinte());
        self::assertSame($bloc['empreinte'], (new Moteur(Vecteurs::cleBrute($nom)))->empreinte());
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $bloc['empreinte']);
    }

    #[DataProvider('noms')]
    public function testFonctionDeTour(string $nom): void
    {
        $permutation = new Permutation(Cle::deriver(Vecteurs::cleBrute($nom), Cle::ETIQUETTE_PERMUTATION));
        $vus = 0;
        foreach (self::bloc($nom)['interne'] as $cas) {
            if (isset($cas['f_hex'], $cas['tour'], $cas['x_hex'])) {
                self::assertSame($cas['f_hex'], gmp_strval($permutation->f($cas['tour'], gmp_init($cas['x_hex'], 16)), 16));
                $vus++;
            }
        }
        self::assertSame(4, $vus);
    }

    #[DataProvider('noms')]
    public function testPermutationEtInverse(string $nom): void
    {
        $permutation = new Permutation(Cle::deriver(Vecteurs::cleBrute($nom), Cle::ETIQUETTE_PERMUTATION));
        $vus = 0;
        foreach (self::bloc($nom)['interne'] as $cas) {
            if (isset($cas['p_hex'], $cas['x_hex'])) {
                self::assertSame($cas['p_hex'], gmp_strval($permutation->p(gmp_init($cas['x_hex'], 16)), 16));
                self::assertSame($cas['x_hex'], gmp_strval($permutation->pInverse(gmp_init($cas['p_hex'], 16)), 16));
                $vus++;
            }
        }
        self::assertSame(4, $vus);
    }

    #[DataProvider('noms')]
    public function testFluxDuModeBruit(string $nom): void
    {
        $composition = new Composition(Cle::deriver(Vecteurs::cleBrute($nom), Cle::ETIQUETTE_BRUIT));
        $vus = 0;
        foreach (self::bloc($nom)['interne'] as $cas) {
            if (isset($cas['bruit_texte'], $cas['position'], $cas['remplissage_debut'], $cas['remplissage_sha256'])) {
                [$position, $remplissage] = $composition->bruit($cas['bruit_texte']);
                self::assertSame($cas['position'], $position);
                self::assertSame(Moteur::LONGUEUR_PAGE - strlen($cas['bruit_texte']), strlen($remplissage));
                self::assertSame($cas['remplissage_debut'], substr($remplissage, 0, strlen($cas['remplissage_debut'])));
                self::assertSame($cas['remplissage_sha256'], hash('sha256', $remplissage));
                $vus++;
            }
        }
        self::assertSame(1, $vus);
    }

    /**
     * @return array{nom: string, cle_hex: string, babel_key: string, empreinte: string, lire: list<array{adresse: string, page: string, page_sha256: string, applications: int}>, chercher: list<array{texte: string, mode: string, adresse: string, adresse_sha256: string, page_sha256: string, applications: int, position?: int}>, interne: list<array{tour?: int, x_hex?: string, f_hex?: string, p_hex?: string, bruit_texte?: string, position?: int, remplissage_debut?: string, remplissage_sha256?: string}>}
     */
    private static function bloc(string $nom): array
    {
        foreach (Vecteurs::cles() as $bloc) {
            if ($bloc['nom'] === $nom) {
                return $bloc;
            }
        }
        throw new \InvalidArgumentException($nom);
    }
}
