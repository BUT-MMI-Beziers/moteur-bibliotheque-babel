<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Cle;

use Babel\Moteur\Erreur\CleInvalide;
use Babel\Moteur\Moteur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function valeursRefusees(): iterable
    {
        $cle31 = base64_encode(str_repeat("\x5A", 31));
        $cle33 = base64_encode(str_repeat("\x5A", 33));
        yield 'vide' => ['', 'absente'];
        yield 'sans préfixe' => [base64_encode(str_repeat("\x5A", 32)), 'base64:'];
        yield 'préfixe en majuscules' => ['BASE64:' . base64_encode(str_repeat("\x5A", 32)), 'base64:'];
        yield 'préfixe seul' => ['base64:', '32 octets'];
        yield 'base64 invalide' => ['base64:WldaW!!!WldaWldaWldaWldaWldaWldaWldaWldaWlo=', 'base64 valide'];
        yield 'espace dans le base64' => ['base64:WlpaWlpa WlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlo=', 'base64 valide'];
        yield 'sans le = final' => ['base64:WlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlo', 'base64 valide'];
        yield 'saut de ligne final' => ["base64:WlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlo=\n", 'base64 valide'];
        yield 'base64 URL' => ['base64:WlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaWlpaW-_=', 'base64 valide'];
        yield '31 octets' => ['base64:' . $cle31, '32 octets'];
        yield '33 octets' => ['base64:' . $cle33, '32 octets'];
        yield 'clé hexadécimale' => ['base64:' . bin2hex(str_repeat("\x5A", 32)), '32 octets'];
    }

    #[DataProvider('valeursRefusees')]
    public function testDepuisConfigurationRefuse(string $valeur, string $indice): void
    {
        try {
            Moteur::depuisConfiguration($valeur);
            self::fail('clé acceptée');
        } catch (CleInvalide $e) {
            self::assertStringContainsString($indice, $e->getMessage());
            self::assertStringContainsString('php artisan babel:cle', $e->getMessage());
            if (strlen($valeur) > 7) {
                self::assertStringNotContainsString(substr($valeur, 7, 8), $e->getMessage());
            }
            self::assertStringNotContainsString('WlpaWlpa', $e->getTraceAsString());
        }
    }

    public function testConstructeurRefuseUneMauvaiseTaille(): void
    {
        foreach ([0, 16, 31, 33, 64] as $taille) {
            try {
                new Moteur(str_repeat('k', $taille));
                self::fail('clé acceptée');
            } catch (CleInvalide $e) {
                self::assertStringContainsString('32 octets', $e->getMessage());
            }
        }
    }

    public function testCleValide(): void
    {
        $cle = random_bytes(32);
        $moteur = Moteur::depuisConfiguration('base64:' . base64_encode($cle));
        self::assertSame((new Moteur($cle))->empreinte(), $moteur->empreinte());
        self::assertSame(hash_hmac('sha256', 'babel-moteur/v1/empreinte', $cle), $moteur->empreinte());
    }
}
