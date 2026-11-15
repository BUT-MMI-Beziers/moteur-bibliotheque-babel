<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Unitaire;

use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Interne\Page;
use Babel\Moteur\Moteur;
use PHPUnit\Framework\TestCase;

final class AdressePageTest extends TestCase
{
    public function testPremiereEtDerniereAdresse(): void
    {
        $n = Constantes::get()->n;
        $hmax = gmp_strval(Constantes::get()->hexagoneMax, 36);

        self::assertSame(0, gmp_cmp(Adresse::analyser('0-w1-s1-v1:1'), 0));
        self::assertSame('0-w1-s1-v1:1', Adresse::ecrire(gmp_init(0)));
        self::assertSame(0, gmp_cmp(Adresse::analyser($hmax . '-w1-s1-v1:1'), gmp_sub($n, 1)));
        self::assertSame('0-w4-s5-v32:410', Adresse::ecrire(gmp_init(Constantes::PAGES_PAR_HEXAGONE - 1)));
        self::assertSame('1-w1-s1-v1:1', Adresse::ecrire(gmp_init(Constantes::PAGES_PAR_HEXAGONE)));
    }

    public function testEcrireEtAnalyserSontReciproques(): void
    {
        foreach (['0', '1', '409', '410', '13119', '262399', '262400', '123456789012345678901234567890'] as $decimal) {
            $numero = gmp_init($decimal);
            $adresse = Adresse::ecrire($numero);
            self::assertSame(0, gmp_cmp($numero, Adresse::analyser($adresse)), $adresse);
        }
    }

    public function testAdresseDeLivreSansPageRefusee(): void
    {
        try {
            Adresse::analyser('babel-w2-s3-v17');
            self::fail('adresse acceptée');
        } catch (ErreurMoteur $e) {
            self::assertSame('ERR_INVALID_ADDRESS_SYNTAX (page)', $e->getMessage());
        }
    }

    public function testLimitePcreTraiteeCommeAdresseMalEcrite(): void
    {
        $jit = ini_get('pcre.jit');
        $limite = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');
        try {
            Adresse::analyser('0-w1-s1-v1:1');
            self::fail('adresse acceptée malgré l\'échec de PCRE');
        } catch (ErreurMoteur $e) {
            self::assertSame('ERR_INVALID_ADDRESS_SYNTAX (adresse)', $e->getMessage());
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limite);
        }
    }

    public function testPageVersNombreEtRetour(): void
    {
        $premiere = str_repeat('a', Moteur::LONGUEUR_PAGE);
        $derniere = str_repeat('.', Moteur::LONGUEUR_PAGE);

        self::assertSame(0, gmp_cmp(Page::versNombre($premiere), 0));
        self::assertSame($premiere, Page::versTexte(gmp_init(0)));
        self::assertSame(0, gmp_cmp(Page::versNombre($derniere), gmp_sub(Constantes::get()->n, 1)));
        self::assertSame($derniere, Page::versTexte(gmp_sub(Constantes::get()->n, 1)));
        self::assertSame(str_repeat('a', 3199) . 'b', Page::versTexte(gmp_init(1)));
        self::assertSame(str_repeat('a', 3198) . 'ba', Page::versTexte(gmp_init(29)));
    }
}
