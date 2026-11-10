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
function is_alpha(?array $u): bool { return $u && ($u['role'] === 'admin' || !empty($u['alpha'])); }
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
function podcast_ok(?array $u): bool { return $u && (podcast_level() === 'all' || ($u['role'] ?? '') === 'admin'); }
// Kategorien ausblenden: vorerst nur für Admins (Test). Gespeichert in users.hide_cats als kommagetrennte Liste.
function hidecats_ok(?array $u): bool { return $u && ($u['role'] ?? '') === 'admin'; }
function hidden_cats(?array $u): array {
    if (!hidecats_ok($u)) return [];
    return array_values(array_intersect(explode(',', (string) ($u['hide_cats'] ?? '')), ['author', 'artist', 'film', 'series', 'podcast']));
}
