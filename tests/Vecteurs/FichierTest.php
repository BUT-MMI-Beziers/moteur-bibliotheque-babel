<?php

declare(strict_types=1);

namespace Babel\Moteur\Tests\Vecteurs;

use Babel\Moteur\Tests\Support\Vecteurs;
use PHPUnit\Framework\TestCase;

final class FichierTest extends TestCase
{
    public function testLeFichierDeVecteursEstIntact(): void
    {
        self::assertSame(Vecteurs::SHA256, hash_file('sha256', Vecteurs::FICHIER));
    }

    public function testLaCopieEstIdentiqueALaReference(): void
    {
        self::assertFileEquals(__DIR__ . '/../../reference/vecteurs.json', Vecteurs::FICHIER);
    }
}
