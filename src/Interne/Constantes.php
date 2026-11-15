<?php

declare(strict_types=1);

namespace Babel\Moteur\Interne;

/**
 * Constantes de l'algorithme babel-moteur/v1. Les grands nombres sont calculés une fois par processus.
 *
 * @internal
 */
final class Constantes
{
    public const ALPHABET = 'abcdefghijklmnopqrstuvwxyz ,.';
    /** Chiffres de gmp_strval() en base 29, dans l'ordre de l'alphabet. */
    public const CHIFFRES29 = '0123456789abcdefghijklmnopqrs';
    public const LONGUEUR_PAGE = 3200;
    public const MURS = 4;
    public const ETAGERES = 5;
    public const VOLUMES = 32;
    public const PAGES = 410;
    public const PAGES_PAR_HEXAGONE = self::MURS * self::ETAGERES * self::VOLUMES * self::PAGES;
    public const LONGUEUR_MAX_HEXAGONE = 3004;
    /** Nombre de bits de N = 29^3200. */
    public const BITS = 15546;
    public const DEMI = 7773;
    public const OCTETS_DEMI = 972;
    public const TOURS = 8;
    public const BLOCS = 31;
    /** Garde-fou du cycle-walking : ne se déclenche jamais, sauf bogue. */
    public const APPLICATIONS_MAX = 1000;

    private static ?self $instance = null;

    /** N = 29^3200, le nombre de pages. */
    public readonly \GMP $n;
    /** 2^7773 − 1. */
    public readonly \GMP $masque;
    /** 2^7773, pour les décalages de 7 773 bits. */
    public readonly \GMP $deuxPuissanceDemi;
    /** Hmax = (N − 1) div 262 400. */
    public readonly \GMP $hexagoneMax;

    private function __construct()
    {
        $this->n = gmp_pow(29, self::LONGUEUR_PAGE);
        $this->deuxPuissanceDemi = gmp_pow(2, self::DEMI);
        $this->masque = gmp_sub($this->deuxPuissanceDemi, 1);
        $this->hexagoneMax = gmp_div_q(gmp_sub($this->n, 1), self::PAGES_PAR_HEXAGONE);
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }
}
