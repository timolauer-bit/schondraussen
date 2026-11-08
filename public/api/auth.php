<?php
function email_blocked(PDO $pdo, string $email): bool { try { $s = $pdo->prepare('SELECT 1 FROM blocked_emails WHERE email=?'); $s->execute([$email]); return (bool) $s->fetchColumn(); } catch (Throwable $e) { return false; } }
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/anon.php';
start_session();
$a = $_GET['a'] ?? '';
$pdo = db();

function limited(PDO $pdo, string $kind, int $max, int $minutes): bool {
    $s = $pdo->prepare('SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND kind=? AND at > (NOW() - INTERVAL ? MINUTE)');
    $s->execute([client_ip(), $kind, $minutes]);
    return (int) $s->fetch()['c'] >= $max;
}
function note(PDO $pdo, string $kind): void { $pdo->prepare('INSERT INTO login_attempts (ip,kind) VALUES (?,?)')->execute([client_ip(), $kind]); }
function valid_email(string $e): bool { return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL); }
function link_mail(PDO $pdo, int $uid, string $email, string $kind): void {
    [$t, $h] = token_new();
    $mins = $kind === 'verify' ? 60 * 24 : 60;
    $pdo->prepare('INSERT INTO email_tokens (user_id,kind,token_hash,expires_at) VALUES (?,?,?,NOW() + INTERVAL ? MINUTE)')->execute([$uid, $kind, $h, $mins]);
    $base = rtrim(cfg('base_url', 'https://schondraussen.de'), '/');
    if ($kind === 'verify') {
        send_mail($email, 'Bitte bestätige deine E-Mail-Adresse',
            "Willkommen bei Schondraussen.de!\n\nBitte bestätige deine Adresse mit diesem Link (24 Stunden gültig):\n$base/api/auth.php?a=verify&t=$t\n\nWenn du dich nicht angemeldet hast, kannst du diese Mail ignorieren.\n");
    } else {
        send_mail($email, 'Passwort zurücksetzen',
            "Du hast ein neues Passwort angefordert. Der Link ist eine Stunde gültig:\n$base/?reset=$t\n\nWenn du das nicht warst, kannst du diese Mail ignorieren.\n");
    }
}

