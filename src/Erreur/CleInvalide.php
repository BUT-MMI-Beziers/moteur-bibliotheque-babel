<?php

declare(strict_types=1);

namespace Babel\Moteur\Erreur;

/**
 * La clé est absente ou mal formée. C'est une erreur d'installation, pas une erreur d'entrée.
 *
 * Aucun message ne contient la valeur reçue, même partielle.
 */
final class CleInvalide extends \RuntimeException
{
    private const CONSEIL = ' Lancez « php artisan babel:cle » pour en créer une, puis « php artisan babel:verifier ».';

    public static function absente(): self
    {
        return new self('La clé BABEL_KEY est absente : le moteur refuse de démarrer sans clé.' . self::CONSEIL);
    }

    public static function prefixe(): self
    {
        return new self('La clé BABEL_KEY doit commencer par « base64: ».' . self::CONSEIL);
    }

    public static function base64(): self
    {
        return new self('La clé BABEL_KEY n\'est pas un base64 valide (mal recopiée ?).' . self::CONSEIL);
    }

    public static function taille(): self
    {
        return new self('La clé doit faire exactement 32 octets.' . self::CONSEIL);
    }
}
