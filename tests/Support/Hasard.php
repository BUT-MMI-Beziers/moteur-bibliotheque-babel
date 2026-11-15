<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Support;

use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Moteur;

/**
 * Tirages pour les tests de propriétés (jamais utilisé par le moteur lui-même).
 */
final class Hasard
{
    /** Un numéro de page uniforme entre 0 et N − 1, par rejet sur 15 546 bits. */
    public static function numero(): \GMP
    {
        $n = Constantes::get()->n;
        $masque = gmp_sub(gmp_pow(2, Constantes::BITS), 1);
        do {
            $x = gmp_and(gmp_import(random_bytes(2 * Constantes::OCTETS_DEMI)), $masque);
        } while (gmp_cmp($x, $n) >= 0);

        return $x;
    }

    /** Une page de 3 200 caractères tirés uniformément dans l'alphabet. */
    public static function page(): string
    {
        $page = '';
        for ($i = 0; $i < Moteur::LONGUEUR_PAGE; $i++) {
            $page .= Moteur::ALPHABET[random_int(0, 28)];
        }

        return $page;
    }

    public static function moteur(): Moteur
    {
        return new Moteur(random_bytes(32));
    }
}
