<?php
// Anzeige-Helfer für Datenschutz-Einstellungen der Nutzer (E-Mail und Aktivität ausblenden)
function user_label(array $u, bool $withEmail = true): string {
    $n = !empty($u['username']) ? $u['username'] : 'Mitglied Nr. ' . (int) $u['id'];
    // E-Mail-Adressen erscheinen nirgends mehr in Mails oder Anzeigen an den Admin
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

/** Vom Admin gelöscht: Konto sofort gesperrt, Daten (z. B. Chatverlauf) bleiben noch 7 Tage lesbar, danach löscht der Cron endgültig. */
function soft_delete_user(PDO $pdo, int $uid): void {
    try { $pdo->prepare("UPDATE users SET delete_requested_at=COALESCE(delete_requested_at,NOW()), admin_deleted=1, approved_at=NULL, notify=0 WHERE id=? AND role<>'admin'")->execute([$uid]); }
    catch (Throwable $e) { delete_user_keep_feedback($pdo, $uid); }
}

/** Abschiedszeile: Dauer des Kontos und Zahl der Einträge je Kategorie. */
function farewell_line(PDO $pdo, int $uid): string {
    $r = $pdo->prepare('SELECT created_at FROM users WHERE id=?'); $r->execute([$uid]); $c = $r->fetchColumn();
    $days = $c ? max(0, (int) floor((time() - strtotime((string) $c)) / 86400)) : 0;
    $pl = fn($n, $a, $b) => $n . ' ' . ($n === 1 ? $a : $b);
    if ($days < 1) $dur = 'weniger als einen Tag';
    elseif ($days < 60) $dur = $pl($days, 'Tag', 'Tage');
    elseif ($days < 365) { $m = intdiv($days, 30); $d = $days - $m * 30; $dur = $pl($m, 'Monat', 'Monate') . ($d ? ' und ' . $pl($d, 'Tag', 'Tage') : ''); }
    else { $y = intdiv($days, 365); $m = intdiv($days - $y * 365, 30); $dur = $pl($y, 'Jahr', 'Jahre') . ($m ? ' und ' . $pl($m, 'Monat', 'Monate') : ''); }
    $cnt = ['artist' => 0, 'author' => 0, 'film' => 0, 'series' => 0];
    try { $q = $pdo->prepare('SELECT s.type, COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? GROUP BY s.type'); $q->execute([$uid]); foreach ($q->fetchAll(PDO::FETCH_NUM) as $x) $cnt[$x[0]] = (int) $x[1]; } catch (Throwable $e) {}
    $parts = [];
    if ($cnt['artist']) $parts[] = $cnt['artist'] . ' Musiker:in' . ($cnt['artist'] === 1 ? '' : 'nen');
    if ($cnt['author']) $parts[] = $cnt['author'] . ' Autor:in' . ($cnt['author'] === 1 ? '' : 'nen');
    if ($cnt['film']) $parts[] = $pl($cnt['film'], 'Film', 'Filme');
    if ($cnt['series']) $parts[] = $pl($cnt['series'], 'Serie', 'Serien');
    $list = $parts ? implode(', ', array_slice($parts, 0, -1)) . (count($parts) > 1 ? ' und ' : '') . end($parts) : '';
    return 'Schade, dass du uns verlässt. Du warst ' . $dur . ' dabei' . ($list ? ' und hattest ' . $list . ' auf deiner Liste.' : '.');
}
