# Cahier des charges : le moteur de la Bibliothèque de Babel

Version 1.0 du 15 novembre 2026. Ce document est écrit pour être donné tel quel à Claude Code, avec le dossier `reference/` qui l'accompagne.

| Fichier | Rôle |
| --- | --- |
| `cahier-des-charges-moteur.md` | ce document : ce que le moteur doit faire, à l'octet près |
| `reference/moteur_reference.py` | implémentation de référence en Python 3, sans dépendance. Elle tranche toute ambiguïté de ce document |
| `reference/vecteurs.json` | vecteurs de test produits par la référence. Le moteur PHP doit les reproduire exactement |
| `reference/faisabilite.php` | esquisse PHP jetable, en un seul fichier, qui rejoue les vecteurs. Elle prouve que tout se reproduit avec PHP 8.3 et GMP et donne les temps. À lire pour les appels GMP, pas pour l'architecture |

## 0. Consignes pour Claude Code

1. Lis ce document en entier, puis `reference/moteur_reference.py`, avant d'écrire du code.
2. La règle qui passe avant toutes les autres : **les vecteurs de `reference/vecteurs.json` passent tous, à l'octet près**. Si le document et la référence Python se contredisent, la référence a raison, et tu le signales.
3. Ne change rien à l'algorithme : ni les étiquettes, ni l'ordre des octets, ni le nombre de tours, ni l'ordre des contrôles. Une « amélioration » qui change une seule page rend le moteur inutilisable, puisque des adresses auront déjà été partagées.
4. Ne modifie jamais `vecteurs.json` à la main et n'adapte jamais un test pour le faire passer.
5. Avance par étapes (section 14), avec des tests verts et un commit à la fin de chaque étape.
6. Si un point n'est pas couvert, pose la question au lieu d'inventer. Les choix encore ouverts sont listés en section 16.
7. Le code, les commentaires et les messages sont en français, comme le contrat. Les codes d'erreur restent en anglais (`ERR_…`).

## 1. Objectif et périmètre

Le moteur est une bibliothèque PHP que l'enseignant distribue aux étudiants le mardi 17 novembre 2026. Chaque étudiant l'installe avec Composer dans son serveur Laravel et s'en sert comme d'une boîte noire. Il fournit trois fonctions du livrable 2 du cahier des charges du projet :

| Fonction | Rôle |
| --- | --- |
| F1 `lire(adresse)` | donne le texte de la page qui se trouve à cette adresse |
| F2 `chercher(texte, mode)` | donne l'adresse d'une page qui contient ce texte |
| F8 `empreinte()` | donne une empreinte de la clé, sans la révéler |

Le principe : la bibliothèque contient toutes les pages possibles de 3 200 caractères, soit 29^3200 pages. Rien n'est stocké. Une permutation à clé relie le numéro d'une adresse au numéro d'un texte, dans les deux sens : `lire` applique la permutation, `chercher` applique son inverse. Chaque bibliothèque a sa propre clé, donc son propre rangement.

Le moteur garantit :

- **Bijection exacte.** Deux adresses différentes donnent deux pages différentes, et toute page de 3 200 caractères a exactement une adresse. `chercher(lire(a), "page") == a` pour toute adresse valide, et `lire(chercher(t, "page")) == t` pour toute page.
- **Déterminisme.** Mêmes entrées et même clé : même sortie, aujourd'hui et dans un an, sur toutes les machines, y compris en mode `bruit`.
- **Aucun état.** Pas de base de données, pas de fichier, pas de cache, pas de réseau, pas d'horloge, pas de hasard pendant `lire` et `chercher`.
- **La clé ne sort pas.** Elle n'apparaît dans aucune signature de fonction métier, aucune sortie, aucun message d'erreur, aucun journal.

Le moteur ne fait pas, et ne doit pas faire :

| Hors périmètre | Qui s'en charge |
| --- | --- |
| F3 `normaliser(texte)` | les étudiants, sur le serveur. Le moteur refuse un texte hors alphabet, il ne le convertit pas |
| F5 `valider(adresse)` | les étudiants, sur le serveur. Le moteur contrôle ses entrées pour lui-même mais n'expose pas de fonction de validation |
| F7 `auHasard()` | les étudiants, sur le serveur |
| F4 `localiser`, F6 `voisine` | les étudiants, dans l'application web |
| corriger une adresse (`w04`, `W4`) | personne : on refuse, on ne corrige pas |
| renvoyer la position du texte trouvé | l'application web (`localiser`) |
| routes HTTP, statuts, JSON | le serveur de chaque étudiant |
| liens courts, marque-pages, textes bannis, réseau | le serveur de chaque étudiant |

## 2. Vocabulaire et constantes

