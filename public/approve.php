<?php
// Freischaltung eines neuen Kontos per Link aus der Admin-Mail (Bestätigung per Knopf, einmal verwendbar)
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex');
$t = preg_replace('/[^0-9a-f]/', '', (string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$state = 'invalid'; $who = '';
if (strlen($t) === 48) {
    try {
        $pdo = db();
        $s = $pdo->prepare('SELECT t.id tid,t.used_at,t.expires_at > NOW() AS fresh,u.id,u.email,u.username,u.approved_at' . (users_have_privacy($pdo) ? ',u.hide_email' : '') . ' FROM admin_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=?');
        $s->execute([hash('sha256', $t)]); $r = $s->fetch();
        if ($r && $r['fresh']) {
            $who = user_label($r);
            if ($r['used_at'] || $r['approved_at']) $state = 'already';
            elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=? AND approved_at IS NULL')->execute([$r['id']]);
                $pdo->prepare('UPDATE admin_tokens SET used_at=NOW() WHERE id=?')->execute([$r['tid']]);
                $state = 'done';
            } else $state = 'ask';
        }
    } catch (Throwable $e) {}
}
$e = fn($x) => htmlspecialchars((string) $x);
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Konto freischalten – Schondraussen.de</title>
<style>:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#db4d1a}@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e;--accent:#ff7842}}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,sans-serif;padding:0 16px}main{max-width:440px;margin:12vh auto}.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px}h1{font-size:22px;margin:0 0 8px}p{color:var(--muted)}
button{padding:10px 16px;border:1px solid var(--accent);background:var(--accent);color:#fff;border-radius:10px;font:600 15px inherit;cursor:pointer}a{color:var(--accent)}</style></head><body><main><div class="card">
<?php if ($state === 'ask'): ?><h1>Konto freischalten?</h1><p><b><?= $e($who) ?></b> hat sich registriert und wartet auf die Freischaltung.</p>
<form method="post"><input type="hidden" name="t" value="<?= $e($t) ?>"><button type="submit">Freischalten</button></form><p style="margin-top:14px"><a href="/">Zur Nutzerverwaltung</a></p>
<?php elseif ($state === 'done'): ?><h1>Freigeschaltet</h1><p><b><?= $e($who) ?></b> kann die Seite jetzt nutzen.</p><p><a href="/">Zur Seite</a></p>
<?php elseif ($state === 'already'): ?><h1>Schon erledigt</h1><p><b><?= $e($who) ?></b> ist bereits freigeschaltet.</p><p><a href="/">Zur Seite</a></p>
<?php else: ?><h1>Link ungültig</h1><p>Der Link ist abgelaufen oder wurde schon verwendet. Du kannst Konten auch in der Nutzerverwaltung freischalten.</p><p><a href="/">Zur Seite</a></p>
<?php endif; ?>
</div></main></body></html>
