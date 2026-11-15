<?php

declare(strict_types=1);

namespace Babel\Moteur\Interne;

use Babel\Moteur\Erreur\CodeErreur;
use Babel\Moteur\Erreur\ErreurMoteur;

/**
 * Composition de la page cherchée : contrôles de la recherche puis modes exact, bruit et page.
 *
 * La sous-clé k_bruit vit dans une WeakMap statique privée, comme k_perm (voir Permutation).
 *
 * @internal
 */
final class Composition
{
    public const MODES = ['exact', 'bruit', 'page'];

    /** 232 = 8 × 29 : un octet b < 232 donne ALPHABET[b mod 29] sans biais. */
    private const OCTET_MAX = 232;

    /** @var \WeakMap<self, string>|null */
    private static ?\WeakMap $cles = null;

    /**
     * @param string $cle k_bruit, 32 octets
     */
    public function __construct(#[\SensitiveParameter] string $cle)
    {
        self::$cles ??= new \WeakMap();
        self::$cles[$this] = $cle;
    }

    /**
     * Contrôle la recherche (on s'arrête à la première erreur) et compose la page de 3 200 caractères.
     *
     * @throws ErreurMoteur une seule erreur
     */
    public function composer(string $texte, string $mode): string
    {
        if (!in_array($mode, self::MODES, true)) {
            throw ErreurMoteur::une(CodeErreur::ModeInconnu, 'mode');
        }
        if ($texte === '') {
            throw ErreurMoteur::une(CodeErreur::RechercheVide, 'texte');
        }
        $longueur = strlen($texte);
        if (!self::dansLAlphabet($texte)) {
            throw ErreurMoteur::une(CodeErreur::AlphabetInvalide, 'texte');
        }
        if ($mode === 'page') {
            if ($longueur !== Constantes::LONGUEUR_PAGE) {
                throw ErreurMoteur::une(CodeErreur::LongueurDePage, 'texte');
            }

            return $texte;
        }
        if ($longueur > Constantes::LONGUEUR_PAGE) {
            throw ErreurMoteur::une(CodeErreur::TexteTropLong, 'texte');
        }
        if ($mode === 'exact') {
            return str_pad($texte, Constantes::LONGUEUR_PAGE, ' ');
        }

        [$position, $remplissage] = $this->bruit($texte);

        return substr($remplissage, 0, $position) . $texte . substr($remplissage, $position);
    }

    /**
     * Position du texte et remplissage (3 200 − L caractères), dérivés de k_bruit et du texte, jamais du hasard.
     *
     * flux = HMAC(k_bruit, be32(0) ‖ texte) ‖ HMAC(k_bruit, be32(1) ‖ texte) ‖ …, consommé dans l'ordre.
     *
     * @param string $texte entre 1 et 3 200 caractères de l'alphabet (déjà contrôlés)
     *
     * @return array{int, string} position (entre 0 et 3 200 − L) et remplissage
     */
    public function bruit(string $texte): array
    {
        $cle = $this->cle();
        $flux = '';
        $lu = 0;
        $bloc = 0;
        $besoin = Constantes::LONGUEUR_PAGE - strlen($texte);

        $places = $besoin + 1;
        $limite = 65536 - 65536 % $places;
        do {
            while (strlen($flux) - $lu < 2) {
                $flux .= hash_hmac('sha256', pack('N', $bloc++) . $texte, $cle, true);
            }
            $u = (ord($flux[$lu]) << 8) | ord($flux[$lu + 1]);
            $lu += 2;
        } while ($u >= $limite);
        $position = $u % $places;

        $remplissage = '';
        while (strlen($remplissage) < $besoin) {
            if ($lu >= strlen($flux)) {
                $flux .= hash_hmac('sha256', pack('N', $bloc++) . $texte, $cle, true);
            }
            $octet = ord($flux[$lu++]);
            if ($octet < self::OCTET_MAX) {
                $remplissage .= Constantes::ALPHABET[$octet % 29];
            }
        }

        return [$position, $remplissage];
    }

    /**
     * Chaque octet est-il un des 29 symboles ? Même résultat que strspn($texte, ALPHABET) === strlen($texte),
     * mais en temps linéaire : strspn compare chaque octet aux 29 symboles (145 ms sur 8 Mo, contre 8 ms ici).
     */
    private static function dansLAlphabet(string $texte): bool
    {
        $intrus = preg_match('/[^a-z ,.]/', $texte);
        if ($intrus === false) {
            return strspn($texte, Constantes::ALPHABET) === strlen($texte);
        }

        return $intrus === 0;
    }

    private function cle(): string
    {
        return self::$cles[$this] ?? throw new \LogicException('Composition sans clé : objet copié ou désérialisé.');
    }

    public function __debugInfo(): array
    {
        return [];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Une composition du moteur ne se sérialise pas.');
    }

    /**
     * @param array<mixed> $donnees
     */
    public function __unserialize(array $donnees): void
    {
        throw new \LogicException('Une composition du moteur ne se désérialise pas.');
    }

    private function __clone()
    {
    }
}
