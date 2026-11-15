<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Vecteurs;

use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\TestCase;

final class ConstantesTest extends TestCase
{
    public function testConstantesDeLAlgorithme(): void
    {
        $attendu = Vecteurs::donnees()['constantes'];
        $n = Constantes::get()->n;

        self::assertSame($attendu['alphabet'], Moteur::ALPHABET);
        self::assertSame($attendu['longueur_page'], Moteur::LONGUEUR_PAGE);
        self::assertSame($attendu['bits'], Constantes::BITS);
        self::assertSame($attendu['bits'], strlen(gmp_strval($n, 2)));
        self::assertSame($attendu['demi'], Constantes::DEMI);
        self::assertSame(intdiv(Constantes::BITS, 2), Constantes::DEMI);
        self::assertSame($attendu['octets_demi'], Constantes::OCTETS_DEMI);
        self::assertSame($attendu['tours'], Constantes::TOURS);
        self::assertSame($attendu['blocs_par_tour'], Constantes::BLOCS);
        self::assertSame($attendu['pages_par_hexagone'], Constantes::PAGES_PAR_HEXAGONE);
        self::assertSame($attendu['pages_par_hexagone'], Moteur::MURS * Moteur::ETAGERES * Moteur::VOLUMES * Moteur::PAGES);
    }

    public function testNombreDePages(): void
    {
        $attendu = Vecteurs::donnees()['constantes'];
        $decimal = gmp_strval(Constantes::get()->n, 10);

        self::assertSame($attendu['nombre_de_pages_chiffres'], strlen($decimal));
        self::assertSame($attendu['nombre_de_pages_sha256'], hash('sha256', $decimal));
        self::assertSame(1, gmp_intval(gmp_mod(Constantes::get()->n, Constantes::PAGES_PAR_HEXAGONE)));
    }

    public function testDernierHexagoneEtDerniereAdresse(): void
    {
        $attendu = Vecteurs::donnees()['constantes'];
        $hmax = gmp_strval(Constantes::get()->hexagoneMax, 36);

        self::assertSame($attendu['hexagone_max_longueur'], strlen($hmax));
        self::assertSame(Moteur::LONGUEUR_MAX_HEXAGONE, strlen($hmax));
        self::assertSame($attendu['hexagone_max_sha256'], hash('sha256', $hmax));
        self::assertStringStartsWith('4rlo36cp', $hmax);

        $derniere = Adresse::ecrire(gmp_sub(Constantes::get()->n, 1));
        self::assertSame($attendu['derniere_adresse_sha256'], hash('sha256', $derniere));
        self::assertSame($hmax . '-w1-s1-v1:1', $derniere);
    }

    /**
     * Les constantes publiques font partie du contrat : aucune ne disparaît ni ne change.
     */
    public function testConstantesPubliquesDuMoteur(): void
    {
        self::assertSame([
            'ALPHABET' => 'abcdefghijklmnopqrstuvwxyz ,.',
            'LONGUEUR_PAGE' => 3200,
            'MURS' => 4,
            'ETAGERES' => 5,
            'VOLUMES' => 32,
            'PAGES' => 410,
            'LONGUEUR_MAX_HEXAGONE' => 3004,
            'MODES' => ['exact', 'bruit', 'page'],
            'ALGORITHME' => 'babel-moteur/v1',
        ], (new \ReflectionClass(Moteur::class))->getConstants());
    }
}
