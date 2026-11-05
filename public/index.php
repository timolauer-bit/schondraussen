<?php
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$u = null; try { $u = current_user(); } catch (Throwable $e) {}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Schondraussen.de</title>
<style>
:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#db4d1a}
@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e;--accent:#ff7842}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;padding:0 16px}
main{max-width:440px;margin:10vh auto 40px}h1{font-size:36px;letter-spacing:-.035em;margin:0 0 4px}h1 span{color:var(--accent)}
p{color:var(--muted)}.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px;margin-top:20px}
label{display:block;font-size:14px;color:var(--muted);margin:12px 0 4px}input[type=email],input[type=password]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit}
button{margin-top:16px;padding:10px 16px;border:1px solid var(--accent);background:var(--accent);color:#fff;border-radius:10px;font:600 15px inherit;cursor:pointer}
button.link{background:none;border:0;color:var(--accent);padding:0;margin:0;font-weight:500;text-decoration:underline;cursor:pointer}
.msg{min-height:22px;margin-top:12px;font-size:14px}.err{color:#b62520}.chk{display:flex;gap:8px;align-items:flex-start;margin-top:12px;font-size:14px;color:var(--muted)}
footer{max-width:440px;margin:0 auto 30px;font-size:13px;color:var(--muted)}footer a{color:var(--muted)}.row{display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;font-size:14px}[hidden]{display:none!important}
</style></head><body>
<main>
<h1>Schondraussen<span>.de</span></h1>
<p>Deine Autor:innen, Künstler:innen, Filme und Serien an einem Ort.</p>
<?php if ($u): ?>
  <div class="card"><p style="margin:0">Angemeldet als <b><?= htmlspecialchars($u['email']) ?></b><?= $u['role'] === 'admin' ? ' (Admin)' : '' ?>.</p>
  <p>Die Listen, der Kalender und die Mails werden gerade auf diesen Server umgezogen. Bis dahin gibt es hier nur die Anmeldung.</p>
  <button id="logout" type="button">Abmelden</button></div>
<?php else: ?>
  <div class="card">
    <form id="f-login"><h2 style="margin:0 0 4px;font-size:20px">Anmelden</h2>
      <label for="le">E-Mail-Adresse</label><input id="le" type="email" autocomplete="email" required>
      <label for="lp">Passwort</label><input id="lp" type="password" autocomplete="current-password" required>
      <button type="submit">Anmelden</button>
      <div class="row"><button class="link" type="button" data-show="f-register">Neu registrieren</button><button class="link" type="button" data-show="f-forgot">Passwort vergessen</button></div></form>
    <form id="f-register" hidden><h2 style="margin:0 0 4px;font-size:20px">Registrieren</h2>
      <label for="re">E-Mail-Adresse</label><input id="re" type="email" autocomplete="email" required>
      <label for="rp">Passwort (mindestens 10 Zeichen)</label><input id="rp" type="password" autocomplete="new-password" minlength="10" required>
      <div class="chk"><input id="ra" type="checkbox" required><label for="ra" style="margin:0">Ich habe die <a href="/datenschutz.php" target="_blank">Datenschutzerklärung</a> gelesen.</label></div>
      <button type="submit">Konto anlegen</button>
      <div class="row"><button class="link" type="button" data-show="f-login">Zurück zur Anmeldung</button></div></form>
    <form id="f-forgot" hidden><h2 style="margin:0 0 4px;font-size:20px">Passwort vergessen</h2>
      <label for="fe">E-Mail-Adresse</label><input id="fe" type="email" autocomplete="email" required>
      <button type="submit">Link anfordern</button>
      <div class="row"><button class="link" type="button" data-show="f-login">Zurück zur Anmeldung</button></div></form>
    <form id="f-reset" hidden><h2 style="margin:0 0 4px;font-size:20px">Neues Passwort</h2>
      <label for="np">Neues Passwort (mindestens 10 Zeichen)</label><input id="np" type="password" autocomplete="new-password" minlength="10" required>
      <button type="submit">Passwort speichern</button></form>
    <div class="msg" id="msg" role="status"></div>
  </div>
<?php endif; ?>
</main>
<footer><a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></footer>
<script>
(function () {
  var msg = document.getElementById('msg');
  function say(t, err) { if (!msg) return; msg.textContent = t || ''; msg.className = 'msg' + (err ? ' err' : ''); }
  function api(a, data) {
    return fetch('/api/auth.php?a=' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data || {}), credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { error: 'Unerwartete Antwort' }; }); });
  }
  function show(id) { ['f-login', 'f-register', 'f-forgot', 'f-reset'].forEach(function (f) { var e = document.getElementById(f); if (e) e.hidden = f !== id; }); say(''); }
  document.querySelectorAll('[data-show]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.show); }); });
  function on(id, fn) { var f = document.getElementById(id); if (f) f.addEventListener('submit', function (e) { e.preventDefault(); say('…'); fn(); }); }
  on('f-login', function () { api('login', { email: le.value, password: lp.value }).then(function (r) { if (r.ok) location.reload(); else say(r.error, true); }); });
  on('f-register', function () { api('register', { email: re.value, password: rp.value, accept: ra.checked }).then(function (r) { r.ok ? (say(r.message), show('f-login'), say(r.message)) : say(r.error, true); }); });
  on('f-forgot', function () { api('forgot', { email: fe.value }).then(function (r) { say(r.message || r.error, !r.ok); }); });
  var q = new URLSearchParams(location.search), tok = q.get('reset');
  on('f-reset', function () { api('reset', { token: tok, password: np.value }).then(function (r) { if (r.ok) { history.replaceState(null, '', '/'); show('f-login'); say(r.message); } else say(r.error, true); }); });
  if (tok) show('f-reset');
  if (q.get('verified') === '1') say('Deine Adresse ist bestätigt. Du kannst dich jetzt anmelden.');
  if (q.get('verified') === '0') say('Der Bestätigungslink ist abgelaufen oder ungültig.', true);
  var lo = document.getElementById('logout');
  if (lo) lo.addEventListener('click', function () { api('logout').then(function () { location.reload(); }); });
})();
</script></body></html>
