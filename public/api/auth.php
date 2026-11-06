<?php
function email_blocked(PDO $pdo, string $email): bool { try { $s = $pdo->prepare('SELECT 1 FROM blocked_emails WHERE email=?'); $s->execute([$email]); return (bool) $s->fetchColumn(); } catch (Throwable $e) { return false; } }
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
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

    if ($a === 'register') {
        if (limited($pdo, 'register', 5, 60)) json_out(['error' => 'Zu viele Versuche, bitte später erneut.'], 429);
        note($pdo, 'register');
        $email = strtolower(trim((string) ($b['email'] ?? ''))); $pw = (string) ($b['password'] ?? '');
        if (!valid_email($email)) json_out(['error' => 'Bitte eine gültige E-Mail-Adresse eingeben.'], 422);
        if (strlen($pw) < 10) json_out(['error' => 'Das Passwort braucht mindestens 10 Zeichen.'], 422);
        if (empty($b['accept'])) json_out(['error' => 'Bitte Datenschutzerklärung und Nutzungsbedingungen bestätigen.'], 422);
        $s = $pdo->prepare('SELECT id,email_verified_at FROM users WHERE email=?'); $s->execute([$email]); $ex = $s->fetch();
        if ($ex) {
            // Keine Auskunft, ob die Adresse schon existiert; unbestätigte Konten bekommen den Link erneut.
            if (!$ex['email_verified_at']) link_mail($pdo, (int) $ex['id'], $email, 'verify');
        } elseif (!email_blocked($pdo, $email)) {
            $first = (int) $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] === 0;
            $pdo->prepare('INSERT INTO users (email,pass_hash,role) VALUES (?,?,?)')->execute([$email, password_hash($pw, PASSWORD_DEFAULT), $first ? 'admin' : 'user']);
            link_mail($pdo, (int) $pdo->lastInsertId(), $email, 'verify');
            if ($first) { try { $pdo->exec("UPDATE users SET approved_at=NOW() WHERE role='admin'"); } catch (Throwable $e) {} }
            else {
                try {
                    foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $adm)
                        send_mail($adm, 'Neues Konto auf Schondraussen.de', "Es hat sich ein neuer Nutzer registriert:\n\n$email\n\nDas Konto sieht keine Inhalte, bis du es in der Nutzerverwaltung freischaltest:\n" . rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/') . "/\n");
                } catch (Throwable $e) {}
            }
        }
        json_out(['ok' => true, 'message' => 'Fast geschafft: Wir haben dir eine Mail mit einem Bestätigungslink geschickt. Nach der Bestätigung schaltet der Admin dein Konto frei (Testbetrieb).']);
    }

    if ($a === 'login') {
        if (limited($pdo, 'login', 8, 15)) json_out(['error' => 'Zu viele Versuche, bitte in einigen Minuten erneut.'], 429);
        $email = strtolower(trim((string) ($b['email'] ?? ''))); $pw = (string) ($b['password'] ?? '');
        $s = $pdo->prepare('SELECT id,pass_hash,email_verified_at FROM users WHERE email=?'); $s->execute([$email]); $u = $s->fetch();
        $ok = $u && password_verify($pw, $u['pass_hash']);
        if (!$ok) { note($pdo, 'login'); json_out(['error' => 'E-Mail-Adresse oder Passwort stimmt nicht.'], 401); }
        if (!$u['email_verified_at']) json_out(['error' => 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in der Mail.'], 403);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $pdo->prepare('UPDATE users SET last_seen_at=NOW() WHERE id=?')->execute([$u['id']]);
        json_out(['ok' => true, 'user' => current_user()]);
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
        try { send_mail($cu['email'], 'Dein Konto wird gelöscht', "Du hast die Löschung deines Kontos bei Schondraussen.de beantragt.\n\nDeine Daten bleiben noch eine Woche gespeichert und werden nach dem $until endgültig gelöscht. Wenn du dich bis dahin wieder anmeldest, kannst du die Löschung mit einem Klick abbrechen.\n\nWenn du das nicht warst, melde dich an und brich die Löschung ab, und ändere dann dein Passwort.\n"); } catch (Throwable $e) {}
        json_out(['ok' => true]);
    }

    if ($a === 'forgot') {
        if (limited($pdo, 'forgot', 5, 60)) json_out(['error' => 'Zu viele Versuche, bitte später erneut.'], 429);
        note($pdo, 'forgot');
        $email = strtolower(trim((string) ($b['email'] ?? '')));
        $s = $pdo->prepare('SELECT id FROM users WHERE email=? AND email_verified_at IS NOT NULL'); $s->execute([$email]);
        if ($u = $s->fetch()) link_mail($pdo, (int) $u['id'], $email, 'reset');
        json_out(['ok' => true, 'message' => 'Wenn die Adresse bei uns bekannt ist, haben wir dir einen Link geschickt.']);
    }

    if ($a === 'reset') {
        $pw = (string) ($b['password'] ?? '');
        if (strlen($pw) < 10) json_out(['error' => 'Das Passwort braucht mindestens 10 Zeichen.'], 422);
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
