<?php

declare(strict_types=1);

namespace Babel\Moteur\Interne;

use Babel\Moteur\Erreur\CleInvalide;

/**
 * Lecture de BABEL_KEY et dérivation des sous-clés. La clé K n'est jamais utilisée directement.
 *
 * @internal
 */
final class Cle
{
    public const TAILLE = 32;
    public const PREFIXE = 'base64:';
    public const ETIQUETTE_PERMUTATION = 'babel-moteur/v1/permutation';
    public const ETIQUETTE_BRUIT = 'babel-moteur/v1/bruit';
    public const ETIQUETTE_EMPREINTE = 'babel-moteur/v1/empreinte';

    private const BASE64 = '#\A(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?\z#';

    /**
     * Décode une valeur « base64:… » en 32 octets bruts. Mode strict, sans jamais citer la valeur en cas d'erreur.
     *
     * @throws CleInvalide
     */
    public static function decoder(#[\SensitiveParameter] string $valeur): string
    {
        if ($valeur === '') {
            throw CleInvalide::absente();
        }
        if (!str_starts_with($valeur, self::PREFIXE)) {
            throw CleInvalide::prefixe();
        }
        $base64 = substr($valeur, strlen(self::PREFIXE));
        // base64_decode(…, true) accepte encore les espaces et l'absence de « = » final : on exige un base64
        // canonique, comme la référence (b64decode(validate=True)). Une clé mal recopiée n'est jamais acceptée.
        $cle = preg_match(self::BASE64, $base64) === 1 ? base64_decode($base64, true) : false;
        if ($cle === false) {
            throw CleInvalide::base64();
        }
        self::verifierTaille($cle);

        return $cle;
    }

    /**
     * @throws CleInvalide
     */
    public static function verifierTaille(#[\SensitiveParameter] string $cle): void
    {
        if (strlen($cle) !== self::TAILLE) {
            throw CleInvalide::taille();
        }
    }

    /**
     * HMAC-SHA256(clé = K, message = étiquette), en octets bruts.
     */
    public static function deriver(#[\SensitiveParameter] string $cle, string $etiquette): string
    {
        return hash_hmac('sha256', $etiquette, $cle, true);
    }
}
