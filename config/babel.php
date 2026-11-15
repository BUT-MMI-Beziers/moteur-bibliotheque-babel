<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Clé de la bibliothèque
    |--------------------------------------------------------------------------
    |
    | 32 octets aléatoires, écrits dans .env sous la forme BABEL_KEY=base64:…
    | par la commande « php artisan babel:cle ». La clé fixe le rangement de
    | la bibliothèque : ne la changez jamais une fois des adresses partagées,
    | et ne la committez jamais. Elle n'est jamais dérivée de APP_KEY.
    |
    | Lisez-la toujours par config('babel.cle'), jamais par env() hors de ce
    | fichier : après « php artisan config:cache », env() renvoie null.
    |
    */

    'cle' => env('BABEL_KEY'),

];
