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
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
    if (!$u) return null;
    unset($u['pass_hash']);
    $u['deleting'] = !empty($u['delete_requested_at']);
    $u['approved'] = !$u['deleting'] && ($u['role'] === 'admin' || !empty($u['approved_at']));
    return $u;
}

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45); }
function token_new(): array { $t = bin2hex(random_bytes(24)); return [$t, hash('sha256', $t)]; }

// Alphafunktionen: sichtbar für Admins und als Alphatester markierte Nutzer.
function is_alpha(?array $u): bool { return $u && ($u['role'] === 'admin' || !empty($u['alpha'])); }
function require_alpha(?array $u): void { if (!is_alpha($u)) json_out(['error' => 'Diese Funktion ist noch nicht freigegeben.'], 403); }

/** Prüft einen Benutzernamen; gibt eine Fehlermeldung oder null zurück. */
function username_error(string $n): ?string {
    if (mb_strlen($n) < 3 || mb_strlen($n) > 20) return 'Der Benutzername braucht 3 bis 20 Zeichen.';
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_.\-]*[\p{L}\p{N}]$/u', $n)) return 'Erlaubt sind Buchstaben, Ziffern, Punkt, Unterstrich und Bindestrich, ohne Leerzeichen und nicht am Anfang oder Ende.';
    $low = mb_strtolower($n);
    foreach (['admin', 'administrator', 'schondraussen', 'support', 'moderator', 'system', 'team', 'info'] as $r) if ($low === $r) return 'Dieser Name ist reserviert.';
    return null;
}
