<?php
// Gemeinsame Grundfunktionen: Konfiguration, Datenbank, Sitzung, JSON-Antworten.
$GLOBALS['CFG'] = is_file(__DIR__ . '/../config.php') ? require __DIR__ . '/../config.php' : [];
function cfg(string $k, $d = null) { return $GLOBALS['CFG'][$k] ?? $d; }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg('db', []);
    $pdo = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return $pdo;
}

// Härtung: allgemeine Sicherheits-Kopfzeilen (für Seiten und API) und Herkunftsprüfung bei Schreibzugriffen.
function security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('Strict-Transport-Security: max-age=31536000');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
}
function origin_guard(): void {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($m, ['GET', 'HEAD', 'OPTIONS'], true)) return;
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'unsubscribe.php') return; // Ein-Klick-Abmeldung kommt aus Mailprogrammen ohne Herkunft
    $o = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($o === '') return; // ältere Clients ohne Herkunftsangabe; SameSite=Lax der Sitzung gilt weiterhin
    $oh = strtolower((string) parse_url($o, PHP_URL_HOST)); $hh = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($oh === '' || $oh !== $hh) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => 'Anfrage von fremder Herkunft abgelehnt.']); exit; }
}
security_headers();
origin_guard();

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    session_name('sd_session');
    session_start();
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw ?: '', true);
    return is_array($j) ? $j : [];
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $s = db()->prepare('SELECT * FROM users WHERE id=? AND email_verified_at IS NOT NULL');
    $s->execute([$_SESSION['uid']]);
    $u = $s->fetch();
    if (!$u || !empty($u['admin_deleted'])) return null;
    unset($u['pass_hash']);
    $u['totp_on'] = (int) ($u['totp_enabled'] ?? 0) === 1; unset($u['totp_secret'], $u['totp_codes'], $u['totp_step']);
    $u['deleting'] = !empty($u['delete_requested_at']);
    $u['rename'] = $u['role'] !== 'admin' && !empty($u['name_change_required']);
    $u['approved'] = !$u['deleting'] && !$u['rename'] && ($u['role'] === 'admin' || !empty($u['approved_at']));
    return $u;
}

/** Muss der Admin neue Konten freischalten? Einstellung im Adminmenü (Standard: nein). */
function approval_required(): bool {
    try { $q = db()->prepare("SELECT v FROM site_settings WHERE k='approval_required'"); $q->execute(); return $q->fetchColumn() === '1'; }
    catch (Throwable $e) { return false; }
}

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45); }
function token_new(): array { $t = bin2hex(random_bytes(24)); return [$t, hash('sha256', $t)]; }

// Alphafunktionen: sichtbar für Admins und als Alphatester markierte Nutzer.
function is_alpha(?array $u): bool { return perm($u, 'fb_staff'); } // „Ideen und Bugs: alle sehen und verwalten“ (Rechtetabelle im Admin)
function require_alpha(?array $u): void { if (!is_alpha($u)) json_out(['error' => 'Diese Funktion ist noch nicht freigegeben.'], 403); }

/** Minuten, in denen die erste Namenswahl einmal ohne 30-Tage-Sperre korrigiert werden darf (0 = nicht mehr möglich). */
function username_free_minutes(PDO $pdo, int $uid): int {
    try {
        $s = $pdo->prepare('SELECT old_name, TIMESTAMPDIFF(MINUTE, changed_at, NOW()) AS age FROM username_log WHERE user_id=? ORDER BY id'); $s->execute([$uid]); $r = $s->fetchAll(PDO::FETCH_ASSOC);
        if (count($r) === 1 && ($r[0]['old_name'] === null || $r[0]['old_name'] === '') && (int) $r[0]['age'] < 60) return 60 - (int) $r[0]['age'];
    } catch (Throwable $e) {}
    return 0;
}

