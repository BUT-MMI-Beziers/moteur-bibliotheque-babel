<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Robustesse;

use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Toute chaîne est acceptée en entrée sans plantage : une valeur ou une ErreurMoteur, en moins de 50 ms,
 * sans avertissement PHP (la suite échoue sur le moindre avertissement, notice ou dépréciation).
 */
final class EntreesHostilesTest extends TestCase
{
    private const DELAI_MAX_MS = 50.0;

    /**
     * @return iterable<string, array{string}>
     */
    public static function entrees(): iterable
    {
        yield 'vide' => [''];
        yield '1 Mo de z' => [str_repeat('z', 1 << 20)];
        yield '8 Mo de z' => [str_repeat('z', 8 << 20)];
        yield '1 Mo de tirets' => [str_repeat('-', 1 << 20)];
        yield 'adresse puis 1 Mo de tirets' => ['0-w1-s1-v' . str_repeat('-', 1 << 20)];
        yield 'adresse puis 1 Mo de deux-points' => ['0-w1-s1-v1:' . str_repeat(':', 1 << 20)];
        yield 'motif répété' => [str_repeat('-w-s-v:', 150000)];
        yield 'octets nuls' => ["\0\0\0"];
        yield 'octet nul dans une adresse' => ["0-w1-s1-v1:1\0"];
        yield 'octet nul dans un texte' => ["abc\0def"];
        yield 'UTF-8 invalide' => ["\xC3\x28\xA0\xA1\xFF\xFE"];
        yield 'UTF-8 invalide dans une adresse' => ["0\xFF-w1-s1-v1:1"];
        yield 'saut de ligne final' => ["0-w1-s1-v1:1\n"];
        yield 'retour chariot final' => ["0-w1-s1-v1:1\r\n"];
        yield 'espaces autour' => ['  0-w1-s1-v1:1  '];
        yield 'majuscules' => ['ABC-W1-S1-V1:1'];
        yield 'nombres géants' => ['0-w' . str_repeat('9', 5000) . '-s1-v1:1'];
        yield 'hexagone géant' => [str_repeat('z', 1 << 20) . '-w1-s1-v1:1'];
        yield 'chiffres non ASCII' => ["0-w\u{0661}-s1-v1:1"];
        yield 'emoji' => ["\u{1F4DA}"];
    }

    #[DataProvider('entrees')]
    public function testLireRefuseProprement(string $entree): void
    {
        $moteur = new Moteur(Vecteurs::cleBrute('A'));
        $debut = hrtime(true);
        try {
            $moteur->lire($entree);
            self::fail('entrée hostile acceptée');
        } catch (ErreurMoteur $e) {
            self::assertLessThan(200, strlen($e->getMessage()));
        }
        self::assertLessThan(self::DELAI_MAX_MS, (hrtime(true) - $debut) / 1e6);
    }

    #[DataProvider('entrees')]
    public function testChercherRefuseProprement(string $entree): void
    {
        $moteur = new Moteur(Vecteurs::cleBrute('A'));
        foreach ([...Moteur::MODES, 'inconnu', $entree] as $mode) {
            $debut = hrtime(true);
            try {
                $moteur->chercher($entree, $mode);
                self::fail('entrée hostile acceptée');
            } catch (ErreurMoteur $e) {
                self::assertCount(1, $e->erreurs());
            }
            self::assertLessThan(self::DELAI_MAX_MS, (hrtime(true) - $debut) / 1e6);
        }
    }

    public function testModeHostile(): void
    {
        $moteur = new Moteur(Vecteurs::cleBrute('A'));
        foreach (['', "exact\0", "exact\n", ' exact', 'EXACT', str_repeat('x', 1 << 20)] as $mode) {
            try {
                $moteur->chercher('abc', $mode);
                self::fail('mode accepté');
            } catch (ErreurMoteur $e) {
                self::assertSame('ERR_UNKNOWN_MODE (mode)', $e->getMessage());
            }
        }
    }
}
