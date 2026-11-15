# Maintenance du moteur (enseignant)

Ce document s'adresse au mainteneur du paquet, pas aux étudiants (eux lisent le `README.md`). Il n'est pas distribué par Composer.

## Règle d'or

**Aucune version ne change une sortie de `lire`, `chercher` ou `empreinte`.** Des adresses auront été partagées : une page qui change rend le moteur inutilisable. L'algorithme `babel-moteur/v1` est figé : étiquettes, ordre des octets, nombre de tours, ordre des contrôles. Les vecteurs de `tests/donnees/vecteurs.json` (copie exacte de `reference/vecteurs.json`, SHA-256 contrôlé par un test) passent à l'octet près ; on ne les modifie jamais, et on n'adapte jamais un test pour le faire passer.

## Organisation

```text
src/Moteur.php                   API publique : lire, chercher, empreinte, depuisConfiguration, constantes
src/Erreur/                      API publique : CodeErreur, Erreur, ErreurMoteur, CleInvalide
src/Interne/                     @internal : Constantes, Page, Adresse, Cle, Permutation, Composition
src/Laravel/                     MoteurServiceProvider, commandes babel:cle et babel:verifier
config/babel.php                 'cle' => env('BABEL_KEY')
resources/verification.json      vecteurs embarqués pour babel:verifier (généré, voir plus bas)
reference/                       cahier des charges, référence Python, vecteurs, esquisse PHP (non distribués)
tests/                           Unitaire, Vecteurs, Proprietes, Robustesse, Cle, Laravel (non distribués)
outils/                          banc d'essai, génération de resources/verification.json (non distribués)
```

`.gitattributes` exclut de l'archive Composer tout ce qui n'est pas utile à l'exécution : un étudiant reçoit `src/`, `config/`, `resources/`, `composer.json`, `README.md`, `LICENSE` et `CHANGELOG.md`.

## Commandes de développement

Prérequis : PHP 8.2+ avec `gmp` (`sudo apt install php8.3-gmp`).

```bash
composer install
```

```bash
composer qualite
```

| Commande | Effet |
| --- | --- |
| `composer test` | toute la suite PHPUnit (vecteurs, propriétés sur 200 tirages, robustesse, fuite de clé, Laravel) |
| `composer analyse` | PHPStan, niveau max |
| `composer style` / `composer formater` | vérifie / corrige le formatage (Pint, PSR-12) |
| `composer qualite` | les trois à la suite |
| `composer bench` | 300 allers-retours : médiane, 95e centile, maximum (échoue au-delà de 50 ms de médiane) |
| `composer vecteurs:embarquer` | régénère `resources/verification.json` depuis `tests/donnees/vecteurs.json` |
| `python3 reference/moteur_reference.py vecteurs` | régénère les vecteurs depuis la référence (doit redonner exactement le même fichier) |

Mesures sur le poste de développement (PHP 8.3, GMP 6.3) : `lire` et `chercher` à 1,2 ms de médiane, 3,5 ms au 95e centile ; construction du moteur 0,5 ms.

## Publication : mise en place (une seule fois)

Ces étapes ne peuvent pas être automatisées depuis le dépôt :