/** Prüft einen Benutzernamen; gibt eine Fehlermeldung oder null zurück. */
function username_error(string $n): ?string {
    if (mb_strlen($n) < 3 || mb_strlen($n) > 20) return 'Der Benutzername braucht 3 bis 20 Zeichen.';
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_.\-]*[\p{L}\p{N}]$/u', $n)) return 'Erlaubt sind Buchstaben, Ziffern, Punkt, Unterstrich und Bindestrich, ohne Leerzeichen und nicht am Anfang oder Ende.';
    require_once __DIR__ . '/namefilter.php';
    if ($e = name_policy_error($n)) return $e;
    return null;
}
require_once __DIR__ . '/privacy.php';
require_once __DIR__ . '/totp.php';

// Podcasts: Sichtbarkeit stufenweise. 'admin' (Standard) = nur Administratoren, 'all' = alle Nutzer. Einstellung site_settings.podcast_level.
function podcast_level(): string {
    static $l = null;
    if ($l === null) { try { $q = db()->prepare("SELECT v FROM site_settings WHERE k='podcast_level'"); $q->execute(); $v = $q->fetchColumn(); $l = $v === 'all' ? 'all' : 'admin'; } catch (Throwable $e) { $l = 'admin'; } }
    return $l;
}
function podcast_ok(?array $u): bool { return perm($u, 'podcast'); }
/** Darf der Nutzer diese Kategorie sehen und nutzen? (Rechte der Nutzergruppen, Admin immer) */
function cat_ok(?array $u, string $t): bool { return $t === 'podcast' ? podcast_ok($u) : perm($u, 'cat_' . $t); }
function allowed_cats(?array $u): array { return array_values(array_filter(['author', 'artist', 'film', 'series', 'podcast'], fn($t) => cat_ok($u, $t))); }
// Kategorien ausblenden: vorerst nur für Admins (Test). Gespeichert in users.hide_cats als kommagetrennte Liste.
function hidecats_ok(?array $u): bool { return perm($u, 'hidecats'); }
function hidden_cats(?array $u): array {
    if (!hidecats_ok($u)) return [];
    return array_values(array_intersect(explode(',', (string) ($u['hide_cats'] ?? '')), ['author', 'artist', 'film', 'series', 'podcast']));
}

