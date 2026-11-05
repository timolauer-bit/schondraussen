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
main{max-width:440px;margin:10vh auto 40px}main.wide{max-width:760px;margin-top:5vh}h1{font-size:36px;letter-spacing:-.035em;margin:0 0 4px}h1 span{color:var(--accent)}
p{color:var(--muted)}.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px;margin-top:20px}
label{display:block;font-size:14px;color:var(--muted);margin:12px 0 4px}input[type=email],input[type=password]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit}
button{margin-top:16px;padding:10px 16px;border:1px solid var(--accent);background:var(--accent);color:#fff;border-radius:10px;font:600 15px inherit;cursor:pointer}
button.link{background:none;border:0;color:var(--accent);padding:0;margin:0;font-weight:500;text-decoration:underline;cursor:pointer}
.msg{min-height:22px;margin-top:12px;font-size:14px}.err{color:#b62520}.chk{display:flex;gap:8px;align-items:flex-start;margin-top:12px;font-size:14px;color:var(--muted)}
footer{max-width:440px;margin:0 auto 30px;font-size:13px;color:var(--muted)}footer a{color:var(--muted)}.row{display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;font-size:14px}[hidden]{display:none!important}
#tabs{display:flex;gap:6px;flex-wrap:wrap;margin:14px 0}#tabs button{margin:0;background:none;color:var(--ink);border:1px solid var(--line);padding:6px 12px;font-size:14px}#tabs button.on{background:var(--accent);color:#fff;border-color:var(--accent)}
button.ghost{margin:0;background:none;color:var(--ink);border:1px solid var(--line);padding:6px 12px;font-size:14px}.muted{color:var(--muted);font-size:14px}
.it{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 4px;border-top:1px solid var(--line);cursor:pointer}.it:hover{background:var(--bg)}.it b{font-weight:600}
.dot{display:inline-block;background:#d93025;color:#fff;border-radius:99px;font-size:12px;min-width:20px;text-align:center;padding:1px 6px}
.d{display:inline-block;min-width:92px;color:var(--muted);font-size:13px}.chip{display:inline-block;border:1px solid var(--line);border-radius:99px;padding:1px 9px;font-size:12px;color:var(--muted);margin:0 4px 4px 0}
h3{margin:18px 0 6px;font-size:15px}
.note{background:var(--bg);border:1px solid var(--accent);border-radius:10px;padding:10px 12px;font-size:14px;margin-bottom:14px}.row2{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.row2 button{margin:0}button.danger{color:#b62520;border-color:#b62520}
</style></head><body>
<main<?= $u ? ' class="wide"' : '' ?>>
<h1>Schondraussen<span>.de</span></h1>
<p>Deine Autor:innen, Künstler:innen, Filme und Serien an einem Ort.</p>
<?php if ($u): ?>
<?php if (empty($u['approved'])): ?>
  <div class="card"><p style="margin:0">Angemeldet als <b><?= htmlspecialchars($u['email']) ?></b>.</p>
  <p><b>Dein Konto wartet auf Freischaltung.</b> Die Seite ist noch im Testbetrieb. Sobald der Admin dich freigeschaltet hat, siehst du hier deine Listen. Schau später einfach noch einmal vorbei.</p>
  <button id="logout" type="button" class="ghost">Abmelden</button></div>
<?php else: ?>
  <div id="app" class="card" style="max-width:none">
    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center"><span class="muted"><?= htmlspecialchars($u['email']) ?><?= $u['role'] === 'admin' ? ' (Admin)' : '' ?></span><button id="logout" type="button" class="ghost">Abmelden</button></div>
    <nav id="tabs"></nav><div id="view"></div>
  </div>
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

  var tabsEl = document.getElementById('tabs');
  if (tabsEl) (function () {
    var ADMIN = <?= json_encode($u['role'] === 'admin') ?>, pend = 0;
    var T = [['author', 'Autoren'], ['artist', 'Künstler'], ['film', 'Filme'], ['series', 'Serien'], ['cal', 'Termine']];
    if (ADMIN) T.push(['users', 'Nutzer']);
    var cat = 'series', all = [], view = document.getElementById('view');
    function esc(x) { return String(x == null ? '' : x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function fd(d) { if (!d) return 'Termin offen'; var p = d.split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : p.length === 2 ? p[1] + '.' + p[0] : d; }
    function get(q) { return fetch('/api/data.php?' + q, { credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
    function tabs() {
      tabsEl.innerHTML = T.map(function (t) {
        var n = t[0] === 'cal' ? '' : t[0] === 'users' ? (pend ? ' <span class="dot">' + pend + '</span>' : '') : ' ' + all.filter(function (e) { return e.type === t[0]; }).length;
        return '<button data-c="' + t[0] + '" class="' + (t[0] === cat ? 'on' : '') + '">' + t[1] + n + '</button>';
      }).join('');
      tabsEl.querySelectorAll('button').forEach(function (b) { b.onclick = function () { cat = b.dataset.c; tabs(); show(); }; });
    }
    function show() {
      if (cat === 'cal') return calendar();
      if (cat === 'users') return users();
      var l = all.filter(function (e) { return e.type === cat; });
      var head = '<div style="margin-bottom:8px"><button class="ghost" id="addbtn">+ Hinzufügen</button></div><div id="addbox"></div>';
      if (!l.length) head += '<p class="muted">Hier ist noch nichts.</p>';
      view.innerHTML = head + l.map(function (e) {
        return '<div class="it" data-id="' + e.id + '"><span><b>' + esc(e.name) + '</b><br><span class="muted">' +
          (e.fetched_at ? (e.next_date ? 'Nächster Termin: ' + fd(e.next_date) : (e.works ? e.works + ' Werke' : 'Keine Termine')) : 'Noch nicht geladen') +
          '</span></span>' + (e.next_date ? '<span class="dot">' + e.upcoming + '</span>' : '') + '</div>';
      }).join('');
      view.querySelectorAll('.it').forEach(function (x) { x.onclick = function () { entry(x.dataset.id); }; });
      document.getElementById('addbtn').onclick = addForm;
    }
    function addForm() {
      var box = document.getElementById('addbox'), label = { author: 'Autor:in', artist: 'Künstler:in / Band', film: 'Film', series: 'Serie' }[cat], live = cat === 'series' || cat === 'artist';
      box.innerHTML = '<form id="addf" style="display:flex;gap:6px;margin-bottom:8px"><input id="addq" placeholder="' + label + ' suchen oder eingeben" style="flex:1;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit"><button style="margin:0;padding:8px 14px">' + (live ? 'Suchen' : 'Hinzufügen') + '</button></form><div id="addres"></div>';
      var q = document.getElementById('addq'); q.focus();
      function add(name, ext) {
        fetch('/api/data.php?a=add', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type: cat, name: name, ext: ext || {} }) })
          .then(function (r) { return r.json(); }).then(function (r) { if (r.ok) load(function () { entry(r.id); }); else document.getElementById('addres').innerHTML = '<p class="err">' + esc(r.error) + '</p>'; });
      }
      document.getElementById('addf').onsubmit = function (ev) {
        ev.preventDefault(); var v = q.value.trim(); if (v.length < 2) return;
        var res = document.getElementById('addres');
        if (!live) return add(v);
        res.innerHTML = '<p class="muted">Suche …</p>';
        get('a=search&type=' + cat + '&q=' + encodeURIComponent(v)).then(function (r) {
          var l = r.results || [];
          res.innerHTML = l.map(function (x, i) { return '<div class="it" data-i="' + i + '"><span><b>' + esc(x.name) + '</b><br><span class="muted">' + esc(x.hint) + '</span></span><span class="muted">+</span></div>'; }).join('') +
            '<div class="it" data-i="-1"><span class="muted">„' + esc(v) + '“ trotzdem so hinzufügen</span></div>';
          res.querySelectorAll('.it').forEach(function (n) { n.onclick = function () { var i = +n.dataset.i; i < 0 ? add(v) : add(l[i].name, l[i].ext); }; });
        });
      };
    }
    function adm(a, id) { return fetch('/api/admin.php?a=' + a, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) }).then(function (r) { return r.json(); }); }
    function users() {
      view.innerHTML = '<p class="muted">Lade …</p>';
      fetch('/api/admin.php?a=users', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
        var l = r.users || []; pend = l.filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; tabs();
        view.innerHTML = l.map(function (x) {
          var ok = x.role === 'admin' || x.approved_at, me = x.id == r.me;
          return '<div class="it" style="cursor:default;align-items:flex-start"><span><b>' + esc(x.email) + '</b>' + (x.role === 'admin' ? ' <span class="chip">Admin</span>' : '') +
            '<br><span class="muted">' + (ok ? 'Freigeschaltet' : '<b style="color:var(--accent)">Wartet auf Freischaltung</b>') + (x.email_verified_at ? '' : ' · E-Mail nicht bestätigt') +
            ' · ' + x.entries + ' Einträge · registriert ' + esc((x.created_at || '').slice(0, 10)) + '</span>' +
            (me ? '' : '<div class="row2">' + (ok ? (x.role === 'admin' ? '' : '<button class="ghost" data-a="revoke" data-id="' + x.id + '">Sperren</button>') : '<button data-a="approve" data-id="' + x.id + '" style="padding:6px 12px;font-size:14px">Freischalten</button>') +
              (x.role === 'admin' ? '' : '<button class="ghost danger" data-a="delete" data-id="' + x.id + '">Löschen</button>') + '</div>') + '</span></div>';
        }).join('');
        view.querySelectorAll('button[data-a]').forEach(function (b) {
          b.onclick = function () {
            if (b.dataset.a === 'delete' && !confirm('Konto samt Liste wirklich löschen?')) return;
            adm(b.dataset.a, +b.dataset.id).then(function (x) { if (x.ok) users(); else alert(x.error); });
          };
        });
      });
    }
    function calendar() {
      view.innerHTML = '<p class="muted">Lade …</p>';
      get('a=calendar').then(function (r) {
        var l = r.releases || [];
        view.innerHTML = l.length ? l.map(function (x) {
          return '<div class="it" data-id="' + x.subject_id + '"><span><span class="d">' + fd(x.release_date) + '</span><b>' + esc(x.name) + '</b> <span class="muted">' + esc(x.title) + '</span></span></div>';
        }).join('') : '<p class="muted">Keine kommenden Termine. Serien werden beim ersten Öffnen geladen.</p>';
        view.querySelectorAll('.it').forEach(function (x) { x.onclick = function () { entry(x.dataset.id); }; });
      });
    }
    function entry(id) {
      view.innerHTML = '<p class="muted">Lade …</p>';
      get('a=entry&id=' + id).then(function (r) {
        if (!r.entry) { view.innerHTML = '<p class="err">' + esc(r.error) + '</p>'; return; }
        var e = r.entry;
        var h = '<div style="display:flex;justify-content:space-between"><button class="ghost" id="back">← Zurück</button><button class="ghost danger" id="rm">Aus meiner Liste entfernen</button></div><h2 style="margin:14px 0 4px">' + esc(e.name) + '</h2>' +
          (e.genres || []).map(function (g) { return '<span class="chip">' + esc(g) + '</span>'; }).join('') +
          (e.summary ? '<p>' + esc(e.summary) + '</p>' : '');
        if (!e.fetched_at || ((e.type === 'series' || e.type === 'artist') && !r.works.length && !r.releases.length)) h += '<p class="muted" id="st">Daten werden geladen …</p>';
        else if (e.type !== 'series' && e.type !== 'artist') h += '<p class="muted">Für diese Kategorie wird die Datenquelle noch angebunden.</p>';
        h += '<h3>Kommende Termine</h3>' + (r.releases.length ? r.releases.map(function (x) { return '<div><span class="d">' + fd(x.release_date) + '</span>' + esc(x.title) + '</div>'; }).join('') : '<p class="muted">Keine bekannt.</p>');
        h += '<h3>Werke</h3>' + (r.works.length ? r.works.map(function (x) { return '<div><span class="d">' + esc(x.year || '') + '</span>' + esc(x.title) + (x.note ? ' <span class="muted">· ' + esc(x.note) + '</span>' : '') + '</div>'; }).join('') : '<p class="muted">Noch keine.</p>');
        view.innerHTML = h;
        document.getElementById('rm').onclick = function () {
          if (!confirm('„' + e.name + '“ aus deiner Liste entfernen?')) return;
          fetch('/api/data.php?a=remove&id=' + id, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: '{}' }).then(function () { load(function () { cat === 'cal' ? calendar() : show(); }); });
        };
        document.getElementById('back').onclick = function () { cat === 'cal' ? calendar() : show(); };
        if (document.getElementById('st') || ((e.type === 'series' || e.type === 'artist') && !e.fetched_at)) {
          fetch('/api/data.php?a=refresh&id=' + id, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: '{}' })
            .then(function (x) { return x.json(); }).then(function (x) {
              if (x.ok) { load(function () { entry(id); }); } else { var st = document.getElementById('st'); if (st) st.textContent = x.msg || x.error; }
            });
        }
      });
    }
    function load(cb) { get('a=list').then(function (r) { all = r.entries || []; tabs(); if (cb) cb(); else show(); }); }
    load();
    if (ADMIN) fetch('/api/admin.php?a=users', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) { pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; tabs(); });
  })();
  var lo = document.getElementById('logout');
  if (lo) lo.addEventListener('click', function () { api('logout').then(function () { location.reload(); }); });
})();
</script></body></html>
