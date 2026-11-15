<?php

declare(strict_types=1);

// Régénère resources/verification.json à partir de tests/donnees/vecteurs.json (composer vecteurs:embarquer).
// Ne jamais modifier resources/verification.json à la main : un test vérifie qu'il correspond à ce script.

use Babel\Moteur\Tests\Support\VerificationEmbarquee;

require __DIR__ . '/../vendor/autoload.php';

file_put_contents(VerificationEmbarquee::FICHIER, VerificationEmbarquee::generer());
fwrite(STDOUT, 'resources/verification.json régénéré.' . PHP_EOL);
