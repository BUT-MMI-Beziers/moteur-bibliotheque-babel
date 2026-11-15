<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Laravel;

use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\VerificationEmbarquee;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\BufferedOutput;

final class CommandeVerifierTest extends CasLaravel
{
    public function testToutVaBien(): void
    {
        $cle = random_bytes(32);
        $valeur = 'base64:' . base64_encode($cle);
        $this->configurer('babel.cle', $valeur);

        [$code, $sortie] = $this->lancer();

        self::assertSame(0, $code, $sortie);
        self::assertStringContainsString((new Moteur($cle))->empreinte(), $sortie);
        self::assertStringContainsString('48/48', $sortie);
        self::assertStringContainsString('Lecture moyenne', $sortie);
        foreach ([$valeur, base64_encode($cle), bin2hex($cle), $cle] as $forme) {
            self::assertStringNotContainsString($forme, $sortie);
        }
        foreach (['babel-moteur/v1/permutation', 'babel-moteur/v1/bruit'] as $etiquette) {
            $sousCle = hash_hmac('sha256', $etiquette, $cle, true);
            self::assertStringNotContainsString(bin2hex($sousCle), $sortie);
            self::assertStringNotContainsString(base64_encode($sousCle), $sortie);
        }
    }

    public function testSansCle(): void
    {
        $this->configurer('babel.cle', null);

        [$code, $sortie] = $this->lancer();

        self::assertSame(1, $code);
        self::assertStringContainsString('absente', $sortie);
        self::assertStringContainsString('php artisan babel:cle', $sortie);
    }

    public function testCleMalFormee(): void
    {
        $this->configurer('babel.cle', 'base64:ZZZZ-secret-mal-recopie');

        [$code, $sortie] = $this->lancer();

        self::assertSame(1, $code);
        self::assertStringNotContainsString('secret-mal-recopie', $sortie);
    }

    public function testLesVecteursEmbarquesCorrespondentALaReference(): void
    {
        self::assertSame(VerificationEmbarquee::generer(), file_get_contents(VerificationEmbarquee::FICHIER));
    }

    /**
     * @return array{int, string}
     */
    private function lancer(): array
    {
        $sortie = new BufferedOutput();
        $code = $this->application()->make(Kernel::class)->call('babel:verifier', [], $sortie);

        return [$code, $sortie->fetch()];
    }
}
