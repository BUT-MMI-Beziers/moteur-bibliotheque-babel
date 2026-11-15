<?php

declare(strict_types=1);

namespace Babel\Moteur\Erreur;

/**
 * Une erreur sur une entrée : un code, le champ concerné et une phrase lisible.
 */
final readonly class Erreur
{
    /** Phrase lisible, en français. */
    public string $message;

    /**
     * @param string $champ adresse, hexagone, mur, etagere, volume, page, texte ou mode
     */
    public function __construct(
        public CodeErreur $code,
        public string $champ,
    ) {
        $this->message = $code->message($champ);
    }

    /**
     * @return array{code: string, champ: string, message: string}
     */
    public function versTableau(): array
    {
        return ['code' => $this->code->value, 'champ' => $this->champ, 'message' => $this->message];
    }
}
