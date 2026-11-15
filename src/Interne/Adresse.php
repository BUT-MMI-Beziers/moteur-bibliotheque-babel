<?php

declare(strict_types=1);

namespace Babel\Moteur\Interne;

use Babel\Moteur\Erreur\CodeErreur;
use Babel\Moteur\Erreur\Erreur;
use Babel\Moteur\Erreur\ErreurMoteur;

/**
 * Adresse canonique <hexagone>-w<mur>-s<étagère>-v<volume>:<page> <-> numéro de page entre 0 et N − 1.
 *
 * @internal
 */
final class Adresse
{
    private const DECOUPAGE = '/\A([^-]*)-w([^-]*)-s([^-]*)-v([^:]*)(?::(.*))?\z/s';

    /**
     * Contrôle une adresse de page (section 4 du cahier des charges) et renvoie son numéro.
     *
     * @throws ErreurMoteur toutes les erreurs de champ ensemble, dans l'ordre hexagone, mur, etagere, volume, page
     */
    public static function analyser(string $adresse): \GMP
    {
        // preg_match renvoie false si PCRE atteint une limite : c'est une adresse mal écrite, jamais une réussite.
        if (preg_match(self::DECOUPAGE, $adresse, $m, PREG_UNMATCHED_AS_NULL) !== 1) {
            throw ErreurMoteur::une(CodeErreur::AdresseMalEcrite, 'adresse');
        }

        $constantes = Constantes::get();
        $erreurs = [];
        $hexagone = null;

        $brut = (string) $m[1];
        if ($brut === '') {
            $erreurs[] = new Erreur(CodeErreur::AdresseMalEcrite, 'hexagone');
        } elseif (preg_match('/\A[0-9a-z]+\z/', $brut) !== 1) {
            $erreurs[] = new Erreur(CodeErreur::HexagoneInvalide, 'hexagone');
        } elseif (strlen($brut) > 1 && $brut[0] === '0') {
            $erreurs[] = new Erreur(CodeErreur::AdresseMalEcrite, 'hexagone');
        } elseif (strlen($brut) > Constantes::LONGUEUR_MAX_HEXAGONE) {
            // Avant toute conversion en grand nombre : une entrée énorme est refusée sans calcul.
            $erreurs[] = new Erreur(CodeErreur::HorsLimites, 'hexagone');
        } else {
            $hexagone = gmp_init($brut, 36);
            if (gmp_cmp($hexagone, $constantes->hexagoneMax) > 0) {
                $erreurs[] = new Erreur(CodeErreur::HorsLimites, 'hexagone');
            }
        }

        $mur = self::champ('mur', $m[2], Constantes::MURS, $erreurs);
        $etagere = self::champ('etagere', $m[3], Constantes::ETAGERES, $erreurs);
        $volume = self::champ('volume', $m[4], Constantes::VOLUMES, $erreurs);
        $page = self::champ('page', $m[5] ?? null, Constantes::PAGES, $erreurs);

        if ($erreurs !== []) {
            throw new ErreurMoteur($erreurs);
        }
        if ($hexagone === null || $mur === null || $etagere === null || $volume === null || $page === null) {
            throw new \LogicException('Adresse sans erreur mais incomplète : bogue du moteur.');
        }

        $numero = gmp_add(gmp_mul($hexagone, Constantes::MURS), $mur - 1);
        $numero = gmp_add(gmp_mul($numero, Constantes::ETAGERES), $etagere - 1);
        $numero = gmp_add(gmp_mul($numero, Constantes::VOLUMES), $volume - 1);
        $numero = gmp_add(gmp_mul($numero, Constantes::PAGES), $page - 1);

        if (gmp_cmp($numero, $constantes->n) >= 0) {
            throw ErreurMoteur::une(CodeErreur::HorsLimites, 'adresse');
        }

        return $numero;
    }

    /**
     * Contrôle un champ décimal (mur, étagère, volume, page). Au plus une erreur, la première qui s'applique.
     *
     * @param list<Erreur> $erreurs
     *
     * @param-out list<Erreur> $erreurs
     */
    private static function champ(string $nom, ?string $valeur, int $maximum, array &$erreurs): ?int
    {
        if ($valeur === null || preg_match('/\A(0|[1-9][0-9]*)\z/', $valeur) !== 1) {
            $erreurs[] = new Erreur(CodeErreur::AdresseMalEcrite, $nom);

            return null;
        }
        // Longueur contrôlée avant la conversion en entier : « 99999999999999999999 » ne déborde pas.
        if (strlen($valeur) > 3 || (int) $valeur < 1 || (int) $valeur > $maximum) {
            $erreurs[] = new Erreur(CodeErreur::HorsLimites, $nom);

            return null;
        }

        return (int) $valeur;
    }

    /**
     * Écriture canonique d'un numéro de page.
     *
     * @param \GMP $numero entre 0 et N − 1
     */
    public static function ecrire(\GMP $numero): string
    {
        [$reste, $page] = self::diviser($numero, Constantes::PAGES);
        [$reste, $volume] = self::diviser($reste, Constantes::VOLUMES);
        [$reste, $etagere] = self::diviser($reste, Constantes::ETAGERES);
        [$hexagone, $mur] = self::diviser($reste, Constantes::MURS);

        return sprintf('%s-w%d-s%d-v%d:%d', gmp_strval($hexagone, 36), $mur + 1, $etagere + 1, $volume + 1, $page + 1);
    }

    /**
     * @return array{\GMP, int} quotient et reste
     */
    private static function diviser(\GMP $nombre, int $diviseur): array
    {
        return [gmp_div_q($nombre, $diviseur), gmp_intval(gmp_div_r($nombre, $diviseur))];
    }
}
