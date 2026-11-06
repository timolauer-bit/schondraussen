<?php
// Zwei-Faktor-Anmeldung (TOTP nach RFC 6238, 6 Stellen, 30 Sekunden) ohne externe Bibliothek.
function b32_encode(string $bin): string { $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($bits, 5) as $ch) $o .= $a[bindec(str_pad($ch, 5, '0'))]; return $o; }
function b32_decode(string $s): string { $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $o .= chr(bindec($b)); return $o; }
function totp_new_secret(): string { return b32_encode(random_bytes(20)); }
function totp_code(string $secret, int $step): string {
    $h = hash_hmac('sha1', pack('J', $step), b32_decode($secret), true); $o = ord($h[19]) & 15;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Prüft den Code (±1 Schritt Toleranz); gibt den benutzten Schritt zurück oder null. */
function totp_check(string $secret, string $code, int $minStep = 0): ?int {
    $code = preg_replace('/\D/', '', $code); if (strlen($code) !== 6) return null;
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) { $st = $now + $d; if ($st > $minStep && hash_equals(totp_code($secret, $st), $code)) return $st; }
    return null;
}
function totp_backup_codes(): array { $c = []; for ($i = 0; $i < 8; $i++) $c[] = strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))); return $c; }
function totp_hash_codes(array $c): string { return json_encode(array_map(fn($x) => hash('sha256', $x), $c)); }
/** Prüft Authentifizierungs- oder Backup-Code für den Nutzer; verbraucht Schritt bzw. Backup-Code. */
function totp_verify_user(PDO $pdo, array $row, string $input): bool {
    $input = trim($input);
    if (preg_match('/^[A-Fa-f0-9]{4}-?[A-Fa-f0-9]{4}$/', $input) && !preg_match('/^\d{6}$/', $input)) {
        $norm = strtoupper(substr($input, 0, 4)) . '-' . strtoupper(substr($input, -4)); $list = json_decode((string) $row['totp_codes'], true) ?: [];
        $h = hash('sha256', $norm); $i = array_search($h, $list, true);
        if ($i === false) return false; unset($list[$i]);
        $pdo->prepare('UPDATE users SET totp_codes=? WHERE id=?')->execute([json_encode(array_values($list)), $row['id']]); return true;
    }
    $st = totp_check((string) $row['totp_secret'], $input, (int) $row['totp_step']); if ($st === null) return false;
    $pdo->prepare('UPDATE users SET totp_step=? WHERE id=?')->execute([$st, $row['id']]); return true;
}
