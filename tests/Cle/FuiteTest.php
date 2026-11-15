<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Cle;

use Babel\Moteur\Erreur\CleInvalide;
use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Moteur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * Ni la clé (brute, hexadécimale, base64) ni les sous-clés n'apparaissent dans les sorties de débogage,
 * les sérialisations, les messages et les traces d'exception.
 */
final class FuiteTest extends TestCase
{
    private string $cle;
    private Moteur $moteur;

    protected function setUp(): void
    {
        $this->cle = random_bytes(32);
        $this->moteur = new Moteur($this->cle);
    }

    public function testVarDumpPrintRVarExportEtTranstypage(): void
    {
        ob_start();
        var_dump($this->moteur);
        $sortie = (string) ob_get_clean();
        $sortie .= print_r($this->moteur, true);
        $sortie .= var_export($this->moteur, true);
        $sortie .= print_r((array) $this->moteur, true);
        ob_start();
        var_dump((array) $this->moteur);
        $sortie .= (string) ob_get_clean();

        self::assertStringContainsString($this->moteur->empreinte(), $sortie);
        $this->assertAucunSecret($sortie);
    }

    public function testDumpEtDdDeLaravel(): void
    {
        $dumper = new CliDumper();
        $sortie = (string) $dumper->dump((new VarCloner())->cloneVar($this->moteur), true);

        self::assertStringContainsString($this->moteur->empreinte(), $sortie);
        $this->assertAucunSecret($sortie);
    }

    public function testJsonEncode(): void
    {
        $json = json_encode($this->moteur, JSON_THROW_ON_ERROR);
        self::assertSame(json_encode(['algorithme' => 'babel-moteur/v1', 'empreinte' => $this->moteur->empreinte()]), $json);
        $this->assertAucunSecret($json);
    }

    public function testSerializeLeveUneException(): void
    {
        $this->expectException(\LogicException::class);
        serialize($this->moteur);
    }

    public function testSerializeDesObjetsInternesLeveUneException(): void
    {
        foreach ((array) $this->moteur as $valeur) {
            if (is_object($valeur)) {
                try {
                    serialize($valeur);
                    self::fail('objet interne sérialisé');
                } catch (\LogicException $e) {
                    $this->assertAucunSecret($e->getMessage() . $e->getTraceAsString());
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    public function testTracesDesExceptions(): void
    {
        $traces = '';
        try {
            new Moteur(substr($this->cle, 0, 31));
        } catch (CleInvalide $e) {
            $traces .= self::trace($e);
        }
        try {
            Moteur::depuisConfiguration(base64_encode($this->cle));
        } catch (CleInvalide $e) {
            $traces .= self::trace($e);
        }
        try {
            Moteur::depuisConfiguration('base64:' . base64_encode($this->cle) . 'AA==');
        } catch (CleInvalide $e) {
            $traces .= self::trace($e);
        }
        try {
            $this->moteur->lire('0-w9-s1-v1:1');
        } catch (ErreurMoteur $e) {
            $traces .= self::trace($e);
        }
        try {
            $this->moteur->chercher('A', 'exact');
        } catch (ErreurMoteur $e) {
            $traces .= self::trace($e);
        }

        self::assertStringContainsString('SensitiveParameterValue', $traces);
        $this->assertAucunSecret($traces);
    }

    /**
     * Message, trace textuelle et arguments des appels du moteur (les cadres de PHPUnit, qui portent l'objet
     * de test et donc la clé de test, ne concernent pas le moteur).
     */
    private static function trace(\Throwable $e): string
    {
        $sortie = $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        foreach ($e->getTrace() as $cadre) {
            $classe = $cadre['class'] ?? '';
            if (str_starts_with($classe, 'Babel\\Moteur\\') && !str_starts_with($classe, 'Babel\\Moteur\\Tests\\')) {
                $sortie .= print_r($cadre['args'] ?? [], true);
            }
        }

        return $sortie;
    }

    private function assertAucunSecret(string $sortie): void
    {
        $sousCles = [
            hash_hmac('sha256', 'babel-moteur/v1/permutation', $this->cle, true),
            hash_hmac('sha256', 'babel-moteur/v1/bruit', $this->cle, true),
        ];
        foreach ([$this->cle, ...$sousCles] as $secret) {
            foreach ([$secret, bin2hex($secret), base64_encode($secret), rtrim(base64_encode($secret), '=')] as $forme) {
                self::assertStringNotContainsString($forme, $sortie);
            }
        }
    }
}
