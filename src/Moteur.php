<?php

declare(strict_types=1);

namespace Babel\Moteur;

use Babel\Moteur\Erreur\CleInvalide;
use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Interne\Cle;
use Babel\Moteur\Interne\Composition;
use Babel\Moteur\Interne\Constantes;
use Babel\Moteur\Interne\Page;
use Babel\Moteur\Interne\Permutation;

/**
 * Le moteur de la Bibliothèque de Babel.
 *
 * La bibliothèque contient toutes les pages possibles de 3 200 caractères. Rien n'est stocké :
 * une permutation à clé relie le numéro d'une adresse au numéro d'un texte, dans les deux sens.
 *
 * Un Moteur est immuable, sans état et réutilisable (files d'attente, Octane). Il ne se sérialise pas.
 */
final class Moteur implements \JsonSerializable
{
    /** Les 29 symboles, dans l'ordre : « a » vaut 0, « z » 25, l'espace 26, la virgule 27, le point 28. */
    public const ALPHABET = Constantes::ALPHABET;
    /** Longueur d'une page, en caractères. */
    public const LONGUEUR_PAGE = Constantes::LONGUEUR_PAGE;
    public const MURS = Constantes::MURS;
    public const ETAGERES = Constantes::ETAGERES;
    public const VOLUMES = Constantes::VOLUMES;
    public const PAGES = Constantes::PAGES;
    /** Longueur maximale de l'hexagone, en caractères base 36. */
    public const LONGUEUR_MAX_HEXAGONE = Constantes::LONGUEUR_MAX_HEXAGONE;
    /** Modes acceptés par chercher(). */
    public const MODES = Composition::MODES;
    /** Nom et version de l'algorithme : aucune version du paquet ne change une page. */
    public const ALGORITHME = 'babel-moteur/v1';

    private readonly Permutation $permutation;
    private readonly Composition $composition;
    private readonly string $empreinte;

    /**
     * @param string $cle exactement 32 octets bruts ; elle n'est pas conservée après la construction
     *
     * @throws CleInvalide
     */
    public function __construct(#[\SensitiveParameter] string $cle)
    {
        Cle::verifierTaille($cle);
        $this->permutation = new Permutation(Cle::deriver($cle, Cle::ETIQUETTE_PERMUTATION));
        $this->composition = new Composition(Cle::deriver($cle, Cle::ETIQUETTE_BRUIT));
        $this->empreinte = bin2hex(Cle::deriver($cle, Cle::ETIQUETTE_EMPREINTE));
    }

    /**
     * Construit le moteur à partir de la valeur de BABEL_KEY.
     *
     * @param string $valeur de la forme « base64:… » (44 caractères après le préfixe)
     *
     * @throws CleInvalide
     */
    public static function depuisConfiguration(#[\SensitiveParameter] string $valeur): self
    {
        return new self(Cle::decoder($valeur));
    }

    /**
     * F1 : le texte de la page qui se trouve à cette adresse.
     *
     * @param string $adresse forme canonique <hexagone>-w<mur>-s<étagère>-v<volume>:<page>
     *
     * @return string exactement 3 200 caractères de l'alphabet
     *
     * @throws ErreurMoteur toutes les erreurs de l'adresse ensemble
     */
    public function lire(string $adresse): string
    {
        return Page::versTexte($this->permutation->versContenu(Adresse::analyser($adresse)));
    }

    /**
     * F2 : l'adresse d'une page qui contient ce texte.
     *
     * @param string $mode « exact » (texte puis espaces), « bruit » (texte entouré de caractères) ou « page » (3 200 caractères)
     *
     * @return string adresse canonique
     *
     * @throws ErreurMoteur la première erreur rencontrée
     */
    public function chercher(string $texte, string $mode): string
    {
        $page = $this->composition->composer($texte, $mode);

        return Adresse::ecrire($this->permutation->versNumero(Page::versNombre($page)));
    }

    /**
     * F8 : empreinte de la clé, 64 caractères hexadécimaux minuscules. Elle ne permet pas de retrouver la clé.
     */
    public function empreinte(): string
    {
        return $this->empreinte;
    }

    /**
     * var_dump(), print_r() : seule l'empreinte apparaît.
     *
     * @return array{algorithme: string, empreinte: string}
     */
    public function __debugInfo(): array
    {
        return ['algorithme' => self::ALGORITHME, 'empreinte' => $this->empreinte];
    }

    /**
     * json_encode() : seule l'empreinte apparaît.
     *
     * @return array{algorithme: string, empreinte: string}
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Un moteur ne part pas dans la charge d'une tâche en file d'attente : injectez-le dans handle().
     */
    public function __serialize(): array
    {
        throw new \LogicException('Un Moteur ne se sérialise pas : injectez-le dans la méthode handle() de la tâche.');
    }

    /**
     * @param array<mixed> $donnees
     */
    public function __unserialize(array $donnees): void
    {
        throw new \LogicException('Un Moteur ne se désérialise pas.');
    }
}
