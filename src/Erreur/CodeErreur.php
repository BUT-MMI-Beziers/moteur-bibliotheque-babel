<?php

declare(strict_types=1);

namespace Babel\Moteur\Erreur;

/**
 * Codes d'erreur renvoyés par le moteur. Les valeurs sont celles du contrat d'API.
 */
enum CodeErreur: string
{
    case AdresseMalEcrite = 'ERR_INVALID_ADDRESS_SYNTAX';
    case HexagoneInvalide = 'ERR_INVALID_HEX_CHARS';
    case HorsLimites = 'ERR_OUT_OF_BOUNDS';
    case RechercheVide = 'ERR_EMPTY_QUERY';
    case TexteTropLong = 'ERR_TEXT_TOO_LONG';
    case AlphabetInvalide = 'ERR_INVALID_ALPHABET';
    case ModeInconnu = 'ERR_UNKNOWN_MODE';
    case LongueurDePage = 'ERR_INVALID_PAGE_LENGTH';

    /**
     * Phrase lisible, en français, pour ce code et ce champ. Elle ne recopie jamais l'entrée reçue.
     */
    public function message(string $champ): string
    {
        return match ($this) {
            self::AdresseMalEcrite => match ($champ) {
                'adresse' => 'L\'adresse doit avoir la forme <hexagone>-w<mur>-s<étagère>-v<volume>:<page>.',
                'hexagone' => 'L\'hexagone doit être un nombre en base 36 non vide, sans zéro de tête.',
                'page' => 'La page est absente ou mal écrite : un nombre décimal sans zéro de tête ni signe.',
                default => self::libelle($champ) . ' doit être un nombre décimal sans zéro de tête ni signe.',
            },
            self::HexagoneInvalide => 'L\'hexagone ne peut contenir que les chiffres 0 à 9 et les lettres minuscules a à z.',
            self::HorsLimites => match ($champ) {
                'adresse' => 'Cette page n\'existe pas : le dernier hexagone ne contient que la page w1-s1-v1:1.',
                'hexagone' => 'L\'hexagone dépasse le dernier hexagone de la bibliothèque.',
                'mur' => 'Le mur doit être compris entre 1 et 4.',
                'etagere' => 'L\'étagère doit être comprise entre 1 et 5.',
                'volume' => 'Le volume doit être compris entre 1 et 32.',
                'page' => 'La page doit être comprise entre 1 et 410.',
                default => self::libelle($champ) . ' est hors limites.',
            },
            self::RechercheVide => 'Le texte recherché est vide.',
            self::TexteTropLong => 'Le texte recherché dépasse 3 200 caractères.',
            self::AlphabetInvalide => 'Le texte contient un caractère hors de l\'alphabet : lettres minuscules a à z sans accent, espace, virgule et point.',
            self::ModeInconnu => 'Le mode doit être exact, bruit ou page.',
            self::LongueurDePage => 'En mode page, le texte doit faire exactement 3 200 caractères.',
        };
    }

    private static function libelle(string $champ): string
    {
        return match ($champ) {
            'mur' => 'Le mur',
            'etagere' => 'L\'étagère',
            'volume' => 'Le volume',
            'page' => 'La page',
            'texte' => 'Le texte',
            'mode' => 'Le mode',
            'hexagone' => 'L\'hexagone',
            default => 'L\'adresse',
        };
    }
}
