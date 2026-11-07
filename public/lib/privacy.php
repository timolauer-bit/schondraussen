<?php
// Anzeige-Helfer für Datenschutz-Einstellungen der Nutzer (E-Mail und Aktivität ausblenden)
function user_label(array $u, bool $withEmail = true): string {
    $n = !empty($u['username']) ? $u['username'] : 'Mitglied Nr. ' . (int) $u['id'];
    if ($withEmail && empty($u['hide_email']) && !empty($u['email'])) $n .= ' (' . $u['email'] . ')';
    return $n;
}
function seen_bucket(?string $ts): string {
    if (!$ts) return 'noch nie';
    $d = (time() - strtotime($ts)) / 86400;
    if ($d <= 7) return 'in den letzten 7 Tagen';
    if ($d <= 30) return 'vor über einer Woche';
    if ($d <= 90) return 'vor über einem Monat';
    return 'vor über 3 Monaten';
}
function users_have_privacy(PDO $pdo): bool { // false, solange migrate.php (022) noch nicht lief
    static $has = null;
    if ($has === null) { try { $has = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'hide_email'")->fetch(); } catch (Throwable $e) { $has = false; } }
    return $has;
}

/** Konto endgültig löschen. Ideen und Bugmeldungen bleiben erhalten (Name wird als gelöscht gemerkt), Chat-Bilder werden entfernt. */
function delete_user_keep_feedback(PDO $pdo, int $uid): void {
    try {
        $pdo->prepare("UPDATE feedback SET author_gone = (SELECT COALESCE(NULLIF(username,''), CONCAT('Mitglied Nr. ', id)) FROM users WHERE id=?) WHERE user_id=?")->execute([$uid, $uid]);
        $pdo->prepare("DELETE FROM images WHERE user_id=? AND scope='chat'")->execute([$uid]);
    } catch (Throwable $e) {}
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
}
