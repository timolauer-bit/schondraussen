(function () {
  var S = window.SD || {}, root = document.getElementById('appview');
  if (!root) return;
  var SKINS = [['auto', 'System', ['#f2f2f0', '#101419', '#db4d1a']], ['light', 'Hell', ['#f2f2f0', '#fff', '#db4d1a']], ['dark', 'Dunkel', ['#101419', '#171d23', '#ff7842']],
    ['paper', 'Papier', ['#f3ead6', '#fbf6e9', '#a23f1c']], ['cinema', 'Kino', ['#0b0b10', '#15151d', '#e8b830']], ['mint', 'Mint', ['#e6f2ee', '#f4faf7', '#0c7560']], ['eighties', '80er', ['#160a2e', '#211146', '#ff4fb0']]];
  var CATS = [['author', 'Autor:innen', 'Autor:in'], ['artist', 'Künstler:innen', 'Künstler:in'], ['film', 'Filme', 'Film'], ['series', 'Serien', 'Serie']];
  var ICON = {
    author: '<path d="M4 4h12a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3z M7 4v16"/>',
    artist: '<path d="M9 18V5l11-2v13 M9 18a3 3 0 1 1-3-3 3 3 0 0 1 3 3z M20 16a3 3 0 1 1-3-3 3 3 0 0 1 3 3z"/>',
    film: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16 M17 4v16 M3 9h4 M3 15h4 M17 9h4 M17 15h4"/>',
    series: '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8 M12 17v4"/>',
    cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18 M8 3v4 M16 3v4"/>'
  };
  function ico(k) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICON[k] + '</svg>'; }
  var cat = 'cal', all = [], sel = null, q = '', sort = 'name', pend = 0, adding = false;
  var topbar = document.getElementById('topbar'), view = root;

  function esc(x) { return String(x == null ? '' : x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function parts(d) { var p = (d || '').split('-'); return { y: p[0], m: p[1], d: p[2], n: p.length }; }
  var MON = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  var DOW = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
  function fd(d) { if (!d) return 'Termin offen'; var p = parts(d); return p.n === 3 ? p.d + '.' + p.m + '.' + p.y : p.n === 2 ? MON[+p.m - 1] + ' ' + p.y : d; }
  function dayBox(d) {
    var p = parts(d);
    if (p.n === 3) { var w = new Date(+p.y, +p.m - 1, +p.d).getDay(); return '<div><div class="day">' + +p.d + '</div><div class="dow">' + DOW[w] + '</div></div>'; }
    return '<div><div class="day soft">' + esc(d ? fd(d) : 'Termin offen') + '</div></div>';
  }
  function get(qs) { return fetch('/api/data.php?' + qs, { credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function post(url, data) { return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data || {}) }).then(function (r) { return r.json(); }); }
  function typeLabel(t) { return (CATS.filter(function (c) { return c[0] === t; })[0] || [])[2] || t; }

  function setSkin(s) { var h = document.documentElement; if (s === 'auto' || !s) h.removeAttribute('data-skin'); else h.setAttribute('data-skin', s); S.skin = s; }
  if (S.skin) setSkin(S.skin);

  function nav() {
    var h = '<button class="nav" data-c="cal" ' + (cat === 'cal' ? 'aria-current="page"' : '') + '>' + ico('cal') + 'Kalender</button>';
    CATS.forEach(function (c) {
      var n = all.filter(function (e) { return e.type === c[0]; }).length;
      h += '<button class="nav" data-c="' + c[0] + '" ' + (cat === c[0] ? 'aria-current="page"' : '') + '>' + ico(c[0]) + c[1] + '<small>' + n + '</small>' + '</button>';
    });
    if (S.admin) h += '<button class="nav rg" data-c="users" ' + (cat === 'users' ? 'aria-current="page"' : '') + '>Nutzer' + (pend ? '<span class="badge">' + pend + '</span>' : '') + '</button>';
    h += '<button class="nav' + (S.admin ? '' : ' rg') + '" data-c="set" ' + (cat === 'set' ? 'aria-current="page"' : '') + '>Einstellungen</button>';
    topbar.innerHTML = h;
    topbar.querySelectorAll('.nav').forEach(function (b) { b.onclick = function () { cat = b.dataset.c; sel = null; adding = false; q = ''; genre = ''; pop = false; render(); }; });
  }
  function render() { nav(); if (cat === 'cal') return calendar(); if (cat === 'users') return users(); if (cat === 'set') return settings(); listView(); }

  /* ---------- Listen mit linker Spalte ---------- */
  function sorted(l) {
    var c = l.slice();
    if (sort === 'next') c.sort(function (a, b) { return (a.next_date || '9') < (b.next_date || '9') ? -1 : (a.next_date || '9') > (b.next_date || '9') ? 1 : a.name.localeCompare(b.name, 'de'); });
    else if (sort === 'count') c.sort(function (a, b) { return (b.upcoming | 0) - (a.upcoming | 0) || a.name.localeCompare(b.name, 'de'); });
    else c.sort(function (a, b) { return a.name.localeCompare(b.name, 'de'); });
    return c;
  }
  var genre = '', pop = false;
  var SORTS = [['name', 'A–Z'], ['next', 'Nächster Termin zuerst'], ['count', 'Meiste Termine zuerst']];
  function genreList() {
    var s = {}; all.filter(function (e) { return e.type === cat; }).forEach(function (e) { (e.genres || []).forEach(function (g) { s[g] = (s[g] || 0) + 1; }); });
    return Object.keys(s).sort(function (a, b) { return s[b] - s[a] || a.localeCompare(b, 'de'); }).slice(0, 40);
  }
  function listView() {
    var plural = CATS.filter(function (c) { return c[0] === cat; })[0];
    view.innerHTML = '<div class="layout has-rail"><aside class="rail" id="rail" data-cat="' + cat + '" style="--cc:var(--c-' + cat + ')"><div class="label">' + plural[1] + '</div>' +
      '<div><div class="srchrow"><input type="search" id="q" placeholder="Suchen …" value="' + esc(q) + '" aria-label="Suchen"><button class="filtbtn" id="filtbtn" type="button" aria-label="Filtern und sortieren" aria-expanded="false">' +
      '<svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span class="fbadge" id="fbadge" hidden></span></button><div class="filtpop" id="filtpop" hidden></div></div><div class="fchips" id="fchips" style="margin-top:8px"></div></div>' +
      '<div class="addrow"><button class="btn small" id="addbtn" type="button">+ ' + plural[2] + ' hinzufügen</button></div><div id="addbox"></div><div class="list" id="list"></div></aside><section class="main" id="main"></section></div>';
    document.getElementById('q').oninput = function () { q = this.value; rows(); };
    document.getElementById('filtbtn').onclick = function (ev) { ev.stopPropagation(); pop = !pop; filt(); };
    document.getElementById('addbtn').onclick = function () { adding = !adding; addForm(); };
    document.getElementById('filtpop').onclick = function (ev) { ev.stopPropagation(); };
    document.addEventListener('click', function () { if (pop) { pop = false; filt(); } });
    filt(); rows(); mainPane(); if (adding) addForm();
  }
  function filt() {
    var p = document.getElementById('filtpop'); if (!p) return;
    var n = (sort !== 'name' ? 1 : 0) + (genre ? 1 : 0), gs = genreList();
    var bd = document.getElementById('fbadge'); bd.hidden = !n; bd.textContent = n;
    document.getElementById('filtbtn').setAttribute('aria-expanded', pop);
    document.getElementById('fchips').innerHTML = (sort !== 'name' ? '<span class="fchip">' + esc(SORTS.filter(function (s) { return s[0] === sort; })[0][1]) + '</span>' : '') + (genre ? '<span class="fchip">' + esc(genre) + '</span>' : '');
    p.hidden = !pop; if (!pop) return;
    p.innerHTML = '<div class="label">Sortieren</div>' + SORTS.map(function (s) { return '<button type="button" class="fopt" data-s="' + s[0] + '" aria-pressed="' + (sort === s[0]) + '">' + s[1] + '</button>'; }).join('') +
      (gs.length ? '<div class="label">Genre</div><select id="gsel" aria-label="Genre"><option value="">Alle Genres</option>' + gs.map(function (g) { return '<option' + (g === genre ? ' selected' : '') + '>' + esc(g) + '</option>'; }).join('') + '</select>' : '');
    p.querySelectorAll('.fopt').forEach(function (b) { b.onclick = function () { sort = b.dataset.s; filt(); rows(); }; });
    var gsel = document.getElementById('gsel'); if (gsel) gsel.onchange = function () { genre = this.value; filt(); rows(); };
  }
  function rows() {
    var l = sorted(all.filter(function (e) { return e.type === cat && (!q || e.name.toLowerCase().indexOf(q.toLowerCase()) >= 0) && (!genre || (e.genres || []).indexOf(genre) >= 0); }));
    var box = document.getElementById('list');
    if (!l.length) { box.innerHTML = '<p class="hint">' + (q || genre ? 'Nichts gefunden.' : 'Noch nichts in dieser Liste.') + '</p>'; return; }
    box.innerHTML = l.map(function (e) {
      var tp = !e.fetched_at ? 'noch nicht geladen' : e.next_date ? 'nächster Termin ' + fd(e.next_date) : e.works ? e.works + (e.type === 'series' ? ' Staffeln' : ' Werke') : 'keine Termine';
      return '<button class="row-btn" data-id="' + e.id + '" ' + (sel == e.id ? 'aria-current="true"' : '') + '><span class="nm">' + esc(e.name) + '</span><span class="tp">' + esc(tp) + '</span>' + (e.next_date ? '<span class="ct" title="Kommende Veröffentlichungen">' + e.upcoming + '</span>' : '') + '</button>';
    }).join('');
    box.querySelectorAll('.row-btn').forEach(function (b) { b.onclick = function () { sel = b.dataset.id; rows(); mainPane(); if (window.innerWidth < 900) document.getElementById('main').scrollIntoView({ behavior: 'smooth' }); }; });
  }
  function mainPane() {
    var m = document.getElementById('main');
    if (!sel) {
      var n = all.filter(function (e) { return e.type === cat; }).length;
      m.innerHTML = '<div class="empty"><h2>' + (n ? 'Wähle einen Eintrag' : 'Noch nichts hier') + '</h2><p>' + (n ? 'Links in der Liste auf einen Namen klicken, dann siehst du Werke und kommende Termine.' : 'Mit „+ Neu“ legst du den ersten Eintrag an.') + '</p></div>'; return;
    }
    entry(sel);
  }
  function entry(id) {
    var m = document.getElementById('main') || view;
    m.innerHTML = '<p class="state">Lade …</p>';
    get('a=entry&id=' + id).then(function (r) {
      if (!r.entry) { m.innerHTML = '<p class="state msg err">' + esc(r.error) + '</p>'; return; }
      var e = r.entry, nw = e.total_works, h = '<article class="card cat-' + e.type + '" style="--cc:var(--c-' + e.type + ')"><div class="card-head"><div class="ch1"><div class="meta"><span class="mcat">' + ico(e.type) + typeLabel(e.type) + '</span>' +
        (nw ? '<span class="mbadge">' + nw + (e.type === 'series' ? ' Staffeln' : ' Werke') + '</span>' : '') + (r.releases.length ? '<span class="mbadge">' + r.releases.length + ' kommend</span>' : '') + '</div><h2>' + esc(e.name) + '</h2>';
      if ((e.genres || []).length) h += '<div class="gchips">' + e.genres.map(function (g) { return '<span class="gchip">' + esc(g) + '</span>'; }).join('') + '</div>';
      h += '</div><div class="actions"><button class="btn warn small" id="rm" type="button">Aus meiner Liste entfernen</button></div></div>';
      if (e.summary) h += '<p class="sum">' + esc(e.summary) + '</p>';
      if (!e.fetched_at && e.type !== 'film') h += '<p class="state pulse" id="st">Daten werden geladen …</p>';
      else if (e.type === 'film') h += '<p class="hint">Für Filme wird die Datenquelle noch angebunden.</p>';
      h += '<h3 class="sec">Kommende Termine</h3>' + (r.releases.length ? r.releases.map(function (x) { return '<div class="rel compact">' + dayBox(x.release_date).replace('class="day"', 'class="day"') + '<div><span class="ttl" style="font-size:15px">' + esc(x.title) + '</span>' + (x.kind ? ' <span class="sub">· ' + esc(x.kind) + '</span>' : '') + '</div></div>'; }).join('') : '<p class="hint">Keine bekannt.</p>');
      h += '<h3 class="sec">Werke</h3>' + (r.works.length ? r.works.map(function (x) { return '<div class="work"><span class="yr">' + esc(x.year || '') + '</span><span class="ttl" style="font-size:15px">' + esc(x.title) + '</span><span class="sub">' + esc(x.note || x.kind || '') + '</span></div>'; }).join('') : '<p class="hint">Noch keine.</p>');
      m.innerHTML = h + '</article>';
      document.getElementById('rm').onclick = function () {
        if (!confirm('„' + e.name + '“ aus deiner Liste entfernen?')) return;
        post('/api/data.php?a=remove&id=' + id).then(function () { sel = null; load(); });
      };
      if (document.getElementById('st')) post('/api/data.php?a=refresh&id=' + id).then(function (x) {
        if (x.ok) load(function () { if (sel == id) entry(id); }); else { var st = document.getElementById('st'); if (st) { st.classList.remove('pulse'); st.textContent = x.msg || x.error; } load(); }
      });
    });
  }
  function addForm() {
    var box = document.getElementById('addbox'); if (!box) return;
    if (!adding) { box.innerHTML = ''; return; }
    var c = CATS.filter(function (x) { return x[0] === cat; })[0], live = cat !== 'film';
    box.innerHTML = '<form id="addf" class="railtools"><input type="text" id="addq" placeholder="' + c[2] + (live ? ' suchen' : ' eingeben') + '" aria-label="Name"><button class="btn small" type="submit">' + (live ? 'Suchen' : 'Hinzufügen') + '</button></form><div id="addres" class="suggest"></div>';
    var inp = document.getElementById('addq'); inp.focus();
    function add(name, ext) {
      post('/api/data.php?a=add', { type: cat, name: name, ext: ext || {} }).then(function (r) {
        if (r.ok) { adding = false; sel = r.id; load(function () { render(); }); } else document.getElementById('addres').innerHTML = '<p class="msg err">' + esc(r.error) + '</p>';
      });
    }
    document.getElementById('addf').onsubmit = function (ev) {
      ev.preventDefault(); var v = inp.value.trim(); if (v.length < 2) return; if (!live) return add(v);
      var res = document.getElementById('addres'); res.innerHTML = '<p class="hint">Suche …</p>';
      get('a=search&type=' + cat + '&q=' + encodeURIComponent(v)).then(function (r) {
        var l = r.results || [];
        res.innerHTML = l.map(function (x, i) { return '<button type="button" class="row-btn" data-i="' + i + '"><span class="nm">' + esc(x.name) + '</span><span class="tp">' + esc(x.hint) + '</span></button>'; }).join('') +
          '<button type="button" class="row-btn" data-i="-1"><span class="tp">„' + esc(v) + '“ trotzdem so hinzufügen</span></button>';
        res.querySelectorAll('.row-btn').forEach(function (b) { b.onclick = function () { var i = +b.dataset.i; i < 0 ? add(v) : add(l[i].name, l[i].ext); }; });
      });
    };
  }

  /* ---------- Kalender ---------- */
  function calendar() {
    view.innerHTML = '<p class="state">Lade …</p>';
    get('a=calendar').then(function (r) {
      var l = r.releases || [], g = {}, order = [];
      l.forEach(function (x) { var p = parts(x.release_date), k = x.release_date ? p.y + '-' + (p.m || '00') : 'offen'; if (!g[k]) { g[k] = []; order.push(k); } g[k].push(x); });
      order.sort(function (a, b) { return a === 'offen' ? 1 : b === 'offen' ? -1 : a < b ? -1 : 1; });
      if (!order.length) { view.innerHTML = '<div class="empty"><h2>Keine Termine</h2><p>Sobald deine Einträge kommende Veröffentlichungen haben, erscheinen sie hier.</p></div>'; return; }
      view.innerHTML = '<div class="main">' + order.map(function (k) {
        var title = k === 'offen' ? 'Termin offen' : (k.split('-')[1] === '00' ? k.split('-')[0] : MON[+k.split('-')[1] - 1] + ' ' + k.split('-')[0]);
        return '<section class="month"><h3>' + title + '</h3>' + g[k].map(function (x) {
          return '<div class="rel wide cal cat-' + x.type + '" data-t="' + x.type + '" data-id="' + x.subject_id + '">' + dayBox(x.release_date) + '<div><div class="ttl">' + esc(x.name) + '</div><div class="sub">' + esc(x.title) + '</div></div></div>';
        }).join('') + '</section>';
      }).join('') + '</div>';
      view.querySelectorAll('.cal').forEach(function (n) { n.onclick = function () { cat = n.dataset.t; sel = n.dataset.id; render(); }; });
    });
  }

  /* ---------- Nutzer (Admin) ---------- */
  function users() {
    view.innerHTML = '<p class="state">Lade …</p>';
    fetch('/api/admin.php?a=users', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
      var l = r.users || []; pend = l.filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; nav();
      view.innerHTML = '<div class="main"><div class="label" style="margin-bottom:6px">Nutzerverwaltung</div>' + l.map(function (x) {
        var ok = x.role === 'admin' || x.approved_at, me = x.id == r.me;
        return '<div class="arow"><div><b>Nr. ' + x.id + ' · ' + esc(x.email) + '</b>' + (x.role === 'admin' ? '<span class="tier">Admin</span>' : '') + '<div class="meta2">' + (ok ? 'freigeschaltet' : '<b style="color:var(--accent)">wartet auf Freischaltung</b>') + (x.email_verified_at ? '' : ' · E-Mail nicht bestätigt') + ' · ' + x.entries + ' Einträge · seit ' + esc((x.created_at || '').slice(0, 10)) + '</div></div>' +
          '<div class="acts">' + (me || x.role === 'admin' ? '' : (ok ? '<button class="btn ghost small" data-a="revoke" data-id="' + x.id + '">Sperren</button>' : '<button class="btn small" data-a="approve" data-id="' + x.id + '">Freischalten</button>') + '<button class="btn warn small" data-a="delete" data-id="' + x.id + '">Löschen</button>') + '</div></div>';
      }).join('') + '</div>';
      view.querySelectorAll('button[data-a]').forEach(function (b) {
        b.onclick = function () {
          if (b.dataset.a === 'delete' && !confirm('Konto samt Liste wirklich löschen?')) return;
          post('/api/admin.php?a=' + b.dataset.a, { id: +b.dataset.id }).then(function (x) { if (x.ok) users(); else alert(x.error); });
        };
      });
    });
  }

  /* ---------- Einstellungen ---------- */
  function settings() {
    view.innerHTML = '<div class="main"><div class="label" style="margin-bottom:6px">Konto</div><p>' + esc(S.email) + ' · Mitglied Nr. ' + S.id + (S.admin ? ' · Admin' : '') + '</p>' +
      '<div class="label" style="margin:22px 0 8px">Benachrichtigungen</div><p class="hint" style="margin-bottom:10px">Du bekommst eine Mail, wenn bei deinen Einträgen neue Termine angekündigt werden und wenn ein Termin in den nächsten 7 Tagen ansteht.</p>' +
      '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" id="ntf"' + (S.notify ? ' checked' : '') + '> E-Mail-Benachrichtigungen einschalten</label>' +
      '<p style="margin:10px 0 0"><button class="btn ghost small" id="tm" type="button">Testmail an mich senden</button> <span class="msg" id="tmmsg"></span></p>' +
      '<div class="label" style="margin:22px 0 8px">Aussehen</div><div class="skins">' + SKINS.map(function (k) {
        return '<button class="skin" data-s="' + k[0] + '" aria-pressed="' + ((S.skin || 'auto') === k[0]) + '"><span class="dots">' + k[2].map(function (c) { return '<i style="background:' + c + '"></i>'; }).join('') + '</span><b>' + k[1] + '</b></button>';
      }).join('') + '</div><p style="margin-top:24px"><button class="btn ghost" id="lo2" type="button">Abmelden</button></p></div>';
    view.querySelectorAll('.skin').forEach(function (b) { b.onclick = function () { setSkin(b.dataset.s); post('/api/data.php?a=skin', { skin: b.dataset.s }); settings(); }; });
    document.getElementById('ntf').onchange = function () { S.notify = this.checked; post('/api/data.php?a=notify', { on: this.checked }); };
    document.getElementById('tm').onclick = function () { var m = document.getElementById('tmmsg'); m.className = 'msg'; m.textContent = 'Sende …'; post('/api/data.php?a=testmail').then(function (r) { m.className = 'msg' + (r.ok ? '' : ' err'); m.textContent = r.msg || r.error || ''; }); };
    document.getElementById('lo2').onclick = function () { post('/api/auth.php?a=logout').then(function () { location.reload(); }); };
  }

  function load(cb) { get('a=list').then(function (r) { all = r.entries || []; if (cb) { nav(); if (document.getElementById('list')) rows(); cb(); } else render(); }); }
  load();
  if (S.admin) fetch('/api/admin.php?a=users', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) { pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; nav(); });
})();
