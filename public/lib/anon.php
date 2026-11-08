<?php
// Konten ohne E-Mail-Adresse: Anmeldung per Benutzername und Passwort, Wiederherstellung per Code.
function anon_enabled(PDO $pdo): bool {
    try { $q = $pdo->prepare("SELECT v FROM site_settings WHERE k='anon_signup'"); $q->execute(); $v = $q->fetchColumn(); return $v === false ? true : $v === '1'; }
    catch (Throwable $e) { return false; }
}
/** Neuer Wiederherstellungscode im Format XXXX-XXXX-XXXX-XXXX (ohne verwechselbare Zeichen). */
function recovery_code_new(): string {
    $al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $out = [];
    for ($g = 0; $g < 4; $g++) { $s = ''; for ($i = 0; $i < 4; $i++) $s .= $al[random_int(0, strlen($al) - 1)]; $out[] = $s; }
    return implode('-', $out);
}
function recovery_norm(string $c): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c)); }
/** Meldung an den Admin (erscheint im Kontakt-Bereich unter „System“); gleiche Art höchstens einmal je $throttleMin Minuten. */
function admin_notice(PDO $pdo, string $kind, string $body, int $throttleMin = 0): void {
    try {
        if ($throttleMin > 0) { $q = $pdo->prepare('SELECT 1 FROM admin_notices WHERE kind=? AND created_at > NOW() - INTERVAL ? MINUTE LIMIT 1'); $q->execute([$kind, $throttleMin]); if ($q->fetchColumn()) return; }
        $pdo->prepare('INSERT INTO admin_notices (kind,body) VALUES (?,?)')->execute([$kind, mb_substr($body, 0, 500)]);
        $pdo->exec('DELETE FROM admin_notices WHERE created_at < NOW() - INTERVAL 90 DAY');
    } catch (Throwable $e) {}
}