1. **Dépôt public.** Packagist n'indexe que les dépôts publics. Le code et les clés de test A et B sont publics, c'est voulu : la sécurité repose sur la clé de chaque bibliothèque.
2. **Branche `main`.** Fusionner la branche de développement dans `main` et en faire la branche par défaut (Settings → General → Default branch). Les workflows de publication se déclenchent sur `main`.
3. **Droits de GitHub Actions** (Settings → Actions → General → Workflow permissions, au niveau du dépôt **et** de l'organisation si elle impose une politique) : cocher *Read and write permissions* et *Allow GitHub Actions to create and approve pull requests*. Sans cela, release-please ne peut pas ouvrir sa PR de release.
4. **Packagist.** Se connecter à <https://packagist.org> avec un compte GitHub, *Submit*, coller `https://github.com/BUT-MMI-Beziers/moteur-bibliotheque-babel`. Le nom `but-mmi-beziers/babel-moteur` vient de `composer.json`. Dans le profil Packagist, connecter le compte GitHub : Packagist installe alors son crochet sur le dépôt et se met à jour à chaque tag, sans rien faire de plus.
5. **Facultatif** : secrets `PACKAGIST_USERNAME` et `PACKAGIST_TOKEN` (le jeton d'API du profil Packagist) dans Settings → Secrets and variables → Actions. Le workflow `Publication` notifie alors Packagist explicitement après chaque release, en plus du crochet. Sans ces secrets, l'étape se contente d'un avertissement.

## Publication : au quotidien (release-please)

Les messages de commit suivent les [commits conventionnels](https://www.conventionalcommits.org/fr/) :

| Préfixe | Effet sur la version | Exemple |
| --- | --- | --- |
| `fix:` | correctif : 1.0.0 → 1.0.1 | `fix: message d'erreur du mur` |
| `feat:` | nouveauté compatible : 1.0.1 → 1.1.0 | `feat: commande babel:verifier --json` |
| `docs:`, `test:`, `chore:`, `ci:`, `refactor:` | aucune release à elles seules | `docs: FAQ Windows` |
| `feat!:` ou `BREAKING CHANGE:` | version majeure | **à proscrire** : une rupture de l'API casse les projets des étudiants |

Le déroulé :

1. Chaque push sur `main` lance les tests (`tests.yml`) et met à jour une **PR de release** ouverte par release-please (version calculée + `CHANGELOG.md`).
2. **Fusionner cette PR** crée le tag `vX.Y.Z` et la Release GitHub. Le workflow `Publication` relance alors toute la suite de tests sur le commit de release, joint une archive `.zip` (le même contenu que celui que télécharge Composer) à la Release et notifie Packagist.
3. Les étudiants récupèrent la version avec `composer update but-mmi-beziers/babel-moteur`.

La première release sera **1.0.0** : le commit de documentation porte le pied `Release-As: 1.0.0`, que release-please lit une fois. Rien à défaire ensuite.

Limite connue de GitHub : une PR ouverte par le jeton `GITHUB_TOKEN` (celle de release-please) ne déclenche pas le workflow de tests. Les tests tournent sur `main` avant (à chaque push) et sur le commit de release après la fusion.

## Intégration continue

`tests.yml` : `composer validate --strict`, Pint, PHPStan max, puis PHPUnit sur PHP 8.2, 8.3, 8.4, 8.5 × Laravel 12 et 13 (Laravel 13 exige PHP 8.3), dépendances les plus basses et les plus hautes, plus une tâche Windows. Une tâche vérifie aussi que Composer **refuse** l'installation sans `ext-gmp` en nommant l'extension.

Pour ajouter une version de PHP (8.6, 9.0), l'ajouter à `matrix.php`. Pour une nouvelle version de Laravel : élargir les contraintes `illuminate/*` et `orchestra/testbench` dans `composer.json`, puis ajouter la valeur à `matrix.laravel` et la version d'`orchestra/testbench` correspondante dans l'étape d'installation.

## Écarts avec le cahier des charges (à connaître)

| Sujet | Cahier des charges | Choix retenu | Pourquoi |
| --- | --- | --- | --- |
| Nom du paquet | `babel/moteur` | `but-mmi-beziers/babel-moteur` | le vendor `babel` appartient déjà à un autre mainteneur sur Packagist (`babel/summer`) ; l'espace de noms PHP `Babel\Moteur` est inchangé |
| Distribution (choix 16.2) | dépôt Git de type `vcs` | Packagist, `composer require` direct | demande de l'enseignant |
| Laravel (choix 16.8) | 11 et suivants | 12 et 13 | Laravel 11 n'a plus de correctifs ; Laravel 13 exige PHP 8.3 |
| Dépendances | `illuminate/support` | `illuminate/support`, `illuminate/console`, `illuminate/contracts` | les commandes Artisan et les contrats vivent dans ces paquets ; tous sont fournis par `laravel/framework` |
| Lecture de `BABEL_KEY` | `base64_decode(…, true)` | `base64_decode(…, true)` **plus** une expression régulière de base64 canonique | `base64_decode` strict accepte encore les espaces et l'absence de `=` final, alors que la référence (`b64decode(validate=True)`) les refuse. On s'aligne sur la référence |
| Contrôle de l'alphabet | `strspn($texte, ALPHABET)` | `preg_match('/[^a-z ,.]/')` (repli sur `strspn` si PCRE échoue) | même résultat, mais `strspn` compare chaque octet aux 29 symboles : 145 ms sur 8 Mo contre 8 ms |
| Sous-clés | « propriétés privées » | `WeakMap` statique privée | `dump()` et `dd()` (Symfony VarDumper) affichent les propriétés privées **malgré** `__debugInfo()`. Les sous-clés ne sont donc dans aucune propriété d'objet |
| Test « fonctionnement après `config:cache` » | testbench | test unitaire en deux temps + essai réel | testbench recharge toujours ses fichiers de configuration et ignore le cache. L'essai réel est fait dans un Laravel neuf (voir plus bas) |

Conséquence de la `WeakMap` : la limite connue du cahier des charges (« `var_export`, `(array)` ou la réflexion peuvent lire des propriétés privées ») se réduit à la **réflexion sur une propriété statique** d'une classe `@internal`. `var_export` et `(array)` ne révèlent rien. Révéler `k_perm` revient toujours à révéler la clé.

## Essai dans un Laravel neuf

À refaire avant chaque version majeure de Laravel :

```bash
composer create-project laravel/laravel essai-babel
```

```bash
cd essai-babel && composer require but-mmi-beziers/babel-moteur
```

```bash
php artisan babel:cle && php artisan babel:verifier
```

```bash
php artisan tinker --execute="echo strlen(app(Babel\Moteur\Moteur::class)->lire('0-w1-s1-v1:1'));"
```

La dernière commande doit afficher `3200`. Recommencer après `php artisan config:cache` : même résultat, même empreinte. Pour tester une version non publiée, ajouter un dépôt de type `path` pointant vers ce dossier dans le `composer.json` du projet d'essai.

## Avant la distribution aux étudiants

- [ ] Étapes de mise en place ci-dessus faites, PR de release 1.0.0 fusionnée, version visible sur Packagist.
- [ ] `composer require but-mmi-beziers/babel-moteur` fonctionne dans un projet neuf (sans dépôt `path`).
- [ ] Mettre à jour la **fiche moteur** des étudiants (cahier des charges § 17) : `chercher` renvoie seulement l'adresse (plus la position), et `empreinte` existe.
- [ ] Reporter l'empreinte (`^[0-9a-f]{64}$`) dans le contrat OpenAPI et dans `BIBLIOTHEQUE.empreinte_cle` (choix 16.3).
- [ ] Vérifier que les postes de la salle ont l'extension `gmp`.
