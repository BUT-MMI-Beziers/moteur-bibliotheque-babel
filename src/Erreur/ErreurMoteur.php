<?php

declare(strict_types=1);

namespace Babel\Moteur\Erreur;

/**
 * Une entrée refusée. C'est un cas normal, que le serveur traduit en réponse HTTP.
 *
 * Le message liste les codes et les champs (« ERR_OUT_OF_BOUNDS (mur), ERR_INVALID_ADDRESS_SYNTAX (page) »)
 * et ne recopie jamais l'entrée reçue.
 */
final class ErreurMoteur extends \DomainException
{
    /** @var non-empty-list<Erreur> */
    private readonly array $erreurs;

    /**
     * @param non-empty-list<Erreur> $erreurs
     */
    public function __construct(array $erreurs)
    {
        $this->erreurs = $erreurs;
        parent::__construct(implode(', ', array_map(
            static fn (Erreur $e): string => sprintf('%s (%s)', $e->code->value, $e->champ),
            $erreurs,
        )));
    }

    /** @internal */
    public static function une(CodeErreur $code, string $champ): self
    {
        return new self([new Erreur($code, $champ)]);
    }

    /**
     * Les erreurs, dans l'ordre : hexagone, mur, etagere, volume, page pour une adresse ; une seule pour une recherche.
     *
     * @return non-empty-list<Erreur>
     */
    public function erreurs(): array
    {
        return $this->erreurs;
    }

    /**
     * Les erreurs sous forme de tableaux, prêtes pour une réponse JSON.
     *
     * @return non-empty-list<array{code: string, champ: string, message: string}>
     */
    public function versTableau(): array
    {
        return array_map(static fn (Erreur $e): array => $e->versTableau(), $this->erreurs);
    }
}
