<?php
// Einblick für Gäste: Daten des Beispielnutzers (nur lesen). Die Oberfläche (app.js im Einblick-Modus) holt hier Liste, Kalender, Serienseiten und Community.
require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/showcase.php';
$u = null; try { $u = current_user(); } catch (Throwable $e) {}
if (!showcase_viewer_ok($u)) json_out(['error' => 'Nicht gefunden.'], 404);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => 'Im Einblick ist das nicht möglich.', 'demo' => true], 405);
$pdo = db(); $a = $_GET['a'] ?? ''; $snap = showcase_get($pdo);
try {
    if ($a === 'list') {
        $site = ['tag' => '', 'domain' => 'amazon.de'];
        try { foreach ($pdo->query("SELECT k,v FROM site_settings WHERE k IN ('tag','domain')")->fetchAll() as $r) $site[$r['k']] = $r['v']; } catch (Throwable $e) {}
        json_out(['entries' => showcase_list($pdo, $snap, (int) ($_GET['x'] ?? 0)), 'site' => $site]);
    }
    if ($a === 'calendar') json_out(['releases' => showcase_calendar($pdo, $snap)]);
    if ($a === 'entry') { $r = showcase_entry($pdo, $snap, (int) ($_GET['id'] ?? 0)); if (!$r) json_out(['error' => 'Eintrag nicht gefunden.'], 404); json_out($r); }
    if ($a === 'episum') json_out(['text' => showcase_episum($pdo, $snap, (int) ($_GET['id'] ?? 0), (int) ($_GET['s'] ?? 0), (int) ($_GET['n'] ?? 0))]);
    if ($a === 'community') { if (empty($snap['comm'])) json_out(['error' => 'Die Community-Übersicht ist gerade nicht verfügbar.']); json_out($snap['comm']); }
} catch (Throwable $e) { json_out(['error' => 'Das hat gerade nicht geklappt.'], 500); }
json_out(['error' => 'Unbekannte Aktion'], 400);
