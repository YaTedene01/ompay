<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Les chemins pour lesquels CORS est appliqué (séparés par des virgules dans
    // la variable d'environnement CORS_PATHS). Valeurs par défaut : api/* et
    // sanctum/csrf-cookie.
    'paths' => array_values(array_filter(array_map('trim', explode(',', env('CORS_PATHS', 'api/*,sanctum/csrf-cookie'))))),

    // Méthodes autorisées (ex: GET,POST,PUT). Utilisez '*' pour autoriser toutes
    // les méthodes. Vous pouvez passer une liste séparée par des virgules via
    // CORS_ALLOWED_METHODS.
    'allowed_methods' => (function () {
        $m = env('CORS_ALLOWED_METHODS', '*');
        if ($m === '*') {
            return ['*'];
        }
        return array_values(array_filter(array_map('trim', explode(',', strtoupper($m)))));
    })(),

    // Origines autorisées. Par défaut '*'. Pour autoriser plusieurs origines, mettez
    // par exemple: CORS_ALLOWED_ORIGINS=http://localhost:3000,https://example.com
    'allowed_origins' => (function () {
        $o = env('CORS_ALLOWED_ORIGINS', '*');
        if ($o === '*') {
            return ['*'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $o))));
    })(),

    'allowed_origins_patterns' => (function () {
        $p = env('CORS_ALLOWED_ORIGINS_PATTERNS', '');
        if ($p === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $p))));
    })(),

    // En-têtes autorisés. Utilisez '*' pour tout autoriser.
    'allowed_headers' => (function () {
        $h = env('CORS_ALLOWED_HEADERS', '*');
        if ($h === '*') {
            return ['*'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $h))));
    })(),

    'exposed_headers' => (function () {
        $e = env('CORS_EXPOSED_HEADERS', '');
        if ($e === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $e))));
    })(),

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),

];
