<?php

declare(strict_types=1);

// Banc d'essai (composer bench) : 300 allers-retours lire/chercher avec une clé tirée au sort.
// Affiche médiane, 95e centile et maximum. Échoue seulement sur une régression grossière (médiane > 50 ms).

use Babel\Moteur\Interne\Adresse;
use Babel\Moteur\Moteur;
use Babel\Moteur\Tests\Support\Hasard;

require __DIR__ . '/../vendor/autoload.php';

const ALLERS_RETOURS = 300;
const SEUIL_MEDIANE_MS = 50.0;

$debut = hrtime(true);
$moteur = new Moteur(random_bytes(32));
$construction = (hrtime(true) - $debut) / 1e6;

$durees = ['lire' => [], 'chercher' => [], 'chercher (bruit)' => []];
for ($i = 0; $i < ALLERS_RETOURS; $i++) {
    $adresse = Adresse::ecrire(Hasard::numero());

    $debut = hrtime(true);
    $page = $moteur->lire($adresse);
    $milieu = hrtime(true);
    $retour = $moteur->chercher($page, 'page');
    $fin = hrtime(true);
    $moteur->chercher(substr($page, 0, 40), 'bruit');
    $bruit = hrtime(true);

    if ($retour !== $adresse) {
        fwrite(STDERR, "Aller-retour faux : le moteur est cassé.\n");
        exit(1);
    }
    $durees['lire'][] = ($milieu - $debut) / 1e6;
    $durees['chercher'][] = ($fin - $milieu) / 1e6;
    $durees['chercher (bruit)'][] = ($bruit - $fin) / 1e6;
}

printf("PHP %s, GMP %s, %d allers-retours\n\n", PHP_VERSION, GMP_VERSION, ALLERS_RETOURS);
printf("%-18s %10s %10s %10s\n", '', 'médiane', 'p95', 'max');
$echec = false;
foreach ($durees as $nom => $liste) {
    sort($liste);
    $mediane = $liste[intdiv(count($liste), 2)];
    printf("%-18s %7.2f ms %7.2f ms %7.2f ms\n", $nom, $mediane, $liste[(int) floor(0.95 * (count($liste) - 1))], $liste[count($liste) - 1]);
    $echec = $echec || $mediane > SEUIL_MEDIANE_MS;
}
printf("%-18s %7.3f ms\n", 'construction', $construction);

if ($echec) {
    fwrite(STDERR, sprintf("\nRégression : médiane au-dessus de %.0f ms.\n", SEUIL_MEDIANE_MS));
    exit(1);
}
