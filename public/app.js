(function () {
  var D = window.SD || {}, root = document.getElementById('appview');
  if (!root) return;
  var CL = window.CHANGELOG || [], LATEST = CL.length ? CL[0].v : 1;
  var LOGTOTAL = CL.reduce(function (a, c) { return a + (c.base ? 0 : c.t.length); }, 0);
  var TYPES = {
    author: { label: 'Bücher', plural: 'Bücher', who: 'Autor:innen', ph: 'z. B. Terry Pratchett', idx: 'stripbooks', work: 'Bücher' },
    artist: { label: 'Musik', plural: 'Musik', who: 'Musiker:innen', ph: 'z. B. Radiohead', idx: 'music', work: 'Alben' },
    film: { label: 'Film', plural: 'Filme', who: 'Filme', ph: 'z. B. Dune', idx: 'video', work: 'Filme' },
    series: { label: 'Serie', plural: 'Serien', who: 'Serien', ph: 'z. B. Severance', idx: 'video', work: 'Staffeln' }
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
    clip: '<svg viewBox="0 0 24 24"><path d="M21 11.5 12.6 20a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/></svg>',
    thumb: '<svg viewBox="0 0 24 24"><path d="M7 11v9H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3zm0 0 4-8a2 2 0 0 1 2 2v4h5.2a2 2 0 0 1 2 2.4l-1.3 6A2 2 0 0 1 16.9 20H7"/></svg>',
    bell: '<svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    bellOff: '<svg viewBox="0 0 24 24"><path d="M13.7 21a1.94 1.94 0 0 1-3.4 0M17 17H3s3-2 3-9a6 6 0 0 1 .3-1.9M8.7 3.3A6 6 0 0 1 18 8c0 2.5.4 4.4.9 5.7M2 2l20 20"/></svg>',
    gear: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    shield: '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    bulb: '<svg viewBox="0 0 24 24"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.7.6 1 1.4 1 2.3h6c0-.9.3-1.7 1-2.3A7 7 0 0 0 12 2z"/></svg>',
    bug: '<svg viewBox="0 0 24 24"><rect x="8" y="6" width="8" height="14" rx="4"/><path d="M19 7l-3 2M5 7l3 2M19 19l-3-2M5 19l3-2M20 13h-4M4 13h4M10 2l1 2M14 2l-1 2"/></svg>',
    info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    chat: '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
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
  function popup(title, text) { // Bestätigung als Fenster, schließt mit OK, Esc oder Klick daneben
    var prev = document.activeElement, ok = h('button', { class: 'btn', type: 'button' }, 'OK');
    var box = h('div', { class: 'unmodal', role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': 'pph' }, h('div', { class: 'unbox' }, h('h2', { id: 'pph', style: 'margin:0 0 6px;font-size:20px' }, title), h('p', { class: 'hint', style: 'margin:0 0 14px' }, text), ok));
    function close() { document.removeEventListener('keydown', key); box.remove(); if (prev && prev.focus) prev.focus(); }
    function key(ev) { if (ev.key === 'Escape' || ev.key === 'Enter') { ev.preventDefault(); close(); } }
    ok.addEventListener('click', close); box.addEventListener('click', function (ev) { if (ev.target === box) close(); }); document.addEventListener('keydown', key);
    document.body.append(box); ok.focus();
  }
  function say(t, err) { S.msg = t || ''; S.msgErr = !!err; var m = $('msg'); if (m) { m.textContent = S.msg; m.className = 'msg' + (err ? ' err' : ''); } }

  /* ---------- Kopf und Leiste ---------- */
  function logBadge() { var s; try { s = parseInt(localStorage.getItem('sd-seen'), 10); } catch (_) {} if (isNaN(s)) return 0; return Math.max(0, LOGTOTAL - s); }
  function markSeen() { try { localStorage.setItem('sd-seen', String(LOGTOTAL)); } catch (_) {} }
  function renderVer() {
    var v = $('ver'); if (!v) return; var n = logBadge();
    v.textContent = 'Version ' + LATEST; if (n && D.nerd) v.append(h('span', { class: 'badge', 'aria-label': n + ' neue Änderungen' }, n > 99 ? '99+' : String(n)));
    v.setAttribute('aria-pressed', String(S.view === 'log')); v.title = 'Versionsverlauf';
  }
  function pl(n, one, many) { n = +n || 0; return n + ' ' + (n === 1 ? one : many); }
  // Funktionsstatus: 'alpha' = nur für Admins und Alphatester sichtbar (mit auffälliger Markierung), sonst für alle.
  var FEATURES = {}; // Ideen und Bugs sind für alle sichtbar (freigegebene Beiträge)
  function isAlpha() { return !S.preview && !!D.alpha; }
  function feat(key) { return (FEATURES[key] || 'live') !== 'alpha' || isAlpha(); }
  function alphaBadge() { return h('span', { class: 'alphabadge', title: 'Alpha: Diese Funktion ist noch in Entwicklung und nur für Alphatester sichtbar' }, 'Alpha'); }
  function alphaWrap(key, el) { if ((FEATURES[key] || 'live') !== 'alpha') return el; var w = h('div', { class: 'alphabox' }, alphaBadge()); w.append(el); return w; }
  function isAdm() { return !!D.admin && !S.preview; }
  function setPreview(on) { S.preview = !!on; var pb = $('previewbar'); if (pb) pb.hidden = !on; S.view = on ? 'cal' : 'adm'; S.cat = S.view; S.sel = null; S.menuFor = null; say(''); nav(); render(); window.scrollTo(0, 0); }
  var po = $('previewOff'); if (po) po.addEventListener('click', function () { setPreview(false); });
  function go(view, cat) { S.view = view; S.cat = cat || view; S.sel = null; S.confirm = null; S.menuFor = null; S.filter = ''; S.adding = false; S.sugg = null; say(''); render(); window.scrollTo(0, 0); }
  function nav() {
    topbar.textContent = '';
    // Rechte Gruppe: Symbole. Für alle nur Symbole (Name per Tooltip, bei der aktiven Seite sichtbar).
    var RI = { adm: 'shield', set: 'gear', feat: 'bulb', bug: 'bug', info: 'info', sup: 'chat' }, iconOnly = true;
    function btn(key, text, count, extra) {
      var cur = S.view === 'list' ? S.cat === key : S.view === key, ri = RI[key], io = ri && iconOnly;
      return h('button', { class: 'nav' + (extra || '') + (io ? ' ionly' : ''), type: 'button', 'aria-current': cur ? 'page' : null, title: io ? text : null, 'aria-label': io ? text : null, onclick: function () { /^(author|artist|film|series)$/.test(key) ? go('list', key) : go(key); } },
        key === 'cal' ? ico(ICONS.cal) : null, ri ? ico(ICONS[ri]) : null, /^(author|artist|film|series)$/.test(key) ? h('i', { class: 'cdot c-' + key, 'aria-hidden': 'true' }) : null, io ? h('span', { class: 'lbl' }, text) : text, count ? h('small', null, String(count)) : null);
    }
    topbar.append(btn('cal', 'Kalender', S.rels.length));
    ORDER.forEach(function (k) { topbar.append(btn(k, TYPES[k].plural, entriesOf(k).length)); });
    var rg = ' rg';
    if (isAdm()) { var b = btn('adm', 'Admin', 0, ' adminnav' + rg); if (S.pend) b.append(h('span', { class: 'badge' }, String(S.pend))); topbar.append(b); rg = ''; }
    topbar.append(btn('set', 'Einstellungen', 0, ' settings' + rg));
    if (feat('feat')) topbar.append(btn('feat', 'Ideen', 0, ' feat'));
    topbar.append(btn('bug', 'Bugs', 0, ' bugs'));
    topbar.append(btn('info', 'Info', 0, ' info'));
    var k = btn('sup', 'Kontakt', 0, ' support'); if (S.unread) k.append(h('span', { class: 'badge', 'aria-label': S.unread + ' ungelesen' }, S.unread > 99 ? '99+' : String(S.unread))); topbar.append(k);
    renderVer();
  }
  function welcome() {
    var w = $('welcome'); if (!w) { w = h('section', { class: 'warnbox', id: 'welcome', style: 'margin:12px 0' }); $('topbar').before(w); }
    w.textContent = '';
    w.hidden = true;
    w.hidden = true;
  }
  function render() {
    nav(); welcome(); mainEl.textContent = '';
    if (!S.ready) { mainEl.append(h('p', { class: 'state pulse' }, 'Lade deine Liste …')); return; }
    clearInterval(supTimer);
    var v = S.view;
    saveView();
    if (v === 'cal') mainEl.append(renderCalendar());
    else if (v === 'list') listView();
    else if (v === 'set') settings(); else if (v === 'info') info(); else if (v === 'log') logView(); else if (v === 'sup') support(); else if (v === 'adm') admin(); else if (v === 'feat' && feat('feat')) feedbackView('feature'); else if (v === 'bug') feedbackView('bug');
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
    { pop.append(h('p', { class: 'label', style: 'margin-top:14px' }, 'Genre'));
      var sel = h('select', { 'aria-label': 'Nach Genre filtern', disabled: gl.length ? null : true, onchange: function () { S.genre[S.cat] = this.value; filt(); rows(); } }, h('option', { value: '' }, gl.length ? 'Alle Genres' : 'Keine Genres vorhanden'), gl.map(function (x) { return h('option', { value: x[0] }, x[0] + ' (' + x[1] + ')'); }));
      sel.value = g; pop.append(sel); }
    var b = $('fbadge'); b.hidden = !n; b.textContent = String(n);
    var ch = $('fchips'); ch.textContent = '';
    if (cur !== 'alpha') ch.append(h('button', { class: 'fchip', type: 'button', title: 'Sortierung zurücksetzen', onclick: function () { S.sort[S.cat] = 'alpha'; filt(); rows(); } }, SORTS.filter(function (o) { return o[0] === cur; })[0][1] + ' ×'));
    if (g) ch.append(h('button', { class: 'fchip', type: 'button', title: 'Genre-Filter zurücksetzen', onclick: function () { S.genre[S.cat] = ''; filt(); rows(); } }, g + ' ×'));
  }
  function worksLine(e) { var t = TYPES[e.type]; return !e.fetched_at ? 'noch nicht geladen' : e.works ? (e.type === 'series' ? pl(e.works, 'Staffel', 'Staffeln') : e.type === 'author' ? pl(e.works, 'Buch', 'Bücher') : e.type === 'artist' ? pl(e.works, 'Album', 'Alben') : pl(e.works, 'Werk', 'Werke')) : 'keine Daten'; }
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
        h('span', { class: 'nm' }, e.name), h('span', { class: 'tp' }, (mode === 'last' || mode === 'next') ? (e._lab || 'keine Angabe') : worksLine(e), +e.bell === 0 ? h('span', { class: 'beloff', title: 'Benachrichtigungen für diesen Eintrag sind aus' }, ico(ICONS.bellOff)) : null),
        n ? h('span', { class: 'ct', title: 'Kommende Veröffentlichungen' }, String(n)) : null));
    });
  }
  function saveView() { try { sessionStorage.setItem('sd_view', JSON.stringify({ v: S.view, c: S.cat, s: S.sel, t: Date.now() })); } catch (_) {} }
  function content() {
    saveView();
    var box = $('content'); box.textContent = '';
    var es = entriesOf(S.cat), t = TYPES[S.cat];
    if (!es.length) { box.append(h('div', { class: 'empty' }, h('h2', null, 'Noch keine ' + t.plural), h('p', null, 'Gib links einen Namen ein und wähle den passenden Treffer. Werke und angekündigte Neuerscheinungen werden dann automatisch geladen.'), h('p', { class: 'hint' }, 'Tipp: In den ', h('button', { class: 'namelink', type: 'button', onclick: function () { go('set'); } }, 'Einstellungen'), ' kannst du unter „Import“ ganze Listen oder Exporte (zum Beispiel von Spotify oder Trakt) einspielen und eigene Sammellisten hinzufügen.'))); box.append(popularBox(S.cat)); return; }
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
    var tiles = cat === 'author' ? [['Autor:innen', es.length], ['Bücher in deiner Liste', works, null, 'Die Bücher, die wir pro Autor:in tatsächlich speichern: höchstens 200 Treffer je Person, mehrfache Ausgaben desselben Titels nur einmal, angekündigte Bücher zählen unter „Kommende Veröffentlichungen“.'], ['Bücher insgesamt (geschätzt)', tot, totKnown ? 'Angabe bei ' + totKnown + ' von ' + es.length : 'noch keine Angaben', 'Trefferzahl von Open Library. Sie enthält mehrfache Ausgaben und Übersetzungen desselben Buchs und Treffer über der Grenze von 200 je Autor:in, deshalb liegt sie höher als die Zahl in deiner Liste.'], ['Kommende Veröffentlichungen', ups, null, 'Angekündigte Bücher oder Alben mit Termin oder zumindest Erscheinungsjahr in der Zukunft.']]
      : cat === 'artist' ? [['Musiker:innen', es.length], ['Alben in deiner Liste', works, null, 'Die Alben und EPs, die wir pro Musiker:in speichern. Livealben, Compilations, Remixe, Soundtracks und Ähnliches werden nur gezählt, wenn sie noch kommen.'], ['Alben insgesamt (geschätzt)', tot, totKnown ? 'Angabe bei ' + totKnown + ' von ' + es.length : 'noch keine Angaben', 'Zahl der Alben und EPs, die MusicBrainz zur Person führt, inklusive mancher Doppel- oder Sonderfassungen.'], ['Kommende Veröffentlichungen', ups, null, 'Angekündigte Bücher oder Alben mit Termin oder zumindest Erscheinungsjahr in der Zukunft.']]
      : cat === 'film' ? [['Filme', es.length], ['Filme und Teile der Reihen', works], ['Kommende Starts', ups]]
      : [['Serien', es.length], ['Staffeln', works, null, 'Summe der Staffeln aller Serien in deiner Liste (Quelle: TVmaze).'], ['Folgen gesamt', eps, epsKnown ? 'Folgenzahl bei ' + epsKnown + ' von ' + es.length : 'noch keine Angaben', 'Alle bekannten Folgen. Bei Serien, die noch nicht geladen sind, fehlt die Angabe.'], ['Kommende Termine', ups]];
    wrap.append(h('h2', { class: 'sec' }, 'Übersicht ' + t.plural));
    if (cat === 'film') wrap.append(h('p', { class: 'warnbox' }, h('b', null, 'Hinweis: '), 'Die Datenquelle für Filme ist hier noch nicht angebunden. Du kannst Filme schon auf deine Liste setzen, Werke und Kinotermine erscheinen aber erst, wenn die Anbindung aktiviert ist.'));
    if (cat === 'author') wrap.append(h('p', { class: 'warnbox' }, h('b', null, 'Hinweis: '), 'Die frei verfügbaren Buch-Schnittstellen liefern nur wenige zukünftige Bücher. Meist kommen Bücher erst nach dem Erscheinen dazu. Darum stehen bei den Terminen auch Bücher, die in den letzten 4 Wochen neu bei der Quelle hinzugekommen sind („neu hinzugefügt“).'));
    wrap.append(h('div', { class: 'ovtiles' }, tiles.map(function (x) { return h('div', { class: 'ovtile' + (x[3] ? ' hastip' : ''), title: x[3] || null, tabindex: x[3] ? '0' : null }, h('div', { class: 'ovnum' }, fmtN(x[1])), h('div', { class: 'ovlbl' }, x[0]), x[2] ? h('div', { class: 'ovnote tipd', title: 'Für die übrigen Einträge liefert die Datenquelle keine Gesamtzahl, sie fehlen in dieser Summe. Die Zahl oben ist deshalb eine Untergrenze.' }, x[2]) : null); })));
    items.sort(function (a, b) { return a.p.sort - b.p.sort || String(a.u.title).localeCompare(String(b.u.title), 'de'); });
    wrap.append(h('h3', { class: 'sec' }, 'Kommende Veröffentlichungen' + (items.length > 10 ? ' (die nächsten 10 von ' + items.length + ')' : '')));
    if (!items.length) wrap.append(h('p', { class: 'state' }, 'Keine angekündigten Termine bekannt.'));
    items.slice(0, 10).forEach(function (it) {
      var p = it.p, d = p.prec === 'day' ? LONG.format(p.start) : p.prec === 'month' ? MSHORT.format(p.start).replace('.', '') + ' ' + p.y : p.prec === 'none' ? 'Datum offen' : String(p.y);
      wrap.append(h('div', { class: 'ovrow' }, h('span', { class: 'ovd', title: p.prec === 'month' ? 'Nur der Monat ist bekannt' : p.prec === 'year' ? 'Nur das Jahr ist bekannt' : null }, d), h('span', { class: 'ovt' }, h('b', null, nameLink(it.e)), ' – ' + it.u.title + (it.u.kind && it.u.kind !== 'Folge' ? ' · ' + it.u.kind : ''))));
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
    var bellBtn = h('button', { class: 'btn ghost small bellbtn ' + (bellOn ? 'on' : 'off'), type: 'button', title: bellOn ? 'Benachrichtigungen für diesen Eintrag sind an' : 'Benachrichtigungen für diesen Eintrag sind aus', 'aria-pressed': String(bellOn), 'aria-label': bellOn ? 'Benachrichtigungen an' : 'Benachrichtigungen aus',
      onclick: function () { bellOn = !bellOn; if (le) le.bell = bellOn ? 1 : 0; api('/api/data.php?a=bell&id=' + e.id, { on: bellOn }); bellBtn.className = 'btn ghost small bellbtn ' + (bellOn ? 'on' : 'off'); bellBtn.title = bellOn ? 'Benachrichtigungen für diesen Eintrag sind an' : 'Benachrichtigungen für diesen Eintrag sind aus'; bellBtn.setAttribute('aria-pressed', String(bellOn)); bellBtn.setAttribute('aria-label', bellOn ? 'Benachrichtigungen an' : 'Benachrichtigungen aus'); bellBtn.textContent = ''; bellBtn.append(ico(on_(bellOn))); rows(); } }, ico(on_(bellOn)));
    var menu = h('div', { class: 'menu', role: 'menu', hidden: true }), moreBtn, wrap;
    function closeMenu() { menu.hidden = true; moreBtn.setAttribute('aria-expanded', 'false'); document.removeEventListener('click', outside, true); document.removeEventListener('keydown', esc); }
    function outside(ev) { if (!wrap.contains(ev.target)) closeMenu(); }
    function esc(ev) { if (ev.key === 'Escape') closeMenu(); }
    function fillMenu() {
      menu.textContent = '';
      menu.append(h('button', { class: 'mi', id: 'rfr', type: 'button', role: 'menuitem', onclick: function () { closeMenu(); refresh(cur); } }, ico(ICONS.refresh), 'Aktualisieren'));
      if (e.type !== 'film') menu.append(h('button', { class: 'mi', type: 'button', role: 'menuitem', onclick: function () { closeMenu(); S.relink = e.id; content(); } }, 'Treffer ändern'));
      menu.append(h('button', { class: 'mi warn', type: 'button', role: 'menuitem', onclick: function () {
        menu.textContent = '';
        menu.append(h('p', { class: 'mq' }, 'Wirklich aus deiner Liste entfernen?'),
          h('button', { class: 'mi warn', type: 'button', onclick: function () { api('/api/data.php?a=remove&id=' + e.id, {}).then(function () { S.sel = null; S.confirm = null; load(); }); } }, 'Ja, entfernen'),
          h('button', { class: 'mi', type: 'button', onclick: closeMenu }, 'Abbrechen'));
      } }, 'Entfernen'));
    }
    moreBtn = h('button', { class: 'btn ghost small more', type: 'button', title: 'Weitere Aktionen', 'aria-label': 'Weitere Aktionen', 'aria-haspopup': 'menu', 'aria-expanded': 'false', onclick: function () {
      if (!menu.hidden) { closeMenu(); return; }
      fillMenu(); menu.hidden = false; moreBtn.setAttribute('aria-expanded', 'true');
      setTimeout(function () { document.addEventListener('click', outside, true); document.addEventListener('keydown', esc); }, 0);
    } }, '…');
    wrap = h('div', { class: 'menuwrap' }, moreBtn, menu);
    var actions = h('div', { class: 'actions' }, bellBtn, wrap);
    var meta = h('div', { class: 'meta' }, h('span', { class: 'mcat' }, ico(ICONS[e.type]), t.label), stamp ? h('span', { class: 'mstamp tipd', title: 'Zeitpunkt der letzten Aktualisierung aus der Datenquelle. Die Angaben werden automatisch alle 12 Stunden erneuert; „Aktualisieren“ lädt sie sofort neu.' }, stamp) : null);
    var gch = (e.genres || []).length ? h('div', { class: 'gchips' }, e.genres.map(function (g) { return h('span', { class: 'gchip' }, g); })) : null;
    card.append(h('div', { class: 'card-head' }, h('div', { class: 'ch1' }, meta, h('h2', null, e.name), gch), actions));
    if (S.relink === e.id) card.append(relinkBox(cur, e));
    else if (e.fetched_at && e.type !== 'film' && !r.works.length && !r.releases.length && !(r.episodes || []).length) card.append(h('p', { class: 'warnbox' }, 'Zu diesem Eintrag wurden keine Daten gefunden. Oft hilft ein anderer Titel, bei Serien und Filmen zum Beispiel der englische. ' + ((r.entry && r.entry.err) ? 'Rückmeldung der Quelle: ' + r.entry.err + ' ' : ''), h('button', { class: 'btn small', type: 'button', style: 'margin-left:6px', title: 'Suche in der Datenquelle nach dem passenden Titel, zum Beispiel mit dem englischen Namen', onclick: function () { S.relink = e.id; content(); } }, 'Treffer ändern')));
    if (!e.fetched_at && e.type !== 'film') { card.append(h('p', { class: 'state pulse', id: 'st' }, 'Daten werden geladen …')); refresh(cur, true); return card; }
    if (e.type === 'film') card.append(h('p', { class: 'state' }, 'Für Filme wird die Datenquelle noch angebunden. Der Eintrag steht schon auf deiner Liste.'));
    if (e.summary) card.append(h('p', { class: 'sum' }, e.summary));
    var up = r.releases.map(function (x) { return { e: e, p: parseDate(x.release_date), u: { title: x.title, date: x.release_date, kind: x.kind || '', note: x.note || '' } }; });
    card.append(h('h3', { class: 'sec' }, 'Kommende Veröffentlichungen'));
    if (e.type === 'author') card.append(h('p', { class: 'warnbox' }, h('b', null, 'Hinweis: '), (up.length ? 'Die frei verfügbaren Buch-Schnittstellen liefern nur wenige zukünftige Bücher. Meist kommen Bücher erst nach dem Erscheinen dazu. Darum stehen hier auch Bücher, die in den letzten 4 Wochen neu bei der Quelle hinzugekommen sind („neu hinzugefügt“).' : 'Die frei verfügbaren Buch-Schnittstellen liefern nur wenige zukünftige Bücher. Zurzeit ist hier nichts angekündigt, und in den letzten 4 Wochen ist bei der Quelle auch nichts Neues dazugekommen. Sobald sich das ändert, erscheint es hier und du bekommst eine Mail, wenn du sie eingeschaltet hast.')));
    if (up.length) up.forEach(function (it) { card.append(relRow(it)); }); else card.append(h('p', { class: 'state' }, 'Keine angekündigten Termine bekannt.'));
    if (e.type === 'series' && (r.episodes || []).length) { card.append(renderEpisodes(e, r.episodes, doneSetOf(r))); card.append(renderSimilar(e)); return card; }
    if (e.type === 'series' && e.fetched_at && !S.epTried[e.id]) { S.epTried[e.id] = 1; refresh(cur, true); }
    card.append(h('h3', { class: 'sec' }, e.type === 'series' ? 'Staffeln' : 'Bekannte Werke'));
    if (!r.works.length) card.append(h('p', { class: 'state' }, e.type === 'film' ? '' : 'Keine Werke gefunden.'));
    else if (e.type !== 'series') card.append(h('p', { class: 'hint' }, r.works.length + ' ' + t.work + (+e.total_works > r.works.length ? ' von ca. ' + e.total_works : '') + ' aufgelistet.'));
    var dv = DONEV[e.type] || DONEV.artist, done = doneSetOf(r), hideDone = false, rows = [], cnt = h('span', { class: 'cnt' }), flt = h('button', { class: 'pill', type: 'button', 'aria-pressed': 'false' }, 'Nur Neues für mich');
    function updAll() { var n = r.works.filter(function (w) { return done[w.k]; }).length; cnt.textContent = n + ' von ' + r.works.length + ' ' + t.work + ' ' + dv[0];
      rows.forEach(function (x) { var on = !!done[x.w.k]; x.pill.className = 'pill' + (on ? ' on' : ''); x.pill.setAttribute('aria-pressed', on ? 'true' : 'false'); x.pill.textContent = on ? dv[0].charAt(0).toUpperCase() + dv[0].slice(1) : dv[1]; x.row.hidden = hideDone && on; }); }
    flt.addEventListener('click', function () { hideDone = !hideDone; flt.className = 'pill' + (hideDone ? ' on' : ''); flt.setAttribute('aria-pressed', hideDone ? 'true' : 'false'); updAll(); });
    if (r.works.length && e.type !== 'series') card.append(h('div', { class: 'donebar' }, cnt, flt));
    r.works.forEach(function (w) {
      var pill = h('button', { type: 'button', title: 'Nur für dich: Markiere, was du schon ' + dv[0] + ' hast. Andere sehen das nicht.' }), row = h('div', { class: 'work' }, h('div', { class: 'yr' }, w.year || '–'), h('div', null, h('div', { class: 'ttl' }, w.title), (w.kind || w.note) ? h('div', { class: 'sub' }, [w.kind, w.note].filter(Boolean).join(' · ')) : null), h('div', { class: 'wact' }, pill, amazonLink(e, w.title)));
      pill.addEventListener('click', function () { var on = !done[w.k]; setDone(e, [w.k], on, function () { if (on) done[w.k] = 1; else delete done[w.k]; updAll(); }); });
      rows.push({ w: w, pill: pill, row: row }); card.append(row);
    });
    updAll();
    card.append(renderSimilar(e));
    return card;
  }
  /* ---------- Vorschläge: ähnliche Einträge und beliebte Einträge je Genre ---------- */
  var POP = {
    artist: [['Rock', 'rock'], ['Metal', 'metal'], ['Thrash Metal', 'thrash metal'], ['Hard Rock', 'hard rock'], ['Punk', 'punk'], ['Pop', 'pop'], ['Hip-Hop', 'hip hop'], ['Elektronik', 'electronic'], ['Jazz', 'jazz'], ['Klassik', 'classical'], ['Folk', 'folk'], ['Blues', 'blues'], ['Reggae', 'reggae'], ['Country', 'country'], ['Indie', 'indie'], ['Soul', 'soul'], ['Schlager', 'schlager']],
    series: [['Drama', 'Drama'], ['Komödie', 'Comedy'], ['Science-Fiction', 'Science-Fiction'], ['Fantasy', 'Fantasy'], ['Krimi', 'Crime'], ['Thriller', 'Thriller'], ['Horror', 'Horror'], ['Action', 'Action'], ['Abenteuer', 'Adventure'], ['Mystery', 'Mystery'], ['Romanze', 'Romance'], ['Anime', 'Anime'], ['Familie', 'Family'], ['Western', 'Western'], ['Krieg', 'War'], ['Geschichte', 'History']],
    author: [['Fantasy', 'Fantasy'], ['Science-Fiction', 'Science fiction'], ['Krimi', 'Mystery and detective stories'], ['Thriller', 'Thriller'], ['Horror', 'Horror'], ['Liebesroman', 'Romance'], ['Historischer Roman', 'Historical fiction'], ['Biografie', 'Biography'], ['Jugendbuch', 'Young adult fiction'], ['Humor', 'Humor'], ['Abenteuer', 'Adventure']]
  };
  function addSuggestion(type, it, btn, done) {
    btn.disabled = true; btn.textContent = 'Wird hinzugefügt …';
    api('/api/data.php?a=add', { type: type, name: it.name, ext: it.ext || {} }).then(function (r) {
      if (!r.ok) { btn.disabled = false; btn.textContent = 'Hinzufügen'; say(r.error || 'Das hat nicht geklappt.', true); popup('Nicht hinzugefügt', r.error || 'Das hat nicht geklappt.'); return; }
      btn.textContent = 'Hinzugefügt ✓'; btn.className = 'btn ghost small';
      load(function () { nav(); if (S.view === 'list' && $('list')) { filt(); rows(); } if (done) done(); });
    });
  }
  function suggestList(type, res, onChange) {
    var box = h('div', { class: 'sglist' });
    if (res.msg && !(res.items || []).length) box.append(h('p', { class: 'hint' }, res.msg));
    (res.items || []).forEach(function (it) {
      var b = it.have ? h('span', { class: 'hint', style: 'margin:0' }, 'Schon auf deiner Liste') : h('button', { class: 'btn small', type: 'button', onclick: function () { addSuggestion(type, it, b, onChange); } }, 'Hinzufügen');
      box.append(h('div', { class: 'sgrow' }, h('div', { class: 'sgt' }, h('b', null, it.name), it.note ? h('span', { class: 'hint', style: 'margin:0' }, ' · ' + it.note) : null), b));
    });
    return box;
  }
  function renderSimilar(e) {
    var t = TYPES[e.type], sec = h('section', { class: 'similar' }), body = h('div', null);
    sec.append(h('h3', { class: 'sec' }, 'Ähnliche ' + t.who), body);
    function ask(fresh) {
      body.textContent = ''; body.append(h('p', { class: 'state pulse' }, 'Suche Vorschläge …'));
      api('/api/suggest.php?a=similar&id=' + e.id + (fresh ? '&fresh=1' : '')).then(function (r) {
        body.textContent = '';
        if (r.error) { body.append(h('p', { class: 'warnbox' }, r.error)); return; }
        body.append(suggestList(e.type, r), h('p', { class: 'hint' }, 'Die Vorschläge kommen aus ' + ({ artist: 'ListenBrainz', series: 'TVmaze-Daten (gleiche Genres, nach Beliebtheit)', author: 'Open Library (gleiche Themen)' }[e.type] || 'freien Datenquellen') + ' und können Lücken haben.'));
      });
    }
    if (e.type === 'film') { body.append(h('p', { class: 'hint' }, 'Für Filme folgt das, sobald die Filmdatenbank angebunden ist.')); return sec; }
    body.append(h('p', { class: 'hint' }, 'Lass dir zeigen, was dir noch gefallen könnte.'), h('p', null, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { ask(false); } }, 'Vorschläge laden')));
    return sec;
  }
  function popularBox(cat) {
    var t = TYPES[cat], sec = h('section', { class: 'similar' }), chips = h('div', { class: 'gchips pick', role: 'group', 'aria-label': 'Genre wählen' }), out = h('div', null), cur = null;
    sec.append(h('h3', { class: 'sec' }, 'Beliebte ' + t.who + ' entdecken'));
    if (!POP[cat]) { sec.append(h('p', { class: 'hint' }, 'Für Filme folgt das, sobald die Filmdatenbank angebunden ist.')); return sec; }
    sec.append(h('p', { class: 'hint' }, 'Wähle ein Genre und bekomme bis zu 10 typische Vorschläge, die du mit einem Klick auf deine Liste setzt.'), chips, out);
    function draw() { chips.textContent = ''; POP[cat].forEach(function (g) { chips.append(h('button', { class: 'gchip pickbtn', type: 'button', 'aria-pressed': String(cur === g[1]), onclick: function () { cur = g[1]; draw(); go2(g); } }, g[0])); }); }
    function go2(g) {
      out.textContent = ''; out.append(h('p', { class: 'state pulse' }, 'Suche Vorschläge …'));
      api('/api/suggest.php?a=popular&type=' + cat + '&genre=' + encodeURIComponent(g[1])).then(function (r) {
        if (cur !== g[1]) return; out.textContent = '';
        if (r.error) { out.append(h('p', { class: 'warnbox' }, r.error)); return; }
        out.append(suggestList(cat, r));
      });
    }
    draw(); return sec;
  }
  function relinkBox(cur, e) {
    var box = h('div', { class: 'cfgpanel', style: 'margin:14px 0' }), inp = h('input', { type: 'text', maxlength: '80', placeholder: 'Anderer Titel oder Schreibweise', value: '', style: 'flex:1 1 200px;min-width:0;width:auto' }), res = h('div', { class: 'suggest wide2' });
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
      h('div', { class: 'add wide2', style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center' }, inp, h('button', { class: 'btn', type: 'button', onclick: search }, 'Suchen'), h('button', { class: 'btn ghost', type: 'button', onclick: function () { S.relink = null; content(); } }, 'Abbrechen')), res);
    setTimeout(function () { inp.focus(); }, 0);
    return box;
  }
  var TVS = { 'Running': 'läuft', 'Ended': 'beendet', 'In Development': 'in Planung', 'To Be Determined': 'offen' };
  var DONEV = { series: ['gesehen', 'Noch nicht gesehen', 'eye'], film: ['gesehen', 'Noch nicht gesehen', 'eye'], artist: ['gehört', 'Noch nicht gehört', 'headphones'], author: ['gelesen', 'Noch nicht gelesen', 'book'] };
  function setDone(e, keys, on, cb) { api('/api/data.php?a=done', { id: e.id, keys: keys, on: on ? 1 : 0 }).then(function (x) { if (x && x.ok) cb(); else if (x && x.error) window.alert(x.error); }); }
  function doneSetOf(r) { var o = {}; (r.done || []).forEach(function (k) { o[k] = 1; }); return o; }
  function renderEpisodes(e, eps, done) {
    done = done || {};
    var now = today0(), box = h('div', null), by = {};
    box.append(h('h3', { class: 'sec' }, 'Staffeln und Folgen'));
    box.append(h('p', { class: 'note' }, 'Folgen und Sendetermine von ', h('a', { href: e.tvmaze ? 'https://www.tvmaze.com/shows/' + e.tvmaze : 'https://www.tvmaze.com', target: '_blank', rel: 'noopener noreferrer' }, 'TVmaze'),
      e.fetched_at ? ', Stand ' + LONG.format(new Date(e.fetched_at.replace(' ', 'T'))) : '', e.tv_status ? '. Serienstatus: ' + (TVS[e.tv_status] || e.tv_status) : '', '. Die Angaben werden alle 12 Stunden erneuert.'));
    eps.forEach(function (x) { (by[x.season] = by[x.season] || []).push(x); });
    Object.keys(by).map(Number).sort(function (a, b) { return a - b; }).forEach(function (sn) {
      var l = by[sn], fut = 0; l.forEach(function (x) { var p = x.airdate ? parseDate(x.airdate) : null; if (p && p.prec !== 'none' && p.end >= now) fut++; });
      var past = l.length - fut, live = fut > 0, state = !live ? 'abgeschlossen' : past === 0 ? 'startet bald' : 'läuft gerade';
      var prog = h('span', { class: 'pill', title: 'So viele Folgen dieser Staffel hast du als gesehen markiert. Nur für dich sichtbar.' }), cks = [], allBtn = h('button', { class: 'btn ghost small', type: 'button' });
      function upd() { var n = cks.filter(function (c) { return done[c.k]; }).length; prog.textContent = n + ' von ' + l.length + ' gesehen'; prog.className = 'pill' + (n === l.length ? ' on' : ''); allBtn.textContent = n === l.length ? 'Staffel zurücksetzen' : 'Staffel als gesehen markieren';
        cks.forEach(function (c) { c.b.className = 'ck' + (done[c.k] ? ' on' : ''); c.b.setAttribute('aria-pressed', done[c.k] ? 'true' : 'false'); c.b.textContent = done[c.k] ? '✓' : ''; }); }
      var det = h('details', { class: 'season', open: live ? true : null }, h('summary', null, 'Staffel ' + sn, h('span', { class: 'sub' }, ' · ' + pl(l.length, 'Folge', 'Folgen') + ' · '), h('span', { class: live ? 'soon' : 'sub' }, state), ' ', prog));
      l.forEach(function (x) {
        var p = x.airdate ? parseDate(x.airdate) : null, ok = p && p.prec === 'day', isFut = ok && p.end >= now, diff = ok ? Math.round((p.start - now) / 864e5) : 0;
        var when = ok ? WDAY.format(p.start).replace('.', '') + ', ' + LONG.format(p.start) : 'Datum offen', stat = isFut ? (diff === 0 ? 'heute' : diff === 1 ? 'morgen' : 'in ' + diff + ' Tagen') : (ok ? 'gezeigt' : '');
        var ckb = h('button', { type: 'button', 'aria-label': 'Folge ' + x.number + ' als gesehen markieren' }); cks.push({ b: ckb, k: x.k });
        ckb.addEventListener('click', function () { var on = !done[x.k]; setDone(e, [x.k], on, function () { if (on) done[x.k] = 1; else delete done[x.k]; upd(); }); });
        det.append(h('div', { class: 'work ep' }, ckb, h('div', { class: 'yr' }, 'E' + String(x.number).padStart(2, '0')), h('div', null, h('div', { class: 'ttl' }, x.title || 'Titel noch offen'), h('div', { class: 'sub' }, when)), h('div', { class: 'stat ' + (isFut ? 'soon' : 'sub') }, stat)));
      });
      allBtn.addEventListener('click', function () { var keys = cks.map(function (c) { return c.k; }), on = !keys.every(function (k) { return done[k]; }); setDone(e, keys, on, function () { keys.forEach(function (k) { if (on) done[k] = 1; else delete done[k]; }); upd(); }); });
      upd();
      det.append(h('div', { class: 'season-foot' }, allBtn, ' ', amazonLink(e, e.name + ' Staffel ' + sn)));
      box.append(det);
    });
    return box;
  }
  function toast(msg) {
    var old = document.getElementById('sdtoast'); if (old) old.remove();
    var d = h('div', { id: 'sdtoast', class: 'sdtoast', role: 'status' }, msg); document.body.append(d);
    setTimeout(function () { if (d.parentNode) d.remove(); }, 7000);
  }
  function refresh(cur, auto) {
    var b = $('rfr'); if (b) { b.disabled = true; b.lastChild.textContent = 'Lädt …'; }
    api('/api/data.php?a=refresh&id=' + cur.id, {}).then(function (x) {
      if (!auto) toast((x.ok ? (x.msg || 'Aktualisiert.') : (x.msg || x.error || 'Das hat nicht geklappt.')) + (x.wait ? ' Eine weitere Aktualisierung dieses Eintrags ist erst nach 10 Minuten möglich.' : ''));
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
    box.append(h('p', { class: 'hint' }, s.list.length ? (s.byTitle ? 'Bücher mit diesem Titel. Welche Autor:in ist gemeint?' : 'Welcher Treffer ist gemeint?') : (s.byTitle ? 'Kein Buch mit diesem Titel gefunden.' : 'Keine Treffer.')));
    var list = h('div', { class: 'suggest' });
    s.list.forEach(function (x) { list.append(h('button', { class: 'row-btn', type: 'button', onclick: function () {
      if (x.title_search) { S.sugg = { loading: true }; drawSugg(); api('/api/data.php?a=search&type=author&mode=title&q=' + encodeURIComponent(s.q)).then(function (r) { S.sugg = { q: s.q, list: r.results || [], byTitle: true }; drawSugg(); }); return; }
      addNow(x.name, x.ext); } }, h('span', { class: 'nm' }, x.name), h('span', { class: 'tp' }, x.hint || ''))); });
    list.append(h('button', { class: 'row-btn', type: 'button', onclick: function () { addNow(s.q, {}); } }, h('span', { class: 'tp' }, '„' + s.q + '“ trotzdem so hinzufügen')));
    box.append(list);
  }

  /* ---------- Einstellungen ---------- */
  function setSkin(id) { var r = document.documentElement; if (id) r.setAttribute('data-skin', id); else r.removeAttribute('data-skin'); S.skin = id; }
  // Bereiche als abgegrenzte Karten mit Überschrift
  function setCard(title, kids, cls) {
    var c = h('section', { class: 'setcard' + (cls ? ' ' + cls : '') }, h('h3', null, title));
    kids.forEach(function (k) { if (k) c.append(k); }); return c;
  }
  function sectionize(p, firstTitle) {
    var h2 = p.querySelector('h2.sec'), kids = [].slice.call(p.children).filter(function (x) { return x !== h2; }), groups = [], cur = null;
    kids.forEach(function (k) {
      if (k.tagName === 'H3') { cur = { t: k.textContent, items: [] }; groups.push(cur); k.remove(); }
      else { if (!cur) { cur = { t: firstTitle, items: [] }; groups.push(cur); } cur.items.push(k); }
    });
    var st = h('div', { class: 'setstack' }); groups.forEach(function (g) { st.append(setCard(g.t, g.items)); }); p.append(st);
  }
  function settings() {
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Einstellungen'));
    var box = h('div', { class: 'cfgpanel' });
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Angemeldet als'), h('p', { style: 'margin:0' }, D.email + ' · Nr. ' + D.id + (isAdm() ? ' · Admin' : '') + (!D.admin && D.alpha ? ' · Alphatester' : ''))));
    var un = h('input', { type: 'text', value: D.username || '', maxlength: '20', 'aria-label': 'Benutzername', autocomplete: 'off' }), unMsg = h('p', { class: 'msg', role: 'status' }), unLeft = +D.uname_left || 0;
    if (unLeft) un.disabled = true;
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Benutzername'),
      h('p', { class: 'hint' }, D.username ? 'Diesen Namen sehen andere Nutzer. Zur Anmeldung brauchst du ihn nicht. Du kannst ihn alle 30 Tage ändern. Erlaubt sind 3 bis 20 Zeichen: Buchstaben (auch Umlaute), Ziffern, Punkt, Unterstrich und Bindestrich, ohne Leerzeichen und nicht am Anfang oder Ende.' : 'Du hast noch keinen Benutzernamen. Wähle einen, den andere Nutzer sehen dürfen (nicht für die Anmeldung). Erlaubt sind 3 bis 20 Zeichen: Buchstaben (auch Umlaute), Ziffern, Punkt, Unterstrich und Bindestrich, ohne Leerzeichen und nicht am Anfang oder Ende.'),
      unLeft ? h('p', { class: 'hint' }, 'Änderung wieder möglich in ' + pl(unLeft, 'Tag', 'Tagen') + '.') : null,
      h('div', { class: 'inline', style: 'margin-top:12px' }, un, h('button', { class: 'btn', type: 'button', disabled: unLeft > 0, onclick: function () { api('/api/auth.php?a=username', { username: un.value }).then(function (r) { if (r.ok) { D.username = r.username; D.uname_left = 30; unMsg.className = 'msg'; unMsg.textContent = 'Gespeichert.'; var w = $('who'); if (w) { var t = w.lastChild; if (t && t.nodeType === 3) t.textContent = r.username + ' · ' + D.email; } render(); } else { unMsg.className = 'msg err'; unMsg.textContent = r.error || 'Das hat nicht geklappt.'; } }); } }, 'Speichern')), unMsg));
    var skins = h('div', { class: 'skins', role: 'group', 'aria-label': 'Oberfläche wählen' });
    SKINS.forEach(function (k) { skins.append(h('button', { class: 'skin', type: 'button', 'aria-pressed': String((S.skin || '') === k[0]), onclick: function () { setSkin(k[0]); api('/api/data.php?a=skin', { skin: k[0] }); render(); } }, h('span', { class: 'dots' }, k[2].map(function (c) { var i = h('i'); i.style.background = c; return i; })), h('b', null, k[1]))); });
    var nerdMsg = h('span', { class: 'msg', style: 'margin:0' }), nerdCk = h('input', { type: 'checkbox', onchange: function () { var on = this.checked; api('/api/auth.php?a=nerd', { on: on }).then(function (r) { if (r.ok) { D.nerd = on; nerdMsg.className = 'msg'; nerdMsg.textContent = 'Gespeichert.'; renderVer(); } else { nerdCk.checked = !on; nerdMsg.className = 'msg err'; nerdMsg.textContent = r.error || 'Speichern nicht möglich.'; } }); } }); nerdCk.checked = !!D.nerd;
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Oberfläche'), skins,
      h('div', { style: 'margin-top:14px' }, h('label', { style: 'display:flex;gap:8px;align-items:center' }, nerdCk, 'Nerdoption: Genauere Info, was seit deinem letzten Besuch geändert wurde, und Nerdinfos zur Technik'), h('p', { class: 'hint', style: 'margin:2px 0 0 26px' }, 'Zeigt an der Versionsnummer, wie viele Änderungen neu sind, und im Versionsverlauf oben die neuen Einträge. Auf der Info-Seite erscheint oben der zusätzliche Reiter „Nerdinfos“ (Technik, Datenquellen, Dienste).'), nerdMsg)));
    var tm = h('span', { class: 'msg', style: 'margin:0' }), cb = h('input', { type: 'checkbox', id: 'ntf', onchange: function () { S.notify = this.checked; api('/api/data.php?a=notify', { on: this.checked }); opts.style.opacity = this.checked ? '1' : '.5'; } }); cb.checked = !!S.notify;
    var sv = h('span', { class: 'msg', style: 'margin:0' });
    function saveN() { api('/api/data.php?a=notifyset', { 'new': S.n['new'], day: S.n.day, week: S.n.week, mode: S.n.mode, weekday: S.n.weekday, bell_default: S.n.bell_default }).then(function (r) { sv.className = 'msg' + (r.ok ? '' : ' err'); sv.textContent = r.ok ? 'Gespeichert.' : (r.msg || r.error || 'Speichern nicht möglich.'); setTimeout(function () { sv.textContent = ''; }, 2500); }); }
    function chk(key, label) { var c = h('input', { type: 'checkbox', onchange: function () { S.n[key] = this.checked ? 1 : 0; saveN(); } }); c.checked = !!+S.n[key]; return h('label', { style: 'display:flex;gap:8px;align-items:center' }, c, label); }
    var wd = h('select', { style: 'width:auto', 'aria-label': 'Wochentag der Wochenübersicht', onchange: function () { S.n.weekday = +this.value; saveN(); } }, ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'].map(function (d, i) { return h('option', { value: String(i + 1) }, d); })); wd.value = String(S.n.weekday || 1);
    var md = h('select', { 'aria-label': 'Zustellung', onchange: function () { S.n.mode = this.value; saveN(); } }, h('option', { value: 'digest' }, 'Eine Sammelmail pro Tag (ab 7 Uhr)'), h('option', { value: 'instant' }, 'Sofort beim nächsten Abgleich (alle 2 Stunden, 7 bis 22 Uhr)')); md.value = S.n.mode === 'instant' ? 'instant' : 'digest';
    var nn = +S.n['new'], nd = +S.n.day, nw = +S.n.week, preset = (nn && nd) ? (nw ? 'daily_week' : 'daily') : (!nn && !nd && nw ? 'weekly' : 'custom');
    var ps = h('select', { 'aria-label': 'Wie möchtest du informiert werden', onchange: function () { var v = this.value; if (v === 'custom') return; S.n['new'] = v === 'weekly' ? 0 : 1; S.n.day = v === 'weekly' ? 0 : 1; S.n.week = v === 'daily' ? 0 : 1; saveN(); render(); } },
      h('option', { value: 'daily' }, 'Morgens, sobald es etwas Neues gibt'), h('option', { value: 'daily_week' }, 'Morgens bei Neuigkeiten und zusätzlich die Wochenübersicht'), h('option', { value: 'weekly' }, 'Nur die Wochenübersicht (eine Mail pro Woche)'), preset === 'custom' ? h('option', { value: 'custom' }, 'Eigene Auswahl (siehe unten)') : null); ps.value = preset;
    var opts = h('div', { class: 'nopts', style: 'display:grid;gap:8px;margin-top:12px;opacity:' + (S.notify ? '1' : '.5') },
      h('label', { class: 'field' }, h('span', null, 'Wie möchtest du informiert werden?'), ps), h('p', { class: 'hint', style: 'margin:0' }, 'Mit Kurzinfo und Presse-Links zu neuen Titeln. Im Detail kannst du es darunter anpassen.'),
      chk('new', 'Neu angekündigte Termine'), chk('day', 'Am Erscheinungstag (morgens ab 7 Uhr)'),
      h('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap' }, chk('week', 'Wochenübersicht (Termine der nächsten 14 Tage), jeden'), wd),
      h('label', { class: 'field', style: 'margin-top:4px' }, h('span', null, 'Zustellung'), md),
      chk('bell_default', 'Glocke bei neu hinzugefügten Einträgen standardmäßig an'),
      h('p', { style: 'margin:4px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center' },
        h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/data.php?a=bellall', { on: true }).then(function () { load(function () { render(); }); }); } }, 'Alle Glocken an'),
        h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/data.php?a=bellall', { on: false }).then(function () { load(function () { render(); }); }); } }, 'Alle Glocken aus'), sv));
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Benachrichtigungen'), h('p', { class: 'hint', style: 'margin-bottom:10px' }, 'Du bekommst Mails nur zu Einträgen, bei denen die Glocke an ist (sie sitzt im Kopf jedes Eintrags). Jede Mail enthält einen Abmeldelink.'),
      h('label', { style: 'display:flex;gap:8px;align-items:center' }, cb, 'E-Mail-Benachrichtigungen einschalten'), opts,
      h('p', { style: 'margin:14px 0 0' }, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { tm.className = 'msg'; tm.textContent = 'Sende …'; api('/api/data.php?a=testmail', {}).then(function (r) { tm.className = 'msg' + (r.ok ? '' : ' err'); tm.textContent = r.msg || r.error || ''; }); } }, 'Vorschau-Mail an mich senden'), ' ', tm)));
    var imp = h('div', { class: 'wide2' }), impBtns = h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }), impPanels = h('div'); imp.append(impBtns, impPanels);
    function splitCsv(l, dl) { var o = [], cur = '', q = false; for (var i = 0; i < l.length; i++) { var ch = l[i]; if (ch === '"') { if (q && l[i + 1] === '"') { cur += '"'; i++; } else q = !q; } else if (ch === dl && !q) { o.push(cur); cur = ''; } else cur += ch; } o.push(cur); return o; }
    function namesFrom(text, fname) {
      var out = [];
      function add(v) { if (typeof v === 'string' && v.trim()) out.push(v.trim()); }
      var t = text.replace(/^\ufeff/, '');
      if (/\.json$/i.test(fname) || /^\s*[\[{]/.test(t)) { try { (function walk(o) { if (Array.isArray(o)) o.forEach(function (x) { if (typeof x === 'string') add(x); else walk(x); }); else if (o && typeof o === 'object') { var hit = false, hasArr = Object.keys(o).some(function (k) { return Array.isArray(o[k]); }); ['artistName', 'artist_name', 'artist', 'name', 'Artist Name(s)'].forEach(function (k) { if (k === 'name' && hasArr) return; if (!hit && typeof o[k] === 'string') { add(o[k]); hit = true; } else if (!hit && o[k] && typeof o[k] === 'object' && typeof o[k].name === 'string') { add(o[k].name); hit = true; } }); if (!hit) Object.keys(o).forEach(function (k) { walk(o[k]); }); } })(JSON.parse(t)); return out; } catch (_) {} }
      var lines = t.split(/\r?\n/).filter(function (l) { return l.trim(); });
      if (/\.csv$/i.test(fname) || (lines[0] && lines[0].indexOf(',') > -1 && /artist|name|interpret/i.test(lines[0]))) {
        var dl = lines[0].indexOf(',') > -1 ? ',' : (lines[0].indexOf('\t') > -1 ? '\t' : ';'), hd = lines[0].split(dl).map(function (x) { return x.replace(/^"|"$/g, '').toLowerCase(); }), ci = 0; hd.forEach(function (x, i) { if (/artist|interpret|name/.test(x) && !ci && i >= 0 && ci === 0 && /artist|interpret/.test(x)) ci = i; });
        if (!ci) hd.forEach(function (x, i) { if (!ci && x === 'name') ci = i; });
        lines.slice(1).forEach(function (l) { var c = splitCsv(l, dl)[ci] || ''; c = c.replace(/^"|"$/g, ''); c.split(/;\s*/).forEach(add); });
        return out;
      }
      lines.forEach(add); return out;
    }
    var IMPCATS = [['author', 'Bücher', 'Titel', 'Titel von Büchern', 'Der Name der Reihe\nDie Känguru-Chroniken'], ['artist', 'Musik', 'Musiker:in', 'Namen von Musiker:innen', 'Radiohead\nBlind Guardian\nNightwish'], ['film', 'Filme', 'Film', 'Titel von Filmen', 'Der Pate\nAlien'], ['series', 'Serien', 'Serie', 'Titel von Serien', 'Dark\nBreaking Bad']];
    function mkImporter(label, fixed, hintFn, cls) {
      var ifile = h('input', { type: 'file', accept: '.json,.csv,.txt,text/plain,application/json,text/csv', 'aria-label': 'Datei laden' }), itext = h('textarea', { rows: '6', 'aria-label': 'Namen, einer pro Zeile', style: 'width:100%;margin-top:8px' }), imsg = h('span', { class: 'msg', style: 'margin:0' }), ipanel = h('div', { hidden: true, style: 'margin-top:10px' }), sel = null, hint = h('p', { class: 'hint' });
      function cat() { var v = fixed || (sel ? sel.value : 'artist'); return IMPCATS.filter(function (x) { return x[0] === v; })[0]; }
      function refresh() { var c = cat(); hint.textContent = hintFn(c); itext.placeholder = c[4]; }
      if (!fixed) { sel = h('select', { 'aria-label': 'Kategorie', onchange: refresh }); IMPCATS.forEach(function (x) { sel.append(h('option', { value: x[0] }, x[1])); }); sel.value = 'artist'; }
      ifile.addEventListener('change', function () {
        var f = ifile.files && ifile.files[0]; if (!f) return;
        if (f.size > 5 * 1024 * 1024) { imsg.className = 'msg err'; imsg.textContent = 'Die Datei ist größer als 5 MB.'; return; }
        var rd = new FileReader(); rd.onload = function () { var l = namesFrom(String(rd.result || ''), f.name), seen = {}; l = l.filter(function (x) { var k = x.toLowerCase(); if (seen[k]) return false; seen[k] = 1; return true; }); itext.value = l.join('\n'); imsg.className = 'msg'; imsg.textContent = l.length ? ((l.length === 1 ? '1 Name erkannt. Du kannst ihn unten noch ändern.' : l.length + ' Namen erkannt. Du kannst sie unten noch ändern.')) : 'In der Datei habe ich keine Namen gefunden.'; };
        rd.onerror = function () { imsg.className = 'msg err'; imsg.textContent = 'Die Datei konnte nicht gelesen werden.'; }; rd.readAsText(f);
      });
      var kids = [hint];
      if (sel) kids.push(h('p', { style: 'margin:8px 0' }, h('label', { style: 'display:flex;gap:8px;align-items:center' }, 'Kategorie', sel)));
      kids.push(ifile, itext, h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' }, h('button', { class: 'btn', type: 'button', onclick: function () {
        var l = itext.value.split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean); if (!l.length) { imsg.className = 'msg err'; imsg.textContent = 'Keine Namen gefunden.'; return; }
        var c = cat(); imsg.className = 'msg'; imsg.textContent = 'Importiere …';
        api('/api/data.php?a=import', { names: l, type: c[0] }).then(function (r) { if (!r.ok) { imsg.className = 'msg err'; imsg.textContent = r.msg || r.error || 'Das hat nicht geklappt.'; return; } imsg.textContent = r.added + ' ' + (r.added === 1 ? c[2] : (c[0] === 'artist' ? 'Musiker:innen' : c[1])) + ' hinzugefügt' + (r.had ? ', ' + r.had + ' schon auf der Liste' : '') + (r.over ? ', ' + r.over + ' nicht mehr möglich (Grenze 100 pro Liste)' : '') + '.'; itext.value = ''; load(function () { nav(); }); });
      } }, 'Importieren'), imsg));
      ipanel.append.apply(ipanel, kids); refresh();
      impBtns.append(h('button', { class: 'btn ghost small' + (cls ? ' ' + cls : ''), type: 'button', 'aria-expanded': 'false', onclick: function () { var open = ipanel.hidden; document.querySelectorAll('.imppanel').forEach(function (p) { p.hidden = true; }); ipanel.hidden = !open; impBtns.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); if (open) this.setAttribute('aria-expanded', 'true'); } }, label)); impPanels.append(ipanel);
      ipanel.classList.add('imppanel');
    }
    mkImporter('Listen importieren', null, function (c) { return 'Wähle die Kategorie und füge ' + c[3] + ' ein, einen pro Zeile, oder lade eine Datei (JSON, CSV oder Text). Die erkannten Namen erscheinen unten, du kannst sie vor dem Import ändern. Die Angaben dazu werden danach nach und nach geladen, bei langen Listen kann das einige Stunden dauern.'; });
    mkImporter('Spotify-Import', 'artist', function () { return 'Importiere die Musiker:innen aus deinem Spotify-Datenexport (JSON oder CSV). Die erkannten Namen erscheinen unten, du kannst sie vor dem Import prüfen und ändern. Eine ausführliche Schritt-für-Schritt-Anleitung folgt in Kürze.'; });
    box.append(imp);
    // Trakt-Import (Export-ZIP oder einzelne JSON-Dateien), alles im Browser ausgewertet
    var tImp = h('div', { class: 'wide2' }), tFile = h('input', { type: 'file', multiple: true, accept: '.zip,.json,application/zip,application/json', 'aria-label': 'Trakt-Export laden' }), tMsg = h('p', { class: 'msg', role: 'status' }), tGo = h('button', { class: 'btn', type: 'button', hidden: true }, 'Importieren'), tPanel = h('div', { hidden: true, style: 'margin-top:10px' }), tItems = [];
    function zipRead(buf) {
      var dv = new DataView(buf), u8 = new Uint8Array(buf), out = [], eocd = -1, i;
      for (i = buf.byteLength - 22; i >= Math.max(0, buf.byteLength - 70000); i--) { if (dv.getUint32(i, true) === 0x06054b50) { eocd = i; break; } }
      if (eocd < 0) return Promise.reject(new Error('Das ist keine gültige ZIP-Datei.'));
      var n = dv.getUint16(eocd + 10, true), p0 = dv.getUint32(eocd + 16, true), jobs = [];
      for (i = 0; i < n && i < 500; i++) {
        if (dv.getUint32(p0, true) !== 0x02014b50) break;
        var meth = dv.getUint16(p0 + 10, true), cs = dv.getUint32(p0 + 20, true), us = dv.getUint32(p0 + 24, true), nl = dv.getUint16(p0 + 28, true), el = dv.getUint16(p0 + 30, true), cl = dv.getUint16(p0 + 32, true), lo = dv.getUint32(p0 + 42, true);
        var name = new TextDecoder().decode(u8.subarray(p0 + 46, p0 + 46 + nl)); p0 += 46 + nl + el + cl;
        if (!/\.json$/i.test(name) || us > 60 * 1024 * 1024) continue;
        var ds = lo + 30 + dv.getUint16(lo + 26, true) + dv.getUint16(lo + 28, true), data = u8.subarray(ds, ds + cs);
        (function (nm, m, d) {
          if (m === 0) jobs.push(Promise.resolve({ name: nm, text: new TextDecoder().decode(d) }));
          else if (m === 8 && window.DecompressionStream) jobs.push(new Response(new Blob([d]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).text().then(function (t) { return { name: nm, text: t }; }));
        })(name, meth, data);
      }
      if (!jobs.length) return Promise.reject(new Error(window.DecompressionStream ? 'In der ZIP-Datei habe ich keine JSON-Dateien gefunden.' : 'Dein Browser kann ZIP-Dateien nicht entpacken. Entpacke sie bitte selbst und wähle die JSON-Dateien aus.'));
      return Promise.all(jobs);
    }
    function traktParse(docs) {
      var map = {}, order = [];
      function get(type, name, year) { var k = type + '|' + name.toLowerCase(); if (!map[k]) { map[k] = { type: type, name: name, year: year || null, eps: {} }; order.push(k); } return map[k]; }
      function walk(o, hint) {
        if (Array.isArray(o)) { o.forEach(function (x) { walk(x, hint); }); return; }
        if (!o || typeof o !== 'object') return;
        if (o.show && typeof o.show.title === 'string') {
          var it = get('series', o.show.title, o.show.year);
          if (o.episode && typeof o.episode.number === 'number') it.eps[(o.episode.season | 0) + 'x' + o.episode.number] = [o.episode.season | 0, o.episode.number];
          (o.seasons || []).forEach(function (sn) { (sn.episodes || []).forEach(function (ep) { if (typeof ep.number === 'number') it.eps[(sn.number | 0) + 'x' + ep.number] = [sn.number | 0, ep.number]; }); });
          return;
        }
        if (o.movie && typeof o.movie.title === 'string') { get('film', o.movie.title, o.movie.year); return; }
        if (typeof o.title === 'string' && o.ids && typeof o.ids === 'object' && (hint === 'film' || hint === 'series')) { get(hint, o.title, o.year); return; }
        Object.keys(o).forEach(function (k) { if (o[k] && typeof o[k] === 'object') walk(o[k], hint); });
      }
      docs.forEach(function (d) { var hint = /movie|film/i.test(d.name) ? 'film' : /show|serie|episode/i.test(d.name) ? 'series' : ''; try { walk(JSON.parse(d.text.replace(/^﻿/, '')), hint); } catch (_) {} });
      return order.map(function (k) { var x = map[k]; return { type: x.type, name: x.name, year: x.year, eps: Object.keys(x.eps).map(function (e) { return x.eps[e]; }) }; });
    }
    tFile.addEventListener('change', function () {
      var fs = [].slice.call(tFile.files || []); if (!fs.length) return; tGo.hidden = true; tItems = [];
      tMsg.className = 'msg'; tMsg.textContent = 'Lese Datei …';
      Promise.all(fs.map(function (f) {
        if (f.size > 80 * 1024 * 1024) return Promise.reject(new Error('Eine Datei ist größer als 80 MB.'));
        return /\.zip$/i.test(f.name) ? f.arrayBuffer().then(zipRead) : f.text().then(function (t) { return [{ name: f.name, text: t }]; });
      })).then(function (parts) {
        tItems = traktParse([].concat.apply([], parts));
        var ns = tItems.filter(function (x) { return x.type === 'series'; }).length, nf = tItems.length - ns, ne = tItems.reduce(function (a, x) { return a + x.eps.length; }, 0);
        if (!tItems.length) { tMsg.className = 'msg err'; tMsg.textContent = 'Ich habe keine Serien oder Filme gefunden. Wähle die ZIP-Datei aus dem Trakt-Export oder JSON-Dateien daraus (z. B. Watchlist und Verlauf).'; return; }
        tMsg.className = 'msg'; tMsg.textContent = 'Erkannt: ' + pl(ns, 'Serie', 'Serien') + ', ' + pl(nf, 'Film', 'Filme') + (ne ? ' und ' + pl(ne, 'gesehene Folge', 'gesehene Folgen') : '') + '. Pro Liste passen höchstens 100 Einträge, der Rest wird übersprungen.'; tGo.hidden = false;
      }).catch(function (e) { tMsg.className = 'msg err'; tMsg.textContent = e.message || 'Die Datei konnte nicht gelesen werden.'; });
    });
    tGo.addEventListener('click', function () {
      var items = tItems.slice(), tot = { series: { added: 0, had: 0, over: 0 }, film: { added: 0, had: 0, over: 0 }, eps: 0 }; tGo.disabled = true; tMsg.className = 'msg'; tMsg.textContent = 'Importiere …';
      (function next() {
        if (!items.length) {
          tGo.disabled = false; tGo.hidden = true; tItems = [];
          function part(k, one, many) { var o = tot[k]; return pl(o.added, one, many) + ' neu' + (o.had ? ', ' + o.had + ' schon vorhanden' : '') + (o.over ? ', ' + o.over + ' wegen der Grenze von 100 übersprungen' : ''); }
          tMsg.textContent = 'Fertig: ' + part('series', 'Serie', 'Serien') + '; ' + part('film', 'Film', 'Filme') + '; ' + pl(tot.eps, 'Folge', 'Folgen') + ' als gesehen markiert. Die Angaben werden nach und nach geladen.';
          load(function () { nav(); }); return;
        }
        api('/api/data.php?a=trakt', { items: items.splice(0, 40).map(function (x) { return { type: x.type, name: x.name, eps: x.eps }; }) }).then(function (r) {
          if (!r.ok) { tGo.disabled = false; tMsg.className = 'msg err'; tMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; }
          ['series', 'film'].forEach(function (k) { ['added', 'had', 'over'].forEach(function (f) { tot[k][f] += r[k][f]; }); }); tot.eps += r.eps; next();
        });
      })();
    });
    tPanel.append(h('p', { class: 'hint' }, 'Lade deinen Datenexport von Trakt hoch (die ZIP-Datei oder einzelne JSON-Dateien daraus, z. B. Watchlist und Verlauf). Serien und Filme kommen auf deine Liste, bei Serien werden die gesehenen Folgen abgehakt. Die Datei wird nur in deinem Browser gelesen, übertragen werden die Titel und gesehenen Folgen. Die Angaben zu den Titeln werden danach nach und nach geladen.'), h('p', { class: 'hint' }, 'Erlaubt sind ZIP- und JSON-Dateien (bis 80 MB), keine CSV-Dateien. Gelesen werden Serien und Filme im Aufbau von Trakt. Exporte anderer Dienste können ebenfalls klappen, wenn sie ähnlich aufgebaut sind: Serien und Filme als eigene Einträge mit den Feldern „title“ und „ids“, am besten in Dateien, deren Name „show“, „serie“, „movie“ oder „film“ enthält. Ob etwas erkannt wurde, siehst du vor dem Import. Klappt es nicht, melde dich gern über „Kontakt“ (Bugmeldung) mit dem Namen der Seite und einem kleinen Beispiel aus der Datei, ohne persönliche Daten.'), tFile, h('p', { style: 'margin:8px 0 0' }, tGo), tMsg);
    tImp.append(h('button', { class: 'btn ghost small', type: 'button', 'aria-expanded': 'false', onclick: function () { tPanel.hidden = !tPanel.hidden; this.setAttribute('aria-expanded', String(!tPanel.hidden)); } }, 'Trakt-Export importieren'), tPanel);
    tImp.style.marginTop = '12px'; imp.append(tImp); // gehört in dieselbe Karte „Import“ (die Karten werden über die Reihenfolge der Blöcke zugeordnet)
    // Kalender-Abo (.ics)
    var calUrl = h('input', { type: 'text', readonly: true, 'aria-label': 'Link zum Kalender-Abo', style: 'width:100%;margin-top:8px', onclick: function () { this.select(); } }), calMsg = h('p', { class: 'msg', role: 'status' }), calBtns = h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' });
    function calSet(mode) { calMsg.className = 'msg'; calMsg.textContent = 'Einen Moment …'; api('/api/data.php?a=caltoken', { mode: mode }).then(function (r) { if (!r.ok) { calMsg.className = 'msg err'; calMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; } calMsg.textContent = mode === 'off' ? 'Das Abo ist beendet, der alte Link funktioniert nicht mehr.' : (mode === 'reset' ? 'Neuer Link erzeugt, der alte funktioniert nicht mehr.' : ''); calDraw(r.url); }); }
    function calDraw(url) {
      calBtns.textContent = ''; calUrl.hidden = !url;
      if (!url) { calBtns.append(h('button', { class: 'btn small', type: 'button', onclick: function () { calSet('create'); } }, 'Link erzeugen')); return; }
      calUrl.value = url;
      calBtns.append(
        h('button', { class: 'btn small', type: 'button', onclick: function () { calUrl.select(); var ok = false; try { if (navigator.clipboard) { navigator.clipboard.writeText(url); ok = true; } else ok = document.execCommand('copy'); } catch (_) {} calMsg.className = 'msg'; calMsg.textContent = ok ? 'Link kopiert.' : 'Bitte den markierten Link selbst kopieren.'; } }, 'Link kopieren'),
        h('a', { class: 'btn ghost small', href: url.replace(/^https?:/, 'webcal:') }, 'In Kalender-App öffnen'),
        h('button', { class: 'btn ghost small', type: 'button', onclick: function () { if (confirm('Einen neuen Link erzeugen? Der alte funktioniert dann nicht mehr und muss im Kalender ersetzt werden.')) calSet('reset'); } }, 'Neuen Link erzeugen'),
        h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Das Kalender-Abo beenden? Der Link funktioniert dann nicht mehr.')) calSet('off'); } }, 'Abo beenden'));
    }
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Kalender-Abo'),
      h('p', { class: 'hint' }, 'Mit diesem Link zeigt dein Kalender (Google, Apple, Outlook, Thunderbird) alle angekündigten Termine deiner Einträge und aktualisiert sich selbst. Der Link ist privat: Wer ihn kennt, sieht deine Termine, gib ihn also nicht weiter. Termine ohne genaues Datum erscheinen nicht. Je nach Kalender-App dauert die Aktualisierung einige Stunden.'),
      calUrl, calBtns, calMsg));
    calUrl.hidden = true; api('/api/data.php?a=calinfo').then(function (r) { if (r.error) { calMsg.className = 'msg err'; calMsg.textContent = r.error; return; } calDraw(r.url); });
    // Zwei-Faktor-Anmeldung
    var tfaBox = h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Zwei-Faktor-Anmeldung'));
    function tfaApi(a, data) { return fetch('/api/auth.php?a=' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), credentials: 'same-origin' }).then(function (r) { return r.json().catch(function () { return { error: 'Unerwartete Antwort' }; }); }); }
    function qrLoad(ok, fail) { if (window.qrcode) return ok(window.qrcode); var sc = document.createElement('script'); sc.src = '/vendor/qrcode.js'; sc.onload = function () { window.qrcode ? ok(window.qrcode) : fail(); }; sc.onerror = fail; document.head.append(sc); }
    function tfaDraw(codes) {
      while (tfaBox.children.length > 1) tfaBox.removeChild(tfaBox.lastChild);
      var msg = h('p', { class: 'msg', role: 'status' }), pw = h('input', { type: 'password', autocomplete: 'current-password', 'aria-label': 'Passwort', placeholder: 'Passwort' }), cd = h('input', { type: 'text', autocomplete: 'one-time-code', 'aria-label': 'Code', placeholder: 'Code aus der App oder Backup-Code', maxlength: 12 });
      function err(r) { msg.className = 'msg err'; msg.textContent = r.error || 'Das hat nicht geklappt.'; }
      function codeList(c) { return h('div', null, h('p', { class: 'hint' }, 'Backup-Codes: Jeder Code gilt einmal, falls du dein Handy nicht hast. Speichere sie jetzt, sie werden nicht noch einmal angezeigt.'), h('pre', { class: 'codes' }, c.join('\n')), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { try { navigator.clipboard.writeText(c.join('\n')); } catch (_) {} } }, 'Codes kopieren')); }
      if (codes) { tfaBox.append(h('p', { class: 'msg' }, 'Gespeichert.'), codeList(codes)); }
      if (D.totp) {
        tfaBox.append(h('p', { class: 'hint' }, 'Aktiv: Bei der Anmeldung brauchst du zusätzlich einen Code aus deiner Authenticator-App.'),
          h('p', { style: 'display:grid;gap:8px;max-width:340px;margin:8px 0 0' }, pw, cd,
            h('span', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
              h('button', { class: 'btn ghost small', type: 'button', onclick: function () { tfaApi('totp_codes', { password: pw.value, code: cd.value }).then(function (r) { if (r.ok) tfaDraw(r.codes); else err(r); }); } }, 'Neue Backup-Codes'),
              h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (!confirm('Die Zwei-Faktor-Anmeldung ausschalten?')) return; tfaApi('totp_disable', { password: pw.value, code: cd.value }).then(function (r) { if (r.ok) { D.totp = false; tfaDraw(); } else err(r); }); } }, 'Ausschalten'))), msg);
      } else {
        tfaBox.append(h('p', { class: 'hint' }, 'Schützt dein Konto zusätzlich zum Passwort mit einem Code aus einer Authenticator-App (zum Beispiel Google Authenticator, Microsoft Authenticator, Aegis oder 2FAS).'),
          h('p', { style: 'display:grid;gap:8px;max-width:340px;margin:8px 0 0' }, pw, h('button', { class: 'btn small', type: 'button', onclick: function () {
            tfaApi('totp_start', { password: pw.value }).then(function (r) {
              if (!r.ok) { err(r); return; }
              var c2 = h('input', { type: 'text', inputmode: 'numeric', autocomplete: 'one-time-code', 'aria-label': 'Code aus der App', placeholder: '6-stelliger Code', maxlength: 8 });
              while (tfaBox.children.length > 1) tfaBox.removeChild(tfaBox.lastChild);
              var qrBox = h('div', { class: 'qrbox', 'aria-label': 'QR-Code zum Einrichten' }); qrLoad(function (Q) { try { var q = Q(0, 'M'); q.addData(r.uri); q.make(); qrBox.innerHTML = q.createSvgTag({ cellSize: 5, margin: 4, scalable: true }); } catch (_) { qrBox.remove(); } }, function () { qrBox.remove(); });
              tfaBox.append(h('p', { class: 'hint' }, '1. Öffne die Authenticator-App und scanne diesen QR-Code. Auf dem Handy selbst öffnet dieser Link die App direkt: ', h('a', { href: r.uri }, 'In App öffnen')),
                qrBox, h('p', { class: 'hint' }, 'Oder den Schlüssel von Hand eintragen:'), h('pre', { class: 'codes' }, r.secret), h('p', { class: 'hint' }, '2. Gib den Code ein, den die App jetzt anzeigt:'),
                h('p', { style: 'display:grid;gap:8px;max-width:340px;margin:8px 0 0' }, c2, h('button', { class: 'btn small', type: 'button', onclick: function () { tfaApi('totp_enable', { code: c2.value }).then(function (q) { if (q.ok) { D.totp = true; tfaDraw(q.codes); } else { msg.className = 'msg err'; msg.textContent = q.error || 'Das hat nicht geklappt.'; } }); } }, 'Bestätigen und einschalten')), msg);
            });
          } }, 'Einrichten starten')), msg);
      }
    }
    tfaDraw(); box.append(tfaBox);
    var adminSees = h('p', { class: 'hint wide2' });
    function drawAdminSees() {
      adminSees.textContent = 'Der Administrator sieht deinen Benutzernamen, deine Mitgliedsnummer und die Zahl deiner Einträge sowie ' + (D.hide_activity ? 'nur grob, wann du zuletzt hier warst (zum Beispiel „vor über einer Woche“)' : 'wann du zuletzt hier warst') + '. Deine E-Mail-Adresse wird ihm nicht angezeigt. Nur im Ausnahmefall (Missbrauch, Rechtsanfragen) kann er sie einzeln aufdecken, das wird protokolliert. Technisch bleibt die Adresse für Anmeldung und Mails auf dem Server gespeichert. Deine Einträge selbst bleiben privat.';
    }
    drawAdminSees();
    var pm = h('span', { class: 'msg', style: 'margin:0' });
    function savePriv() { api('/api/auth.php?a=privacy', { hide_activity: !!D.hide_activity }).then(function (r) { pm.className = 'msg' + (r.ok ? '' : ' err'); pm.textContent = r.ok ? 'Gespeichert.' : (r.error || 'Speichern nicht möglich.'); drawAdminSees(); }); }
    function privChk(key, label, hint) { var c = h('input', { type: 'checkbox', onchange: function () { D[key] = this.checked; savePriv(); } }); c.checked = !!D[key]; return h('div', null, h('label', { style: 'display:flex;gap:8px;align-items:center' }, c, label), h('p', { class: 'hint', style: 'margin:2px 0 0 26px' }, hint)); }
    box.append(h('div', { class: 'wide2 privbox' }, h('p', { class: 'label' }, 'Privatsphäre'),
      h('p', { class: 'hint', style: 'margin-bottom:10px' }, 'Du entscheidest, wie genau der Administrator sieht, wann du zuletzt hier warst. Deine E-Mail-Adresse bekommt er in der Nutzerverwaltung grundsätzlich nicht angezeigt. Andere Nutzer sehen ohnehin nur deinen Benutzernamen.'),
      privChk('hide_activity', 'Letzte Anmeldung nur grob anzeigen', 'Statt Datum und Uhrzeit steht dort nur „in den letzten 7 Tagen“, „vor über einer Woche“, „vor über einem Monat“ oder „vor über 3 Monaten“.'), pm));
    box.append(adminSees);
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Meine Daten'),
      h('p', { class: 'hint' }, 'Lade alles herunter, was zu dir gespeichert ist: Konto, Einstellungen, Liste, Nachrichten sowie deine Ideen und Bugs (hochgeladene Bilder sind nicht enthalten). Die Liste gibt es auch als CSV-Tabelle, die du zum Beispiel in einer Tabellenkalkulation oder später für einen Import nutzen kannst.'),
      h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' },
        h('a', { class: 'btn ghost small', href: '/api/data.php?a=export&fmt=json', download: '' }, 'Alle Daten (JSON)'),
        h('a', { class: 'btn ghost small', href: '/api/data.php?a=export&fmt=csv', download: '' }, 'Liste (CSV)'))));
    if (!isAdm()) {
      var dm = h('span', { class: 'msg', style: 'margin:0' }), pw = h('input', { type: 'password', autocomplete: 'current-password', placeholder: 'Passwort zur Bestätigung', style: 'max-width:260px' }), dbox = h('div', { hidden: true, style: 'margin-top:10px' });
      dbox.append(h('p', { class: 'hint' }, 'Dein Konto wird zur Löschung vorgemerkt und du wirst abgemeldet. Deine Daten (Konto, Listen, Einstellungen, Nachrichten) bleiben noch eine Woche gespeichert, falls du es dir anders überlegst: Melde dich einfach wieder an und brich die Löschung ab. Danach werden sie endgültig gelöscht.'), h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' }, pw,
        h('button', { class: 'btn warn small', type: 'button', onclick: function () { dm.className = 'msg'; dm.textContent = ''; api('/api/auth.php?a=delete_account', { password: pw.value }).then(function (r) { if (r && r.ok) location.reload(); else { dm.className = 'msg err'; dm.textContent = (r && r.error) || 'Das hat nicht geklappt.'; } }); } }, 'Konto jetzt löschen'), dm));
      box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Konto löschen'), h('p', { class: 'hint' }, 'Du kannst dein Konto samt aller Einträge jederzeit löschen lassen.'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { dbox.hidden = !dbox.hidden; if (!dbox.hidden) pw.focus(); } }, 'Konto löschen …'), dbox));
    }
    var K = [].slice.call(box.children), st = h('div', { class: 'setstack' });
    st.append(setCard('Profil', [K[0], K[1]]), setCard('Darstellung', [K[2]], 'solo'), setCard('Benachrichtigungen', [K[3]], 'solo'), setCard('Import', [K[4]], 'solo'), setCard('Kalender-Abo', [K[5]], 'solo'), setCard('Zwei-Faktor-Anmeldung', [K[6]], 'solo'));
    if (K[10]) K[10].classList.add('dangerzone');
    st.append(setCard('Privatsphäre', [K[7], K[8]], 'solo'), setCard('Meine Daten', [K[9]], 'solo'));
    if (K[10]) st.append(setCard('Konto löschen', [K[10]], 'danger solo'));
    p.append(st); mainEl.append(p);
  }

  /* ---------- Info ---------- */
  function cronSection() {
    var cronBox = h('div', { class: 'cronbox' }, h('p', { class: 'state pulse' }, 'Lade …'));
    function ts(x) { x = String(x || ''); return x ? x.slice(8, 10) + '.' + x.slice(5, 7) + '. ' + x.slice(11, 16) : 'nie'; }
    function cronLoad() {
      api('/api/admin.php?a=cron').then(function (r) {
        cronBox.textContent = '';
        if (r.error) { cronBox.append(h('p', { class: 'warnbox' }, r.error)); return; }
        if (r.needs_migration) cronBox.append(h('p', { class: 'warnbox' }, 'Für das Protokoll der Läufe fehlt noch die Migration 021 (migrate.php einmal aufrufen).'));
        if (r.ops) { var o = r.ops, hr = function (m) { return m == null ? 'nie' : (m < 90 ? 'vor ' + m + ' Min.' : 'vor ' + Math.round(m / 60) + ' Std.'); };
          cronBox.append(h('p', { class: o.cron_overdue ? 'warnbox' : 'hint' }, 'Cron-Job: letzter Lauf ' + hr(o.cron_minutes) + (o.cron_overdue ? ' – überfällig, eine Warnmail ist unterwegs oder bereits verschickt.' : ' – in Ordnung.')),
            h('p', { class: o.backup_overdue || o.backup_error ? 'warnbox' : 'hint' }, 'Datenbanksicherung: ' + (o.backup_info ? 'zuletzt ' + o.backup_info + ' (' + hr(o.backup_minutes) + ')' : 'noch keine') + (o.backup_error ? ' – Fehler: ' + o.backup_error : '') + (o.backup_overdue ? ' – überfällig.' : '')),
            h('p', { class: 'hint' }, 'Überwachung von außen: Adresse /api/status.php (mit ?strict=1 meldet sie auch Cron- und Sicherungsausfall) in einem Dienst wie UptimeRobot eintragen.')); }
        if (r.api_errors === null) cronBox.append(h('p', { class: 'warnbox' }, 'Für das Fehlerprotokoll der Datenquellen fehlt noch die Migration 045 (migrate.php einmal aufrufen).'));
        else if (r.api_errors) {
          cronBox.append(h('h4', null, 'Schnittstellen (Fehler der letzten 7 Tage)'));
          var SRC = ['MusicBrainz', 'ListenBrainz', 'TVmaze', 'Open Library', 'Google Books', 'Deutsche Nationalbibliothek', 'lobid.org', 'Wikipedia', 'Wikidata', 'DeepL', 'Google News'], by = {};
          r.api_errors.forEach(function (x) { (by[x.source] = by[x.source] || []).push(x); if (SRC.indexOf(x.source) < 0) SRC.push(x.source); });
          var ae = h('ul', { class: 'cronruns' });
          SRC.forEach(function (n) {
            var l = by[n];
            if (!l) { ae.append(h('li', null, h('b', null, n), ' · keine Schnittstellenfehler')); return; }
            l.forEach(function (x) { ae.append(h('li', { style: 'color:#a3281c' }, h('b', null, n), ' · ' + x.kind + ' · ' + x.cnt + '×, zuletzt ' + ts(x.last_at) + (x.first_at && String(x.first_at).slice(0, 10) !== String(x.last_at).slice(0, 10) ? ', seit ' + ts(x.first_at) : ''), x.message ? h('div', { class: 'hint' }, x.message) : null)); });
          });
          cronBox.append(ae, h('p', { class: 'hint' }, 'Protokolliert werden nur Komplettfehler (nicht erreichbar, Limit oder Kontingent, Zugang abgelehnt, Fehler beim Dienst), keine einzelnen „nicht gefunden“. TMDB ist noch nicht angebunden.'));
          if (r.api_errors.length) cronBox.append(h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/admin.php?a=apierrors_clear', {}).then(function () { cronLoad(); }); } }, 'Liste leeren'));
        }
        var tbl = h('table', { class: 'crontbl' }, h('thead', null, h('tr', null, ['Kategorie', 'Einträge', 'nie geladen', 'älter als 12 h', 'ohne Werke', 'ältester Stand'].map(function (x) { return h('th', null, x); }))));
        var tb = h('tbody'); (r.stats || []).forEach(function (x) { tb.append(h('tr', { class: +x.old12 || +x.never ? 'warnrow' : '' }, h('td', null, (TYPES[x.type] ? TYPES[x.type].plural : x.type)), h('td', null, x.total), h('td', null, x.never), h('td', null, x.old12), h('td', null, x.empty), h('td', null, ts(x.oldest)))); });
        tbl.append(tb); cronBox.append(h('div', { class: 'tblwrap' }, tbl));
        if (r.runs) {
          cronBox.append(h('h4', null, 'Letzte Läufe'));
          if (!r.runs.length) cronBox.append(h('p', { class: 'hint' }, 'Noch kein Lauf protokolliert. Der nächste Cron-Lauf erscheint hier.'));
          var ul = h('ul', { class: 'cronruns' });
          r.runs.forEach(function (x) { var d = {}; try { d = JSON.parse(x.details || '{}'); } catch (_) {}
            var f = d.failed || [];
            ul.append(h('li', null, h('b', null, ts(x.started_at)), ' · ' + pl(x.ok_count, 'aktualisiert', 'aktualisiert') + ', ' + x.fail_count + ' fehlgeschlagen, ' + (Math.round(x.duration_ms / 100) / 10) + ' s' + (+x.stopped_early ? ' · Zeitbudget erreicht, noch ' + x.left_count + ' offen' : ''),
              f.length ? h('details', null, h('summary', null, 'Fehlgeschlagene anzeigen'), h('ul', null, f.map(function (z) { return h('li', null, z); }))) : null)); });
          cronBox.append(ul);
        }
        function lst(title, items, note) { if (!items || !items.length) return; cronBox.append(h('details', { class: 'cronlist' }, h('summary', null, title + ' (' + items.length + (items.length >= 40 ? '+' : '') + ')'), note ? h('p', { class: 'hint' }, note) : null, h('ul', null, items.map(function (x) { return h('li', null, (TYPES[x.type] ? TYPES[x.type].label : x.type) + ': ' + x.name + ' · Stand ' + ts(x.fetched_at)); })))); }
        lst('Noch nicht aktuell (älter als 12 Stunden oder nie geladen)', r.stale);
        lst('Geprüft, aber ohne Werke', r.empty, 'Die Quelle hat nichts gefunden oder der Name passt nicht. „Treffer ändern“ im Menü des Eintrags hilft.');
      });
    }
    cronLoad();
    return [h('p', { class: 'hint' }, 'Der Cron-Job aktualisiert die ältesten Einträge zuerst und arbeitet reihum Serien, Musiker:innen und Autor:innen ab, solange sein Zeitbudget (80 Sekunden) reicht. Danach laufen die Mails. Die Werte werden beim Öffnen der Seite neu geladen.'), cronBox];
  }

  /* ---------- Entstehung und Aufwand (Info-Seite, für alle) ---------- */
  function effortSection() {
    function tbl(head, rows) {
      return h('div', { class: 'nerdtbl' }, h('table', null, h('thead', null, h('tr', null, head.map(function (x) { return h('th', null, x); }))),
        h('tbody', null, rows.map(function (r) { return h('tr', null, r.map(function (x, i) { return h('td', i === 0 ? { class: 'k' } : null, x); })); }))));
    }
    return h('section', { class: 'infosec' }, h('h3', null, 'Entstehung und Aufwand'),
      h('p', { class: 'hint' }, 'Stand 08.11.2026. Alle Zahlen außer dem Umfang sind grobe Schätzungen und können deutlich danebenliegen: Es gibt keinen Zähler für Zeit, Tokens oder Wasser.'),
      h('p', null, 'Diese Seite ist mit Hilfe einer KI entstanden: Der Betreiber hat sie gemeinsam mit Claude (Anthropic) entwickelt. Die Idee, die Entscheidungen, das Testen und die Freigaben liegen beim Betreiber, den Code hat überwiegend die KI geschrieben.'),
      h('p', null, 'Umfang (gemessen): rund 7.300 Zeilen PHP, JavaScript und CSS, 12 API-Dateien, 197 Änderungen in der Versionsverwaltung seit dem 03.11.2026. Die Gespräche zur Seite laufen seit Mitte Oktober 2026, also etwa dreieinhalb Wochen.'),
      tbl(['Größe', 'Schätzung'], [
        ['Aktive Arbeitszeit des Betreibers (testen, Screenshots, Entscheidungen)', 'etwa 30–50 Stunden'],
        ['Ausgabe der KI (Code und Texte)', 'grob 2–4 Mio. Tokens'],
        ['Eingabe der KI inklusive wiederholt gelesenem Kontext', 'einige hundert Mio. Tokens, überwiegend aus dem Zwischenspeicher'],
        ['Rechnerischer Wert nach API-Listenpreisen', 'grob 200–600 €; im Abo pauschal enthalten, der genaue Preis des Modells ist nicht bekannt'],
        ['Wasserverbrauch der Rechenzentren', 'sehr unsicher, etwa 2–100 Liter, realistisch einige bis niedrige zweistellige Liter; Anbieter veröffentlichen dazu keine belastbaren Werte'],
        ['Erfahrener Entwickler allein', 'etwa 350–600 Stunden, also 9–15 Wochen in Vollzeit'],
        ['Laie ohne Unterstützung', 'etwa 1.000–2.000 Stunden, viele würden vorher aufgeben']]),
      h('p', { class: 'hint' }, 'Die Angaben zu Entwickler und Laie sind Erfahrungswerte für den gleichen Funktionsumfang (Mehrnutzerverwaltung, Anmeldung mit Zwei-Faktor-Schritt, Cronjobs, Mailversand mit SPF/DKIM, Benachrichtigungen, Admin-Bereich, Kalender-Abo, Web-App, Datenschutz und rund zehn Datenquellen). Aus Codezeilen lässt sich der Aufwand nur grob ableiten.'));
  }

  /* ---------- Nerdinfos (Projektübersicht) ---------- */
  function nerdOn() { return !!D.nerd && !S.preview; }
  function nerdPage() {
    var P = h('section', { class: 'panel infopanel nerdinfo' }, h('h2', { class: 'sec' }, 'Nerdinfos'),
      h('p', { class: 'hint' }, 'Wie Schondraussen.de gebaut ist, woher die Daten kommen und welche Dienste im Hintergrund laufen. Stand 08.11.2026.'));
    function sec(title) { var s = h('section', { class: 'infosec' }, h('h3', null, title)); for (var i = 1; i < arguments.length; i++) s.append(arguments[i]); P.append(s); }
    function ul(items) { return h('ul', { class: 'nerdul' }, items.map(function (x) { return h('li', null, x); })); }
    function cell(x) { return (x && x.href) ? h('a', { href: x.href, target: '_blank', rel: 'noopener noreferrer' }, x.t) : x; }
    function tbl(head, rows) {
      return h('div', { class: 'nerdtbl' }, h('table', null, h('thead', null, h('tr', null, head.map(function (x) { return h('th', null, x); }))),
        h('tbody', null, rows.map(function (r) { return h('tr', null, r.map(function (x, i) { return h('td', i === 0 ? { class: 'k' } : null, cell(x)); })); }))));
    }
    sec('Kurzüberblick', h('p', null, 'Schondraussen.de ist ein kostenloser Medien-Tracker: Nutzer legen Autor:innen, Musiker:innen, Filme und Serien auf eine Liste und werden per Mail und Kalender über Neuerscheinungen informiert. Die Seite ist am 08.11.2026 offiziell gestartet.'),
      ul(['Betreiber: Timo Lauer, Trier (Einzelprojekt; Einnahmen nur über Amazon-Partnerlinks)', 'Technik in einem Satz: PHP und MySQL auf ALL-INKL, Code in einem privaten GitHub-Repository, automatische Bereitstellung bei jedem Push', 'Stand der Kategorien: Bücher, Musik und Serien sind angebunden, Filme warten auf die Freigabe der Datenquelle (TMDB)']));
    sec('Funktionen für Nutzer', ul([
      'Konto mit E-Mail-Bestätigung, frei wählbarem Benutzernamen und optionaler Zwei-Faktor-Anmeldung; Konto jederzeit löschbar (endgültig nach 7 Tagen)',
      'Vier Listen (Bücher, Musik, Filme, Serien, je bis zu 100 Einträge) mit Werkübersicht, Beschreibung (deutsch, übersetzt) und Markierung als gesehen, gelesen oder gehört',
      'Kalender aller angekündigten Veröffentlichungen, dazu ein persönliches Kalender-Abo (ICS)',
      'Bücher auch als Reihe über den Titel (zum Beispiel Manga), Hinweis „neu hinzugefügt“ für Bücher der letzten 4 Wochen',
      'Benachrichtigungen per Mail: morgens bei Neuigkeiten, morgens plus Wochenübersicht oder nur die Wochenübersicht; mit Kurzinfo und bis zu zwei aktuellen Schlagzeilen je Titel, Abmeldelink in jeder Mail',
      'Vorschläge ähnlicher Künstler:innen, Autor:innen und Serien; Import aus Trakt und Spotify; Datenexport',
      'Ideen, Bugmeldungen und Kontakt-Chat mit dem Betreiber; öffentliches Kontaktformular ohne Konto',
      'Mehrere Oberflächen (Skins), Nutzung als App auf dem Handy, Systemnachrichten des Betreibers als Fenster']));
    sec('Funktionen für den Admin', ul([
      'Nutzerverwaltung (freischalten, sperren, Alphatester, Einladen per Link), Option „Neue Konten manuell freischalten“ (Standard aus)',
      'Systemnachrichten an alle Nutzer, Bilder der Anmeldeseite, Amazon-Partner-Daten, Testmail',
      'Sicherheit: Protokoll der Admin-Zugriffe, Ansicht als normaler Nutzer, Eintrag prüfen (Diagnose)',
      'Cron-Status, Versionsverlauf (Changelog) mit Kategorien, Meldung bei neuen Ideen und Bugs']));
    sec('Technik und Betrieb', tbl(['Bereich', 'Umsetzung'], [
      ['Hosting', 'ALL-INKL (Webspace, MySQL, Mailserver), HTTPS erzwungen'],
      ['Sprache und Datenbank', 'PHP und MySQL; Oberfläche in JavaScript ohne Framework'],
      ['Bereitstellung', 'Privates GitHub-Repository; jeder Push auf den Hauptzweig wird per GitHub Actions auf den Server übertragen (rund 1,5 Minuten)'],
      ['Aktualisierung der Daten', 'Ein geplanter Server-Job (Cron) stößt die Aktualisierung an; älteste Einträge zuerst, je Lauf etwa 80 Sekunden Zeitbudget, Einträge werden alle 12 Stunden erneuert; danach laufen die Mails'],
      ['Mailversand', 'SMTP über den Mailserver des Hosters, Double-Opt-in, Abmeldelink und List-Unsubscribe-Kopfzeile'],
      ['Versionsverlauf', 'Eine Sammelversion je Kalendertag, Kategorien F, O, U, B, A, D, T']]));
    sec('Externe Datenquellen und Schnittstellen', h('p', null, 'Alle Abfragen laufen serverseitig und nur für Titel, die Nutzer wirklich anlegen. An die Dienste gehen nie E-Mail-Adressen oder Kontodaten.'),
      tbl(['Quelle', 'Wofür', 'Schlüssel', 'Auflagen und Hinweise'], [
        [{ t: 'TVmaze', href: 'https://www.tvmaze.com' }, 'Serien: Staffeln, Folgen, Sendetermine, Beschreibung; auch Anime', 'keiner', 'Daten unter CC BY-SA 4.0, Quellenangabe im Footer und auf der Infoseite'],
        [{ t: 'MusicBrainz', href: 'https://musicbrainz.org' }, 'Musik: Künstler:innen, Alben, Termine', 'keiner', 'höchstens eine Anfrage pro Sekunde, daher gebremste Abfrage'],
        ['ListenBrainz', 'Musik: Vorschläge ähnlicher Künstler:innen', 'keiner', '–'],
        [{ t: 'Open Library', href: 'https://openlibrary.org' }, 'Bücher: Autor:innen und Werke, Wikidata-Verweis', 'keiner', 'liefert kaum angekündigte Bücher; Werke ohne deutsche Ausgabe werden ausgeblendet'],
        ['Google Books', 'Bücher: angekündigte Titel (deutsche Ausgaben)', 'API-Schlüssel', 'Eigener Schlüssel, begrenzte Abfragemenge pro Tag; für deutsche Manga kaum Treffer'],
        ['Deutsche Nationalbibliothek (SRU, ersatzweise lobid.org)', 'Bücher-Reihen über den Titel: nummerierte Bände mit Jahr, auch angekündigte', 'keiner', 'freie Schnittstelle; Antwortstruktur noch in Erprobung'],
        ['Wikipedia und Wikidata', 'Kurzvorstellung von Bands und Autor:innen', 'keiner', 'deutsch bevorzugt, sonst englisch und übersetzt'],
        [{ t: 'DeepL', href: 'https://www.deepl.com' }, 'Übersetzung englischer Beschreibungen ins Deutsche', 'API-Schlüssel (Free-Tarif)', 'nur öffentliche Texte, Ergebnis wird gespeichert (je Text eine Übersetzung)'],
        ['Google News (RSS-Suche)', 'aktuelle Schlagzeilen in den Benachrichtigungsmails (höchstens 7 Tage alt)', 'keiner', 'nur Name des Eintrags und Titel des Werks gehen an Google'],
        ['TMDB', 'Filme (geplant)', 'API-Schlüssel', 'offen: private oder kommerzielle Nutzung; erst danach wird die Anbindung gebaut'],
        ['Amazon-Partnerprogramm', 'Kauflinks mit Partner-ID', 'Partner-ID', 'Kennzeichnung als Werbung im Footer, im Impressum und im Datenschutz']]));
    sec('Dienste und Konten', tbl(['Dienst', 'Zweck'], [
      ['ALL-INKL (Hoster)', 'Webspace, Datenbank, Mailserver, Cron-Job, Fehler- und Zugriffslogs (7 Tage)'],
      ['GitHub (privates Repository, Actions)', 'Quellcode und automatische Bereitstellung bei jedem Push'],
      [{ t: 'UptimeRobot', href: 'https://stats.uptimerobot.com/hWI2Kjc0jq' }, 'Erreichbarkeitsprüfung; die öffentliche Statusseite ist auf der Infoseite verlinkt'],
      ['Google Search Console', 'Domain angemeldet, Sitemap bereitgestellt, später Auswertung der Suchanfragen'],
      ['Google Cloud (Projekt für die Books API)', 'API-Schlüssel für Google Books, auf diese API beschränkt'],
      ['DeepL-Konto (API Free)', 'Schlüssel für die Übersetzung'],
      ['Amazon-Partnerprogramm', 'Partner-ID für die Kauflinks']]),
      h('p', { class: 'hint' }, 'Schlüssel und Passwörter sind nur auf dem Server hinterlegt und hier bewusst nicht genannt.'));
    sec('Datenschutz, Sicherheit und Rechtliches', ul([
      'Impressum und Datenschutzerklärung (Stand 07.11.2026) sind online; sie nennen alle Quellen, DeepL, die Google-News-Schlagzeilen, Amazon-Partnerlinks, Aufbewahrungsfristen und die Rechte der Nutzer',
      'Es gibt nur ein Sitzungs-Cookie, keine Werbe- oder Analyse-Dienste, und die Seite lädt nur von der eigenen Domain (keine externen Schriften, Skripte oder CDNs)',
      'E-Mail-Adressen sieht der Admin in der Nutzerverwaltung nicht; ein Aufdecken im Ausnahmefall wird protokolliert',
      'Passwörter nur als Hash; optionale Zwei-Faktor-Anmeldung; Anmeldeversuche und Kontaktformular sind mengenbegrenzt',
      'Hochgeladene Bilder werden geprüft; Anhänge sind erst nach Freigabe für andere sichtbar',
      'Löschung: Konto sofort gesperrt, Daten nach 7 Tagen endgültig weg; Ideen und Bugmeldungen bleiben ohne E-Mail-Verknüpfung (Name durchgestrichen)',
      'Mails nur mit Einwilligung (Double-Opt-in), Widerruf per Abmeldelink in jeder Mail']));
    return P;
  }
  function info() {
    var d = document.createElement('section'); d.className = 'panel infopanel';
    d.innerHTML = '<h2 class="sec">Info</h2><div class="intro"><p class="lead">Du bist hier richtig, wenn du irgendwann angefangen hast zu arbeiten und einfach keine Zeit mehr hast, deinen Lieblingsmusiker:innen, Autor:innen oder Serien zu folgen. Wenn du überrascht feststellst, dass die Band deiner Jugend schon vor zwei Jahren ein neues Album veröffentlicht hat.</p>' +
      '<p>Schondraussen.de hilft dir dabei, ganz ohne viel Schnickschnack: Du legst fest, wem du folgst, und die Seite schickt dir eine Mail, sobald es etwas Neues gibt. So behältst du den Überblick und bestimmst selbst, was dich wirklich erreicht.</p>' +
      (D.approval ? '<p class="muted">Neue Konten werden vom Admin freigeschaltet.</p>' : '') + '</div>' +
      '<section class="infosec"><h3>Feedback</h3><p>Die Seite ist neu und wächst weiter. Die Daten kommen aus den Quellen unten und können Lücken haben; manchmal tauchen auch fremdsprachige Ergebnisse auf. Fehlt dir etwas oder stimmt eine Angabe nicht, schreib mir über „Kontakt“ oder reiche eine Idee oder Bugmeldung ein.</p></section>' +
      '<section class="infosec"><h3>Wann werden Einträge erneuert?</h3><p>Automatisch alle 12 Stunden. Jeder Titel wird dabei nur einmal für alle Nutzer abgefragt und zwischengespeichert. Neuerscheinungen tauchen dann ohne eigenes Zutun auf. Beim Hinzufügen eines Eintrags und über „Aktualisieren“ werden die Daten sofort geladen. Das Datum steht als „Stand“ am Eintrag.</p><p class="muted" id="lastrun"></p><p class="muted"><a href="https://stats.uptimerobot.com/hWI2Kjc0jq" target="_blank" rel="noopener">Systemstatus ansehen</a> (zeigt, ob die Seite erreichbar ist)</p></section>' +
      '<section class="infosec"><h3>Quellen je Kategorie</h3>' +
      '<div class="infocat"><h4>Bücher</h4><p><a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> liefert die Bücher. Angekündigte Bücher kennt diese Quelle kaum, dafür kommt später eine zweite Quelle, etwa Google Books.</p></div>' +
      '<div class="infocat"><h4>Musik</h4><p><a href="https://musicbrainz.org" target="_blank" rel="noopener noreferrer">MusicBrainz</a>, eine offene Musikdatenbank, für Alben, EPs und angekündigte Veröffentlichungen.</p></div>' +
      '<div class="infocat"><h4>Filme</h4><p>Die Datenquelle wird noch angebunden, vorgesehen ist <a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer">TMDB</a>. Einträge kannst du schon anlegen.</p></div>' +
      '<div class="infocat"><h4>Serien</h4><p><a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">TVmaze</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>) für Staffeln und Sendetermine der Folgen. Manga und Comics findest du unter Bücher, soweit Open Library und Google Books sie führen.</p></div>' +
      '<div class="infocat"><h4>Amazon-Links</h4><p>Suchlinks mit der Partner-ID des Betreibers (Programm: <a href="https://partnernet.amazon.de" target="_blank" rel="noopener noreferrer">Amazon PartnerNet</a>). Eine Provision gibt es nur bei qualifizierten Käufen.</p></div></section>';
    api('/api/data.php?a=lastrun').then(function (r) {
      var el = d.querySelector('#lastrun'); if (!el || r.minutes == null) return; var m = +r.minutes, t;
      if (m < 60) t = 'vor weniger als einer Stunde'; else if (m < 180) t = 'vor etwa ' + Math.round(m / 60) + ' Stunden'; else if (m < 1440) t = 'vor ' + Math.round(m / 60) + ' Stunden'; else t = 'vor über einem Tag';
      el.textContent = 'Zuletzt wurden Einträge aktualisiert: ' + t + '.';
    });
    var mine = h('section', { class: 'infosec' }, h('h3', null, 'Deine Statistik'), h('p', { class: 'hint' }, 'Lade …')); d.append(mine);
    api('/api/data.php?a=mystats').then(function (r) {
      mine.textContent = ''; mine.append(h('h3', null, 'Deine Statistik'));
      if (!r || r.error) { mine.append(h('p', { class: 'hint' }, 'Die Statistik ist gerade nicht verfügbar.')); return; }
      var n = function (v) { return Number(v || 0).toLocaleString('de-DE'); }, en = r.entries || {}, tot = (en.author | 0) + (en.artist | 0) + (en.film | 0) + (en.series | 0);
      var rows = [['Angemeldet seit', r.since ? new Date(String(r.since).replace(' ', 'T')).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '–'],
        ['Einträge insgesamt', n(tot) + ' (' + n(en.author) + ' Bücher, ' + n(en.artist) + ' Musik, ' + n(en.film) + ' Filme, ' + n(en.series) + ' Serien)'],
        ['Werke zu deinen Einträgen', n(r.works)], ['Eingereichte Ideen', n(r.ideas)], ['Eingereichte Bugs', n(r.bugs)], ['Gesendete Chatnachrichten', n(r.messages)]];
      if (r.top_percent) mine.append(h('p', { style: 'margin:6px 0 10px;font-weight:600' }, 'Du gehörst zu den ' + r.top_percent + ' % der aktivsten Nutzer.'), h('p', { class: 'hint', style: 'margin:-6px 0 10px' }, 'Gezählt werden Einträge, eingereichte Ideen und Bugs sowie gesendete Chatnachrichten, im Vergleich mit allen aktiven Nutzern.')); var dl = h('dl', { class: 'mystats' }); rows.forEach(function (x) { dl.append(h('dt', null, x[0]), h('dd', null, x[1])); }); mine.append(dl);
    });
    d.append(effortSection());
    if (!nerdOn()) { mainEl.append(d); return; }
    var holder = h('div', null), bar = h('div', { class: 'admtabs infotabs', role: 'tablist', 'aria-label': 'Info-Seiten' }), btns = [];
    function showTab(k) {
      S.infoTab = k; holder.textContent = ''; holder.append(k === 'nerd' ? nerdPage() : d);
      btns.forEach(function (b) { var on = b.k === k; b.el.className = 'admtab' + (on ? ' on' : ''); b.el.setAttribute('aria-selected', on ? 'true' : 'false'); });
    }
    [['info', 'Info'], ['nerd', 'Nerdinfos']].forEach(function (x) { var b = h('button', { class: 'admtab', type: 'button', role: 'tab', onclick: function () { showTab(x[0]); } }, x[1]); btns.push({ k: x[0], el: b }); bar.append(b); });
    mainEl.append(bar, holder); showTab(S.infoTab === 'nerd' ? 'nerd' : 'info');
  }
  function logView() {
    var staffLog = !!D.nerd && !S.preview, nNew = staffLog ? logBadge() : 0;
    markSeen(); renderVer();
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Versionsverlauf'), h('p', { class: 'hint' }, 'Was sich in welcher Version geändert hat.'));
    var CATS = [['F', 'Neue Funktionen'], ['O', 'Optionen'], ['U', 'Oberfläche'], ['B', 'Bugfixes'], ['A', 'Admin'], ['D', 'Datenschutz und Sicherheit'], ['T', 'Technik']];
    if (staffLog) { // Neue Einträge seit dem letzten Besuch: die neuesten stehen in der Liste jeweils oben
      var flat = []; CL.forEach(function (c) { if (c.base) return; c.t.forEach(function (x) { var m = /^([FOUBADT])\|/.exec(x); flat.push({ v: c.v, k: m ? m[1] : 'F', t: m ? x.slice(2) : x }); }); });
      var fresh = flat.slice(0, nNew), catName = {}; CATS.forEach(function (k) { catName[k[0]] = k[1]; });
      p.append(h('div', { class: 'lognew' }, h('h3', null, 'Änderungen seit deinem letzten Besuch dieser Seite'),
        fresh.length ? h('ul', null, fresh.map(function (x) { return h('li', null, h('b', null, catName[x.k] + ' (Version ' + x.v + '): '), x.t); })) : h('p', { class: 'hint', style: 'margin:0' }, 'Keine neuen Änderungen seit deinem letzten Besuch.')));
    }
    CL.forEach(function (c, ci) {
      var by = {}; c.t.forEach(function (x) { var m = /^([FOUBADT])\|/.exec(x), k = m ? m[1] : 'F'; (by[k] = by[k] || []).push(m ? x.slice(2) : x); });
      var hd = h('h3', null, 'Version ' + c.v, h('small', null, c.d)), old = ci >= 3, v = old ? h('details', { class: 'logv logold' }, h('summary', null, hd)) : h('div', { class: 'logv' }, hd);
      CATS.forEach(function (k) { if (by[k[0]]) v.append(h('h4', { class: 'logcat' }, k[1]), h('ul', null, by[k[0]].map(function (x) { return h('li', null, x); }))); });
      p.append(v);
    });
    mainEl.append(p);
  }

  /* ---------- Kontakt ---------- */
  /* ---------- Bilder (Chat, Ideen, Bugs) ---------- */
  function imgUrl(id) { return '/api/image.php?id=' + id; }
  function shrinkImage(file) { // verkleinert im Browser (spart Upload, entfernt EXIF), liefert JPEG-Blob
    return new Promise(function (res, rej) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { rej(new Error('Erlaubt sind JPG, PNG und WebP.')); return; }
      var u = URL.createObjectURL(file), im = new Image();
      im.onload = function () {
        var k = Math.min(1, 1600 / Math.max(im.naturalWidth, im.naturalHeight)), c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(im.naturalWidth * k)); c.height = Math.max(1, Math.round(im.naturalHeight * k));
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(im, 0, 0, c.width, c.height);
        URL.revokeObjectURL(u); c.toBlob(function (b) { b ? res(b) : rej(new Error('Das Bild konnte nicht verarbeitet werden.')); }, 'image/jpeg', 0.85);
      };
      im.onerror = function () { URL.revokeObjectURL(u); rej(new Error('Das Bild konnte nicht gelesen werden.')); };
      im.src = u;
    });
  }
  function uploadText(file) {
    if (file.size > 200 * 1024) return Promise.reject(new Error('Die Textdatei ist zu groß (höchstens 200 KB).'));
    return fetch('/api/image.php?a=upload&scope=feedback&kind=text&name=' + encodeURIComponent(file.name), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' }, body: file })
      .then(function (r) { return r.json().catch(function () { return { error: 'Unerwartete Antwort' }; }); })
      .then(function (r) { if (!r.ok) throw new Error(r.error || 'Hochladen nicht möglich.'); return { id: r.id, name: r.name || file.name }; });
  }
  function uploadImage(file, scope) {
    return shrinkImage(file).then(function (b) {
      return fetch('/api/image.php?a=upload&scope=' + scope, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'image/jpeg' }, body: b })
        .then(function (r) { return r.json().catch(function () { return { error: 'Unerwartete Antwort' }; }); })
        .then(function (r) { if (!r.ok) throw new Error(r.error || 'Hochladen nicht möglich.'); return { id: r.id, url: URL.createObjectURL(b) }; });
    });
  }
  function picLink(id, cls) { return h('a', { href: imgUrl(id), target: '_blank', rel: 'noopener', class: 'pic ' + (cls || '') }, h('img', { src: imgUrl(id), alt: 'Hochgeladenes Bild', loading: 'lazy' })); }

  function support() {
    if (S.preview) { mainEl.append(h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Kontakt'), h('p', { class: 'hint' }, 'Schreibe dem Administrator eine Nachricht. Nur der Administrator sieht sie, Antworten erscheinen hier.'), h('p', { class: 'warnbox' }, 'In der Vorschau kannst du hier nichts schreiben.'))); return; }
    var admin = D.admin, p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Kontakt'),
      h('p', { class: 'hint' }, admin ? 'Hier siehst du die Nachrichten deiner Nutzer und kannst antworten.' : 'Schreibe dem Administrator eine Nachricht. Nur der Administrator sieht sie, Antworten erscheinen hier.'));
    var grid = h('div', { class: 'supgrid' + (admin ? ' admin2' : '') }), threads = h('aside', { class: 'supthreads', hidden: !admin }), log = h('div', { class: 'chatlog', role: 'log', 'aria-live': 'polite' });
    var inp = h('textarea', { rows: '2', maxlength: '1000', 'aria-label': 'Nachricht', placeholder: 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)' }), msg = h('p', { class: 'msg', role: 'status' });
    var drafts = [], draftBox = h('div', { class: 'drafts' });
    var mailCk = h('input', { type: 'checkbox' }), mailRow = h('label', { class: 'mailck', hidden: !admin }, mailCk, ' Zusätzlich per E-Mail senden (mit Hinweis, dass man darauf nicht antworten kann)');
    var pick = h('select', { 'aria-label': 'Neue Unterhaltung mit einem Nutzer beginnen', class: 'userpick', onchange: function () { if (!pick.value) return; S.sup.sel = pick.value; pick.value = ''; drawThreads(); pull(); } }, h('option', { value: '' }, 'Nutzer anschreiben …'));
    if (admin) api('/api/chat.php?a=users').then(function (r) { (r.users || []).forEach(function (x) { pick.append(h('option', { value: String(x.id) }, (x.username || 'Mitglied') + ' · Nr. ' + x.id)); }); });
    var titleIn = h('input', { type: 'text', maxlength: '120', class: 'fbtitle', hidden: true, 'aria-label': 'Kurzbeschreibung', placeholder: 'Kurzbeschreibung (erscheint als Titel in der Liste)', autocomplete: 'off', onkeydown: function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); inp.focus(); } } });
    var attNote = h('p', { class: 'hint picnote' });
    function drawNote() { attNote.textContent = kindSel === 'chat' ? 'Bilder (JPG, PNG, WebP) bis zu 3 pro Nachricht. Zeige bitte keine fremden Personen oder privaten Daten.' : 'Anhänge: ' + drafts.length + ' von 3. Erlaubt sind Bilder (JPG, PNG, WebP) und Textdateien (.txt, .log, .md, .csv, .json, bis 200 KB). Zeige bitte keine fremden Personen oder privaten Daten.'; }
    function scopeOf() { return kindSel === 'chat' ? 'chat' : 'feedback'; }
    function drawDrafts() { draftBox.textContent = ''; drafts.forEach(function (d) { draftBox.append(h('span', { class: 'draft' + (d.name ? ' txt' : '') }, d.name ? h('span', { class: 'dname' }, '📄 ' + d.name) : h('img', { src: d.url, alt: 'Angehängtes Bild' }), h('button', { type: 'button', class: 'x', 'aria-label': 'Bild entfernen', onclick: function () { drafts = drafts.filter(function (e) { return e !== d; }); api('/api/image.php?a=remove', { id: d.id }); drawDrafts(); } }, '×'))); }); drawNote(); }
    function addFiles(files) {
      [].slice.call(files).forEach(function (f) {
        if (drafts.length >= 3) { msg.className = 'msg err'; msg.textContent = kindSel === 'chat' ? 'Höchstens 3 Bilder pro Nachricht.' : 'Höchstens 3 Anhänge.'; return; }
        var isTxt = !/^image\//.test(f.type) && (/^text\//.test(f.type) || /\.(txt|log|md|csv|json)$/i.test(f.name));
        if (isTxt && kindSel === 'chat') { msg.className = 'msg err'; msg.textContent = 'Textdateien gehen nur bei Ideen und Bugmeldungen.'; return; }
        msg.className = 'msg'; msg.textContent = isTxt ? 'Datei wird hochgeladen …' : 'Bild wird hochgeladen …';
        var sc = scopeOf();
        (isTxt ? uploadText(f) : uploadImage(f, sc)).then(function (d) { d.scope = sc; if (scopeOf() !== sc) { api('/api/image.php?a=remove', { id: d.id }); return; } drafts.push(d); msg.textContent = ''; drawDrafts(); })
          .catch(function (e) { msg.className = 'msg err'; msg.textContent = e.message; });
      });
    }
    function send() {
      var t = inp.value.trim(), ids = drafts.map(function (d) { return d.id; }); if (kindSel !== 'chat') { var ti = titleIn.value.trim(); if (ti.length < 3) { msg.className = 'msg err'; msg.textContent = 'Bitte gib eine Kurzbeschreibung an (mindestens 3 Zeichen).'; titleIn.focus(); return; } } else if (!t && !(kindSel === 'chat' && ids.length)) return; if (admin && kindSel === 'chat' && !S.sup.sel) { msg.textContent = 'Wähle erst eine Unterhaltung.'; return; }
      if (kindSel !== 'chat') { var kd = kindSel; api('/api/feedback.php?a=add', { kind: kd, title: titleIn.value.trim(), text: t, images: ids }).then(function (r) { if (r.ok) { inp.value = ''; titleIn.value = ''; drafts = []; drawDrafts(); msg.className = 'msg'; msg.textContent = ''; popup(kd === 'feature' ? 'Idee abgeschickt' : 'Bugmeldung abgeschickt', (D.admin ? (kd === 'feature' ? 'Gespeichert. Du findest sie in der Liste „Ideen“ und kannst sie dort freigeben.' : 'Gespeichert. Du findest sie in der Liste „Bugs“ und kannst sie dort freigeben.') : (kd === 'feature' ? 'Danke! Deine Idee ist angekommen. Sie erscheint in der Liste, sobald der Admin sie freigegeben hat.' : 'Danke! Deine Bugmeldung ist angekommen. Sie erscheint in der Liste, sobald der Admin sie freigegeben hat.')) + (ids.length && !D.alpha && !D.admin ? ' Dein Bild erscheint für andere, sobald es freigegeben ist.' : '')); } else { msg.className = 'msg err'; msg.textContent = r.error || 'Senden nicht möglich.'; } }); return; }
      inp.value = ''; drafts = []; drawDrafts(); var wantMail = admin && mailCk.checked; api('/api/chat.php?a=send', { text: t, uid: S.sup.sel, images: ids, mail: wantMail }).then(function (r) { msg.textContent = r.ok ? (wantMail ? (r.mailed ? 'Gesendet, auch per E-Mail zugestellt.' : 'Im Chat gesendet, aber die E-Mail konnte nicht verschickt werden.') : '') : (r.error || 'Senden nicht möglich.'); msg.className = 'msg' + (r.ok && (!wantMail || r.mailed) ? '' : ' err'); pull(); });
    }
    var kindSel = 'chat', kindBox = h('div', { class: 'kindsel', role: 'radiogroup', 'aria-label': 'Art der Nachricht' }), kindHint = h('p', { class: 'hint', style: 'margin:0' });
    var KINDS = [['chat', admin ? 'Antwort' : 'Chat', 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)', ''], ['feature', 'Idee', 'Details zu deiner Idee: Was genau schwebt dir vor, und wozu wäre es gut?', 'Der Admin schaut sich deine Idee an. Nach der Freigabe erscheint sie in der Liste „Ideen“ und alle können dafür abstimmen.'], ['bug', 'Bugmeldung', 'Details: Was funktioniert nicht? Wo, und was hast du gemacht?', 'Der Admin schaut sich die Meldung an. Nach der Freigabe erscheint sie in der Liste „Bugs“.']];
    function drawKinds() { kindBox.textContent = ''; KINDS.forEach(function (k) { kindBox.append(h('button', { class: 'kindbtn', type: 'button', role: 'radio', 'aria-checked': String(kindSel === k[0]), onclick: function () { var was = scopeOf(); kindSel = k[0]; if (scopeOf() !== was && drafts.length) { drafts.forEach(function (d) { api('/api/image.php?a=remove', { id: d.id }); }); drafts = []; drawDrafts(); msg.className = 'msg'; msg.textContent = 'Die angehängten Bilder wurden entfernt, weil du die Art gewechselt hast.'; } inp.placeholder = k[2]; kindHint.textContent = k[3]; inp.maxLength = k[0] === 'chat' ? 1000 : 2000; titleIn.hidden = k[0] === 'chat'; form.classList.toggle('fbmode', k[0] !== 'chat'); inp.rows = k[0] === 'chat' ? 2 : 6; fileIn.accept = k[0] === 'chat' ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,text/plain,.txt,.log,.md,.csv,.json'; mailRow.hidden = !admin || k[0] !== 'chat'; drawKinds(); drawDrafts(); } }, k[1])); }); }
    drawKinds(); drawNote();
    var fileIn = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: true, hidden: true, onchange: function () { addFiles(fileIn.files); fileIn.value = ''; } });
    var form = h('form', { class: 'chatform', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); send(); } }, titleIn, inp, fileIn,
      h('button', { class: 'btn ghost attach', type: 'button', title: 'Bild oder Textdatei anhängen (Bilder auch per Strg+V einfügbar)', 'aria-label': 'Datei anhängen', onclick: function () { fileIn.click(); } }, ico(ICONS.clip)), h('button', { class: 'btn', type: 'submit' }, 'Senden'));
    inp.addEventListener('paste', function (ev) { var fs = [].filter.call((ev.clipboardData && ev.clipboardData.files) || [], function (f) { return /^image\//.test(f.type); }); if (fs.length) { ev.preventDefault(); addFiles(fs); } });
    inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && !ev.shiftKey && (admin || kindSel === 'chat')) { ev.preventDefault(); send(); } });
    function fmtChat(t) { try { var d = new Date(t.replace(' ', 'T')), now = new Date(), hm = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }); return d.toDateString() === now.toDateString() ? hm : LONG.format(d).slice(0, 6) + ' ' + hm; } catch (_) { return ''; } }
    function drawThreads() {
      threads.textContent = ''; threads.append(pick); if (!S.sup.threads.length) threads.append(h('p', { class: 'hint' }, 'Noch keine Nachrichten.'));
      S.sup.threads.forEach(function (t) { threads.append(h('button', { class: 'thr', type: 'button', 'aria-current': String(S.sup.sel == t.id), onclick: function () { S.sup.sel = t.id; pull(); } }, h('span', { class: 't1' }, h('span', null, t.username || ('Mitglied Nr. ' + t.id)), +t.unread > 0 ? h('span', { class: 'badge' }, String(t.unread)) : null), h('span', { class: 't2' }, (+t.last_admin ? 'Du: ' : '') + (t.last_body || '')))); });
    }
    function pull() {
      var go2 = function () {
        if (admin && !S.sup.sel) { log.textContent = ''; log.append(h('p', { class: 'chatempty' }, 'Wähle links eine Unterhaltung.')); return; }
        api('/api/chat.php?a=thread' + (admin ? '&uid=' + S.sup.sel : '')).then(function (r) {
          var atEnd = log.scrollHeight - log.scrollTop - log.clientHeight < 40 || !log.childNodes.length; log.textContent = '';
          if (admin) mailCk.checked = !(r.messages || []).length;
          if (!(r.messages || []).length) log.append(h('p', { class: 'chatempty' }, h('b', null, 'Noch keine Nachrichten.'), h('br'), admin ? 'Hier ist noch nichts geschrieben worden. Deine Nachricht erscheint auch im Chat des Nutzers.' : 'Schreibe unten deine erste Nachricht. Antworten des Administrators erscheinen hier.'));
          (r.messages || []).forEach(function (m) { var mine = admin ? +m.from_admin === 1 : +m.from_admin === 0;
            log.append(h('div', { class: 'bub ' + (mine ? 'me' : 'them') }, m.body, (m.images || []).map(function (id) { return picLink(id, 'inchat'); }), h('small', null, fmtChat(m.created_at), mine ? h('span', { class: 'ticks' + (m.read_at ? ' seen' : ''), title: m.read_at ? 'Gelesen' : 'Gesendet' }, m.read_at ? '✓✓' : '✓') : null))); });
          if (atEnd) log.scrollTop = log.scrollHeight; unreadPoll();
        });
      };
      if (admin) api('/api/chat.php?a=threads').then(function (r) { S.sup.threads = r.threads || []; if (!S.sup.sel && S.sup.threads.length) S.sup.sel = S.sup.threads[0].id; drawThreads(); go2(); }); else go2();
    }
    grid.append(threads, h('div', { class: 'chat' }, setCard(admin ? 'Unterhaltung' : 'Unterhaltung mit dem Administrator', [log]), setCard(admin ? 'Antworten' : 'Neue Nachricht', [kindBox, kindHint, form, admin ? mailRow : null, draftBox, attNote, msg], 'composer'))); p.append(grid); mainEl.append(p); pull(); supTimer = setInterval(pull, 15000);
  }
  function feedbackView(kind) {
    var bug = kind === 'bug', staff = !S.preview && !!(D.admin || D.alpha), p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, bug ? 'Bugs' : 'Ideen'),
      h('p', { class: 'hint' }, staff ? (bug ? 'Meldungen von Nutzern. Neue Meldungen stehen unten, bis du sie freigibst; freigegebene sehen alle. Mit den Knöpfen legst du fest, wie dringend sie sind.' : 'Ideen von allen Nutzern. Neue Ideen stehen unten, bis du sie freigibst; freigegebene sehen alle und können dafür abstimmen.') : (bug ? 'Bekannte Fehler und ihr Stand. Neue Fehler meldest du unter „Kontakt“ (Auswahl „Bugmeldung“).' : 'Ideen von allen Nutzern, sortiert nach Stimmen. Mit dem Daumen stimmst du für eine Idee, ein zweiter Klick nimmt die Stimme zurück. Neue Ideen schreibst du unter „Kontakt“ (Auswahl „Idee“).'))),
      box = h('div', { class: 'fblist' }); p.append(box); mainEl.append(alphaWrap(bug ? 'bugs' : 'feat', p)); box.append(h('p', { class: 'state pulse' }, 'Lade …'));
    var PRIO = { urgent: ['Dringend', 'urgent'], normal: ['Normal', 'normal'], low: ['Unwichtig', 'low'] };
    function when(t) { try { return LONG.format(new Date(String(t).replace(' ', 'T'))); } catch (_) { return ''; } }
    function draw(r) {
      box.textContent = ''; var l = r.items || [];
      if (r.error) { box.append(h('p', { class: 'warnbox' }, r.error)); return; }
      if (!l.length) { box.append(h('p', { class: 'hint' }, bug ? 'Noch keine Bugmeldungen.' : 'Noch keine Ideen. Schreib den ersten unter „Kontakt“.')); return; }
      var pendHead = false;
      l.forEach(function (x) {
        var pics = h('div', { class: 'pics' });
        (x.images || []).forEach(function (im) {
          var pend = im.status === 'pending', priv = D.admin || D.alpha, isT = im.kind === 'text';
          if (im.removed_by) { pics.append(h('p', { class: 'hint fbremoved' }, (isT ? 'Datei' : 'Bild') + ' wurde von ' + im.removed_by + ' entfernt')); return; }
          var rmBtn = staff ? h('button', { class: 'btn ghost small', type: 'button', title: (isT ? 'Datei' : 'Bild') + ' aus dem Beitrag entfernen', onclick: function () { if (confirm((isT ? 'Datei' : 'Bild') + ' wirklich entfernen? Im Beitrag bleibt ein Hinweis stehen, dass sie entfernt wurde.')) api('/api/image.php?a=strike', { id: im.id }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, 'Entfernen') : null;
          if (isT) {
            var tl = h('span', { class: 'txtatt' }, h('a', { href: imgUrl(im.id), target: '_blank', rel: 'noopener' }, '📄 ' + (im.name || 'Textdatei')));
            if (pend && priv) tl.append(' ', h('span', { class: 'hint' }, 'wartet auf Freigabe'), ' ', h('button', { class: 'btn small', type: 'button', onclick: function () { api('/api/image.php?a=approve', { id: im.id }).then(function () { reload(); }); } }, 'Freigeben'), ' ', h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Datei ablehnen und löschen?')) api('/api/image.php?a=reject', { id: im.id }).then(function () { reload(); }); } }, 'Ablehnen'));
            else if (pend) tl.append(' ', h('span', { class: 'hint' }, 'wartet auf Freigabe, nur für dich sichtbar'));
            if (rmBtn) tl.append(' ', rmBtn);
            pics.append(tl); return;
          }
          var a1 = picLink(im.id, pend && priv ? 'blur' : '');
          var fig = h('figure', { class: 'fbpic' }, a1);
          if (pend && priv) fig.append(h('figcaption', null, h('span', null, 'Wartet auf Freigabe'),
            h('button', { class: 'btn ghost small', type: 'button', onclick: function () { a1.classList.toggle('blur'); } }, 'Anzeigen'),
            h('button', { class: 'btn small', type: 'button', onclick: function () { api('/api/image.php?a=approve', { id: im.id }).then(function () { reload(); }); } }, 'Freigeben'),
            h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Bild ablehnen und löschen?')) api('/api/image.php?a=reject', { id: im.id }).then(function () { reload(); }); } }, 'Ablehnen')));
          else if (pend) fig.append(h('figcaption', null, h('span', null, 'Wartet auf Freigabe, nur für dich sichtbar')));
          if (rmBtn) fig.append(h('div', { class: 'fbrm' }, rmBtn));
          pics.append(fig);
        });
        if (+x.pending_hidden) pics.append(h('p', { class: 'hint', style: 'margin:0' }, pl(x.pending_hidden, 'Bild wartet', 'Bilder warten') + ' auf Freigabe.'));
        var liveImgs = (x.images || []).filter(function (im) { return !im.removed_by; }), nImg = liveImgs.filter(function (im) { return im.kind !== 'text'; }).length, nTxt = liveImgs.length - nImg, nAtt = nImg + nTxt;
        var attInfo = nAtt ? h('p', { class: 'hint', style: 'margin:0 0 8px' }, 'Anhänge: ' + [nImg ? pl(nImg, 'Bild', 'Bilder') : '', nTxt ? pl(nTxt, 'Textdatei', 'Textdateien') : ''].filter(Boolean).join(', ')) : null;
        var body = h('div', { class: 'fbbody', hidden: true }, x.body ? h('div', { class: 'fbdetail' }, editedSpan(x, 'body', x.body)) : null, attInfo, pics, h('p', { class: 'hint', style: 'margin:0' }, when(x.created_at) + (x.author && !x.gone ? ' · von ' + x.author : '') + (x.mine ? ' · von dir' : ''), x.gone ? [' · von ', h('s', { title: 'Das Konto wurde gelöscht' }, x.author), ' (Konto gelöscht)'] : null, x.author_tier === 'admin' ? [' ', h('span', { class: 'tier' }, 'Admin')] : x.author_tier === 'alpha' ? [' ', h('span', { class: 'tier alpha' }, 'Alphatester')] : null, bug && x.done_at ? ' · erledigt am ' + when(x.done_at) : null));
        var editBtn = null;
        if (x.mine && !x.gone && !x.done_at && !+x.rejected) {
          var eT = h('input', { type: 'text', maxlength: '120', value: x.title, 'aria-label': 'Kurzbeschreibung', style: 'width:100%' }), eB = h('textarea', { rows: '6', maxlength: '2000', 'aria-label': 'Details', style: 'width:100%;margin-top:8px' }), eM = h('span', { class: 'msg', style: 'margin:0' }), eBox = h('div', { class: 'fbedit', hidden: true, style: 'margin:10px 0' });
          eB.value = x.body || '';
          eBox.append(h('p', { class: 'hint', style: 'margin:0 0 6px' }, 'Andere Nutzer sehen, dass der Text bearbeitet wurde, und können die frühere Fassung per Maus-Hinweis ansehen.'), eT, eB,
            h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' }, h('button', { class: 'btn small', type: 'button', onclick: function () { eM.className = 'msg'; eM.textContent = 'Speichere …'; api('/api/feedback.php?a=edit', { id: x.id, title: eT.value, text: eB.value }).then(function (r2) { if (r2.ok) reload(); else { eM.className = 'msg err'; eM.textContent = r2.error || 'Das hat nicht geklappt.'; } }); } }, 'Speichern'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { eBox.hidden = true; } }, 'Abbrechen'), eM));
          body.append(eBox);
          editBtn = h('button', { class: 'btn ghost small', type: 'button', title: 'Meinen Beitrag bearbeiten', onclick: function () { body.hidden = false; eBox.hidden = !eBox.hidden; } }, 'Bearbeiten');
        }
        var head = h('button', { class: 'fbhead', type: 'button', 'aria-expanded': 'false', onclick: function () { body.hidden = !body.hidden; head.setAttribute('aria-expanded', String(!body.hidden)); } }, h('span', { class: 'fbt' }, bug && x.bug_no ? h('span', { class: 'bugno' }, '#' + x.bug_no + ' ') : null, editedSpan(x, 'title', x.title), nAtt ? h('span', { class: 'attchip', title: pl(nAtt, 'Anhang', 'Anhänge') }, '📎 ' + nAtt) : null), bug ? h('span', { class: 'prio ' + x.priority }, PRIO[x.priority][0]) : (+x.rejected ? h('span', { class: 'prio low' }, 'abgelehnt') : null));
        var acts = h('div', { class: 'fbacts' });
        if (editBtn) acts.append(editBtn);
        var voteBtn = null;
        var dbtn = h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': String(!!x.done_at), title: x.done_at ? 'Als offen markieren' : 'Als erledigt markieren', onclick: function () { api('/api/feedback.php?a=done', { id: x.id, on: !x.done_at }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, +x.rejected ? 'Abgelehnt' : 'Erledigt');
        if (bug && staff) acts.append(dbtn);
        if (bug) { if (staff) ['urgent', 'normal', 'low'].forEach(function (k) { acts.append(h('button', { class: 'btn ghost small prio-' + k, type: 'button', 'aria-pressed': String(x.priority === k), onclick: function () { api('/api/feedback.php?a=priority', { id: x.id, priority: k }).then(function () { reload(); }); } }, PRIO[k][0])); }); }
        else {
          var on = +x.mine_vote > 0;
          if (x.released_at || staff) voteBtn = (h('button', { class: 'vote' + (on ? ' on' : ''), type: 'button', title: on ? 'Stimme zurücknehmen' : 'Dafür stimmen', 'aria-pressed': String(on), onclick: function () { api('/api/feedback.php?a=vote', { id: x.id, on: !on }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, ico(ICONS.thumb), h('b', null, String(x.votes))));
          if (staff) acts.append(dbtn);
          if (D.admin && !S.preview) acts.append(h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': String(!!+x.rejected), onclick: function () { api('/api/feedback.php?a=reject', { id: x.id, on: !+x.rejected }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, +x.rejected ? 'Ablehnung zurücknehmen' : 'Ablehnen'));
        }
        if (staff) {
          var rg2 = h('span', { class: 'fbright' }, h('button', { class: 'btn small' + (x.released_at ? ' ghost' : ''), type: 'button', title: x.released_at ? 'Beitrag wieder ausblenden' : 'Beitrag für alle sichtbar machen', onclick: function () { api('/api/feedback.php?a=release', { id: x.id, on: !x.released_at }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, x.released_at ? 'Freigabe zurücknehmen' : 'Freigeben'));
          if (D.admin) rg2.append(h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Wirklich löschen?')) api('/api/feedback.php?a=delete', { id: x.id }).then(function () { reload(); }); } }, 'Löschen'));
          acts.append(rg2);
        }
        var row = h('div', { class: 'fbrow' + (bug ? ' bugrow p-' + x.priority : '') + (x.done_at ? ' done' : '') + (!x.released_at ? ' unreleased' : '') }, h('div', { class: 'fbmain' }, voteBtn ? h('div', { class: 'fbline' }, voteBtn, head) : head, body, acts.childNodes.length ? acts : null));
        if (!x.released_at && !pendHead) { pendHead = true; box.append(h('h3', { class: 'fbdiv' }, staff ? 'Noch nicht freigegeben (nur für Admin und Alphatester sichtbar)' : 'Deine Beiträge, die noch auf Freigabe warten')); }
        box.append(row);
      });
    }
    function reload() { api('/api/feedback.php?a=list&kind=' + kind).then(draw); }
    reload();
  }
  function editedSpan(x, field, text) {
    var ed = (x.edits || []).filter(function (e) { return e.field === field; });
    if (!ed.length) return text;
    var tip = 'Bearbeitet. Frühere Fassung' + (ed.length > 1 ? 'en' : '') + ':\n' + ed.map(function (e) { var d = new Date(String(e.at).replace(' ', 'T')); return '„' + (e.old.length > 400 ? e.old.slice(0, 400) + '…' : (e.old || '(leer)')) + '“ (bis ' + d.toLocaleDateString('de-DE') + ', ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) + ' Uhr)'; }).join('\n');
    return h('span', { class: 'edited', title: tip }, text, h('sup', { class: 'edmark', 'aria-label': 'bearbeitet' }, ' ✎'));
  }
  function unreadPoll() { api('/api/chat.php?a=unread').then(function (r) { var n = +r.unread || 0; if (n !== S.unread) { S.unread = n; var k = topbar.querySelector('.support'); if (k) { var b = k.querySelector('.badge'); if (b) b.remove(); if (n && S.view !== 'sup') k.append(h('span', { class: 'badge' }, n > 99 ? '99+' : String(n))); } } }); }

  /* ---------- Admin ---------- */
  function admin() {
    var admBadge = null;
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Admin')), amz = h('div', { class: 'cfgpanel' }), msg = h('span', { class: 'msg', style: 'margin:0' });
    var tag = h('input', { type: 'text', maxlength: '64', placeholder: 'deinname-21', spellcheck: 'false', autocapitalize: 'off', value: S.site.tag || '' }), dom = h('select', null, ['amazon.de', 'amazon.fr', 'amazon.it', 'amazon.es', 'amazon.co.uk', 'amazon.com'].map(function (d) { return h('option', { value: d }, d); })); dom.value = S.site.domain || 'amazon.de';
    amz.append(h('label', { class: 'field' }, h('span', null, 'Amazon-Partner-ID (gilt für alle Nutzer)'), tag), 
      h('p', { class: 'hint wide2' }, 'Die Partner-ID wird in alle Amazon-Links aller Nutzer eingebaut. Die Links gehen an amazon.de, du brauchst dafür eine ID aus dem deutschen PartnerNet.'),
      h('div', { class: 'add' }, h('button', { class: 'btn', type: 'button', onclick: function () { api('/api/admin.php?a=savesite', { tag: tag.value.trim(), domain: S.site.domain || 'amazon.de' }).then(function (r) { msg.className = 'msg' + (r.ok ? '' : ' err'); msg.textContent = r.ok ? 'Gespeichert.' : (r.error || 'Speichern nicht möglich.'); if (r.ok) S.site = { tag: tag.value.trim(), domain: dom.value }; }); } }, 'Speichern'), msg));
    var gal = h('div', { class: 'cfgpanel' }), galList = h('div', { class: 'galadm' }), galMsg = h('p', { class: 'msg', role: 'status' });
    function galDraw(items) {
      galList.textContent = '';
      if (!items.length) galList.append(h('p', { class: 'hint' }, 'Noch keine Bilder. Ohne Bilder zeigt die Anmeldeseite die mitgelieferten Beispiele.'));
      items.forEach(function (x, i) {
        var ti = h('input', { type: 'text', value: x.title || '', maxlength: '120', placeholder: 'Beschreibung', 'aria-label': 'Beschreibung des Bildes ' + (i + 1) });
        function move(d) { var o = items.map(function (e) { return e.id; }), t = o[i]; o[i] = o[i + d]; o[i + d] = t; api('/api/shots.php?a=order', { ids: o }).then(galLoad); }
        galList.append(h('div', { class: 'galrow' }, h('img', { src: '/api/shot.php?id=' + x.id + '&v=' + x.v, alt: '' }), ti,
          h('div', { class: 'galbtns' },
            h('button', { class: 'btn ghost small', type: 'button', disabled: i === 0, 'aria-label': 'Nach vorn', title: 'Nach vorn', onclick: function () { move(-1); } }, '↑'),
            h('button', { class: 'btn ghost small', type: 'button', disabled: i === items.length - 1, 'aria-label': 'Nach hinten', title: 'Nach hinten', onclick: function () { move(1); } }, '↓'),
            h('button', { class: 'btn small', type: 'button', onclick: function () { api('/api/shots.php?a=title', { id: x.id, title: ti.value }).then(function (r) { galMsg.className = 'msg' + (r.ok ? '' : ' err'); galMsg.textContent = r.ok ? 'Beschreibung gespeichert.' : (r.error || 'Das hat nicht geklappt.'); }); } }, 'Speichern'),
            h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Dieses Bild von der Anmeldeseite entfernen?')) api('/api/shots.php?a=delete', { id: x.id }).then(galLoad); } }, 'Löschen'))));
      });
    }
    function galLoad() { api('/api/shots.php?a=list').then(function (r) { if (r.error) { galList.textContent = ''; galList.append(h('p', { class: 'warnbox' }, r.error)); return; } galDraw(r.items || []); }); }
    var galFile = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: true, hidden: true, onchange: function () {
      var fs = [].slice.call(galFile.files); galFile.value = ''; galMsg.className = 'msg'; galMsg.textContent = 'Wird hochgeladen …';
      fs.reduce(function (pr, f) { return pr.then(function () { return shrinkImage(f).then(function (b) { return fetch('/api/shots.php?a=upload', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'image/jpeg' }, body: b }).then(function (r) { return r.json(); }).then(function (r) { if (!r.ok) throw new Error(r.error || 'Hochladen nicht möglich.'); }); }); }); }, Promise.resolve())
        .then(function () { galMsg.textContent = 'Hochgeladen. Vergib noch eine Beschreibung.'; galLoad(); }).catch(function (e) { galMsg.className = 'msg err'; galMsg.textContent = e.message; galLoad(); });
    } });
    gal.append(h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Diese Bilder erscheinen unter der Anmeldung als Vorschau und lassen sich dort durchblättern. Zeige keine echten E-Mail-Adressen oder privaten Daten. Mit den Pfeilen änderst du die Reihenfolge, bis zu 12 Bilder sind möglich.'), galList,
      h('p', { style: 'margin:10px 0 0' }, h('button', { class: 'btn', type: 'button', onclick: function () { galFile.click(); } }, 'Bild hinzufügen'), galFile), galMsg));
    galLoad();
    var invMail = h('input', { type: 'email', maxlength: '120', placeholder: 'E-Mail-Adresse (optional)', 'aria-label': 'E-Mail-Adresse der eingeladenen Person', autocomplete: 'off', style: 'flex:1;min-width:200px' }), invMsg = h('p', { class: 'msg', role: 'status' }), invOut = h('div'), invList = h('div', { class: 'adm' });
    function invLoad() {
      api('/api/admin.php?a=invite_list').then(function (r) {
        invList.textContent = ''; var l = r.invites || [];
        if (!l.length) return;
        invList.append(h('p', { class: 'hint', style: 'margin:12px 0 4px' }, 'Offene Einladungen (der Link wird nur beim Erstellen angezeigt):'));
        l.forEach(function (x) { invList.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' }, x.email || 'Link ohne E-Mail-Adresse'), h('div', { class: 'hint', style: 'margin:0' }, 'Gültig bis ' + new Date(String(x.expires_at).replace(' ', 'T')).toLocaleDateString('de-DE'))), h('div', { class: 'acts' }, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/admin.php?a=invite_revoke', { id: x.id }).then(invLoad); } }, 'Widerrufen')))); });
      });
    }
    var invBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Erzeuge einen Einladungslink für eine Person. Er ist nur einmal verwendbar, 14 Tage gültig und schaltet das neue Konto sofort frei. Mit E-Mail-Adresse schickt die Seite die Einladung direkt per Mail (die Adresse wird nach der Registrierung oder dem Ablauf wieder gelöscht); ohne Adresse bekommst du nur den Link zum Weitergeben. Für die nächste Person erzeugst du einen neuen Link.'),
      h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); invMsg.className = 'msg'; invMsg.textContent = '…'; invOut.textContent = '';
        api('/api/admin.php?a=invite_create', { email: invMail.value }).then(function (r) {
          if (!r.ok) { invMsg.className = 'msg err'; invMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; }
          invMsg.textContent = r.email ? (r.sent ? 'Einladung per Mail verschickt. Den Link kannst du zusätzlich selbst weitergeben:' : 'Die Mail konnte nicht verschickt werden. Gib den Link selbst weiter:') : 'Einladungslink erstellt:'; invMail.value = '';
          var f = h('input', { type: 'text', readonly: true, value: r.link, 'aria-label': 'Einladungslink', style: 'flex:1;min-width:220px' });
          invOut.append(h('div', { class: 'inline' }, f, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { f.select(); try { navigator.clipboard.writeText(r.link); } catch (_) { document.execCommand('copy'); } this.textContent = 'Kopiert'; } }, 'Kopieren')));
          invLoad();
        });
      } }, invMail, h('button', { class: 'btn', type: 'submit' }, 'Einladung erstellen')), invMsg, invOut, invList);
    invLoad();
    var tmMail = h('input', { type: 'email', maxlength: '120', placeholder: 'Adresse, z. B. von mail-tester.com', 'aria-label': 'Empfängeradresse der Testmail', autocomplete: 'off', style: 'flex:1;min-width:220px' }), tmMsg = h('p', { class: 'msg', role: 'status' });
    var apMsg = h('div', { class: 'msg', role: 'status' }), apCb = h('input', { type: 'checkbox', id: 'apReq' }), apInfo = h('p', { class: 'hint' });
    function apLoad() { api('/api/admin.php?a=approval').then(function (r) { apCb.checked = !!r.required; apInfo.textContent = r.pending ? (r.pending + (r.pending === 1 ? ' Konto wartet' : ' Konten warten') + ' auf Freischaltung (Nutzerverwaltung).') : 'Zurzeit wartet kein Konto auf Freischaltung.'; }); }
    apCb.addEventListener('change', function () { apMsg.className = 'msg'; apMsg.textContent = '…'; api('/api/admin.php?a=setapproval', { required: apCb.checked }).then(function (r) { if (!r.ok) { apMsg.className = 'msg err'; apMsg.textContent = r.error || 'Das hat nicht geklappt.'; apCb.checked = !apCb.checked; return; } D.approval = !!r.required; apMsg.textContent = r.required ? 'Gespeichert: Neue Konten müssen von dir freigeschaltet werden.' : 'Gespeichert: Neue Konten sind nach der Bestätigung der E-Mail-Adresse sofort freigeschaltet.'; apLoad(); }); });
    apLoad();
    var apBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Aus (Standard): Wer sich registriert und die E-Mail-Adresse bestätigt, kann sofort loslegen. An: Neue Konten sehen erst Inhalte, wenn du sie freischaltest, und du bekommst bei jeder Registrierung eine Mail mit Freischalt-Link. Bereits wartende oder gesperrte Konten bleiben beim Umschalten unverändert; gesperrt bleibt, wer von dir gesperrt wurde.'), h('label', { for: 'apReq', style: 'display:flex;gap:8px;align-items:center;margin:0' }, apCb, 'Neue Konten manuell freischalten'), apMsg, apInfo);
    var tmBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Schickt eine Mail im Format der echten Benachrichtigungen (mit Abmeldelink und List-Unsubscribe-Kopfzeile, der Link ist ein Platzhalter). Damit kannst du die Zustellbarkeit prüfen, zum Beispiel mit der Testadresse von mail-tester.com.'),
      h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); tmMsg.className = 'msg'; tmMsg.textContent = 'Sende …'; api('/api/admin.php?a=testmail', { email: tmMail.value }).then(function (r) { if (r.ok) tmMsg.textContent = 'Testmail wurde gesendet.'; else { tmMsg.className = 'msg err'; tmMsg.textContent = r.error || 'Das hat nicht geklappt.'; } }); } }, tmMail, h('button', { class: 'btn', type: 'submit' }, 'Testmail senden')), tmMsg);
    var ecIn = h('input', { type: 'text', maxlength: '120', placeholder: 'Name des Eintrags, z. B. Apothecary', 'aria-label': 'Name des Eintrags', autocomplete: 'off' }), ecOut = h('pre', { style: 'white-space:pre-wrap;word-break:break-word;font-size:13px;margin:10px 0 0' }), ecBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Zeigt, wie ein Eintrag in der Datenbank gespeichert ist und wem er auf der Liste steht (nur Nutzernummern, keine Adressen). Zur Fehlersuche.'),
      h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); ecOut.textContent = 'Suche …'; api('/api/admin.php?a=entry_check&q=' + encodeURIComponent(ecIn.value)).then(function (r) { if (r.error) { ecOut.textContent = r.error; return; } var t = (r.subjects || []).map(function (s) { return '#' + s.id + ' · ' + s.type + ' · „' + s.name + '“ · Stand ' + (s.fetched_at || 'nie') + ' · Termine ' + s.releases + ' · Werke ' + s.works + ' · Text ' + (s.sum_len || 0) + ' Zeichen\n   Kennung: ' + (s.external_ids || '–') + '\n   Listenabfrage: ' + ((s.list_check || []).join(' | ') || '–') + '\n   Auf der Liste von: ' + ((s.owners || []).map(function (o) { return 'Nr. ' + o.user_id + (+o.is_sample ? ' (Beispiel)' : ''); }).join(', ') || 'niemandem'); }); ecOut.textContent = t.length ? t.join('\n\n') : 'Nichts gefunden.'; }); } }, ecIn, h('button', { class: 'btn', type: 'submit' }, 'Prüfen')), ecOut);
    var nwT = h('input', { type: 'text', maxlength: '120', placeholder: 'Überschrift', 'aria-label': 'Überschrift der Systemnachricht', autocomplete: 'off' }), nwB = h('textarea', { rows: '4', maxlength: '2000', placeholder: 'Haupttext', 'aria-label': 'Text der Systemnachricht' }), nwMsg = h('p', { class: 'msg', role: 'status' }), nwList = h('div', { style: 'margin-top:10px;display:grid;gap:8px' });
    function nwLoad() { api('/api/admin.php?a=news_list').then(function (r) { nwList.textContent = ''; if (r.needs_migration) { nwList.append(h('p', { class: 'hint' }, 'Bitte zuerst Migration 040 einspielen.')); return; } if (!(r.news || []).length) nwList.append(h('p', { class: 'hint' }, 'Noch keine Systemnachrichten.')); (r.news || []).forEach(function (n) { nwList.append(h('div', { class: 'cfgpanel', style: 'margin:0' }, h('b', null, n.title), h('div', { class: 'hint', style: 'margin:2px 0 4px' }, String(n.created_at).slice(0, 16).replace('T', ' ') + ' · von ' + n.reads_n + ' Nutzern weggeklickt'), h('div', { style: 'white-space:pre-wrap' }, n.body), h('button', { class: 'btn ghost small', type: 'button', style: 'margin-top:8px', onclick: function () { if (!confirm('Nachricht löschen?')) return; api('/api/admin.php?a=news_delete', { id: n.id }).then(nwLoad); } }, 'Löschen'))); }); }); }
    var nwBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Die Nachricht erscheint allen Nutzern beim nächsten Seitenbesuch als Fenster, das sie mit dem X schließen. Neue Konten sehen nur Nachrichten, die nach ihrer Anmeldung erschienen sind.'),
      h('form', { autocomplete: 'off', style: 'display:grid;gap:8px', onsubmit: function (ev) { ev.preventDefault(); nwMsg.className = 'msg'; nwMsg.textContent = 'Veröffentliche …'; api('/api/admin.php?a=news_create', { title: nwT.value, body: nwB.value }).then(function (r) { if (r.ok) { nwT.value = ''; nwB.value = ''; nwMsg.textContent = 'Veröffentlicht.'; nwLoad(); } else { nwMsg.className = 'msg err'; nwMsg.textContent = r.error || 'Das hat nicht geklappt.'; } }); } }, nwT, nwB, h('div', null, h('button', { class: 'btn', type: 'submit' }, 'Veröffentlichen'))), nwMsg, nwList);
    nwLoad();
    var cs = cronSection();
    p.append(amz, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Bilder der Anmeldeseite'), gal, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Registrierung'), apBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Einladen'), invBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Systemnachrichten'), nwBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Eintrag prüfen'), ecBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Aktualisierung der Einträge'), cs[0], cs[1], h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Testmail'), tmBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Nutzerverwaltung'), h('p', { class: 'hint' }, 'Wartende Konten (falls die manuelle Freischaltung an ist) siehst du hier zuerst. Beim Löschen verschwinden Konto und Liste.'));
    var box = h('div', { class: 'adm' }), ulast = null, usearch = h('input', { type: 'search', maxlength: '40', placeholder: 'Suchen nach Nummer oder Benutzername', 'aria-label': 'Nutzer suchen', style: 'width:100%;max-width:360px;margin:8px 0', oninput: function () { paint(); } });
    p.append(usearch, box); mainEl.append(p);
    function users() { api('/api/admin.php?a=users').then(function (r) { ulast = r; S.pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; if (admBadge) { admBadge.textContent = S.pend + ' neu'; admBadge.hidden = !S.pend; } nav(); paint(); }); }
    function paint() {
        var r = ulast; if (!r) return; var l = (r.users || []).slice().sort(function (x, y) { return x.id - y.id; }), q = usearch.value.trim().toLowerCase().replace(/^nr\.?\s*/, ''); box.textContent = '';
        if (q) l = l.filter(function (x) { return String(x.id) === q || String(x.id).indexOf(q) === 0 || (x.username || '').toLowerCase().indexOf(q) > -1 || (x.email || '').toLowerCase().indexOf(q) > -1; });
        if (!l.length) { box.append(h('p', { class: 'hint' }, 'Keine Nutzer gefunden.')); return; }
        l.forEach(function (x) {
          var ok = x.role === 'admin' || x.approved_at, me = x.id == r.me;
          var hm = h('span', { class: 'hiddenmail' }, 'E-Mail ausgeblendet'), rev = h('button', { class: 'btn ghost small', type: 'button', title: 'E-Mail-Adresse aufdecken (wird protokolliert)', onclick: function () { var bt = this; if (!confirm('Adresse von ' + (x.username || 'Nr. ' + x.id) + ' wirklich anzeigen? Das wird protokolliert und sollte nur im Ausnahmefall (Missbrauch, Rechtsanfragen) nötig sein.')) return; api('/api/admin.php?a=reveal', { id: x.id }).then(function (r) { if (r.ok) { hm.textContent = r.email; hm.className = ''; bt.remove(); logLoad(); } else alert(r.error); }); } }, 'E-Mail anzeigen');
          box.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' + (x.delete_requested_at ? ' gonename' : '') }, 'Nr. ' + x.id + ' · ', x.username ? h('span', { class: 'unm', title: (x.name_hist && x.name_hist.length) ? 'Letzte Namensänderungen:\n' + x.name_hist.map(function (q) { return new Date(String(q.at).replace(' ', 'T')).toLocaleDateString('de-DE') + ': ' + (q.from ? q.from + ' → ' : 'festgelegt: ') + q.to; }).join('\n') : 'Keine Namensänderung erfasst' }, x.username) : null, '' + (x.delete_requested_at ? (+x.admin_deleted ? ' · gelöscht, endgültig nach 7 Tagen' : ' · Löschung beantragt') : '') + ' · ', x.email ? x.email : hm, +x.name_change_required ? h('span', { class: 'tier', style: 'background:#c0392b;color:#fff' }, 'Neuer Name verlangt') : null, x.role === 'admin' ? h('span', { class: 'tier' }, 'Admin') : null, +x.alpha ? h('span', { class: 'tier alpha' }, 'Alphatester') : null), h('div', { class: 'meta2' }, (ok ? 'freigeschaltet' : 'wartet auf Freischaltung') + (x.email_verified_at ? '' : ' · E-Mail nicht bestätigt') + ' · ' + pl(x.entries, 'Eintrag', 'Einträge') + ' · zuletzt ' + (x.seen_label || (x.last_seen_at || '–').slice(0, 16)) + ' · seit ' + (x.created_at || '').slice(0, 10))),
            me || x.role === 'admin' ? h('div') : h('div', { class: 'acts' }, x.email ? null : rev, h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': String(!!+x.name_change_required), title: +x.name_change_required ? 'Aufforderung zurücknehmen' : 'Nutzer muss einen neuen Benutzernamen wählen, bevor er die Seite wieder nutzen kann', onclick: function () { var on = !+x.name_change_required; if (on && !confirm('Soll ' + (x.username || 'Nr. ' + x.id) + ' einen neuen Benutzernamen wählen müssen? Bis dahin kann der Nutzer die Seite nicht nutzen.')) return; api('/api/admin.php?a=renamereq', { id: x.id, on: on }).then(function (r) { if (r.ok) users(); else alert(r.error); }); } }, 'Namensänderung'),
              h('button', { class: 'btn ghost small', type: 'button', title: (+x.alpha ? 'Alphatester: Alpha entziehen. ' : 'Kein Alphatester: zum Alphatester machen. ') + 'Alphatester sehen Funktionen, die noch in Entwicklung sind', 'aria-label': (+x.alpha ? 'Alphatester, aktiv' : 'Alphatester, nicht aktiv'), onclick: function () { api('/api/admin.php?a=alpha', { id: x.id, on: !+x.alpha }).then(function (r) { if (r.ok) users(); else alert(r.error); }); } }, h('span', { class: +x.alpha ? 'tick yes' : 'tick no', 'aria-hidden': 'true' }, +x.alpha ? '✓' : '✗'), ' Alphatester'), ok ? h('button', { class: 'btn ghost small', type: 'button', onclick: function () { act('revoke', x.id); } }, 'Sperren') : h('button', { class: 'btn small', type: 'button', onclick: function () { act('approve', x.id); } }, 'Freischalten'),
              h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (!confirm('Konto samt Liste wirklich löschen?')) return; var blk = confirm('Soll diese E-Mail-Adresse auch für neue Konten gesperrt werden?\n\nOK = sperren, Abbrechen = nur löschen.'); api('/api/admin.php?a=delete', { id: x.id, block: blk }).then(function (r) { if (r.ok) { users(); blocked(); } else alert(r.error); }); } }, 'Löschen'))));
        });
    }
    function act(a, id) { api('/api/admin.php?a=' + a, { id: id }).then(function (r) { if (r.ok) users(); else alert(r.error); }); }
    var bl = h('div', { class: 'adm' }), bm = h('input', { type: 'email', maxlength: '120', 'aria-label': 'E-Mail-Adresse sperren', placeholder: 'Adresse sperren', autocomplete: 'off' }), bmsg = h('p', { class: 'msg', role: 'status' });
    p.append(h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Gesperrte E-Mail-Adressen'), h('p', { class: 'hint' }, 'Mit diesen Adressen lässt sich kein neues Konto anlegen. Wer sich ohne Hinweis anmeldet, sieht keine Fehlermeldung. Beim Sperren einer Adresse mit bestehendem Konto wird dieses samt Liste gelöscht.'), bl,
      h('form', { class: 'add', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); var v = bm.value.trim(); if (!v) return; api('/api/admin.php?a=block', { email: v }).then(function (r) { bmsg.className = 'msg' + (r.ok ? '' : ' err'); bmsg.textContent = r.ok ? 'Gesperrt.' : (r.error || 'Das hat nicht geklappt.'); if (r.ok) { bm.value = ''; blocked(); users(); } }); } }, bm, h('button', { class: 'btn small', type: 'submit' }, 'Sperren')), bmsg,
      h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Ansicht prüfen'), h('p', { class: 'hint' }, 'Zeigt dir die Seite so, wie sie ein normaler Nutzer sieht, ohne Admin-Funktionen. Zurück geht es über den Hinweis oben auf der Seite.'),
      h('p', null, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { setPreview(true); } }, 'Als normaler Nutzer ansehen')));
    var lgBox = h('div', { class: 'adm' });
    p.append(h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Protokoll der Admin-Zugriffe'), h('p', { class: 'hint' }, 'Hier steht, wann welche E-Mail-Adresse aufgedeckt wurde (die letzten 100 Einträge).'), lgBox);
    function logLoad() {
      api('/api/admin.php?a=adminlog').then(function (r) {
        lgBox.textContent = ''; var l = r.log || [];
        if (!l.length) { lgBox.append(h('p', { class: 'hint' }, r.error || 'Noch keine Einträge.')); return; }
        l.forEach(function (x) { lgBox.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' }, (x.admin_name || x.admin_email || 'Admin') + ' hat die E-Mail-Adresse von ' + (x.target_name ? x.target_name + ' (Nr. ' + x.target_id + ')' : 'Nr. ' + x.target_id + ' (Konto gelöscht)') + ' aufgedeckt'), h('div', { class: 'meta2' }, (function (t) { try { return new Date(String(t).replace(' ', 'T')).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }); } catch (_) { return String(t); } })(x.created_at))))); });
      });
    }
    logLoad();
    sectionize(p, 'Amazon-Partner-Links');
    (function () { // Reiter: die Kästen nach Kategorie gruppieren
      var TABS = [['Nutzer', ['Nutzerverwaltung', 'Registrierung', 'Einladen', 'Gesperrte E-Mail-Adressen']], ['Inhalte', ['Systemnachrichten', 'Bilder der Anmeldeseite', 'Amazon-Partner-Links']], ['Mails', ['Testmail']], ['Sicherheit', ['Protokoll der Admin-Zugriffe', 'Ansicht prüfen', 'Eintrag prüfen', 'Aktualisierung der Einträge']]];
      var cards = [].slice.call(p.querySelectorAll('.setstack>.setcard')), cur = 'Nutzer', bar = h('div', { class: 'admtabs', role: 'tablist' });
      try { var sv = sessionStorage.getItem('sd_admtab'); if (sv && TABS.some(function (t) { return t[0] === sv; })) cur = sv; } catch (_) {}
      admBadge = h('span', { class: 'admbadge', hidden: true });
      var btns = TABS.map(function (t) { var b = h('button', { class: 'admtab', type: 'button', role: 'tab', onclick: function () { cur = t[0]; try { sessionStorage.setItem('sd_admtab', cur); } catch (_) {} show(); } }, t[0]); if (t[0] === 'Nutzer') b.append(' ', admBadge); bar.append(b); return b; });
      function show() {
        TABS.forEach(function (t, i) { btns[i].setAttribute('aria-selected', String(t[0] === cur)); btns[i].className = 'admtab' + (t[0] === cur ? ' on' : ''); });
        cards.forEach(function (c) { var tt = c.querySelector('h3').textContent, own = TABS.filter(function (t) { return t[1].indexOf(tt) > -1; })[0]; c.hidden = own ? own[0] !== cur : cur !== 'Inhalte'; });
      }
      p.querySelector('h2.sec').after(bar); show();
    })();
    function blocked() {
      api('/api/admin.php?a=blocked').then(function (r) {
        bl.textContent = ''; var l = r.blocked || [];
        if (!l.length) bl.append(h('p', { class: 'hint' }, r.error || 'Keine Adresse gesperrt.'));
        l.forEach(function (x) { bl.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' }, x.masked || x.email), h('div', { class: 'meta2' }, 'gesperrt seit ' + String(x.created_at || '').slice(0, 10))), h('div', { class: 'acts' }, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/admin.php?a=unblock', { key: x.key }).then(function () { blocked(); }); } }, 'Freigeben')))); });
      });
    }
    blocked();
    api('/api/admin.php?a=site').then(function (r) { if (r.tag != null) { S.site = { tag: r.tag, domain: r.domain }; tag.value = r.tag; dom.value = r.domain; } });
    users();
  }

  /* ---------- Start ---------- */
  var who = $('who'); if (who) who.addEventListener('click', function () { go('set'); });
  var lg = $('logoutTop'); if (lg) lg.addEventListener('click', function () { api('/api/auth.php?a=logout', {}).then(function () { location.reload(); }); });
  var ver = $('ver'); if (ver) ver.addEventListener('click', function () { go('log'); });
  document.addEventListener('click', function () { var p = $('filtPop'); if (p && !p.hidden) { p.hidden = true; var b = $('filtBtn'); if (b) b.setAttribute('aria-expanded', 'false'); } });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { var p = $('filtPop'); if (p) p.hidden = true; } });
  function askName() {
    var skipped = false; try { skipped = sessionStorage.getItem('unskip') === '1'; } catch (_) {}
    if (D.username || skipped || S.preview) return;
    var inp = h('input', { type: 'text', maxlength: '20', 'aria-label': 'Benutzername', autocomplete: 'off', placeholder: 'z. B. Lesefuchs' }), msg = h('p', { class: 'msg', role: 'status' });
    var box = h('div', { class: 'unmodal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'unh' }, h('div', { class: 'unbox' },
      h('h2', { id: 'unh', style: 'margin:0 0 6px;font-size:20px' }, 'Wähle einen Benutzernamen'),
      h('p', { class: 'hint' }, 'Diesen Namen sehen andere Nutzer. Zur Anmeldung brauchst du ihn nicht. 3 bis 20 Zeichen: Buchstaben (auch Umlaute), Ziffern, Punkt, Unterstrich, Bindestrich, ohne Leerzeichen. Nicht möglich sind vergebene, anstößige oder mit dem Betreiber verwechselbare Namen; klappt es nicht, steht der Grund direkt unter dem Feld. Du kannst ihn danach alle 30 Tage ändern.'),
      inp, msg,
      h('div', { class: 'inline', style: 'margin-top:10px' },
        h('button', { class: 'btn', type: 'button', onclick: function () { api('/api/auth.php?a=username', { username: inp.value }).then(function (r) { if (r.ok) { D.username = r.username; D.uname_left = 30; var w = $('who'); if (w && w.lastChild && w.lastChild.nodeType === 3) w.lastChild.textContent = r.username + ' · ' + D.email; box.remove(); } else { msg.className = 'msg err'; msg.textContent = r.error || 'Das hat nicht geklappt.'; } }); } }, 'Speichern'),
        h('button', { class: 'btn ghost', type: 'button', onclick: function () { try { sessionStorage.setItem('unskip', '1'); } catch (_) {} box.remove(); } }, 'Später'))));
    document.body.append(box); inp.focus();
  }
  function showNews() {
    if (S.preview) return;
    api('/api/data.php?a=news').then(function (r) {
      var l = r.news || []; if (!l.length) return;
      var ids = l.map(function (n) { return n.id; });
      var box = h('div', { class: 'unmodal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'nwh0' }), card = h('div', { class: 'unbox newsbox' });
      var x = h('button', { class: 'newsx', type: 'button', 'aria-label': 'Schließen', onclick: function () { api('/api/data.php?a=news_read', { ids: ids }); box.remove(); } });
      x.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
      card.append(x);
      l.forEach(function (n, i) { card.append(h('h2', { id: 'nwh' + i, style: 'margin:' + (i ? '18px' : '0') + ' 28px 6px 0;font-size:20px' }, n.title), h('p', { style: 'margin:0;white-space:pre-wrap' }, n.body)); });
      box.append(card); document.body.append(box); x.focus();
    });
  }
  // Nach F5 wieder dort starten, wo man war (nur im selben Tab und höchstens 8 Stunden; sonst Kalender)
  try {
    var sv = JSON.parse(sessionStorage.getItem('sd_view') || 'null');
    if (sv && Date.now() - sv.t < 8 * 3600 * 1000) {
      var okv = { cal: 1, list: 1, set: 1, info: 1, log: 1, sup: 1, adm: !!D.admin, feat: true, bug: true };
      if (okv[sv.v] && (sv.v !== 'list' || TYPES[sv.c])) { S.view = sv.v; S.cat = sv.v === 'list' ? sv.c : sv.v; S.sel = sv.v === 'list' && sv.s ? String(sv.s) : null; }
    }
  } catch (_) {}
  load(function () { render(); showNews(); askName(); unreadPoll(); setInterval(unreadPoll, 60000); if (D.admin) api('/api/admin.php?a=users').then(function (r) { S.pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; nav(); }); });
})();