| Nom | Valeur |
| --- | --- |
| Alphabet | `abcdefghijklmnopqrstuvwxyz ,.` : 29 symboles, dans cet ordre. `a` vaut 0, `z` vaut 25, l'espace 26, la virgule 27, le point 28 |
| Longueur d'une page | 3 200 caractères, exactement |
| Nombre de pages N | 29^3200 (4 680 chiffres décimaux, 15 546 bits) |
| Murs, étagères, volumes, pages | 4, 5, 32, 410 |
| Pages par hexagone | 4 × 5 × 32 × 410 = 262 400 |
| Dernier hexagone Hmax | (N − 1) div 262 400. Il s'écrit avec 3 004 caractères en base 36 et commence par `4rlo36cp` |
| Dernière adresse | `<Hmax>-w1-s1-v1:1`. N mod 262 400 = 1 : le dernier hexagone ne contient qu'une page |
| Adresse la plus longue | 3 018 caractères |
| Largeur de la permutation | 15 546 bits, en deux moitiés de 7 773 bits (972 octets chacune, dont 3 bits de tête inutilisés) |

Les empreintes SHA-256 de N, de Hmax et de la dernière adresse sont dans `vecteurs.json`, rubrique `constantes`, pour vérifier ces valeurs sans les recopier.

### L'adresse

Forme canonique : `<hexagone>-w<mur>-s<étagère>-v<volume>:<page>`.

