<?php

declare(strict_types=1);

namespace Babel\Moteur\Interne;

/**
 * Permutation à clé des nombres de 15 546 bits : réseau de Feistel à 8 tours sur HMAC-SHA256,
 * puis cycle-walking pour rester entre 0 et N − 1.
 *
 * La sous-clé k_perm n'est pas une propriété de l'objet : elle vit dans une WeakMap statique privée,
 * invisible pour var_dump, print_r, dump()/dd(), var_export, json_encode et le transtypage (array).
 *
 * @internal
 */
final class Permutation
{
    /** @var \WeakMap<self, string>|null */
    private static ?\WeakMap $cles = null;

    /**
     * @param string $cle k_perm, 32 octets
     */
    public function __construct(#[\SensitiveParameter] string $cle)
    {
        self::$cles ??= new \WeakMap();
        self::$cles[$this] = $cle;
    }

    /**
     * Adresse vers page : y = P(I) ; tant que y ≥ N : y = P(y).
     */
    public function versContenu(\GMP $numero): \GMP
    {
        $n = Constantes::get()->n;
        $y = $this->p($numero);
        for ($applications = 1; gmp_cmp($y, $n) >= 0; $applications++) {
            self::gardeFou($applications);
            $y = $this->p($y);
        }

        return $y;
    }

    /**
     * Page vers adresse : x = P_inverse(C) ; tant que x ≥ N : x = P_inverse(x).
     */
    public function versNumero(\GMP $contenu): \GMP
    {
        $n = Constantes::get()->n;
        $x = $this->pInverse($contenu);
        for ($applications = 1; gmp_cmp($x, $n) >= 0; $applications++) {
            self::gardeFou($applications);
            $x = $this->pInverse($x);
        }

        return $x;
    }

    /**
     * P(x), x entre 0 et 2^15546 − 1.
     */
    public function p(\GMP $x): \GMP
    {
        $c = Constantes::get();
        $cle = $this->cle();
        $g = gmp_div_q($x, $c->deuxPuissanceDemi);
        $d = gmp_and($x, $c->masque);
        for ($tour = 0; $tour < Constantes::TOURS; $tour++) {
            [$g, $d] = [$d, gmp_xor($g, self::tour($cle, $tour, $d))];
        }

        return gmp_or(gmp_mul($g, $c->deuxPuissanceDemi), $d);
    }

    /**
     * P_inverse(y), y entre 0 et 2^15546 − 1.
     */
    public function pInverse(\GMP $y): \GMP
    {
        $c = Constantes::get();
        $cle = $this->cle();
        $g = gmp_div_q($y, $c->deuxPuissanceDemi);
        $d = gmp_and($y, $c->masque);
        for ($tour = Constantes::TOURS - 1; $tour >= 0; $tour--) {
            [$g, $d] = [gmp_xor($d, self::tour($cle, $tour, $g)), $g];
        }

        return gmp_or(gmp_mul($g, $c->deuxPuissanceDemi), $d);
    }

    /**
     * Fonction de tour F(tour, x), x entre 0 et 2^7773 − 1.
     */
    public function f(int $tour, \GMP $x): \GMP
    {
        return self::tour($this->cle(), $tour, $x);
    }

    /**
     * message = octet(tour) ‖ be972(x) ; flux = HMAC(k_perm, be16(0) ‖ message) ‖ … ‖ HMAC(k_perm, be16(30) ‖ message) ;
     * renvoie (les 972 premiers octets du flux, poids fort en premier) ET MASQUE.
     */
    private static function tour(#[\SensitiveParameter] string $cle, int $tour, \GMP $x): \GMP
    {
        // gmp_export renvoie une chaîne vide pour 0 et ne complète jamais : on complète à 972 octets.
        $octets = gmp_export($x, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $message = chr($tour) . str_pad($octets, Constantes::OCTETS_DEMI, "\0", STR_PAD_LEFT);
        $flux = '';
        for ($bloc = 0; $bloc < Constantes::BLOCS; $bloc++) {
            $flux .= hash_hmac('sha256', pack('n', $bloc) . $message, $cle, true);
        }
        $valeur = gmp_import(substr($flux, 0, Constantes::OCTETS_DEMI), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);

        return gmp_and($valeur, Constantes::get()->masque);
    }

    private function cle(): string
    {
        return self::$cles[$this] ?? throw new \LogicException('Permutation sans clé : objet copié ou désérialisé.');
    }

    private static function gardeFou(int $applications): void
    {
        if ($applications >= Constantes::APPLICATIONS_MAX) {
            throw new \LogicException('Cycle-walking : plus de 1 000 applications de la permutation, bogue du moteur.');
        }
    }

    public function __debugInfo(): array
    {
        return [];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Une permutation du moteur ne se sérialise pas.');
    }

    /**
     * @param array<mixed> $donnees
     */
    public function __unserialize(array $donnees): void
    {
        throw new \LogicException('Une permutation du moteur ne se désérialise pas.');
    }

    private function __clone()
    {
    }
}
