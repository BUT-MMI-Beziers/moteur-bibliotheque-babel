<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Vecteurs;

use Babel\Moteur\Erreur\Erreur;
use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rubriques erreurs.lire et erreurs.chercher : mêmes codes, mêmes champs, même ordre.
 */
final class ErreursTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{code: string, champ: string}>}>
     */
    public static function adressesRefusees(): iterable
    {
        foreach (Vecteurs::donnees()['erreurs']['lire'] as $i => $cas) {
            yield sprintf('#%02d %s', $i, json_encode(substr($cas['adresse'], 0, 30))) => [$cas['adresse'], $cas['erreurs']];
        }
    }

    /**
     * @return iterable<string, array{string, string, list<array{code: string, champ: string}>}>
     */
    public static function recherchesRefusees(): iterable
    {
        foreach (Vecteurs::donnees()['erreurs']['chercher'] as $i => $cas) {
            yield sprintf('#%02d %s %s', $i, json_encode($cas['mode']), json_encode(substr($cas['texte'], 0, 12))) => [$cas['texte'], $cas['mode'], $cas['erreurs']];
        }
    }

    /**
     * @param list<array{code: string, champ: string}> $attendues
     */
    #[DataProvider('adressesRefusees')]
    public function testLireRefuseLAdresse(string $adresse, array $attendues): void
    {
        foreach (['A', 'B'] as $nom) {
            $erreur = $this->capturer(static fn () => (new Moteur(Vecteurs::cleBrute($nom)))->lire($adresse));
            self::assertSame($attendues, self::simplifier($erreur));
            self::assertSame(self::message($attendues), $erreur->getMessage());
        }
    }

    /**
     * @param list<array{code: string, champ: string}> $attendues
     */
    #[DataProvider('recherchesRefusees')]
    public function testChercherRefuseLaRecherche(string $texte, string $mode, array $attendues): void
    {
        $erreur = $this->capturer(static fn () => (new Moteur(Vecteurs::cleBrute('A')))->chercher($texte, $mode));
        self::assertSame($attendues, self::simplifier($erreur));
        self::assertCount(1, $erreur->erreurs());
    }

    public function testLeMessageNeRecopiePasLEntree(): void
    {
        $adresse = str_repeat('Z', 2000) . '-w9-s1-v1:1';
        $erreur = $this->capturer(static fn () => (new Moteur(Vecteurs::cleBrute('A')))->lire($adresse));
        self::assertSame('ERR_INVALID_HEX_CHARS (hexagone), ERR_OUT_OF_BOUNDS (mur)', $erreur->getMessage());
        foreach ($erreur->erreurs() as $e) {
            self::assertStringNotContainsString('ZZZ', $e->message);
            self::assertNotSame('', $e->message);
        }
    }

    private function capturer(\Closure $appel): ErreurMoteur
    {
        try {
            $appel();
        } catch (ErreurMoteur $e) {
            return $e;
        }
        self::fail('Une ErreurMoteur était attendue.');
    }

    /**
     * @return list<array{code: string, champ: string}>
     */
    private static function simplifier(ErreurMoteur $e): array
    {
        return array_map(static fn (Erreur $x): array => ['code' => $x->code->value, 'champ' => $x->champ], $e->erreurs());
    }

    /**
     * @param list<array{code: string, champ: string}> $erreurs
     */
    private static function message(array $erreurs): string
    {
        return implode(', ', array_map(static fn (array $e): string => $e['code'] . ' (' . $e['champ'] . ')', $erreurs));
    }
}
