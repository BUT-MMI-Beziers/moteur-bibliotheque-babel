<?php

declare(strict_types=1);

namespace Babel\Moteur\Interne;

/**
 * Une page est un nombre écrit en base 29 avec l'alphabet, premier caractère de poids fort.
 *
 * @internal
 */
final class Page
{
    /**
     * @param string $texte exactement 3 200 caractères de l'alphabet (déjà contrôlés)
     */
    public static function versNombre(string $texte): \GMP
    {
        return gmp_init(strtr($texte, Constantes::ALPHABET, Constantes::CHIFFRES29), 29);
    }

    /**
     * @param \GMP $contenu entre 0 et N − 1
     */
    public static function versTexte(\GMP $contenu): string
    {
        $chiffres = str_pad(gmp_strval($contenu, 29), Constantes::LONGUEUR_PAGE, '0', STR_PAD_LEFT);

        return strtr($chiffres, Constantes::CHIFFRES29, Constantes::ALPHABET);
    }
}
