<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Vecteurs;

use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Interne\Cle;
use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Interne\Page;
use Babel\Moteur\Interne\Permutation;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rubriques cles[].lire et cles[].chercher.
 */
final class LireChercherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string, int}>
     */
    public static function lectures(): iterable
    {
        foreach (Vecteurs::cles() as $bloc) {
            foreach ($bloc['lire'] as $i => $cas) {
                yield sprintf('clé %s, lecture %d', $bloc['nom'], $i) => [$bloc['nom'], $cas['adresse'], $cas['page'], $cas['page_sha256'], $cas['applications']];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, string, int, int|null}>
     */
    public static function recherches(): iterable
    {
        foreach (Vecteurs::cles() as $bloc) {
            foreach ($bloc['chercher'] as $i => $cas) {
                yield sprintf('clé %s, recherche %d (%s)', $bloc['nom'], $i, $cas['mode']) => [
                    $bloc['nom'], $cas['texte'], $cas['mode'], $cas['adresse'], $cas['adresse_sha256'], $cas['page_sha256'], $cas['applications'], $cas['position'] ?? null,
                ];
            }
        }
    }

    #[DataProvider('lectures')]
    public function testLire(string $nom, string $adresse, string $page, string $sha, int $applications): void
    {
        $moteur = new Moteur(Vecteurs::cleBrute($nom));

        self::assertSame($page, $moteur->lire($adresse));
        self::assertSame($sha, hash('sha256', $page));
        self::assertSame($adresse, $moteur->chercher($page, 'page'));
        self::assertSame($applications, self::applications($nom, Adresse::analyser($adresse), false));
    }

    #[DataProvider('recherches')]
    public function testChercher(string $nom, string $texte, string $mode, string $adresse, string $shaAdresse, string $shaPage, int $applications, ?int $position): void
    {
        $moteur = new Moteur(Vecteurs::cleBrute($nom));

        self::assertSame($adresse, $moteur->chercher($texte, $mode));
        self::assertSame($shaAdresse, hash('sha256', $adresse));

        $page = $moteur->lire($adresse);
        self::assertSame($shaPage, hash('sha256', $page));
        self::assertStringContainsString($texte, $page);
        self::assertSame($applications, self::applications($nom, Page::versNombre($page), true));

        if ($mode === 'exact') {
            self::assertSame(str_pad($texte, Moteur::LONGUEUR_PAGE, ' '), $page);
        }
        if ($mode === 'bruit') {
            self::assertNotNull($position);
            // Le texte commence à la position annoncée (il peut aussi apparaître plus tôt par hasard).
            self::assertSame($texte, substr($page, $position, strlen($texte)));
        }
    }

    public function testLeCycleWalkingEstCouvertParLesVecteurs(): void
    {
        $applications = [];
        foreach (Vecteurs::cles() as $bloc) {
            foreach ([...$bloc['lire'], ...$bloc['chercher']] as $cas) {
                $applications[] = $cas['applications'];
            }
        }
        self::assertContains(2, $applications);
        self::assertContains(3, $applications);
    }

    public function testValeursDeControleALOeil(): void
    {
        $a = new Moteur(Vecteurs::cleBrute('A'));
        $b = new Moteur(Vecteurs::cleBrute('B'));

        self::assertStringStartsWith('tnznd.olonj xxqvbefublhf', $a->lire('0-w1-s1-v1:1'));
        self::assertStringStartsWith(',cehb,fslipiv.teoc,bhyob', $b->lire('0-w1-s1-v1:1'));
        self::assertStringStartsWith('43sxzqy307xbxpf7', $a->chercher('ou est passe l ete', 'exact'));
        self::assertSame(3018, strlen($a->chercher('ou est passe l ete', 'exact')));
        self::assertStringStartsWith('1qwvi3hzgq26x04u', $b->chercher('ou est passe l ete', 'exact'));
        self::assertStringStartsWith('2zck7fxg6c3k2qax', $a->chercher('ou est passe l ete', 'bruit'));
        self::assertStringStartsWith('3nqcdg6mkzde7llu', $b->chercher('ou est passe l ete', 'bruit'));
    }

    /**
     * Nombre d'applications de P (ou de P_inverse) avant de retomber dans la bibliothèque.
     */
    private static function applications(string $nom, \GMP $depart, bool $inverse): int
    {
        $permutation = new Permutation(Cle::deriver(Vecteurs::cleBrute($nom), Cle::ETIQUETTE_PERMUTATION));
        $n = Constantes::get()->n;
        $x = $depart;
        $k = 0;
        do {
            $x = $inverse ? $permutation->pInverse($x) : $permutation->p($x);
            $k++;
        } while (gmp_cmp($x, $n) >= 0);

        return $k;
    }
}