try {
    // Bestätigungslink aus der Mail (GET)
    if ($a === 'verify') {
        $h = hash('sha256', (string) ($_GET['t'] ?? ''));
        $s = $pdo->prepare("SELECT id,user_id FROM email_tokens WHERE token_hash=? AND kind='verify' AND used_at IS NULL AND expires_at > NOW()");
        $s->execute([$h]); $r = $s->fetch();
        if ($r) {
            $pdo->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=? AND email_verified_at IS NULL')->execute([$r['user_id']]);
            $pdo->prepare('UPDATE email_tokens SET used_at=NOW() WHERE id=?')->execute([$r['id']]);
            header('Location: /?verified=1');
        } else { header('Location: /?verified=0'); }
        exit;
    }

    if ($a === 'me') { $u = current_user(); json_out(['user' => $u]); }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST erforderlich'], 405);
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) json_out(['error' => 'Ungültige Anfrage'], 400);
    $b = body();

    if ($a === 'register' && !empty($b['anon'])) { // Konto ohne E-Mail-Adresse
        if (!anon_enabled($pdo)) json_out(['error' => 'Die Anmeldung ohne E-Mail-Adresse ist gerade nicht möglich. Bitte registriere dich mit einer E-Mail-Adresse.'], 403);
        if (limited($pdo, 'register', 5, 60)) json_out(['error' => 'Zu viele Versuche, bitte später erneut.'], 429);
        note($pdo, 'register');
        $pw = (string) ($b['password'] ?? '');
        if (strlen($pw) < 10) json_out(['error' => 'Das Passwort braucht mindestens 10 Zeichen.'], 422); if (strlen($pw) > 72) json_out(['error' => 'Das Passwort darf höchstens 72 Zeichen haben.'], 422);
        if (empty($b['accept'])) json_out(['error' => 'Bitte Datenschutzerklärung und Nutzungsbedingungen bestätigen.'], 422);
        $uname = trim((string) ($b['username'] ?? ''));
        if ($err = username_error($uname)) json_out(['error' => $err], 422);
        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) json_out(['error' => 'Das erste Konto braucht eine E-Mail-Adresse.'], 422);
        $t = $pdo->prepare('SELECT 1 FROM users WHERE username=?'); $t->execute([$uname]);
        if ($t->fetch()) json_out(['error' => 'Dieser Benutzername ist schon vergeben.'], 409);
        // Grenzen: ein Konto je Anschluss und 24 Stunden, insgesamt höchstens 5 pro Stunde
        if (limited($pdo, 'anon', 1, 1440)) json_out(['error' => 'Von deinem Anschluss aus wurde in den letzten 24 Stunden schon ein Konto ohne E-Mail-Adresse angelegt. Bitte melde dich damit an oder registriere dich mit einer E-Mail-Adresse.'], 429);
        $g = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE kind='anon_all' AND at > (NOW() - INTERVAL 60 MINUTE)")->fetchColumn();
        if ((int) $g >= 5) {
            admin_notice($pdo, 'anon_limit', 'Das Limit für Konten ohne E-Mail-Adresse ist erreicht (5 pro Stunde). Weitere Anmeldungen ohne Adresse werden bis zur vollen Stunde abgelehnt. Ungewöhnlich viele Anmeldungen können auf Missbrauch hindeuten; prüfe die Nutzerverwaltung oder schalte die Option unter Admin → Registrierung ab.', 60);
            json_out(['error' => 'Heute sind schon viele Konten ohne E-Mail-Adresse angelegt worden. Bitte versuche es in einer Stunde noch einmal oder registriere dich mit einer E-Mail-Adresse.', 'limit' => true], 429);
        }
        $code = recovery_code_new();
        try {
            $pdo->prepare('INSERT INTO users (email,pass_hash,role,username,username_changed_at,email_verified_at,recovery_hash) VALUES (NULL,?,?,?,NOW(),NOW(),?)')->execute([password_hash($pw, PASSWORD_DEFAULT), 'user', $uname, password_hash(recovery_norm($code), PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'username') !== false && stripos($e->getMessage(), 'Unknown column') === false) json_out(['error' => 'Dieser Benutzername ist schon vergeben.'], 409);
            json_out(['error' => 'Das hat nicht geklappt. Ist die Datenbank aktualisiert (migrate.php)?'], 500);
        }
        $nid = (int) $pdo->lastInsertId();
        if (!approval_required()) { try { $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=?')->execute([$nid]); } catch (Throwable $e) {} }
        try { $pdo->prepare('UPDATE users SET hide_email=1, hide_activity=1 WHERE id=?')->execute([$nid]); } catch (Throwable $e) {}
        note($pdo, 'anon'); note($pdo, 'anon_all');
        admin_notice($pdo, 'anon_new', 'Neues Konto ohne E-Mail-Adresse: ' . $uname . (approval_required() ? ' (wartet auf deine Freischaltung)' : ''));
        json_out(['ok' => true, 'recovery_code' => $code, 'message' => approval_required() ? 'Dein Konto ist angelegt und wartet auf die Freischaltung durch den Admin.' : 'Dein Konto ist angelegt. Du kannst dich jetzt mit Benutzername und Passwort anmelden.']);
    }

    if ($a === 'recover') { // Passwort mit Wiederherstellungscode zurücksetzen
        if (limited($pdo, 'recover', 5, 60)) json_out(['error' => 'Zu viele Versuche, bitte später erneut.'], 429);
        $uname = trim((string) ($b['username'] ?? '')); $pw = (string) ($b['password'] ?? '');
        if (strlen($pw) < 10) json_out(['error' => 'Das Passwort braucht mindestens 10 Zeichen.'], 422); if (strlen($pw) > 72) json_out(['error' => 'Das Passwort darf höchstens 72 Zeichen haben.'], 422);
        $s = $pdo->prepare('SELECT id,recovery_hash FROM users WHERE username=? AND email_verified_at IS NOT NULL'); $s->execute([$uname]); $row = $s->fetch();
        if (!$row || empty($row['recovery_hash']) || !password_verify(recovery_norm((string) ($b['code'] ?? '')), $row['recovery_hash'])) { note($pdo, 'recover'); json_out(['error' => 'Benutzername oder Wiederherstellungscode stimmt nicht.'], 401); }
        $code = recovery_code_new();
        $pdo->prepare('UPDATE users SET pass_hash=?, recovery_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), password_hash(recovery_norm($code), PASSWORD_DEFAULT), $row['id']]);
        json_out(['ok' => true, 'recovery_code' => $code, 'message' => 'Dein Passwort wurde geändert. Der alte Code ist verbraucht, bitte notiere den neuen.']);
    }
    if ($a === 'recovery_new') { // neuen Code erzeugen (angemeldet, mit Passwort)
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte melde dich an.'], 401);
        if (limited($pdo, 'login', 8, 15)) json_out(['error' => 'Zu viele Versuche, bitte in einigen Minuten erneut.'], 429);
        $s = $pdo->prepare('SELECT pass_hash FROM users WHERE id=?'); $s->execute([$cu['id']]); $row = $s->fetch();
        if (!$row || !password_verify((string) ($b['password'] ?? ''), $row['pass_hash'])) { note($pdo, 'login'); json_out(['error' => 'Das Passwort stimmt nicht.'], 403); }
        $code = recovery_code_new();
        try { $pdo->prepare('UPDATE users SET recovery_hash=? WHERE id=?')->execute([password_hash(recovery_norm($code), PASSWORD_DEFAULT), $cu['id']]); }
        catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
        json_out(['ok' => true, 'recovery_code' => $code]);
    }

    if ($a === 'register') {
        if (limited($pdo, 'register', 5, 60)) json_out(['error' => 'Zu viele Versuche, bitte später erneut.'], 429);
        note($pdo, 'register');
        $email = strtolower(trim((string) ($b['email'] ?? ''))); $pw = (string) ($b['password'] ?? '');
        if (!valid_email($email)) json_out(['error' => 'Bitte eine gültige E-Mail-Adresse eingeben.'], 422);
        if (strlen($pw) < 10) json_out(['error' => 'Das Passwort braucht mindestens 10 Zeichen.'], 422); if (strlen($pw) > 72) json_out(['error' => 'Das Passwort darf höchstens 72 Zeichen haben.'], 422);
        if (empty($b['accept'])) json_out(['error' => 'Bitte Datenschutzerklärung und Nutzungsbedingungen bestätigen.'], 422);
        $uname = trim((string) ($b['username'] ?? ''));
        if ($err = username_error($uname)) json_out(['error' => $err], 422);
        $inv = null; $itok = trim((string) ($b['invite'] ?? ''));
        if ($itok !== '') { // Einladungslink (einmal verwendbar)
            try { $q = $pdo->prepare('SELECT id,email FROM invites WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW()'); $q->execute([hash('sha256', $itok)]); $inv = $q->fetch() ?: null; } catch (Throwable $e) { $inv = null; }
            if (!$inv) json_out(['error' => 'Der Einladungslink ist ungültig, abgelaufen oder schon benutzt.'], 422);
            if (!empty($inv['email']) && strtolower($inv['email']) !== $email) json_out(['error' => 'Diese Einladung gilt für eine andere E-Mail-Adresse.'], 422);
        }
        $done = null;
        $s = $pdo->prepare('SELECT id,email_verified_at FROM users WHERE email=?'); $s->execute([$email]); $ex = $s->fetch();
        if ($ex) {
            // Keine Auskunft, ob die Adresse schon existiert; unbestätigte Konten bekommen den Link erneut.
            if (!$ex['email_verified_at']) link_mail($pdo, (int) $ex['id'], $email, 'verify');
        } elseif (!email_blocked($pdo, $email)) {
            $first = (int) $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] === 0;
            try {
                $t = $pdo->prepare('SELECT 1 FROM users WHERE username=?'); $t->execute([$uname]);
                if ($t->fetch()) json_out(['error' => 'Dieser Benutzername ist schon vergeben.'], 409);
                $pdo->prepare('INSERT INTO users (email,pass_hash,role,username,username_changed_at) VALUES (?,?,?,?,NOW())')->execute([$email, password_hash($pw, PASSWORD_DEFAULT), $first ? 'admin' : 'user', $uname]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'username') !== false && strpos($e->getMessage(), 'Unknown column') === false) json_out(['error' => 'Dieser Benutzername ist schon vergeben.'], 409);
                $pdo->prepare('INSERT INTO users (email,pass_hash,role) VALUES (?,?,?)')->execute([$email, password_hash($pw, PASSWORD_DEFAULT), $first ? 'admin' : 'user']); // Spalte fehlt noch (migrate.php)
            }
            $nid2 = (int) $pdo->lastInsertId();
            $needApproval = approval_required();
            if (!$needApproval) { try { $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=?')->execute([$nid2]); } catch (Throwable $e) {} }
            if ($inv && !$first) {
                try {
                    $used = $pdo->prepare('UPDATE invites SET used_at=NOW(), email=NULL WHERE id=? AND used_at IS NULL'); $used->execute([$inv['id']]);
                    if ($used->rowCount()) {
                        $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=?')->execute([$nid2]);
                        if (!empty($inv['email'])) { $pdo->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=?')->execute([$nid2]); $done = 'Dein Konto ist angelegt und freigeschaltet. Du kannst dich jetzt anmelden.'; }
                        else $done = 'Fast geschafft: Du bekommst gleich eine Mail mit einem Bestätigungslink. Danach kannst du direkt loslegen, dein Konto ist schon freigeschaltet.';
                    } else $inv = null;
                } catch (Throwable $e) { $inv = null; }
            }
            if (!$inv || $done === null || strpos((string) $done, 'Bestätigungslink') !== false) link_mail($pdo, $nid2, $email, 'verify');
            if (!$first) { try { $pdo->prepare('UPDATE users SET hide_email=1, hide_activity=1 WHERE email=?')->execute([$email]); } catch (Throwable $e) {} } // neue Konten: E-Mail und Aktivität standardmäßig ausgeblendet
            if ($first) { try { $pdo->exec("UPDATE users SET approved_at=NOW() WHERE role='admin'"); } catch (Throwable $e) {} }
            elseif (!$inv && $needApproval) {
                try {
                    $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
                    $link = '';
                    try { // Ein-Klick-Freischaltung per Mail (14 Tage gültig, einmal verwendbar)
                        [$tk, $th] = token_new(); $nid = (int) $pdo->query('SELECT MAX(id) FROM users')->fetchColumn();
                        $q = $pdo->prepare('SELECT id FROM users WHERE email=?'); $q->execute([$email]); $nid = (int) $q->fetchColumn();
                        $pdo->prepare('INSERT INTO admin_tokens (user_id,token_hash,expires_at) VALUES (?,?,NOW() + INTERVAL 14 DAY)')->execute([$nid, $th]);
                        $link = "\n\nKonto direkt freischalten (Link nur für dich, 14 Tage gültig):\n$base/approve.php?t=$tk\n";
                    } catch (Throwable $e) {}
                    foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $adm)
                        send_mail($adm, 'Neues Konto auf Schondraussen.de', "Es hat sich ein neuer Nutzer registriert:\n\nBenutzername: $uname\nE-Mail: ausgeblendet (Standard bei neuen Konten; im Notfall in der Nutzerverwaltung unter „E-Mail anzeigen“)\n\nDas Konto sieht keine Inhalte, bis du es freischaltest." . $link . "\nOder in der Nutzerverwaltung:\n$base/\n\nKeine Freischaltung nötig? Dann ignoriere diese Mail einfach.\n");
                } catch (Throwable $e) {}
            }
        }
        if ($done) json_out(['ok' => true, 'message' => $done]);
        json_out(['ok' => true, 'message' => 'Fast geschafft: Du bekommst gleich eine Mail mit einem Bestätigungslink.' . (approval_required() ? ' Nach der Bestätigung schaltet der Admin dein Konto frei.' : ' Danach kannst du direkt loslegen.')]);
    }

    if ($a === 'login') {
        if (limited($pdo, 'login', 8, 15)) json_out(['error' => 'Zu viele Versuche, bitte in einigen Minuten erneut.'], 429);
        $email = strtolower(trim((string) ($b['email'] ?? ''))); $pw = (string) ($b['password'] ?? '');
        $byName = strpos($email, '@') === false; // ohne @: Benutzername (für Konten ohne E-Mail-Adresse)
        $s = $pdo->prepare('SELECT id,pass_hash,email_verified_at,admin_deleted FROM users WHERE ' . ($byName ? 'username' : 'email') . '=?'); $s->execute([$byName ? trim((string) ($b['email'] ?? '')) : $email]); $u = $s->fetch();
        $ok = $u && password_verify($pw, $u['pass_hash']);
        if (!$ok) { note($pdo, 'login'); json_out(['error' => 'E-Mail-Adresse bzw. Benutzername oder Passwort stimmt nicht.'], 401); }
        if (!empty($u['admin_deleted'])) json_out(['error' => 'Dieses Konto wurde gelöscht.'], 403);
        if (!$u['email_verified_at']) json_out(['error' => 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in der Mail.'], 403);
        try { $t = $pdo->prepare('SELECT totp_enabled FROM users WHERE id=?'); $t->execute([$u['id']]); if ((int) $t->fetchColumn() === 1) { $_SESSION['mfa_uid'] = (int) $u['id']; $_SESSION['mfa_at'] = time(); json_out(['ok' => true, 'mfa' => true]); } } catch (Throwable $e) {}
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $pdo->prepare('UPDATE users SET last_seen_at=NOW() WHERE id=?')->execute([$u['id']]);
        json_out(['ok' => true, 'user' => current_user()]);
    }

    if ($a === 'login2') {
        if (limited($pdo, 'login', 8, 15)) json_out(['error' => 'Zu viele Versuche, bitte in einigen Minuten erneut.'], 429);
        $uid = (int) ($_SESSION['mfa_uid'] ?? 0);
        if (!$uid || time() - (int) ($_SESSION['mfa_at'] ?? 0) > 600) { unset($_SESSION['mfa_uid'], $_SESSION['mfa_at']); json_out(['error' => 'Die Anmeldung ist abgelaufen, bitte noch einmal mit Passwort beginnen.', 'restart' => true], 401); }
        $s = $pdo->prepare('SELECT id,totp_secret,totp_codes,totp_step FROM users WHERE id=? AND totp_enabled=1'); $s->execute([$uid]); $row = $s->fetch();
        if (!$row || !totp_verify_user($pdo, $row, (string) ($b['code'] ?? ''))) { note($pdo, 'login'); json_out(['error' => 'Der Code stimmt nicht.'], 401); }
        unset($_SESSION['mfa_uid'], $_SESSION['mfa_at']);
        session_regenerate_id(true); $_SESSION['uid'] = $uid;
        $pdo->prepare('UPDATE users SET last_seen_at=NOW() WHERE id=?')->execute([$uid]);
        json_out(['ok' => true]);
    }
    if (in_array($a, ['totp_start', 'totp_enable', 'totp_disable', 'totp_codes'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte melde dich an.'], 401);
        try { $s = $pdo->prepare('SELECT pass_hash,totp_enabled,totp_secret,totp_codes,totp_step,id FROM users WHERE id=?'); $s->execute([$cu['id']]); $row = $s->fetch(); }
        catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
        if (limited($pdo, 'login', 8, 15)) json_out(['error' => 'Zu viele Versuche, bitte in einigen Minuten erneut.'], 429);
        $pwOk = password_verify((string) ($b['password'] ?? ''), $row['pass_hash']);
        if ($a === 'totp_start') {
            if (!$pwOk) { note($pdo, 'login'); json_out(['error' => 'Das Passwort stimmt nicht.'], 403); }
            if ((int) $row['totp_enabled'] === 1) json_out(['error' => 'Die Zwei-Faktor-Anmeldung ist schon aktiv.'], 409);
            $sec = totp_new_secret(); $_SESSION['totp_pending'] = $sec;
            $lbl = rawurlencode('Schondraussen.de:' . ($cu['username'] ?: 'Konto'));
            json_out(['ok' => true, 'secret' => trim(chunk_split($sec, 4, ' ')), 'uri' => 'otpauth://totp/' . $lbl . '?secret=' . $sec . '&issuer=Schondraussen.de&digits=6&period=30']);
        }
        if ($a === 'totp_enable') {
            $sec = (string) ($_SESSION['totp_pending'] ?? ''); if (!$sec) json_out(['error' => 'Bitte zuerst neu starten.'], 422);
            $st = totp_check($sec, (string) ($b['code'] ?? '')); if ($st === null) { note($pdo, 'login'); json_out(['error' => 'Der Code stimmt nicht. Prüfe, dass du den Eintrag aus diesem Durchgang in der App hast (bei jedem Start entsteht ein neuer Schlüssel) und die Uhrzeit deines Handys stimmt. Serverzeit: ' . date('H:i:s') . ' (Berlin).'], 422); }
            $codes = totp_backup_codes();
            $pdo->prepare('UPDATE users SET totp_secret=?, totp_enabled=1, totp_codes=?, totp_step=? WHERE id=?')->execute([$sec, totp_hash_codes($codes), $st, $cu['id']]);
            unset($_SESSION['totp_pending']); json_out(['ok' => true, 'codes' => $codes]);
        }
        if ((int) $row['totp_enabled'] !== 1) json_out(['error' => 'Die Zwei-Faktor-Anmeldung ist nicht aktiv.'], 409);
        if (!$pwOk || !totp_verify_user($pdo, $row, (string) ($b['code'] ?? ''))) { note($pdo, 'login'); json_out(['error' => 'Passwort oder Code stimmt nicht.'], 403); }
        if ($a === 'totp_disable') { $pdo->prepare('UPDATE users SET totp_enabled=0, totp_secret=NULL, totp_codes=NULL, totp_step=0 WHERE id=?')->execute([$cu['id']]); json_out(['ok' => true]); }
        $codes = totp_backup_codes(); $pdo->prepare('UPDATE users SET totp_codes=? WHERE id=?')->execute([totp_hash_codes($codes), $cu['id']]); json_out(['ok' => true, 'codes' => $codes]);
    }

    if ($a === 'privacy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte melde dich an.'], 401);
        try { $pdo->prepare('UPDATE users SET hide_activity=? WHERE id=?')->execute([!empty($b['hide_activity']) ? 1 : 0, $cu['id']]); }
        catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
        json_out(['ok' => true]);
    }
    if ($a === 'nerd' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte melde dich an.'], 401);
        try { $pdo->prepare('UPDATE users SET nerd_changes=? WHERE id=?')->execute([!empty($b['on']) ? 1 : 0, $cu['id']]); }
        catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
        json_out(['ok' => true]);
    }
    if ($a === 'community' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte melde dich an.'], 401);
        try { $pdo->prepare('UPDATE users SET community=? WHERE id=?')->execute([!empty($b['on']) ? 1 : 0, $cu['id']]); }
        catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
        json_out(['ok' => true]);
    }
    if ($a === 'username') {
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte melde dich an.'], 401);
        $n = trim((string) ($b['username'] ?? '')); if ($err = username_error($n)) json_out(['error' => $err], 422);
        if (mb_strtolower($n) === mb_strtolower((string) ($cu['username'] ?? ''))) {
            if ($n === ($cu['username'] ?? '')) json_out(['error' => 'Das ist schon dein Benutzername.'], 422);
        } else {
            $t = $pdo->prepare('SELECT 1 FROM users WHERE username=? AND id<>?'); $t->execute([$n, $cu['id']]);
            if ($t->fetch()) json_out(['error' => 'Dieser Benutzername ist schon vergeben.'], 409);
        }
        $freeMin = username_free_minutes($pdo, (int) $cu['id']);
        if (!$freeMin && !empty($cu['username']) && $cu['role'] !== 'admin' && !empty($cu['username_changed_at']) && empty($cu['name_change_required'])) {
            $left = 30 - (int) floor((time() - strtotime($cu['username_changed_at'])) / 86400);
            if ($left > 0) json_out(['error' => 'Den Benutzernamen kannst du erst in ' . $left . ($left === 1 ? ' Tag' : ' Tagen') . ' wieder ändern.'], 429);
        }
        try { $pdo->prepare('UPDATE users SET username=?, username_changed_at=NOW(), name_change_required=0 WHERE id=?')->execute([$n, $cu['id']]); try { $pdo->prepare('INSERT INTO username_log (user_id,old_name,new_name) VALUES (?,?,?)')->execute([$cu['id'], ($cu['username'] ?? '') !== '' ? $cu['username'] : null, $n]); } catch (Throwable $e) {} }
        catch (PDOException $e) { json_out(['error' => 'Das hat nicht geklappt. Ist die Datenbank aktualisiert (migrate.php)?'], 500); }
        json_out(['ok' => true, 'username' => $n, 'free_min' => empty($cu['username']) ? username_free_minutes($pdo, (int) $cu['id']) : 0]);
    }
    if ($a === 'logout') { $_SESSION = []; session_destroy(); json_out(['ok' => true]); }

    if ($a === 'delete_account' || $a === 'cancel_delete') {
        $cu = current_user(); if (!$cu) json_out(['error' => 'Bitte zuerst anmelden.'], 401);
        if ($a === 'cancel_delete') {
            $pdo->prepare('UPDATE users SET delete_requested_at=NULL WHERE id=?')->execute([$cu['id']]);
            json_out(['ok' => true]);
        }
        if ($cu['role'] === 'admin') json_out(['error' => 'Das Admin-Konto kann hier nicht gelöscht werden.'], 403);
        if (limited($pdo, 'login', 8, 15)) json_out(['error' => 'Zu viele Versuche, bitte in einigen Minuten erneut.'], 429);
        $s = $pdo->prepare('SELECT pass_hash FROM users WHERE id=?'); $s->execute([$cu['id']]); $row = $s->fetch();
        if (!$row || !password_verify((string) ($b['password'] ?? ''), $row['pass_hash'])) { note($pdo, 'login'); json_out(['error' => 'Das Passwort stimmt nicht.'], 403); }
        $pdo->prepare('UPDATE users SET delete_requested_at=NOW() WHERE id=? AND delete_requested_at IS NULL')->execute([$cu['id']]);
        $until = date('d.m.Y', time() + 7 * 86400);
        try { if (!empty($cu['email'])) send_mail($cu['email'], 'Dein Konto wird gelöscht', farewell_line($pdo, (int) $cu['id']) . "\n\nDu hast die Löschung deines Kontos bei Schondraussen.de beantragt.\n\nDeine Daten bleiben noch eine Woche gespeichert und werden nach dem $until endgültig gelöscht. Wenn du dich bis dahin wieder anmeldest, kannst du die Löschung mit einem Klick abbrechen.\n\nWenn du das nicht warst, melde dich an und brich die Löschung ab, und ändere dann dein Passwort.\n"); } catch (Throwable $e) {}
        json_out(['ok' => true]);
    }

    if ($a === 'forgot') {
        if (limited($pdo, 'forgot', 5, 60)) json_out(['error' => 'Zu viele Versuche, bitte später erneut.'], 429);
        note($pdo, 'forgot');
        $email = strtolower(trim((string) ($b['email'] ?? '')));
        $s = $pdo->prepare('SELECT id FROM users WHERE email=? AND email_verified_at IS NOT NULL'); $s->execute([$email]);
        if ($u = $s->fetch()) link_mail($pdo, (int) $u['id'], $email, 'reset');
        json_out(['ok' => true, 'message' => 'Wenn die Adresse bekannt ist, bekommst du gleich einen Link per Mail.']);
    }

    if ($a === 'reset') {
        $pw = (string) ($b['password'] ?? '');
        if (strlen($pw) < 10) json_out(['error' => 'Das Passwort braucht mindestens 10 Zeichen.'], 422); if (strlen($pw) > 72) json_out(['error' => 'Das Passwort darf höchstens 72 Zeichen haben.'], 422);
        $h = hash('sha256', (string) ($b['token'] ?? ''));
        $s = $pdo->prepare("SELECT id,user_id FROM email_tokens WHERE token_hash=? AND kind='reset' AND used_at IS NULL AND expires_at > NOW()");
        $s->execute([$h]); $r = $s->fetch();
        if (!$r) json_out(['error' => 'Der Link ist abgelaufen oder wurde schon benutzt.'], 400);
        $pdo->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $r['user_id']]);
        $pdo->prepare('UPDATE email_tokens SET used_at=NOW() WHERE id=?')->execute([$r['id']]);
        json_out(['ok' => true, 'message' => 'Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.']);
    }

    json_out(['error' => 'Unbekannte Aktion'], 404);
} catch (Throwable $e) {
    json_out(['error' => 'Serverfehler. Bitte später erneut versuchen.'], 500);
}
