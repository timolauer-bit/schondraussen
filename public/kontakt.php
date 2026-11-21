<?php
require __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/seo.php';
header('Content-Type: text/html; charset=utf-8'); header('Referrer-Policy: no-referrer');
$seo = seo_head('/kontakt.php', 'Kontakt – Schondraussen.de', 'Kontakt zu Schondraussen.de: Fragen, Hinweise oder Feedback über das Kontaktformular schicken, auch ohne eigenes Konto.');
$msg = ''; $ok = false; $name = ''; $mail = ''; $text = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(preg_replace('/[\r\n]+/', ' ', mb_substr((string) ($_POST['name'] ?? ''), 0, 80)));
    $mail = trim((string) ($_POST['email'] ?? '')); $text = trim((string) ($_POST['text'] ?? ''));
    try {
        $pdo = db();
        $lim = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND kind='kontakt' AND at > (NOW() - INTERVAL 1 HOUR)"); $lim->execute([client_ip()]);
        if (!empty($_POST['website'])) { $ok = true; } // Honeypot: Bots füllen das versteckte Feld, sie sehen eine Erfolgsmeldung, es wird nichts gesendet
        elseif ((int) $lim->fetchColumn() >= 3) $msg = 'Du hast gerade mehrere Nachrichten gesendet. Bitte versuche es in einer Stunde noch einmal.';
        elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL) || strlen($mail) > 120) $msg = 'Bitte gib eine gültige E-Mail-Adresse an, damit ich antworten kann.';
        elseif (mb_strlen($text) < 10) $msg = 'Die Nachricht ist zu kurz (mindestens 10 Zeichen).';
        elseif (mb_strlen($text) > 3000) $msg = 'Die Nachricht ist zu lang (höchstens 3000 Zeichen).';
        else {
            $pdo->prepare("INSERT INTO login_attempts (ip,kind) VALUES (?,'kontakt')")->execute([client_ip()]);
            $sent = false; $nm2 = str_replace(['[[', ']]'], ['[ [', '] ]'], $name); $tx2 = str_replace(['[[', ']]'], ['[ [', '] ]'], $text); // keine Knöpfe ([[Text|Link]]) in die Admin-Mail einschleusen
            foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $adm)
                $sent = send_mail($adm, 'Kontaktformular Schondraussen.de', "Nachricht über das öffentliche Kontaktformular\n\nName: " . ($nm2 !== '' ? $nm2 : '(nicht angegeben)') . "\nE-Mail: $mail\n\n$tx2\n", ['Reply-To: ' . $mail]) || $sent;
            if ($sent) $ok = true; else $msg = 'Die Nachricht konnte gerade nicht gesendet werden. Bitte schreibe stattdessen direkt an admin@schondraussen.de.';
        }
    } catch (Throwable $e) { $msg = 'Die Nachricht konnte gerade nicht gesendet werden. Bitte schreibe stattdessen direkt an admin@schondraussen.de.'; }
}
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kontakt – Schondraussen.de</title><?= $seo ?>
<style>body{font:16px/1.6 system-ui,sans-serif;max-width:620px;margin:6vh auto;padding:0 16px}label{display:block;margin:14px 0 4px;font-weight:600}input,textarea{font:inherit;width:100%;box-sizing:border-box;padding:8px;border:1px solid #888;border-radius:6px}button{font:inherit;margin-top:16px;padding:9px 18px;border:0;border-radius:6px;background:#1d4ed8;color:#fff;cursor:pointer}.err{color:#b3261e;font-weight:600}.ok{background:#e7f6ea;padding:12px;border-radius:6px}.hp{position:absolute;left:-9999px;height:0;overflow:hidden}.fine{font-size:14px;color:#555}</style></head><body>
<h1>Kontakt</h1>
<?php if ($ok): ?>
<p class="ok" role="status">Danke, deine Nachricht ist angekommen. Ich antworte dir an die angegebene E-Mail-Adresse.</p>
<?php else: ?>
<p>Schreib mir eine Nachricht, auch ohne Konto. Du kannst auch direkt an <a href="mailto:admin@schondraussen.de">admin@schondraussen.de</a> mailen.</p>
<?php if ($msg): ?><p class="err" role="alert"><?= $e($msg) ?></p><?php endif; ?>
<form method="post" action="/kontakt.php" novalidate>
<label for="n">Name (optional)</label><input id="n" name="name" maxlength="80" autocomplete="name" value="<?= $e($name) ?>">
<label for="m">Deine E-Mail-Adresse (für die Antwort)</label><input id="m" name="email" type="email" maxlength="120" autocomplete="email" required value="<?= $e($mail) ?>">
<label for="t">Nachricht</label><textarea id="t" name="text" rows="8" maxlength="3000" required><?= $e($text) ?></textarea>
<div class="hp" aria-hidden="true"><label for="w">Bitte leer lassen</label><input id="w" name="website" tabindex="-1" autocomplete="off"></div>
<p class="fine">Name, E-Mail-Adresse und Nachricht werden nur zur Beantwortung per E-Mail an mich gesendet und nicht in der Datenbank gespeichert. Zum Schutz vor Missbrauch wird kurz deine IP-Adresse vermerkt. Mehr in der <a href="/datenschutz.php">Datenschutzerklärung</a>.</p>
<button type="submit">Nachricht senden</button>
</form>
<?php endif; ?>
<p><a href="/">Zurück</a> · <a href="/neuerscheinungen">Neuerscheinungen</a> · <a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></p></body></html>
