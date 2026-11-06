<?php
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$u = null; try { $u = current_user(); } catch (Throwable $e) {}
$app = $u && !empty($u['approved']);
$skin = ($u && in_array($u['skin'] ?? '', ['light', 'dark', 'paper', 'cinema', 'mint', 'eighties'], true)) ? $u['skin'] : '';
?><!doctype html>
<html lang="de"<?= $skin ? ' data-skin="' . $skin . '"' : '' ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Schondraussen.de</title>
<?php if ($app): ?><link rel="stylesheet" href="/app.css?v=<?= filemtime(__DIR__ . '/app.css') ?>"><?php else: ?>
<style>
:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#db4d1a}
@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e;--accent:#ff7842}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;padding:0 16px}
main{max-width:440px;margin:10vh auto 40px}main.wide{max-width:760px;margin-top:5vh}h1{font-size:36px;letter-spacing:-.035em;margin:0 0 4px}h1 span{color:var(--accent)}
p{color:var(--muted)}.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px;margin-top:20px}
label{display:block;font-size:14px;color:var(--muted);margin:12px 0 4px}input[type=email],input[type=password]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit}
button{margin-top:16px;padding:10px 16px;border:1px solid var(--accent);background:var(--accent);color:#fff;border-radius:10px;font:600 15px inherit;cursor:pointer}
button.link{background:none;border:0;color:var(--accent);padding:0;margin:0;font-weight:500;text-decoration:underline;cursor:pointer}
.msg{min-height:22px;margin-top:12px;font-size:14px}.err{color:#b62520}.chk{display:flex;gap:8px;align-items:flex-start;margin-top:12px;font-size:14px;color:var(--muted)}
.shots{margin-top:26px}.shots h2{font-size:15px;margin:0 0 10px;color:var(--muted);font-weight:600}.thumbs{display:flex;gap:12px;flex-wrap:wrap}
.thumb{margin:0;padding:0;border:1px solid var(--line);background:var(--surface);border-radius:10px;overflow:hidden;width:calc(50% - 6px);text-align:left;color:var(--ink);cursor:pointer;font:500 13px system-ui}
.thumb img{display:block;width:100%;height:110px;object-fit:cover;object-position:top left}.thumb span{display:block;padding:6px 10px}.thumb:hover,.thumb:focus-visible{border-color:var(--accent)}
.lb{position:fixed;inset:0;background:rgba(10,14,18,.88);display:flex;align-items:center;justify-content:center;z-index:50;padding:16px}.lb[hidden]{display:none}
.lb figure{margin:0;max-width:min(1100px,100%);text-align:center}.lb img{max-width:100%;max-height:78vh;border-radius:10px;background:#fff}.lb figcaption{color:#e8ecee;font-size:14px;margin-top:8px}
.lb button{margin:0;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:999px;width:42px;height:42px;font-size:26px;line-height:1;padding:0}
.lbx{position:absolute;top:14px;right:14px}.lbn{flex:none;margin:0 8px!important}.lb.one .lbn{display:none}
footer{max-width:440px;margin:0 auto 30px;font-size:13px;color:var(--muted)}footer a{color:var(--muted)}.row{display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;font-size:14px}[hidden]{display:none!important}
#tabs{display:flex;gap:6px;flex-wrap:wrap;margin:14px 0}#tabs button{margin:0;background:none;color:var(--ink);border:1px solid var(--line);padding:6px 12px;font-size:14px}#tabs button.on{background:var(--accent);color:#fff;border-color:var(--accent)}
button.ghost{margin:0;background:none;color:var(--ink);border:1px solid var(--line);padding:6px 12px;font-size:14px}.muted{color:var(--muted);font-size:14px}
.it{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 4px;border-top:1px solid var(--line);cursor:pointer}.it:hover{background:var(--bg)}.it b{font-weight:600}
.dot{display:inline-block;background:#d93025;color:#fff;border-radius:99px;font-size:12px;min-width:20px;text-align:center;padding:1px 6px}
.d{display:inline-block;min-width:92px;color:var(--muted);font-size:13px}.chip{display:inline-block;border:1px solid var(--line);border-radius:99px;padding:1px 9px;font-size:12px;color:var(--muted);margin:0 4px 4px 0}
h3{margin:18px 0 6px;font-size:15px}
.note{background:var(--bg);border:1px solid var(--accent);border-radius:10px;padding:10px 12px;font-size:14px;margin-bottom:14px}.row2{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.row2 button{margin:0}button.danger{color:#b62520;border-color:#b62520}
</style>
<?php endif; ?>
</head><body>
<?php if ($app): ?>
<div class="wrap">
  <header class="masthead"><div><h1>Schondraussen<span class="tld">.de</span></h1><p>Deine Autor:innen, Künstler:innen, Filme und Serien an einem Ort. Mit Werkübersicht, Kalender und Amazon-Links.</p></div>
  <div class="whobox"><button class="who" id="who" type="button" title="Einstellungen"><span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>Mitglied Nr. <?= (int) $u['id'] ?> · <?= htmlspecialchars($u['email']) ?></button><div class="whorow"><button class="ver" id="ver" type="button"></button><button class="ver" id="logoutTop" type="button" title="Von diesem Gerät abmelden">Abmelden</button></div></div></header>
  <nav class="topbar" id="topbar" aria-label="Bereiche"></nav>
  <div class="warnbox previewbar" id="previewbar" hidden><b>Vorschau:</b> Du siehst die Seite gerade so, wie ein normaler Nutzer sie sieht. Deine Einträge und Einstellungen sind dieselben, nur die Admin-Funktionen fehlen. <button class="btn small" id="previewOff" type="button">Zur Admin-Ansicht zurück</button></div>
  <div id="appview"></div>
  <footer>
    <p>Testbetrieb: Die Seite ist noch nicht öffentlich.</p>
    <p>Als Amazon-Partner verdiene ich an qualifizierten Verkäufen. Links mit Partner-ID sind Werbelinks; für dich ändert sich der Preis nicht.</p>
    <p>Werke und Termine stammen aus <a href="https://musicbrainz.org" target="_blank" rel="noopener noreferrer">MusicBrainz</a> (Künstler:innen) und <a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> (Autor:innen). Folgen und Sendetermine von Serien stammen von <a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">TVmaze</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>). Termine bitte vor dem Kauf prüfen.</p>
    <p><a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></p>
  </footer>
</div>
<script>window.SD = <?= json_encode(['alpha' => is_alpha($u), 'admin' => $u['role'] === 'admin', 'id' => (int) $u['id'], 'email' => $u['email'], 'skin' => $skin ?: 'auto', 'notify' => !isset($u['notify']) || (int) $u['notify'] === 1, 'sample_asked' => (int) ($u['sample_asked'] ?? 1), 'n' => ['new' => (int) ($u['n_new'] ?? 1), 'day' => (int) ($u['n_day'] ?? 1), 'week' => (int) ($u['n_week'] ?? 0), 'mode' => ($u['n_mode'] ?? 'digest') === 'instant' ? 'instant' : 'digest', 'weekday' => (int) ($u['n_weekday'] ?? 1), 'bell_default' => (int) ($u['bell_default'] ?? 1)]]) ?>;</script>
<script src="/changelog.js?v=<?= filemtime(__DIR__ . '/changelog.js') ?>"></script>
<script src="/app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
<?php else: ?>
<main>
<h1>Schondraussen<span>.de</span></h1>
<p>Deine Autor:innen, Künstler:innen, Filme und Serien an einem Ort.</p>
<?php if ($u): ?>
  <div class="card"><p style="margin:0">Angemeldet als <b><?= htmlspecialchars($u['email']) ?></b> (Mitglied Nr. <?= (int) $u['id'] ?>).</p>
<?php if (!empty($u['deleting'])): $until = date('d.m.Y', strtotime($u['delete_requested_at']) + 7 * 86400); ?>
  <p><b>Dein Konto ist zur Löschung vorgemerkt.</b> Deine Daten werden nach dem <?= $until ?> endgültig gelöscht. Bis dahin kannst du es behalten, wenn du die Löschung abbrichst.</p>
  <button id="canceldel" type="button">Löschung abbrechen</button> <button id="logout" type="button" class="ghost">Abmelden</button></div>
<?php else: ?>
  <p><b>Dein Konto wartet auf Freischaltung.</b> Die Seite ist noch im Testbetrieb. Sobald der Admin dich freigeschaltet hat, siehst du hier deine Listen. Schau später einfach noch einmal vorbei.</p>
  <button id="logout" type="button" class="ghost">Abmelden</button></div>
<?php endif; ?>
<?php else: ?>
  <div class="card">
    <div class="note"><b>Testbetrieb:</b> Diese Seite ist noch nicht öffentlich. Neue Konten sehen erst Inhalte, nachdem der Admin sie freigeschaltet hat.</div>
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
  <section class="shots" aria-labelledby="shots-h">
    <h2 id="shots-h">So sieht es aus</h2>
    <div class="thumbs">
      <button type="button" class="thumb" data-i="0"><img src="/shots/shot1.webp" alt="Kalender mit allen angekündigten Veröffentlichungen" loading="lazy"><span>Kalender</span></button>
      <button type="button" class="thumb" data-i="1"><img src="/shots/shot2.webp" alt="Serienübersicht mit Liste und kommenden Veröffentlichungen" loading="lazy"><span>Serienübersicht</span></button>
    </div>
  </section>
  <div id="lb" class="lb" role="dialog" aria-modal="true" aria-label="Bildansicht" hidden>
    <button type="button" class="lbx" id="lbx" aria-label="Schließen">×</button>
    <button type="button" class="lbn prev" id="lbp" aria-label="Vorheriges Bild">‹</button>
    <figure><img id="lbi" alt=""><figcaption id="lbc"></figcaption></figure>
    <button type="button" class="lbn next" id="lbn" aria-label="Nächstes Bild">›</button>
  </div>
<?php endif; ?>
</main>
<footer><a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></footer>
<script>
(function () {
  var lb = document.getElementById('lb');
  if (lb) {
    var SH = [['/shots/shot1.webp', 'Kalender mit allen angekündigten Veröffentlichungen'], ['/shots/shot2.webp', 'Serienübersicht mit Liste und kommenden Veröffentlichungen']], cur = 0, opener = null;
    function lbShow(i) { cur = (i + SH.length) % SH.length; document.getElementById('lbi').src = SH[cur][0]; document.getElementById('lbi').alt = SH[cur][1]; document.getElementById('lbc').textContent = SH[cur][1] + ' (' + (cur + 1) + ' von ' + SH.length + ')'; }
    function lbOpen(i, el) { opener = el; lb.classList.toggle('one', SH.length < 2); lbShow(i); lb.hidden = false; document.getElementById('lbx').focus(); }
    function lbClose() { lb.hidden = true; if (opener) opener.focus(); }
    document.querySelectorAll('.thumb').forEach(function (b) { b.addEventListener('click', function () { lbOpen(+b.dataset.i, b); }); });
    document.getElementById('lbx').addEventListener('click', lbClose);
    document.getElementById('lbp').addEventListener('click', function () { lbShow(cur - 1); });
    document.getElementById('lbn').addEventListener('click', function () { lbShow(cur + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) lbClose(); });
    document.addEventListener('keydown', function (e) { if (lb.hidden) return; if (e.key === 'Escape') lbClose(); else if (e.key === 'ArrowLeft') lbShow(cur - 1); else if (e.key === 'ArrowRight') lbShow(cur + 1); });
  }
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
  var cd = document.getElementById('canceldel');
  if (cd) cd.addEventListener('click', function () { api('cancel_delete').then(function () { location.reload(); }); });
  if (lo) lo.addEventListener('click', function () { api('logout').then(function () { location.reload(); }); });
})();
</script>
<?php endif; ?>
</body></html>