// ---------- Rechte der Nutzergruppen (Admin → Sicherheit): Gruppen user, alpha (Alphatester), admin (immer alles) ----------
function perm_defs(): array {
    $pl = podcast_level() === 'all' ? 1 : 0; // bisherige Einstellung bleibt als Ausgangswert
    return [
        'fb_staff' => ['Ideen und Bugs: alle Beiträge sehen und verwalten', 'Auch noch nicht freigegebene Beiträge mit Absender sehen, freigeben, Status setzen, bearbeiten, Bilder prüfen', [0, 1], 'live'],
        'fb_comment_view' => ['Kommentare zu Ideen und Bugs lesen', 'Nur Admin und Alphatester sehen sie (Standard)', [0, 1], 'live'],
        'fb_comment_write' => ['Kommentare zu Ideen und Bugs schreiben', 'Interne Kommentare unter einem Beitrag hinterlassen', [0, 1], 'live'],
        'cat_author' => ['Kategorie Bücher / Autor:innen', 'Suchen, hinzufügen, in Menü, Kalender und Community sehen. Aus: Kategorie verschwindet für die Gruppe, Einträge bleiben gespeichert', [1, 1], 'live'],
        'cat_artist' => ['Kategorie Musik', 'Suchen, hinzufügen, in Menü, Kalender und Community sehen. Aus: Kategorie verschwindet für die Gruppe, Einträge bleiben gespeichert', [1, 1], 'live'],
        'cat_film' => ['Kategorie Filme', 'Suchen, hinzufügen, in Menü, Kalender und Community sehen. Aus: Kategorie verschwindet für die Gruppe, Einträge bleiben gespeichert', [1, 1], 'live'],
        'cat_series' => ['Kategorie Serien', 'Suchen, hinzufügen, in Menü, Kalender und Community sehen. Aus: Kategorie verschwindet für die Gruppe, Einträge bleiben gespeichert', [1, 1], 'live'],
        'podcast' => ['Kategorie Podcasts', 'Suchen, hinzufügen, in Menü, Kalender und Community sehen. Aus: Kategorie verschwindet für die Gruppe, Einträge bleiben gespeichert', [$pl, $pl], 'live'],
        'news' => ['Seite „News“ ansehen', 'Neuigkeiten zur Seite und zu Inhalten lesen (derzeit nur in der Entwicklungsversion sichtbar)', [1, 1], 'live'],
        'hidecats' => ['Kategorien ausblenden', 'Option in den Einstellungen (Test)', [0, 0], 'live'],
        'devmode' => ['Entwicklerversion nutzen', 'Schalter „Live/Dev“ neben dem Benutzernamen: neue Funktionen testen, bevor sie für alle freigegeben werden', [0, 1], 'live'],
    ];
}
function perm_stored(): array {
    static $st = null;
    if ($st === null) { $st = []; try { $q = db()->prepare("SELECT v FROM site_settings WHERE k='perms'"); $q->execute(); $j = json_decode((string) $q->fetchColumn(), true); if (is_array($j)) $st = $j; } catch (Throwable $e) {} }
    return $st;
}
/** Wirksame Werte je Recht: ['user' => bool, 'alpha' => bool]. Alphatester haben mindestens die Rechte der Nutzer. */
function perm_values(): array {
    $st = perm_stored(); $o = [];
    foreach (perm_defs() as $k => $d) {
        $u = isset($st[$k]['user']) ? (bool) $st[$k]['user'] : (bool) $d[2][0];
        $a = isset($st[$k]['alpha']) ? (bool) $st[$k]['alpha'] : (bool) $d[2][1];
        $o[$k] = ['user' => $u, 'alpha' => $a || $u];
    }
    return $o;
}
function perm(?array $u, string $k): bool {
    if (!$u) return false;
    if (($u['role'] ?? '') === 'admin') return true;
    $v = perm_values(); return !empty($v[$k][!empty($u['alpha']) ? 'alpha' : 'user']);
}
/** Kurze Liste der Bereiche, in denen die Entwicklungsversion von der Live-Version abweicht (public/dev/changes.json, Texte wie „Serien → Seite einer einzelnen Serie“). */
/** Design-Varianten zum Testen: public/designs/index.json + je Variante ein Ordner designs/<kennung>/ mit app.js und app.css. */
function design_list(): array {
    $f = __DIR__ . '/../designs/index.json'; if (!is_file($f)) return [];
    $j = json_decode((string) @file_get_contents($f), true); if (!is_array($j)) return [];
    $out = [];
    foreach ($j as $x) {
        $slug = is_array($x) ? (string) ($x['slug'] ?? '') : '';
        if (!preg_match('/^[a-z0-9-]{1,30}$/', $slug) || !is_file(__DIR__ . "/../designs/$slug/app.js") || !is_file(__DIR__ . "/../designs/$slug/app.css")) continue;
        $out[] = ['slug' => $slug, 'name' => mb_substr(trim((string) ($x['name'] ?? $slug)), 0, 40), 'desc' => mb_substr(trim((string) ($x['desc'] ?? '')), 0, 200), 'public' => !empty($x['public'])];
    }
    return $out;
}
// Für alle Nutzer wählbare (experimentelle) Themes: Einträge in designs/index.json mit "public": true
function design_public(): array { return array_values(array_filter(design_list(), function ($d) { return !empty($d['public']); })); }
function dev_notes(): array {
    $f = __DIR__ . '/../dev/changes.json'; if (!is_file($f)) return [];
    $j = json_decode((string) @file_get_contents($f), true); if (!is_array($j)) return [];
    return array_values(array_filter(array_map(fn($x) => is_string($x) ? mb_substr(trim($x), 0, 120) : '', $j), fn($x) => $x !== ''));
}
