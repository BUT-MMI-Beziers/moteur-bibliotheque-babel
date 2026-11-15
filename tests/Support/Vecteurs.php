<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Support;

/**
 * Accès typé à tests/donnees/vecteurs.json (copie exacte de reference/vecteurs.json, jamais modifiée à la main).
 *
 * @phpstan-type Lecture array{adresse: string, page: string, page_sha256: string, applications: int}
 * @phpstan-type Recherche array{texte: string, mode: string, adresse: string, adresse_sha256: string, page_sha256: string, applications: int, position?: int}
 * @phpstan-type Interne array{tour?: int, x_hex?: string, f_hex?: string, p_hex?: string, bruit_texte?: string, position?: int, remplissage_debut?: string, remplissage_sha256?: string}
 * @phpstan-type BlocCle array{nom: string, cle_hex: string, babel_key: string, empreinte: string, lire: list<Lecture>, chercher: list<Recherche>, interne: list<Interne>}
 * @phpstan-type ErreurAttendue array{code: string, champ: string}
 * @phpstan-type Donnees array{
 *     constantes: array{alphabet: string, longueur_page: int, bits: int, demi: int, octets_demi: int, tours: int, blocs_par_tour: int, pages_par_hexagone: int, nombre_de_pages_sha256: string, nombre_de_pages_chiffres: int, hexagone_max_longueur: int, hexagone_max_sha256: string, derniere_adresse_sha256: string},
 *     cles: list<BlocCle>,
 *     erreurs: array{lire: list<array{adresse: string, erreurs: list<ErreurAttendue>}>, chercher: list<array{texte: string, mode: string, erreurs: list<ErreurAttendue>}>}
 * }
 */
final class Vecteurs
{
    public const FICHIER = __DIR__ . '/../donnees/vecteurs.json';

    /** SHA-256 du fichier livré avec le cahier des charges : il ne doit jamais changer. */
    public const SHA256 = '86f012f695d509d3a8407abc51d2388f70afb01d800ae5991811c452df0204ae';

    /** @var Donnees|null */
    private static ?array $donnees = null;

    /**
     * @return Donnees
     */
    public static function donnees(): array
    {
        if (self::$donnees === null) {
            $contenu = file_get_contents(self::FICHIER);
            if ($contenu === false) {
                throw new \RuntimeException('vecteurs.json illisible');
            }
            /** @var Donnees $donnees */
            $donnees = json_decode($contenu, true, 512, JSON_THROW_ON_ERROR);
            self::$donnees = $donnees;
        }

        return self::$donnees;
    }

    /**
     * @return list<BlocCle>
     */
    public static function cles(): array
    {
        return self::donnees()['cles'];
    }

    public static function cleBrute(string $nom): string
    {
        foreach (self::cles() as $bloc) {
            if ($bloc['nom'] === $nom) {
                return (string) hex2bin($bloc['cle_hex']);
            }
        }
        throw new \InvalidArgumentException('clé de test inconnue');
    }
}
