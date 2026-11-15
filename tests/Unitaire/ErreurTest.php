<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Unitaire;

use Babel\Moteur\Erreur\CodeErreur;
use Babel\Moteur\Erreur\Erreur;
use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\TestCase;

final class ErreurTest extends TestCase
{
    public function testLesCodesSontExactementCeuxDesVecteurs(): void
    {
        $vecteurs = [];
        foreach ([...Vecteurs::donnees()['erreurs']['lire'], ...Vecteurs::donnees()['erreurs']['chercher']] as $cas) {
            foreach ($cas['erreurs'] as $erreur) {
                $vecteurs[$erreur['code']] = true;
            }
        }
        $codes = array_map(static fn (CodeErreur $c): string => $c->value, CodeErreur::cases());
        $attendus = array_keys($vecteurs);
        sort($codes);
        sort($attendus);
        self::assertSame($attendus, $codes);
    }

    public function testChaqueCodeAUnMessagePourChaqueChamp(): void
    {
        foreach (CodeErreur::cases() as $code) {
            foreach (['adresse', 'hexagone', 'mur', 'etagere', 'volume', 'page', 'texte', 'mode'] as $champ) {
                $message = $code->message($champ);
                self::assertNotSame('', $message);
                self::assertStringEndsWith('.', $message);
            }
        }
    }

    public function testErreurMoteurEtVersTableau(): void
    {
        $e = new ErreurMoteur([
            new Erreur(CodeErreur::HorsLimites, 'mur'),
            new Erreur(CodeErreur::AdresseMalEcrite, 'page'),
        ]);

        self::assertSame('ERR_OUT_OF_BOUNDS (mur), ERR_INVALID_ADDRESS_SYNTAX (page)', $e->getMessage());
        self::assertSame([
            ['code' => 'ERR_OUT_OF_BOUNDS', 'champ' => 'mur', 'message' => 'Le mur doit être compris entre 1 et 4.'],
            ['code' => 'ERR_INVALID_ADDRESS_SYNTAX', 'champ' => 'page', 'message' => 'La page est absente ou mal écrite : un nombre décimal sans zéro de tête ni signe.'],
        ], $e->versTableau());
        self::assertSame(CodeErreur::HorsLimites, $e->erreurs()[0]->code);
    }
}
