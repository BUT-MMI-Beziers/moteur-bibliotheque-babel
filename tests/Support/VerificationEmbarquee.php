<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Support;

/**
 * Produit resources/verification.json à partir de tests/donnees/vecteurs.json : le sous-ensemble des vecteurs
 * que « php artisan babel:verifier » rejoue chez les étudiants (tests/ n'est pas distribué par Composer).
 */
final class VerificationEmbarquee
{
    public const FICHIER = __DIR__ . '/../../resources/verification.json';

    public static function generer(): string
    {
        $cles = [];
        foreach (Vecteurs::cles() as $bloc) {
            $cles[] = [
                'nom' => $bloc['nom'],
                'babel_key' => $bloc['babel_key'],
                'empreinte' => $bloc['empreinte'],
                'lire' => array_map(
                    static fn (array $cas): array => ['adresse' => $cas['adresse'], 'page_sha256' => $cas['page_sha256']],
                    $bloc['lire'],
                ),
                'chercher' => array_map(
                    static fn (array $cas): array => ['texte' => $cas['texte'], 'mode' => $cas['mode'], 'adresse_sha256' => $cas['adresse_sha256']],
                    $bloc['chercher'],
                ),
            ];
        }

        $donnees = [
            'source' => 'reference/vecteurs.json',
            'source_sha256' => Vecteurs::SHA256,
            'cles' => $cles,
        ];

        return json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
