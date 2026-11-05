<?php
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
$t = preg_replace('/[^0-9a-f]/', '', (string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$done = false; $ok = false;
if (strlen($t) === 40) {
    try {
        $pdo = db();
        $s = $pdo->prepare('SELECT id FROM users WHERE unsub_token=?'); $s->execute([$t]); $uid = $s->fetchColumn();
        $ok = (bool) $uid;
        if ($ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->prepare('UPDATE users SET notify=0 WHERE id=?')->execute([$uid]); $done = true;
            if (isset($_POST['List-Unsubscribe'])) { http_response_code(200); exit('OK'); }
        }
    } catch (Throwable $e) {}
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Mails abbestellen – Schondraussen.de</title>
<style>:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#db4d1a}@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e;--accent:#ff7842}}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,sans-serif;padding:0 16px}main{max-width:440px;margin:12vh auto}.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px}
button{padding:10px 16px;border:1px solid var(--accent);background:var(--accent);color:#fff;border-radius:10px;font:600 15px inherit;cursor:pointer}a{color:var(--accent)}p{color:var(--muted)}h1{font-size:24px;margin:0 0 8px}</style></head><body><main><div class="card">
<?php if ($done): ?><h1>Abbestellt</h1><p>Du bekommst keine Benachrichtigungen mehr. In den Einstellungen auf der Seite kannst du sie jederzeit wieder einschalten.</p><p><a href="/">Zu Schondraussen.de</a></p>
<?php elseif ($ok): ?><h1>Keine Mails mehr?</h1><p>Möchtest du die Benachrichtigungen von Schondraussen.de abbestellen?</p><form method="post"><input type="hidden" name="t" value="<?= htmlspecialchars($t) ?>"><button>Ja, abbestellen</button></form>
<?php else: ?><h1>Link ungültig</h1><p>Dieser Abmeldelink ist nicht gültig. Du kannst die Benachrichtigungen auch nach der Anmeldung in den Einstellungen ausschalten.</p><p><a href="/">Zu Schondraussen.de</a></p><?php endif; ?>
</div></main></body></html>
