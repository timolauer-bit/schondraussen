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
    exit_log($pdo, $uid, true); // falls noch nicht erfasst (Konto zählt dann als vom Admin entfernt)
    try {
        $pdo->prepare("UPDATE feedback SET author_gone = (SELECT COALESCE(NULLIF(username,''), CONCAT('Mitglied Nr. ', id)) FROM users WHERE id=?) WHERE user_id=?")->execute([$uid, $uid]);
        $pdo->prepare("DELETE FROM images WHERE user_id=? AND scope='chat'")->execute([$uid]);
    } catch (Throwable $e) {}
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
    try { $pdo->prepare('UPDATE account_exits SET user_id=NULL WHERE user_id=?')->execute([$uid]); } catch (Throwable $e) {} // ab hier ist der Eintrag anonym
}

/** Vom Admin gelöscht: Konto sofort gesperrt, Daten (z. B. Chatverlauf) bleiben noch 7 Tage lesbar, danach löscht der Cron endgültig. */
function soft_delete_user(PDO $pdo, int $uid): void {
    exit_log($pdo, $uid, true);
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


/** Gründe, die beim Löschen des Kontos zur Auswahl stehen (freiwillig). */
function exit_reasons(): array {
    return [
        'no_need' => 'Ich brauche die Seite nicht mehr',
        'test_only' => 'Ich wollte nur mal reinschauen',
        'missing_feature' => 'Mir fehlen Funktionen',
        'missing_content' => 'Ich finde nicht, was ich suche (Titel, Künstler, Autoren)',
        'bugs' => 'Fehler oder unzuverlässige Daten',
        'complicated' => 'Zu kompliziert oder unübersichtlich',
        'other_tool' => 'Ich nutze ein anderes Angebot',
        'mails' => 'Zu viele oder unpassende Mails',
        'privacy' => 'Bedenken beim Datenschutz',
        'other' => 'Etwas anderes',
    ];
}
/** Anonymes Löschprotokoll: nur Tage, Dauer und freiwilliger Grund. Die Nutzer-ID bleibt bis zur endgültigen Löschung (damit ein Abbruch den Eintrag entfernen kann), danach wird sie geleert. Gibt die Kontodauer in Tagen zurück oder null, wenn nichts eingetragen wurde. */
function exit_log(PDO $pdo, int $uid, bool $byAdmin, ?string $reason = null, ?string $text = null): ?int {
    try {
        $q = $pdo->prepare('SELECT 1 FROM account_exits WHERE user_id=? LIMIT 1'); $q->execute([$uid]); if ($q->fetchColumn()) return null;
        $r = $pdo->prepare("SELECT DATE(created_at) FROM users WHERE id=? AND role<>'admin'"); $r->execute([$uid]); $c = $r->fetchColumn(); if (!$c) return null;
        $days = max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime((string) $c)) / 86400));
        $pdo->prepare('INSERT INTO account_exits (user_id,registered_on,exited_on,days_active,by_admin,reason,reason_text) VALUES (?,?,CURDATE(),?,?,?,?)')->execute([$uid, $c, $days, $byAdmin ? 1 : 0, $reason, $text]);
        return $days;
    } catch (Throwable $e) { return null; }
}
function exit_cancel(PDO $pdo, int $uid): void {
    try { $pdo->prepare('DELETE FROM account_exits WHERE user_id=? AND by_admin=0')->execute([$uid]); } catch (Throwable $e) {}
}