- `hexagone` : un entier écrit en base 36 avec les chiffres `0` à `9` puis les minuscules `a` à `z`, sans zéro de tête (sauf l'hexagone `0` lui-même), de 0 à Hmax.
- `mur` de 1 à 4, `étagère` de 1 à 5, `volume` de 1 à 32, `page` de 1 à 410, en décimal, sans zéro de tête ni signe.
- Une page n'a qu'une seule écriture valide.

Numéro d'une adresse, entre 0 et N − 1 :

```
I = (((H × 4 + (mur − 1)) × 5 + (étagère − 1)) × 32 + (volume − 1)) × 410 + (page − 1)
```

Dans l'autre sens, par divisions successives : `page − 1 = I mod 410`, puis `volume − 1` (mod 32), `étagère − 1` (mod 5), `mur − 1` (mod 4), et le quotient restant est H.

### La page

Une page est un nombre écrit en base 29 avec l'alphabet, le premier caractère étant le chiffre de poids fort :

```
C = c[0] × 29^3199 + c[1] × 29^3198 + … + c[3199]
```

Dans l'autre sens, le nombre est toujours écrit sur 3 200 caractères, complété à gauche par des `a` (le chiffre 0).

## 3. Interface publique

Paquet Composer `babel/moteur`, espace de noms `Babel\Moteur` (noms à confirmer, section 16). PHP 8.2 ou plus, extension `gmp` obligatoire (`"ext-gmp": "*"` dans `composer.json`). Le cœur ne dépend pas de Laravel.

```php
namespace Babel\Moteur;

final class Moteur
{
    public const ALPHABET = 'abcdefghijklmnopqrstuvwxyz ,.';
    public const LONGUEUR_PAGE = 3200;
    public const MURS = 4;
    public const ETAGERES = 5;
    public const VOLUMES = 32;
    public const PAGES = 410;
    public const LONGUEUR_MAX_HEXAGONE = 3004;
    public const MODES = ['exact', 'bruit', 'page'];
    public const ALGORITHME = 'babel-moteur/v1';

    /** @param string $cle exactement 32 octets bruts */
    public function __construct(#[\SensitiveParameter] string $cle);

    /** @param string $valeur la valeur de BABEL_KEY, de la forme "base64:…" */
    public static function depuisConfiguration(#[\SensitiveParameter] string $valeur): self;

    /** @throws ErreurMoteur */
    public function lire(string $adresse): string;

    /** @throws ErreurMoteur */
    public function chercher(string $texte, string $mode): string;

    public function empreinte(): string;
}
```

Les trois méthodes métier ne reçoivent jamais la clé. Les constantes publiques servent aux étudiants pour écrire `valider`, `normaliser` et `auHasard` sans recopier de nombres.

### Les erreurs

```php
namespace Babel\Moteur\Erreur;

enum CodeErreur: string
{
    case AdresseMalEcrite   = 'ERR_INVALID_ADDRESS_SYNTAX';
    case HexagoneInvalide   = 'ERR_INVALID_HEX_CHARS';
    case HorsLimites        = 'ERR_OUT_OF_BOUNDS';
    case RechercheVide      = 'ERR_EMPTY_QUERY';
    case TexteTropLong      = 'ERR_TEXT_TOO_LONG';
    case AlphabetInvalide   = 'ERR_INVALID_ALPHABET';
    case ModeInconnu        = 'ERR_UNKNOWN_MODE';
    case LongueurDePage     = 'ERR_INVALID_PAGE_LENGTH';
}

final readonly class Erreur
{
    public CodeErreur $code;
    public string $champ;      // adresse, hexagone, mur, etagere, volume, page, texte, mode
    public string $message;    // phrase lisible, en français
}

/** Une entrée refusée. C'est un cas normal, que le serveur traduit en réponse HTTP. */
final class ErreurMoteur extends \DomainException
{
    /** @return non-empty-list<Erreur> */
    public function erreurs(): array;
}

/** La clé est absente ou mal formée. C'est une erreur d'installation, pas une erreur d'entrée. */
final class CleInvalide extends \RuntimeException {}
```

- `ErreurMoteur::getMessage()` liste les codes et les champs (`ERR_OUT_OF_BOUNDS (mur), ERR_INVALID_ADDRESS_SYNTAX (page)`). Il ne recopie pas l'entrée reçue, qui peut faire 3 000 caractères.
- Les codes `ERR_LIBRARY_LIMIT` et `ERR_TOO_MANY_REQUESTS` du cahier des charges ne concernent pas le moteur : ne les déclare pas.
- Un message de `CleInvalide` ne contient jamais la valeur reçue, même partielle.

## 4. Contrôle d'une adresse (`lire`)

`lire` attend une adresse de page. Les contrôles se font dans cet ordre, sur les octets reçus, sans aucune correction (pas de `trim`, pas de passage en minuscules).

1. **Découpage.** L'adresse entière doit correspondre à l'expression `([^-]*)-w([^-]*)-s([^-]*)-v([^:]*)(?::(.*))?`, le point acceptant aussi le saut de ligne. Sinon : une seule erreur `ERR_INVALID_ADDRESS_SYNTAX`, champ `adresse`, et on s'arrête.
2. **Chaque champ est contrôlé séparément**, et toutes les erreurs sont renvoyées ensemble, dans l'ordre hexagone, mur, etagere, volume, page. Au plus une erreur par champ, la première qui s'applique.

| Champ | Contrôles, dans l'ordre | Erreur |
| --- | --- | --- |
| hexagone | vide | `ERR_INVALID_ADDRESS_SYNTAX` |
| | un caractère hors de `0-9a-z` (une majuscule, une espace, un accent) | `ERR_INVALID_HEX_CHARS` |
| | plus d'un caractère et commence par `0` | `ERR_INVALID_ADDRESS_SYNTAX` |
| | plus de 3 004 caractères, ou valeur supérieure à Hmax | `ERR_OUT_OF_BOUNDS` |
| mur, etagere, volume, page | ne correspond pas à `0` ou `[1-9][0-9]*` (vide, zéro de tête, signe, point, chiffre non ASCII) | `ERR_INVALID_ADDRESS_SYNTAX` |
| | plus de 3 chiffres, ou valeur hors de 1 à 4, 5, 32, 410 (`0` compris) | `ERR_OUT_OF_BOUNDS` |
| page | absente (adresse de livre, sans `:<page>`) | `ERR_INVALID_ADDRESS_SYNTAX` |

3. **La page existe-t-elle ?** Si aucun champ n'est en erreur, on calcule le numéro I. Si I ≥ N : `ERR_OUT_OF_BOUNDS`, champ `adresse`. Cela n'arrive que dans le dernier hexagone, qui n'a qu'une page.

Le contrôle de longueur se fait avant toute conversion en grand nombre : une entrée énorme est refusée sans calcul.

Les 35 cas de `vecteurs.json`, rubrique `erreurs.lire`, fixent le résultat attendu, liste d'erreurs comprise.

## 5. La permutation

### Sous-clés

La clé K fait 32 octets. Elle n'est jamais utilisée directement. Trois valeurs en sont dérivées à la construction, puis K est oubliée (elle n'est pas gardée dans l'objet) :

```
k_perm    = HMAC-SHA256(clé = K, message = "babel-moteur/v1/permutation")     32 octets
k_bruit   = HMAC-SHA256(clé = K, message = "babel-moteur/v1/bruit")           32 octets
empreinte = HMAC-SHA256(clé = K, message = "babel-moteur/v1/empreinte")       en hexadécimal
```

Les étiquettes sont des chaînes ASCII, sans octet nul final.

### Fonction de tour

Notations : `be16(i)` est l'entier i sur 2 octets, poids fort en premier ; `be972(x)` est x sur exactement 972 octets, poids fort en premier, complété à gauche par des octets nuls ; `‖` est la concaténation ; `MASQUE = 2^7773 − 1`.

```
F(tour, x) :                                   tour de 0 à 7, x entre 0 et 2^7773 − 1
    message = octet(tour) ‖ be972(x)
    flux = HMAC-SHA256(k_perm, be16(0) ‖ message) ‖ HMAC-SHA256(k_perm, be16(1) ‖ message) ‖ … ‖ HMAC-SHA256(k_perm, be16(30) ‖ message)
    renvoyer (les 972 premiers octets de flux, lus comme un entier poids fort en premier) ET MASQUE
```

31 blocs de 32 octets font 992 octets : on garde les 972 premiers. Le masque efface les 3 bits de tête.

### Réseau de Feistel, 8 tours

```
P(x) :                                         x entre 0 et 2^15546 − 1
    g = x décalé de 7773 bits vers la droite
    d = x ET MASQUE
    pour tour de 0 à 7 :
        (g, d) = (d, g OU-EXCLUSIF F(tour, d))
    renvoyer (g décalé de 7773 bits vers la gauche) OU d

P_inverse(y) :
    g = y décalé de 7773 bits vers la droite
    d = y ET MASQUE
    pour tour de 7 à 0 :
        (g, d) = (d OU-EXCLUSIF F(tour, g), g)
    renvoyer (g décalé de 7773 bits vers la gauche) OU d
```

### Rester dans la bibliothèque (cycle-walking)

P permute les nombres de 15 546 bits, et il y en a un peu plus que de pages (2^15546 / N ≈ 1,376). Quand le résultat dépasse la bibliothèque, on applique P de nouveau, jusqu'à retomber dedans. C'est ce qui rend la bijection exacte sur les N pages.

```
adresse vers page :   y = P(I) ;          tant que y ≥ N : y = P(y)
page vers adresse :   x = P_inverse(C) ;  tant que x ≥ N : x = P_inverse(x)
```

Il faut 1,38 application en moyenne. Mets un garde-fou à 1 000 applications qui lève une `\LogicException` : il ne se déclenche jamais, sauf bogue.

### Les deux fonctions

```
lire(adresse) :
    I = numéro de l'adresse (section 4, erreurs comprises)
    C = adresse vers page (I)
    renvoyer le texte de C (3 200 caractères)

chercher(texte, mode) :
    page = composer(texte, mode)               (section 6, erreurs comprises)
    I = page vers adresse (nombre de la page)
    renvoyer l'écriture canonique de I
```

## 6. Les modes de recherche (`chercher`)

Contrôles, dans cet ordre. On s'arrête à la première erreur : `chercher` ne renvoie jamais qu'une erreur à la fois.

| Ordre | Condition | Erreur | Champ |
| --- | --- | --- | --- |
| 1 | le mode n'est pas exactement `exact`, `bruit` ou `page` | `ERR_UNKNOWN_MODE` | mode |
| 2 | le texte est vide | `ERR_EMPTY_QUERY` | texte |
| 3 | un octet du texte n'est pas dans l'alphabet (majuscule, accent, saut de ligne) | `ERR_INVALID_ALPHABET` | texte |
| 4 | mode `page` et longueur différente de 3 200 | `ERR_INVALID_PAGE_LENGTH` | texte |
| 5 | modes `exact` et `bruit`, longueur supérieure à 3 200 | `ERR_TEXT_TOO_LONG` | texte |

La longueur se compte en octets. Une fois l'alphabet contrôlé, un octet vaut un caractère.

Composition de la page cherchée :

- **`page`** : la page est le texte lui-même.
- **`exact`** : le texte, suivi d'espaces jusqu'à 3 200 caractères.
- **`bruit`** : le texte, placé à une position et entouré d'un remplissage qui dépendent de la clé et du texte, jamais du hasard. Deux recherches du même texte donnent donc la même adresse, et deux bibliothèques n'entourent pas le texte du même bruit.

Le mode `bruit`, précisément. Soit L la longueur du texte. On produit un flux d'octets :

```
flux = HMAC-SHA256(k_bruit, be32(0) ‖ texte) ‖ HMAC-SHA256(k_bruit, be32(1) ‖ texte) ‖ …        (be32 : 4 octets, poids fort en premier)
```

On le consomme dans l'ordre, en produisant de nouveaux blocs au besoin :

```
places = 3200 − L + 1
limite = 65536 − (65536 mod places)
répéter : lire 2 octets, u = leur valeur (poids fort en premier)      jusqu'à u < limite
position = u mod places

remplissage = ""
tant que le remplissage a moins de 3200 − L caractères :
    lire 1 octet b
    si b < 232 : ajouter ALPHABET[b mod 29] au remplissage           (232 = 8 × 29 : pas de biais)

page = remplissage[0 .. position − 1] ‖ texte ‖ remplissage[position .. fin]
```

Avec un texte de 3 200 caractères, les trois modes donnent la même page. Le moteur ne renvoie pas la position : l'application web la retrouve avec `localiser`.

## 7. L'empreinte (`empreinte`)

`empreinte()` renvoie les 64 caractères hexadécimaux minuscules de `HMAC-SHA256(K, "babel-moteur/v1/empreinte")`. Elle est calculée à la construction. Elle ne change pas tant que la clé ne change pas, deux clés différentes donnent deux empreintes différentes, et elle ne permet pas de retrouver la clé. Chaque bibliothèque la publie avec son identité (story B6).

## 8. La clé

| Règle | Détail |
| --- | --- |
| Taille | exactement 32 octets tirés avec `random_bytes(32)` |
| Écriture | dans le `.env` du serveur : `BABEL_KEY=base64:<44 caractères>`, sur le modèle de `APP_KEY` |
| Lecture | `depuisConfiguration` exige le préfixe `base64:`, décode en mode strict et vérifie les 32 octets. Sinon `CleInvalide`, avec un message qui dit quoi faire (`php artisan babel:cle`) sans citer la valeur |
| Indépendance | jamais dérivée de `APP_KEY`, jamais de valeur par défaut, jamais de clé de secours. Sans clé, le moteur refuse de démarrer |
| Stabilité | la clé ne change pas après l'ouverture de la bibliothèque : toutes les adresses partagées désigneraient un autre texte |
| Discrétion | `#[\SensitiveParameter]` sur tout paramètre qui porte la clé ; la clé brute n'est pas conservée après la construction ; les sous-clés sont dans des propriétés privées |
| Affichage | `__debugInfo()` ne montre que l'empreinte : `var_dump`, `print_r`, `dd()` et `dump()` ne révèlent ni la clé ni les sous-clés. `json_encode` d'un `Moteur` ne les révèle pas non plus |
| Sérialisation | `serialize()` d'un `Moteur` lève une exception : il ne doit pas partir dans la charge d'une tâche en file d'attente |
| Erreurs | aucun message d'exception, aucune trace, aucune ligne de journal ne contient la clé ou une sous-clé |

Limite connue, à documenter sans chercher à la contourner : `var_export`, un transtypage `(array)` ou la réflexion peuvent lire des propriétés privées. Révéler `k_perm` revient à révéler la clé.

## 9. Intégration à Laravel

Dans le même paquet, dans `Babel\Moteur\Laravel`, sans que le cœur en dépende (`illuminate/support` en dépendance, versions de Laravel à confirmer en section 16).

- **Fournisseur de services** `MoteurServiceProvider`, découvert automatiquement (`extra.laravel.providers` dans `composer.json`). Il fusionne `config/babel.php` (`'cle' => env('BABEL_KEY')`), publiable avec l'étiquette `babel-config`, et déclare `Moteur` en singleton, construit à la première utilisation à partir de `config('babel.cle')`.
- La clé se lit toujours par `config()`, jamais par `env()` hors du fichier de configuration : avec `php artisan config:cache`, `env()` renvoie `null`.
- Pas de façade : les étudiants injectent `Moteur` dans leurs contrôleurs, leurs services et leurs tâches de fond.
- **`php artisan babel:cle`** : tire une clé et écrit `BABEL_KEY=base64:…` dans `.env`. Si une clé existe déjà, la commande refuse et explique pourquoi ; `--force` la remplace après un avertissement clair (toutes les adresses changent). `--afficher` écrit la ligne sur la sortie sans toucher au `.env`.
- **`php artisan babel:verifier`** : vérifie l'extension `gmp`, la présence et la forme de la clé, rejoue les vecteurs embarqués avec les clés de test, fait un aller-retour avec la vraie clé, puis affiche l'empreinte et le temps moyen d'une lecture. Code de sortie 0 si tout va bien, 1 sinon. C'est la commande que les étudiants lancent après l'installation.

Usage attendu côté étudiant :

```php
use Babel\Moteur\Moteur;
use Babel\Moteur\Erreur\ErreurMoteur;

public function show(string $adresse, Moteur $moteur)
{
    try {
        return response()->json(['adresse' => $adresse, 'texte' => $moteur->lire($adresse)]);
    } catch (ErreurMoteur $e) {
        // $e->erreurs() : la liste {code, champ, message} à traduire dans la forme d'erreur du contrat d'API
    }
}
```

## 10. Organisation du paquet

Proposition, à adapter si une meilleure découpe apparaît. Seuls `Moteur` et le dossier `Erreur` sont publics ; le reste porte `@internal`.

```
composer.json
README.md                        notice pour les étudiants : installer, configurer, appeler, erreurs
config/babel.php
src/Moteur.php                   les trois fonctions, rien d'autre
src/Adresse.php                  chaîne <-> numéro, avec les contrôles de la section 4
src/Page.php                     texte <-> nombre
src/Permutation.php              F, P, P_inverse, cycle-walking
src/Composition.php              les trois modes, dont le bruit
src/Cle.php                      lecture de BABEL_KEY, dérivation des sous-clés
src/Erreur/                      CodeErreur, Erreur, ErreurMoteur, CleInvalide
src/Laravel/                     MoteurServiceProvider, commandes babel:cle et babel:verifier
tests/donnees/vecteurs.json      copie exacte de reference/vecteurs.json
tests/                           tests unitaires, tests de propriétés, tests Laravel (orchestra/testbench)
```

Le `README.md` s'adresse aux étudiants : il dit ce que le moteur garantit et comment s'en servir, pas comment il est fait.

## 11. Exigences non fonctionnelles

| Sujet | Exigence |
| --- | --- |
| Performance | `lire` et `chercher` : médiane sous 5 ms et 95e centile sous 15 ms sur un portable courant. L'esquisse PHP mesure environ 1,5 ms de médiane et 4 à 5 ms au 95e centile. Construction du moteur sous 1 ms |
| Mémoire | quelques centaines de kilo-octets par appel, rien de conservé entre deux appels en dehors des constantes |
| Entiers | tous les grands nombres passent par GMP. Aucun nombre flottant, aucun entier natif pour un numéro de page, d'adresse ou d'hexagone |
| Hasard | aucun pendant `lire` et `chercher`. Seule `babel:cle` tire au sort, avec `random_bytes` |
| Entrées | toute chaîne est acceptée en entrée sans plantage : le résultat est une valeur ou une `ErreurMoteur`. Jamais de `TypeError`, de `ValueError` ni d'avertissement PHP sur une entrée d'utilisateur |
| Compatibilité | PHP 8.2 à la dernière version stable, Linux, macOS et Windows. Mêmes résultats partout |
| Processus longs | un `Moteur` est immuable et réutilisable : il fonctionne tel quel dans un travailleur de file d'attente ou sous Octane |
| Qualité | `declare(strict_types=1)` partout, PHPStan au niveau maximal sans erreur, formatage Pint ou PHP-CS-Fixer (PSR-12) |
| Dépendances | à l'exécution : `ext-gmp`, `ext-hash` (toujours présente) et `illuminate/support` pour la partie Laravel. Rien d'autre |
| Versions | versionnage sémantique. `1.0.0` est distribuée aux étudiants. Après cette date, aucune version ne change une sortie de `lire`, `chercher` ou `empreinte` |
| Sûreté | la construction (Feistel à 8 tours sur HMAC-SHA256) convient à ce projet pédagogique. Ce n'est pas un chiffrement normalisé : ne pas la réutiliser pour protéger des données réelles |

## 12. Tests

Avec PHPUnit. Chaque rubrique ci-dessous est obligatoire.

### Vecteurs (`tests/donnees/vecteurs.json`)

| Rubrique du fichier | Ce que le test vérifie |
| --- | --- |
| `constantes` | bits, moitié, octets, pages par hexagone, longueur de Hmax, SHA-256 de N en décimal, de Hmax et de la dernière adresse |
| `cles[].babel_key`, `empreinte` | `depuisConfiguration(babel_key)->empreinte()` |
| `cles[].lire[]` | `lire(adresse) == page` (texte entier fourni), et `chercher(page, "page") == adresse` |
| `cles[].chercher[]` | `chercher(texte, mode) == adresse` ; `lire(adresse)` a pour SHA-256 `page_sha256` et contient le texte ; en mode `bruit`, le texte commence à `position` |
| `cles[].interne[]` | `F(tour, x_hex) == f_hex` ; `P(x_hex) == p_hex` et `P_inverse(p_hex) == x_hex` ; position, début et SHA-256 du remplissage en mode bruit |
| `erreurs.lire[]`, `erreurs.chercher[]` | la liste d'erreurs exacte : mêmes codes, mêmes champs, même ordre |

Deux clés de test : A (les octets 0 à 31) et B (32 octets à 255). Le champ `applications` indique le nombre d'applications de P : des vecteurs à 2 et 3 applications couvrent le cycle-walking. Quelques valeurs pour un contrôle à l'œil :

| Valeur | Clé A (octets 0 à 31) | Clé B (32 octets à 255) |
| --- | --- | --- |
| `BABEL_KEY` | `base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=` | `base64://////////////////////////////////////////8=` |
| empreinte | `f85a8e8ae6a8a1ceb96d8884d2d45c00c3cc73c95e1bdf4e5cba35664bb0c637` | `2fdda2d51a73ae9a97ac7582e138de114ee271f3f2fe419f3545d188d2b11046` |
| `lire("0-w1-s1-v1:1")`, 24 premiers caractères | `tnznd.olonj xxqvbefublhf` | `,cehb,fslipiv.teoc,bhyob` |
| SHA-256 de cette page | `702797f9095780ebe8ad4cb569ed98d815fe5d881b16b7004d93b90f4ab3b3a6` | `2fcf8057b5281049dfcc6061d8010b3dc5ceaf801c69573adf5bb5dee364367f` |
| `chercher("ou est passe l ete", "exact")`, début de l'adresse | `43sxzqy307xbxpf7…` (3018 caractères) | `1qwvi3hzgq26x04u…` (3015 caractères) |
| SHA-256 de cette adresse | `22cc810b8b446cdc69f55cc2ea5612e8481e317c43acf3e899de80528f5c50d5` | `22de67ab1201b33ea3b3beb3a891825d90222b6db7c1d70d826d9614f23c9532` |
| `chercher("ou est passe l ete", "bruit")`, début de l'adresse | `2zck7fxg6c3k2qax…` (3018 caractères) | `3nqcdg6mkzde7llu…` (3017 caractères) |
| SHA-256 de cette adresse | `5a2099e801ca04b7772ebe563e96cf5bc75e36c6ef4dc357bf79978402e39468` | `a1b6b2b9b1c9ead1c2edc3063b46569467c984c59aff7938cd19a387a08da5b8` |
| position du texte en mode `bruit` | 2703 | 2582 |

### Propriétés

Sur au moins 200 tirages par propriété, avec une clé tirée au sort :

- aller-retour : `chercher(lire(a), "page") == a` pour des adresses tirées uniformément entre 0 et N − 1, et `lire(chercher(t, "page")) == t` pour des pages tirées au sort ;
- toute page lue fait 3 200 caractères de l'alphabet ; toute adresse renvoyée est canonique et relisible ;
- `exact` : la page lue est le texte suivi d'espaces ; `bruit` : la page lue contient le texte ; dans les deux cas, deux appels donnent la même adresse ;
- deux adresses voisines donnent des pages sans rapport (moins de 10 % de caractères identiques à la même position) ;
- deux clés différentes donnent des pages, des adresses et des empreintes différentes ;
- bornes : `0-w1-s1-v1:1` et `<Hmax>-w1-s1-v1:1` se lisent ; `<Hmax>-w1-s1-v1:2` et `<Hmax + 1>-w1-s1-v1:1` sont refusées.

### Robustesse

- Entrées hostiles pour `lire` et `chercher` : chaîne vide, 1 Mo de `z`, octets nuls, UTF-8 invalide, saut de ligne final, espaces autour. Le résultat est toujours une `ErreurMoteur`, en moins de 50 ms.
- Aucun avertissement ni dépréciation PHP pendant la suite (`failOnWarning`, `failOnDeprecation`, `failOnNotice`).

### Clé

- `depuisConfiguration` refuse : valeur vide, sans préfixe, base64 invalide, 31 ou 33 octets.
- Fuite : ni la clé (brute, hexadécimale, base64) ni les sous-clés n'apparaissent dans `var_dump`, `print_r`, `json_encode`, le message et la trace des exceptions (`getTraceAsString`), ni dans la sortie de `babel:verifier`. `serialize` lève une exception.

### Laravel

Avec `orchestra/testbench` : résolution du singleton, message clair sans clé, `babel:cle` (création, refus d'écraser, `--force`, `--afficher`), `babel:verifier` (codes de sortie 0 et 1), fonctionnement après `config:cache`.

### Performance

Un banc d'essai (`composer bench`) sur 300 allers-retours, qui affiche médiane, 95e centile et maximum pour `lire` et `chercher`. Il ne fait pas échouer l'intégration continue sur un seuil serré : seuil large à 50 ms de médiane, pour repérer une régression grossière.

### Intégration continue

GitHub Actions : matrice PHP 8.2, 8.3, 8.4 et suivantes avec `gmp`, sur Linux, plus une tâche Windows. Étapes : installation, PHPStan, style, tests.

## 13. Pièges PHP déjà rencontrés

Relevés en écrivant `reference/faisabilite.php` :

1. `gmp_export` renvoie une chaîne vide pour 0 et ne complète jamais : il faut toujours compléter à gauche avec des octets nuls jusqu'à 972 octets. Drapeaux : `GMP_MSW_FIRST | GMP_BIG_ENDIAN`, mots de 1 octet, pour `gmp_export` comme pour `gmp_import`.
2. `gmp_strval($x, 29)` écrit avec les chiffres `0-9a-s` et sans zéro de tête : compléter à gauche avec `0` jusqu'à 3 200, puis convertir avec `strtr($s, '0123456789abcdefghijklmnopqrs', Moteur::ALPHABET)`. Dans l'autre sens, `strtr` puis `gmp_init($s, 29)`.
3. `gmp_init($s, 36)` accepte aussi les majuscules et lève une `ValueError` sur un caractère invalide : contrôler les caractères et la longueur avant de l'appeler.
4. Dans une expression régulière, `$` accepte un saut de ligne final : utiliser `\A` et `\z`. Ajouter le modificateur `s`. Utiliser `PREG_UNMATCHED_AS_NULL` pour distinguer une page absente d'une page vide.
5. Ne convertir un champ en entier avec `(int)` qu'après le contrôle par expression régulière et le contrôle de longueur (3 chiffres au plus).
6. Compter avec `strlen`, pas `mb_strlen`. Contrôler l'alphabet avec `strspn($texte, Moteur::ALPHABET) === strlen($texte)`.
7. `hash_hmac('sha256', $message, $cle, true)` : ne pas oublier le quatrième argument pour obtenir des octets. `pack('n', $i)` pour 2 octets, `pack('N', $i)` pour 4.
8. `base64_decode($valeur, true)` : le mode strict, sinon une clé mal recopiée est acceptée en silence.
9. Les opérateurs `>>`, `<<`, `|`, `*`, `+` fonctionnent sur des objets GMP ; pour comparer, préférer `gmp_cmp`.
10. `preg_match` renvoie `false` quand PCRE atteint une de ses limites : le traiter comme une adresse mal écrite (`ERR_INVALID_ADDRESS_SYNTAX`, champ `adresse`), jamais comme une réussite. Les essais sur 8 Mo passent sans atteindre ces limites, mais le cas doit être couvert.

## 14. Étapes de développement

| Étape | Contenu | Terminée quand |
| --- | --- | --- |
| 1 | Squelette : `composer.json`, PHPUnit, PHPStan, style, intégration continue, copie de `vecteurs.json` | la chaîne tourne à vide |
| 2 | `Page` et `Adresse` : conversions et contrôles de la section 4 | constantes et `erreurs.lire` passent |
| 3 | `Cle` et `Permutation` | vecteurs `interne` (F, P, P_inverse) et empreintes passent |
| 4 | `Composition` et `Moteur` | tous les vecteurs et toutes les propriétés passent |
| 5 | Hygiène de la clé et robustesse | tests de fuite et d'entrées hostiles passent |
| 6 | Intégration Laravel : fournisseur, configuration, deux commandes | tests testbench passent, essai dans un Laravel neuf |
| 7 | Banc d'essai, `README.md` étudiant, étiquette `v1.0.0` | critères de la section 15 tous remplis |

## 15. Critères d'acceptation

- [ ] Tous les vecteurs de `vecteurs.json` passent, sans aucune modification du fichier.
- [ ] Toutes les propriétés de la section 12 passent sur 200 tirages au moins.
- [ ] `lire` et `chercher` : médiane sous 5 ms sur le poste de développement.
- [ ] Aucun test de fuite de clé n'échoue.
- [ ] PHPStan au niveau maximal : aucune erreur. Aucun avertissement PHP pendant les tests.
- [ ] Dans un projet Laravel neuf : `composer require`, `php artisan babel:cle`, `php artisan babel:verifier` réussissent, puis `app(Moteur::class)->lire('0-w1-s1-v1:1')` renvoie 3 200 caractères.
- [ ] Sans extension `gmp`, l'installation échoue avec un message qui la nomme.
- [ ] Le `README.md` permet à un étudiant d'installer et d'appeler le moteur sans lire le code.

## 16. Choix à confirmer

Ces choix ont une valeur par défaut, appliquée dans ce document. Ils sont à confirmer par l'enseignant avant la distribution, parce qu'ils ne se changent plus après.

| N° | Choix par défaut | Remarque |
| --- | --- | --- |
| 1 | Paquet `babel/moteur`, espace de noms `Babel\Moteur` | simple à changer avant la version 1.0.0 |
| 2 | Distribution par un dépôt Git étiqueté, installé avec Composer (dépôt de type `vcs`) | ne pas chercher à masquer ou chiffrer le code : la boîte noire est une règle du jeu, la sécurité repose sur la clé |
| 3 | Empreinte : 64 caractères hexadécimaux minuscules | à reporter dans le contrat OpenAPI (`^[0-9a-f]{64}$`) et dans `BIBLIOTHEQUE.empreinte_cle` |
| 4 | `BABEL_KEY=base64:…`, une clé par bibliothèque, tirée par `babel:cle` | même forme que `APP_KEY`, que les étudiants connaissent |
| 5 | Le moteur n'expose ni `valider`, ni `normaliser`, ni `auHasard` | elles restent le travail des étudiants (F3, F5, F7) ; les constantes publiques les aident |
| 6 | `lire` renvoie toutes les erreurs d'adresse en une fois ; `chercher` s'arrête à la première | conforme à `valider` (F5) pour les adresses |
| 7 | Mode `bruit` : position et remplissage dérivés de la clé et du texte | conforme à la décision « même texte, même adresse » |
| 8 | PHP 8.2 au minimum, Laravel 11 et suivants | à aligner sur la version installée en salle |

## 17. Écarts avec les documents précédents

- **Prototype du 30 octobre** (`babel-proto/babel.py`) : même construction (Feistel à 8 tours, HMAC-SHA256 en mode compteur, cycle-walking). La version 1 ajoute les sous-clés dérivées par étiquette, l'empreinte, le mode `bruit` déterministe et le contrôle complet des adresses. Les pages du prototype et celles du moteur ne sont donc pas les mêmes, ce qui est sans conséquence : rien n'a été distribué.
- **Fiche moteur** (document pour les étudiants) : elle annonce encore que `chercher` renvoie l'adresse et la position, et ne mentionne pas `empreinte`. Elle est à mettre à jour avant de distribuer le moteur.
