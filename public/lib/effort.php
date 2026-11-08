<?php
// "Entstehung und Aufwand" (Info-Seite): wöchentlich gemessene Umfangszahlen. Die Schätzungen werden im Browser daraus hochgerechnet.
require_once __DIR__ . '/watchdog.php';

function effort_measure(): array {
    $root = realpath(__DIR__ . '/..'); $lines = 0; $files = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname()); $rel = substr($p, strlen($root) + 1);
        if (!preg_match('/\.(php|js|css)$/', $rel) || preg_match('#^(vendor|shots|appicons)/#', $rel) || $rel === 'sw.js' || $rel === 'config.php') continue;
        $c = @file($p); if ($c === false) continue; $lines += count($c); $files++;
    }
    $api = count(glob($root . '/api/*.php') ?: []);
    $cl = (string) @file_get_contents($root . '/changelog.js');
    return ['lines' => $lines, 'files' => $files, 'api' => $api, 'versions' => preg_match_all('/^\s*\{ v:/m', $cl), 'items' => preg_match_all("/^\s*'[FOUBADT]\|/m", $cl), 'd' => date('Y-m-d')];
}

/** Sonntags zwischen 5 und 12 Uhr (Berlin) einmal pro Kalenderwoche messen und speichern. */
function effort_weekly(PDO $pdo, bool $force = false): string {
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    $week = $now->format('o-W');
    $cur = state_get($pdo, 'effort_week');
    if (!$force) {
        if ($now->format('w') !== '0' || (int) $now->format('G') < 5 || (int) $now->format('G') >= 12) return 'nicht fällig';
        if ($cur && $cur['v'] === $week) return 'diese Woche schon aktualisiert';
    }
    $m = effort_measure();
    state_set($pdo, 'effort', json_encode($m));
    state_set($pdo, 'effort_week', $week);
    return 'aktualisiert (' . $m['lines'] . ' Zeilen)';
}
