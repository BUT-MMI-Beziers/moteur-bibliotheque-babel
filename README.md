# Moteur de la Bibliothèque de Babel

[![Tests](https://github.com/BUT-MMI-Beziers/moteur-bibliotheque-babel/actions/workflows/tests.yml/badge.svg)](https://github.com/BUT-MMI-Beziers/moteur-bibliotheque-babel/actions/workflows/tests.yml)
[![Version](https://img.shields.io/packagist/v/but-mmi-beziers/babel-moteur?label=version)](https://packagist.org/packages/but-mmi-beziers/babel-moteur)
[![PHP](https://img.shields.io/packagist/dependency-v/but-mmi-beziers/babel-moteur/php)](https://packagist.org/packages/but-mmi-beziers/babel-moteur)

La bibliothèque imaginée par Borges contient **toutes les pages possibles de 3 200 caractères**, soit 29^3200 pages. Ce paquet est le moteur qui la fait exister sans rien stocker : vous lui donnez une adresse, il vous rend la page ; vous lui donnez un texte, il vous rend l'adresse d'une page qui le contient.

Vous l'installez dans votre projet Laravel et vous vous en servez comme d'une **boîte noire** : ce document suffit, inutile de lire le code.

---

## Sommaire

1. [Ce que fait le moteur](#1-ce-que-fait-le-moteur)
2. [Prérequis : PHP et l'extension gmp](#2-prérequis--php-et-lextension-gmp)
3. [Installation en 3 commandes](#3-installation-en-3-commandes)
4. [Utilisation dans Laravel](#4-utilisation-dans-laravel)
5. [Les adresses](#5-les-adresses)
6. [Les modes de recherche](#6-les-modes-de-recherche)
7. [Les erreurs](#7-les-erreurs)
8. [La clé : règles d'or](#8-la-clé--règles-dor)
9. [Ce que le moteur ne fait pas (c'est votre travail)](#9-ce-que-le-moteur-ne-fait-pas-cest-votre-travail)
10. [Dépannage](#10-dépannage)

---

## 1. Ce que fait le moteur

Trois méthodes, sur un objet `Babel\Moteur\Moteur` :

| Méthode | Fonction du cahier des charges | Renvoie |
| --- | --- | --- |
| `lire(string $adresse): string` | **F1** | le texte de la page à cette adresse (3 200 caractères) |
| `chercher(string $texte, string $mode): string` | **F2** | l'adresse d'une page qui contient ce texte |
| `empreinte(): string` | **F8** | l'empreinte de la clé (64 caractères hexadécimaux), à publier avec l'identité de votre bibliothèque |

Ce que le moteur vous **garantit** :

- **Bijection exacte** : deux adresses différentes donnent deux pages différentes, et toute page de 3 200 caractères a exactement une adresse. `chercher(lire($a), 'page') === $a` pour toute adresse valide.
- **Déterminisme** : même clé, mêmes entrées → même résultat, aujourd'hui, dans un an, sur toutes les machines (y compris en mode `bruit`).
- **Aucun état** : pas de base de données, pas de fichier, pas de cache, pas de réseau. Le moteur ne fait que calculer (environ 1 à 2 ms par appel).
- **La clé ne sort jamais** : elle n'apparaît ni dans les résultats, ni dans les messages d'erreur, ni dans `dd()`.

Chaque bibliothèque (donc chaque étudiant) a **sa propre clé**, donc son propre rangement : la même adresse ne donne pas la même page chez vous et chez votre voisin.

## 2. Prérequis : PHP et l'extension gmp

| | Version |
| --- | --- |
| PHP | 8.2 ou plus (8.3 ou plus pour Laravel 13) |
| Laravel | 12 ou 13 |
| Extension PHP | **`gmp`** (calcul sur de très grands nombres) |

Vérifiez que `gmp` est active :

```bash
php -m | grep -i gmp
```

Si rien ne s'affiche, activez-la :

| Système | Comment faire |
| --- | --- |
| Ubuntu, Debian, WSL | `sudo apt install php8.3-gmp` (remplacez `8.3` par votre version de PHP, voir `php -v`) |
| macOS (Homebrew, Laravel Herd) | normalement déjà incluse ; sinon `brew reinstall php` |
| Windows (PHP seul, Laragon, XAMPP) | dans `php.ini`, retirez le `;` devant `extension=gmp`, puis redémarrez le serveur |
| Docker (image `php` officielle) | `apt-get install -y libgmp-dev && docker-php-ext-install gmp` dans le `Dockerfile` |

> ⚠️ Le PHP de la ligne de commande et celui du serveur web peuvent être différents (`php-fpm`, Apache). L'extension doit être active **dans les deux**. Sans `gmp`, Composer refuse d'installer le paquet et le dit : `requires PHP extension ext-gmp`.

## 3. Installation en 3 commandes

Dans le dossier de votre projet Laravel :

```bash
composer require but-mmi-beziers/babel-moteur
```

```bash
php artisan babel:cle
```

```bash
php artisan babel:verifier
```

1. **`composer require`** installe le moteur. Laravel le découvre tout seul : rien à déclarer.
2. **`php artisan babel:cle`** tire une clé au hasard et ajoute une ligne `BABEL_KEY=base64:…` dans votre `.env`. Si une clé existe déjà, la commande refuse de l'écraser (voir [§ 8](#8-la-clé--règles-dor)).
3. **`php artisan babel:verifier`** contrôle l'installation : extension `gmp`, clé, résultats de référence, aller-retour avec votre clé. Elle affiche l'empreinte de votre clé et le temps moyen d'une lecture, et doit finir par **« Tout est prêt »**.

```text
  Moteur de la Bibliothèque de Babel (babel-moteur/v1)

  Extension gmp ..................................... GMP 6.3.0 OK
  Clé BABEL_KEY ......................... présente et bien formée OK
  Vecteurs de référence (clés de test A et B) ............. 48/48 OK
  Aller-retour avec la clé de la bibliothèque ..... lire ⇄ chercher OK

  Empreinte de la clé ......... 17733b48109ec09205a57b1db673c70c64bba4c4641097caf62b697a39149473
  Lecture moyenne .................................. 1.76 ms (sur 50 lectures)

   INFO  Tout est prêt : le moteur est utilisable.
```

Options de `babel:cle` :

| Commande | Effet |
| --- | --- |
| `php artisan babel:cle --afficher` | affiche la ligne `BABEL_KEY=…` sans toucher au `.env` (pour la copier ailleurs, par exemple sur le serveur de production) |
| `php artisan babel:cle --force` | **remplace** une clé existante. Toutes les adresses déjà partagées changeront de texte : à ne faire qu'avant l'ouverture de votre bibliothèque |

Facultatif : `php artisan vendor:publish --tag=babel-config` copie le fichier de configuration `config/babel.php` dans votre projet. Vous n'en avez normalement pas besoin.

## 4. Utilisation dans Laravel

Le moteur est enregistré dans le conteneur de Laravel : **demandez-le en paramètre** (injection de dépendances), ne faites jamais `new Moteur(...)` vous-même. Il n'y a pas de façade.

### Dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use Babel\Moteur\Erreur\ErreurMoteur;
use Babel\Moteur\Moteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(private readonly Moteur $moteur)
    {
    }

    // GET /api/pages/{adresse}
    public function show(string $adresse): JsonResponse
    {
        try {
            return response()->json([
                'adresse' => $adresse,
                'texte' => $this->moteur->lire($adresse),
            ]);
        } catch (ErreurMoteur $e) {
            // $e->versTableau() : [['code' => 'ERR_…', 'champ' => '…', 'message' => '…'], …]
            return response()->json(['erreurs' => $e->versTableau()], 422);
        }
    }

    // POST /api/recherches  { "texte": "...", "mode": "exact" }
    public function search(Request $request): JsonResponse
    {
        try {
            $adresse = $this->moteur->chercher(
                (string) $request->input('texte', ''),
                (string) $request->input('mode', 'exact'),
            );

            return response()->json(['adresse' => $adresse]);
        } catch (ErreurMoteur $e) {
            return response()->json(['erreurs' => $e->versTableau()], 422);
        }
    }
}
```

Le statut HTTP (`422`, `400`…) et la forme exacte du JSON sont ceux de **votre contrat d'API** : adaptez l'exemple.
Le livrable 2 propose `404` lorsque la liste ne contient que des erreurs `ERR_OUT_OF_BOUNDS`, et `422` dès qu'une erreur de forme y figure ; l'exemple simplifié renvoie toujours `422`.

### Traiter toutes les erreurs du moteur au même endroit (facultatif)

Plutôt que des `try/catch` partout, vous pouvez traduire `ErreurMoteur` une fois pour toutes dans `bootstrap/app.php` :

```php
use Babel\Moteur\Erreur\ErreurMoteur;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(fn (ErreurMoteur $e) => response()->json(['erreurs' => $e->versTableau()], 422));
})
```

### Ailleurs : services, commandes, tâches de fond

```php
// Dans une méthode, n'importe où :
$texte = app(\Babel\Moteur\Moteur::class)->lire('0-w1-s1-v1:1');

// Dans une tâche en file d'attente : injectez le moteur dans handle(), jamais dans le constructeur.
public function handle(\Babel\Moteur\Moteur $moteur): void
{
    $texte = $moteur->lire($this->adresse);
}
```

Un `Moteur` refuse d'être sérialisé (`serialize()` lève une exception) : il ne doit pas voyager dans la charge d'une tâche. Il est immuable et réutilisable : il fonctionne tel quel sous Octane et dans les workers de file d'attente.

### L'empreinte

```php
$moteur->empreinte(); // "f85a8e8ae6a8a1ce…" : 64 caractères hexadécimaux minuscules
```

Elle identifie votre clé **sans la révéler**. Elle ne change jamais tant que la clé ne change pas. Publiez-la avec l'identité de votre bibliothèque.

## 5. Les adresses

Une adresse de page s'écrit :

```text
<hexagone>-w<mur>-s<étagère>-v<volume>:<page>
```

| Partie | Valeurs | Écriture |
| --- | --- | --- |
| hexagone | de `0` au dernier hexagone (un nombre de 3 004 chiffres) | base 36 : chiffres `0-9` puis lettres **minuscules** `a-z`, sans zéro de tête |
| mur | 1 à 4 | décimal, sans zéro de tête ni signe |
| étagère | 1 à 5 | idem |
| volume | 1 à 32 | idem |
| page | 1 à 410 | idem |

Exemples valides : `0-w1-s1-v1:1` (la toute première page), `babel-w2-s3-v17:204`, `1x-w4-s5-v32:410`.

- Une page n'a **qu'une seule écriture valide** : le moteur **refuse** `0-w04-s1-v1:1`, `ABC-w1-s1-v1:1` ou `0-w1-s1-v1:1 ` (espace final). Il ne corrige rien, il ne fait pas de `trim()`.
- Une adresse renvoyée par `chercher()` peut faire jusqu'à **3 018 caractères**.
- Le dernier hexagone ne contient qu'une seule page : `<dernier hexagone>-w1-s1-v1:1`.

Pour écrire vos propres fonctions sans recopier de nombres, utilisez les constantes publiques :

| Constante | Valeur |
| --- | --- |
| `Moteur::ALPHABET` | `'abcdefghijklmnopqrstuvwxyz ,.'` (29 symboles, dans cet ordre) |
| `Moteur::LONGUEUR_PAGE` | `3200` |
| `Moteur::MURS`, `Moteur::ETAGERES`, `Moteur::VOLUMES`, `Moteur::PAGES` | `4`, `5`, `32`, `410` |
| `Moteur::LONGUEUR_MAX_HEXAGONE` | `3004` |
| `Moteur::MODES` | `['exact', 'bruit', 'page']` |
| `Moteur::ALGORITHME` | `'babel-moteur/v1'` |

## 6. Les modes de recherche

Le texte cherché ne contient **que** les 29 symboles de l'alphabet : les 26 lettres minuscules **sans accent**, l'espace, la virgule et le point. Pas de majuscule, pas d'apostrophe, pas de saut de ligne. C'est à vous de normaliser le texte avant (fonction F3).

| Mode | La page trouvée contient… | Usage |
| --- | --- | --- |
| `exact` | votre texte au début, suivi d'espaces jusqu'à 3 200 caractères | retrouver « la » page de ce texte, propre et lisible |
| `bruit` | votre texte placé quelque part, entouré de caractères qui ont l'air aléatoires | une page « naturelle », comme si on était tombé dessus par hasard |
| `page` | exactement votre texte, qui doit faire 3 200 caractères | retrouver l'adresse d'une page complète (par exemple une page lue plus tôt) |

- Même texte, même mode, même clé → **toujours la même adresse**, y compris en mode `bruit` (le « hasard » est dérivé de la clé et du texte).
- Le moteur ne renvoie **que l'adresse**, pas la position du texte dans la page : retrouvez-la vous-même (fonction F4 `localiser`).
- Un texte de 3 200 caractères donne la même adresse dans les trois modes.

## 7. Les erreurs

Une entrée refusée lève une **`Babel\Moteur\Erreur\ErreurMoteur`**. C'est un cas normal, que votre serveur traduit en réponse HTTP.

```php
try {
    $moteur->lire('ABC-w9-s1-v1:1');
} catch (ErreurMoteur $e) {
    $e->getMessage();   // "ERR_INVALID_HEX_CHARS (hexagone), ERR_OUT_OF_BOUNDS (mur)"
    $e->erreurs();      // liste d'objets Erreur : ->code (enum CodeErreur), ->champ, ->message
    $e->versTableau();  // [['code' => 'ERR_INVALID_HEX_CHARS', 'champ' => 'hexagone', 'message' => '…'], [...]]
}
```

- **`lire`** renvoie **toutes** les erreurs de l'adresse d'un coup, dans l'ordre hexagone, mur, etagere, volume, page (au plus une par partie).
- **`chercher`** s'arrête à la **première** erreur : une seule à la fois.
- Les messages sont en français et ne recopient jamais votre entrée.

| Code | Champ(s) | Quand |
| --- | --- | --- |
| `ERR_INVALID_ADDRESS_SYNTAX` | `adresse` | l'adresse n'a pas la forme `…-w…-s…-v…:…` |
| | `hexagone` | hexagone vide, ou zéro de tête (`01`) |
| | `mur`, `etagere`, `volume`, `page` | vide, zéro de tête, signe, point, chiffre non ASCII ; ou `:page` absente |
| `ERR_INVALID_HEX_CHARS` | `hexagone` | caractère hors de `0-9a-z` (majuscule, espace, accent…) |
| `ERR_OUT_OF_BOUNDS` | `hexagone` | au-delà du dernier hexagone |
| | `mur`, `etagere`, `volume`, `page` | hors de 1–4, 1–5, 1–32, 1–410 (`0` compris) |
| | `adresse` | page inexistante dans le dernier hexagone |
| `ERR_UNKNOWN_MODE` | `mode` | mode autre que `exact`, `bruit`, `page` (la casse compte) |
| `ERR_EMPTY_QUERY` | `texte` | texte vide |
| `ERR_INVALID_ALPHABET` | `texte` | caractère hors de l'alphabet |
| `ERR_INVALID_PAGE_LENGTH` | `texte` | mode `page` et texte qui ne fait pas 3 200 caractères |
| `ERR_TEXT_TOO_LONG` | `texte` | modes `exact` et `bruit`, texte de plus de 3 200 caractères |

Une autre exception, **`Babel\Moteur\Erreur\CleInvalide`**, signale une clé absente ou mal formée. Ce n'est pas une erreur de l'utilisateur mais **d'installation** : lancez `php artisan babel:cle` puis `php artisan babel:verifier`. Ne l'attrapez pas pour la transformer en 422 : laissez-la remonter en 500, elle doit se voir.

## 8. La clé : règles d'or

La clé fixe le rangement de **votre** bibliothèque. Elle vit dans `.env` :

```dotenv
BABEL_KEY=base64:…44 caractères…
```

1. **Ne la changez jamais** une fois des adresses partagées : toutes vos adresses désigneraient un autre texte. (C'est pour ça que `babel:cle` refuse d'écraser une clé existante sans `--force`.)
2. **Ne la committez jamais** et ne la mettez jamais dans le code : `.env` est déjà ignoré par Git dans un projet Laravel.
3. **Sauvegardez-la** (gestionnaire de mots de passe) : perdue, votre bibliothèque est perdue.
4. **En production**, copiez la **même** ligne `BABEL_KEY=…` dans le `.env` du serveur (ne relancez pas `babel:cle` là-bas).
5. Elle n'a **rien à voir avec `APP_KEY`** : ne la dérivez pas, ne les mélangez pas.
6. Lisez-la avec `config('babel.cle')` si besoin, **jamais `env('BABEL_KEY')`** dans votre code : après `php artisan config:cache`, `env()` renvoie `null`. (En pratique, vous n'avez jamais besoin de la lire : injectez `Moteur`.)
7. Seule l'**empreinte** se publie.

## 9. Ce que le moteur ne fait pas (c'est votre travail)

| Fonction | Où | Indice |
| --- | --- | --- |
| **F3** `normaliser(texte)` | votre serveur | minuscules, accents retirés, tout caractère hors alphabet remplacé par une espace, espaces consécutives réduites à une seule et espaces des bords retirées (livrable 2 du cahier des charges, F3). Le moteur **refuse** un texte non normalisé, il ne le convertit pas |
| **F5** `valider(adresse)` | votre serveur | les règles du [§ 5](#5-les-adresses) et du tableau des erreurs ; les constantes `Moteur::MURS`… vous évitent de recopier les nombres |
| **F7** `auHasard()` | votre serveur | tirez une adresse valide au hasard (attention : l'hexagone est un nombre de 3 004 chiffres en base 36) |
| **F4** `localiser`, **F6** `voisine` | votre application web | position du texte dans la page, page suivante ou précédente |
| Routes, statuts HTTP, JSON, liens courts, marque-pages… | votre serveur | |

## 10. Dépannage

| Symptôme | Cause et solution |
| --- | --- |
| `requires PHP extension ext-gmp * but it is missing` | l'extension `gmp` n'est pas active : voir [§ 2](#2-prérequis--php-et-lextension-gmp) |
| `Call to undefined function gmp_…` en navigateur mais pas en ligne de commande | `gmp` est active pour la ligne de commande seulement : activez-la aussi pour le PHP du serveur web |
| `CleInvalide : La clé BABEL_KEY est absente` | pas de ligne `BABEL_KEY` dans `.env` : `php artisan babel:cle` |
| `CleInvalide : … n'est pas un base64 valide` | la clé a été mal recopiée (espace, caractère en moins) : recopiez la ligne entière |
| La clé est dans `.env` mais le moteur dit qu'elle est absente | la configuration est en cache : `php artisan config:clear` (ou relancez `php artisan config:cache`) |
| Mes adresses ne donnent plus les mêmes pages | la clé a changé (`--force`, autre `.env`, autre machine). Comparez l'empreinte affichée par `php artisan babel:verifier` |
| `ErreurMoteur` sur une adresse qui « a l'air bonne » | regardez `$e->versTableau()` : zéro de tête, majuscule, espace en trop, page absente… |
| Un texte avec des accents est refusé | normal : normalisez-le d'abord (F3) |

---

Algorithme `babel-moteur/v1` : réseau de Feistel à 8 tours sur HMAC-SHA256, avec cycle-walking. Cette construction convient à un projet pédagogique ; ce n'est pas un chiffrement normalisé, ne la réutilisez pas pour protéger des données réelles.

Licence MIT.
