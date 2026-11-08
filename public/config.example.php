<?php
// Kopie als config.php direkt auf dem Server anlegen (NICHT ins Repository einchecken).
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'dXXXXXXX',
        'user' => 'dXXXXXXX',
        'pass' => '***',
    ],
    'base_url' => 'https://schondraussen.de',
    'tmdb_key' => '',
    'deepl_key' => '', // optional: DeepL-API-Free-Schlüssel (endet auf :fx) für die Übersetzung englischer Beschreibungen
    'google_books_key' => '', // optional: Google-Books-API-Schlüssel, falls Anfragen wegen Begrenzung abgelehnt werden
    'mail' => [
        'from'      => 'benachrichtigung@schondraussen.de',
        'from_name' => 'Schondraussen.de',
        'smtp_host' => 'wXXXXXXX.kasserver.com',
        'smtp_user' => 'benachrichtigung@schondraussen.de',
        'smtp_pass' => '***',
        'smtp_port' => 587,
    ],
    'cron_token' => 'langer-zufaelliger-wert',
];
