(function () {
  var D = window.SD || {}, root = document.getElementById('appview');
  if (!root) return;
  var CL = window.CHANGELOG || [], LATEST = CL.length ? CL[0].v : 1;
  var LOGTOTAL = CL.reduce(function (a, c) { return a + (c.base ? 0 : c.t.length); }, 0);
  var TYPES = {
    author: { label: 'Autor:in', plural: 'Autor:innen', ph: 'z. B. Terry Pratchett', idx: 'stripbooks', work: 'Bücher' },
    artist: { label: 'Künstler:in', plural: 'Künstler:innen', ph: 'z. B. Radiohead', idx: 'music', work: 'Alben' },
    film: { label: 'Film', plural: 'Filme', ph: 'z. B. Dune', idx: 'video', work: 'Filme' },
    series: { label: 'Serie', plural: 'Serien', ph: 'z. B. Severance', idx: 'video', work: 'Staffeln' }
  };
  var ORDER = ['author', 'artist', 'film', 'series'], MAXN = 100;
  var CATCOL = { author: 'var(--c-author)', artist: 'var(--c-artist)', film: 'var(--c-film)', series: 'var(--c-series)' };
  var SKINS = [['', 'System', ['#f2f2f0', '#db4d1a', '#101419']], ['light', 'Hell', ['#f2f2f0', '#db4d1a', '#11171d']], ['dark', 'Dunkel', ['#101419', '#ff7842', '#e8ecee']],
    ['paper', 'Papier', ['#f3ead6', '#a23f1c', '#2b2218']], ['cinema', 'Kino', ['#0b0b10', '#e8b830', '#f2ede0']], ['mint', 'Minze', ['#e6f2ee', '#0c7560', '#0f2e28']], ['eighties', '80er', ['#160a2e', '#ff4fb0', '#2de2ff']]];
  var SORTS = [['alpha', 'Alphabetisch'], ['works', 'Anzahl Werke'], ['last', 'Letzte Veröffentlichung'], ['next', 'Nächste Veröffentlichung']];
  var ICONS = {
    author: '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM20 17v4H6.5A2.5 2.5 0 0 1 4 18.5"/></svg>',
    artist: '<svg viewBox="0 0 24 24"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    film: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/></svg>',
    series: '<svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="m17 2-5 5-5-5"/></svg>',
    cal: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    user: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    bell: '<svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    bellOff: '<svg viewBox="0 0 24 24"><path d="M13.7 21a1.94 1.94 0 0 1-3.4 0M17 17H3s3-2 3-9a6 6 0 0 1 .3-1.9M8.7 3.3A6 6 0 0 1 18 8c0 2.5.4 4.4.9 5.7M2 2l20 20"/></svg>',
    refresh: '<svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-15-6.7L3 8M3 3v5h5M3 12a9 9 0 0 0 15 6.7L21 16M21 21v-5h-5"/></svg>'
  };

  var S = { n: D.n || { 'new': 1, day: 1, week: 0, mode: 'digest', weekday: 1, bell_default: 1 }, relink: null, epTried: {}, view: 'cal', cat: 'cal', sel: null, entries: [], rels: [], site: { tag: '', domain: 'amazon.de' }, ready: false, filter: '', sort: {}, genre: {}, confirm: null, menuFor: null,
    unread: 0, pend: 0, adding: false, sugg: null, msg: '', msgErr: false, notify: D.notify, skin: D.skin === 'auto' ? '' : (D.skin || ''), sup: { sel: null, threads: [] } };
  var $ = function (id) { return document.getElementById(id); };
  var topbar = $('topbar'), mainEl = root, supTimer = null;

  function h(tag, attrs) {
    var el = document.createElement(tag), k, v;
    attrs = attrs || {};
    for (k in attrs) { v = attrs[k]; if (v == null || v === false) continue; if (k === 'class') el.className = v; else if (k.indexOf('on') === 0) el.addEventListener(k.slice(2), v); else el.setAttribute(k, v === true ? '' : v); }
    for (var i = 2; i < arguments.length; i++) { var kids = [].concat(arguments[i]); for (var j = 0; j < kids.length; j++) { var c = kids[j]; if (c == null || c === false) continue; el.append(c.nodeType ? c : document.createTextNode(String(c))); } }
    return el;
  }
  function ico(svg, cls) { var s = document.createElement('span'); s.className = cls || 'ico'; s.setAttribute('aria-hidden', 'true'); s.innerHTML = svg; return s; }
  function api(url, data) {
    var o = { credentials: 'same-origin' };
    if (data !== undefined) { o.method = 'POST'; o.headers = { 'Content-Type': 'application/json' }; o.body = JSON.stringify(data || {}); }
    return fetch(url, o).then(function (r) { return r.json().catch(function () { return { error: 'Unerwartete Antwort' }; }); });
  }

  /* ---------- Datum ---------- */
  var MONTH = new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric' }), MSHORT = new Intl.DateTimeFormat('de-DE', { month: 'short' }),
    WDAY = new Intl.DateTimeFormat('de-DE', { weekday: 'short' }), LONG = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  function today0() { var n = new Date(); return new Date(n.getFullYear(), n.getMonth(), n.getDate()); }
  function parseDate(s) {
    var m = /^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/.exec(String(s || '').trim());
    if (!m) { var f = new Date(9999, 11, 31); return { prec: 'none', y: 9999, start: f, end: f, sort: f.getTime() }; }
    var y = +m[1], mo = m[2] ? +m[2] : 0, d = m[3] ? +m[3] : 0;
    if (d) { var dt = new Date(y, mo - 1, d); return { prec: 'day', y: y, m: mo, d: d, start: dt, end: dt, sort: dt.getTime() }; }
    if (mo) { var st = new Date(y, mo - 1, 1), en = new Date(y, mo, 0); return { prec: 'month', y: y, m: mo, start: st, end: en, sort: en.getTime() }; }
    return { prec: 'year', y: y, start: new Date(y, 0, 1), end: new Date(y, 11, 31), sort: new Date(y, 11, 31).getTime() };
  }
  function fmtP(p) { return !p ? '' : p.prec === 'day' ? LONG.format(p.start) : p.prec === 'month' ? MONTH.format(p.start) : p.prec === 'none' ? 'offen' : String(p.y); }

  /* ---------- Daten ---------- */
  function entriesOf(t) { return S.entries.filter(function (e) { return e.type === t; }).sort(function (a, b) { return a.name.localeCompare(b.name, 'de'); }); }
  function itemsOf(e) { // kommende Veröffentlichungen eines Eintrags
    return S.rels.filter(function (r) { return r.subject_id == e.id; }).map(toItem);
  }
  function toItem(r) {
    var e = S.entries.filter(function (x) { return x.id == r.subject_id; })[0] || { id: r.subject_id, name: r.name, type: r.type };
    return { e: e, p: parseDate(r.release_date), u: { title: r.title, date: r.release_date, kind: r.kind || '', note: r.note || '' } };
  }
  function nextRel(e) { var l = itemsOf(e); return l.length ? l.reduce(function (a, b) { return a.p.sort <= b.p.sort ? a : b; }).p : null; }
  function sortedEntries(t) {
    var mode = S.sort[t] || 'alpha', es = entriesOf(t);
    if (mode === 'alpha') return es;
    var k = es.map(function (e) {
      if (mode === 'works') return { e: e, k: e.works | 0 };
      if (mode === 'last') return { e: e, k: e.last_year ? +e.last_year : null, lab: e.last_year ? 'zuletzt ' + e.last_year : '' };
      var n = nextRel(e); return { e: e, k: n ? n.sort : null, lab: n ? 'nächste ' + fmtP(n) : '' };
    });
    k.sort(function (a, b) { if (a.k == null && b.k == null) return 0; if (a.k == null) return 1; if (b.k == null) return -1; return mode === 'next' ? a.k - b.k : b.k - a.k; });
    return k.map(function (x) { x.e._lab = x.lab || ''; return x.e; });
  }
  function load(cb) {
    Promise.all([api('/api/data.php?a=list'), api('/api/data.php?a=calendar')]).then(function (r) {
      if (r[0].pending) { location.reload(); return; }
      S.entries = (r[0].entries || []).map(function (e) { e.id = String(e.id); e.works = +e.works || 0; e.upcoming = +e.upcoming || 0; return e; });
      S.site = r[0].site || S.site; S.rels = r[1].releases || []; S.ready = true;
      if (cb) cb(); else render();
    });
  }
  function amazonUrl(e, title) {
    var d = S.site.domain || 'amazon.de', t = TYPES[e.type] || TYPES.film, term = [title, (e.type === 'author' || e.type === 'artist') ? e.name : ''].filter(Boolean).join(' '), idx = t.idx;
    if (idx === 'music') idx = d === 'amazon.com' ? 'music' : 'popular'; else if (idx === 'video') idx = d === 'amazon.com' ? 'movies-tv' : 'dvd';
    var u = 'https://www.' + d + '/s?k=' + encodeURIComponent(term) + '&i=' + idx; if (S.site.tag) u += '&tag=' + encodeURIComponent(S.site.tag); return u;
  }
  function amazonLink(e, title) { return h('a', { class: 'go', href: amazonUrl(e, title), target: '_blank', rel: 'sponsored noopener noreferrer' }, 'Bei Amazon'); }
  function say(t, err) { S.msg = t || ''; S.msgErr = !!err; var m = $('msg'); if (m) { m.textContent = S.msg; m.className = 'msg' + (err ? ' err' : ''); } }

  /* ---------- Kopf und Leiste ---------- */
  function logBadge() { var s; try { s = parseInt(localStorage.getItem('sd-seen'), 10); } catch (_) {} if (isNaN(s)) return 0; return Math.max(0, LOGTOTAL - s); }
  function markSeen() { try { localStorage.setItem('sd-seen', String(LOGTOTAL)); } catch (_) {} }
  function renderVer() {
    var v = $('ver'); if (!v) return; var n = logBadge();
    v.textContent = 'Version ' + LATEST; if (n) v.append(h('span', { class: 'badge', 'aria-label': n + ' neue Änderungen' }, n > 99 ? '99+' : String(n)));
    v.setAttribute('aria-pressed', String(S.view === 'log')); v.title = 'Versionsverlauf';
  }
  function go(view, cat) { S.view = view; S.cat = cat || view; S.sel = null; S.confirm = null; S.menuFor = null; S.filter = ''; S.adding = false; S.sugg = null; say(''); render(); window.scrollTo(0, 0); }
  function nav() {
    topbar.textContent = '';
    function btn(key, text, count, extra) {
      var cur = S.view === 'list' ? S.cat === key : S.view === key;
      return h('button', { class: 'nav' + (extra || ''), type: 'button', 'aria-current': cur ? 'page' : null, onclick: function () { /^(author|artist|film|series)$/.test(key) ? go('list', key) : go(key); } },
        key === 'cal' ? ico(ICONS.cal) : null, /^(author|artist|film|series)$/.test(key) ? h('i', { class: 'cdot c-' + key, 'aria-hidden': 'true' }) : null, text, count ? h('small', null, String(count)) : null);
    }
    topbar.append(btn('cal', 'Kalender', S.rels.length));
    ORDER.forEach(function (k) { topbar.append(btn(k, TYPES[k].plural, entriesOf(k).length)); });
    var rg = ' rg';
    if (D.admin) { var b = btn('adm', 'Admin', 0, ' adminnav' + rg); if (S.pend) b.append(h('span', { class: 'badge' }, String(S.pend))); topbar.append(b); rg = ''; }
    topbar.append(btn('set', 'Einstellungen', 0, ' settings' + rg), btn('info', 'Info', 0, ' info'));
    var k = btn('sup', 'Kontakt', 0, ' support'); if (S.unread) k.append(h('span', { class: 'badge', 'aria-label': S.unread + ' ungelesen' }, S.unread > 99 ? '99+' : String(S.unread))); topbar.append(k);
    renderVer();
  }
  function render() {
    nav(); mainEl.textContent = '';
    if (!S.ready) { mainEl.append(h('p', { class: 'state pulse' }, 'Lade deine Liste …')); return; }
    clearInterval(supTimer);
    var v = S.view;
    if (v === 'cal') mainEl.append(renderCalendar());
    else if (v === 'list') listView();
    else if (v === 'set') settings(); else if (v === 'info') info(); else if (v === 'log') logView(); else if (v === 'sup') support(); else if (v === 'adm') admin();
  }

  /* ---------- Kalender ---------- */
  function relRow(it, cal) {
    var p = it.p, now = today0(), left;
    if (p.prec === 'day') left = h('div', null, h('div', { class: 'day' }, cal ? String(p.d) : p.d + '. ' + MSHORT.format(p.start).replace('.', '')), h('div', { class: 'dow' }, WDAY.format(p.start).replace('.', '') + (cal ? '' : ' ' + p.y)));
    else if (p.prec === 'month') left = h('div', { class: 'day soft' }, cal ? 'Tag offen' : MONTH.format(p.start));
    else if (p.prec === 'none') left = h('div', { class: 'day soft' }, 'offen'); else left = h('div', { class: 'day soft' }, String(p.y));
    var diff = Math.round((p.start - now) / 864e5), soon = p.prec === 'day' && diff <= 60 && diff >= 0 ? (diff === 0 ? 'heute' : diff === 1 ? 'morgen' : 'in ' + diff + ' Tagen') : '';
    var sub = h('div', { class: 'sub' }, h('b', null, it.e.name), it.u.kind && it.u.kind !== 'Folge' ? ' · ' + it.u.kind : '', it.u.note ? ' · ' + it.u.note : '');
    var kindTxt = (it.u.kind && it.u.kind !== 'Folge' ? ' · ' + it.u.kind : '') + (it.u.note ? ' · ' + it.u.note : '');
    if (cal) return h('div', { class: 'rel calrow c-' + it.e.type }, h('i', { class: 'cbar', 'aria-hidden': 'true' }), left,
      h('div', { class: 'one' }, nameLink(it.e), ' – ' + it.u.title + kindTxt), soon ? h('span', { class: 'soon' }, soon) : null);
    return h('div', { class: 'rel wide hasacts' }, left,
      h('div', null, h('div', { class: 'ttl' }, it.u.title), sub, soon ? h('div', { class: 'soon' }, soon) : null),
      h('div', { class: 'acts' }, amazonLink(it.e, it.u.title)));
  }
  function nameLink(e) { return h('button', { class: 'namelink', type: 'button', title: 'Seite von ' + e.name + ' öffnen', onclick: function () { S.view = 'list'; S.cat = e.type; S.sel = String(e.id); S.confirm = null; S.filter = ''; render(); window.scrollTo(0, 0); } }, e.name); }
  function groupByMonth(items) {
    var g = {}, order = [], wrap = h('div', null);
    items.forEach(function (it) { var key = (it.p.prec === 'year' || it.p.prec === 'none') ? 'later' : it.p.y + '-' + String(it.p.m).padStart(2, '0'); if (!g[key]) { g[key] = []; order.push(key); } g[key].push(it); });
    order.sort(function (a, b) { if (a === 'later') return 1; if (b === 'later') return -1; return a < b ? -1 : 1; });
    order.forEach(function (key) {
      var l = g[key]; l.sort(function (a, b) { return a.p.sort - b.p.sort || String(a.u.title).localeCompare(String(b.u.title), 'de'); });
      var sec = h('section', { class: 'month' }, h('h3', null, key === 'later' ? 'Termin noch offen' : MONTH.format(l[0].p.start)));
      l.forEach(function (it) { sec.append(relRow(it, true)); }); wrap.append(sec);
    });
    return wrap;
  }
  function renderMonths(items) {
    var now = today0(), byDay = {};
    items.forEach(function (it) { if (it.p.prec !== 'day') return; var k = it.p.y + '-' + it.p.m + '-' + it.p.d, o = byDay[k] || (byDay[k] = { n: 0, cats: {} }); o.n++; o.cats[it.e.type] = 1; });
    var grid = h('div', { class: 'mgrid' });
    for (var mi = 0; mi < 4; mi++) {
      var first = new Date(now.getFullYear(), now.getMonth() + mi, 1), y = first.getFullYear(), m = first.getMonth() + 1, days = new Date(y, m, 0).getDate(), lead = (first.getDay() + 6) % 7;
      var cal = h('div', { class: 'mcal' }, ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'].map(function (d) { return h('div', { class: 'wd' }, d); }));
      for (var b = 0; b < lead; b++) cal.append(h('div', { class: 'dc' }));
      for (var d = 1; d <= days; d++) {
        var o = byDay[y + '-' + m + '-' + d], isT = y === now.getFullYear() && m === now.getMonth() + 1 && d === now.getDate(), cell = h('div', { class: 'dc' + (isT ? ' today' : '') });
        if (o) {
          var cats = ORDER.filter(function (k) { return o.cats[k]; }), step = 100 / cats.length;
          var stops = cats.map(function (k, i) { return CATCOL[k] + ' ' + (i * step) + '% ' + ((i + 1) * step) + '%'; }).join(', ');
          cell.append(h('span', { class: 'pie', style: 'background:conic-gradient(' + stops + ')', title: o.n + (o.n === 1 ? ' Termin' : ' Termine') + ': ' + cats.map(function (k) { return TYPES[k].plural; }).join(', ') }, h('span', { class: 'pn' }, String(d))));
        } else cell.append(h('span', { class: 'pn plain' }, String(d)));
        cal.append(cell);
      }
      grid.append(h('div', { class: 'mon' }, h('h3', null, MONTH.format(first)), cal));
    }
    return h('section', { class: 'months', 'aria-label': 'Monatsübersicht' }, grid, h('div', { class: 'legend' }, ORDER.map(function (k) { return h('span', null, h('i', { style: 'background:' + CATCOL[k] }), TYPES[k].plural); })));
  }
  function renderCalendar() {
    var items = S.rels.map(toItem), wrap = h('div', { class: 'main' });
    wrap.append(h('p', { class: 'note' }, 'Angekündigte Veröffentlichungen aus allen deinen Listen, nach Datum sortiert. Die Angaben kommen aus TVmaze, MusicBrainz und Open Library und werden alle 12 Stunden erneuert.'));
    if (!S.entries.length) { wrap.append(h('div', { class: 'empty' }, h('h2', null, 'Noch nichts auf deinen Listen'), h('p', null, 'Wähle oben eine Liste und füge etwas hinzu. Die kommenden Termine erscheinen dann hier.'))); return wrap; }
    if (!items.length) { wrap.append(h('p', { class: 'state' }, 'Keine angekündigten Termine bekannt.')); return wrap; }
    wrap.append(renderMonths(items)); wrap.append(groupByMonth(items)); return wrap;
  }

  /* ---------- Listen ---------- */
  function listView() {
    var t = TYPES[S.cat], es = entriesOf(S.cat);
    var layout = h('div', { class: 'layout has-rail' }), rail = h('aside', { class: 'rail', id: 'rail', 'data-cat': S.cat, style: '--cc:' + CATCOL[S.cat] }), main = h('section', { class: 'main', id: 'content' });
    rail.append(h('section', null, h('p', { class: 'label' }, 'Neu hinzufügen'),
      h('form', { class: 'add', id: 'addform', autocomplete: 'off', onsubmit: addSubmit }, h('input', { id: 'q', type: 'text', maxlength: '80', 'aria-label': 'Name', placeholder: t.ph }), h('button', { class: 'btn', id: 'addbtn', type: 'submit' }, S.cat === 'film' ? 'Hinzufügen' : 'Suchen')),
      h('p', { class: 'msg', id: 'msg', role: 'status' }), h('div', { id: 'sugg' })));
    rail.append(h('section', null, h('p', { class: 'label', id: 'listlbl' }, t.plural + ' · ' + es.length + ' / ' + MAXN),
      h('div', { class: 'srchrow' }, h('label', { class: 'field srch' }, h('input', { id: 'srch', type: 'search', maxlength: '80', 'aria-label': 'Einträge durchsuchen', placeholder: 'Liste durchsuchen', value: S.filter, oninput: function () { S.filter = this.value; rows(); } })),
        h('button', { class: 'btn ghost filtbtn', id: 'filtBtn', type: 'button', 'aria-label': 'Sortieren und filtern', 'aria-expanded': 'false', onclick: function (ev) { ev.stopPropagation(); var p = $('filtPop'); p.hidden = !p.hidden; this.setAttribute('aria-expanded', String(!p.hidden)); } },
          h('span', { class: 'fico', html: '' }), h('span', { class: 'fbadge', id: 'fbadge', hidden: true })),
        h('div', { class: 'filtpop', id: 'filtPop', hidden: true, onclick: function (ev) { ev.stopPropagation(); } })),
      h('div', { class: 'fchips', id: 'fchips' }), h('div', { class: 'list', id: 'list' })));
    layout.append(rail, main); mainEl.append(layout);
    $('filtBtn').insertAdjacentHTML('afterbegin', '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>');
    var fi = $('filtBtn').querySelector('.fico'); if (fi) fi.remove();
    filt(); rows(); content(); drawSugg();
  }
  function genresOf(cat) { var c = {}; entriesOf(cat).forEach(function (e) { (e.genres || []).forEach(function (g) { c[g] = (c[g] || 0) + 1; }); }); return Object.keys(c).sort(function (a, b) { return c[b] - c[a] || a.localeCompare(b, 'de'); }).slice(0, 25).map(function (g) { return [g, c[g]]; }); }
  function filt() {
    var cur = S.sort[S.cat] || 'alpha', g = S.genre[S.cat] || '', n = (cur !== 'alpha' ? 1 : 0) + (g ? 1 : 0), pop = $('filtPop'); pop.textContent = '';
    pop.append(h('p', { class: 'label' }, 'Sortieren nach'));
    SORTS.forEach(function (o) { pop.append(h('button', { class: 'fopt', type: 'button', 'aria-pressed': String(cur === o[0]), onclick: function () { S.sort[S.cat] = o[0]; filt(); rows(); } }, o[1])); });
    var gl = genresOf(S.cat);
    if (gl.length) { pop.append(h('p', { class: 'label', style: 'margin-top:14px' }, 'Genre'));
      var sel = h('select', { 'aria-label': 'Nach Genre filtern', onchange: function () { S.genre[S.cat] = this.value; filt(); rows(); } }, h('option', { value: '' }, 'Alle Genres'), gl.map(function (x) { return h('option', { value: x[0] }, x[0] + ' (' + x[1] + ')'); }));
      sel.value = g; pop.append(sel); }
    var b = $('fbadge'); b.hidden = !n; b.textContent = String(n);
    var ch = $('fchips'); ch.textContent = '';
    if (cur !== 'alpha') ch.append(h('button', { class: 'fchip', type: 'button', title: 'Sortierung zurücksetzen', onclick: function () { S.sort[S.cat] = 'alpha'; filt(); rows(); } }, SORTS.filter(function (o) { return o[0] === cur; })[0][1] + ' ×'));
    if (g) ch.append(h('button', { class: 'fchip', type: 'button', title: 'Genre-Filter zurücksetzen', onclick: function () { S.genre[S.cat] = ''; filt(); rows(); } }, g + ' ×'));
  }
  function worksLine(e) { var t = TYPES[e.type]; return !e.fetched_at ? 'noch nicht geladen' : e.works ? e.works + ' ' + (e.type === 'series' ? 'Staffeln' : e.type === 'author' ? 'Bücher' : e.type === 'artist' ? 'Alben' : 'Werke') : 'keine Daten'; }
  function rows() {
    var box = $('list'); box.textContent = '';
    var es = sortedEntries(S.cat), t = TYPES[S.cat], gq = S.genre[S.cat] || '', fq = (S.filter || '').trim().toLowerCase();
    if (!es.length) { box.append(h('p', { class: 'hint' }, 'Noch keine ' + t.plural + ' auf der Liste.')); return; }
    if (gq) es = es.filter(function (e) { return (e.genres || []).indexOf(gq) >= 0; });
    if (fq) es = es.filter(function (e) { return e.name.toLowerCase().indexOf(fq) >= 0; });
    if (!es.length) { box.append(h('p', { class: 'hint' }, fq ? 'Keine Treffer für „' + S.filter.trim() + '“.' : 'Keine Einträge im Genre „' + gq + '“.')); return; }
    es.forEach(function (e) {
      var mode = S.sort[S.cat], n = e.upcoming;
      box.append(h('button', { class: 'row-btn', type: 'button', 'aria-current': String(S.sel === e.id), onclick: function () { S.sel = e.id; S.confirm = null; S.menuFor = null; rows(); content(); if (window.innerWidth < 900) $('content').scrollIntoView({ behavior: 'smooth' }); } },
        h('span', { class: 'nm' }, e.name), h('span', { class: 'tp' }, (mode === 'last' || mode === 'next') ? (e._lab || 'keine Angabe') : worksLine(e)),
        n ? h('span', { class: 'ct', title: 'Kommende Veröffentlichungen' }, String(n)) : null,
        +e.bell === 0 ? h('span', { class: 'beloff', title: 'Keine Benachrichtigungen für diesen Eintrag' }, ico(ICONS.bellOff)) : null));
    });
  }
  function content() {
    var box = $('content'); box.textContent = '';
    var es = entriesOf(S.cat), t = TYPES[S.cat];
    if (!es.length) { box.append(h('div', { class: 'empty' }, h('h2', null, 'Noch keine ' + t.plural), h('p', null, 'Gib links einen Namen ein und wähle den passenden Treffer. Werke und angekündigte Neuerscheinungen werden dann automatisch geladen.'))); return; }
    var cur = es.filter(function (e) { return e.id === S.sel; })[0];
    if (!cur) { box.append(overview(S.cat)); return; }
    box.append(h('button', { class: 'backlink', type: 'button', onclick: function () { S.sel = null; S.confirm = null; rows(); content(); } }, '← Alle ' + t.plural));
    var holder = h('div', null, h('p', { class: 'state pulse' }, 'Lade …')); box.append(holder);
    api('/api/data.php?a=entry&id=' + cur.id).then(function (r) { if (S.sel !== cur.id) return; holder.replaceWith(entryCard(cur, r)); });
  }
  function fmtN(n) { try { return Number(n).toLocaleString('de-DE'); } catch (_) { return String(n); } }
  function overview(cat) {
    var t = TYPES[cat], es = entriesOf(cat), wrap = h('div', { class: 'overview' }), works = 0, ups = 0, eps = 0, epsKnown = 0, tot = 0, totKnown = 0, items = [];
    es.forEach(function (e) { works += e.works; var l = itemsOf(e); ups += l.length; items = items.concat(l); var ne = +e.episodes || 0; eps += ne; if (ne) epsKnown++; var tw = +e.total_works; if (tw > 0) { tot += tw; totKnown++; } });
    var tiles = cat === 'author' ? [['Autor:innen', es.length], ['Bücher in deiner Liste', works], ['Bücher insgesamt (geschätzt)', tot, totKnown ? 'Angabe bei ' + totKnown + ' von ' + es.length : 'noch keine Angaben'], ['Kommende Veröffentlichungen', ups]]
      : cat === 'artist' ? [['Künstler:innen', es.length], ['Alben in deiner Liste', works], ['Alben insgesamt (geschätzt)', tot, totKnown ? 'Angabe bei ' + totKnown + ' von ' + es.length : 'noch keine Angaben'], ['Kommende Veröffentlichungen', ups]]
      : cat === 'film' ? [['Filme', es.length], ['Filme und Teile der Reihen', works], ['Kommende Starts', ups]]
      : [['Serien', es.length], ['Staffeln', works], ['Folgen gesamt', eps, epsKnown ? 'Folgenzahl bei ' + epsKnown + ' von ' + es.length : 'noch keine Angaben'], ['Kommende Termine', ups]];
    wrap.append(h('h2', { class: 'sec' }, 'Übersicht ' + t.plural));
    if (cat === 'film') wrap.append(h('p', { class: 'warnbox' }, h('b', null, 'Hinweis: '), 'Die Datenquelle für Filme ist hier noch nicht angebunden. Du kannst Filme schon auf deine Liste setzen, Werke und Kinotermine erscheinen aber erst, wenn die Anbindung aktiviert ist.'));
    wrap.append(h('div', { class: 'ovtiles' }, tiles.map(function (x) { return h('div', { class: 'ovtile' }, h('div', { class: 'ovnum' }, fmtN(x[1])), h('div', { class: 'ovlbl' }, x[0]), x[2] ? h('div', { class: 'ovnote' }, x[2]) : null); })));
    items.sort(function (a, b) { return a.p.sort - b.p.sort || String(a.u.title).localeCompare(String(b.u.title), 'de'); });
    wrap.append(h('h3', { class: 'sec' }, 'Kommende Veröffentlichungen' + (items.length > 10 ? ' (die nächsten 10 von ' + items.length + ')' : '')));
    if (!items.length) wrap.append(h('p', { class: 'state' }, 'Keine angekündigten Termine bekannt.'));
    items.slice(0, 10).forEach(function (it) {
      var p = it.p, d = p.prec === 'day' ? p.d + '. ' + MSHORT.format(p.start).replace('.', '') + ' ' + p.y : p.prec === 'month' ? MONTH.format(p.start) : p.prec === 'none' ? 'Datum offen' : String(p.y);
      wrap.append(h('div', { class: 'ovrow' }, h('span', { class: 'ovd' }, d), h('span', { class: 'ovt' }, h('b', null, nameLink(it.e)), ' – ' + it.u.title + (it.u.kind && it.u.kind !== 'Folge' ? ' · ' + it.u.kind : ''))));
    });
    wrap.append(h('p', { class: 'hint' }, 'Wähle links einen Eintrag, um Werke und Termine zu sehen.'));
    return wrap;
  }
  function on_(b) { return b ? ICONS.bell : ICONS.bellOff; }
  function entryCard(cur, r) {
    var e = r.entry, card = h('article', { class: 'card cat-' + e.type, style: '--cc:' + CATCOL[e.type] }), t = TYPES[e.type];
    var stamp = e.fetched_at ? 'Stand ' + LONG.format(new Date(e.fetched_at.replace(' ', 'T'))) : '';
    var le = null; S.entries.forEach(function (x) { if (x.id === String(e.id)) le = x; });
    var bellOn = !le || +le.bell !== 0;
    var bellBtn = h('button', { class: 'btn ghost small bellbtn' + (bellOn ? ' on' : ''), type: 'button', title: bellOn ? 'Benachrichtigungen für diesen Eintrag sind an' : 'Benachrichtigungen für diesen Eintrag sind aus', 'aria-pressed': String(bellOn),
      onclick: function () { var on = !bellOn; if (le) le.bell = on ? 1 : 0; api('/api/data.php?a=bell&id=' + e.id, { on: on }); rows(); content(); } }, ico(on_(bellOn)), bellOn ? 'Benachrichtigung an' : 'Benachrichtigung aus');
    var actions = h('div', { class: 'actions' }, bellBtn,
      h('button', { class: 'btn ghost small', id: 'rfr', type: 'button', onclick: function () { refresh(cur); } }, ico(ICONS.refresh), 'Aktualisieren'),
      S.confirm === e.id ? [h('button', { class: 'btn warn small', type: 'button', onclick: function () { api('/api/data.php?a=remove&id=' + e.id, {}).then(function () { S.sel = null; S.confirm = null; load(); }); } }, 'Wirklich entfernen'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { S.confirm = null; content(); } }, 'Abbrechen')]
        : [h('button', { class: 'btn ghost small more', type: 'button', title: 'Weitere Aktionen', 'aria-label': 'Weitere Aktionen', 'aria-expanded': String(S.menuFor === e.id), onclick: function () { S.menuFor = S.menuFor === e.id ? null : e.id; content(); } }, '…'),
           S.menuFor === e.id && e.type !== 'film' ? h('button', { class: 'btn ghost small', type: 'button', onclick: function () { S.menuFor = null; S.relink = e.id; content(); } }, 'Treffer ändern') : null,
           S.menuFor === e.id ? h('button', { class: 'btn warn small', type: 'button', onclick: function () { S.menuFor = null; S.confirm = e.id; content(); } }, 'Entfernen') : null]);
    var meta = h('div', { class: 'meta' }, h('span', { class: 'mcat' }, ico(ICONS[e.type]), t.label), stamp ? h('span', { class: 'mstamp' }, stamp) : null);
    var gch = (e.genres || []).length ? h('div', { class: 'gchips' }, e.genres.map(function (g) { return h('span', { class: 'gchip' }, g); })) : null;
    card.append(h('div', { class: 'card-head' }, h('div', { class: 'ch1' }, meta, h('h2', null, e.name), gch), actions));
    if (S.relink === e.id) card.append(relinkBox(cur, e));
    else if (e.fetched_at && e.type !== 'film' && !r.works.length && !r.releases.length && !(r.episodes || []).length) card.append(h('p', { class: 'warnbox' }, 'Zu diesem Eintrag wurden keine Daten gefunden. Oft hilft ein anderer Titel, bei Serien und Filmen zum Beispiel der englische. ', h('button', { class: 'btn small', type: 'button', style: 'margin-left:6px', onclick: function () { S.relink = e.id; content(); } }, 'Treffer ändern')));
    if (!e.fetched_at && e.type !== 'film') { card.append(h('p', { class: 'state pulse', id: 'st' }, 'Daten werden geladen …')); refresh(cur, true); return card; }
    if (e.type === 'film') card.append(h('p', { class: 'state' }, 'Für Filme wird die Datenquelle noch angebunden. Der Eintrag steht schon auf deiner Liste.'));
    if (e.summary) card.append(h('p', { class: 'sum' }, e.summary));
    var up = r.releases.map(function (x) { return { e: e, p: parseDate(x.release_date), u: { title: x.title, date: x.release_date, kind: x.kind || '', note: x.note || '' } }; });
    card.append(h('h3', { class: 'sec' }, 'Kommende Veröffentlichungen'));
    if (up.length) up.forEach(function (it) { card.append(relRow(it)); }); else card.append(h('p', { class: 'state' }, 'Keine angekündigten Termine bekannt.'));
    if (e.type === 'series' && (r.episodes || []).length) { card.append(renderEpisodes(e, r.episodes)); return card; }
    if (e.type === 'series' && e.fetched_at && !S.epTried[e.id]) { S.epTried[e.id] = 1; refresh(cur, true); }
    card.append(h('h3', { class: 'sec' }, e.type === 'series' ? 'Staffeln' : 'Bekannte Werke'));
    if (!r.works.length) card.append(h('p', { class: 'state' }, e.type === 'film' ? '' : 'Keine Werke gefunden.'));
    else if (e.type !== 'series') card.append(h('p', { class: 'hint' }, r.works.length + ' ' + t.work + (+e.total_works > r.works.length ? ' von ca. ' + e.total_works : '') + ' aufgelistet.'));
    r.works.forEach(function (w) { card.append(h('div', { class: 'work' }, h('div', { class: 'yr' }, w.year || '–'), h('div', null, h('div', { class: 'ttl' }, w.title), (w.kind || w.note) ? h('div', { class: 'sub' }, [w.kind, w.note].filter(Boolean).join(' · ')) : null), amazonLink(e, w.title))); });
    return card;
  }
  function relinkBox(cur, e) {
    var box = h('div', { class: 'cfgpanel', style: 'margin:14px 0' }), inp = h('input', { type: 'text', maxlength: '80', placeholder: 'Anderer Titel oder Schreibweise', value: '' }), res = h('div', { class: 'suggest wide2' });
    function pick(x) { res.textContent = ''; res.append(h('p', { class: 'hint pulse' }, 'Lade Daten …')); api('/api/data.php?a=relink&id=' + e.id, { ext: x.ext }).then(function (r) { S.relink = null; load(function () { nav(); if ($('list')) { filt(); rows(); } content(); }); if (!r.ok) say(r.msg || 'Das hat nicht geklappt.', true); }); }
    function search() {
      var v = inp.value.trim(); if (v.length < 2) return; res.textContent = ''; res.append(h('p', { class: 'hint pulse' }, 'Suche …'));
      api('/api/data.php?a=search&type=' + e.type + '&q=' + encodeURIComponent(v)).then(function (r) {
        res.textContent = ''; var l = r.results || []; res.append(h('p', { class: 'hint' }, l.length ? 'Welcher Treffer ist gemeint?' : 'Keine Treffer.'));
        l.forEach(function (x) { res.append(h('button', { class: 'row-btn', type: 'button', onclick: function () { pick(x); } }, h('span', { class: 'nm' }, x.name), h('span', { class: 'tp' }, x.hint || ''))); });
      });
    }
    inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); search(); } });
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Treffer ändern'), h('p', { class: 'hint' }, 'Suche nach dem passenden Titel, zum Beispiel mit dem englischen Namen. Der Name in deiner Liste bleibt, die Daten kommen vom gewählten Treffer.')),
      h('div', { class: 'add wide2' }, inp, h('button', { class: 'btn', type: 'button', onclick: search }, 'Suchen'), h('button', { class: 'btn ghost', type: 'button', onclick: function () { S.relink = null; content(); } }, 'Abbrechen')), res);
    setTimeout(function () { inp.focus(); }, 0);
    return box;
  }
  var TVS = { 'Running': 'läuft', 'Ended': 'beendet', 'In Development': 'in Planung', 'To Be Determined': 'offen' };
  function renderEpisodes(e, eps) {
    var now = today0(), box = h('div', null), by = {};
    box.append(h('h3', { class: 'sec' }, 'Staffeln und Folgen'));
    box.append(h('p', { class: 'note' }, 'Folgen und Sendetermine von ', h('a', { href: e.tvmaze ? 'https://www.tvmaze.com/shows/' + e.tvmaze : 'https://www.tvmaze.com', target: '_blank', rel: 'noopener noreferrer' }, 'TVmaze'),
      e.fetched_at ? ', Stand ' + LONG.format(new Date(e.fetched_at.replace(' ', 'T'))) : '', e.tv_status ? '. Serienstatus: ' + (TVS[e.tv_status] || e.tv_status) : '', '. Die Angaben werden alle 12 Stunden erneuert.'));
    eps.forEach(function (x) { (by[x.season] = by[x.season] || []).push(x); });
    Object.keys(by).map(Number).sort(function (a, b) { return a - b; }).forEach(function (sn) {
      var l = by[sn], fut = 0; l.forEach(function (x) { var p = x.airdate ? parseDate(x.airdate) : null; if (p && p.prec !== 'none' && p.end >= now) fut++; });
      var past = l.length - fut, live = fut > 0, state = !live ? 'abgeschlossen' : past === 0 ? 'startet bald' : 'läuft gerade';
      var det = h('details', { class: 'season', open: live ? true : null }, h('summary', null, 'Staffel ' + sn, h('span', { class: 'sub' }, ' · ' + l.length + ' Folgen · '), h('span', { class: live ? 'soon' : 'sub' }, state)));
      l.forEach(function (x) {
        var p = x.airdate ? parseDate(x.airdate) : null, ok = p && p.prec === 'day', isFut = ok && p.end >= now, diff = ok ? Math.round((p.start - now) / 864e5) : 0;
        var when = ok ? WDAY.format(p.start).replace('.', '') + ', ' + LONG.format(p.start) : 'Datum offen', stat = isFut ? (diff === 0 ? 'heute' : diff === 1 ? 'morgen' : 'in ' + diff + ' Tagen') : (ok ? 'gezeigt' : '');
        det.append(h('div', { class: 'work' }, h('div', { class: 'yr' }, 'E' + x.number), h('div', null, h('div', { class: 'ttl' }, x.title || 'Titel noch offen'), h('div', { class: 'sub' }, when)), h('div', { class: 'stat ' + (isFut ? 'soon' : 'sub') }, stat)));
      });
      det.append(h('div', { class: 'season-foot' }, amazonLink(e, e.name + ' Staffel ' + sn)));
      box.append(det);
    });
    return box;
  }
  function refresh(cur, auto) {
    var b = $('rfr'); if (b) { b.disabled = true; b.lastChild.textContent = 'Lädt …'; }
    api('/api/data.php?a=refresh&id=' + cur.id, {}).then(function (x) {
      if (x.ok) load(function () { S.ready = true; if (S.view === 'list') { nav(); if ($('list')) { filt(); rows(); content(); } } }); else { var st = $('st'); if (st) { st.classList.remove('pulse'); st.textContent = x.msg || x.error || 'Das hat nicht geklappt.'; } if (b) { b.disabled = false; b.lastChild.textContent = 'Aktualisieren'; } load(function () { nav(); }); }
    });
  }
  /* Hinzufügen mit Suche */
  function addSubmit(ev) {
    ev.preventDefault(); var v = $('q').value.trim(); if (!v) { say('Gib erst einen Namen ein.', true); return; }
    if (S.cat === 'film') return addNow(v, {});
    S.sugg = { loading: true }; drawSugg(); say('');
    api('/api/data.php?a=search&type=' + S.cat + '&q=' + encodeURIComponent(v)).then(function (r) { S.sugg = { q: v, list: r.results || [] }; drawSugg(); });
  }
  function addNow(name, ext) {
    api('/api/data.php?a=add', { type: S.cat, name: name, ext: ext || {} }).then(function (r) {
      if (!r.ok) { say(r.error || 'Das hat nicht geklappt.', true); return; }
      S.sugg = null; S.sel = String(r.id); load(function () { render(); var q = $('q'); if (q) q.value = ''; });
    });
  }
  function drawSugg() {
    var box = $('sugg'); if (!box) return; box.textContent = ''; var s = S.sugg; if (!s) return;
    if (s.loading) { box.append(h('p', { class: 'hint pulse' }, 'Suche …')); return; }
    box.append(h('p', { class: 'hint' }, s.list.length ? 'Welcher Treffer ist gemeint?' : 'Keine Treffer.'));
    var list = h('div', { class: 'suggest' });
    s.list.forEach(function (x) { list.append(h('button', { class: 'row-btn', type: 'button', onclick: function () { addNow(x.name, x.ext); } }, h('span', { class: 'nm' }, x.name), h('span', { class: 'tp' }, x.hint || ''))); });
    list.append(h('button', { class: 'row-btn', type: 'button', onclick: function () { addNow(s.q, {}); } }, h('span', { class: 'tp' }, '„' + s.q + '“ trotzdem so hinzufügen')));
    box.append(list);
  }

  /* ---------- Einstellungen ---------- */
  function setSkin(id) { var r = document.documentElement; if (id) r.setAttribute('data-skin', id); else r.removeAttribute('data-skin'); S.skin = id; }
  function settings() {
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Einstellungen'));
    var box = h('div', { class: 'cfgpanel' });
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Konto'), h('p', { style: 'margin:0' }, D.email + ' · Mitglied Nr. ' + D.id + (D.admin ? ' · Admin' : ''))));
    var skins = h('div', { class: 'skins', role: 'group', 'aria-label': 'Oberfläche wählen' });
    SKINS.forEach(function (k) { skins.append(h('button', { class: 'skin', type: 'button', 'aria-pressed': String((S.skin || '') === k[0]), onclick: function () { setSkin(k[0]); api('/api/data.php?a=skin', { skin: k[0] }); render(); } }, h('span', { class: 'dots' }, k[2].map(function (c) { var i = h('i'); i.style.background = c; return i; })), h('b', null, k[1]))); });
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Oberfläche'), skins));
    var tm = h('span', { class: 'msg', style: 'margin:0' }), cb = h('input', { type: 'checkbox', id: 'ntf', onchange: function () { S.notify = this.checked; api('/api/data.php?a=notify', { on: this.checked }); opts.style.opacity = this.checked ? '1' : '.5'; } }); cb.checked = !!S.notify;
    var sv = h('span', { class: 'msg', style: 'margin:0' });
    function saveN() { api('/api/data.php?a=notifyset', { 'new': S.n['new'], day: S.n.day, week: S.n.week, mode: S.n.mode, weekday: S.n.weekday, bell_default: S.n.bell_default }).then(function (r) { sv.className = 'msg' + (r.ok ? '' : ' err'); sv.textContent = r.ok ? 'Gespeichert.' : (r.msg || r.error || 'Speichern nicht möglich.'); setTimeout(function () { sv.textContent = ''; }, 2500); }); }
    function chk(key, label) { var c = h('input', { type: 'checkbox', onchange: function () { S.n[key] = this.checked ? 1 : 0; saveN(); } }); c.checked = !!+S.n[key]; return h('label', { style: 'display:flex;gap:8px;align-items:center' }, c, label); }
    var wd = h('select', { style: 'width:auto', 'aria-label': 'Wochentag der Wochenübersicht', onchange: function () { S.n.weekday = +this.value; saveN(); } }, ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'].map(function (d, i) { return h('option', { value: String(i + 1) }, d); })); wd.value = String(S.n.weekday || 1);
    var md = h('select', { 'aria-label': 'Zustellung', onchange: function () { S.n.mode = this.value; saveN(); } }, h('option', { value: 'digest' }, 'Eine Sammelmail pro Tag (ab 7 Uhr)'), h('option', { value: 'instant' }, 'Sofort beim nächsten Abgleich (alle 2 Stunden, 7 bis 22 Uhr)')); md.value = S.n.mode === 'instant' ? 'instant' : 'digest';
    var opts = h('div', { class: 'nopts', style: 'display:grid;gap:8px;margin-top:12px;opacity:' + (S.notify ? '1' : '.5') },
      chk('new', 'Neu angekündigte Termine'), chk('day', 'Am Erscheinungstag (morgens ab 7 Uhr)'),
      h('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap' }, chk('week', 'Wochenübersicht (Termine der nächsten 14 Tage), jeden'), wd),
      h('label', { class: 'field', style: 'margin-top:4px' }, h('span', null, 'Zustellung'), md),
      chk('bell_default', 'Glocke bei neu hinzugefügten Einträgen standardmäßig an'),
      h('p', { style: 'margin:4px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center' },
        h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/data.php?a=bellall', { on: true }).then(function () { load(function () { render(); }); }); } }, 'Alle Glocken an'),
        h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/data.php?a=bellall', { on: false }).then(function () { load(function () { render(); }); }); } }, 'Alle Glocken aus'), sv));
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Benachrichtigungen'), h('p', { class: 'hint', style: 'margin-bottom:10px' }, 'Du bekommst Mails nur zu Einträgen, bei denen die Glocke an ist (sie sitzt im Kopf jedes Eintrags). Jede Mail enthält einen Abmeldelink.'),
      h('label', { style: 'display:flex;gap:8px;align-items:center' }, cb, 'E-Mail-Benachrichtigungen einschalten'), opts,
      h('p', { style: 'margin:14px 0 0' }, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { tm.className = 'msg'; tm.textContent = 'Sende …'; api('/api/data.php?a=testmail', {}).then(function (r) { tm.className = 'msg' + (r.ok ? '' : ' err'); tm.textContent = r.msg || r.error || ''; }); } }, 'Testmail an mich senden'), ' ', tm)));
    box.append(h('p', { class: 'hint wide2' }, 'Der Administrator dieser Seite sieht deine E-Mail-Adresse, die Zahl deiner Einträge und wann du zuletzt hier warst. Deine Einträge selbst bleiben privat.'));
    if (!SD.admin) {
      var dm = h('span', { class: 'msg', style: 'margin:0' }), pw = h('input', { type: 'password', autocomplete: 'current-password', placeholder: 'Passwort zur Bestätigung', style: 'max-width:260px' }), dbox = h('div', { hidden: true, style: 'margin-top:10px' });
      dbox.append(h('p', { class: 'hint' }, 'Dein Konto wird zur Löschung vorgemerkt und du wirst abgemeldet. Deine Daten (Konto, Listen, Einstellungen, Nachrichten) bleiben noch eine Woche gespeichert, falls du es dir anders überlegst: Melde dich einfach wieder an und brich die Löschung ab. Danach werden sie endgültig gelöscht.'), h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' }, pw,
        h('button', { class: 'btn warn small', type: 'button', onclick: function () { dm.className = 'msg'; dm.textContent = ''; api('/api/auth.php?a=delete_account', { password: pw.value }).then(function (r) { if (r && r.ok) location.reload(); else { dm.className = 'msg err'; dm.textContent = (r && r.error) || 'Das hat nicht geklappt.'; } }); } }, 'Konto jetzt löschen'), dm));
      box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Konto löschen'), h('p', { class: 'hint' }, 'Du kannst dein Konto samt aller Einträge jederzeit löschen lassen.'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { dbox.hidden = !dbox.hidden; if (!dbox.hidden) pw.focus(); } }, 'Konto löschen …'), dbox));
    }
    p.append(box); mainEl.append(p);
  }

  /* ---------- Info ---------- */
  function info() {
    var d = document.createElement('section'); d.className = 'panel infopanel';
    d.innerHTML = '<h2 class="sec">Info</h2><div class="intro"><p class="lead">Du bist hier richtig, wenn du irgendwann angefangen hast zu arbeiten und einfach keine Zeit mehr hast, deinen Lieblingskünstler:innen, Autor:innen oder Serien zu folgen. Wenn du überrascht feststellst, dass die Band deiner Jugend schon vor zwei Jahren ein neues Album veröffentlicht hat.</p>' +
      '<p>Schondraussen.de hilft dir dabei, ganz ohne viel Schnickschnack: Du legst fest, wem du folgst, und die Seite schickt dir eine Mail, sobald es etwas Neues gibt. So behältst du den Überblick und bestimmst selbst, was dich wirklich erreicht.</p>' +
      '<p class="muted">Aktuell ist die Seite noch im Testbetrieb und nicht öffentlich. Neue Konten werden vom Admin freigeschaltet.</p></div>' +
      '<section class="infosec"><h3>Zum Testen</h3><p>Trage bitte möglichst unterschiedliche Künstler:innen, Autor:innen und Serien ein, am besten auch solche mit angekündigten Neuerscheinungen. So sehen wir, wo die Angaben stimmen und wo etwas fehlt. Schau außerdem, ob der Kalender die Termine zeigt, ob die Mails ankommen und ob die Seite auf deinem Handy gut aussieht. Fällt dir etwas auf, schreib mir über „Kontakt“.</p></section>' +
      '<section class="infosec"><h3>Wann werden Einträge erneuert?</h3><p>Automatisch alle 12 Stunden. Jeder Titel wird dabei nur einmal für alle Nutzer abgefragt und zwischengespeichert. Neuerscheinungen tauchen dann ohne eigenes Zutun auf. Beim Hinzufügen eines Eintrags und über „Aktualisieren“ werden die Daten sofort geladen. Das Datum steht als „Stand“ am Eintrag.</p></section>' +
      '<section class="infosec"><h3>Quellen je Kategorie</h3>' +
      '<div class="infocat"><h4>Autor:innen</h4><p><a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> liefert die Bücher. Angekündigte Bücher kennt diese Quelle kaum, dafür kommt später eine zweite Quelle, etwa Google Books.</p></div>' +
      '<div class="infocat"><h4>Künstler:innen</h4><p><a href="https://musicbrainz.org" target="_blank" rel="noopener noreferrer">MusicBrainz</a>, eine offene Musikdatenbank, für Alben, EPs und angekündigte Veröffentlichungen.</p></div>' +
      '<div class="infocat"><h4>Filme</h4><p>Die Datenquelle wird noch angebunden, vorgesehen ist <a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer">TMDB</a>. Einträge kannst du schon anlegen.</p></div>' +
      '<div class="infocat"><h4>Serien</h4><p><a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">TVmaze</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>) für Staffeln und Sendetermine der Folgen.</p></div>' +
      '<div class="infocat"><h4>Amazon-Links</h4><p>Suchlinks mit der Partner-ID des Betreibers (Programm: <a href="https://partnernet.amazon.de" target="_blank" rel="noopener noreferrer">Amazon PartnerNet</a>). Eine Provision gibt es nur bei qualifizierten Käufen.</p></div></section>' +
      '<section class="infosec"><h3>Was noch fehlt</h3><ul><li>Filmdaten und Kinostarts (TMDB, Lizenz wird geklärt)</li><li>Streaminganbieter bei Serien</li><li>Gesehene Folgen abhaken</li><li>Weitere Shops als Alternative zu Amazon</li><li>Mehr Auswahl und Einschränkungen im Kalender</li><li><b>Kalender-Abo (.ics):</b> ein privater Link, den man in Google, Apple oder Outlook abonniert und der sich mit den Terminen von selbst aktualisiert</li></ul></section>';
    mainEl.append(d);
  }
  function logView() {
    markSeen(); renderVer();
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Versionsverlauf'), h('p', { class: 'hint' }, 'Was sich in welcher Version geändert hat.'));
    CL.forEach(function (c) { p.append(h('div', { class: 'logv' }, h('h3', null, 'Version ' + c.v, h('small', null, c.d)), h('ul', null, c.t.map(function (x) { return h('li', null, x); })))); });
    mainEl.append(p);
  }

  /* ---------- Kontakt ---------- */
  function support() {
    var admin = D.admin, p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Kontakt'),
      h('p', { class: 'hint' }, admin ? 'Hier siehst du die Nachrichten deiner Nutzer und kannst antworten.' : 'Schreibe dem Administrator eine Nachricht. Nur der Administrator sieht sie, Antworten erscheinen hier.'));
    var grid = h('div', { class: 'supgrid' + (admin ? ' admin2' : '') }), threads = h('aside', { class: 'supthreads', hidden: !admin }), log = h('div', { class: 'chatlog', role: 'log', 'aria-live': 'polite' });
    var inp = h('textarea', { rows: '2', maxlength: '1000', 'aria-label': 'Nachricht', placeholder: 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)' }), msg = h('p', { class: 'msg', role: 'status' });
    function send() {
      var t = inp.value.trim(); if (!t) return; if (admin && !S.sup.sel) { msg.textContent = 'Wähle erst eine Unterhaltung.'; return; }
      inp.value = ''; api('/api/chat.php?a=send', { text: t, uid: S.sup.sel }).then(function (r) { msg.textContent = r.ok ? '' : (r.error || 'Senden nicht möglich.'); msg.className = 'msg' + (r.ok ? '' : ' err'); pull(); });
    }
    var form = h('form', { class: 'chatform', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); send(); } }, inp, h('button', { class: 'btn', type: 'submit' }, 'Senden'));
    inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); send(); } });
    function fmtChat(t) { try { var d = new Date(t.replace(' ', 'T')), now = new Date(), hm = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }); return d.toDateString() === now.toDateString() ? hm : LONG.format(d).slice(0, 6) + ' ' + hm; } catch (_) { return ''; } }
    function drawThreads() {
      threads.textContent = ''; if (!S.sup.threads.length) threads.append(h('p', { class: 'hint' }, 'Noch keine Nachrichten.'));
      S.sup.threads.forEach(function (t) { threads.append(h('button', { class: 'thr', type: 'button', 'aria-current': String(S.sup.sel == t.id), onclick: function () { S.sup.sel = t.id; pull(); } }, h('span', { class: 't1' }, h('span', null, t.email), +t.unread > 0 ? h('span', { class: 'badge' }, String(t.unread)) : null), h('span', { class: 't2' }, (+t.last_admin ? 'Du: ' : '') + (t.last_body || '')))); });
    }
    function pull() {
      var go2 = function () {
        if (admin && !S.sup.sel) { log.textContent = ''; return; }
        api('/api/chat.php?a=thread' + (admin ? '&uid=' + S.sup.sel : '')).then(function (r) {
          var atEnd = log.scrollHeight - log.scrollTop - log.clientHeight < 40 || !log.childNodes.length; log.textContent = '';
          (r.messages || []).forEach(function (m) { var mine = admin ? +m.from_admin === 1 : +m.from_admin === 0;
            log.append(h('div', { class: 'bub ' + (mine ? 'me' : 'them') }, m.body, h('small', null, fmtChat(m.created_at), mine ? h('span', { class: 'ticks' + (m.read_at ? ' seen' : ''), title: m.read_at ? 'Gelesen' : 'Gesendet' }, m.read_at ? '✓✓' : '✓') : null))); });
          if (atEnd) log.scrollTop = log.scrollHeight; unreadPoll();
        });
      };
      if (admin) api('/api/chat.php?a=threads').then(function (r) { S.sup.threads = r.threads || []; if (!S.sup.sel && S.sup.threads.length) S.sup.sel = S.sup.threads[0].id; drawThreads(); go2(); }); else go2();
    }
    grid.append(threads, h('div', { class: 'chat' }, log, form, msg)); p.append(grid); mainEl.append(p); pull(); supTimer = setInterval(pull, 15000);
  }
  function unreadPoll() { api('/api/chat.php?a=unread').then(function (r) { var n = +r.unread || 0; if (n !== S.unread) { S.unread = n; var k = topbar.querySelector('.support'); if (k) { var b = k.querySelector('.badge'); if (b) b.remove(); if (n && S.view !== 'sup') k.append(h('span', { class: 'badge' }, n > 99 ? '99+' : String(n))); } } }); }

  /* ---------- Admin ---------- */
  function admin() {
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Admin')), amz = h('div', { class: 'cfgpanel' }), msg = h('span', { class: 'msg', style: 'margin:0' });
    var tag = h('input', { type: 'text', maxlength: '64', placeholder: 'deinname-21', spellcheck: 'false', autocapitalize: 'off', value: S.site.tag || '' }), dom = h('select', null, ['amazon.de', 'amazon.fr', 'amazon.it', 'amazon.es', 'amazon.co.uk', 'amazon.com'].map(function (d) { return h('option', { value: d }, d); })); dom.value = S.site.domain || 'amazon.de';
    amz.append(h('label', { class: 'field' }, h('span', null, 'Amazon-Partner-ID (gilt für alle Nutzer)'), tag), 
      h('p', { class: 'hint wide2' }, 'Die Partner-ID wird in alle Amazon-Links aller Nutzer eingebaut. Die Links gehen an amazon.de, du brauchst dafür eine ID aus dem deutschen PartnerNet.'),
      h('div', { class: 'add' }, h('button', { class: 'btn', type: 'button', onclick: function () { api('/api/admin.php?a=savesite', { tag: tag.value.trim(), domain: S.site.domain || 'amazon.de' }).then(function (r) { msg.className = 'msg' + (r.ok ? '' : ' err'); msg.textContent = r.ok ? 'Gespeichert.' : (r.error || 'Speichern nicht möglich.'); if (r.ok) S.site = { tag: tag.value.trim(), domain: dom.value }; }); } }, 'Speichern'), msg));
    p.append(amz, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Nutzerverwaltung'), h('p', { class: 'hint' }, 'Neue Konten sehen erst Inhalte, wenn du sie freischaltest. Beim Löschen verschwinden Konto und Liste.'));
    var box = h('div', { class: 'adm' }); p.append(box); mainEl.append(p);
    function users() {
      api('/api/admin.php?a=users').then(function (r) {
        var l = r.users || []; S.pend = l.filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; nav(); box.textContent = '';
        l.forEach(function (x) {
          var ok = x.role === 'admin' || x.approved_at, me = x.id == r.me;
          box.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' }, 'Nr. ' + x.id + ' · ' + x.email, x.role === 'admin' ? h('span', { class: 'tier' }, 'Admin') : null), h('div', { class: 'meta2' }, (ok ? 'freigeschaltet' : 'wartet auf Freischaltung') + (x.email_verified_at ? '' : ' · E-Mail nicht bestätigt') + ' · ' + x.entries + ' Einträge · zuletzt ' + (x.last_seen_at || '–').slice(0, 16) + ' · seit ' + (x.created_at || '').slice(0, 10))),
            me || x.role === 'admin' ? h('div') : h('div', { class: 'acts' }, ok ? h('button', { class: 'btn ghost small', type: 'button', onclick: function () { act('revoke', x.id); } }, 'Sperren') : h('button', { class: 'btn small', type: 'button', onclick: function () { act('approve', x.id); } }, 'Freischalten'),
              h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Konto samt Liste wirklich löschen?')) act('delete', x.id); } }, 'Löschen'))));
        });
      });
    }
    function act(a, id) { api('/api/admin.php?a=' + a, { id: id }).then(function (r) { if (r.ok) users(); else alert(r.error); }); }
    api('/api/admin.php?a=site').then(function (r) { if (r.tag != null) { S.site = { tag: r.tag, domain: r.domain }; tag.value = r.tag; dom.value = r.domain; } });
    users();
  }

  /* ---------- Start ---------- */
  var who = $('who'); if (who) who.addEventListener('click', function () { go('set'); });
  var lg = $('logoutTop'); if (lg) lg.addEventListener('click', function () { api('/api/auth.php?a=logout', {}).then(function () { location.reload(); }); });
  var ver = $('ver'); if (ver) ver.addEventListener('click', function () { go('log'); });
  document.addEventListener('click', function () { var p = $('filtPop'); if (p && !p.hidden) { p.hidden = true; var b = $('filtBtn'); if (b) b.setAttribute('aria-expanded', 'false'); } });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { var p = $('filtPop'); if (p) p.hidden = true; } });
  load(function () { render(); unreadPoll(); setInterval(unreadPoll, 60000); if (D.admin) api('/api/admin.php?a=users').then(function (r) { S.pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; nav(); }); });
})();
