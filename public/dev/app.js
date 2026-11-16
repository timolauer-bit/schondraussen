(function () {
  var D = window.SD || {}, root = document.getElementById('appview');
  if (D.dev) document.documentElement.classList.add('devmode'); // gelbe Rahmen um Bereiche, die von der Live-Version abweichen
  function devMark(el, id) { if (D.dev && el && el.setAttribute) el.setAttribute('data-dev', id || 'dev'); return el; }
  D.uname_free_until = (+D.uname_free || 0) > 0 ? Date.now() + D.uname_free * 60000 : 0;
  function unFree() { return Math.max(0, Math.ceil(((D.uname_free_until || 0) - Date.now()) / 60000)); }
  if (!root) return;
  if (D.dev) document.documentElement.classList.add('devmode');
  var CL = window.CHANGELOG || [], LATEST = CL.length ? CL[0].v : 1;
  var LOGTOTAL = CL.reduce(function (a, c) { return a + (c.base ? 0 : c.t.length); }, 0);
  var TYPES = {
    author: { label: 'Bücher', plural: 'Bücher', who: 'Autor:innen', ph: 'z. B. Terry Pratchett', idx: 'stripbooks', work: 'Bücher' },
    artist: { label: 'Musik', plural: 'Musik', who: 'Musiker:innen', ph: 'z. B. Radiohead', idx: 'music', work: 'Alben' },
    film: { label: 'Film', plural: 'Filme', who: 'Filme', ph: 'z. B. Dune', idx: 'video', work: 'Filme' },
    series: { label: 'Serie', plural: 'Serien', who: 'Serien', ph: 'z. B. Severance', idx: 'video', work: 'Staffeln' },
    podcast: { label: 'Podcast', plural: 'Podcasts', who: 'Podcasts', ph: 'z. B. Lage der Nation', idx: '', work: 'Folgen' }
  };
  var ALLC = ['author', 'artist', 'film', 'series', 'podcast'];
  var ORDER0 = ALLC.filter(function (t) { return t !== 'podcast' && (!D.cats || D.cats.indexOf(t) >= 0); }), ORDER = ORDER0.slice(), MAXN = 1000;
  if (D.podcast) ORDER.push('podcast');
  function isHid(t) { return (D.hide || []).indexOf(t) >= 0; }
  var CATCOL = { author: 'var(--c-author)', artist: 'var(--c-artist)', film: 'var(--c-film)', series: 'var(--c-series)', podcast: 'var(--c-podcast)' };
  var SKINS = [['', 'Auto', ['#f2f2f0', '#db4d1a', '#101419']], ['light', 'Hell', ['#f2f2f0', '#db4d1a', '#11171d']], ['dark', 'Dunkel', ['#101419', '#ff7842', '#e8ecee']],
    ['paper', 'Papier', ['#f3ead6', '#a23f1c', '#2b2218']], ['cinema', 'Kino', ['#0b0b10', '#e8b830', '#f2ede0']], ['mint', 'Minze', ['#e6f2ee', '#0c7560', '#0f2e28']], ['eighties', '80er', ['#160a2e', '#ff4fb0', '#2de2ff']]];
  var SORTS = [['alpha', 'Alphabetisch'], ['works', 'Anzahl Werke'], ['last', 'Letzte Veröffentlichung'], ['next', 'Nächste Veröffentlichung']];
  var ICONS = {
    author: '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM20 17v4H6.5A2.5 2.5 0 0 1 4 18.5"/></svg>',
    artist: '<svg viewBox="0 0 24 24"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    film: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/></svg>',
    series: '<svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="m17 2-5 5-5-5"/></svg>',
    podcast: '<svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4M8 22h8"/></svg>',
    lock: '<svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    unlock: '<svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/></svg>',
    news: '<svg viewBox="0 0 24 24"><path d="M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2zM19 8h2v10a2 2 0 0 1-2 2M8 8h7M8 12h7M8 16h4"/></svg>',
    cal: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    user: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    clip: '<svg viewBox="0 0 24 24"><path d="M21 11.5 12.6 20a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/></svg>',
    thumb: '<svg viewBox="0 0 24 24"><path d="M7 11v9H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3zm0 0 4-8a2 2 0 0 1 2 2v4h5.2a2 2 0 0 1 2 2.4l-1.3 6A2 2 0 0 1 16.9 20H7"/></svg>',
    star: '<svg viewBox="0 0 24 24"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>',
    bell: '<svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    bellOff: '<svg viewBox="0 0 24 24"><path d="M13.7 21a1.94 1.94 0 0 1-3.4 0M17 17H3s3-2 3-9a6 6 0 0 1 .3-1.9M8.7 3.3A6 6 0 0 1 18 8c0 2.5.4 4.4.9 5.7M2 2l20 20"/></svg>',
    gear: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    people: '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><circle cx="17.5" cy="9" r="2.7"/><path d="M17 14.3A6 6 0 0 1 22 21"/></svg>',
    shield: '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    bulb: '<svg viewBox="0 0 24 24"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.7.6 1 1.4 1 2.3h6c0-.9.3-1.7 1-2.3A7 7 0 0 0 12 2z"/></svg>',
    bug: '<svg viewBox="0 0 24 24"><rect x="8" y="6" width="8" height="14" rx="4"/><path d="M19 7l-3 2M5 7l3 2M19 19l-3-2M5 19l3-2M20 13h-4M4 13h4M10 2l1 2M14 2l-1 2"/></svg>',
    info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    chat: '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    refresh: '<svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-15-6.7L3 8M3 3v5h5M3 12a9 9 0 0 0 15 6.7L21 16M21 21v-5h-5"/></svg>'
  };

  var S = { n: D.n || { 'new': 1, day: 1, week: 0, mode: 'digest', weekday: 1, bell_default: 1 }, relink: null, epTried: {}, view: 'cal', cat: 'cal', sel: null, entries: [], rels: [], site: { tag: '', domain: 'amazon.de' }, ready: false, filter: '', sort: {}, genre: {}, kind: {}, confirm: null, menuFor: null,
    unread: 0, pend: 0, adding: false, sugg: null, msg: '', msgErr: false, notify: D.notify, skin: D.skin === 'auto' ? '' : (D.skin || ''), sup: { sel: null, threads: [] } };
  var $ = function (id) { return document.getElementById(id); };
  var topbar = $('topbar'), mainEl = root, supTimer = null;

  function hiddenMail(addr) {
    var s = h('span', { class: 'hidmail' });
    var b = h('button', { class: 'btn ghost small', type: 'button', title: 'Adresse nur auf Wunsch zeigen (zum Beispiel bei Screenshots)', onclick: function () { s.textContent = addr; } }, 'E-Mail-Adresse anzeigen');
    s.append(b); return s;
  }
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
      S.entries = (r[0].entries || []).filter(function (e) { return !isHid(e.type); }).map(function (e) { e.id = String(e.id); e.works = +e.works || 0; e.upcoming = +e.upcoming || 0; return e; });
      S.site = r[0].site || S.site; S.rels = (r[1].releases || []).filter(function (x) { return !isHid(x.type); }); S.ready = true;
      if (cb) cb(); else render();
    });
  }
  function amazonUrl(e, title) {
    var d = S.site.domain || 'amazon.de', t = TYPES[e.type] || TYPES.film, term = [title, (e.type === 'author' || e.type === 'artist') ? e.name : ''].filter(Boolean).join(' '), idx = t.idx;
    if (idx === 'music') idx = d === 'amazon.com' ? 'music' : 'popular'; else if (idx === 'video') idx = d === 'amazon.com' ? 'movies-tv' : 'dvd';
    var u = 'https://www.' + d + '/s?k=' + encodeURIComponent(term) + '&i=' + idx; if (S.site.tag) u += '&tag=' + encodeURIComponent(S.site.tag); return u;
  }
  // Podcast-Folge: Titel mit Kurzfassung aus dem Feed per „i“ (Klick oder Hover), kein Spoiler-Hinweis nötig
  function podTitle(w) {
    var ttl = h('div', { class: 'ttl' }, w.title); if (!w.sum) return ttl;
    var box = h('div', { class: 'epsum', role: 'tooltip', hidden: true }, w.sum), shown = false;
    var ib = h('button', { class: 'epinfo', type: 'button', 'aria-label': 'Kurzfassung der Folge anzeigen', 'aria-expanded': 'false' }, 'i');
    function show(on) { shown = on; box.hidden = !on; ib.setAttribute('aria-expanded', on ? 'true' : 'false'); }
    ib.addEventListener('click', function () { show(!shown); });
    if (window.matchMedia && matchMedia('(hover:hover)').matches) { ib.addEventListener('mouseenter', function () { if (!shown) { show(true); ib.dataset.hv = '1'; } }); ib.addEventListener('mouseleave', function () { if (ib.dataset.hv) { delete ib.dataset.hv; show(false); } }); ib.addEventListener('click', function () { delete ib.dataset.hv; }); }
    ttl.append(' ', ib); return h('div', { class: 'epcell' }, ttl, box);
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
  function logBadge() { var s; try { s = parseInt(localStorage.getItem('sd-seen'), 10); } catch (_) {} if (isNaN(s)) return 0; if (s > LOGTOTAL) { markSeen(); return 0; } return LOGTOTAL - s; }
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
  function hasAdm() { return !!(D.admin || (D.admsec || []).length) && !S.preview; } // Admin-Symbol: Admins und Moderatoren mit mindestens einem Bereich
  function setPreview(on) { S.preview = !!on; var pb = $('previewbar'); if (pb) pb.hidden = !on; S.view = on ? 'cal' : 'adm'; S.cat = S.view; S.sel = null; S.menuFor = null; say(''); nav(); render(); window.scrollTo(0, 0); }
  var po = $('previewOff'); if (po) po.addEventListener('click', function () { setPreview(false); });
  function go(view, cat) { if (view === 'adm' && S.view !== 'adm') { try { sessionStorage.setItem('sd_admtab', 'Briefing'); } catch (_) {} } S.view = view; S.cat = cat || view; S.sel = null; S.confirm = null; S.menuFor = null; S.filter = ''; S.adding = false; S.sugg = null; say(''); render(); window.scrollTo(0, 0); }
  // Versionswahl (Live, Dev, Design-Varianten): kleines Menü am Schalter, setzt nur das Cookie
  function devMenu(anchor) {
    var old = document.getElementById('devmenu'); if (old) { old.remove(); return; }
    var opts = [{ m: 'live', t: 'Live-Version', d: 'Was alle Nutzer sehen.', on: !D.dev }, { m: 'dev', t: 'Entwicklungsversion', d: 'Neues, das noch nicht freigegeben ist.', on: D.dev && !D.design }].concat((D.designs || []).map(function (x) { return { m: 'd:' + x.slug, t: x.name, d: x.desc, on: D.design === x.slug }; }));
    var box = h('div', { id: 'devmenu', class: 'devmenu', role: 'menu' }, opts.map(function (o) {
      return h('button', { type: 'button', role: 'menuitemradio', 'aria-checked': o.on ? 'true' : 'false', class: 'dmi' + (o.on ? ' on' : ''), onclick: function () { if (o.on) { box.remove(); return; } api('/api/data.php?a=devmode', { mode: o.m }).then(function () { location.reload(); }); } }, h('b', null, o.t), o.d ? h('small', null, o.d) : null);
    }));
    var wb = anchor.parentNode; wb.append(box);
    function off(ev) { if (!box.contains(ev.target) && ev.target !== anchor) { box.remove(); document.removeEventListener('click', off, true); document.removeEventListener('keydown', esc, true); } }
    function esc(ev) { if (ev.key === 'Escape') { box.remove(); document.removeEventListener('click', off, true); document.removeEventListener('keydown', esc, true); anchor.focus(); } }
    setTimeout(function () { document.addEventListener('click', off, true); document.addEventListener('keydown', esc, true); }, 0);
  }
  function nav() {
    topbar.textContent = '';
    // Rechte Gruppe: Symbole. Für alle nur Symbole (Name per Tooltip, bei der aktiven Seite sichtbar).
    var RI = { comm: 'people', adm: 'shield', set: 'gear', feat: 'bulb', bug: 'bug', info: 'info', sup: 'chat' }, iconOnly = true;
    function btn(key, text, count, extra) {
      var cur = S.view === 'list' ? S.cat === key : S.view === key, ri = RI[key], io = ri && iconOnly;
      return h('button', { class: 'nav' + (extra || '') + (io ? ' ionly' : ''), type: 'button', 'aria-current': cur ? 'page' : null, title: io ? text : null, 'aria-label': io ? text : null, onclick: function () { /^(author|artist|film|series|podcast)$/.test(key) ? go('list', key) : go(key); } },
        key === 'cal' ? ico(ICONS.cal) : (key === 'news' ? ico(ICONS.news) : null), ri ? ico(ICONS[ri]) : null, /^(author|artist|film|series|podcast)$/.test(key) ? h('i', { class: 'cdot c-' + key, 'aria-hidden': 'true' }) : null, io ? h('span', { class: 'lbl' }, text) : h('span', { class: 'nt' }, text), count ? h('small', null, String(count)) : null);
    }
    var navMain = h("div", { class: "navgrp navmain" }), navRest = h("div", { class: "navgrp navrest" });
    var navTop = null;
    if (D.dev && D.news && !S.preview) { navTop = h("div", { class: "navgrp navtop" }); navTop.append(btn('cal', 'Kalender', 0), devMark(btn('news', 'News', 0, ' newsnav'), 'news')); } // Handy: eigene Zeile
    else navMain.append(btn('cal', 'Kalender', 0));
    ORDER0.forEach(function (k) { if (!isHid(k)) navMain.append(btn(k, TYPES[k].plural, 0)); });
    var rg = ' rg';
    if (D.podcast && !isHid('podcast')) navMain.append(btn('podcast', 'Podcasts', 0, ' podnav'));
    if (commOn()) { navRest.append(btn('comm', 'Community', 0, ' commnav' + rg)); rg = ''; }
    if ((D.admin || D.devsw) && !S.preview) { var dsLabel = D.design ? ((D.designs || []).filter(function (x) { return x.slug === D.design; })[0] || {}).name || 'Design' : (D.dev ? 'Dev' : 'Live'); var dsw = h('button', { class: 'devsw' + (D.dev ? ' on' : ''), type: 'button', 'aria-haspopup': 'menu', title: 'Du siehst: ' + dsLabel + '. Klicken, um die Version zu wechseln (Live, Entwicklung, Design-Varianten).', onclick: function () { devMenu(dsw); } }, h('span', { class: 'devlbl' }, dsLabel)); var dn = (D.devnotes || []).filter(function (x) { return typeof x === 'string' && x; }); if (dn.length) { dsw.append(h('span', { class: 'devbadge', 'aria-hidden': 'true' }, String(dn.length))); dsw.title += '\n\nUnterschiede der Entwicklungsversion zur Live-Version (' + dn.length + '):\n• ' + dn.join('\n• '); dsw.setAttribute('aria-label', dsLabel + ', ' + dn.length + ' Unterschiede zur Live-Version'); } var wb = document.querySelector('.whobox'), od = document.getElementById('devsw'); if (od) od.remove(); dsw.id = 'devsw'; if (wb) wb.insertBefore(dsw, wb.firstChild); }
    if (hasAdm()) { var b = btn('adm', 'Admin', 0, ' adminnav' + rg); if (S.pend) b.append(h('span', { class: 'badge' }, String(S.pend))); navRest.append(b); rg = ''; }
    navRest.append(btn('set', 'Einstellungen', 0, ' settings' + rg));
    function fbBadge(b, key) { var n = (S.fbNew || {})[key] || 0; if (n && S.view !== key && !S.preview) b.append(h('span', { class: 'badge', 'aria-label': n + ' offen: Freischaltungen und Neues' }, n > 99 ? '99+' : String(n))); return b; }
    if (feat('feat')) navRest.append(fbBadge(btn('feat', 'Ideen', 0, ' feat'), 'feat'));
    navRest.append(fbBadge(btn('bug', 'Bugs', 0, ' bugs'), 'bug'));
    navRest.append(btn('info', 'Info', 0, ' info'));
    var k = btn('sup', 'Kontakt', 0, ' support'); if (S.unread) k.append(h('span', { class: 'badge', 'aria-label': S.unread + ' ungelesen' }, S.unread > 99 ? '99+' : String(S.unread))); navRest.append(k);
    if (navTop) topbar.append(navTop);
    topbar.append(navMain, navRest);
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
    else if (v === 'set') settings(); else if (v === 'info') info(); else if (v === 'log') logView(); else if (v === 'sup') support(); else if (v === 'adm') admin(); else if (v === 'comm' && commOn()) community(); else if (v === 'feat' && feat('feat')) feedbackView('feature'); else if (v === 'bug') feedbackView('bug'); else if (v === 'news' && D.dev && D.news) newsView();
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
      h('div', { class: 'acts' }, it.e.type === 'podcast' ? null : amazonLink(it.e, it.u.title)));
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
  /* Kalender: Tooltip an den Tagen mit Terminen (Maus, Tastatur und Antippen) */
  var dayTipEl = null;
  function hideDayTip() { if (dayTipEl) { dayTipEl.remove(); dayTipEl = null; } }
  function showDayTip(anchor, date, list) {
    hideDayTip();
    var rows = list.slice(0, 8).map(function (it) { return h('li', null, h('i', { class: 'tdot', style: 'background:' + CATCOL[it.e.type] }), h('span', null, h('b', null, it.e.name), it.u.title && it.u.title !== it.e.name ? ' – ' + it.u.title : '', it.u.kind ? h('small', null, ' ' + it.u.kind) : null)); });
    if (list.length > 8) rows.push(h('li', { class: 'more' }, '+ ' + (list.length - 8) + ' weitere'));
    var tip = dayTipEl = h('div', { class: 'daytip', role: 'tooltip' }, h('p', { class: 'dthead' }, date.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long' })), h('ul', null, rows));
    document.body.append(tip);
    var r = anchor.getBoundingClientRect(), tw = tip.offsetWidth, th = tip.offsetHeight, sx = window.pageXOffset, sy = window.pageYOffset;
    var left = Math.max(8, Math.min(r.left + r.width / 2 - tw / 2, document.documentElement.clientWidth - tw - 8)) + sx, top = r.top - th - 8 >= 8 ? r.top - th - 8 + sy : r.bottom + 8 + sy;
    tip.style.left = left + 'px'; tip.style.top = top + 'px';
  }
  function dayTip(el, date, list) {
    el.addEventListener('mouseenter', function () { showDayTip(el, date, list); });
    el.addEventListener('mouseleave', hideDayTip);
    el.addEventListener('focus', function () { showDayTip(el, date, list); });
    el.addEventListener('blur', hideDayTip);
    el.addEventListener('click', function (ev) { ev.stopPropagation(); if (dayTipEl) hideDayTip(); else showDayTip(el, date, list); });
    el.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') hideDayTip(); });
  }
  document.addEventListener('scroll', hideDayTip, true); document.addEventListener('click', hideDayTip);
  function renderMonths(items, onFilter) {
    var now = today0(), byDay = {};
    items.forEach(function (it) { if (it.p.prec !== 'day') return; var k = it.p.y + '-' + it.p.m + '-' + it.p.d, o = byDay[k] || (byDay[k] = { n: 0, cats: {}, list: [] }); o.n++; o.cats[it.e.type] = 1; o.list.push(it); });
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
          var pie = h('span', { class: 'pie', style: 'background:conic-gradient(' + stops + ')', tabindex: '0', role: 'button', 'aria-label': d + '.' + m + '.: ' + o.n + (o.n === 1 ? ' Termin' : ' Termine') + ' (' + cats.map(function (k) { return TYPES[k].plural; }).join(', ') + ')' }, h('span', { class: 'pn' }, String(d))); dayTip(pie, new Date(y, m - 1, d), o.list); cell.append(pie);
        } else cell.append(h('span', { class: 'pn plain' }, String(d)));
        cal.append(cell);
      }
      grid.append(h('div', { class: 'mon' }, h('h3', null, MONTH.format(first)), cal));
    }
    var cnt = {}; items.forEach(function (it) { cnt[it.e.type] = (cnt[it.e.type] || 0) + 1; });
    var leg = h('div', { class: 'legend legfilt', role: 'group', 'aria-label': 'Kategorien filtern' }, ORDER.filter(function (k) { return !isHid(k); }).map(function (k) {
      var b = h('button', { class: 'lchip', type: 'button', style: '--c:' + CATCOL[k], 'aria-pressed': S.calHide[k] ? 'false' : 'true', title: (S.calHide[k] ? 'Einblenden: ' : 'Ausblenden: ') + TYPES[k].plural + ' (' + (cnt[k] || 0) + ' Termine)', onclick: function () { S.calHide[k] = !S.calHide[k]; b.setAttribute('aria-pressed', S.calHide[k] ? 'false' : 'true'); b.title = (S.calHide[k] ? 'Einblenden: ' : 'Ausblenden: ') + TYPES[k].plural + ' (' + (cnt[k] || 0) + ' Termine)'; if (onFilter) onFilter(); } }, h('i', { 'aria-hidden': 'true' }), TYPES[k].plural);
      return b;
    }));
    return h('section', { class: 'months', 'aria-label': 'Monatsübersicht' }, grid, leg);
  }
  function renderCalendar() {
    var items = S.rels.map(toItem), wrap = h('div', { class: 'main' });
    wrap.append(h('p', { class: 'note' }, 'Angekündigte Veröffentlichungen aus allen deinen Listen, nach Datum sortiert. Die Angaben kommen aus TVmaze, MusicBrainz, Open Library und TMDB und werden alle 12 Stunden erneuert.'));
    if (!S.entries.length) { wrap.append(h('div', { class: 'empty' }, h('h2', null, 'Noch nichts auf deinen Listen'), h('p', null, 'Wähle oben eine Liste und füge etwas hinzu. Die kommenden Termine erscheinen dann hier.'))); return wrap; }
    if (!items.length) { wrap.append(h('p', { class: 'state' }, 'Keine angekündigten Termine bekannt.')); return wrap; }
    S.calHide = S.calHide || {};
    var tbl = h('div', { class: 'caltbl' });
    function drawTbl() {
      var vis = items.filter(function (it) { return !S.calHide[it.e.type]; }); tbl.textContent = '';
      /* Liste: von Podcasts höchstens die 10 letzten vergangenen Folgen (Monatsübersicht oben zeigt weiter alle) */
      var now = new Date(), tdy = now.getFullYear() * 10000 + (now.getMonth() + 1) * 100 + now.getDate(), tym = Math.floor(tdy / 100);
      function isPast(it) { var q = it.p || {}; if (!q.y) return false; return q.prec === 'day' ? q.y * 10000 + q.m * 100 + q.d < tdy : q.y * 100 + q.m < tym; }
      var pastPod = vis.filter(function (it) { return it.e.type === 'podcast' && isPast(it); });
      if (pastPod.length > 10) { var drop = pastPod.slice().sort(function (x, y) { return x.p.sort - y.p.sort; }).slice(0, pastPod.length - 10); vis = vis.filter(function (it) { return drop.indexOf(it) < 0; }); }
      if (!vis.length) tbl.append(h('p', { class: 'state' }, 'Alle Kategorien sind ausgeblendet. Wähle oben eine Kategorie, um Termine zu sehen.')); else tbl.append(groupByMonth(vis));
    }
    wrap.append(renderMonths(items, drawTbl)); wrap.append(tbl); drawTbl(); return wrap;
  }

  /* ---------- Listen ---------- */
  function listView() {
    var t = TYPES[S.cat], es = entriesOf(S.cat);
    var layout = h('div', { class: 'layout has-rail' }), rail = h('aside', { class: 'rail', id: 'rail', 'data-cat': S.cat, style: '--cc:' + CATCOL[S.cat] }), main = h('section', { class: 'main', id: 'content' });
    rail.append(h('section', null, h('p', { class: 'label' }, 'Neu hinzufügen'),
      h('form', { class: 'add', id: 'addform', autocomplete: 'off', onsubmit: addSubmit }, h('input', { id: 'q', type: 'text', maxlength: '80', 'aria-label': 'Name', placeholder: t.ph, oninput: localSugg }), h('button', { class: 'btn', id: 'addbtn', type: 'submit' }, 'Suchen')),
      h('p', { class: 'msg', id: 'msg', role: 'status' }), h('div', { id: 'sugg' })));
    rail.append(h('section', null, h('p', { class: 'label', id: 'listlbl' }, t.plural + ' · ' + es.length + ' / ' + MAXN),
      h('div', { class: 'srchrow' }, h('label', { class: 'field srch' }, h('input', { id: 'srch', type: 'search', maxlength: '80', 'aria-label': 'Einträge durchsuchen', placeholder: 'In der Liste filtern', value: S.filter, oninput: function () { S.filter = this.value; rows(); } })),
        h('button', { class: 'btn ghost filtbtn', id: 'filtBtn', type: 'button', 'aria-label': 'Sortieren und filtern', 'aria-expanded': 'false', onclick: function (ev) { ev.stopPropagation(); var p = $('filtPop'); p.hidden = !p.hidden; this.setAttribute('aria-expanded', String(!p.hidden)); } },
          h('span', { class: 'fico', html: '' }), h('span', { class: 'fbadge', id: 'fbadge', hidden: true })),
        h('div', { class: 'filtpop', id: 'filtPop', hidden: true, onclick: function (ev) { ev.stopPropagation(); } })),
      h('div', { class: 'fchips', id: 'fchips' }), h('div', { class: 'list', id: 'list' })));
    layout.append(rail, main); mainEl.append(layout);
    $('filtBtn').insertAdjacentHTML('afterbegin', '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>');
    var fi = $('filtBtn').querySelector('.fico'); if (fi) fi.remove();
    filt(); rows(); content(); drawSugg();
  }
  // Genres zusammenfassen: Die Quellen liefern sehr viele Einzelbezeichnungen (z. B. „Science-Fiction-Literatur“, „Dystopie“); die Anzeige fasst sie zu wenigen Hauptgenres zusammen. Unbekanntes bleibt unverändert.
  var GRULES = {
    author: [['Dystopie & Utopie', /utopi|dystopi/i], ['Science-Fiction', /science.?fiction|sci.?fi|\bsf\b|cyberpunk|steampunk|space.?opera/i], ['Märchen & Sagen', /märchen|\bsagen?\b|mythol|heldenepos/i], ['Fantasy', /fantas/i], ['Spionage & Agenten', /spionage|agenten|espionage/i], ['Krimi & Thriller', /krimi|thriller|detektiv|mystery|crime|noir|kriminal|suspense|spannung/i], ['Horror & Grusel', /horror|grusel|schauer|gothic|gespenster/i], ['Liebesroman', /liebes|romanz|romance|erotik|frauenroman/i], ['Western', /western/i], ['Abenteuer', /abenteuer|adventure/i], ['Historischer Roman', /histor/i], ['Kurzgeschichten', /kurzgeschicht|short stories|erzählungen/i], ['Geschichte', /geschichte|weltkrieg|\bkrieg/i], ['Kinder & Jugend', /jugend|kinder|young adult|children|bilderbuch|\bya\b/i], ['Comic & Manga', /comic|manga|graphic|anime/i], ['Biografie & Memoiren', /biogra|autobiogr|memoir|erinnerungen|lebensbericht/i], ['Philosophie', /philosoph/i], ['Politik & Gesellschaft', /politik|gesellschaft|soziolog|journalis/i], ['Wirtschaft', /wirtschaft|business|finanz|management|ökonom|economics/i], ['Psychologie & Ratgeber', /psycholog|ratgeber|selbsthilfe|self.?help|lebenshilfe|gesundheit/i], ['Religion & Spiritualität', /religio|spiritual|theolog|esoter|christen|buddh|islam/i], ['Natur & Reisen', /natur|reise|travel|outdoor|garten/i], ['Kochen & Genuss', /koch|kulinar|cook|backen|rezept|genuss/i], ['Kunst & Kultur', /kunst|fotograf|architektur|design|kultur/i], ['Wissenschaft & Technik', /wissenschaft|technik|technolog|informatik|mathemat|physik|biolog|medizin|popul/i], ['Essay', /essay/i], ['Sachbuch', /sachbuch|non.?fiction|sachliteratur/i], ['Humor & Satire', /humor|satir|komödie|comedy|witz/i], ['Lyrik & Drama', /lyrik|poesie|gedicht|drama|theater|schauspiel|dichtung/i], ['Belletristik', /roman|erzähl|novelle|belletristik|literatur|fiction|prosa|klassik/i]],
    artist: [['Thrash Metal', /thrash/i], ['Death Metal', /death metal|deathcore|grind/i], ['Black Metal', /black metal/i], ['Power Metal', /power metal|speed metal|symphonic metal|melodic metal/i], ['Doom Metal', /doom|sludge|stoner/i], ['Metal', /metal|hardcore/i], ['Gothic & Dark Wave', /goth|dark ?wave|industrial/i], ['Progressive', /prog/i], ['Hard Rock', /hard rock|glam|heavy/i], ['Punk', /punk/i], ['Indie & Alternative', /indie|alternative|britpop|grunge|new wave/i], ['Rock', /rock/i], ['House & Techno', /house|techno|trance|\bedm\b|dubstep|drum/i], ['Ambient & Downtempo', /ambient|downtempo|chill|\bidm\b/i], ['Elektronik', /electro|elektro|synth/i], ['Hip-Hop', /hip.?hop|\brap\b|trap/i], ['Funk & Disco', /funk|disco/i], ['R&B & Soul', /r&b|\brnb\b|soul|motown|gospel/i], ['Jazz', /jazz|swing|bebop/i], ['Blues', /blues/i], ['Soundtrack', /soundtrack|film score|filmmusik|\bost\b/i], ['Oper & Musical', /opern|\boper\b|oper$|musical|operette/i], ['Klassik', /klassi|classical|orchest|barock|symphon|kammer/i], ['Country', /country|bluegrass|americana/i], ['Folk & Singer-Songwriter', /folk|singer|songwriter|liedermacher/i], ['Reggae & Ska', /reggae|\bska\b|dancehall|\bdub\b/i], ['Latin', /latin|salsa|samba|bossa|flamenco/i], ['Schlager & Volksmusik', /schlager|volksmusik|volkstüm/i], ['Weltmusik', /world|afro|weltmusik/i], ['Pop', /\bpop\b|pop$|^pop|dance|boy ?band/i]]
  };
  function canonG(type, g) { var rs = GRULES[type]; if (rs) for (var i = 0; i < rs.length; i++) if (rs[i][1].test(g)) return rs[i][0]; return g; }
  function gensOf(e) { var o = [], seen = {}; (e.genres || []).forEach(function (g) { var c = canonG(e.type, g); if (!seen[c]) { seen[c] = 1; o.push(c); } }); return o; }
  function genresOf(cat) { var c = {}; entriesOf(cat).forEach(function (e) { gensOf(e).forEach(function (g) { c[g] = (c[g] || 0) + 1; }); }); return Object.keys(c).sort(function (a, b) { return c[b] - c[a] || a.localeCompare(b, 'de'); }).slice(0, 30).map(function (g) { return [g, c[g]]; }); }
  var KINDS = { author: [['', 'Alle'], ['author', 'Autor:innen'], ['series', 'Reihen']], film: [['', 'Alle'], ['movie', 'Einzelfilme'], ['col', 'Filmreihen'], ['person', 'Regie']] };
  function kindOf(e) { return e.type === 'film' ? (+e.is_person ? 'person' : +e.is_col ? 'col' : 'movie') : (+e.is_series === 1 ? 'series' : 'author'); }
  function filt() {
    var cur = S.sort[S.cat] || 'alpha', g = S.genre[S.cat] || '', kd = KINDS[S.cat] ? (S.kind[S.cat] || '') : '', n = (cur !== 'alpha' ? 1 : 0) + (g ? 1 : 0) + (kd ? 1 : 0), pop = $('filtPop'); pop.textContent = '';
    pop.append(h('p', { class: 'label' }, 'Sortieren nach'));
    SORTS.forEach(function (o) { pop.append(h('button', { class: 'fopt', type: 'button', 'aria-pressed': String(cur === o[0]), onclick: function () { S.sort[S.cat] = o[0]; filt(); rows(); } }, o[1])); });
    if (KINDS[S.cat]) {
      pop.append(h('p', { class: 'label', style: 'margin-top:14px' }, 'Art'));
      KINDS[S.cat].forEach(function (o) { pop.append(h('button', { class: 'fopt', type: 'button', 'aria-pressed': String(kd === o[0]), onclick: function () { S.kind[S.cat] = o[0]; filt(); rows(); } }, o[1])); });
    }
    var gl = genresOf(S.cat);
    { pop.append(h('p', { class: 'label', style: 'margin-top:14px' }, 'Genre'));
      var sel = h('select', { 'aria-label': 'Nach Genre filtern', disabled: gl.length ? null : true, onchange: function () { S.genre[S.cat] = this.value; filt(); rows(); } }, h('option', { value: '' }, gl.length ? 'Alle Genres' : 'Keine Genres vorhanden'), gl.map(function (x) { return h('option', { value: x[0] }, x[0] + ' (' + x[1] + ')'); }));
      sel.value = g; pop.append(sel); }
    var b = $('fbadge'); b.hidden = !n; b.textContent = String(n);
    var ch = $('fchips'); ch.textContent = '';
    if (cur !== 'alpha') ch.append(h('button', { class: 'fchip', type: 'button', title: 'Sortierung zurücksetzen', onclick: function () { S.sort[S.cat] = 'alpha'; filt(); rows(); } }, SORTS.filter(function (o) { return o[0] === cur; })[0][1] + ' ×'));
    if (kd) ch.append(h('button', { class: 'fchip', type: 'button', title: 'Filter zurücksetzen', onclick: function () { S.kind[S.cat] = ''; filt(); rows(); } }, KINDS[S.cat].filter(function (o) { return o[0] === kd; })[0][1] + ' ×'));
    if (g) ch.append(h('button', { class: 'fchip', type: 'button', title: 'Genre-Filter zurücksetzen', onclick: function () { S.genre[S.cat] = ''; filt(); rows(); } }, g + ' ×'));
  }
  function worksLine(e) { var t = TYPES[e.type]; return !e.fetched_at ? 'noch nicht geladen' : e.works ? (e.type === 'series' ? pl(e.works, 'Staffel', 'Staffeln') : e.type === 'author' ? pl(e.works, 'Buch', 'Bücher') : e.type === 'artist' ? (+e.singles === +e.works ? pl(e.works, 'Veröffentlichung', 'Veröffentlichungen') : pl(e.works, 'Album', 'Alben')) : e.type === 'podcast' ? pl(e.works, 'Folge', 'Folgen') : e.type === 'film' ? (+e.is_col ? 'Reihe, ' : +e.is_person ? 'Regie, ' : '') + pl(e.works, 'Film', 'Filme') : pl(e.works, 'Werk', 'Werke')) : 'keine Daten'; }
  function rows() {
    var box = $('list'); box.textContent = '';
    var es = sortedEntries(S.cat), t = TYPES[S.cat], gq = S.genre[S.cat] || '', fq = (S.filter || '').trim().toLowerCase();
    if (!es.length) { box.append(h('p', { class: 'hint' }, 'Noch keine ' + t.plural + ' auf der Liste.')); return; }
    var kq = KINDS[S.cat] ? (S.kind[S.cat] || '') : '';
    if (kq) es = es.filter(function (e) { return kindOf(e) === kq; });
    if (gq) es = es.filter(function (e) { return gensOf(e).indexOf(gq) >= 0; });
    if (fq) es = es.filter(function (e) { return e.name.toLowerCase().indexOf(fq) >= 0; });
    if (!es.length) { box.append(h('p', { class: 'hint' }, fq ? 'Keine Treffer für „' + S.filter.trim() + '“.' : (kq && !gq ? 'Keine Einträge in dieser Auswahl.' : 'Keine Einträge im Genre „' + gq + '“.'))); return; }
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
    es.forEach(function (e) { works += e.works - (e.type === 'artist' ? (+e.singles || 0) : 0); var l = itemsOf(e); ups += l.length; items = items.concat(l); var ne = +e.episodes || 0; eps += ne; if (ne) epsKnown++; var tw = +e.total_works; if (tw > 0) { tot += tw; totKnown++; } });
    var tiles = cat === 'author' ? [['Autor:innen', es.length], ['Bücher in deiner Liste', works, null, 'Die Bücher, die wir pro Autor:in tatsächlich speichern: höchstens 200 Treffer je Person, mehrfache Ausgaben desselben Titels nur einmal, angekündigte Bücher zählen unter „Kommende Veröffentlichungen“.'], ['Bücher insgesamt (geschätzt)', tot, totKnown ? 'Angabe bei ' + totKnown + ' von ' + es.length : 'noch keine Angaben', 'Trefferzahl von Open Library. Sie enthält mehrfache Ausgaben und Übersetzungen desselben Buchs und Treffer über der Grenze von 200 je Autor:in, deshalb liegt sie höher als die Zahl in deiner Liste.'], ['Kommende Veröffentlichungen', ups, null, 'Angekündigte Bücher oder Alben mit Termin oder zumindest Erscheinungsjahr in der Zukunft.']]
      : cat === 'artist' ? [['Musiker:innen', es.length], ['Alben in deiner Liste', works, null, 'Die Alben und EPs, die wir pro Musiker:in speichern. Livealben, Compilations, Remixe, Soundtracks und Ähnliches werden nur gezählt, wenn sie noch kommen.'], ['Alben insgesamt (geschätzt)', tot, totKnown ? 'Angabe bei ' + totKnown + ' von ' + es.length : 'noch keine Angaben', 'Zahl der Alben und EPs, die MusicBrainz zur Person führt, inklusive mancher Doppel- oder Sonderfassungen.'], ['Kommende Veröffentlichungen', ups, null, 'Angekündigte Bücher oder Alben mit Termin oder zumindest Erscheinungsjahr in der Zukunft.']]
      : cat === 'film' ? [['Filme', es.length], ['Filme und Teile der Reihen', works], ['Kommende Starts', ups]]
      : [['Serien', es.length], ['Staffeln', works, null, 'Summe der Staffeln aller Serien in deiner Liste (Quelle: TVmaze).'], ['Folgen gesamt', eps, epsKnown ? 'Folgenzahl bei ' + epsKnown + ' von ' + es.length : 'noch keine Angaben', 'Alle bekannten Folgen. Bei Serien, die noch nicht geladen sind, fehlt die Angabe.'], ['Kommende Termine', ups]];
    wrap.append(h('h2', { class: 'sec' }, 'Übersicht ' + t.plural));
    if (cat === 'film' && es.length && es.every(function (x) { return !x.fetched_at; })) wrap.append(h('p', { class: 'warnbox' }, h('b', null, 'Hinweis: '), 'Die Datenquelle für Filme ist hier noch nicht angebunden. Du kannst Filme schon auf deine Liste setzen, Werke und Kinotermine erscheinen aber erst, wenn die Anbindung aktiviert ist.'));
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
  function nextBox(e, r, up, t) {
    var now = today0(), nxt = null, node = h('aside', { class: 'nextbox' });
    up.slice().sort(function (a, b) { return a.p.sort - b.p.sort; }).forEach(function (it) { if (!nxt && it.p.prec !== 'none' && it.p.end >= now) nxt = it; });
    var cap = { series: 'Nächste Folge', podcast: 'Aktuelle Folge', author: 'Nächste Veröffentlichung', artist: +e.singles > 0 && +e.singles === +e.works ? 'Neue Veröffentlichung' : 'Neues Album', film: 'Im Kino' }[e.type] || 'Nächster Termin';
    if (e.type === 'podcast') nxt = up.length ? up.slice().sort(function (a, b) { return b.p.sort - a.p.sort; })[0] : null;
    node.append(h('small', null, cap));
    if (nxt) {
      var p = nxt.p, big, diff = p.prec === 'day' ? Math.round((p.start - now) / 864e5) : null;
      if (e.type === 'podcast') big = diff === null ? '' : diff === 0 ? 'heute' : diff === -1 ? 'gestern' : 'vor ' + (-diff) + ' Tagen';
      else big = p.prec === 'day' ? (diff === 0 ? 'heute' : diff === 1 ? 'morgen' : 'in ' + diff + ' Tagen') : p.prec === 'month' ? MSHORT.format(p.start).replace('.', '') + ' ' + p.y : String(p.y);
      var when = p.prec === 'day' ? LONG.format(p.start) : p.prec === 'month' ? 'Monat bekannt' : 'Jahr bekannt';
      node.append(h('b', { class: 'nbig' }, big), h('div', { class: 'nline' }, nxt.u.title), h('div', { class: 'nsub' }, when));
    } else node.append(h('b', { class: 'nbig none' }, e.type === 'podcast' ? 'nichts neu' : 'offen'), h('div', { class: 'nsub' }, e.type === 'podcast' ? 'Keine Folge in den letzten 14 Tagen.' : 'Noch nichts angekündigt.'));
    var st = h('div', { class: 'nstats' }), prog = null, a1 = null, a2 = null;
    if (e.type === 'series') {
      var eps = r.episodes || [], seas = {}; eps.forEach(function (x) { seas[x.season] = 1; });
      st.append(h('span', null, h('b', null, Object.keys(seas).length), ' ' + (Object.keys(seas).length === 1 ? 'Staffel' : 'Staffeln')), h('span', null, h('b', null, eps.length), ' ' + (eps.length === 1 ? 'Folge' : 'Folgen')));
      var gs = h('span', null); node.append(st, h('div', { class: 'nstats' }, gs));
      prog = function (n, tot) { gs.textContent = ''; gs.append(h('b', null, n), ' von ' + tot + ' gesehen'); };
      var dn = doneSetOf(r), c0 = 0; eps.forEach(function (x) { if (dn[x.k]) c0++; }); prog(c0, eps.length);
    } else {
      var tot = Math.max(+e.total_works || 0, r.works.length), dn2 = doneSetOf(r), c1 = 0; r.works.forEach(function (w) { if (dn2[w.k]) c1++; });
      if (tot) st.append(h('span', null, h('b', null, tot), ' ' + (e.type === 'author' ? 'Bücher' : e.type === 'artist' ? (+e.singles > 0 && +e.singles === +e.works ? 'Veröffentlichungen' : 'Alben') : e.type === 'podcast' ? 'Folgen' : 'Werke')));
      if (r.works.length && e.type !== 'podcast') { var dv0 = (DONEV[e.type] || DONEV.artist)[0]; st.append(h('span', null, h('b', null, c1), ' ' + dv0)); }
      if (st.childNodes.length) node.append(st);
    }
    return { node: node, prog: prog };
  }
  function entryCard(cur, r) {
    var e = r.entry, card = h('article', { class: 'card cat-' + e.type, style: '--cc:' + CATCOL[e.type] }), t = TYPES[e.type];
    var stamp = e.fetched_at ? 'Stand ' + LONG.format(new Date(e.fetched_at.replace(' ', 'T'))) : '';
    var le = null; S.entries.forEach(function (x) { if (x.id === String(e.id)) le = x; });
    var bellOn = !le || +le.bell !== 0;
    var bellBtn = h('button', { class: 'btn ghost small bellbtn ' + (bellOn ? 'on' : 'off'), type: 'button', title: bellOn ? 'Benachrichtigungen für diesen Eintrag sind an' : 'Benachrichtigungen für diesen Eintrag sind aus', 'aria-pressed': String(bellOn), 'aria-label': bellOn ? 'Benachrichtigungen an' : 'Benachrichtigungen aus',
      onclick: function () { bellOn = !bellOn; if (le) le.bell = bellOn ? 1 : 0; api('/api/data.php?a=bell&id=' + e.id, { on: bellOn }); bellBtn.className = 'btn ghost small bellbtn ' + (bellOn ? 'on' : 'off'); bellBtn.title = bellOn ? 'Benachrichtigungen für diesen Eintrag sind an' : 'Benachrichtigungen für diesen Eintrag sind aus'; bellBtn.setAttribute('aria-pressed', String(bellOn)); bellBtn.setAttribute('aria-label', bellOn ? 'Benachrichtigungen an' : 'Benachrichtigungen aus'); bellBtn.textContent = ''; bellBtn.append(ico(on_(bellOn))); rows(); } }, ico(on_(bellOn)));
    var favOn = !!(le && +le.fav === 1);
    var favBtn = h('button', { class: 'btn ghost small favbtn ' + (favOn ? 'on' : 'off'), type: 'button', 'aria-pressed': String(favOn), title: favOn ? 'Favorit: erscheint in der Community als persönliche Empfehlung' : 'Als Favorit markieren (bis zu 5 je Kategorie, erscheint in der Community als persönliche Empfehlung)', 'aria-label': favOn ? 'Favorit, anklicken zum Entfernen' : 'Als Favorit markieren',
      onclick: function () {
        var want = !favOn; favBtn.disabled = true;
        api('/api/data.php?a=fav&id=' + e.id, { on: want }).then(function (x) {
          favBtn.disabled = false;
          if (!x || !x.ok) { popup(x && x.limit ? 'Nur 5 Favoriten je Kategorie' : 'Nicht gespeichert', (x && x.error) || 'Das hat nicht geklappt.'); return; }
          favOn = want; if (le) le.fav = want ? 1 : 0;
          favBtn.className = 'btn ghost small favbtn ' + (favOn ? 'on' : 'off'); favBtn.setAttribute('aria-pressed', String(favOn)); favBtn.setAttribute('aria-label', favOn ? 'Favorit, anklicken zum Entfernen' : 'Als Favorit markieren');
          favBtn.title = favOn ? 'Favorit: erscheint in der Community als persönliche Empfehlung' : 'Als Favorit markieren (bis zu 5 je Kategorie, erscheint in der Community als persönliche Empfehlung)';
        });
      } }, ico(ICONS.star));
    var menu = h('div', { class: 'menu', role: 'menu', hidden: true }), moreBtn, wrap;
    function closeMenu() { menu.hidden = true; moreBtn.setAttribute('aria-expanded', 'false'); document.removeEventListener('click', outside, true); document.removeEventListener('keydown', esc); }
    function outside(ev) { if (!wrap.contains(ev.target)) closeMenu(); }
    function esc(ev) { if (ev.key === 'Escape') closeMenu(); }
    function fillMenu() {
      menu.textContent = '';
      menu.append(h('button', { class: 'mi', id: 'rfr', type: 'button', role: 'menuitem', onclick: function () { closeMenu(); refresh(cur); } }, ico(ICONS.refresh), 'Aktualisieren'));
      menu.append(h('button', { class: 'mi', type: 'button', role: 'menuitem', onclick: function () { closeMenu(); S.relink = e.id; content(); } }, 'Treffer ändern'));
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
    var actions = h('div', { class: 'actions' }, bellBtn, favBtn, wrap);
    var meta = h('div', { class: 'meta' }, h('span', { class: 'mcat' }, ico(ICONS[e.type]), t.label), stamp ? h('span', { class: 'mstamp tipd', title: 'Zeitpunkt der letzten Aktualisierung aus der Datenquelle. Die Angaben werden automatisch alle 12 Stunden erneuert; „Aktualisieren“ lädt sie sofort neu.' }, stamp) : null);
    var gch = gensOf(e).length ? h('div', { class: 'gchips' }, gensOf(e).map(function (g) { return h('span', { class: 'gchip' }, g); })) : null;
    card.append(h('div', { class: 'card-head' }, h('div', { class: 'ch1' }, meta, h('h2', null, e.name), gch), actions));
    if (S.relink === e.id) card.append(relinkBox(cur, e));
    else if (e.fetched_at && !r.works.length && !r.releases.length && !(r.episodes || []).length) card.append(h('p', { class: 'warnbox' }, 'Zu diesem Eintrag wurden keine Daten gefunden. Oft hilft ein anderer Titel, bei Serien und Filmen zum Beispiel der englische. ' + ((r.entry && r.entry.err) ? 'Rückmeldung der Quelle: ' + r.entry.err + ' ' : ''), h('button', { class: 'btn small', type: 'button', style: 'margin-left:6px', title: 'Suche in der Datenquelle nach dem passenden Titel, zum Beispiel mit dem englischen Namen', onclick: function () { S.relink = e.id; content(); } }, 'Treffer ändern')));
    if (!e.fetched_at) { card.append(h('p', { class: 'state pulse', id: 'st' }, 'Daten werden geladen …')); refresh(cur, true); return card; }
    if (e.type === 'film' && e.selfkey && (!e.kind || e.kind === 'movie')) {
      var fdone = doneSetOf(r), fb = h('button', { class: 'pill', type: 'button', 'aria-pressed': 'false', title: 'Nur für dich: Markiere, ob du den Film schon gesehen hast. Andere sehen das nicht.' });
      var fupd = function () { var on = !!fdone[e.selfkey]; fb.className = 'pill' + (on ? ' on' : ''); fb.setAttribute('aria-pressed', on ? 'true' : 'false'); fb.textContent = on ? 'Gesehen' : 'Noch nicht gesehen'; };
      fb.addEventListener('click', function () { var on = !fdone[e.selfkey]; setDone(e, [e.selfkey], on, function () { if (on) fdone[e.selfkey] = 1; else delete fdone[e.selfkey]; fupd(); }); });
      fupd(); card.append(h('p', null, fb));
    }
    if (e.type === 'film' && (e.dirs || []).length) {
      var dl = h('p', { class: 'hint' }, 'Regie: ');
      e.dirs.forEach(function (d, i) {
        var have = S.entries.some(function (x) { return x.type === 'film' && +x.is_person && x.name === d.name; });
        dl.append(i ? ', ' : '', d.name);
        if (!have) { var ab = h('button', { class: 'pill', type: 'button', style: 'margin-left:6px', title: 'Regie als eigenen Eintrag anlegen: alle Filme und kommende Termine von ' + d.name }, 'Eintrag anlegen');
          ab.addEventListener('click', function () { ab.disabled = true; api('/api/data.php?a=add', { type: 'film', name: d.name, ext: { tmdb_person: d.id } }).then(function (x) { if (x.ok) { toast(d.name + ' steht jetzt unter Filme.'); load(function () { ab.remove(); }); } else { ab.disabled = false; toast(x.error || 'Das hat nicht geklappt.'); } }); });
          dl.append(ab); }
      });
      card.append(dl);
    }
    if (e.type === 'film' && e.watch) { // Streaming in Deutschland (Daten von JustWatch über TMDB)
      var wb = h('div', { class: 'watchbox' }, h('h3', { class: 'sec' }, 'Wo läuft der Film?')), any = false;
      [['flatrate', 'Streaming'], ['rent', 'Leihen'], ['buy', 'Kaufen']].forEach(function (g) { var l = e.watch[g[0]] || []; if (l.length) { any = true; wb.append(h('p', { class: 'hint', style: 'margin:4px 0' }, h('b', null, g[1] + ': '), l.join(', '))); } });
      if (!any) wb.append(h('p', { class: 'hint' }, 'Zurzeit ist der Film bei keinem Anbieter in Deutschland gelistet.'));
      wb.append(h('p', { class: 'note' }, 'Angaben zur Verfügbarkeit in Deutschland von ', h('a', { href: 'https://www.justwatch.com', target: '_blank', rel: 'noopener noreferrer' }, 'JustWatch'), ' (über TMDB), ohne Gewähr.'));
      card.append(wb);
    }
    var sumNode = null;
    if (e.summary) {
      var sp = h('p', { class: 'sum' }, e.summary);
      if (e.summary.length > 320) {
        sp.classList.add('clamp');
        var mb = h('button', { class: 'sumtoggle', type: 'button', 'aria-expanded': 'false' }, 'Mehr anzeigen');
        mb.addEventListener('click', function () { var open = sp.classList.toggle('clamp') === false; mb.textContent = open ? 'Weniger anzeigen' : 'Mehr anzeigen'; mb.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        sumNode = h('div', { class: 'sumbox' }, sp, mb);
      } else sumNode = h('div', { class: 'sumbox' }, sp);
    }
    var up = r.releases.map(function (x) { return { e: e, p: parseDate(x.release_date), u: { title: x.title, date: x.release_date, kind: x.kind || '', note: x.note || '' } }; });
    var nb = nextBox(e, r, up, t);
    card.append(h('div', { class: 'hero' + (sumNode ? '' : ' solo') }, sumNode, nb.node));
    var progCb = nb.prog;
    if (e.type === 'film' && e.fetched_at) card.append(h('p', { class: 'note' }, 'Filmdaten von ', h('a', { href: 'https://www.themoviedb.org', target: '_blank', rel: 'noopener noreferrer' }, 'TMDB'), '. Dieses Produkt nutzt die TMDB-API, wird aber von TMDB weder unterstützt noch zertifiziert.'));
    if (e.type === 'film' && (!e.kind || e.kind === 'movie') && r.works.length <= 1) { card.append(renderSimilar(e)); return card; } // einzelner Film: Gesehen-Knopf oben genügt
    if (e.type === 'podcast' && (e.by || e.page)) card.append(h('p', { class: 'hint' }, e.by ? 'Von ' + e.by : '', e.by && e.page ? ' · ' : '', e.page ? h('a', { href: e.page, target: '_blank', rel: 'noopener noreferrer' }, 'Bei Apple Podcasts ansehen') : null));
    if (e.type !== 'series') { // bei Serien steht das schon in der Staffelübersicht und im Kasten rechts oben
    card.append(h('h3', { class: 'sec' }, e.type === 'podcast' ? 'Neue Folgen (letzte 14 Tage)' : 'Kommende Veröffentlichungen'));
    if (e.type === 'author') card.append(h('p', { class: 'warnbox' }, h('b', null, 'Hinweis: '), (up.length ? 'Die frei verfügbaren Buch-Schnittstellen liefern nur wenige zukünftige Bücher. Meist kommen Bücher erst nach dem Erscheinen dazu. Darum stehen hier auch Bücher, die in den letzten 4 Wochen neu bei der Quelle hinzugekommen sind („neu hinzugefügt“).' : 'Die frei verfügbaren Buch-Schnittstellen liefern nur wenige zukünftige Bücher. Zurzeit ist hier nichts angekündigt, und in den letzten 4 Wochen ist bei der Quelle auch nichts Neues dazugekommen. Sobald sich das ändert, erscheint es hier und du bekommst eine Mail, wenn du sie eingeschaltet hast.')));
    if (up.length) up.forEach(function (it) { card.append(relRow(it)); }); else card.append(h('p', { class: 'state' }, e.type === 'podcast' ? 'Keine neuen Folgen in den letzten 14 Tagen.' : 'Keine angekündigten Termine bekannt.'));
    }
    if (e.type === 'series' && (r.episodes || []).length) { card.append(renderEpisodes(e, r.episodes, doneSetOf(r), progCb)); card.append(renderSimilar(e)); return card; }
    if (e.type === 'series' && e.fetched_at && !S.epTried[e.id]) { S.epTried[e.id] = 1; refresh(cur, true); }
    card.append(h('h3', { class: 'sec' }, e.type === 'series' ? 'Staffeln' : e.type === 'podcast' ? 'Letzte Folgen' : e.type === 'film' ? (e.kind === 'person' ? 'Filme als Regisseur:in' : 'Filme der Reihe') : 'Bekannte Werke'));
    if (!r.works.length) card.append(h('p', { class: 'state' }, e.type === 'film' ? '' : 'Keine Werke gefunden.'));
    else if (e.type !== 'series') card.append(h('p', { class: 'hint' }, r.works.length + ' ' + t.work + (+e.total_works > r.works.length ? ' von ca. ' + e.total_works : '') + ' aufgelistet.'));
    var favw = {}; (r.favw || []).forEach(function (k) { favw[k] = 1; });
    var dv = DONEV[e.type] || DONEV.artist, done = doneSetOf(r), hideDone = false, rows = [], cnt = h('span', { class: 'cnt' }), flt = h('button', { class: 'pill', type: 'button', 'aria-pressed': 'false' }, 'Nur Neues für mich'), wfill = h('i'), wbar = h('span', { class: 'wbar' }, wfill), grid = e.type === 'podcast' ? card : h('div', { class: 'wgrid' });
    function updAll() { allLbl(); var n = r.works.filter(function (w) { return done[w.k]; }).length; wfill.style.width = (r.works.length ? Math.round(100 * n / r.works.length) : 0) + '%'; cnt.textContent = n + ' von ' + r.works.length + ' ' + t.work + ' ' + dv[0];
      rows.forEach(function (x) { var on = !!done[x.w.k]; x.pill.className = 'pill' + (on ? ' on' : ''); x.pill.setAttribute('aria-pressed', on ? 'true' : 'false'); x.pill.textContent = on ? dv[0].charAt(0).toUpperCase() + dv[0].slice(1) : dv[1]; x.row.hidden = hideDone && on; }); }
    var allB = h('button', { class: 'pill', type: 'button', title: 'Markiert alle aufgelisteten Einträge auf einmal. Nur für dich sichtbar.' });
    function allLbl() { var n = r.works.filter(function (w) { return done[w.k]; }).length; allB.textContent = r.works.length && n === r.works.length ? 'Alles zurücksetzen' : 'Alles ' + dv[0]; }
    allB.addEventListener('click', function () { var keys = r.works.map(function (w) { return w.k; }), on = !keys.every(function (k) { return done[k]; }); setDone(e, keys, on, function () { keys.forEach(function (k) { if (on) done[k] = 1; else delete done[k]; }); updAll(); }); });
    flt.addEventListener('click', function () { hideDone = !hideDone; flt.className = 'pill' + (hideDone ? ' on' : ''); flt.setAttribute('aria-pressed', hideDone ? 'true' : 'false'); updAll(); });
    if (r.works.length && e.type !== 'series') card.append(h('div', { class: 'donebar' }, cnt, flt, allB), wbar);
    r.works.forEach(function (w) {
      var pill = h('button', { type: 'button', title: 'Nur für dich: Markiere, was du schon ' + dv[0] + ' hast. Andere sehen das nicht.' }), row = e.type === 'podcast' ? h('div', { class: 'work' }, h('div', { class: 'yr' }, w.year || '–'), h('div', null, podTitle(w), (w.kind || w.note) ? h('div', { class: 'sub' }, [w.kind, w.note].filter(Boolean).join(' · ')) : null), h('div', { class: 'wact' }, pill)) : h('div', { class: 'work wcard' }, h('div', { class: 'wtop' }, h('div', { class: 'yr' }, w.year || '–'), h('div', { class: 'wtr' }, pill, wstar(e, w, favw))), h('div', { class: 'ttl' }, w.title), (w.kind || w.note) ? h('div', { class: 'sub' }, [w.kind, w.note].filter(Boolean).join(' · ')) : null, h('div', { class: 'wfoot' }, amazonLink(e, w.title)));
      pill.addEventListener('click', function () { var on = !done[w.k]; setDone(e, [w.k], on, function () { if (on) done[w.k] = 1; else delete done[w.k]; updAll(); }); });
      rows.push({ w: w, pill: pill, row: row }); grid.append(row);
    });
    if (grid !== card) card.append(grid);
    updAll();
    if (e.type !== 'podcast') card.append(renderSimilar(e));
    return card;
  }
  /* ---------- Vorschläge: ähnliche Einträge und beliebte Einträge je Genre ---------- */
  var POP = {
    artist: [['Rock', 'rock'], ['Metal', 'metal'], ['Thrash Metal', 'thrash metal'], ['Hard Rock', 'hard rock'], ['Punk', 'punk'], ['Pop', 'pop'], ['Hip-Hop', 'hip hop'], ['Elektronik', 'electronic'], ['Jazz', 'jazz'], ['Klassik', 'classical'], ['Folk', 'folk'], ['Blues', 'blues'], ['Reggae', 'reggae'], ['Country', 'country'], ['Indie', 'indie'], ['Soul', 'soul'], ['Schlager', 'schlager']],
    film: [['Action', '28'], ['Abenteuer', '12'], ['Animation', '16'], ['Komödie', '35'], ['Krimi', '80'], ['Dokumentarfilm', '99'], ['Drama', '18'], ['Familie', '10751'], ['Fantasy', '14'], ['Historie', '36'], ['Horror', '27'], ['Musik', '10402'], ['Mystery', '9648'], ['Liebesfilm', '10749'], ['Science-Fiction', '878'], ['Thriller', '53'], ['Krieg', '10752'], ['Western', '37']],
    series: [['Drama', 'Drama'], ['Komödie', 'Comedy'], ['Science-Fiction', 'Science-Fiction'], ['Fantasy', 'Fantasy'], ['Krimi', 'Crime'], ['Thriller', 'Thriller'], ['Horror', 'Horror'], ['Action', 'Action'], ['Abenteuer', 'Adventure'], ['Mystery', 'Mystery'], ['Romanze', 'Romance'], ['Anime', 'Anime'], ['Familie', 'Family'], ['Western', 'Western'], ['Krieg', 'War'], ['Geschichte', 'History']],
    author: [['Fantasy', 'Fantasy'], ['Science-Fiction', 'Science fiction'], ['Krimi', 'Mystery and detective stories'], ['Thriller', 'Thriller'], ['Horror', 'Horror'], ['Liebesroman', 'Romance'], ['Historischer Roman', 'Historical fiction'], ['Biografie', 'Biography'], ['Jugendbuch', 'Young adult fiction'], ['Humor', 'Humor'], ['Abenteuer', 'Adventure']]
  };
  /* Stern an einem Einzeltitel (Film einer Reihe, Album, Buch); zählt zu den 5 Favoriten der Kategorie */
  function wstar(e, w, set) {
    if (['film', 'artist', 'author'].indexOf(e.type) < 0 || !w.k) return null;
    var on = !!set[w.k], b = h('button', { type: 'button', class: 'btn ghost small favbtn ' + (on ? 'on' : 'off'), 'aria-pressed': String(on) }, ico(ICONS.star));
    function look() { b.className = 'btn ghost small favbtn ' + (on ? 'on' : 'off'); b.setAttribute('aria-pressed', String(on)); b.setAttribute('aria-label', (on ? 'Favorit, anklicken zum Entfernen: ' : 'Als Favorit markieren: ') + w.title); b.title = on ? 'Favorit: erscheint in der Community als persönliche Empfehlung' : 'Als Favorit markieren (bis zu 5 je Kategorie, erscheint in der Community als persönliche Empfehlung)'; }
    look();
    b.addEventListener('click', function () {
      var want = !on; b.disabled = true;
      api('/api/data.php?a=favwork&id=' + e.id, { k: w.k, on: want }).then(function (x) {
        b.disabled = false;
        if (!x || !x.ok) { if (x && x.limit) popup('Nur 5 Favoriten je Kategorie', x.error); else say((x && x.error) || 'Das hat nicht geklappt.', true); return; }
        on = want; if (on) set[w.k] = 1; else delete set[w.k]; look();
      });
    });
    return b;
  }
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
        body.append(suggestList(e.type, r), h('p', { class: 'hint' }, 'Die Vorschläge kommen aus ' + ({ artist: 'ListenBrainz', series: 'TVmaze-Daten (gleiche Genres, nach Beliebtheit)', author: 'Open Library (gleiche Themen)', film: 'TMDB (Empfehlungen zu diesem Film)' }[e.type] || 'freien Datenquellen') + ' und können Lücken haben.'));
      });
    }
    if (e.type === 'film' && e.kind === 'person') return h('div');
    body.append(h('p', { class: 'hint' }, 'Lass dir zeigen, was dir noch gefallen könnte.'), h('p', null, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { ask(false); } }, 'Vorschläge laden')));
    return sec;
  }
  function popularBox(cat) {
    if (cat === 'podcast') return h('div');
    var t = TYPES[cat], sec = h('section', { class: 'similar' }), chips = h('div', { class: 'gchips pick', role: 'group', 'aria-label': 'Genre wählen' }), out = h('div', null), cur = null;
    sec.append(h('h3', { class: 'sec' }, 'Beliebte ' + t.who + ' entdecken'));
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
  var DONEV = { podcast: ['gehört', 'Noch nicht gehört', 'headphones'], series: ['gesehen', 'Noch nicht gesehen', 'eye'], film: ['gesehen', 'Noch nicht gesehen', 'eye'], artist: ['gehört', 'Noch nicht gehört', 'headphones'], author: ['gelesen', 'Noch nicht gelesen', 'book'] };
  function setDone(e, keys, on, cb) { api('/api/data.php?a=done', { id: e.id, keys: keys, on: on ? 1 : 0 }).then(function (x) { if (x && x.ok) cb(); else toast(x && x.error && x.error !== 'Unerwartete Antwort' ? x.error : 'Das hat gerade nicht geklappt. Bitte versuche es gleich noch einmal.'); }); }
  function doneSetOf(r) { var o = {}; (r.done || []).forEach(function (k) { o[k] = 1; }); return o; }
  function renderEpisodes(e, eps, done, progCb) {
    done = done || {};
    var now = today0(), box = h('div', null), by = {}, strip = h('div', { class: 'strip', role: 'tablist' }), panels = {}, tiles = {}, sel = null, gbtns = [], updFns = [];
    box.append(h('h3', { class: 'sec' }, 'Staffeln und Folgen'));
    box.append(h('p', { class: 'note' }, 'Folgen und Sendetermine von ', h('a', { href: e.tvmaze ? 'https://www.tvmaze.com/shows/' + e.tvmaze : 'https://www.tvmaze.com', target: '_blank', rel: 'noopener noreferrer' }, 'TVmaze'),
      e.fetched_at ? ', Stand ' + LONG.format(new Date(e.fetched_at.replace(' ', 'T'))) : '', e.tv_status ? '. Serienstatus: ' + (TVS[e.tv_status] || e.tv_status) : '', '. Die Angaben werden alle 12 Stunden erneuert.'));
    eps.forEach(function (x) { (by[x.season] = by[x.season] || []).push(x); });
    var nums = Object.keys(by).map(Number).sort(function (a, b) { return a - b; }), liveSeason = null;
    function total() { var n = 0; eps.forEach(function (x) { if (done[x.k]) n++; }); if (progCb) progCb(n, eps.length); gbtns.forEach(function (b) { b.textContent = n === eps.length ? 'Alles zurücksetzen' : 'Alles als gesehen markieren'; }); }
    function pick(sn) { sel = sn; nums.forEach(function (n) { var on = n === sn; tiles[n].classList.toggle('on', on); tiles[n].setAttribute('aria-selected', on ? 'true' : 'false'); panels[n].hidden = !on; }); }
    nums.forEach(function (sn) {
      var l = by[sn], fut = 0; l.forEach(function (x) { var p = x.airdate ? parseDate(x.airdate) : null; if (p && p.prec !== 'none' && p.end >= now) fut++; });
      var past = l.length - fut, live = fut > 0, state = !live ? 'abgeschlossen' : past === 0 ? 'startet bald' : 'läuft gerade';
      if (live && liveSeason === null) liveSeason = sn;
      var cks = [], allBtn = h('button', { class: 'btn ghost small', type: 'button' }), fill = h('i'), prog = h('span', { class: 'sprog' });
      var tile = h('button', { class: 's', type: 'button', role: 'tab', title: 'So viele Folgen dieser Staffel hast du als gesehen markiert. Nur für dich sichtbar.' }, h('b', null, 'Staffel ' + sn), h('span', null, pl(l.length, 'Folge', 'Folgen') + ' · ' + state), h('span', { class: 'bar' }, fill), prog);
      tile.addEventListener('click', function () { pick(sn); });
      tiles[sn] = tile; strip.append(tile);
      function upd() { var n = cks.filter(function (c) { return done[c.k]; }).length; prog.textContent = n + ' von ' + l.length + ' gesehen'; fill.style.width = (l.length ? Math.round(100 * n / l.length) : 0) + '%'; tile.classList.toggle('full', n === l.length);
        allBtn.textContent = n === l.length ? 'Staffel zurücksetzen' : 'Staffel als gesehen markieren';
        cks.forEach(function (c) { c.b.className = 'ck' + (done[c.k] ? ' on' : ''); c.b.setAttribute('aria-pressed', done[c.k] ? 'true' : 'false'); c.b.textContent = done[c.k] ? '✓' : ''; }); total(); }
      var det = h('div', { class: 'season', role: 'tabpanel', hidden: true });
      panels[sn] = det;
      l.forEach(function (x) {
        var p = x.airdate ? parseDate(x.airdate) : null, ok = p && p.prec === 'day', isFut = ok && p.end >= now, diff = ok ? Math.round((p.start - now) / 864e5) : 0;
        var when = ok ? WDAY.format(p.start).replace('.', '') + ', ' + LONG.format(p.start) : 'Datum offen', stat = isFut ? (diff === 0 ? 'heute' : diff === 1 ? 'morgen' : 'in ' + diff + ' Tagen') : (ok ? 'gezeigt' : '');
        var ckb = h('button', { type: 'button', 'aria-label': 'Folge ' + x.number + ' als gesehen markieren' }); cks.push({ b: ckb, k: x.k });
        ckb.addEventListener('click', function () { var on = !done[x.k]; setDone(e, [x.k], on, function () { if (on) done[x.k] = 1; else delete done[x.k]; upd(); }); });
        var ttl = h('div', { class: 'ttl' }, x.title || 'Titel noch offen'), sbox = null;
        if (+x.has_sum) {
          var ib = h('button', { class: 'epinfo', type: 'button', 'aria-label': 'Kurzfassung der Folge ' + x.number + ' anzeigen' }, 'i'), loaded = false, shown = false;
          sbox = h('div', { class: 'epsum', role: 'tooltip', hidden: true });
          var show = function (on) {
            shown = on; sbox.hidden = !on; ib.setAttribute('aria-expanded', on ? 'true' : 'false');
            if (on && !loaded) { loaded = true; sbox.textContent = 'Lade …'; api('/api/data.php?a=episum&id=' + e.id + '&s=' + x.season + '&n=' + x.number).then(function (r) { sbox.textContent = r && r.text ? r.text : 'Keine Zusammenfassung vorhanden.'; if (r && r.text) sbox.append(h('div', { class: 'sub' }, 'Achtung: kann Spoiler enthalten.')); }); }
          };
          ib.addEventListener('click', function () { show(!shown); });
          if (window.matchMedia && matchMedia('(hover:hover)').matches) { ib.addEventListener('mouseenter', function () { if (!shown) { show(true); ib.dataset.hv = '1'; } }); ib.addEventListener('mouseleave', function () { if (ib.dataset.hv) { delete ib.dataset.hv; show(false); } }); ib.addEventListener('click', function () { delete ib.dataset.hv; }); }
          ttl.append(' ', ib);
        }
        det.append(h('div', { class: 'work ep' }, ckb, h('div', { class: 'yr' }, 'E' + String(x.number).padStart(2, '0')), h('div', { class: 'epcell' }, ttl, h('div', { class: 'sub' }, when), sbox), h('div', { class: 'stat ' + (isFut ? 'soon' : 'sub') }, stat)));
      });
      allBtn.addEventListener('click', function () { var keys = cks.map(function (c) { return c.k; }), on = !keys.every(function (k) { return done[k]; }); setDone(e, keys, on, function () { keys.forEach(function (k) { if (on) done[k] = 1; else delete done[k]; }); upd(); }); });
      var gBtn = h('button', { class: 'btn ghost small', type: 'button', title: 'Markiert alle Folgen aller Staffeln dieser Serie auf einmal' }); gbtns.push(gBtn);
      gBtn.addEventListener('click', function () { var keys = eps.map(function (x) { return x.k; }), on = !keys.every(function (k) { return done[k]; }); setDone(e, keys, on, function () { keys.forEach(function (k) { if (on) done[k] = 1; else delete done[k]; }); updFns.forEach(function (f) { f(); }); }); });
      updFns.push(upd);
      upd();
      det.insertBefore(h('div', { class: 'season-foot' }, allBtn, ' ', gBtn, ' ', amazonLink(e, e.name + ' Staffel ' + sn)), det.firstChild);
      box.append(det);
    });
    box.insertBefore(strip, panels[nums[0]]);
    pick(liveSeason !== null ? liveSeason : nums[nums.length - 1]);
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
      if (x.moved) S.sel = String(x.moved); // der Einzelfilm ist jetzt in der Filmreihe aufgegangen
      if (x.ok) load(function () { S.ready = true; if (S.view === 'list') { nav(); if ($('list')) { filt(); rows(); content(); } } }); else { var st = $('st'); if (st) { st.classList.remove('pulse'); st.textContent = x.msg || x.error || 'Das hat nicht geklappt.'; } if (b) { b.disabled = false; b.lastChild.textContent = 'Aktualisieren'; } load(function () { nav(); }); }
    });
  }
  /* Hinzufügen mit Suche */
  var locT = 0, locSeq = 0;
  function localSugg() { // Sofort beim Tippen: Treffer aus den schon vorhandenen Einträgen der Seite; Enter sucht wie bisher im Netz
    var v = this.value.trim(), cat = S.cat; clearTimeout(locT); var my = ++locSeq;
    if (v.length < 2) { if (S.sugg && S.sugg.local) { S.sugg = null; drawSugg(); } return; }
    locT = setTimeout(function () {
      api('/api/data.php?a=suggest_local&type=' + cat + '&q=' + encodeURIComponent(v)).then(function (r) {
        if (my !== locSeq || cat !== S.cat) return; var q = $('q'); if (!q || q.value.trim() !== v) return;
        S.sugg = { local: true, q: v, list: (r && r.results) || [] }; drawSugg();
      });
    }, 150);
  }
  function addSubmit(ev) {
    ev.preventDefault(); locSeq++; clearTimeout(locT); var v = $('q').value.trim(); if (!v) { say('Gib erst einen Namen ein.', true); return; }
    S.sugg = { loading: true }; drawSugg(); say('');
    api('/api/data.php?a=search&type=' + S.cat + '&q=' + encodeURIComponent(v)).then(function (r) { S.sugg = { q: v, list: r.results || [], manual: !!r.manual }; drawSugg(); });
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
    if (s.local) {
      box.append(h('p', { class: 'hint' }, s.list.length ? 'Schon auf der Seite vorhanden. Passt keiner, drücke Enter, dann wird im Netz gesucht.' : 'Noch nicht auf der Seite vorhanden. Drücke Enter, um im Netz zu suchen.'));
      if (s.list.length) { var ll = h('div', { class: 'suggest' });
        s.list.forEach(function (x) { ll.append(h('button', { class: 'row-btn', type: 'button', disabled: !!x.mine, onclick: function () { addNow(x.name, x.ext); } }, h('span', { class: 'nm' }, x.name), h('span', { class: 'tp' }, x.mine ? 'schon in deiner Liste' : (x.hint || '')))); });
        box.append(ll); }
      return;
    }
    box.append(h('p', { class: 'hint' }, s.manual ? 'Für Filme gibt es noch keine Suche. Der Titel wird so übernommen, wie du ihn eingegeben hast.' : s.list.length ? (s.byTitle ? 'Bücher mit diesem Titel. Welche Autor:in ist gemeint?' : 'Welcher Treffer ist gemeint?') : (s.byTitle ? 'Kein Buch mit diesem Titel gefunden.' : 'Keine Treffer.')));
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

  var AVN = 50;
  function avUrl(a) { a = a || ''; return /^s\d{2}$/.test(a) ? '/avatars/avatar' + a.slice(1) + '.svg' : /^c\d{1,9}$/.test(a) ? '/api/data.php?a=avatar&id=' + a.slice(1) : ''; }
  var SERGEN = ['Drama', 'Comedy', 'Crime', 'Thriller', 'Mystery', 'Action', 'Adventure', 'Science-Fiction', 'Fantasy', 'Horror', 'Supernatural', 'Romance', 'Family', 'Children', 'Anime', 'History', 'War', 'Western', 'Espionage', 'Legal', 'Medical', 'Sports', 'Music', 'Food', 'Travel', 'Nature', 'DIY', 'Adult'];
  function genOpts(t) { return t === 'author' || t === 'artist' ? GRULES[t].map(function (r) { return r[0]; }) : SERGEN; }
  function profileBox() {
    var wrap = h('div', { class: 'wide2 profbox' }), msg = h('p', { class: 'msg', role: 'status' });
    wrap.append(h('p', { class: 'hint' }, 'Alle Angaben sind freiwillig. Andere sehen sie nur in der Community, und nur wenn du der Nutzung deiner Einträge für die Community zugestimmt hast.'), h('p', { class: 'state pulse' }, 'Lade …'));
    Promise.all([api('/api/data.php?a=profile'), api('/api/data.php?a=wish').catch(function () { return {}; })]).then(function (rr) {
      var r = rr[0], wd = rr[1] || {};
      wrap.lastChild.remove();
      if (!r || r.error) { wrap.append(h('p', { class: 'msg err' }, (r && r.error) || 'Das hat nicht geklappt.')); return; }
      var v = { name: r.name || '', year: r.year || '', date: r.date || '', age_show: r.age_show || 'none', avatar: r.avatar || '', favs: r.favs || {} };
      var prev = h('img', { class: 'profprev', alt: 'Dein Bild', width: '96', height: '96' }), grid = h('div', { class: 'avgrid', role: 'radiogroup', 'aria-label': 'Bild wählen' });
      function paintPrev() { var u = avUrl(v.avatar); prev.hidden = !u; if (u) prev.src = u; [].forEach.call(grid.children, function (b) { var on = b.dataset.a === v.avatar; b.setAttribute('aria-checked', on ? 'true' : 'false'); b.className = 'avopt' + (on ? ' on' : ''); }); }
      function addOpt(n, front) { var id = 'c' + n; if (grid.querySelector('[data-a="' + id + '"]')) return; var b = h('button', { class: 'avopt wish', type: 'button', role: 'radio', 'aria-label': 'Wunschbild ' + n, onclick: function () { v.avatar = v.avatar === id ? '' : id; paintPrev(); } }, h('img', { src: avUrl(id), alt: '', width: '44', height: '44' })); b.dataset.a = id; if (front) grid.prepend(b); else grid.append(b); }
      (wd.options || []).forEach(function (n) { addOpt(n); });
      for (var i = 1; i <= AVN; i++) (function (id) {
        var b = h('button', { class: 'avopt', type: 'button', role: 'radio', 'aria-label': 'Bild ' + id, onclick: function () { v.avatar = v.avatar === id ? '' : id; paintPrev(); } }, h('img', { src: avUrl(id), alt: '', width: '44', height: '44', loading: 'lazy' }));
        b.dataset.a = id; grid.append(b);
      })('s' + String(i).padStart(2, '0'));
      paintPrev();
      var picRow = h('div', null, h('div', { class: 'profpic' }, prev, grid));
      var wishBox = h('div', { class: 'wishbox' });
      function drawWish(d) {
        wishBox.textContent = ''; var ws = d.wishes || [], last = ws[0], open = ws.some(function (x) { return x.status === 'open'; });
        wishBox.append(h('p', { class: 'plab' }, 'Wunschbild'));
        if (open) { wishBox.append(h('p', { class: 'hint' }, 'Dein Wunsch ist eingegangen: „' + (ws.filter(function (x) { return x.status === 'open'; })[0].wish) + '“. Sobald Bilder bereit sind, bekommst du eine Nachricht und findest sie ganz vorn bei den Bildern.')); return; }
        if (last && last.status === 'declined') wishBox.append(h('p', { class: 'hint' }, 'Dein letzter Wunsch konnte nicht umgesetzt werden' + (last.note ? ': ' + last.note : '.')));
        if (ws.length >= 5) { wishBox.append(h('p', { class: 'hint' }, 'Du hast die möglichen Wünsche schon genutzt.')); return; }
        var ta = h('textarea', { rows: '3', maxlength: '400', 'aria-label': 'Beschreibung deines Wunschbilds', placeholder: 'z. B. Frau mit kurzen roten Haaren, Brille, grüner Pullover' }), wm = h('p', { class: 'msg', role: 'status' });
        wishBox.append(h('p', { class: 'hint' }, 'Du magst keines der Bilder? Beschreibe, wie dein Bild aussehen soll. Du bekommst 2 bis 3 Vorschläge im gleichen Stil und suchst dir eines aus.'), ta,
          h('p', { style: 'margin:8px 0 0' }, h('button', { class: 'btn ghost', type: 'button', onclick: function () { wm.className = 'msg'; wm.textContent = '';
            api('/api/data.php?a=wish_set', { wish: ta.value }).then(function (x) { if (x && x.ok) { api('/api/data.php?a=wish').then(drawWish); } else { wm.className = 'msg err'; wm.textContent = (x && x.error) || 'Das hat nicht geklappt.'; } }); } }, 'Wunsch senden')), wm);
      }
      drawWish(wd);
      var dbIn = h('input', { type: 'text', maxlength: '900', autocomplete: 'off', spellcheck: 'false', 'aria-label': 'DiceBear-Link', placeholder: 'https://api.dicebear.com/9.x/avataaars/svg?seed=Felix' }), dbMsg = h('p', { class: 'msg', role: 'status' });
      var dbBox = h('div', { class: 'wishbox' }, h('p', { class: 'plab' }, 'Bild mit DiceBear selbst gestalten'),
        h('p', { class: 'hint' }, 'DiceBear ist ein kostenloser Dienst, der Figuren und Muster als Bild erzeugt. So kommst du an deinen Link:'),
        h('ol', { class: 'hint', style: 'margin:4px 0 8px 18px;padding:0' },
          h('li', null, 'Öffne ', h('a', { href: 'https://www.dicebear.com/playground/', target: '_blank', rel: 'noopener noreferrer' }, 'dicebear.com/playground'), ' (neuer Tab).'),
          h('li', null, 'Wähle einen Stil und stelle dein Bild zusammen.'),
          h('li', null, 'Kopiere die Adresse der HTTP-API. Sie beginnt mit https://api.dicebear.com/ und endet auf /svg oder /svg? und weiteren Angaben. Links für PNG oder andere Formate gehen nicht.'),
          h('li', null, 'Füge sie hier ein und klicke auf „Bild übernehmen“.')),
        h('p', { class: 'hint' }, 'Wir laden das Bild einmal selbst von DiceBear, prüfen es und speichern es bei uns. Dein Profil verweist also nie auf eine fremde Seite. Du kannst bis zu 5 Bilder ausprobieren, das älteste ungenutzte wird ersetzt. Die Stile haben eigene Lizenzen, mehr auf ', h('a', { href: 'https://www.dicebear.com/licenses/', target: '_blank', rel: 'noopener noreferrer' }, 'dicebear.com/licenses'), '.'),
        dbIn, h('p', { style: 'margin:8px 0 0' }, h('button', { class: 'btn ghost', type: 'button', onclick: function () {
          dbMsg.className = 'msg'; dbMsg.textContent = 'Lade …';
          api('/api/data.php?a=dicebear_add', { url: dbIn.value }).then(function (x) {
            if (x && x.ok) { addOpt(x.id, true); v.avatar = 'c' + x.id; paintPrev(); dbIn.value = ''; dbMsg.className = 'msg ok'; dbMsg.textContent = 'Bild übernommen. Es ist jetzt ausgewählt, vergiss nicht zu speichern.'; }
            else { dbMsg.className = 'msg err'; dbMsg.textContent = (x && x.error) || 'Das hat nicht geklappt.'; }
          }); } }, 'Bild übernehmen')), dbMsg);
      var nm = h('input', { type: 'text', maxlength: '40', value: v.name, autocomplete: 'nickname', name: 'anzeigename', 'data-lpignore': 'true', 'data-1p-ignore': 'true', 'aria-label': 'Name', placeholder: 'z. B. dein Vorname', oninput: function () { v.name = nm.value; } });
      var yr = h('input', { type: 'number', min: '1900', max: String(new Date().getFullYear() - 10), value: v.year || '', autocomplete: 'bday-year', name: 'geburtsjahr', inputmode: 'numeric', 'data-lpignore': 'true', 'data-1p-ignore': 'true', 'aria-label': 'Geburtsjahr', placeholder: 'z. B. 1985', oninput: function () { v.year = yr.value; } });
      var dt = h('input', { type: 'date', min: '1900-01-01', max: new Date().toISOString().slice(0, 10), value: v.date, autocomplete: 'bday', name: 'geburtsdatum', 'data-lpignore': 'true', 'data-1p-ignore': 'true', 'aria-label': 'Geburtsdatum', oninput: function () { v.date = dt.value; if (v.date) { v.year = v.date.slice(0, 4); yr.value = v.year; } yr.disabled = !!v.date; } });
      yr.disabled = !!v.date;
      var sel = h('select', { 'aria-label': 'Altersanzeige', onchange: function () { v.age_show = sel.value; } }, [['none', 'nicht anzeigen'], ['age', 'als Alter anzeigen'], ['year', 'als Geburtsjahr anzeigen']].map(function (o) { return h('option', { value: o[0], selected: v.age_show === o[0] ? true : null }, o[1]); }));
      wrap.append(h('p', { class: 'plab' }, 'Dein Bild'), picRow, dbBox, wishBox,
        h('p', { class: 'plab' }, 'Name'), nm, h('p', { class: 'plab' }, 'Geburtsdatum'), dt,
        h('p', { class: 'hint' }, 'Optional. Mit Datum passt sich das Alter automatisch an. Anderen zeigen wir nie das Datum, nur Alter oder Jahr.'),
        h('p', { class: 'plab' }, 'Oder nur das Geburtsjahr'), yr,
        h('p', { class: 'hint' }, 'Nur mit Jahr springt das Alter zum Jahreswechsel, mit Datum am echten Geburtstag.'), h('p', { class: 'plab' }, 'Anzeige in der Community'), sel);
      var gcols = h('div', { class: 'gcols' });
      ['author', 'artist', 'film', 'series'].forEach(function (t) {
        var chosen = (v.favs[t] = (v.favs[t] || []).slice(0, 5)), box = h('div', { class: 'gchips pick', role: 'group', 'aria-label': 'Lieblingsgenres ' + TYPES[t].plural }), cap = h('span', { class: 'hint', style: 'margin-left:6px' });
        function draw() { box.textContent = ''; cap.textContent = chosen.length + ' von 5';
          genOpts(t).concat(chosen.filter(function (g) { return genOpts(t).indexOf(g) < 0; })).forEach(function (g) { var on = chosen.indexOf(g) > -1;
            box.append(h('button', { class: 'gchip pickbtn', type: 'button', 'aria-pressed': String(on), disabled: !on && chosen.length >= 5 ? true : null, onclick: function () { if (on) chosen.splice(chosen.indexOf(g), 1); else if (chosen.length < 5) chosen.push(g); draw(); } }, g)); }); }
        draw(); gcols.append(h('div', { class: 'gcol' }, h('p', { class: 'plab' }, TYPES[t].plural, cap), box));
      });
      wrap.append(h('p', { class: 'plab' }, 'Lieblingsgenres (bis zu 5 je Kategorie)'), gcols);
      var cu = (v.favs.custom = (v.favs.custom || []).filter(function (x) { return x && typeof x === 'object'; }).slice(0, 5)), crow = h('div', { class: 'custg' });
      [0, 1, 2, 3, 4].forEach(function (i) {
        var cur = cu[i] || { t: 'artist', g: '' }; cu[i] = cur;
        var sl = h('select', { 'aria-label': 'Kategorie von Genre ' + (i + 1), onchange: function () { cur.t = sl.value; } }, ['author', 'artist', 'film', 'series'].map(function (t) { return h('option', { value: t, selected: cur.t === t ? true : null }, TYPES[t].plural); }));
        var ti = h('input', { type: 'text', maxlength: '24', value: cur.g || '', autocomplete: 'off', autocapitalize: 'off', spellcheck: 'false', name: 'eigenes-genre-' + (i + 1), 'aria-label': 'Eigenes Genre ' + (i + 1), placeholder: i === 1 ? 'z. B. Neoklassik' : (i === 0 ? 'z. B. Cozy Crime' : ''), oninput: function () { cur.g = ti.value; } });
        crow.append(h('div', { class: 'inline' }, sl, ti));
      });
      wrap.append(h('p', { class: 'plab' }, 'Eigene Genres (bis zu 5)'), h('p', { class: 'hint' }, 'Wähle die Kategorie und trag dein Genre ein. Es erscheint nur auf deiner Profilkarte in der Community, nicht in den Genre-Filtern.'), crow);
      function doSave(m) {
        m.className = 'msg'; m.textContent = '';
        api('/api/data.php?a=profile_set', { name: v.name, year: v.year, date: v.date, age_show: v.age_show, avatar: v.avatar, favs: (function () { var f = Object.assign({}, v.favs); f.custom = (v.favs.custom || []).filter(function (c) { return c && c.g && c.g.trim(); }); return f; })() }).then(function (x) { if (x && x.ok) { m.className = 'msg ok'; m.textContent = 'Gespeichert.'; } else { m.className = 'msg err'; m.textContent = (x && x.error) || 'Das hat nicht geklappt.'; } });
      }
      var save = h('button', { class: 'btn', type: 'button', onclick: function () { doSave(msg); } }, 'Profilkarte speichern');
      var msgTop = h('p', { class: 'msg', role: 'status' }), saveTop = h('button', { class: 'btn', type: 'button', onclick: function () { doSave(msgTop); } }, 'Profilkarte speichern');
      picRow.append(h('p', { style: 'margin:10px 0 0' }, saveTop), msgTop);
      wrap.append(h('p', { style: 'margin:14px 0 0' }, save), msg);
    });
    return wrap;
  }
  function idCard(d) {
    var c = d.card || {}, name = d.user.name, ini = (name || '?').charAt(0).toUpperCase(), u = avUrl(c.avatar);
    var pic = h('div', { class: 'idpic' }, u ? h('img', { src: u, alt: 'Bild von ' + name, width: '120', height: '120' }) : h('span', { class: 'idini', 'aria-hidden': 'true' }, ini));
    var info = h('div', { class: 'idinfo' }, h('div', { class: 'idname' }, name));
    if (c.name) info.append(h('div', { class: 'idrow' }, h('span', null, 'Name'), h('b', null, c.name)));
    if (c.age) info.append(h('div', { class: 'idrow' }, h('span', null, 'Alter'), h('b', null, c.age)));
    var gl = h('div', { class: 'idgen' }), any = false, favs = c.favs || {};
    var cust = (favs.custom || []).filter(function (x) { return x && x.t && x.g; });
    ['artist', 'film', 'series', 'author'].forEach(function (t) { var l = favs[t] || [], own = cust.filter(function (x) { return x.t === t; }); if (!l.length && !own.length) return; any = true;
      gl.append(h('div', { class: 'idgrow' }, h('span', { class: 'idcat', style: '--cc:' + CATCOL[t] }, TYPES[t].plural), h('span', { class: 'gchips' }, l.map(function (g) { return h('span', { class: 'gchip' }, g); }).concat(own.map(function (x) { return h('span', { class: 'gchip own' }, x.g); }))))); });
    if (!c.name && !c.age && !any) info.append(h('p', { class: 'hint', style: 'margin:6px 0 0' }, 'Noch keine Angaben.'));
    return h('section', { class: 'idcard', 'aria-label': 'Profilkarte von ' + name }, h('div', { class: 'idstripe' }, h('span', null, 'Schondraussen.de'), h('span', null, 'Mitglied Nr. ' + (d.locked ? '…' : (c.no || d.user.id)))), h('div', { class: 'idbody' }, pic, info), any ? gl : null);
  }
  function emailBox() {
    var wrap = h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'E-Mail-Adresse'));
    function draw() {
      wrap.textContent = ''; wrap.append(h('p', { class: 'label' }, 'E-Mail-Adresse'));
      var ne = h('input', { type: 'email', maxlength: '190', placeholder: 'Neue E-Mail-Adresse', 'aria-label': 'Neue E-Mail-Adresse', autocomplete: 'off', name: 'neue-adresse', readonly: 'readonly', onfocus: function () { this.removeAttribute('readonly'); }, style: 'flex:1;min-width:200px' }), npw = h('input', { type: 'password', maxlength: '72', placeholder: 'Dein Passwort', 'aria-label': 'Passwort zur Bestätigung', autocomplete: 'current-password', style: 'flex:1;min-width:160px' }), m1 = h('p', { class: 'msg', role: 'status' });
      wrap.append(h('p', { class: 'hint' }, D.email ? ['Deine Adresse: ', hiddenMail(D.email), '. Eine neue Adresse gilt erst, wenn du den Link in der Bestätigungsmail anklickst. Bis dahin bleibt die alte aktiv.'] : 'Dein Konto hat keine E-Mail-Adresse. Du kannst eine hinzufügen, zum Beispiel für Benachrichtigungen und zum Zurücksetzen des Passworts per Mail.'),
        h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); m1.className = 'msg'; m1.textContent = '…';
          api('/api/auth.php?a=email_change', { email: ne.value, password: npw.value }).then(function (r) { if (r.ok) { npw.value = ''; ne.value = ''; m1.textContent = r.message; } else { m1.className = 'msg err'; m1.textContent = r.error || 'Das hat nicht geklappt.'; } }); } },
          ne, npw, h('button', { class: 'btn', type: 'submit' }, D.email ? 'Adresse ändern' : 'Adresse hinzufügen')), m1);
      if (!D.email) return;
      var rp = h('input', { type: 'password', maxlength: '72', placeholder: 'Dein Passwort', 'aria-label': 'Passwort zum Entfernen', autocomplete: 'current-password', style: 'flex:1;min-width:160px' }), ck = h('input', { type: 'checkbox', id: 'rmck' }), m2 = h('p', { class: 'msg', role: 'status' }), out = h('div');
      var danger = h('div', { class: 'dangerbox', style: 'margin-top:18px;padding:14px;border:1px solid var(--err,#a23f1c);border-radius:12px' },
        h('p', { class: 'label', style: 'margin:0 0 4px;color:var(--err,#a23f1c)' }, 'E-Mail-Adresse entfernen'),
        h('p', { class: 'hint' }, 'Achtung: Ohne Adresse bekommst du keine Mails mehr und kannst dein Passwort nicht mehr per E-Mail zurücksetzen. Du meldest dich dann nur noch mit Benutzername und Passwort an. Vergisst du das Passwort, hilft dir ausschließlich dein Wiederherstellungscode. Beim Entfernen erzeugen wir einen neuen Code, der alte wird ungültig. Er wird nur einmal angezeigt, notiere ihn dir an einem sicheren Ort. Ohne Code und ohne Passwort kommt niemand mehr an dein Konto, auch der Admin nicht.'),
        h('p', { style: 'margin:8px 0' }, h('label', { for: 'rmck', style: 'display:flex;gap:8px;align-items:flex-start' }, ck, h('span', null, 'Ich habe verstanden, dass mein Konto ohne E-Mail-Adresse nur mit dem neuen Code wiederherstellbar ist.'))),
        h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); m2.className = 'msg'; m2.textContent = '…';
          if (!ck.checked) { m2.className = 'msg err'; m2.textContent = 'Bitte bestätige den Hinweis.'; return; }
          if (!confirm('Die E-Mail-Adresse wirklich entfernen? Das lässt sich nur rückgängig machen, indem du eine neue Adresse hinzufügst.')) { m2.textContent = ''; return; }
          api('/api/auth.php?a=email_remove', { password: rp.value, understood: true }).then(function (r) {
            if (!r.ok) { m2.className = 'msg err'; m2.textContent = r.error || 'Das hat nicht geklappt.'; return; }
            D.email = null; wrap.textContent = ''; wrap.append(h('p', { class: 'label' }, 'E-Mail-Adresse'), h('p', null, 'Deine E-Mail-Adresse wurde entfernt. Dein neuer Wiederherstellungscode (nur jetzt sichtbar, bitte notieren):'), h('p', { class: 'codebox', style: 'font:700 22px/1.4 monospace;letter-spacing:.08em;margin:8px 0' }, r.recovery_code),
              h('button', { class: 'btn', type: 'button', onclick: function () { render(); } }, 'Ich habe den Code notiert')); }); } },
          rp, h('button', { class: 'btn danger', type: 'submit' }, 'Adresse entfernen')), m2);
      wrap.append(danger);
    }
    draw(); return wrap;
  }
  (function () { try { var qq = new URLSearchParams(location.search), mo = qq.get('mailok'), vf = qq.get('verified'), k = mo !== null ? mo : vf; if (k === '1' || k === '0') { history.replaceState(null, '', location.pathname); setTimeout(function () { toast(k === '1' ? (mo !== null ? 'Deine neue E-Mail-Adresse ist bestätigt.' : 'Deine E-Mail-Adresse ist bestätigt.') : 'Der Bestätigungslink ist abgelaufen oder ungültig.'); }, 600); } } catch (_) {} })();
  function settings() {
    var p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Einstellungen'));
    var box = h('div', { class: 'cfgpanel' });
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Angemeldet als'), h('p', { style: 'margin:0' }, D.email ? [D.username ? D.username + ' · ' : '', hiddenMail(D.email)] : (D.username ? D.username + ' (ohne E-Mail-Adresse)' : 'ohne E-Mail-Adresse'), ' · Nr. ' + D.id + (isAdm() ? ' · Admin' : '') + (!D.admin && D.alpha ? ' · Alphatester' : ''))));
    var un = h('input', { type: 'text', value: D.username || '', maxlength: '20', 'aria-label': 'Benutzername', autocomplete: 'off' }), unMsg = h('p', { class: 'msg', role: 'status' }), unLeft = unFree() ? 0 : (+D.uname_left || 0);
    if (unLeft) un.disabled = true;
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Benutzername'),
      h('p', { class: 'hint' }, D.username ? 'Diesen Namen sehen andere Nutzer. Zur Anmeldung brauchst du ihn nicht. Du kannst ihn alle 30 Tage ändern. Erlaubt sind 3 bis 20 Zeichen: Buchstaben (auch Umlaute), Ziffern, Punkt, Unterstrich und Bindestrich, ohne Leerzeichen und nicht am Anfang oder Ende.' : 'Du hast noch keinen Benutzernamen. Wähle einen, den andere Nutzer sehen dürfen (nicht für die Anmeldung). Erlaubt sind 3 bis 20 Zeichen: Buchstaben (auch Umlaute), Ziffern, Punkt, Unterstrich und Bindestrich, ohne Leerzeichen und nicht am Anfang oder Ende.'),
      unLeft ? h('p', { class: 'hint' }, 'Änderung wieder möglich in ' + pl(unLeft, 'Tag', 'Tagen') + '.') : null,
      unFree() ? h('p', { class: 'hint' }, 'Falls du dich verschrieben hast: Du kannst den Namen jetzt noch ' + pl(unFree(), 'Minute', 'Minuten') + ' lang einmal ohne die 30-Tage-Sperre ändern.') : null,
      h('div', { class: 'inline', style: 'margin-top:12px' }, un, h('button', { class: 'btn', type: 'button', disabled: unLeft > 0, onclick: function () { api('/api/auth.php?a=username', { username: un.value }).then(function (r) { if (r.ok) { D.username = r.username; D.uname_left = 30; D.uname_free_until = 0; unMsg.className = 'msg'; unMsg.textContent = 'Gespeichert.'; var w = $('who'); if (w) { var t = w.lastChild; if (t && t.nodeType === 3) t.textContent = r.username + ' · ' + (D.email || 'ohne E-Mail'); } render(); } else { unMsg.className = 'msg err'; unMsg.textContent = r.error || 'Das hat nicht geklappt.'; } }); } }, 'Speichern')), unMsg));
    var rcMsg = h('p', { class: 'msg', role: 'status' }), rcOut = h('p', { style: 'margin:8px 0 0' }), rcPw = h('input', { type: 'password', maxlength: '72', placeholder: 'Dein Passwort', 'aria-label': 'Passwort zur Bestätigung', autocomplete: 'current-password', style: 'flex:1;min-width:180px' });
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Wiederherstellungscode'),
      h('p', { class: 'hint' }, D.email ? 'Optional: Mit einem Code kannst du dein Passwort auch ohne E-Mail zurücksetzen. Ein neuer Code macht den alten ungültig. Er wird nur einmal angezeigt.' : 'Damit setzt du dein Passwort zurück, falls du es vergisst (auf der Anmeldeseite unter „Passwort vergessen“). Ein neuer Code macht den alten ungültig. Er wird nur einmal angezeigt, notiere ihn dir.'),
      h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); rcMsg.className = 'msg'; rcMsg.textContent = '…'; rcOut.textContent = ''; api('/api/auth.php?a=recovery_new', { password: rcPw.value }).then(function (r) { if (!r.ok) { rcMsg.className = 'msg err'; rcMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; } rcPw.value = ''; rcMsg.textContent = 'Neuer Code (jetzt notieren, er wird nicht noch einmal gezeigt):'; rcOut.append(h('code', { style: 'font:600 18px ui-monospace,Menlo,Consolas,monospace;letter-spacing:.08em;user-select:all' }, r.recovery_code)); }); } }, rcPw, h('button', { class: 'btn small', type: 'submit' }, 'Neuen Code erzeugen')), rcMsg, rcOut));
    var skins = h('div', { class: 'skins', role: 'group', 'aria-label': 'Oberfläche wählen' });
    SKINS.forEach(function (k) { skins.append(h('button', { class: 'skin', type: 'button', title: k[0] ? null : 'Folgt der Hell-Dunkel-Einstellung deines Geräts', 'aria-pressed': String((S.skin || '') === k[0]), onclick: function () { setSkin(k[0]); api('/api/data.php?a=skin', { skin: k[0] }); render(); } }, h('span', { class: 'dots' }, k[2].map(function (c) { var i = h('i'); i.style.background = c; return i; })), h('b', null, k[1]))); });
    var nerdMsg = h('span', { class: 'msg', style: 'margin:0' }), nerdCk = h('input', { type: 'checkbox', onchange: function () { var on = this.checked; api('/api/auth.php?a=nerd', { on: on }).then(function (r) { if (r.ok) { D.nerd = on; nerdMsg.className = 'msg'; nerdMsg.textContent = 'Gespeichert.'; renderVer(); } else { nerdCk.checked = !on; nerdMsg.className = 'msg err'; nerdMsg.textContent = r.error || 'Speichern nicht möglich.'; } }); } }); nerdCk.checked = !!D.nerd;
    var hcBox = null;
    if (D.hidecats) { var hcMsg = h('span', { class: 'msg', style: 'margin:0' }), hcRow = h('div', { style: 'display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:6px' });
      ORDER.forEach(function (t) { var ck = h('input', { type: 'checkbox', checked: !isHid(t), onchange: function () { var hide = []; ORDER.forEach(function (x) { if ((x === t ? !ck.checked : isHid(x))) hide.push(x); }); if (hide.length >= ORDER.length) { ck.checked = true; hcMsg.className = 'msg err'; hcMsg.textContent = 'Mindestens eine Kategorie muss sichtbar bleiben.'; return; } api('/api/data.php?a=hidecats', { cats: hide }).then(function (r) { if (!r.ok) { ck.checked = !ck.checked; hcMsg.className = 'msg err'; hcMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; } D.hide = hide; hcMsg.className = 'msg'; hcMsg.textContent = 'Gespeichert.'; load(function () { nav(); }); }); } }); hcRow.append(h('label', { style: 'display:flex;gap:6px;align-items:center' }, ck, TYPES[t].plural)); });
      hcBox = h('div', { style: 'margin-top:14px' }, h('p', { class: 'label' }, 'Kategorien anzeigen'), h('p', { class: 'hint', style: 'margin:0' }, 'Nicht angezeigte Kategorien verschwinden aus Menü, Kalender und Übersicht. Deine Einträge bleiben erhalten, Benachrichtigungen kommen weiterhin, und in der Community siehst du die Kategorien weiterhin.'), hcRow, hcMsg); }
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Farbschema'), skins));
    var uiBox = h('div', { class: 'wide2' }, hcBox,
      h('div', { style: hcBox ? 'margin-top:14px' : '' }, h('label', { style: 'display:flex;gap:8px;align-items:center' }, nerdCk, 'Nerdoption: Genauere Info, was seit deinem letzten Besuch geändert wurde, und Nerdinfos zur Technik'), h('p', { class: 'hint', style: 'margin:2px 0 0 26px' }, 'Zeigt an der Versionsnummer, wie viele Änderungen neu sind, und im Versionsverlauf oben die neuen Einträge. Auf der Info-Seite erscheint oben der zusätzliche Reiter „Nerdinfos“ (Technik, Datenquellen, Dienste).'), nerdMsg));
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
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'E-Mail-Benachrichtigungen'), D.email ? null : h('p', { class: 'warnbox', style: 'margin:0 0 10px' }, 'Dein Konto hat keine E-Mail-Adresse, deshalb bekommst du keine Benachrichtigungen per Mail. Den Kalender und die Einträge siehst du weiter hier auf der Seite.'), h('p', { class: 'hint', style: 'margin-bottom:10px' }, 'Du bekommst Mails nur zu Einträgen, bei denen die Glocke an ist (sie sitzt im Kopf jedes Eintrags). Jede Mail enthält einen Abmeldelink.'),
      h('label', { style: 'display:flex;gap:8px;align-items:center' }, cb, 'E-Mail-Benachrichtigungen einschalten'), opts,
      h('p', { style: 'margin:14px 0 0' }, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { tm.className = 'msg'; tm.textContent = 'Sende …'; api('/api/data.php?a=testmail', {}).then(function (r) { tm.className = 'msg' + (r.ok ? '' : ' err'); tm.textContent = r.msg || r.error || ''; }); } }, 'Vorschau-Mail an mich senden'), ' ', tm)));
    var imp = h('div', { class: 'wide2' }), impBtns = h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }), impPanels = h('div'); imp.append(impBtns, impPanels);
    function splitCsv(l, dl) { var o = [], cur = '', q = false; for (var i = 0; i < l.length; i++) { var ch = l[i]; if (ch === '"') { if (q && l[i + 1] === '"') { cur += '"'; i++; } else q = !q; } else if (ch === dl && !q) { o.push(cur); cur = ''; } else cur += ch; } o.push(cur); return o; }
    function unwrapRow(l) { return (l.length > 1 && l[0] === '"' && l[l.length - 1] === '"' && l.indexOf('""') > -1) ? l.slice(1, -1).replace(/""/g, '"') : l; }
    function imdbFrom(text) { // IMDb-Export (Listen/Bewertungen als CSV): Titel samt Art (Film oder Serie), Folgen und Spiele werden übersprungen
      var ls = text.replace(/^﻿/, '').split(/\r?\n/).filter(function (x) { return x.trim(); }); if (ls.length < 2) return null;
      var hd = splitCsv(unwrapRow(ls[0]), ',').map(function (x) { return x.trim().toLowerCase(); }), ti = hd.indexOf('title'), ty = hd.indexOf('title type'); if (ti < 0 || ty < 0) return null;
      var items = [], skipped = 0;
      ls.slice(1).forEach(function (l) {
        var c = splitCsv(unwrapRow(l), ','), n = (c[ti] || '').trim(), t = (c[ty] || '').toLowerCase(); if (!n) return;
        if (/episode|folge|spiel|game/.test(t)) { skipped++; return; }
        items.push({ n: n, k: /serie|series/.test(t) ? 'series' : 'film' });
      });
      return { items: items, skipped: skipped };
    }
    function namesFrom(text, fname) {
      var out = [];
      function add(v) { if (typeof v === 'string' && v.trim()) out.push(v.trim()); }
      var t = text.replace(/^\ufeff/, '');
      if (/\.json$/i.test(fname) || /^\s*[\[{]/.test(t)) { try { (function walk(o) { if (Array.isArray(o)) o.forEach(function (x) { if (typeof x === 'string') add(x); else walk(x); }); else if (o && typeof o === 'object') { var hit = false, hasArr = Object.keys(o).some(function (k) { return Array.isArray(o[k]); }); ['artistName', 'artist_name', 'artist', 'name', 'Artist Name(s)'].forEach(function (k) { if (k === 'name' && hasArr) return; if (!hit && typeof o[k] === 'string') { add(o[k]); hit = true; } else if (!hit && o[k] && typeof o[k] === 'object' && typeof o[k].name === 'string') { add(o[k].name); hit = true; } }); if (!hit) Object.keys(o).forEach(function (k) { walk(o[k]); }); } })(JSON.parse(t)); return out; } catch (_) {} }
      var lines = t.split(/\r?\n/).filter(function (l) { return l.trim(); });
      if (/\.csv$/i.test(fname) || (lines[0] && lines[0].indexOf(',') > -1 && /artist|name|interpret/i.test(lines[0]))) {
        var dl = lines[0].indexOf(',') > -1 ? ',' : (lines[0].indexOf('\t') > -1 ? '\t' : ';'), hd = lines[0].split(dl).map(function (x) { return x.replace(/^"|"$/g, '').toLowerCase(); }), ci = 0; hd.forEach(function (x, i) { if (/artist|interpret|name/.test(x) && !ci && i >= 0 && ci === 0 && /artist|interpret/.test(x)) ci = i; });
        if (!ci) hd.forEach(function (x, i) { if (!ci && x === 'name') ci = i; });
        lines.slice(1).forEach(function (l) { var c = splitCsv(unwrapRow(l), dl)[ci] || ''; c = c.replace(/^"|"$/g, ''); c.split(/;\s*/).forEach(add); });
        return out;
      }
      lines.forEach(add); return out;
    }
    var IMPCATS = [['author', 'Bücher', 'Titel', 'Titel von Büchern', 'Der Name der Reihe\nDie Känguru-Chroniken'], ['artist', 'Musik', 'Musiker:in', 'Namen von Musiker:innen', 'Radiohead\nBlind Guardian\nNightwish'], ['film', 'Filme', 'Film', 'Titel von Filmen', 'Der Pate\nAlien'], ['series', 'Serien', 'Serie', 'Titel von Serien', 'Dark\nBreaking Bad']];
    function mkImporter(label, fixed, hintFn, cls) {
      var ifile = h('input', { type: 'file', accept: '.json,.csv,.txt,text/plain,application/json,text/csv', 'aria-label': 'Datei laden' }), itext = h('textarea', { rows: '6', 'aria-label': 'Namen, einer pro Zeile', style: 'width:100%;margin-top:8px' }), imsg = h('span', { class: 'msg', style: 'margin:0' }), ipanel = h('div', { hidden: true, style: 'margin-top:10px' }), sel = null, hint = h('p', { class: 'hint' });
      var kindOf = {};
      function cat() { var v = fixed || (sel ? sel.value : 'artist'); return IMPCATS.filter(function (x) { return x[0] === v; })[0]; }
      function refresh() { var c = cat(); hint.textContent = hintFn(c); itext.placeholder = c[4]; }
      if (!fixed) { sel = h('select', { 'aria-label': 'Kategorie', onchange: refresh }); IMPCATS.forEach(function (x) { sel.append(h('option', { value: x[0] }, x[1])); }); sel.value = 'artist'; }
      ifile.addEventListener('change', function () {
        var f = ifile.files && ifile.files[0]; if (!f) return;
        if (f.size > 5 * 1024 * 1024) { imsg.className = 'msg err'; imsg.textContent = 'Die Datei ist größer als 5 MB.'; return; }
        var rd = new FileReader(); rd.onload = function () {
          var im = fixed ? null : imdbFrom(String(rd.result || '')); kindOf = {};
          if (im) { var nm = [], sn = {}, nF = 0, nS = 0; im.items.forEach(function (it) { var k = it.n.toLowerCase(); if (sn[k]) return; sn[k] = 1; kindOf[k] = it.k; nm.push(it.n); if (it.k === 'series') nS++; else nF++; }); itext.value = nm.join('\n'); imsg.className = 'msg'; imsg.textContent = nm.length ? (pl(nF, 'Film', 'Filme') + ' und ' + pl(nS, 'Serie', 'Serien') + ' erkannt' + (im.skipped ? ', ' + im.skipped + ' übersprungen (Folgen, Spiele)' : '') + '. Filme und Serien kommen beim Importieren automatisch in die richtige Liste. Du kannst sie unten noch ändern.') : 'In der Datei habe ich keine Titel gefunden.'; return; }
          var l = namesFrom(String(rd.result || ''), f.name), seen = {}; l = l.filter(function (x) { var k = x.toLowerCase(); if (seen[k]) return false; seen[k] = 1; return true; }); itext.value = l.join('\n'); imsg.className = 'msg'; imsg.textContent = l.length ? ((l.length === 1 ? '1 Name erkannt. Du kannst ihn unten noch ändern.' : l.length + ' Namen erkannt. Du kannst sie unten noch ändern.')) : 'In der Datei habe ich keine Namen gefunden.'; };
        rd.onerror = function () { imsg.className = 'msg err'; imsg.textContent = 'Die Datei konnte nicht gelesen werden.'; }; rd.readAsText(f);
      });
      var kids = [hint];
      if (sel) kids.push(h('p', { style: 'margin:8px 0' }, h('label', { style: 'display:flex;gap:8px;align-items:center' }, 'Kategorie', sel)));
      kids.push(ifile, itext, h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' }, h('button', { class: 'btn', type: 'button', onclick: function () {
        var l = itext.value.split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean); if (!l.length) { imsg.className = 'msg err'; imsg.textContent = 'Keine Namen gefunden.'; return; }
        var c = cat(); imsg.className = 'msg'; imsg.textContent = 'Importiere …';
        var groups = {}, order = []; l.forEach(function (x) { var k = kindOf[x.toLowerCase()] || c[0]; if (!groups[k]) { groups[k] = []; order.push(k); } groups[k].push(x); });
        var parts = [], failed = null;
        function runGroup(i) {
          if (i >= order.length) { imsg.className = 'msg' + (failed ? ' err' : ''); imsg.textContent = failed || (parts.join(' ') || 'Nichts hinzugefügt.'); if (!failed) itext.value = ''; load(function () { nav(); }); return; }
          var k = order[i], cc = IMPCATS.filter(function (x) { return x[0] === k; })[0];
          api('/api/data.php?a=import', { names: groups[k], type: k }).then(function (r) {
            if (!r.ok) { failed = (parts.length ? parts.join(' ') + ' ' : '') + (cc[1] + ': ' + (r.msg || r.error || 'Das hat nicht geklappt.')); runGroup(order.length); return; }
            parts.push(cc[1] + ': ' + r.added + ' hinzugefügt' + (r.had ? ', ' + r.had + ' schon auf der Liste' : '') + (r.over ? ', ' + r.over + ' nicht mehr möglich (Grenze 1.000 pro Liste)' : '') + '.'); runGroup(i + 1);
          });
        }
        runGroup(0);
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
        tMsg.className = 'msg'; tMsg.textContent = 'Erkannt: ' + pl(ns, 'Serie', 'Serien') + ', ' + pl(nf, 'Film', 'Filme') + (ne ? ' und ' + pl(ne, 'gesehene Folge', 'gesehene Folgen') : '') + '. Pro Liste passen höchstens 1.000 Einträge, der Rest wird übersprungen.'; tGo.hidden = false;
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
    var calCatBox = h('div', { style: 'margin-top:12px' }), calCatMsg = h('span', { class: 'msg', style: 'margin:0' });
    function calCatDraw(sel) { // Kategorien des Abos: eigene Auswahl, unabhängig vom Ausblenden in der Oberfläche
      calCatBox.textContent = ''; var cur = sel && sel.length ? sel.slice() : ORDER.slice(), row = h('div', { style: 'display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:6px' });
      ORDER.forEach(function (t) { var ck = h('input', { type: 'checkbox', checked: cur.indexOf(t) >= 0, onchange: function () { var n = ORDER.filter(function (x) { return x === t ? ck.checked : cur.indexOf(x) >= 0; }); if (!n.length) { ck.checked = true; calCatMsg.className = 'msg err'; calCatMsg.textContent = 'Mindestens eine Kategorie muss ausgewählt bleiben.'; return; }
        api('/api/data.php?a=calcats', { cats: n }).then(function (r) { if (!r.ok) { ck.checked = !ck.checked; calCatMsg.className = 'msg err'; calCatMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; } cur = n; calCatMsg.className = 'msg'; calCatMsg.textContent = 'Gespeichert.'; }); } }); row.append(h('label', { style: 'display:flex;gap:6px;align-items:center' }, ck, TYPES[t].plural)); });
      calCatBox.append(h('p', { class: 'label', style: 'margin-bottom:0' }, 'Kategorien im Kalender-Abo'), h('p', { class: 'hint', style: 'margin:0' }, 'Nur diese Kategorien erscheinen im Kalender-Abo, zum Beispiel wenn dich auf dem Handy nur ein Teil interessiert. Diese Auswahl gilt nur für das Abo und ist unabhängig vom Ausblenden unter „Oberflächenanpassungen“.'), row, calCatMsg);
    }
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Kalender-Abo'),
      h('p', { class: 'hint' }, 'Mit diesem Link zeigt dein Kalender (Google, Apple, Outlook, Thunderbird) alle angekündigten Termine deiner Einträge und aktualisiert sich selbst. Der Link ist privat: Wer ihn kennt, sieht deine Termine, gib ihn also nicht weiter. Termine ohne genaues Datum erscheinen nicht. Je nach Kalender-App dauert die Aktualisierung einige Stunden.'),
      calUrl, calBtns, calMsg, calCatBox));
    calUrl.hidden = true; api('/api/data.php?a=calinfo').then(function (r) { if (r.error) { calMsg.className = 'msg err'; calMsg.textContent = r.error; return; } calDraw(r.url); calCatDraw(r.cats); });
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
    var cmMsg = h('span', { class: 'msg', style: 'margin:0' }), cmCk = h('input', { type: 'checkbox', onchange: function () { var on = this.checked; api('/api/auth.php?a=community', { on: on }).then(function (r) { if (r.ok) { D.community = on; D.community_asked = true; if (S.comm) S.comm.data = null; cmMsg.className = 'msg'; cmMsg.textContent = 'Gespeichert.'; } else { cmCk.checked = !on; cmMsg.className = 'msg err'; cmMsg.textContent = r.error || 'Speichern nicht möglich.'; } }); } }); cmCk.checked = !!D.community;
    box.append(h('div', { class: 'wide2' }, h('label', { style: 'display:flex;gap:8px;align-items:center' }, cmCk, 'Meine Einträge für die Community nutzen'), h('p', { class: 'hint', style: 'margin:2px 0 0 26px' }, 'Wenn du zustimmst, erscheinst du mit Benutzernamen bei den aktivsten Nutzern der Community-Seite, und andere Teilnehmende können deine Listen und deine zuletzt hinzugefügten Einträge sehen und Einträge daraus übernehmen. Ohne Zustimmung bleibt deine Liste für andere unsichtbar. Du kannst das jederzeit ändern.'), cmMsg));
    box.append(adminSees);
    box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Meine Daten'),
      h('p', { class: 'hint' }, 'Lade alles herunter, was zu dir gespeichert ist: Konto, Einstellungen, Liste, Nachrichten sowie deine Ideen und Bugs (hochgeladene Bilder sind nicht enthalten). Die Liste gibt es auch als CSV-Tabelle, die du zum Beispiel in einer Tabellenkalkulation oder später für einen Import nutzen kannst.'),
      h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' },
        h('a', { class: 'btn ghost small', href: '/api/data.php?a=export&fmt=json', download: '' }, 'Alle Daten (JSON)'),
        h('a', { class: 'btn ghost small', href: '/api/data.php?a=export&fmt=csv', download: '' }, 'Liste (CSV)'))));
    if (!isAdm()) {
      var dm = h('span', { class: 'msg', style: 'margin:0' }), pw = h('input', { type: 'password', autocomplete: 'current-password', placeholder: 'Passwort zur Bestätigung', style: 'max-width:260px' }), dbox = h('div', { hidden: true, style: 'margin-top:10px' });
      var EXIT_R = [['no_need', 'Ich brauche die Seite nicht mehr'], ['test_only', 'Ich wollte nur mal reinschauen'], ['missing_feature', 'Mir fehlen Funktionen'], ['missing_content', 'Ich finde nicht, was ich suche (Titel, Künstler, Autoren)'], ['bugs', 'Fehler oder unzuverlässige Daten'], ['complicated', 'Zu kompliziert oder unübersichtlich'], ['other_tool', 'Ich nutze ein anderes Angebot'], ['mails', 'Zu viele oder unpassende Mails'], ['privacy', 'Bedenken beim Datenschutz'], ['other', 'Etwas anderes']], exitReason = '';
      var exitTxt = h('textarea', { maxlength: '2000', rows: '4', 'aria-label': 'Was können wir besser machen? (freiwillig)', placeholder: 'Was hätte ich besser machen können? (freiwillig)', style: 'width:100%;max-width:560px;box-sizing:border-box' }), exitCnt = h('span', { class: 'hint', style: 'margin-left:8px' }, '0 / 2000');
      exitTxt.addEventListener('input', function () { exitCnt.textContent = exitTxt.value.length + ' / 2000'; });
      var exitBox = h('fieldset', { style: 'border:0;padding:0;margin:12px 0 0;min-width:0' }, h('legend', { class: 'label', style: 'padding:0' }, 'Darf ich fragen, warum du gehst? (freiwillig)'),
        h('p', { class: 'hint', style: 'margin:2px 0 6px' }, 'Du kannst das Konto auch ohne Angabe löschen. Deine Antwort wird anonym gespeichert und ohne deinen Namen an mich weitergegeben.'),
        h('div', { style: 'display:grid;gap:4px' }, EXIT_R.map(function (x) { return h('label', { style: 'display:flex;gap:8px;align-items:flex-start' }, h('input', { type: 'radio', name: 'exitwhy', value: x[0], onchange: function () { exitReason = x[0]; } }), x[1]); })),
        h('p', { style: 'margin:8px 0 0' }, exitTxt, exitCnt));
      dbox.append(h('p', { class: 'hint' }, 'Dein Konto wird zur Löschung vorgemerkt und du wirst abgemeldet. Deine Daten (Konto, Listen, Einstellungen, Nachrichten) bleiben noch eine Woche gespeichert, falls du es dir anders überlegst: Melde dich einfach wieder an und brich die Löschung ab. Danach werden sie endgültig gelöscht.'), exitBox, h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0 0' }, pw,
        h('button', { class: 'btn warn small', type: 'button', onclick: function () { dm.className = 'msg'; dm.textContent = ''; api('/api/auth.php?a=delete_account', { password: pw.value, reason: exitReason, text: exitTxt.value }).then(function (r) { if (r && r.ok) location.reload(); else { dm.className = 'msg err'; dm.textContent = (r && r.error) || 'Das hat nicht geklappt.'; } }); } }, 'Konto jetzt löschen'), dm));
      box.append(h('div', { class: 'wide2' }, h('p', { class: 'label' }, 'Konto löschen'), h('p', { class: 'hint' }, 'Du kannst dein Konto samt aller Einträge jederzeit löschen lassen.'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { dbox.hidden = !dbox.hidden; if (!dbox.hidden) pw.focus(); } }, 'Konto löschen …'), dbox));
    }
    var K = [].slice.call(box.children), st = h('div', { class: 'setstack' });
    // Reihenfolge der Bausteine oben: 0 Angemeldet, 1 Benutzername, 2 Wiederherstellungscode, 3 Farbschema, 4 Benachrichtigungen, 5 Import, 6 Kalender-Abo, 7 Zwei-Faktor, 8 Privatsphäre, 9 Community-Zustimmung, 10 Was der Admin sieht, 11 Meine Daten, 12 Konto löschen (nicht für Admin); uiBox = Oberflächenanpassungen (nicht für Admin)
    var optikC = null;
    st.append(setCard('Meine Daten', [K[9], K[11]], 'solo'), setCard('Benutzername', [K[1]], 'solo'), setCard('E-Mail und Wiederherstellung', [emailBox(), K[2]], 'solo'), setCard('Profilkarte', [profileBox()], 'solo'), (optikC = setCard('Farbschemata', [K[3]], 'solo')), setCard('Kalender-Abo', [K[6]], 'solo'), setCard('E-Mail-Benachrichtigungen', [K[4]], 'solo'), setCard('Import', [K[5]], 'solo'), setCard('Zwei-Faktor-Anmeldung', [K[7]], 'solo'));
    if (K[12]) K[12].classList.add('dangerzone');
    if ((D.pubdesigns || []).length) (function () { // Themes (experimentell): Standard und öffentliche Gestaltungsvarianten
      var cur = D.theme || '', tm = h('p', { class: 'msg', role: 'status' }), row = h('div', { class: 'skins', role: 'group', 'aria-label': 'Theme wählen' });
      [{ slug: '', name: 'Standard', desc: 'Die gewohnte Oberfläche.' }].concat(D.pubdesigns).forEach(function (x) {
        row.append(h('button', { class: 'skin', type: 'button', 'aria-pressed': String(cur === x.slug), onclick: function () { if (cur === x.slug) return; tm.className = 'msg'; tm.textContent = 'Wechsle …'; api('/api/data.php?a=theme', { theme: x.slug }).then(function (r) { if (r && r.ok) location.reload(); else { tm.className = 'msg err'; tm.textContent = (r && r.error) || 'Das hat nicht geklappt.'; } }); } }, h('b', null, x.name), h('small', { class: 'hint', style: 'margin:0;max-width:34ch' }, x.desc)));
      });
      var tc = (setCard('Themes', [h('div', { class: 'wide2' }, h('p', { class: 'hint', style: 'margin-top:0' }, 'Experimentell: Die Themes sind noch in Arbeit, manche Seiten können dabei etwas anders aussehen oder noch nicht ganz stimmig sein. Deine Farbwahl oben gilt auch für das Theme. Du kannst jederzeit zurück zu „Standard“ wechseln.'), row, tm)], 'solo'));
      if (optikC) optikC.after(tc); else st.append(tc);
    })();
    var uiC = devMark(setCard('Oberflächenanpassungen', [uiBox], 'solo'), 'uianp'), thC = [].slice.call(st.children).filter(function (c) { return c.querySelector('h3').textContent === 'Themes'; })[0];
    if (thC) thC.after(uiC); else if (optikC) optikC.after(uiC); else st.append(uiC);
    st.append(setCard('Privatsphäre', [K[8], K[10]], 'solo'));
    if (K[12]) st.append(setCard('Konto löschen', [K[12]], 'solo'));
    (function () { // Reiter wie im Admin-Bereich
      var T = [['Profil', ['Meine Daten', 'Benutzername', 'Profilkarte']], ['Optik/Optionen', ['Farbschemata', 'Themes', 'Oberflächenanpassungen', 'Kalender-Abo', 'E-Mail-Benachrichtigungen']], ['Daten', ['E-Mail und Wiederherstellung', 'Import']], ['Datenschutz', ['Privatsphäre', 'Zwei-Faktor-Anmeldung', 'Konto löschen']]];
      var cards = [].slice.call(st.children), cur = 'Profil', bar = h('div', { class: 'admtabs', role: 'tablist' });
      try { var sv = sessionStorage.getItem('sd_settab'); if (sv && T.some(function (t) { return t[0] === sv; })) cur = sv; } catch (_) {}
      var btns = T.map(function (t) { var b = h('button', { class: 'admtab', type: 'button', role: 'tab', onclick: function () { cur = t[0]; try { sessionStorage.setItem('sd_settab', cur); } catch (_) {} show(); } }, t[0]); bar.append(b); return b; });
      function show() {
        T.forEach(function (t, i) { btns[i].setAttribute('aria-selected', String(t[0] === cur)); btns[i].className = 'admtab' + (t[0] === cur ? ' on' : ''); });
        cards.forEach(function (c) { var tt = c.querySelector('h3').textContent, own = T.filter(function (t) { return t[1].indexOf(tt) > -1; })[0]; c.hidden = own ? own[0] !== cur : cur !== 'Profil'; });
      }
      p.querySelector('h2.sec').after(bar); show();
    })();
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
          var SRC = ['MusicBrainz', 'ListenBrainz', 'TMDB', 'TVmaze', 'Open Library', 'Google Books', 'Deutsche Nationalbibliothek', 'lobid.org', 'Wikipedia', 'Wikidata', 'DeepL', 'Google News', 'Apple Podcasts'], by = {};
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
        var emp = r.empty || [];
        lst('Geprüft, aber ohne Werke', emp.filter(function (x) { return x.type !== 'artist'; }), 'Die Quelle hat nichts gefunden oder der Name passt nicht. „Treffer ändern“ im Menü des Eintrags hilft.');
        lst('Künstler:innen ohne Alben, EPs und Singles', emp.filter(function (x) { return x.type === 'artist'; }), 'Bei Musik werden Alben und EPs abgefragt, nur wenn es davon keine gibt, auch Singles. Wer hier steht, hat bei MusicBrainz nichts davon oder wurde noch nicht neu geladen. Die Beschreibung kommt aus Wikipedia.');
      });
    }
    cronLoad();
    return [h('p', { class: 'hint' }, 'Der Cron-Job aktualisiert die ältesten Einträge zuerst und arbeitet reihum Serien, Musiker:innen und Autor:innen ab, solange sein Zeitbudget (80 Sekunden) reicht. Danach laufen die Mails. Die Werte werden beim Öffnen der Seite neu geladen.'), cronBox];
  }

  /* ---------- Entstehung und Aufwand (Info-Seite, für alle) ---------- */
  function effortSection() {
    function tbl(head, rows) {
      return h('div', { class: 'nerdtbl fit' }, h('table', null, h('thead', null, h('tr', null, head.map(function (x) { return h('th', null, x); }))),
        h('tbody', null, rows.map(function (r) { return h('tr', null, r.map(function (x, i) { return h('td', i === 0 ? { class: 'k' } : null, x); })); }))));
    }
    var BASE = 5406, FB = { lines: 7424, files: 45, api: 14, versions: 11, items: 91, d: '2026-11-12' }, START = new Date(2026, 9, 14), nf = new Intl.NumberFormat('de-DE');
    function rd(v, st) { return Math.round(v / st) * st; }
    function rng(a, b, f, st, unit) { return 'etwa ' + nf.format(rd(a * f, st)) + '–' + nf.format(rd(b * f, st)) + (unit ? ' ' + unit : ''); }
    var sec = h('section', { class: 'infosec' });
    function draw(m) {
      var src = m || FB, L = +src.lines > 0 ? +src.lines : BASE, f = Math.max(1, L / BASE), dt = src.d ? src.d.split('-') : null;
      var stand = dt ? dt[2] + '.' + dt[1] + '.' + dt[0] : '12.11.2026', ref = dt ? new Date(+dt[0], +dt[1] - 1, +dt[2]) : new Date(2026, 10, 12);
      var wk = Math.max(1, Math.round((ref - START) / 604800000 * 2) / 2);
      var devLo = rd(350 * f, 10), devHi = rd(600 * f, 10);
      sec.textContent = '';
      sec.append(h('h3', null, 'Entstehung und Aufwand'),
        h('p', { class: 'hint' }, 'Stand ' + stand + (m ? ' (wird jeden Sonntagmorgen automatisch neu gemessen)' : '') + '. Gemessen werden nur Umfang und Laufzeit. Alle anderen Zahlen sind grobe Schätzungen, die mit dem Umfang hochgerechnet werden, und können deutlich danebenliegen: Es gibt keinen Zähler für Zeit, Tokens oder Wasser.'),
        h('p', null, 'Diese Seite ist mit Hilfe einer KI entstanden: Der Betreiber hat sie gemeinsam mit Claude (Anthropic) entwickelt. Die Idee, die Entscheidungen, das Testen und die Freigaben liegen beim Betreiber, den Code hat überwiegend die KI geschrieben.'),
        h('p', null, 'Umfang (gemessen): rund ' + nf.format(L) + ' Zeilen PHP, JavaScript und CSS in ' + (src.files || 'über 40') + ' Dateien (davon ' + (src.api || 14) + ' API-Dateien)' + (src.versions ? ', ' + src.versions + ' Versionen mit ' + src.items + ' Einträgen im Versionsverlauf' : '') + '. Die Gespräche zur Seite laufen seit Mitte Oktober 2026, also etwa ' + String(wk).replace('.', ',') + ' Wochen.'),
        tbl(['Größe', 'Schätzung'], [
          ['Aktive Arbeitszeit des Betreibers (testen, Screenshots, Entscheidungen)', rng(30, 50, f, 5, 'Stunden')],
          ['Ausgabe der KI (Code und Texte)', 'grob ' + String(rd(2 * f, .5)).replace('.', ',') + '–' + String(rd(4 * f, .5)).replace('.', ',') + ' Mio. Tokens'],
          ['Eingabe der KI inklusive wiederholt gelesenem Kontext', 'einige hundert Mio. Tokens' + (f > 1.5 ? ' (mehr als ein Mrd. ist möglich)' : '') + ', überwiegend aus dem Zwischenspeicher'],
          ['Rechnerischer Wert nach API-Listenpreisen', 'grob ' + nf.format(rd(200 * f, 50)) + '–' + nf.format(rd(600 * f, 50)) + ' €; im Abo pauschal enthalten, der genaue Preis des Modells ist nicht bekannt'],
          ['Wasserverbrauch der Rechenzentren', 'sehr unsicher, etwa ' + Math.round(2 * f) + '–' + Math.round(100 * f) + ' Liter, realistisch einige bis niedrige zweistellige Liter (mal ' + String(Math.round(f * 10) / 10).replace('.', ',') + '); Anbieter veröffentlichen dazu keine belastbaren Werte'],
          ['Erfahrener Entwickler allein', 'etwa ' + devLo + '–' + devHi + ' Stunden, also ' + Math.round(devLo / 40) + '–' + Math.round(devHi / 40) + ' Wochen in Vollzeit'],
          ['Laie ohne Unterstützung', 'etwa ' + nf.format(rd(1000 * f, 100)) + '–' + nf.format(rd(2000 * f, 100)) + ' Stunden, viele würden vorher aufgeben']]),
        h('p', { class: 'hint' }, 'Die Angaben zu Entwickler und Laie sind Erfahrungswerte für den gleichen Funktionsumfang (Mehrnutzerverwaltung, Anmeldung mit Zwei-Faktor-Schritt, Cronjobs, Mailversand mit SPF/DKIM, Benachrichtigungen, Admin-Bereich, Kalender-Abo, Web-App, Datenschutz und rund zehn Datenquellen). Aus Codezeilen lässt sich der Aufwand nur grob ableiten: Die Schätzungen wachsen hier einfach im gleichen Verhältnis wie der Umfang seit dem Stand vom 08.11.2026 (' + nf.format(BASE) + ' Zeilen).'));
    }
    draw(null);
    api('/api/data.php?a=effort').then(function (r) { if (r && r.m) draw(r.m); });
    return sec;
  }

  /* ---------- Community (vorerst Admin und Alphatester) ---------- */
  function commOn() { return true; }
  function commDate(s) { var m = /^(\d{4})-(\d\d)-(\d\d)/.exec(s || ''); return m ? m[3] + '.' + m[2] + '.' + m[1] : ''; }
  function community() {
    var C = S.comm = S.comm || { open: {}, sort: 'all', period: 'all', uid: null, data: null, ud: null };
    var p = h('section', { class: 'panel commpanel' }, h('h2', { class: 'sec' }, 'Community'));
    mainEl.append(p);
    if (!D.community) p.append(h('p', { class: 'warnbox commnote', role: 'status' }, 'Deine eigenen Einträge fließen in die Übersichten mit Nutzern (Aktivste User, Listen anderer) nicht ein, solange du nicht zugestimmt hast. Bis dahin siehst du bei „Aktivste User“ und bei den Listen anderer nur anonymisierte Platzhalter statt der echten Werte. Du kannst das in den ', h('button', { class: 'namelink', type: 'button', onclick: function () { go('set'); } }, 'Einstellungen'), ' aktivieren.'));
    var body = h('div'); p.append(body);
    var LOCKNOTE = function () { return devMark(h('p', { class: 'warnbox commnote', role: 'status' }, 'Diese Details siehst du erst, wenn du der Nutzung deiner Einträge für die Community zustimmst. Bis dahin zeigen wir nur Platzhalter: Du siehst, wie die Übersicht aussieht, die Inhalte sind aber nicht lesbar. Aktiviere es in den ', h('button', { class: 'namelink', type: 'button', onclick: function () { go('set'); } }, 'Einstellungen'), ' unter „Meine Einträge für die Community nutzen“.'), 'community-locked'); };
    function adoptBtn(row) {
      if (row.id < 0) return '';
      if (+row.mine) return h('span', { class: 'commhave', title: 'Schon in deiner Liste' }, '✓');
      var b = h('button', { class: 'btn ghost small commadd', type: 'button', title: 'In meine Liste übernehmen', 'aria-label': row.name + ' in meine Liste übernehmen', onclick: function () {
        b.disabled = true;
        api('/api/community.php?a=adopt', { subject_id: row.id }).then(function (r) {
          if (r && r.ok) { row.mine = 1; b.replaceWith(h('span', { class: 'commhave' }, '✓')); load(function () { nav(); }); toast('„' + row.name + '“ ist jetzt in deiner Liste.'); }
          else { b.disabled = false; toast((r && r.error) || 'Das hat nicht geklappt.'); }
        });
      } }, '+');
      return b;
    }
    var CT = ORDER.slice();
    function rankTable(key, title, rows, unit, cat, lock) {
      var open = !!C.open[key], shown = rows.slice(0, open ? 50 : 15);
      var box = h('div', { class: 'commbox' + (lock ? ' locked' : ''), style: '--cc:' + (CATCOL[cat] || 'var(--accent)') }, h('h4', null, title)), mx = rows.length ? Math.max.apply(null, rows.map(function (x) { return +x.c || 0; })) : 1;
      if (!rows.length) { box.append(h('p', { class: 'hint' }, 'Noch keine Daten.')); return box; }
      box.append(h('div', { class: 'tblwrap' }, h('table', { class: 'crontbl commtbl' }, h('tbody', null, shown.map(function (r, i) {
        return h('tr', null, h('td', { class: 'rk' + (i < 3 ? ' top' : '') }, String(i + 1)), h('td', { class: 'nm', style: 'background:linear-gradient(90deg,color-mix(in srgb,var(--cc) 16%,transparent) ' + Math.round(100 * (+r.c || 0) / (mx || 1)) + '%,transparent 0)' }, r.name), h('td', { class: 'ct', title: unit }, String(r.c)), h('td', { class: 'ad' }, adoptBtn(r)));
      })))));
      if (rows.length > 15) box.append(h('button', { class: 'btn ghost small', type: 'button', onclick: function () { C.open[key] = !open; drawOverview(); } }, open ? 'Weniger anzeigen' : 'Top ' + Math.min(50, rows.length) + ' anzeigen'));
      return box;
    }
    function drawOverview() {
      var d = C.data; body.textContent = '';
      // Aktivste User (nur mit Zustimmung)
      var us = (d.users || []).map(function (u) {
        var src = C.period === 'rec' ? u.rec : u.all, tot = 0; CT.forEach(function (t) { tot += +src[t] || 0; });
        return { id: u.id, name: u.name, tot: tot, by: src };
      }).filter(function (u) { return u.tot > 0; });
      us.sort(function (a, b) { var x = C.sort === 'all' ? a.tot : a.by[C.sort], y = C.sort === 'all' ? b.tot : b.by[C.sort]; return (y - x) || (b.tot - a.tot) || a.name.localeCompare(b.name, 'de'); });
      var open = !!C.open.users, shown = us.slice(0, open ? 20 : 5);
      var per = h('div', { class: 'seg', role: 'group', 'aria-label': 'Zeitraum' }, [['all', 'Gesamt'], ['rec', 'Letzte 14 Tage']].map(function (x) {
        return h('button', { class: 'btn small' + (C.period === x[0] ? '' : ' ghost'), type: 'button', 'aria-pressed': String(C.period === x[0]), onclick: function () { C.period = x[0]; drawOverview(); } }, x[1]);
      }));
      var head = h('tr', null, h('th', null, '#'), h('th', null, 'Nutzer'));
      [['all', 'Gesamt']].concat(CT.map(function (t) { return [t, TYPES[t].plural]; })).forEach(function (x) {
        head.append(h('th', { class: 'num' }, h('button', { class: 'sortbtn', type: 'button', 'aria-pressed': String(C.sort === x[0]), title: 'Nach ' + x[1] + ' sortieren', onclick: function () { C.sort = x[0]; drawOverview(); } }, x[1] + (C.sort === x[0] ? ' ↓' : ''))));
      });
      var tb = h('tbody');
      shown.forEach(function (u, i) {
        var tr = h('tr', null, h('td', { class: 'rk' }, String(i + 1)), h('td', { class: 'nm' }, h('button', { class: 'namelink', type: 'button', onclick: function () { C.uid = u.id; C.ud = null; drawUser(); } }, u.name)), h('td', { class: 'num' }, String(u.tot)));
        CT.forEach(function (t) { tr.append(h('td', { class: 'num' }, String(u.by[t] || 0))); });
        tb.append(tr);
      });
      var ub = h('section', { class: 'commusers' + (d.locked ? ' locked' : '') }, h('h3', { class: 'commh' }, 'Aktivste User'), d.locked ? LOCKNOTE() : '',
        h('p', { class: 'hint' }, 'Nur Nutzer, die der Nutzung ihrer Einträge für die Community ausdrücklich zugestimmt haben (Beispieleinträge zählen nicht). Namen sind anklickbar.'), per);
      if (!us.length) ub.append(h('p', { class: 'hint' }, 'Noch niemand in diesem Zeitraum.'));
      else {
        ub.append(h('div', { class: 'tblwrap' }, h('table', { class: 'crontbl commtbl' }, h('thead', null, head), tb)));
        if (us.length > 5) ub.append(h('button', { class: 'btn ghost small', type: 'button', onclick: function () { C.open.users = !open; drawOverview(); } }, open ? 'Weniger anzeigen' : 'Top ' + Math.min(20, us.length) + ' anzeigen'));
      }
      body.append(ub);
      body.append(h('h3', { class: 'commh' }, 'Top'), h('p', { class: 'hint' }, 'Am häufigsten in Listen. Die Zahl zeigt, wie viele Nutzer den Eintrag haben, ohne Namen. Es zählen nur Nutzer, die der Community-Nutzung zugestimmt haben.'));
      var g = h('div', { class: 'commgrid' });
      CT.forEach(function (t) { g.append(rankTable('top' + t, 'Top ' + (C.open['top' + t] ? '50' : '15') + ' ' + TYPES[t].plural, d.top[t] || [], 'Nutzer', t)); });
      body.append(g, h('h3', { class: 'commh' }, 'Newcomer'), h('p', { class: 'hint' }, 'Am häufigsten in den letzten 14 Tagen hinzugefügt.' + (d.locked ? ' Bis du zustimmst, siehst du hier nur Platzhalter.' : '')));
      var g2 = h('div', { class: 'commgrid' });
      CT.forEach(function (t) { g2.append(rankTable('new' + t, 'Newcomer ' + TYPES[t].plural, d['new'][t] || [], 'in 14 Tagen hinzugefügt', t, !!d.locked)); });
      body.append(g2);
      var SEENT = { series: ['Meistgesehen Serien', 'mindestens eine halbe Staffel'], author: ['Meistgelesen Bücher', 'mindestens einem gelesenen Buch'], artist: ['Meistgehört Musik', 'mindestens 5 Alben oder 20 %'], film: ['Meistgesehen Filme', 'gesehen (jeder Film einzeln)'], podcast: ['Meistgehört Podcasts', 'mindestens 5 Folgen oder 20 %'] };
      var g3 = h('div', { class: 'commgrid' }); CT.forEach(function (t) { g3.append(rankTable('seen' + t, SEENT[t][0], (d.seen || {})[t] || [], 'Nutzer mit ' + SEENT[t][1], t)); });
      body.append(h('h3', { class: 'commh' }, 'Meistgesehen, gelesen, gehört'), h('p', { class: 'hint' }, 'Jeder Nutzer zählt pro Titel nur einmal, sobald er genug gesehen, gelesen oder gehört hat: Serien eine halbe Staffel, Bücher mindestens ein gelesenes Buch der Autor:in oder Reihe (bei Gleichstand entscheidet die Zahl der gelesenen Bücher), Musik mindestens 5 Alben oder 20 % des Katalogs, Filme einzeln, Podcasts mindestens 5 Folgen oder 20 % (automatisch abgehakte Folgen beim Hinzufügen zählen nicht).'), g3);
    }
    function drawUser() {
      body.textContent = '';
      var back = h('button', { class: 'btn ghost small', type: 'button', onclick: function () { C.uid = null; C.ud = null; drawOverview(); window.scrollTo(0, 0); } }, '← Zurück zur Community');
      body.append(back);
      if (!C.ud) {
        body.append(h('p', { class: 'state pulse' }, 'Lade …'));
        var want = C.uid;
        api('/api/community.php?a=user&id=' + want).then(function (r) {
          if (C.uid !== want) return;
          if (!r || r.error) { body.append(h('p', { class: 'msg err' }, (r && r.error) || 'Das hat nicht geklappt.')); return; }
          C.ud = r; drawUser();
        });
        return;
      }
      var d = C.ud, tot = 0; CT.forEach(function (t) { tot += (d.lists[t] || []).length; });
      body.append(h('h3', { class: 'commh' + (d.locked ? ' locked' : '') }, d.user.name), d.locked ? LOCKNOTE() : '', h('div', { class: d.locked ? 'locked' : null }, idCard(d)), h('p', { class: 'hint' }, d.locked ? 'Platzhalter-Beispiel.' : pl(tot, 'Eintrag', 'Einträge') + ' in den Listen. Mit „+“ übernimmst du einen Eintrag in deine eigene Liste, ein Häkchen heißt, du hast ihn schon.'));
      var pkAny = CT.some(function (t) { return ((d.picks || {})[t] || []).length; });
      if (pkAny) {
        var pk = h('section', { class: 'commbox commpicks' + (d.locked ? ' locked' : '') }, h('h4', null, '★ Persönliche Empfehlungen'));
        pk.append(h('div', { class: 'tblwrap' }, h('table', { class: 'crontbl commtbl' }, h('tbody', null, CT.reduce(function (acc, t) { ((d.picks || {})[t] || []).forEach(function (r) { acc.push(h('tr', null, h('td', { class: 'ty' }, TYPES[t] ? TYPES[t].label : ''), h('td', { class: 'nm' }, r.name), h('td', { class: 'ad' }, adoptBtn(r)))); }); return acc; }, [])))));
        body.append(pk);
      }
      var last = h('section', { class: 'commbox commlast' + (d.locked ? ' locked' : '') }, h('h4', null, 'Letzte 20 hinzugefügte Positionen'));
      if (!d.last.length) last.append(h('p', { class: 'hint' }, 'Noch nichts.'));
      else last.append(h('div', { class: 'tblwrap' }, h('table', { class: 'crontbl commtbl' }, h('tbody', null, d.last.map(function (r) {
        return h('tr', null, h('td', { class: 'ty' }, TYPES[r.type] ? TYPES[r.type].label : ''), h('td', { class: 'nm' }, r.name), h('td', { class: 'ad' }, adoptBtn(r)));
      })))));
      body.append(last);
      var g = h('div', { class: 'commgrid' });
      CT.forEach(function (t) {
        var rows = d.lists[t] || [], bx = h('div', { class: 'commbox' + (d.locked ? ' locked' : '') }, h('h4', null, TYPES[t].plural + (d.locked ? '' : ' (' + rows.length + ')')));
        var PS = 50, pgk = C.uid + '|' + t, pages = Math.ceil(rows.length / PS), pgi; C.pg = C.pg || {}; pgi = Math.min(C.pg[pgk] || 0, Math.max(0, pages - 1)); C.pg[pgk] = pgi;
        var allRows = rows; rows = allRows.slice(pgi * PS, (pgi + 1) * PS);
        if (C.pgFocus === pgk) { C.pgFocus = null; setTimeout(function () { try { bx.scrollIntoView({ block: 'start' }); } catch (_) {} }, 0); }
        if (!rows.length) bx.append(h('p', { class: 'hint' }, 'Leer.'));
        else bx.append(h('div', { class: 'tblwrap' }, h('table', { class: 'crontbl commtbl' }, h('tbody', null, rows.map(function (r) { return h('tr', null, h('td', { class: 'nm' }, r.name), h('td', { class: 'ad' }, adoptBtn(r))); })))));
        if (pages > 1) { var pgn = function (dl) { return function () { C.pg[pgk] = pgi + dl; C.pgFocus = pgk; drawUser(); }; }; bx.append(h('div', { class: 'commpg' }, h('button', { class: 'btn ghost small', type: 'button', disabled: pgi === 0, onclick: pgn(-1) }, '‹ Zurück'), h('span', { class: 'hint', style: 'margin:0' }, 'Seite ' + (pgi + 1) + ' von ' + pages + ' (' + (pgi * PS + 1) + ' bis ' + Math.min(allRows.length, (pgi + 1) * PS) + ')'), h('button', { class: 'btn ghost small', type: 'button', disabled: pgi >= pages - 1, onclick: pgn(1) }, 'Weiter ›'))); }
        g.append(bx);
      });
      body.append(g);
    }
    if (C.uid && C.data) { drawUser(); return; }
    if (C.data) { drawOverview(); }
    else body.append(h('p', { class: 'state pulse' }, 'Lade Community …'));
    api('/api/community.php?a=overview').then(function (r) {
      if (!r || r.error) { body.textContent = ''; body.append(h('p', { class: 'msg err' }, (r && r.error) || 'Das hat nicht geklappt.')); return; }
      C.data = r; if (S.view === 'comm' && !C.uid) drawOverview();
    });
  }

  /* ---------- Nerdinfos (Projektübersicht) ---------- */
  function nerdOn() { return !!D.nerd && !S.preview; }
  function nerdPage() {
    var P = h('section', { class: 'panel infopanel nerdinfo' }, h('h2', { class: 'sec' }, 'Nerdinfos'),
      h('p', { class: 'hint' }, 'Wie Schondraussen.de gebaut ist, woher die Daten kommen und welche Dienste im Hintergrund laufen. Stand 15.11.2026.'));
    function sec(title) { var s = h('section', { class: 'infosec' }, h('h3', null, title)); for (var i = 1; i < arguments.length; i++) s.append(arguments[i]); P.append(s); }
    function ul(items) { return h('ul', { class: 'nerdul' }, items.map(function (x) { return h('li', null, x); })); }
    function cell(x) { return (x && x.href) ? h('a', { href: x.href, target: '_blank', rel: 'noopener noreferrer' }, x.t) : x; }
    function tbl(head, rows) {
      return h('div', { class: 'nerdtbl' }, h('table', null, h('thead', null, h('tr', null, head.map(function (x) { return h('th', null, x); }))),
        h('tbody', null, rows.map(function (r) { return h('tr', null, r.map(function (x, i) { return h('td', i === 0 ? { class: 'k' } : null, cell(x)); })); }))));
    }
    sec('Kurzüberblick', h('p', null, 'Schondraussen.de ist ein kostenloser Medien-Tracker: Nutzer legen Autor:innen, Musiker:innen, Filme und Serien auf eine Liste und werden per Mail und Kalender über Neuerscheinungen informiert. Die Seite ist am 08.11.2026 offiziell gestartet.'),
      ul(['Betreiber: Timo Lauer, Trier (Einzelprojekt, privat und ohne Einnahmen' + (S.site.tag ? ' außer über Amazon-Partnerlinks' : '') + ')', 'Technik in einem Satz: PHP und MySQL auf ALL-INKL, Code in einem privaten GitHub-Repository, automatische Bereitstellung bei jedem Push', 'Stand der Kategorien: Bücher, Musik und Serien sind angebunden, Podcasts werden schrittweise freigegeben, Filme kommen von TMDB']));
    sec('Funktionen für Nutzer', ul([
      'Konto mit E-Mail-Bestätigung oder, wenn der Admin es erlaubt, ganz ohne E-Mail-Adresse (Anmeldung mit Benutzername und Wiederherstellungscode); frei wählbarer Benutzername, optionale Zwei-Faktor-Anmeldung; Konto jederzeit löschbar (endgültig nach 7 Tagen)',
      'Vier Listen (Bücher, Musik, Filme, Serien, je bis zu 1.000 Einträge) mit Werkübersicht, Beschreibung (deutsch, übersetzt) und Markierung als gesehen, gelesen oder gehört',
      'Kalender aller angekündigten Veröffentlichungen mit Kategorie-Chips zum Ein- und Ausblenden und Vorschau je Tag, dazu ein persönliches Kalender-Abo (ICS)',
      'Bücher auch als Reihe über den Titel (zum Beispiel Manga), Hinweis „neu hinzugefügt“ für Bücher der letzten 4 Wochen',
      'Benachrichtigungen per Mail: morgens bei Neuigkeiten, morgens plus Wochenübersicht oder nur die Wochenübersicht; mit Kurzinfo und bis zu zwei aktuellen Schlagzeilen je Titel, Abmeldelink in jeder Mail',
      'Vorschläge ähnlicher Künstler:innen, Autor:innen und Serien; Import aus Trakt und Spotify; Datenexport',
      'Ideen und Bugmeldungen (mit Kategorie, abstimmbar) und Kontakt-Chat mit dem Betreiber; öffentliches Kontaktformular ohne Konto',
      'Mehrere Oberflächen (Skins), dazu experimentelle Themes (Standard, 8-Bit), Nutzung als App auf dem Handy, Systemnachrichten des Betreibers als Fenster und im Chat']));
    sec('Funktionen für den Admin', ul([
      'Nutzerverwaltung (freischalten, sperren, Alphatester, Einladen per Link, Namensänderung verlangen), Rechte der Nutzergruppen, Optionen „Neue Konten manuell freischalten“ (Standard aus) und „Registrierung ohne E-Mail-Adresse erlauben“',
      'Systemnachrichten an alle Nutzer, Infomails, News, Statistik, Bilder der Anmeldeseite, Amazon-Partner-Daten, Testmail',
      'Sicherheit: Protokoll der Admin-Zugriffe, Ansicht als normaler Nutzer, Eintrag prüfen (Diagnose)',
      'Cron-Status, Versionsverlauf (Changelog) mit Kategorien, Meldung bei neuen Ideen und Bugs']));
    sec('Technik und Betrieb', tbl(['Bereich', 'Umsetzung'], [
      ['Hosting', 'ALL-INKL (Webspace, MySQL, Mailserver), HTTPS erzwungen'],
      ['Sprache und Datenbank', 'PHP und MySQL; Oberfläche in JavaScript ohne Framework'],
      ['Bereitstellung', 'Privates GitHub-Repository; jeder Push auf den Hauptzweig wird per GitHub Actions auf den Server übertragen (rund 3 bis 4 Minuten)'],
      ['Aktualisierung der Daten', 'Ein geplanter Server-Job (Cron) stößt die Aktualisierung an; älteste Einträge zuerst, je Lauf etwa 80 Sekunden Zeitbudget, Einträge werden alle 12 Stunden erneuert; danach laufen die Mails'],
      ['Mailversand', 'SMTP über den Mailserver des Hosters, Double-Opt-in, Abmeldelink und List-Unsubscribe-Kopfzeile'],
      ['Versionsverlauf', 'Eine Sammelversion je Kalendertag, Kategorien F, O, U, B, A, D, T']]));
    sec('Externe Datenquellen und Schnittstellen', h('p', null, 'Alle Abfragen laufen serverseitig und nur für Titel, die Nutzer wirklich anlegen. An die Dienste gehen nie E-Mail-Adressen oder Kontodaten.'),
      tbl(['Quelle', 'Wofür', 'Schlüssel', 'Auflagen und Hinweise'], [
        [{ t: 'TMDB', href: 'https://www.themoviedb.org' }, 'Filme: Titel, Beschreibung, Kino- und Digitalstart in Deutschland, Filmreihen', 'Zugangsschlüssel', 'Dieses Produkt nutzt die TMDB-API, wird aber von TMDB weder unterstützt noch zertifiziert.'],
        [{ t: 'JustWatch', href: 'https://www.justwatch.com' }, 'Filme: wo ein Film in Deutschland gestreamt, geliehen oder gekauft werden kann (Daten kommen über TMDB)', 'keiner (über TMDB)', 'Quellenangabe „JustWatch“ ist bei jeder Anzeige Pflicht, sonst verliert die Seite den TMDB-Zugang'],
        [{ t: 'TVmaze', href: 'https://www.tvmaze.com' }, 'Serien: Staffeln, Folgen, Sendetermine, Beschreibung; auch Anime', 'keiner', 'Daten unter CC BY-SA 4.0, Quellenangabe im Footer und auf der Infoseite'],
        [{ t: 'MusicBrainz', href: 'https://musicbrainz.org' }, 'Musik: Künstler:innen, Alben, Termine', 'keiner', 'höchstens eine Anfrage pro Sekunde, daher gebremste Abfrage'],
        ['ListenBrainz', 'Musik: Vorschläge ähnlicher Künstler:innen', 'keiner', '–'],
        [{ t: 'Apple Podcasts', href: 'https://podcasts.apple.com' }, 'Podcasts: Suche und Feed-Adresse (iTunes Search API); die Folgen kommen aus dem öffentlichen Feed des jeweiligen Podcasts', 'keiner', 'etwa 20 Anfragen pro Minute; Feeds werden höchstens alle 12 Stunden abgerufen'],
        [{ t: 'Open Library', href: 'https://openlibrary.org' }, 'Bücher: Autor:innen und Werke, Wikidata-Verweis', 'keiner', 'liefert kaum angekündigte Bücher; Werke ohne deutsche Ausgabe werden ausgeblendet'],
        ['Google Books', 'Bücher: angekündigte Titel (deutsche Ausgaben)', 'API-Schlüssel', 'Eigener Schlüssel, begrenzte Abfragemenge pro Tag; für deutsche Manga kaum Treffer'],
        ['Deutsche Nationalbibliothek (SRU, ersatzweise lobid.org)', 'Bücher-Reihen über den Titel: nummerierte Bände mit Jahr, auch angekündigte', 'keiner', 'freie Schnittstelle; Antwortstruktur noch in Erprobung'],
        [{ t: 'DiceBear', href: 'https://www.dicebear.com' }, 'Profilbilder: Nutzer gestalten ein Bild und fügen den Link ein; der Server holt das SVG einmal, prüft es und speichert es', 'keiner', 'nur api.dicebear.com und nur SVG; die Stile haben eigene Lizenzen (dicebear.com/licenses)'],
        ['Wikipedia und Wikidata', 'Kurzvorstellung von Bands und Autor:innen', 'keiner', 'deutsch bevorzugt, sonst englisch und übersetzt'],
        [{ t: 'DeepL', href: 'https://www.deepl.com' }, 'Übersetzung englischer Beschreibungen ins Deutsche', 'API-Schlüssel (Free-Tarif)', 'nur öffentliche Texte, Ergebnis wird gespeichert (je Text eine Übersetzung)'],
        ['Google News (RSS-Suche)', 'aktuelle Schlagzeilen in den Benachrichtigungsmails (höchstens 7 Tage alt)', 'keiner', 'nur Name des Eintrags und Titel des Werks gehen an Google'],
        ['Amazon (Suchlinks)', 'Kauflinks', S.site.tag ? 'Partner-ID' : 'keiner', S.site.tag ? 'Kennzeichnung als Werbung im Footer, im Impressum und im Datenschutz' : 'zurzeit ohne Partner-ID, also keine Einnahmen']]));
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
      'Impressum und Datenschutzerklärung (Stand 15.11.2026) sind online; sie nennen alle Quellen, DeepL, die Google-News-Schlagzeilen, Amazon-Links, Aufbewahrungsfristen und die Rechte der Nutzer',
      'Es gibt nur technisch nötige Cookies (Sitzung, gewählte Version und Theme), keine Werbe- oder Analyse-Dienste, und die Seite lädt nur von der eigenen Domain (keine externen Schriften, Skripte oder CDNs)',
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
      '<p>Schondraussen.de hilft dir dabei, ganz ohne viel Schnickschnack: Du legst fest, wem du folgst, und die Seite informiert dich über Neuigkeiten, per Mail und im Kalender. So behältst du den Überblick und bestimmst selbst, was dich wirklich erreicht.</p>' +
      '<p>Auf der Suche nach neuem „Stoff“ hilft dir die Community: Dort siehst du, was andere Nutzer:innen gerade hinzufügen und was bei ihnen beliebt ist, und kannst Einträge mit einem Klick übernehmen. Dazu kannst du dir zu Musiker:innen, Autor:innen und Serien ähnliche Vorschläge anzeigen lassen.</p>' +
      (D.approval ? '<p class="muted">Neue Konten werden vom Admin freigeschaltet.</p>' : '') + '</div>' +
      '<section class="infosec"><h3>Feedback</h3><p>Die Seite ist neu und wächst weiter. Die Daten kommen aus den Quellen unten und können Lücken haben; manchmal tauchen auch fremdsprachige Ergebnisse auf. Fehlt dir etwas oder stimmt eine Angabe nicht, schreib mir über „Kontakt“ oder melde eine Idee oder einen Bug auf den Seiten „Ideen“ und „Bugs“.</p></section>' +
      '<section class="infosec"><h3>Wann werden Einträge erneuert?</h3><p>Automatisch alle 12 Stunden. Jeder Titel wird dabei nur einmal für alle Nutzer abgefragt und zwischengespeichert. Neuerscheinungen tauchen dann ohne eigenes Zutun auf. Beim Hinzufügen eines Eintrags und über „Aktualisieren“ werden die Daten sofort geladen. Das Datum steht als „Stand“ am Eintrag.</p><p class="muted" id="lastrun"></p><p class="muted"><a href="https://stats.uptimerobot.com/hWI2Kjc0jq" target="_blank" rel="noopener">Systemstatus ansehen</a> (zeigt, ob die Seite erreichbar ist)</p></section>' +
      '<section class="infosec"><h3>Quellen je Kategorie</h3>' +
      '<div class="infocat"><h4>Bücher</h4><p><a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> liefert die Bücher. Angekündigte Bücher kennt diese Quelle kaum, dafür kommt später eine zweite Quelle, etwa Google Books.</p></div>' +
      '<div class="infocat"><h4>Musik</h4><p><a href="https://musicbrainz.org" target="_blank" rel="noopener noreferrer">MusicBrainz</a>, eine offene Musikdatenbank, für Alben, EPs und angekündigte Veröffentlichungen.</p></div>' +
      '<div class="infocat"><h4>Filme</h4><p>Titel, Beschreibungen, Filmreihen, Regisseur:innen mit ihren Filmen und die Starttermine in Deutschland kommen von <a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer">TMDB</a>. Dieses Produkt nutzt die TMDB-API, wird aber von TMDB weder unterstützt noch zertifiziert. Wo ein Film läuft, zeigt die Seite mit Daten von <a href="https://www.justwatch.com" target="_blank" rel="noopener noreferrer">JustWatch</a>.</p></div>' +
      '<div class="infocat"><h4>Serien</h4><p><a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">TVmaze</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>) für Staffeln und Sendetermine der Folgen. Manga und Comics findest du unter Bücher, soweit Open Library und Google Books sie führen.</p></div>' +
      (!S.site.tag ? '<div class="infocat"><h4>Amazon-Links</h4><p>Suchlinks zu Amazon, ohne Partner-ID. Für den Betreiber entstehen daraus keine Einnahmen.</p></div>' : '<div class="infocat"><h4 class="withtip">Amazon-Links <span class="tip" tabindex="0" role="button" aria-label="Wie lange gilt ein Klick auf einen Amazon-Link?"><i>?</i><span class="tipbox" role="tooltip"><b>Wie lange gilt ein Klick?</b> Nach Amazons Regeln 24 Stunden. Kaufst du in dieser Zeit etwas bei Amazon, zählt es für die Seite, auch ein ganz anderes Produkt. Legst du innerhalb der 24 Stunden etwas in den Warenkorb, kann es länger zählen (bis zu etwa 90 Tage). Für dich ändert sich der Preis nie.</span></span></h4><p>Suchlinks mit der Partner-ID des Betreibers (Programm: <a href="https://partnernet.amazon.de" target="_blank" rel="noopener noreferrer">Amazon PartnerNet</a>). Eine Provision gibt es nur bei qualifizierten Käufen.</p></div>') + '</section>';
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
    var site = h('section', { class: 'infosec' }, h('h3', null, 'Zahlen zur Seite'), h('p', { class: 'hint' }, 'Lade …')); d.append(site);
    api('/api/data.php?a=sitestats').then(function (r) {
      site.textContent = ''; site.append(h('h3', null, 'Zahlen zur Seite'));
      if (!r || r.error || !r.ok) { site.append(h('p', { class: 'hint' }, 'Die Zahlen sind gerade nicht verfügbar.')); return; }
      var n = function (v) { return Number(v || 0).toLocaleString('de-DE'); }, dt = function (v) { return v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '–'; }, sb = r.subjects || {}, m = r.mails;
      var tot = (sb.author | 0) + (sb.series | 0) + (sb.artist | 0) + (sb.film | 0) + (sb.tv | 0) + (sb.podcast | 0);
      var rows = [['Online seit', dt(r.online_since)], ['Nutzer insgesamt (seit dem Start)', n(r.users_ever)], ['Aktuelle Nutzer', n(r.users_now)], ['Davon in der Community', n(r.community)],
        ['Bisher verfolgte Einträge', n(tot) + ' (' + n(sb.author) + ' Autor:innen, ' + n(sb.series) + ' Reihen, ' + n(sb.artist) + ' Musik, ' + n(sb.film) + ' Filme, ' + n(sb.tv) + ' Serien' + (sb.podcast != null ? ', ' + n(sb.podcast) + ' Podcasts' : '') + ')']];
      if (m) rows.push(['Verschickte Mails', n(m.all) + ' (' + n(m.new) + ' mit neuen Terminen, ' + n(m.day) + ' mit Erscheinungstag, ' + n(m.week) + ' Wochenübersichten)']);
      var dl2 = h('dl', { class: 'mystats' }); rows.forEach(function (x) { dl2.append(h('dt', null, x[0]), h('dd', null, x[1])); }); site.append(dl2);
      var hint = 'Jeder Eintrag zählt einmal, egal wie viele Nutzer ihn verfolgen. Er bleibt in der Zahl, auch wenn ihn alle wieder entfernt haben.' + (m ? ' Mails werden seit dem ' + dt(m.since) + ' gezählt. Eine Mail kann neue Termine und Erscheinungstage enthalten, deshalb ergeben die Teilzahlen nicht die Summe.' : '');
      site.append(h('p', { class: 'hint' }, hint));
    });
    d.append(effortSection());
    var holder = h('div', null), bar = h('div', { class: 'admtabs infotabs', role: 'tablist', 'aria-label': 'Info-Seiten' }), btns = [];
    var tabs = [['info', 'Info']]; if (ABOUT_PUBLIC || isAdm()) tabs.push(['about', 'Über mich']); tabs.push(['faq', 'FAQ']); if (nerdOn()) tabs.push(['nerd', 'Nerdinfos']);
    function showTab(k) {
      S.infoTab = k; holder.textContent = ''; holder.append(k === 'nerd' ? nerdPage() : k === 'faq' ? faqPage() : k === 'about' ? aboutPage() : d);
      btns.forEach(function (b) { var on = b.k === k; b.el.className = 'admtab' + (on ? ' on' : ''); b.el.setAttribute('aria-selected', on ? 'true' : 'false'); });
    }
    tabs.forEach(function (x) { var b = h('button', { class: 'admtab', type: 'button', role: 'tab', onclick: function () { showTab(x[0]); } }, x[1]); btns.push({ k: x[0], el: b }); bar.append(b); });
    mainEl.append(bar, holder); showTab(tabs.some(function (x) { return x[0] === S.infoTab; }) ? S.infoTab : 'info');
  }

  /* ---------- FAQ (Info-Reiter) und FAQ-Verwaltung (Admin) ---------- */
  function faqText(t) { // Absätze, Links (http/https) werden anklickbar
    var box = h('div', { class: 'faqans' });
    String(t || '').split(/\n+/).forEach(function (ln) {
      if (!ln.trim()) return; var p = h('p', null), re = /https?:\/\/[^\s<>"']+/g, last = 0, m;
      while ((m = re.exec(ln))) { var u = m[0].replace(/[.,;:!?)]+$/, ''); p.append(document.createTextNode(ln.slice(last, m.index)), h('a', { href: u, target: '_blank', rel: 'noopener noreferrer' }, u)); last = m.index + u.length; re.lastIndex = last; }
      p.append(document.createTextNode(ln.slice(last))); box.append(p);
    });
    return box;
  }
  function faqPage() {
    var P = devMark(h('section', { class: 'panel infopanel faqpanel' }, h('h2', { class: 'sec' }, 'FAQ')), 'faq'), list = h('div', null, h('p', { class: 'hint pulse' }, 'Lade …'));
    var srch = h('input', { type: 'search', maxlength: '80', placeholder: 'In den Fragen suchen', 'aria-label': 'FAQ durchsuchen', style: 'max-width:420px' });
    P.append(h('p', { class: 'hint' }, 'Antworten auf häufige Fragen. Fehlt dir etwas? Schreib uns über „Kontakt“ oder melde eine Idee.'), h('p', null, srch), list);
    var topics = [];
    function draw() {
      var q = srch.value.trim().toLowerCase(), any = false; list.textContent = '';
      topics.forEach(function (t) {
        var its = t.items.filter(function (i) { return !q || (i.question + ' ' + i.answer).toLowerCase().indexOf(q) >= 0; });
        if (!its.length) return; any = true;
        var sec = h('section', { class: 'infosec faqsec' }, h('h3', null, t.title));
        its.forEach(function (i) { var d = h('details', { class: 'faqitem' }, h('summary', null, i.question), faqText(i.answer)); if (q) d.open = true; sec.append(d); });
        list.append(sec);
      });
      if (!any) list.append(h('p', { class: 'hint' }, topics.length ? 'Nichts gefunden. Versuche ein anderes Wort oder frage uns über „Kontakt“.' : 'Noch keine Einträge.'));
    }
    srch.addEventListener('input', draw);
    api('/api/faq.php?a=list').then(function (r) { if (!r || r.error) { list.textContent = ''; list.append(h('p', { class: 'hint' }, (r && r.error) || 'Die FAQ ist gerade nicht verfügbar.')); return; } topics = r.topics || []; draw(); });
    return P;
  }
  function faqAdmin() {
    var box = devMark(h('div', { class: 'wide2 faqadm' }, h('p', { class: 'hint pulse' }, 'Lade …')), 'faqadm'), msg = h('p', { class: 'msg', role: 'status' }), topics = [], openForm = null;
    function say(t, bad) { msg.className = 'msg' + (bad ? ' err' : ''); msg.textContent = t || ''; }
    function call(act, data) { return api('/api/faq.php?a=' + act, data).then(function (r) { if (!r || !r.ok) { say((r && r.error) || 'Das hat nicht geklappt.', true); return r; } say(''); return load().then(function () { return r; }); }); }
    function mv(kind, id, dir) { return h('span', { class: 'faqmv' }, h('button', { class: 'btn ghost small', type: 'button', title: 'Nach oben', 'aria-label': 'Nach oben', onclick: function () { call('move', { kind: kind, id: id, dir: -1 }); } }, '▲'), h('button', { class: 'btn ghost small', type: 'button', title: 'Nach unten', 'aria-label': 'Nach unten', onclick: function () { call('move', { kind: kind, id: id, dir: 1 }); } }, '▼')); }
    function itemForm(item, tid) {
      var sel = h('select', { 'aria-label': 'Themenbereich' }, topics.map(function (t) { return h('option', { value: String(t.id) }, t.title); })); sel.value = String(tid);
      var qi = h('input', { type: 'text', maxlength: '240', value: item ? item.question : '', 'aria-label': 'Frage' }), an = h('textarea', { rows: '7', maxlength: '8000', 'aria-label': 'Antwort', style: 'width:100%;box-sizing:border-box' }, item ? item.answer : '');
      return h('div', { class: 'faqform' }, h('label', { class: 'field' }, h('span', null, 'Themenbereich'), sel), h('label', { class: 'field' }, h('span', null, 'Frage'), qi), h('label', { class: 'field' }, h('span', null, 'Antwort (Absätze mit Leerzeile, Links einfach einfügen)'), an),
        h('p', { style: 'display:flex;gap:8px;margin:8px 0 0' }, h('button', { class: 'btn small', type: 'button', onclick: function () { openForm = null; call('item_save', { id: item ? item.id : 0, topic_id: +sel.value, question: qi.value, answer: an.value }); } }, 'Speichern'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { openForm = null; draw(); } }, 'Abbrechen')));
    }
    function draw() {
      box.textContent = ''; var add = h('input', { type: 'text', maxlength: '120', placeholder: 'Neuer Themenbereich', 'aria-label': 'Name des neuen Themenbereichs' });
      box.append(h('p', { class: 'hint' }, 'Änderungen sind sofort für alle sichtbar, ohne Markierung. Reihenfolge mit den Pfeilen ändern; über „Bearbeiten“ kannst du einen Beitrag auch in einen anderen Themenbereich verschieben.'),
        h('div', { class: 'inline' }, add, h('button', { class: 'btn small', type: 'button', onclick: function () { call('topic_save', { title: add.value }); } }, 'Themenbereich anlegen')), msg);
      topics.forEach(function (t) {
        var ti = h('input', { type: 'text', maxlength: '120', value: t.title, 'aria-label': 'Name des Themenbereichs' }), card = h('div', { class: 'faqtopic' });
        card.append(h('div', { class: 'faqthead' }, ti, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { call('topic_save', { id: t.id, title: ti.value }); } }, 'Umbenennen'), mv('topic', t.id),
          h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Den Themenbereich „' + t.title + '“ mit ' + t.items.length + ' Beitrag/Beiträgen löschen?')) call('topic_delete', { id: t.id }); } }, 'Löschen')));
        t.items.forEach(function (i) {
          var row = h('div', { class: 'faqitemrow' }, h('span', { class: 'faqq' }, i.question), h('span', { class: 'faqacts' }, mv('item', i.id),
            h('button', { class: 'btn ghost small', type: 'button', onclick: function () { openForm = 'e' + i.id; draw(); } }, 'Bearbeiten'),
            h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Den Beitrag „' + i.question + '“ löschen?')) call('item_delete', { id: i.id }); } }, 'Löschen')));
          card.append(row); if (openForm === 'e' + i.id) card.append(itemForm(i, t.id));
        });
        if (openForm === 'n' + t.id) card.append(itemForm(null, t.id)); else card.append(h('p', { style: 'margin:8px 0 0' }, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { openForm = 'n' + t.id; draw(); } }, '＋ Beitrag')));
        box.append(card);
      });
    }
    function load() { return api('/api/faq.php?a=list').then(function (r) { if (!r || r.error) { box.textContent = ''; box.append(h('p', { class: 'warnbox' }, (r && r.error) || 'Das hat nicht geklappt.')); return; } topics = r.topics || []; draw(); }); }
    load(); return box;
  }
  /* ---------- Über mich (Info-Reiter, für alle) ---------- */
  var ABOUT_PUBLIC = true;
  var KOFI_URL = 'https://ko-fi.com/endymionmortis';
  function aboutPage() {
    var P = h('section', { class: 'panel infopanel aboutpanel' }, h('h2', { class: 'sec' }, 'Über mich'));
    P.append(h('section', { class: 'infosec' }, h('h3', null, 'Hallo und willkommen'),
      h('p', null, 'Ich bin Timo, 49, wohne in Trier und arbeite im Bereich Medien. Schondraussen.de betreibe ich als privates Projekt, ohne Firma dahinter.'),
      h('p', null, 'Die Idee dazu hatte ich schon vor über 25 Jahren: eine Übersicht, was ich schon gelesen, gehört und gesehen habe, und was bei meinen Lieblingen als Nächstes erscheint. Damals hatte ich weder eine Ahnung, wie man so etwas umsetzt, noch gab es die Möglichkeiten, die es heute gibt.'),
      h('p', null, 'Gebaut habe ich die Seite jetzt gemeinsam mit einer KI. Die Ideen, die Entscheidungen und das Ausprobieren kommen von mir.')));
    function fig(src, alt, cap) { return h('figure', { class: 'aboutfig' }, h('img', { src: src, alt: alt, loading: 'lazy' }), h('figcaption', null, cap)); }
    P.append(h('section', { class: 'infosec' }, h('h3', null, 'Meine Assistenten'),
      h('p', null, 'Bei der Arbeit an der Seite bekomme ich Unterstützung von Matz und Lucy. Sie achten streng auf Qualität, solange nichts Interessanteres im Karton ist.'),
      h('div', { class: 'aboutfigs' },
        fig('/about/katzen-karton.webp', 'Zwei Katzen schauen aus einem Pappkarton', 'Matz und Lucy bei der Büroinspektion im Karton'),
        fig('/about/katzen-monitor.webp', 'Zwei Katzen sitzen vor einem Monitor', 'Matz und Lucy bei der Qualitätssicherung am Monitor'))));
    if (KOFI_URL) P.append(h('section', { class: 'infosec' }, h('h3', null, 'Die Seite unterstützen'),
      h('p', null, 'Schondraussen.de ist kostenlos und bleibt werbefrei. ' + (S.site.tag ? 'Die einzige geplante Einnahmequelle sind die Amazon-Partnerlinks. ' : '') + 'Wenn dir die Seite gefällt und du mir freiwillig etwas zukommen lassen möchtest, geht das hier:'),
      h('p', null, h('a', { class: 'btn small', href: KOFI_URL, target: '_blank', rel: 'noopener noreferrer' }, 'Auf Ko-fi unterstützen'))));
    return P;
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

  /* ---------- News ---------- */
  function newsSrc(list) { var l = list.map(function (ln) { var i = ln.indexOf('|'), u = i < 0 ? ln.trim() : ln.slice(i + 1).trim(), n = i < 0 ? u : ln.slice(0, i).trim(); return /^https?:\/\//i.test(u) ? h('a', { class: 'newssrc', href: u, target: '_blank', rel: 'noopener noreferrer' }, n) : null; }).filter(Boolean); return l.length ? h('p', { class: 'newssrcs' }, h('span', null, 'Quellen'), l) : null; }
  function newsBody(t) { // einzelne News durch eine Zeile „---“ getrennt; Zeilen „Quelle: Name | Link“ gehören zur jeweiligen News
    var items = String(t || '').split(/\n\s*-{3,}\s*(?:\n|$)/).map(function (s) { return s.trim(); }).filter(Boolean), out = [];
    items.forEach(function (it, i) {
      var src = [], txt = []; it.split('\n').forEach(function (ln) { var m = /^\s*Quellen?:\s*(.+)$/i.exec(ln); if (m) src.push(m[1]); else txt.push(ln); });
      if (i) out.push(h('hr', { class: 'newshr' }));
      out.push(h('div', { class: 'newsitem' }, newsText(txt.join('\n')), newsSrc(src)));
    });
    return out;
  }
  function newsText(t) { // Klartext: Leerzeile = neuer Absatz, Zeilen mit „- “ als Liste
    var out = [], lines = String(t || '').split('\n'), para = [], list = [];
    function flush() { if (para.length) { out.push(h('p', null, para.join(' '))); para = []; } if (list.length) { out.push(h('ul', null, list.map(function (x) { return h('li', null, x); }))); list = []; } }
    lines.forEach(function (ln) { if (!ln.trim()) { flush(); return; } if (/^\s*[-•]\s+/.test(ln)) { if (para.length) flush(); list.push(ln.replace(/^\s*[-•]\s+/, '')); } else { if (list.length) flush(); para.push(ln.trim()); flush(); } });
    flush(); return out;
  }
  function newsDate(d) { try { return new Date(d + 'T12:00:00').toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); } catch (_) { return d; } }
  function newsDay(x, forAdmin) {
    var src = function (t) { return newsSrc(String(t || '').split('\n')); };
    var sec = function (cap, title, text, sources) { return (title || text) ? h('div', { class: 'newssec' }, h('h4', { class: 'logcat' }, cap), title ? h('h5', { class: 'newst' }, title) : null, newsBody(text), src(sources)) : null; };
    return [sec('Seitennews', x.site_title, x.site_text, x.site_sources), sec('News zu Inhalten', x.content_title, x.content_text, x.content_sources)].filter(Boolean);
  }
  function newsView() {
    var p = devMark(h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'News'), h('p', { class: 'hint' }, 'Neuigkeiten zur Seite und zu Büchern, Musik, Filmen, Serien und Podcasts. Pro Tag eine Meldung.')), 'news');
    var box = h('div', null, h('p', { class: 'state pulse' }, 'Lade …')); p.append(box); mainEl.append(p);
    api('/api/news.php?a=list').then(function (r) {
      box.textContent = '';
      if (!r || r.error) { box.append(h('p', { class: 'warnbox' }, (r && r.error) || 'Das hat nicht geklappt.')); return; }
      var l = (r.days || []).filter(function (x) { return !x.future || r.admin; });
      if (!l.length) { box.append(h('p', { class: 'hint' }, 'Noch keine News.')); return; }
      l.forEach(function (x, i) {
        var hd = h('h3', null, newsDate(x.day), x.future ? h('small', null, 'geplant') : null), body = newsDay(x);
        if (i === 0) box.append(h('div', { class: 'logv newsv' }, hd, body)); else box.append(h('details', { class: 'logv logold newsv' }, h('summary', null, hd), body));
      });
    });
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
  function uploadText(file, scope) {
    if (file.size > 200 * 1024) return Promise.reject(new Error('Die Textdatei ist zu groß (höchstens 200 KB).'));
    return fetch('/api/image.php?a=upload&scope=' + (scope || 'feedback') + '&kind=text&name=' + encodeURIComponent(file.name), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' }, body: file })
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
    var admin = D.admin, staff = !!(D.admin || D.alpha), p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, 'Kontakt'),
      h('p', { class: 'hint' }, admin ? 'Melde eine Idee oder einen Bug, oder antworte deinen Nutzern und dem Team.' : 'Melde eine Idee oder einen Bug, oder schreibe dem Administrator. Im Chat sieht nur der Administrator deine Nachrichten, Antworten erscheinen hier.'));
    var grid = h('div', { class: 'supgrid' + (staff ? ' admin2' : '') }), threads = h('aside', { class: 'supthreads', hidden: !staff }), log = h('div', { class: 'chatlog', role: 'log', 'aria-live': 'polite' });
    var inp = h('textarea', { rows: '2', maxlength: '1000', 'aria-label': 'Nachricht', placeholder: 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)' }), msg = h('p', { class: 'msg', role: 'status' });
    var drafts = [], draftBox = h('div', { class: 'drafts' });
    var mailCk = h('input', { type: 'checkbox' }), mailRow = h('label', { class: 'mailck', hidden: !admin }, mailCk, ' Zusätzlich per E-Mail senden (mit Hinweis, dass man darauf nicht antworten kann)');
    var pick = h('select', { 'aria-label': 'Neue Unterhaltung mit einem Nutzer beginnen', class: 'userpick', onchange: function () { if (!pick.value) return; S.sup.sel = pick.value; pick.value = ''; drawThreads(); pull(); } }, h('option', { value: '' }, 'Nutzer anschreiben …'));
    var dmPeers = [], dmPick = h('select', { 'aria-label': 'Chat mit einem Alphatester beginnen', class: 'userpick', onchange: function () { if (!dmPick.value) return; S.sup.sel = 'dm' + dmPick.value; dmPick.value = ''; drawThreads(); pull(); } }, h('option', { value: '' }, 'Alphatester anschreiben …'));
    if (!admin && staff) api('/api/chat.php?a=dm_peers').then(function (r) { dmPeers = r.users || []; dmPeers.forEach(function (x) { dmPick.append(h('option', { value: String(x.id) }, x.username)); }); });
    if (admin) api('/api/chat.php?a=users').then(function (r) { (r.users || []).forEach(function (x) { pick.append(h('option', { value: String(x.id) }, (x.username || 'Mitglied') + ' · Nr. ' + x.id)); }); });
    var titleIn = h('input', { type: 'text', maxlength: '120', class: 'fbtitle', hidden: true, 'aria-label': 'Kurzbeschreibung', placeholder: 'Kurzbeschreibung (erscheint als Titel in der Liste)', autocomplete: 'off', onkeydown: function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); inp.focus(); } } });
    var attNote = h('p', { class: 'hint picnote' });
    function drawNote() { attNote.textContent = kindSel === 'chat' ? 'Bilder (JPG, PNG, WebP) bis zu 3 pro Nachricht. Zeige bitte keine fremden Personen oder privaten Daten.' : 'Anhänge: ' + drafts.length + ' von 3. Erlaubt sind Bilder (JPG, PNG, WebP) und Textdateien (.txt, .log, .md, .csv, .json, bis 200 KB). Zeige bitte keine fremden Personen oder privaten Daten.'; }
    function isTeam() { return staff && S.sup.sel === 'team'; }
    function isDm() { return !admin && staff && /^dm\d+$/.test(String(S.sup.sel)); }
    function scopeOf() { return isTeam() ? 'team' : (kindSel === 'chat' ? 'chat' : 'feedback'); }
    function drawDrafts() { draftBox.textContent = ''; drafts.forEach(function (d) { draftBox.append(h('span', { class: 'draft' + (d.name ? ' txt' : '') }, d.name ? h('span', { class: 'dname' }, '📄 ' + d.name) : h('img', { src: d.url, alt: 'Angehängtes Bild' }), h('button', { type: 'button', class: 'x', 'aria-label': 'Bild entfernen', onclick: function () { drafts = drafts.filter(function (e) { return e !== d; }); api('/api/image.php?a=remove', { id: d.id }); drawDrafts(); } }, '×'))); }); drawNote(); }
    function addFiles(files) {
      [].slice.call(files).forEach(function (f) {
        if (drafts.length >= 3) { msg.className = 'msg err'; msg.textContent = scopeOf() === 'chat' ? 'Höchstens 3 Bilder pro Nachricht.' : 'Höchstens 3 Anhänge.'; return; }
        var isTxt = !/^image\//.test(f.type) && (/^text\//.test(f.type) || /\.(txt|log|md|csv|json)$/i.test(f.name));
        if (isTxt && scopeOf() === 'chat') { msg.className = 'msg err'; msg.textContent = 'Textdateien gehen nur bei Ideen und Bugmeldungen.'; return; }
        msg.className = 'msg'; msg.textContent = isTxt ? 'Datei wird hochgeladen …' : 'Bild wird hochgeladen …';
        var sc = scopeOf();
        (isTxt ? uploadText(f, sc) : uploadImage(f, sc)).then(function (d) { d.scope = sc; if (scopeOf() !== sc) { api('/api/image.php?a=remove', { id: d.id }); return; } drafts.push(d); msg.textContent = ''; drawDrafts(); })
          .catch(function (e) { msg.className = 'msg err'; msg.textContent = e.message; });
      });
    }
    function send() {
      if (S.sup.sel === 'team' && staff) { teamSend(); return; }
      if (isDm()) { var dt = inp.value.trim(); if (!dt) return; api('/api/chat.php?a=dm_send', { uid: String(S.sup.sel).slice(2), text: dt }).then(function (r) { if (r && r.ok) { inp.value = ''; msg.textContent = ''; dmPull(true); } else { msg.className = 'msg err'; msg.textContent = (r && r.error) || 'Das hat nicht geklappt.'; } }); return; }
      var t = inp.value.trim(), ids = drafts.map(function (d) { return d.id; }); if (kindSel !== 'chat') { var ti = titleIn.value.trim(); if (ti.length < 3) { msg.className = 'msg err'; msg.textContent = 'Bitte gib eine Kurzbeschreibung an (mindestens 3 Zeichen).'; titleIn.focus(); return; } } else if (!t && !(kindSel === 'chat' && ids.length)) return; if (admin && kindSel === 'chat' && !S.sup.sel) { msg.textContent = 'Wähle erst eine Unterhaltung.'; return; }
      if (kindSel !== 'chat') { var kd = kindSel; api('/api/feedback.php?a=add', { kind: kd, title: titleIn.value.trim(), text: t, images: ids }).then(function (r) { if (r.ok) { inp.value = ''; titleIn.value = ''; drafts = []; drawDrafts(); msg.className = 'msg'; msg.textContent = ''; popup(kd === 'feature' ? 'Idee abgeschickt' : 'Bugmeldung abgeschickt', (D.admin ? (kd === 'feature' ? 'Gespeichert. Du findest sie in der Liste „Ideen“ und kannst sie dort freigeben.' : 'Gespeichert. Du findest sie in der Liste „Bugs“ und kannst sie dort freigeben.') : (kd === 'feature' ? 'Danke! Deine Idee ist angekommen. Sie erscheint in der Liste, sobald das Team sie freigegeben hat.' : 'Danke! Deine Bugmeldung ist angekommen. Sie erscheint in der Liste, sobald das Team sie freigegeben hat.')) + (ids.length && !D.alpha && !D.admin ? ' Dein Bild erscheint für andere, sobald es freigegeben ist.' : '')); } else { msg.className = 'msg err'; msg.textContent = r.error || 'Senden nicht möglich.'; } }); return; }
      inp.value = ''; drafts = []; drawDrafts(); var wantMail = admin && mailCk.checked; api('/api/chat.php?a=send', { text: t, uid: S.sup.sel, images: ids, mail: wantMail }).then(function (r) { msg.textContent = r.ok ? (wantMail ? (r.mailed ? 'Gesendet, auch per E-Mail zugestellt.' : 'Im Chat gesendet, aber die E-Mail konnte nicht verschickt werden.') : '') : (r.error || 'Senden nicht möglich.'); msg.className = 'msg' + (r.ok && (!wantMail || r.mailed) ? '' : ' err'); pull(); });
    }
    var kindSel = 'chat', kindBox = h('div', { class: 'kindsel', role: 'radiogroup', 'aria-label': 'Art der Nachricht' }), kindHint = h('p', { class: 'hint', style: 'margin:0' });
    var KINDS = [['chat', admin ? 'Antwort' : 'Chat', 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)', ''], ['feature', 'Idee', 'Details zu deiner Idee: Was genau schwebt dir vor, und wozu wäre es gut?', 'Das Team schaut sich deine Idee an. Nach der Freigabe erscheint sie in der Liste „Ideen“ und alle können dafür abstimmen.'], ['bug', 'Bugmeldung', 'Details: Was funktioniert nicht? Wo, und was hast du gemacht?', 'Das Team schaut sich die Meldung an. Nach der Freigabe erscheint sie in der Liste „Bugs“.']];
    function drawKinds() { kindBox.textContent = ''; KINDS.forEach(function (k) { kindBox.append(h('button', { class: 'kindbtn', type: 'button', role: 'radio', 'aria-checked': String(kindSel === k[0]), onclick: function () { var was = scopeOf(); kindSel = k[0]; if (scopeOf() !== was && drafts.length) { drafts.forEach(function (d) { api('/api/image.php?a=remove', { id: d.id }); }); drafts = []; drawDrafts(); msg.className = 'msg'; msg.textContent = 'Die angehängten Bilder wurden entfernt, weil du die Art gewechselt hast.'; } inp.placeholder = k[2]; kindHint.textContent = k[3]; inp.maxLength = k[0] === 'chat' ? 1000 : 2000; titleIn.hidden = k[0] === 'chat'; form.classList.toggle('fbmode', k[0] !== 'chat'); inp.rows = k[0] === 'chat' ? 2 : 6; fileIn.accept = k[0] === 'chat' ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,text/plain,.txt,.log,.md,.csv,.json'; mailRow.hidden = !admin || k[0] !== 'chat'; drawKinds(); drawDrafts(); } }, k[1])); }); }
    drawKinds(); drawNote();
    var fileIn = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: true, hidden: true, onchange: function () { addFiles(fileIn.files); fileIn.value = ''; } });
    var form = h('form', { class: 'chatform', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); send(); } }, titleIn, inp, fileIn,
      h('button', { class: 'btn ghost attach', type: 'button', title: 'Bild oder Textdatei anhängen (Bilder auch per Strg+V einfügbar)', 'aria-label': 'Datei anhängen', onclick: function () { fileIn.click(); } }, ico(ICONS.clip)), h('button', { class: 'btn', type: 'submit' }, 'Senden'));
    inp.addEventListener('paste', function (ev) { var fs = [].filter.call((ev.clipboardData && ev.clipboardData.files) || [], function (f) { return /^image\//.test(f.type); }); if (fs.length) { ev.preventDefault(); addFiles(fs); } });
    inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && !ev.shiftKey && (admin || kindSel === 'chat')) { ev.preventDefault(); send(); } });
    function fmtChat(t) { try { var d = new Date(t.replace(' ', 'T')), now = new Date(), hm = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }); return d.toDateString() === now.toDateString() ? hm : LONG.format(d).slice(0, 6) + ' ' + hm; } catch (_) { return ''; } }
    function drawThreads() {
      threads.textContent = ''; if (admin) threads.append(pick); else if (staff && dmPeers.length) threads.append(dmPick); if (!S.sup.threads.length) threads.append(h('p', { class: 'hint' }, 'Noch keine Nachrichten.')); var lastG = null;
      S.sup.threads.forEach(function (t) { var g = t.id === 'team' ? 'Team' : (admin ? 'Nutzer' : (t.id === 'me' ? 'Administrator' : 'Direkt')); if (g !== lastG) { threads.append(h('p', { class: 'thgrp' }, g)); lastG = g; } threads.append(h('button', { class: 'thr', type: 'button', 'aria-current': String(S.sup.sel == t.id), onclick: function () { S.sup.sel = t.id; drawThreads(); pull(); } }, h('span', { class: 't1' }, h('span', null, t.username || ('Mitglied Nr. ' + t.id)), +t.unread > 0 ? h('span', { class: 'badge' }, String(t.unread)) : null), h('span', { class: 't2' }, (+t.last_admin ? 'Du: ' : '') + (t.last_body || '')))); });
    }
    function pull() {
      var go2 = function () {
        teamMode();
        if (staff && S.sup.sel === 'team') { teamPull(); return; }
        if (isDm()) { dmPull(); return; }
        if (admin && !S.sup.sel) { log.textContent = ''; log.append(h('p', { class: 'chatempty' }, 'Wähle links eine Unterhaltung.')); return; }
        api('/api/chat.php?a=thread' + (admin ? '&uid=' + S.sup.sel : '')).then(function (r) {
          var atEnd = log.scrollHeight - log.scrollTop - log.clientHeight < 40 || !log.childNodes.length; log.textContent = '';
          if (admin) mailCk.checked = !(r.messages || []).length;
          if (!(r.messages || []).length) log.append(h('p', { class: 'chatempty' }, h('b', null, 'Noch keine Nachrichten.'), h('br'), admin ? 'Hier ist noch nichts geschrieben worden. Deine Nachricht erscheint auch im Chat des Nutzers.' : 'Schreibe unten deine erste Nachricht. Antworten des Administrators erscheinen hier.'));
          (r.messages || []).forEach(function (m) { var mine = admin ? +m.from_admin === 1 : +m.from_admin === 0, isSys = !!r.system || /^Systemnachricht:/.test(m.body || ''), bodyTxt = isSys ? String(m.body || '').replace(/^Systemnachricht:\s*/, '') : m.body;
            log.append(h('div', { class: 'bub ' + (isSys ? 'them sys' : mine ? 'me' : 'them') }, isSys ? h('b', { class: 'syslbl' }, 'Systemnachricht') : null, bodyTxt, (m.images || []).map(function (id) { return picLink(id, 'inchat'); }), h('small', null, fmtChat(m.created_at), mine ? h('span', { class: 'ticks' + (m.read_at ? ' seen' : ''), title: m.read_at ? 'Gelesen' : 'Gesendet' }, m.read_at ? '✓✓' : '✓') : null))); });
          if (atEnd) log.scrollTop = log.scrollHeight; unreadPoll();
        });
      };
      if (staff) api('/api/chat.php?a=threads').then(function (r) {
        var l = r.threads || []; if (!admin) { var tix = l.filter(function (x) { return x.id === 'team'; }), dms = l.filter(function (x) { return x.id !== 'team'; }); l = tix.concat([{ id: 'me', username: 'Chat mit dem Administrator', last_body: '', last_admin: 0, unread: 0 }], dms); if (isDm() && !l.some(function (x) { return x.id === S.sup.sel; })) { var pr = dmPeers.filter(function (x) { return 'dm' + x.id === S.sup.sel; })[0]; if (pr) l.push({ id: S.sup.sel, username: pr.username, last_body: '', last_admin: 0, unread: 0 }); } }
        S.sup.threads = l;
        if (!S.sup.sel) { var tm = l.filter(function (x) { return x.id === 'team'; })[0]; S.sup.sel = tm && +tm.unread > 0 ? 'team' : (admin ? ((l.filter(function (x) { return x.id !== 'team'; })[0] || tm || {}).id || null) : 'me'); }
        drawThreads(); go2(); }); else go2();
    }

    /* Team-Chat (Admin und Alphatester) */
    var pollBox = h('div', { class: 'pollform', hidden: true }), teamSending = false, lastScope = null;
    function teamMode() {
      var tm = staff && S.sup.sel === 'team', dm = isDm();
      var sc = scopeOf(); if (lastScope && sc !== lastScope && drafts.length) { drafts.forEach(function (d) { api('/api/image.php?a=remove', { id: d.id }); }); drafts = []; drawDrafts(); } lastScope = sc;
      [kindBox, kindHint, mailRow, titleIn].forEach(function (e) { if (e) e.hidden = tm || dm || (e === mailRow && !admin) || (e === titleIn && kindSel === 'chat'); });
      if (tm) attNote.textContent = 'Bis zu 3 Anhänge pro Nachricht: Bilder (JPG, PNG, WebP) und Textdateien (.txt, .log, .md, .csv, .json, bis 200 KB). Zeige bitte keine fremden Personen oder privaten Daten.'; else drawNote();
      pollBtn.hidden = !tm; if (!tm) pollBox.hidden = true;
      var atb = form.querySelector('.attach'); if (atb) atb.hidden = dm;
      if (dm) { var pn = (S.sup.threads.filter(function (x) { return x.id === S.sup.sel; })[0] || {}).username || 'Alphatester'; attNote.textContent = 'Direktnachricht nur zwischen dir und ' + pn + '. Der Administrator liest nicht mit.'; composerCard.querySelector('h3').textContent = 'Nachricht an ' + pn; chatCard.querySelector('h3').textContent = 'Direktchat mit ' + pn; inp.placeholder = 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)'; return; }
      composerCard.querySelector('h3').textContent = tm ? 'Nachricht an das Team' : (admin ? 'Antworten' : 'Neue Nachricht');
      chatCard.querySelector('h3').textContent = tm ? 'Team-Chat: Admin und Alphatester' : (admin ? 'Unterhaltung' : 'Unterhaltung mit dem Administrator');
      inp.placeholder = tm ? 'Nachricht an das Team … (Enter sendet, Umschalt+Enter für neue Zeile)' : 'Nachricht schreiben … (Enter sendet, Umschalt+Enter für neue Zeile)';
    }
    function teamSend() {
      var t = inp.value.trim(), ids = drafts.map(function (d) { return d.id; }); if ((!t && !ids.length) || teamSending) return; teamSending = true;
      api('/api/chat.php?a=team_send', { text: t, images: ids }).then(function (r) { teamSending = false; if (r && r.ok) { inp.value = ''; drafts = []; drawDrafts(); msg.textContent = ''; teamPull(true); } else { msg.className = 'msg err'; msg.textContent = (r && r.error) || 'Senden nicht möglich.'; } });
    }
    function drawPollForm() {
      pollBox.textContent = ''; pollBox.hidden = false;
      var q = h('input', { type: 'text', maxlength: '300', placeholder: 'Frage', 'aria-label': 'Frage der Abstimmung' }), opts = [], list = h('div', { class: 'pollopts' });
      function addOpt(v) { var i = h('input', { type: 'text', maxlength: '120', placeholder: 'Antwort ' + (opts.length + 1), 'aria-label': 'Antwort ' + (opts.length + 1), value: v || '' }); opts.push(i); list.append(i); }
      addOpt('Ja'); addOpt('Nein');
      var pm = h('span', { class: 'msg', style: 'margin:0' });
      pollBox.append(h('h4', { class: 'logcat' }, 'Neue Abstimmung'), q, list,
        h('p', null, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { if (opts.length < 8) addOpt(''); } }, 'Antwort hinzufügen'), ' ',
          h('button', { class: 'btn small', type: 'button', onclick: function () { api('/api/chat.php?a=team_send', { poll: { q: q.value, options: opts.map(function (o) { return o.value; }) } }).then(function (r) { if (r && r.ok) { pollBox.hidden = true; teamPull(true); } else { pm.className = 'msg err'; pm.textContent = (r && r.error) || 'Das hat nicht geklappt.'; } }); } }, 'Abstimmung senden'), ' ',
          h('button', { class: 'btn ghost small', type: 'button', onclick: function () { pollBox.hidden = true; } }, 'Abbrechen'), ' ', pm));
      q.focus();
    }
    function dmPull(scroll) {
      var sel = S.sup.sel;
      api('/api/chat.php?a=dm_thread&uid=' + String(sel).slice(2)).then(function (r) {
        if (S.sup.sel !== sel) return;
        var atEnd = scroll || log.scrollHeight - log.scrollTop - log.clientHeight < 40 || !log.childNodes.length; log.textContent = '';
        if (!r || r.error) { log.append(h('p', { class: 'warnbox' }, (r && r.error) || 'Das hat nicht geklappt.')); return; }
        if (!(r.messages || []).length) log.append(h('p', { class: 'chatempty' }, h('b', null, 'Noch keine Nachrichten.'), h('br'), 'Schreibe unten die erste Nachricht. Nur ihr beide seht diesen Chat.'));
        (r.messages || []).forEach(function (m) { log.append(h('div', { class: 'bub ' + (m.mine ? 'me' : 'them') }, m.body, h('small', null, fmtChat(m.created_at)))); });
        if (atEnd) log.scrollTop = log.scrollHeight; unreadPoll();
      });
    }
    function teamPull(scroll) {
      api('/api/chat.php?a=team_thread').then(function (r) {
        if (S.sup.sel !== 'team') return;
        var atEnd = scroll || log.scrollHeight - log.scrollTop - log.clientHeight < 40 || !log.childNodes.length; log.textContent = '';
        if (!r || r.error) { log.append(h('p', { class: 'warnbox' }, (r && r.error) || 'Das hat nicht geklappt.')); return; }
        if (!(r.messages || []).length) log.append(h('p', { class: 'chatempty' }, h('b', null, 'Noch keine Nachrichten.'), h('br'), 'Hier schreiben Admin und Alphatester untereinander. Mit „Abstimmung“ kannst du eine Frage mit Antworten stellen.'));
        (r.messages || []).forEach(function (m) {
          var who = h('small', { class: 'who' }, m.name + ' · ' + fmtChat(m.created_at));
          if (!+m.is_poll) { log.append(h('div', { class: 'bub ' + (m.mine ? 'me' : 'them') }, m.body, (m.images || []).map(function (im) { return im.kind === 'text' ? h('a', { class: 'txtatt', href: imgUrl(im.id), target: '_blank', rel: 'noopener' }, '📄 ' + (im.name || 'Textdatei')) : picLink(im.id, 'inchat'); }), who)); return; }
          var total = (m.options || []).reduce(function (a, o) { return a + o.votes; }, 0);
          var cl = !!m.closed, pb = h('div', { class: 'bub poll ' + (m.mine ? 'me' : 'them') + (cl ? ' closed' : '') }, h('div', { class: 'pqrow' }, h('b', { class: 'pq' }, m.body),
            m.can_close ? h('button', { class: 'lockbtn', type: 'button', title: cl ? 'Abstimmung wieder öffnen' : 'Abstimmung schließen', 'aria-label': cl ? 'Abstimmung wieder öffnen' : 'Abstimmung schließen', 'aria-pressed': String(cl), onclick: function () { if (!cl && !confirm('Abstimmung schließen? Danach kann niemand mehr abstimmen.')) return; api('/api/chat.php?a=team_close', { msg: m.id }).then(function (z) { if (z && z.error) { msg.className = 'msg err'; msg.textContent = z.error; } teamPull(); }); } }, ico(cl ? ICONS.lock : ICONS.unlock)) : (cl ? h('span', { class: 'lockbtn static', title: 'Geschlossen' }, ico(ICONS.lock)) : null)));
          (m.options || []).forEach(function (o) {
            var pct = total ? Math.round(o.votes * 100 / total) : 0, sel = m.my_vote === o.id;
            pb.append(h('button', { class: 'popt' + (sel ? ' on' : ''), type: 'button', 'aria-pressed': String(sel), title: cl ? 'Die Abstimmung ist geschlossen' : (sel ? 'Stimme zurücknehmen' : 'Dafür stimmen'), disabled: cl, onclick: function () { api('/api/chat.php?a=team_vote', { msg: m.id, opt: o.id }).then(function () { teamPull(); }); } },
              h('span', { class: 'bar', style: 'width:' + pct + '%' }), h('span', { class: 'pl' }, (sel ? '✓ ' : '') + o.label), h('span', { class: 'pn' }, o.votes + (total ? ' · ' + pct + ' %' : ''))));
          });
          pb.append(h('small', { class: 'who' }, 'Abstimmung von ' + m.name + ' · ' + fmtChat(m.created_at) + ' · ' + total + (total === 1 ? ' Stimme' : ' Stimmen') + (cl ? ' · geschlossen' : ''))); log.append(pb);
        });
        if (atEnd) log.scrollTop = log.scrollHeight; unreadPoll();
      });
    }
    var chatCard = setCard(admin ? 'Unterhaltung' : 'Unterhaltung mit dem Administrator', [log]), composerCard = setCard(admin ? 'Antworten' : 'Neue Nachricht', [pollBox, form, admin ? mailRow : null, draftBox, attNote, msg], 'composer');
    var pollBtn = h('button', { class: 'btn ghost', type: 'button', hidden: true, onclick: drawPollForm }, 'Abstimmung'); form.insertBefore(pollBtn, form.querySelector('button[type=submit]'));
    grid.append(threads, h('div', { class: 'chat' }, chatCard, composerCard)); p.append(grid); devMark(p, 'kontakt'); mainEl.append(p); pull(); supTimer = setInterval(pull, 15000);
  }
  /* Idee und Bug melden: Formular, das auf den Seiten Ideen und Bugs aufklappt */
  var RK = { feature: ['Idee melden', 'Vorschlag für die Seite', 'Dir fehlt etwas oder du hast einen Vorschlag? Nach der Freigabe können alle dafür abstimmen.', ICONS.bulb, 'Kurzbeschreibung (erscheint als Titel in der Liste)', 'Details zu deiner Idee: Was genau schwebt dir vor, und wozu wäre es gut?', 'Idee abschicken'], bug: ['Bug melden', 'Etwas funktioniert nicht', 'Etwas funktioniert nicht richtig? Beschreibe kurz, wo es passiert und was du gemacht hast.', ICONS.bug, 'Kurzbeschreibung (erscheint als Titel in der Liste)', 'Details: Was funktioniert nicht? Wo, und was hast du gemacht?', 'Bug abschicken'] };
  function reportForm(kind, slot, onClose, onSent) {
    var r = RK[kind], rep = { drafts: [], closed: false };
    function closeReport(drop) { if (drop) rep.drafts.forEach(function (d) { api('/api/image.php?a=remove', { id: d.id }); }); rep.drafts = []; rep.closed = true; slot.textContent = ''; if (onClose) onClose(); }
    slot.textContent = '';
      var catSel = h('select', { class: 'repcat', 'aria-label': 'Kategorie' }, h('option', { value: '' }, kind === 'bug' ? 'Kategorie: Wo tritt der Fehler auf? (optional)' : 'Kategorie: Worum geht es? (optional)'), FBCATS[kind].map(function (c) { return h('option', { value: c[0] }, c[1]); })), ti = h('input', { type: 'text', maxlength: '120', class: 'fbtitle', 'aria-label': 'Kurzbeschreibung', placeholder: r[4], autocomplete: 'off' }), tx = h('textarea', { rows: '4', maxlength: '1000', 'aria-label': 'Details', placeholder: r[5] }), rmsg = h('p', { class: 'msg', role: 'status' }), dbox = h('div', { class: 'drafts' }), note = h('p', { class: 'hint picnote' });
      var fi = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,text/plain,.txt,.log,.md,.csv,.json', multiple: true, hidden: true });
      function drawRD() { dbox.textContent = ''; rep.drafts.forEach(function (d) { dbox.append(h('span', { class: 'draft' + (d.name ? ' txt' : '') }, d.name ? h('span', { class: 'dname' }, '📄 ' + d.name) : h('img', { src: d.url, alt: 'Angehängtes Bild' }), h('button', { type: 'button', class: 'x', 'aria-label': 'Anhang entfernen', onclick: function () { rep.drafts = rep.drafts.filter(function (e) { return e !== d; }); api('/api/image.php?a=remove', { id: d.id }); drawRD(); } }, '×'))); }); note.textContent = 'Anhänge: ' + rep.drafts.length + ' von 3. Erlaubt sind Bilder (JPG, PNG, WebP) und Textdateien (.txt, .log, .md, .csv, .json, bis 200 KB). Zeige bitte keine fremden Personen oder privaten Daten.'; }
      function addRF(files) {
        [].slice.call(files).forEach(function (f) {
          if (rep.drafts.length >= 3) { rmsg.className = 'msg err'; rmsg.textContent = 'Höchstens 3 Anhänge.'; return; }
          var isTxt = !/^image\//.test(f.type) && (/^text\//.test(f.type) || /\.(txt|log|md|csv|json)$/i.test(f.name)); rmsg.className = 'msg'; rmsg.textContent = isTxt ? 'Datei wird hochgeladen …' : 'Bild wird hochgeladen …';
          (isTxt ? uploadText(f, 'feedback') : uploadImage(f, 'feedback')).then(function (d) { if (rep.closed) { api('/api/image.php?a=remove', { id: d.id }); return; } rep.drafts.push(d); rmsg.textContent = ''; drawRD(); }).catch(function (e) { rmsg.className = 'msg err'; rmsg.textContent = e.message; });
        });
      }
      fi.addEventListener('change', function () { addRF(fi.files); fi.value = ''; });
      tx.addEventListener('paste', function (ev) { var fs = [].filter.call((ev.clipboardData && ev.clipboardData.files) || [], function (f) { return /^image\//.test(f.type); }); if (fs.length) { ev.preventDefault(); addRF(fs); } });
      var sending = false;
      function submit(ev) {
        ev.preventDefault(); if (sending) return; var t = ti.value.trim(); if (t.length < 3) { rmsg.className = 'msg err'; rmsg.textContent = 'Bitte gib eine Kurzbeschreibung an (mindestens 3 Zeichen).'; ti.focus(); return; }
        var ids = rep.drafts.map(function (d) { return d.id; }); sending = true;
        api('/api/feedback.php?a=add', { kind: kind, category: catSel.value, title: t, text: tx.value.trim(), images: ids }).then(function (z) {
          sending = false;
          if (z && z.ok) { closeReport(false); if (onSent) onSent(); popup(kind === 'feature' ? 'Idee abgeschickt' : 'Bugmeldung abgeschickt', (D.admin ? (kind === 'feature' ? 'Gespeichert. Du findest sie in der Liste „Ideen“ und kannst sie dort freigeben.' : 'Gespeichert. Du findest sie in der Liste „Bugs“ und kannst sie dort freigeben.') : (kind === 'feature' ? 'Danke! Deine Idee ist angekommen. Sie erscheint in der Liste, sobald das Team sie freigegeben hat.' : 'Danke! Deine Bugmeldung ist angekommen. Sie erscheint in der Liste, sobald das Team sie freigegeben hat.')) + (ids.length && !D.alpha && !D.admin ? ' Dein Bild erscheint für andere, sobald es freigegeben ist.' : '')); }
          else { rmsg.className = 'msg err'; rmsg.textContent = (z && z.error) || 'Senden nicht möglich.'; }
        });
      }
      drawRD();
      slot.append(setCard(r[0], [h('p', { class: 'hint' }, kind === 'feature' ? 'Das Team schaut sich deine Idee an. Nach der Freigabe erscheint sie in der Liste „Ideen“ und alle können dafür abstimmen.' : 'Das Team schaut sich die Meldung an. Nach der Freigabe erscheint sie in der Liste „Bugs“.'),
        h('form', { class: 'repform', autocomplete: 'off', onsubmit: submit }, catSel, ti, tx, fi, h('div', { class: 'reprow' }, h('button', { class: 'btn ghost', type: 'button', onclick: function () { fi.click(); } }, 'Anhängen'), h('button', { class: 'btn ghost', type: 'button', onclick: function () { closeReport(true); } }, 'Abbrechen'), h('button', { class: 'btn', type: 'submit' }, r[6]))), dbox, note, rmsg], 'composer'));
      ti.focus();
    return { close: closeReport };
  }
  var FBCATS = { bug: [['calendar', 'Kalender'], ['lists', 'Listen und Einträge'], ['search', 'Suche und Hinzufügen'], ['community', 'Community'], ['news', 'News'], ['contact', 'Kontakt und Chat'], ['account', 'Konto und Anmeldung'], ['email', 'E-Mails'], ['ui', 'Menü und Darstellung'], ['data', 'Falsche oder fehlende Daten'], ['other', 'Sonstiges']], feature: [['feature', 'Neues Feature'], ['improve', 'Verbesserung einer Funktion'], ['source', 'Neue Kategorie oder Datenquelle'], ['design', 'Aussehen und Design'], ['mail', 'E-Mails und Benachrichtigungen'], ['community', 'Community'], ['calendar', 'Kalender'], ['mobile', 'Handy und Bedienung'], ['content', 'Inhalte und Daten'], ['other', 'Sonstiges']] };
  function catLabel(kind, key) { var l = (FBCATS[kind] || []).filter(function (c) { return c[0] === key; })[0]; return l ? l[1] : ''; }
  function feedbackView(kind) {
    if (!S.preview && (D.admin || D.alpha)) fbPoll();
    var bug = kind === 'bug', staff = !S.preview && !!(D.admin || D.alpha), p = h('section', { class: 'panel' }, h('h2', { class: 'sec' }, bug ? 'Bugs' : 'Ideen'),
      h('p', { class: 'hint' }, staff ? (bug ? 'Meldungen von Nutzern. Neue Meldungen warten im Reiter „Freischaltungen“, bis du sie freigibst; freigegebene sehen alle. Mit den Knöpfen legst du fest, wie dringend sie sind.' : 'Ideen von allen Nutzern. Neue Ideen warten im Reiter „Freischaltungen“, bis du sie freigibst; freigegebene sehen alle und können dafür abstimmen.') : (bug ? 'Bekannte Fehler und ihr Stand. Neue Fehler meldest du mit dem Knopf „Bug melden“ oben rechts.' : 'Ideen von allen Nutzern, sortiert nach Stimmen. Mit dem Daumen stimmst du für eine Idee, ein zweiter Klick nimmt die Stimme zurück. Neue Ideen schreibst du mit dem Knopf „Idee melden“ oben rechts.'))),
      box = h('div', { class: 'fblist' }); p.append(box); mainEl.append(alphaWrap(bug ? 'bugs' : 'feat', p)); box.append(h('p', { class: 'state pulse' }, 'Lade …'));
    var PHASE = { brainstorm: 'Im Brainstorming', progress: 'In Bearbeitung', postponed: 'Zurückgestellt' };
  var PRIO = { urgent: ['Dringend', 'urgent'], normal: ['Normal', 'normal'], low: ['Unwichtig', 'low'] };
    function when(t) { try { return LONG.format(new Date(String(t).replace(' ', 'T'))); } catch (_) { return ''; } }
    S.fbTab = S.fbTab || {}; var tabKey = bug ? 'bug' : 'feature', lastR = null, seen = null, firstLoad = true, tabSeen = {};
    function grp(x) { return !x.released_at ? 'rel' : (x.done_at ? 'done' : (x.phase === 'postponed' ? 'post' : 'cur')); }
    function isNew(x) { if (!staff || !seen) return false; var g = grp(x), t = g === 'cur' ? x.released_at : (g === 'done' ? x.done_at : null); return !!t && String(t) > String(seen); }
    var TABS = [['cur', 'Aktuell'], ['done', 'Erledigt/Zurückgezogen'], ['post', 'Zurückgestellt'], ['rel', 'Freischaltungen']], tbtn = {};
    if (!TABS.some(function (t) { return t[0] === S.fbTab[tabKey]; })) S.fbTab[tabKey] = 'cur';
    var tbar = h('div', { class: 'admtabs infotabs', role: 'tablist', 'aria-label': bug ? 'Bugs' : 'Ideen' });
    TABS.forEach(function (t) { var b = h('button', { class: 'admtab', type: 'button', role: 'tab', onclick: function () { S.fbTab[tabKey] = t[0]; tabSeen[t[0]] = true; if (lastR) draw(lastR); } }, t[1]); tbtn[t[0]] = b; tbar.append(b); });
    var repHost = h('div', { class: 'repslot' }), repForm = null, repBtn = h('button', { class: 'admtab addrep', type: 'button', 'aria-expanded': 'false', onclick: function () {
      if (repForm) { repForm.close(true); return; }
      repBtn.setAttribute('aria-expanded', 'true'); repBtn.classList.add('open');
      repForm = reportForm(kind, repHost, function () { repForm = null; repBtn.setAttribute('aria-expanded', 'false'); repBtn.classList.remove('open'); }, function () { S.fbTab[tabKey] = 'rel'; reload(); });
    } }, '＋ ' + (bug ? 'Bug melden' : 'Idee melden'));
    tbar.append(repBtn); mainEl.prepend(tbar); tbar.after(repHost);
    function draw(r) {
      lastR = r; box.textContent = ''; var l = r.items || [];
      if (r.error) { box.append(h('p', { class: 'warnbox' }, r.error)); return; }
      var cnt = { cur: 0, done: 0, post: 0, rel: 0 }, newN = { cur: 0, done: 0 }; l.forEach(function (x) { cnt[grp(x)]++; if (isNew(x)) newN[grp(x)]++; });
      TABS.forEach(function (t) { var on = S.fbTab[tabKey] === t[0], b = tbtn[t[0]]; b.className = 'admtab' + (on ? ' on' : ''); b.setAttribute('aria-selected', on ? 'true' : 'false'); b.textContent = t[1] + (cnt[t[0]] ? ' (' + cnt[t[0]] + ')' : ''); var nn = t[0] === 'rel' ? (staff ? cnt.rel : 0) : (tabSeen[t[0]] ? 0 : newN[t[0]]); if (on && t[0] !== 'rel') tabSeen[t[0]] = true; if (nn && !(on && t[0] !== 'rel')) b.append(h('span', { class: 'badge', 'aria-label': nn + (t[0] === 'rel' ? ' offen: Freischaltungen und Neues' : ' neu seit deinem letzten Besuch') }, String(nn))); });
      var shown = l.filter(function (x) { return grp(x) === S.fbTab[tabKey]; });
      S.fbMine = S.fbMine || {}; var totAll = shown.length, mineAll = shown.filter(function (x) { return !!+x.mine; }).length; if (S.fbMine[tabKey] && !mineAll) S.fbMine[tabKey] = false; var mineOn = !!S.fbMine[tabKey]; if (mineOn) shown = shown.filter(function (x) { return !!+x.mine; });
      S.fbCat = S.fbCat || {}; var cc = {}, ccN = 0; shown.forEach(function (x) { if (x.category && catLabel(bug ? 'bug' : 'feature', x.category)) { cc[x.category] = (cc[x.category] || 0) + 1; ccN++; } });
      if (S.fbCat[tabKey] && !cc[S.fbCat[tabKey]]) S.fbCat[tabKey] = '';
      if (ccN || mineAll) {
        var cur = S.fbCat[tabKey] || '', chips = h('div', { class: 'catfilt', role: 'group', 'aria-label': 'Nach Kategorie filtern' });
        function chip(key, label, n) { chips.append(h('button', { class: 'cchip', type: 'button', 'aria-pressed': String(cur === key && (key !== '' || !mineOn)), onclick: function () { S.fbCat[tabKey] = key; if (key === '') S.fbMine[tabKey] = false; draw(lastR); } }, label, h('small', null, String(n)))); }
        chip('', 'Alle', totAll);
        if (mineAll) chips.append(h('button', { class: 'cchip', type: 'button', 'aria-pressed': String(mineOn), title: 'Nur die Beiträge anzeigen, die du selbst eingereicht hast', onclick: function () { S.fbMine[tabKey] = !mineOn; draw(lastR); } }, 'Meine', h('small', null, String(mineAll)))); (FBCATS[bug ? 'bug' : 'feature'] || []).forEach(function (c) { if (cc[c[0]]) chip(c[0], c[1], cc[c[0]]); });
        box.append(chips); if (cur) shown = shown.filter(function (x) { return x.category === cur; });
      }
      if (!shown.length) { box.append(h('p', { class: 'hint' }, S.fbTab[tabKey] === 'done' ? 'Noch nichts erledigt oder zurückgezogen.' : S.fbTab[tabKey] === 'post' ? (bug ? 'Keine Bugs zurückgestellt.' : 'Keine Ideen zurückgestellt.') : S.fbTab[tabKey] === 'rel' ? (staff ? 'Nichts wartet auf Freischaltung.' : 'Keiner deiner Beiträge wartet auf Freischaltung. Neue Beiträge erscheinen hier, bis das Team sie freigibt.') : (bug ? 'Noch keine Bugmeldungen.' : 'Noch keine Ideen. Schreib die erste mit „Idee melden“.'))); return; }
      var pendHead = false;
      shown.forEach(function (x) {
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
        var meta = h('span', { class: 'fbmeta' }, when(x.created_at) + (x.author && !x.gone ? ' · von ' + x.author : '') + (x.mine ? ' · von dir' : ''), x.gone ? [' · von ', h('s', { title: 'Das Konto wurde gelöscht' }, x.author), ' (Konto gelöscht)'] : null, x.author_tier === 'admin' ? [' ', h('span', { class: 'tier' }, 'Admin')] : x.author_tier === 'alpha' ? [' ', h('span', { class: 'tier alpha' }, 'Alphatester')] : null);
        var noteEl = x.status_note ? h('div', { class: 'fbnote' }, h('div', { class: 'fbnh' }, h('b', null, +x.rejected ? 'Anmerkung zur Ablehnung' : 'Anmerkung zur Zurückstellung'), h('span', null, when(x.status_note_at) + ' · von ' + x.status_note_by + ' ', x.status_note_tier === 'admin' ? h('span', { class: 'tier' }, 'Admin') : h('span', { class: 'tier alpha' }, 'Alphatester'))), h('div', null, x.status_note)) : null;
        var body = h('div', { class: 'fbbody', hidden: true }, (bug && x.done_at) ? h('p', { class: 'hint', style: 'margin:0 0 6px' }, (x.withdrawn_at ? 'Zurückgezogen am ' : 'Erledigt am ') + when(x.done_at)) : null, x.body ? h('div', { class: 'fbdetail' }, editedSpan(x, 'body', x.body)) : null, noteEl, attInfo, pics);
        var editBtn = null;
        if (x.mine && !x.gone && !x.done_at && !+x.rejected) {
          var eT = h('input', { type: 'text', maxlength: '120', value: x.title, 'aria-label': 'Kurzbeschreibung', style: 'width:100%' }), eB = h('textarea', { rows: '6', maxlength: '2000', 'aria-label': 'Details', style: 'width:100%;margin-top:8px' }), eM = h('span', { class: 'msg', style: 'margin:0' }), eBox = h('div', { class: 'fbedit', hidden: true, style: 'margin:10px 0' });
          eB.value = x.body || '';
          eBox.append(h('p', { class: 'hint', style: 'margin:0 0 6px' }, 'Andere Nutzer sehen, dass der Text bearbeitet wurde, und können die frühere Fassung per Maus-Hinweis ansehen.'), eT, eB,
            h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' }, h('button', { class: 'btn small', type: 'button', onclick: function () { eM.className = 'msg'; eM.textContent = 'Speichere …'; api('/api/feedback.php?a=edit', { id: x.id, title: eT.value, text: eB.value }).then(function (r2) { if (r2.ok) reload(); else { eM.className = 'msg err'; eM.textContent = r2.error || 'Das hat nicht geklappt.'; } }); } }, 'Speichern'), h('button', { class: 'btn ghost small', type: 'button', onclick: function () { eBox.hidden = true; } }, 'Abbrechen'), eM));
          body.append(eBox);
          editBtn = h('button', { class: 'btn ghost small', type: 'button', title: 'Meinen Beitrag bearbeiten', onclick: function () { body.hidden = false; eBox.hidden = !eBox.hidden; } }, 'Bearbeiten');
        }
        var head = h('button', { class: 'fbhead', type: 'button', 'aria-expanded': 'false', onclick: function () { body.hidden = !body.hidden; head.setAttribute('aria-expanded', String(!body.hidden)); if (typeof acts !== 'undefined' && acts) acts.hidden = body.hidden; S.fbOpen = S.fbOpen || {}; if (body.hidden) delete S.fbOpen[x.id]; else S.fbOpen[x.id] = 1; } }, h('span', { class: 'fbt' }, x.category && catLabel(bug ? 'bug' : 'feature', x.category) ? h('span', { class: 'fbcat' }, catLabel(bug ? 'bug' : 'feature', x.category)) : null, bug && x.bug_no ? h('span', { class: 'bugno' }, '#' + x.bug_no + ' ') : null, editedSpan(x, 'title', x.title), nAtt ? h('span', { class: 'attchip', title: pl(nAtt, 'Anhang', 'Anhänge') }, '📎 ' + nAtt) : null), bug ? h('span', { class: 'fbslot sp' }, h('span', { class: 'prio ' + x.priority }, PRIO[x.priority][0])) : null, h('span', { class: 'fbslot ss' }, (!bug && +x.rejected) ? h('span', { class: 'prio low' }, 'abgelehnt') : null, (PHASE[x.phase] && !x.done_at && !+x.rejected) ? h('span', { class: 'prio ph-' + x.phase }, PHASE[x.phase]) : null, (x.withdrawn_at && !+x.rejected) ? ((staff && +x.new_wd) ? h('span', { class: 'prio newwd', title: 'Die einreichende Person hat den Beitrag in den letzten 24 Stunden zurückgezogen' }, 'Neu zurückgezogen') : h('span', { class: 'prio low' }, 'zurückgezogen')) : null), meta);
        var cBox = null, cBtn = null;
        if (staff && lastR && lastR.cview) { // interne Kommentare (nur Admin und Alphatester)
          var cl = h('div', { class: 'fbcomments' }), cf = h('div', { class: 'fbcform', hidden: true }), cmsg = h('span', { class: 'msg', style: 'margin:0' });
          (x.comments || []).forEach(function (m) { cl.append(h('div', { class: 'fbcm' }, h('div', { class: 'fbcwho' }, m.name + ' · ' + fmtC(m.at)), h('div', { class: 'fbctext' }, m.body))); });
          if (lastR.cwrite) {
            var cta = h('textarea', { rows: '3', maxlength: '1000', placeholder: 'Interner Kommentar (nur Admin und Alphatester sehen ihn)', 'aria-label': 'Kommentar' });
            cf.append(cta, h('p', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0' },
              h('button', { class: 'btn small', type: 'button', onclick: function () { var t = cta.value.trim(); if (!t) return; api('/api/feedback.php?a=comment', { id: x.id, text: t }).then(function (r2) { if (r2.ok) { S.fbOpen = S.fbOpen || {}; S.fbOpen[x.id] = 1; reload(); } else { cmsg.className = 'msg err'; cmsg.textContent = r2.error || 'Das hat nicht geklappt.'; } }); } }, 'Kommentar speichern'),
              h('button', { class: 'btn ghost small', type: 'button', onclick: function () { cf.hidden = true; } }, 'Abbrechen'), cmsg));
            cBtn = h('button', { class: 'btn ghost small', type: 'button', title: 'Internen Kommentar schreiben', onclick: function () { body.hidden = false; head.setAttribute('aria-expanded', 'true'); cf.hidden = !cf.hidden; if (!cf.hidden) cta.focus(); } }, 'Kommentar');
          }
          cBox = h('div', { class: 'fbcbox' }, cl, cf);
          if ((x.comments || []).length || lastR.cwrite) body.append(cBox);
        }
        if (S.fbOpen && S.fbOpen[x.id]) { body.hidden = false; head.setAttribute('aria-expanded', 'true'); }
        var acts = h('div', { class: 'fbacts' });
        var noteForm = h('div', { class: 'fbnoteform', hidden: true });
        function askNote(label, send) {
          noteForm.textContent = ''; noteForm.hidden = false;
          var ta = h('textarea', { rows: '2', maxlength: '300', placeholder: 'Kurze Anmerkung für den Beitrag und die Nachricht an die Person (optional)', 'aria-label': 'Anmerkung' }), em = h('span', { class: 'msg', style: 'margin:0' });
          noteForm.append(ta, h('div', { class: 'fbnoteacts' },
            h('button', { class: 'btn small', type: 'button', onclick: function () { send(ta.value).then(function (r2) { if (r2.ok) reload(); else { em.className = 'msg err'; em.textContent = r2.error || 'Das hat nicht geklappt.'; } }); } }, label),
            h('button', { class: 'btn ghost small', type: 'button', onclick: function () { noteForm.hidden = true; noteForm.textContent = ''; } }, 'Abbrechen'), em));
          ta.focus();
        }
        var ownBtns = [];
        if (editBtn) ownBtns.push(editBtn);
        if (x.mine && !x.gone && !x.done_at && !+x.rejected) ownBtns.push(h('button', { class: 'btn ghost small', type: 'button', title: 'Beitrag zurückziehen, er gilt dann als erledigt', onclick: function () { if (confirm('Beitrag wirklich zurückziehen? Er gilt danach als erledigt und lässt sich nicht mehr bearbeiten.')) api('/api/feedback.php?a=withdraw', { id: x.id }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, 'Zurückziehen'));
        var voteBtn = null;
        var dbtn = h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': String(!!x.done_at), title: x.done_at ? 'Als offen markieren' : 'Als erledigt markieren', onclick: function () { api('/api/feedback.php?a=done', { id: x.id, on: !x.done_at }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, +x.rejected ? 'Abgelehnt' : (x.withdrawn_at ? 'Zurückgezogen' : 'Erledigt'));
        if (bug && staff && (x.released_at || x.done_at)) acts.append(dbtn);
        if (bug) { if (staff) ['urgent', 'normal', 'low'].forEach(function (k) { acts.append(h('button', { class: 'btn ghost small prio-' + k, type: 'button', 'aria-pressed': String(x.priority === k), onclick: function () { api('/api/feedback.php?a=priority', { id: x.id, priority: k }).then(function () { reload(); }); } }, PRIO[k][0])); }); }
        else {
          var on = +x.mine_vote > 0;
          if (x.released_at || staff) voteBtn = (h('button', { class: 'vote' + (on ? ' on' : ''), type: 'button', title: on ? 'Stimme zurücknehmen' : 'Dafür stimmen', 'aria-pressed': String(on), onclick: function () { api('/api/feedback.php?a=vote', { id: x.id, on: !on }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, ico(ICONS.thumb), h('b', null, String(x.votes))));
          if (staff && (x.released_at || x.done_at)) acts.append(dbtn);
          if (D.admin && !S.preview && (x.released_at || +x.rejected)) acts.append(h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': String(!!+x.rejected), onclick: function () { if (+x.rejected) api('/api/feedback.php?a=reject', { id: x.id, on: false }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); else askNote('Ablehnen', function (t) { return api('/api/feedback.php?a=reject', { id: x.id, on: true, note: t }); }); } }, +x.rejected ? 'Ablehnung zurücknehmen' : 'Ablehnen'));
        }
        if (staff && x.released_at && !x.done_at && !+x.rejected) Object.keys(PHASE).forEach(function (k) { acts.append(h('button', { class: 'btn ghost small ph-' + k, type: 'button', title: 'Status für alle sichtbar setzen, nochmal klicken setzt ihn zurück', 'aria-pressed': String(x.phase === k), onclick: function () { if (x.phase === k || k !== 'postponed') api('/api/feedback.php?a=phase', { id: x.id, phase: x.phase === k ? '' : k }).then(function (r2) { if (r2.ok) reload(); else say(r2.error || 'Das hat nicht geklappt.', true); }); else askNote('Zurückstellen', function (t) { return api('/api/feedback.php?a=phase', { id: x.id, phase: k, note: t }); }); } }, PHASE[k])); });
        if (cBtn) acts.append(cBtn);
        if (staff) {
          var rg2 = h('span', { class: 'fbright' }, h('button', { class: 'btn small' + (x.released_at ? ' ghost' : ''), type: 'button', title: x.released_at ? 'Beitrag wieder ausblenden' : 'Beitrag für alle sichtbar machen', onclick: function () { var rel = !x.released_at; api('/api/feedback.php?a=release', { id: x.id, on: rel }).then(function (r2) { if (r2.ok) { if (rel) { S.fbOpen = S.fbOpen || {}; S.fbOpen[x.id] = true; S.fbFocus = x.id; S.fbTab[tabKey] = 'cur'; } reload(); } else say(r2.error || 'Das hat nicht geklappt.', true); }); } }, x.released_at ? 'Freigabe zurücknehmen' : 'Freigeben'));
          if (D.admin) rg2.append(h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (confirm('Wirklich löschen?')) api('/api/feedback.php?a=delete', { id: x.id }).then(function () { reload(); }); } }, 'Löschen'));
          ownBtns.slice().reverse().forEach(function (b) { rg2.prepend(b); });
          acts.append(rg2);
        } else if (ownBtns.length) acts.append(h('span', { class: 'fbright' }, ownBtns));
        acts.hidden = body.hidden; // Knopfleiste erst zeigen, wenn der Beitrag angewählt (aufgeklappt) ist
        var row = h('div', { class: 'fbrow' + (bug ? ' bugrow p-' + x.priority : '') + (x.done_at ? ' done' : '') + (!x.released_at ? ' unreleased' : '') + (isNew(x) ? ' isnew' : '') }, h('div', { class: 'fbmain' }, voteBtn ? h('div', { class: 'fbline' }, voteBtn, head) : head, body, acts.childNodes.length ? acts : null, noteForm));
                box.append(row);
        if (S.fbFocus === x.id) { S.fbFocus = null; setTimeout(function () { try { row.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) { row.scrollIntoView(); } }, 60); }
      });
    }
    function reload() { var op = firstLoad && staff; firstLoad = false; api('/api/feedback.php?a=list&kind=' + kind + (op ? '&open=1' : '')).then(function (r) { if (op && r) seen = r.seen || null; draw(r); }); }
    reload();
  }
  function fmtC(t) { try { var d = new Date(String(t).replace(' ', 'T')); return d.toLocaleDateString('de-DE') + ', ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) + ' Uhr'; } catch (_) { return String(t); } }
  function editedSpan(x, field, text) {
    var ed = (x.edits || []).filter(function (e) { return e.field === field; });
    if (!ed.length) return text;
    var tip = 'Bearbeitet. Frühere Fassung' + (ed.length > 1 ? 'en' : '') + ':\n' + ed.map(function (e) { var d = new Date(String(e.at).replace(' ', 'T')); return '„' + (e.old.length > 400 ? e.old.slice(0, 400) + '…' : (e.old || '(leer)')) + '“ (bis ' + d.toLocaleDateString('de-DE') + ', ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) + ' Uhr)'; }).join('\n');
    return h('span', { class: 'edited', title: tip }, text, h('sup', { class: 'edmark', 'aria-label': 'bearbeitet' }, ' ✎'));
  }
  function fbPoll(mark) { // mark: nur beim Öffnen der Liste als „gesehen“ vermerken, nicht bei den Abfragen im Hintergrund
    if (S.preview || !(D.admin || D.alpha)) return;
    api('/api/feedback.php?a=newcount').then(function (r) {
      if (!r || !r.n) return; var n = { feat: +r.n.feature || 0, bug: +r.n.bug || 0 }, o = S.fbNew || {};
      if (n.feat !== (o.feat || 0) || n.bug !== (o.bug || 0)) { S.fbNew = n; nav(); }
    });
  }
  function unreadPoll() { api('/api/chat.php?a=unread').then(function (r) { var n = +r.unread || 0; if (n !== S.unread) { S.unread = n; var k = topbar.querySelector('.support'); if (k) { var b = k.querySelector('.badge'); if (b) b.remove(); if (n && S.view !== 'sup') k.append(h('span', { class: 'badge' }, n > 99 ? '99+' : String(n))); } } }); }

  /* ---------- Admin ---------- */
  /* ---------- Admin: Morning-Briefing (letzte 24 Stunden) ---------- */
  function briefingBox() {
    var box = h('div', { class: 'brief' }), out = h('div', null);
    function admTab(name) { try { sessionStorage.setItem('sd_admtab', name); } catch (_) {} go('adm'); }
    function toFb(kind, tab) { S.fbTab = S.fbTab || {}; S.fbTab[kind] = tab; go(kind); }
    function toSup(sel) { S.sup = S.sup || { sel: null, threads: [] }; S.sup.sel = sel; go('sup'); }
    function lnk(txt, fn) { return h('button', { class: 'namelink brlink', type: 'button', onclick: fn }, txt); }
    function row(label, val, fn, cls) { var v = val == null ? '–' : String(val); return h('li', { class: cls || '' }, fn && val ? lnk(label, fn) : h('span', null, label), h('b', null, v)); }
    function card(title, rows, foot) { return h('section', { class: 'brcard' }, h('h4', null, title), h('ul', { class: 'brlist' }, rows.filter(Boolean)), foot || null); }
    function fmt(t) { return String(t || '').slice(5, 16).replace('-', '.').replace('T', ' ').replace(' ', ' · '); }
    function hm(t) { return String(t || '').slice(11, 16); }
    var TL = { author: 'Bücher', artist: 'Musik', film: 'Filme', series: 'Serien', podcast: 'Podcasts' }, ML = { new: 'Neu angekündigt', soon: 'Termin', day: 'Tagesmails' };
    var ACT = { reveal_email: 'E-Mail aufgedeckt', delete: 'Konto gelöscht', approve: 'Konto freigeschaltet', revoke: 'Freischaltung entzogen', block: 'E-Mail gesperrt', unblock: 'Sperre aufgehoben', alpha: 'Alphatester geändert', renamereq: 'Namenswechsel verlangt' };
    function draw(r) {
      out.textContent = '';
      if (!r || !r.ok) { out.append(h('p', { class: 'warnbox' }, (r && r.error) || 'Das Briefing konnte nicht geladen werden.')); return; }
      var H = r.health || {}, ops = H.ops || {}, U = r.users || {}, C = r.contact || {}, F = r.feedback || {}, I = F.ideas || {}, B = F.bugs || {}, K = r.content || {}, S2 = r.security || {}, M = r.mail || {};
      var att = [];
      if (ops.cron_overdue) att.push(['Der Cron-Job meldet sich seit ' + Math.round((ops.cron_minutes || 0) / 60 * 10) / 10 + ' Stunden nicht', function () { admTab('Sicherheit'); }, 'bad']);
      if (ops.backup_overdue || ops.backup_error) att.push(['Datenbanksicherung überfällig oder fehlgeschlagen', function () { admTab('Sicherheit'); }, 'bad']);
      if ((H.errors || []).length) att.push([pl((H.errors || []).length, 'Schnittstelle mit Fehlern', 'Schnittstellen mit Fehlern') + ' in 24 Stunden', function () { admTab('Sicherheit'); }, 'warn']);
      if (B.urgent) att.push([pl(B.urgent, 'dringender Bug offen', 'dringende Bugs offen'), function () { toFb('bug', 'cur'); }, 'bad']);
      if (U.waiting) att.push([pl(U.waiting, 'Konto wartet', 'Konten warten') + ' auf Freischaltung', function () { admTab('Nutzer'); }, 'warn']);
      if (U.rename) att.push([pl(U.rename, 'Konto muss', 'Konten müssen') + ' den Namen ändern', function () { admTab('Nutzer'); }, 'info']);
      if (C.unread) att.push([pl(C.unread, 'ungelesene Nachricht', 'ungelesene Nachrichten') + ' im Kontakt', function () { toSup(null); }, 'warn']);
      if (C.notices_unread) att.push([pl(C.notices_unread, 'ungelesene Systemmeldung', 'ungelesene Systemmeldungen'), function () { toSup('sys'); }, 'info']);
      if (I.waiting) att.push([pl(I.waiting, 'Idee wartet', 'Ideen warten') + ' auf Freigabe', function () { toFb('feature', 'rel'); }, 'warn']);
      if (B.waiting) att.push([pl(B.waiting, 'Bugmeldung wartet', 'Bugmeldungen warten') + ' auf Freigabe', function () { toFb('bug', 'rel'); }, 'warn']);
      if (K.wishes_open) att.push([pl(K.wishes_open, 'Wunschbild offen', 'Wunschbilder offen'), function () { admTab('Nutzer'); }, 'info']);
      if (U.exit_pending) att.push([pl(U.exit_pending, 'Konto steht', 'Konten stehen') + ' zur Löschung an', function () { admTab('Nutzer'); }, 'info']);
      var attBox = h('section', { class: 'brattn' }, h('h4', null, 'Braucht deine Aufmerksamkeit'));
      if (!att.length) attBox.append(h('p', { class: 'hint', style: 'margin:0' }, 'Alles ruhig. Nichts wartet auf dich.'));
      else attBox.append(h('ul', { class: 'brattl' }, att.map(function (a) { return h('li', { class: a[2] }, h('i', { 'aria-hidden': 'true' }), lnk(a[0], a[1])); })));
      var lr = (H.last_run || [])[0];
      var zust = card('Zustand der Seite', [
        row('Letzter Cron-Lauf', ops.cron_minutes == null ? 'unbekannt' : 'vor ' + (ops.cron_minutes < 120 ? ops.cron_minutes + ' Min.' : Math.round(ops.cron_minutes / 60) + ' Std.'), null, ops.cron_overdue ? 'bad' : ''),
        row('Cron-Läufe in 24 Std.', H.runs24, null),
        row('Fehlgeschlagene Abrufe', H.fails24, function () { admTab('Sicherheit'); }, H.fails24 ? 'warn' : ''),
        row('Vorzeitig beendete Läufe', H.early24, null, H.early24 ? 'warn' : ''),
        row('Einträge älter als 12 Std.', H.stale, function () { admTab('Sicherheit'); }),
        row('Datenbanksicherung', ops.backup_minutes == null ? 'keine' : 'vor ' + (ops.backup_minutes < 120 ? ops.backup_minutes + ' Min.' : Math.round(ops.backup_minutes / 60) + ' Std.'), null, (ops.backup_overdue || ops.backup_error) ? 'bad' : '')
      ], (H.errors || []).length ? h('div', { class: 'brsub' }, h('p', { class: 'hint' }, 'Schnittstellen mit Fehlern:'), h('ul', { class: 'brmini' }, H.errors.slice(0, 5).map(function (e) { return h('li', null, h('b', null, e.source), ' ', e.kind + (+e.http_code ? ' (' + e.http_code + ')' : ''), ' · ', pl(e.cnt, 'Mal', 'Mal'), ' · zuletzt ', hm(e.last_at)); }))) : null);
      var nutz = card('Nutzer', [
        row('Neue Anmeldungen', U.new24, function () { admTab('Nutzer'); }),
        row('Aktiv in 24 Std.', U.active24, null),
        row('Löschwünsche', U.exit_req24, function () { admTab('Nutzer'); }, U.exit_req24 ? 'warn' : ''),
        row('Endgültig gelöscht', U.exits24, null),
        row('Warten auf Freischaltung', U.waiting, function () { admTab('Nutzer'); }, U.waiting ? 'warn' : ''),
        row('Nutzer insgesamt', U.total, null)
      ], (U.new_list || []).length ? h('div', { class: 'brsub' }, h('p', { class: 'hint' }, 'Neu dabei:'), h('ul', { class: 'brmini' }, U.new_list.map(function (x) { return h('li', null, lnk(x.name, function () { admTab('Nutzer'); }), ' · ', fmt(x.created_at), x.approved_at ? '' : ' · wartet'); }))) : null);
      var kont = card('Kontakt und Systemmeldungen', [
        row('Neue Nachrichten von Nutzern', C.new24, function () { toSup(null); }),
        row('Von Personen', C.senders24, null),
        row('Ungelesen insgesamt', C.unread, function () { toSup(null); }, C.unread ? 'warn' : ''),
        row('Systemmeldungen in 24 Std.', (C.notices24 || []).length, function () { toSup('sys'); }),
        row('Ungelesene Systemmeldungen', C.notices_unread, function () { toSup('sys'); })
      ], (C.notices24 || []).length ? h('div', { class: 'brsub' }, h('ul', { class: 'brmini' }, C.notices24.slice(0, 4).map(function (n) { return h('li', null, lnk(String(n.body).slice(0, 90) + (String(n.body).length > 90 ? ' …' : ''), function () { toSup('sys'); }), ' · ', hm(n.created_at)); }))) : null);
      var ideen = card('Ideen und Bugs', [
        row('Neue Ideen', I.new24, function () { toFb('feature', 'cur'); }),
        row('Neue Bugmeldungen', B.new24, function () { toFb('bug', 'cur'); }),
        row('Warten auf Freigabe (Ideen / Bugs)', (I.waiting || 0) + ' / ' + (B.waiting || 0), (I.waiting || B.waiting) ? function () { if (I.waiting) toFb('feature', 'rel'); else toFb('bug', 'rel'); } : null, (I.waiting || B.waiting) ? 'warn' : ''),
        row('Dringende Bugs offen', B.urgent, function () { toFb('bug', 'cur'); }, B.urgent ? 'bad' : ''),
        row('Offen (Ideen / Bugs)', (I.open || 0) + ' / ' + (B.open || 0), null),
        row('Kommentare in 24 Std.', F.comments24, null),
        row('Zurückgezogen (Ideen / Bugs)', (I.withdrawn24 || 0) + ' / ' + (B.withdrawn24 || 0), null)
      ], ((I.titles || []).length || (B.titles || []).length) ? h('div', { class: 'brsub' }, h('ul', { class: 'brmini' }, (I.titles || []).map(function (x) { return h('li', null, 'Idee: ', lnk(x.title, function () { toFb('feature', 'rel'); })); }).concat((B.titles || []).map(function (x) { return h('li', null, 'Bug: ', lnk(x.title, function () { toFb('bug', 'rel'); })); })))) : null);
      var ents = {}; (K.entries24 || []).forEach(function (x) { ents[x.type] = +x.n; });
      var total = 0; Object.keys(ents).forEach(function (k) { total += ents[k]; });
      var inh = card('Neue Einträge in den Listen', Object.keys(TL).map(function (k) { return row(TL[k], ents[k] || 0, null); }).concat([row('Zusammen', total, null, 'sum'), row('Offene Wunschbilder', K.wishes_open, function () { admTab('Nutzer'); })]),
        (K.top || []).length ? h('div', { class: 'brsub' }, h('p', { class: 'hint' }, 'Am häufigsten neu hinzugefügt:'), h('ul', { class: 'brmini' }, K.top.map(function (x) { return h('li', null, h('b', null, x.name), ' (', TL[x.type] || x.type, ') · ', pl(x.n, 'Nutzer', 'Nutzer')); }))) : null);
      var mails = {}, mt = 0; ((M.sent24) || []).forEach(function (x) { mails[x.kind] = +x.n; mt += +x.n; });
      var mail = card('Benachrichtigungen per Mail', Object.keys(ML).map(function (k) { return row(ML[k], mails[k] || 0, null); }).concat([row('Zusammen', mt, null, 'sum')]));
      var sic = card('Sicherheit', [
        row('Fehlgeschlagene Anmeldungen', S2.failed24, null, S2.failed24 > 20 ? 'warn' : ''),
        row('Davon verschiedene Adressen', S2.ips24, null, S2.ips24 > 10 ? 'warn' : ''),
        row('Registrierungsversuche', S2.register24, null),
        row('Wiederherstellungsversuche', S2.recover24, null),
        row('Neu gesperrte E-Mail-Adressen', S2.blocked, function () { admTab('Nutzer'); }),
        row('Admin-Aktionen im Protokoll', (S2.admin_log || []).length, function () { admTab('Sicherheit'); })
      ], (S2.admin_log || []).length ? h('div', { class: 'brsub' }, h('ul', { class: 'brmini' }, S2.admin_log.slice(0, 5).map(function (l) { return h('li', null, hm(l.created_at), ' · ', ACT[l.action] || l.action, l.target ? ' · ' + l.target : ''); }))) : null);
      var cardsAll = [r.health ? zust : null, nutz, kont, ideen, inh, mail, r.security ? sic : null].filter(Boolean);
      out.append(h('p', { class: 'hint brstamp' }, 'Stand ' + String(r.now || '').slice(8, 10) + '.' + String(r.now || '').slice(5, 7) + '. ' + hm(r.now) + ' Uhr · Zeitraum: die letzten 24 Stunden · Namen sind die Benutzernamen, E-Mail-Adressen erscheinen hier nie.'), attBox, h('div', { class: 'brgrid' }, cardsAll));
    }
    function load() { out.textContent = ''; out.append(h('p', { class: 'state pulse' }, 'Lade …')); api('/api/admin.php?a=briefing').then(draw); }
    box.append(h('div', { class: 'brtop' }, h('p', { class: 'hint', style: 'margin:0' }, 'Die wichtigsten Zahlen der letzten 24 Stunden auf einen Blick.'), h('button', { class: 'btn ghost small', type: 'button', onclick: load }, 'Aktualisieren')), out);
    box.load = load; return box;
  }
  function admin() {
    var admBadge = null;
    function CAN(k) { return !!D.admin || (D.admsec || []).indexOf(k) > -1; }
    var brBox = briefingBox();
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
    var itArea = h('textarea', { rows: '16', maxlength: '4000', 'aria-label': 'Text der Einladungsmail', spellcheck: 'true', style: 'width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:10px;font:inherit;line-height:1.5' }), itMsg = h('span', { class: 'msg', style: 'margin:0' }), itFoot = h('pre', { class: 'hint', style: 'white-space:pre-wrap;margin:8px 0 0;font-family:inherit' }), itLoaded = false;
    function itLoad() { api('/api/admin.php?a=invite_text_get').then(function (r) { itArea.value = r.text || ''; itFoot.textContent = r.footer || ''; itLoaded = true; }); }
    function itSave(text) { api('/api/admin.php?a=invite_text_set', { text: text }).then(function (r) { itMsg.className = 'msg' + (r.ok ? '' : ' err'); itMsg.textContent = r.ok ? 'Gespeichert.' : (r.error || 'Speichern nicht möglich.'); if (r.ok) { itArea.value = r.text; } }); }
    var itBox = h('details', { class: 'cronlist', style: 'margin:12px 0', ontoggle: function () { if (this.open && !itLoaded) itLoad(); } }, h('summary', null, 'Text der Einladungsmail bearbeiten'),
      h('p', { class: 'hint', style: 'margin:8px 0' }, 'Dieser Text steht am Anfang jeder Einladungsmail (nur reiner Text, Leerzeilen werden übernommen). Darunter hängt die Seite automatisch an, was nötig ist:'), itFoot,
      h('div', { style: 'margin-top:10px' }, itArea), h('p', { class: 'inline', style: 'margin:8px 0 0;gap:8px;align-items:center;flex-wrap:wrap' }, h('button', { class: 'btn small', type: 'button', onclick: function () { itSave(itArea.value); } }, 'Text speichern'),
        h('button', { class: 'btn ghost small', type: 'button', onclick: function () { if (confirm('Standardtext wiederherstellen? Dein eigener Text geht dabei verloren.')) itSave(''); } }, 'Standardtext wiederherstellen'), itMsg));
    var invBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Erzeuge einen Einladungslink für eine Person. Er ist nur einmal verwendbar, 14 Tage gültig und schaltet das neue Konto sofort frei. Mit E-Mail-Adresse schickt die Seite die Einladung direkt per Mail (die Adresse wird nach der Registrierung oder dem Ablauf wieder gelöscht); ohne Adresse bekommst du nur den Link zum Weitergeben. Für die nächste Person erzeugst du einen neuen Link.'),
      h('form', { class: 'inline', autocomplete: 'off', onsubmit: function (ev) { ev.preventDefault(); invMsg.className = 'msg'; invMsg.textContent = '…'; invOut.textContent = '';
        api('/api/admin.php?a=invite_create', { email: invMail.value }).then(function (r) {
          if (!r.ok) { invMsg.className = 'msg err'; invMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; }
          invMsg.textContent = r.email ? (r.sent ? 'Einladung per Mail verschickt. Den Link kannst du zusätzlich selbst weitergeben:' : 'Die Mail konnte nicht verschickt werden. Gib den Link selbst weiter:') : 'Einladungslink erstellt:'; invMail.value = '';
          var f = h('input', { type: 'text', readonly: true, value: r.link, 'aria-label': 'Einladungslink', style: 'flex:1;min-width:220px' });
          invOut.append(h('div', { class: 'inline' }, f, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { f.select(); try { navigator.clipboard.writeText(r.link); } catch (_) { document.execCommand('copy'); } this.textContent = 'Kopiert'; } }, 'Kopieren')));
          invLoad();
        });
      } }, invMail, h('button', { class: 'btn', type: 'submit' }, 'Einladung erstellen')), invMsg, invOut, invList, itBox);
    invLoad();
    var tmMail = h('input', { type: 'email', maxlength: '120', placeholder: 'Adresse, z. B. von mail-tester.com', 'aria-label': 'Empfängeradresse der Testmail', autocomplete: 'off', style: 'flex:1;min-width:220px' }), tmMsg = h('p', { class: 'msg', role: 'status' });
    var apMsg = h('div', { class: 'msg', role: 'status' }), apCb = h('input', { type: 'checkbox', id: 'apReq' }), apInfo = h('p', { class: 'hint' });
    function apLoad() { api('/api/admin.php?a=approval').then(function (r) { apCb.checked = !!r.required; apInfo.textContent = r.pending ? (r.pending + (r.pending === 1 ? ' Konto wartet' : ' Konten warten') + ' auf Freischaltung:') : 'Zurzeit wartet kein Konto auf Freischaltung.'; }); }
    apCb.addEventListener('change', function () { apMsg.className = 'msg'; apMsg.textContent = '…'; api('/api/admin.php?a=setapproval', { required: apCb.checked }).then(function (r) { if (!r.ok) { apMsg.className = 'msg err'; apMsg.textContent = r.error || 'Das hat nicht geklappt.'; apCb.checked = !apCb.checked; return; } D.approval = !!r.required; apMsg.textContent = r.required ? 'Gespeichert: Neue Konten müssen von dir freigeschaltet werden.' : 'Gespeichert: Neue Konten sind nach der Bestätigung der E-Mail-Adresse sofort freigeschaltet.'; apLoad(); }); });
    apLoad();
    var apList = h('div', { class: 'adm', style: 'margin-top:8px' });
    function pendPaint(users) {
      apList.textContent = '';
      (users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at && !x.delete_requested_at; }).sort(function (a, b) { return a.id - b.id; }).forEach(function (x) {
        apList.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' }, 'Nr. ' + x.id + ' · ', x.username ? h('span', { class: 'unm' }, x.username) : h('span', { class: 'hiddenmail' }, 'noch ohne Benutzernamen'), (x.email_verified_at || +x.no_email) ? null : h('span', { class: 'hiddenmail' }, ' · E-Mail nicht bestätigt')), h('div', { class: 'meta2' }, (+x.no_email ? 'ohne E-Mail-Adresse · ' : '') + 'registriert am ' + (x.created_at || '').slice(0, 10))), h('div', { class: 'acts' }, h('button', { class: 'btn small', type: 'button', onclick: function () { act('approve', x.id); } }, 'Freischalten'))));
      });
    }
    var apBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Aus (Standard): Wer sich registriert und die E-Mail-Adresse bestätigt, kann sofort loslegen. An: Neue Konten sehen erst Inhalte, wenn du sie freischaltest, und du bekommst bei jeder Registrierung eine Mail mit Freischalt-Link. Bereits wartende oder gesperrte Konten bleiben beim Umschalten unverändert; gesperrt bleibt, wer von dir gesperrt wurde.'), h('label', { for: 'apReq', style: 'display:flex;gap:8px;align-items:center;margin:0' }, apCb, 'Neue Konten manuell freischalten'), apMsg, apInfo, apList);
    var anCb = h('input', { type: 'checkbox' }), anMsg = h('span', { class: 'msg', style: 'margin:0' });
    api('/api/admin.php?a=anon_get').then(function (r) { anCb.checked = !!r.enabled; });
    anCb.addEventListener('change', function () { anMsg.className = 'msg'; anMsg.textContent = '…'; api('/api/admin.php?a=anon_set', { on: anCb.checked }).then(function (r) { if (!r.ok) { anCb.checked = !anCb.checked; anMsg.className = 'msg err'; anMsg.textContent = r.error || 'Das hat nicht geklappt.'; return; } anMsg.textContent = anCb.checked ? 'Gespeichert: Registrierung ohne E-Mail-Adresse ist möglich.' : 'Gespeichert: Neue Konten brauchen eine E-Mail-Adresse. Bestehende Konten ohne Adresse funktionieren weiter.'; }); });
    var permBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Hier legst du fest, was Nutzer, Alphatester und Moderatoren dürfen. Admins dürfen immer alles. Jede Stufe hat mindestens die Rechte der darunter: Alphatester die der Nutzer, Moderatoren die der Alphatester. Moderatoren ernennst du in der Nutzerverwaltung. Änderungen gelten sofort, die Nutzer sehen sie nach dem nächsten Laden der Seite.')), permMsg = h('p', { class: 'msg', role: 'status' });
    function permPaint(rows) {
      var FIX = [['Kalender, Listen, Einträge hinzufügen und Benachrichtigungen', 1, 1, 1, 1], ['Community und ähnliche Vorschläge', 1, 1, 1, 1], ['Ideen und Bugs einreichen (Knopf auf den Seiten Ideen und Bugs), freigegebene Beiträge lesen, abstimmen, eigene zurückziehen', 1, 1, 1, 1], ['Nur Admin: Registrierung und Freischaltung einstellen, Rechte der Nutzergruppen, Amazon-Partner-ID, Bilder der Anmeldeseite, Einladungen, Infomails und Testmail, Sicherheit (Deployment, Protokoll, Aktualisierung der Einträge), Alphatester und Moderatoren ernennen', 0, 0, 0, 1]];
      var tb = h('table', { class: 'permtbl' }), hd = h('tr', null, h('th', null, 'Recht'), h('th', null, 'Nutzer'), h('th', null, 'Alphatester'), h('th', null, 'Moderator'), h('th', null, 'Admin'));
      tb.append(h('thead', null, hd)); var body = h('tbody');
      function mark(on) { return h('span', { class: 'permfix ' + (on ? 'on' : 'off'), 'aria-label': on ? 'Ja' : 'Nein' }, on ? '✓' : '–'); }
      var lastG = '';
      function grp(t) { body.append(h('tr', { class: 'permgrp' }, h('td', { colspan: '5' }, t))); }
      rows.forEach(function (r) {
        var G = /^fb_/.test(r.key) ? 'Ideen und Bugs' : /^adm_/.test(r.key) ? 'Admin-Bereich (für Moderatoren)' : r.key === 'devmode' ? 'Entwicklung' : (/^cat_/.test(r.key) || r.key === 'podcast') ? 'Kategorien: wer sieht sie?' : 'Einstellungen'; if (G !== lastG) { grp(G); lastG = G; }
        function cb(g) { var ck = h('input', { type: 'checkbox', checked: !!r[g], disabled: (g === 'alpha' && !!r.user) || (g === 'mod' && !!r.alpha), 'aria-label': r.label + ' – ' + (g === 'user' ? 'Nutzer' : g === 'alpha' ? 'Alphatester' : 'Moderator') });
          ck.addEventListener('change', function () { permMsg.className = 'msg'; permMsg.textContent = '…'; api('/api/admin.php?a=perms_set', { key: r.key, group: g, on: ck.checked }).then(function (x) { if (!x.ok) { permMsg.className = 'msg err'; permMsg.textContent = x.error || 'Das hat nicht geklappt.'; } else { permMsg.textContent = 'Gespeichert.'; } permLoad(); }); }); return ck; }
        body.append(h('tr', null, h('td', null, h('div', null, r.label, r.state === 'soon' ? h('span', { class: 'permsoon' }, ' (noch ohne Funktion)') : null), h('div', { class: 'permhint' }, r.hint)), h('td', null, cb('user')), h('td', null, cb('alpha')), h('td', null, cb('mod')), h('td', null, mark(true))));
      });
      grp('Für alle bzw. nur Admin (fest)');
      FIX.forEach(function (f) { body.append(h('tr', { class: 'permfixrow' }, h('td', null, f[0]), h('td', null, mark(f[1])), h('td', null, mark(f[2])), h('td', null, mark(f[3])), h('td', null, mark(f[4])))); });
      tb.append(body); permBox.querySelectorAll('.permwrap').forEach(function (n) { n.remove(); }); permBox.append(h('div', { class: 'permwrap' }, tb));
    }
    function permLoad() { api('/api/admin.php?a=perms_get').then(function (r) { if (r.rows) permPaint(r.rows); }); }
    permLoad(); permBox.append(permMsg);
    var anBox = h('div', { class: 'wide2', style: 'margin-top:14px' }, h('label', { style: 'display:flex;gap:8px;align-items:center' }, anCb, 'Registrierung ohne E-Mail-Adresse erlauben'),
      h('p', { class: 'hint', style: 'margin:2px 0 0 26px' }, 'Nutzer melden sich mit Benutzername und Passwort an und bekommen einen Wiederherstellungscode, aber keine Mails. Grenzen: ein Konto je Anschluss und 24 Stunden, höchstens 5 pro Stunde insgesamt. Wird die Grenze erreicht, bekommst du eine Meldung im Kontakt-Bereich unter „System“. Nur für neue Konten; bestehende bleiben unverändert.'), anMsg);
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
    var mailLoad, mailBox = h('div', { class: 'mailprev' }); // Reiter „Infomails“: Vorschau aller Mailarten (Normal, Handy, Text)
    (function () {
      var items = null, mode = 'normal', sel = h('select', { 'aria-label': 'Mailart' }), meta = h('div', { class: 'mpmeta' }), frame = h('iframe', { class: 'mpframe', title: 'Vorschau der Mail', sandbox: '' }), plain = h('pre', { class: 'mpplain', hidden: true }), modes = {}, state = h('p', { class: 'hint' }, 'Lade …');
      var bar = h('div', { class: 'mpbar' }, sel, h('div', { class: 'mpmodes', role: 'group', 'aria-label': 'Ansicht' }, [['normal', 'Normal'], ['phone', 'Handy'], ['text', 'Text']].map(function (m) { var b = h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': 'false', onclick: function () { mode = m[0]; draw(); } }, m[1]); modes[m[0]] = b; return b; })));
      function draw() {
        var it = items && items[+sel.value]; if (!it) return;
        Object.keys(modes).forEach(function (k) { modes[k].setAttribute('aria-pressed', String(k === mode)); });
        meta.textContent = ''; [['Anlass', it.when], ['An', 'dein Konto (Beispielwerte, es wird nichts verschickt)'], ['Betreff', it.subject]].forEach(function (r) { meta.append(h('div', null, h('span', { class: 'mplbl' }, r[0]), ' ', r[1])); });
        frame.hidden = mode === 'text'; plain.hidden = mode !== 'text';
        frame.className = 'mpframe' + (mode === 'phone' ? ' phone' : '');
        if (mode === 'text') plain.textContent = it.text; else frame.srcdoc = it.html;
      }
      sel.addEventListener('change', draw);
      mailBox.append(h('p', { class: 'hint' }, 'So sehen die Mails der Seite aus, mit deinem Konto als Empfänger. Die Beispieltexte und Links sind Platzhalter, die Texte entsprechen dem aktuellen Stand im Code. „Normal“ ist die Ansicht am Rechner, „Handy“ eine schmale Spalte, „Text“ die reine Textfassung.'), state);
      mailLoad = function () {
        if (items) return;
        api('/api/admin.php?a=mail_previews').then(function (r) {
          if (!r || r.error || !r.items) { state.className = 'warnbox'; state.textContent = (r && r.error) || 'Die Vorschau konnte nicht geladen werden.'; return; }
          items = r.items; state.remove(); var gr = {};
          items.forEach(function (it, i) { var g = gr[it.group]; if (!g) { g = gr[it.group] = h('optgroup', { label: it.group }); sel.append(g); } g.append(h('option', { value: String(i) }, it.label)); });
          sel.value = '0'; mailBox.append(bar, meta, frame, plain); draw();
        }, function () { state.className = 'warnbox'; state.textContent = 'Die Vorschau konnte nicht geladen werden.'; });
      };
    })();
    p.append(amz, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Briefing'), devMark(brBox, 'briefing'), h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Bilder der Anmeldeseite'), gal, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Registrierung'), apBox, anBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Rechte der Nutzergruppen'), permBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Einladen'), invBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Systemnachrichten'), nwBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Eintrag prüfen'), ecBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Aktualisierung der Einträge'), cs[0], cs[1], h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Infomails'), mailBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Testmail'), tmBox, h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Nutzerverwaltung'), h('p', { class: 'hint' }, 'Wartende Konten (falls die manuelle Freischaltung an ist) siehst du hier zuerst. Beim Löschen verschwinden Konto und Liste.'));
    var box = h('div', { class: 'adm' }), ulast = null, usearch = h('input', { type: 'search', maxlength: '40', placeholder: 'Suchen nach Nummer oder Benutzername', 'aria-label': 'Nutzer suchen', style: 'width:100%;max-width:360px;margin:8px 0', oninput: function () { paint(); } });
    p.append(usearch, box); mainEl.append(p);
    function users() { api('/api/admin.php?a=users').then(function (r) { ulast = r; S.pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; if (admBadge) { admBadge.textContent = S.pend + ' neu'; admBadge.hidden = !S.pend; } nav(); pendPaint(r.users); apLoad(); paint(); }); }
    function paint() {
        var r = ulast; if (!r) return; var l = (r.users || []).slice().sort(function (x, y) { return x.id - y.id; }), q = usearch.value.trim().toLowerCase().replace(/^nr\.?\s*/, ''); box.textContent = '';
        if (q) l = l.filter(function (x) { return String(x.id) === q || String(x.id).indexOf(q) === 0 || (x.username || '').toLowerCase().indexOf(q) > -1 || (x.email || '').toLowerCase().indexOf(q) > -1; });
        if (!l.length) { box.append(h('p', { class: 'hint' }, 'Keine Nutzer gefunden.')); return; }
        l.forEach(function (x) {
          var ok = x.role === 'admin' || x.approved_at, me = x.id == r.me;
          var hm = h('span', { class: 'hiddenmail' }), rev = h('button', { class: 'btn ghost small', type: 'button', title: 'E-Mail-Adresse aufdecken (wird protokolliert)', onclick: function () { var bt = this; if (!confirm('Adresse von ' + (x.username || 'Nr. ' + x.id) + ' wirklich anzeigen? Das wird protokolliert und sollte nur im Ausnahmefall (Missbrauch, Rechtsanfragen) nötig sein.')) return; api('/api/admin.php?a=reveal', { id: x.id }).then(function (r) { if (r.ok) { hm.textContent = ' · ' + r.email; hm.className = ''; bt.remove(); logLoad(); } else alert(r.error); }); } }, 'E-Mail anzeigen');
          box.append(h('div', { class: 'arow' }, h('div', null, h('div', { class: 'nm2' + (x.delete_requested_at ? ' gonename' : '') }, 'Nr. ' + x.id + ' · ', x.username ? h('span', { class: 'unm', title: (x.name_hist && x.name_hist.length) ? 'Letzte Namensänderungen:\n' + x.name_hist.map(function (q) { return new Date(String(q.at).replace(' ', 'T')).toLocaleDateString('de-DE') + ': ' + (q.from ? q.from + ' → ' : 'festgelegt: ') + q.to; }).join('\n') : 'Keine Namensänderung erfasst' }, x.username) : null, '' + (x.delete_requested_at ? (+x.admin_deleted ? ' · gelöscht, endgültig nach 7 Tagen' : ' · Löschung beantragt') : '') + (x.email ? ' · ' : ''), x.email ? x.email : (+x.no_email ? null : hm), +x.name_change_required ? h('span', { class: 'tier', style: 'background:#c0392b;color:#fff' }, 'Neuer Name verlangt') : null, x.role === 'admin' ? h('span', { class: 'tier' }, 'Admin') : null, +x.alpha ? h('span', { class: 'tier alpha' }, 'Alphatester') : null), h('div', { class: 'meta2' }, (ok ? 'freigeschaltet' : 'wartet auf Freischaltung') + (x.email_verified_at || +x.no_email ? '' : ' · E-Mail nicht bestätigt') + ' · ' + pl(x.entries, 'Eintrag', 'Einträge') + ' · zuletzt ' + (x.seen_label || (x.last_seen_at || '–').slice(0, 16)) + ' · seit ' + (x.created_at || '').slice(0, 10))),
            me || x.role === 'admin' ? h('div') : h('div', { class: 'acts' }, (x.email || +x.no_email || !CAN('adm_users_danger')) ? null : rev, h('button', { class: 'btn ghost small', type: 'button', 'aria-pressed': String(!!+x.name_change_required), title: +x.name_change_required ? 'Aufforderung zurücknehmen' : 'Nutzer muss einen neuen Benutzernamen wählen, bevor er die Seite wieder nutzen kann', onclick: function () { var on = !+x.name_change_required; if (on && !confirm('Soll ' + (x.username || 'Nr. ' + x.id) + ' einen neuen Benutzernamen wählen müssen? Bis dahin kann der Nutzer die Seite nicht nutzen.')) return; api('/api/admin.php?a=renamereq', { id: x.id, on: on }).then(function (r) { if (r.ok) users(); else alert(r.error); }); } }, 'Namensänderung'),
              D.admin ? h('button', { class: 'btn ghost small', type: 'button', title: (+x.alpha ? 'Alphatester: Alpha entziehen. ' : 'Kein Alphatester: zum Alphatester machen. ') + 'Alphatester sehen Funktionen, die noch in Entwicklung sind', 'aria-label': (+x.alpha ? 'Alphatester, aktiv' : 'Alphatester, nicht aktiv'), onclick: function () { api('/api/admin.php?a=alpha', { id: x.id, on: !+x.alpha }).then(function (r) { if (r.ok) users(); else alert(r.error); }); } }, h('span', { class: +x.alpha ? 'tick yes' : 'tick no', 'aria-hidden': 'true' }, +x.alpha ? '✓' : '✗'), ' Alphatester') : null, D.admin ? h('button', { class: 'btn ghost small', type: 'button', title: (+x.moderator ? 'Moderator: Moderatorrechte entziehen. ' : 'Kein Moderator: zum Moderator machen. ') + 'Moderatoren dürfen die in „Rechte der Nutzergruppen“ freigegebenen Teile des Admin-Bereichs nutzen', 'aria-label': (+x.moderator ? 'Moderator, aktiv' : 'Moderator, nicht aktiv'), onclick: function () { api('/api/admin.php?a=moderator', { id: x.id, on: !+x.moderator }).then(function (r) { if (r.ok) users(); else alert(r.error); }); } }, h('span', { class: +x.moderator ? 'tick yes' : 'tick no', 'aria-hidden': 'true' }, +x.moderator ? '✓' : '✗'), ' Moderator') : null, ok ? (CAN('adm_users_danger') ? h('button', { class: 'btn ghost small', type: 'button', onclick: function () { act('revoke', x.id); } }, 'Sperren') : null) : h('button', { class: 'btn small', type: 'button', onclick: function () { act('approve', x.id); } }, 'Freischalten'),
              !CAN('adm_users_danger') ? null : h('button', { class: 'btn warn small', type: 'button', onclick: function () { if (!confirm('Konto samt Liste wirklich löschen?')) return; var blk = confirm('Soll diese E-Mail-Adresse auch für neue Konten gesperrt werden?\n\nOK = sperren, Abbrechen = nur löschen.'); api('/api/admin.php?a=delete', { id: x.id, block: blk }).then(function (r) { if (r.ok) { users(); blocked(); } else alert(r.error); }); } }, 'Löschen'))));
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
    var stBox = h('div', { class: 'wide2' }, h('p', { class: 'hint' }, 'Nur Zahlen, keine einzelnen Konten. Gelöschte Konten werden erst seit Einführung der Zählung erfasst.'));
    var stOut = h('div', null); stBox.append(stOut);
    function statLoad() {
      api('/api/admin.php?a=stats').then(function (r) {
        stOut.textContent = '';
        if (!r || !r.ok) { stOut.append(h('p', { class: 'hint' }, (r && r.error) || 'Statistik nicht verfügbar.')); return; }
        if (r.missing) { stOut.append(h('p', { class: 'hint' }, 'Die Datenbank ist noch nicht aktualisiert (migrate.php, 054).')); return; }
        function tile(n, l, sub) { return h('div', { class: 'stat' }, h('b', null, String(n)), h('span', null, l), sub ? h('small', null, sub) : null); }
        var pct = function (a, b) { return b ? ' (' + Math.round(a * 100 / b) + ' %)' : ''; };
        stOut.append(h('div', { class: 'statgrid' },
          tile(r.users_total, 'Nutzer', r.users_approved + ' freigeschaltet, ' + (r.users_total - r.users_approved) + ' wartend'),
          tile(r.reg7, 'Anmeldungen, letzte 7 Tage'),
          tile(r.reg28, 'Anmeldungen, letzte 4 Wochen'),
          tile(r.exits_total, 'Gelöschte Konten insgesamt', (r.exits_pending ? r.exits_pending + ' in der 7-Tage-Frist, ' : '') + (r.exits_admin ? r.exits_admin + ' zusätzlich vom Admin entfernt' : 'vom Admin entfernte nicht mitgezählt')),
          tile(r.exits28, 'Gelöscht, letzte 4 Wochen'),
          tile(r.early28, 'Innerhalb von 7 Tagen wieder gelöscht', 'von ' + r.reg28 + ' Anmeldungen der letzten 4 Wochen' + pct(r.early28, r.reg28))));
        stOut.append(h('h4', { style: 'margin:16px 0 6px' }, 'Gründe beim Löschen (alle bisher)'));
        if (!r.reasons.length && !r.no_reason) stOut.append(h('p', { class: 'hint' }, 'Noch keine Löschung erfasst.'));
        else {
          var ul = h('ul', { style: 'margin:0;padding-left:18px' });
          r.reasons.forEach(function (x) { ul.append(h('li', null, x.n + ' × ' + x.label)); });
          if (r.no_reason) ul.append(h('li', null, r.no_reason + ' × ohne Angabe'));
          stOut.append(ul, h('p', { class: 'hint' }, r.texts + (r.texts === 1 ? ' Freitext' : ' Freitexte') + ' gespeichert; sie kommen anonym in „Kontakt“ unter „System“.'));
        }
      });
    }
    p.append(h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Statistik'), stBox); statLoad();
    var wsBox = h('div', { class: 'adm' });
    p.append(h('h3', { class: 'sec', style: 'font:700 18px var(--font-display);margin:22px 0 2px' }, 'Wunschbilder'), h('p', { class: 'hint' }, 'Nutzer können ein eigenes Profilbild wünschen. Lade 1 bis 3 passende SVG-Dateien hoch, dann wählt der Nutzer eines aus.'), h('p', { class: 'hint' }, 'Hinweis für dich: Die Bilder lässt du aktuell von Claude generieren. Geplant ist, hier später eine andere, externe Möglichkeit einzubinden.'), wsBox);
    function wishes() {
      api('/api/admin.php?a=wishes').then(function (r) {
        wsBox.textContent = ''; var l = r.wishes || [];
        if (!l.length) wsBox.append(h('p', { class: 'hint' }, r.error || 'Keine Wünsche offen.'));
        l.forEach(function (x) {
          var row = h('div', { class: 'wishrow' }, h('div', { class: 'nm2' }, (x.username || 'Nutzer ' + x.uid) + ' · ' + String(x.created_at || '').slice(0, 10)), h('div', null, x.wish));
          if (x.status !== 'open') { row.append(h('div', { class: 'meta2' }, x.status === 'ready' ? 'Bilder bereitgestellt (' + x.n + ')' : 'Abgelehnt')); wsBox.append(row); return; }
          var fi = h('input', { type: 'file', accept: '.svg,image/svg+xml', multiple: true, 'aria-label': 'SVG-Dateien' }), pv = h('div', { class: 'wishpv' }), wm = h('p', { class: 'msg', role: 'status' }), nt = h('input', { type: 'text', maxlength: '300', placeholder: 'Anmerkung bei Ablehnung (optional)', 'aria-label': 'Anmerkung' });
          fi.onchange = function () { pv.textContent = ''; [].slice.call(fi.files, 0, 3).forEach(function (f) { pv.append(h('img', { src: URL.createObjectURL(f), alt: '', width: '64', height: '64' })); }); };
          row.append(fi, pv, h('div', { class: 'acts' },
            h('button', { class: 'btn small', type: 'button', onclick: function () {
              var fs = [].slice.call(fi.files); if (!fs.length || fs.length > 3) { wm.className = 'msg err'; wm.textContent = 'Bitte 1 bis 3 SVG-Dateien wählen.'; return; }
              Promise.all(fs.map(function (f) { return f.text(); })).then(function (t) { return api('/api/admin.php?a=wish_upload', { id: x.id, svgs: t }); }).then(function (z) { if (z && z.ok) wishes(); else { wm.className = 'msg err'; wm.textContent = (z && z.error) || 'Das hat nicht geklappt.'; } });
            } }, 'Bilder bereitstellen'),
            nt, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { api('/api/admin.php?a=wish_decline', { id: x.id, note: nt.value }).then(function (z) { if (z && z.ok) wishes(); else { wm.className = 'msg err'; wm.textContent = (z && z.error) || 'Das hat nicht geklappt.'; } }); } }, 'Ablehnen')), wm);
          wsBox.append(row);
        });
      });
    }
    wishes();

    var newsAdm = h('div', { class: 'wide2' }), newsMsg = h('span', { class: 'msg', style: 'margin:0' });
    (function () {
      var iso = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
      var day = h('input', { type: 'date', value: iso(new Date()) }), st = h('input', { type: 'text', maxlength: '160', placeholder: 'Überschrift' }), sx = h('textarea', { rows: '5', maxlength: '6000', placeholder: 'Haupttext. Leerzeile = neuer Absatz, Zeilen mit „- “ werden zur Liste. Neue News mit einer Zeile --- beginnen; Quelle: Name | https://…' }),
        ct = h('input', { type: 'text', maxlength: '160', placeholder: 'Überschrift' }), cx = h('textarea', { rows: '10', maxlength: '6000', placeholder: 'Haupttext. Leerzeile = neuer Absatz, Zeilen mit „- “ werden zur Liste. Neue News mit einer Zeile --- beginnen; Quelle: Name | https://…' }), old = h('div', null), all = [];
      function fill(d) { var x = all.filter(function (y) { return y.day === d; })[0] || {}; st.value = x.site_title || ''; sx.value = x.site_text || ''; ct.value = x.content_title || ''; cx.value = x.content_text || ''; }
      function say2(t, bad) { newsMsg.className = 'msg' + (bad ? ' err' : ''); newsMsg.textContent = t; }
      function load() {
        api('/api/news.php?a=list').then(function (r) {
          if (!r || r.error) { say2((r && r.error) || 'Das hat nicht geklappt.', true); return; }
          all = r.days || []; old.textContent = '';
          if (!all.length) old.append(h('p', { class: 'hint' }, 'Noch keine News.'));
          all.forEach(function (x) {
            old.append(h('details', { class: 'logv logold newsv' }, h('summary', null, h('h3', null, newsDate(x.day), x.future ? h('small', null, 'geplant') : null)), newsDay(x),
              h('p', null, h('button', { class: 'btn ghost small', type: 'button', onclick: function () { day.value = x.day; fill(x.day); say2('Zum Bearbeiten oben geladen.'); window.scrollTo(0, 0); } }, 'Bearbeiten'), ' ',
                h('button', { class: 'btn ghost small', type: 'button', onclick: function () { if (!confirm('News vom ' + newsDate(x.day) + ' löschen?')) return; api('/api/news.php?a=delete', { day: x.day }).then(function (z) { if (z && z.ok) { say2('Gelöscht.'); load(); } else say2((z && z.error) || 'Das hat nicht geklappt.', true); }); } }, 'Löschen'))));
          });
          fill(day.value);
        });
      }
      day.addEventListener('change', function () { fill(day.value); say2(''); });
      newsAdm.append(
        h('label', { class: 'field' }, h('span', null, 'Tag (gleicher Tag überschreibt die vorhandene Meldung)'), day), h('p', { class: 'hint' }, 'Mehrere News pro Bereich: durch eine Zeile mit --- trennen. Quellen direkt unter die jeweilige News schreiben, eine pro Zeile: Quelle: Name | https://…'),
        h('h4', { class: 'logcat' }, 'Seitennews'), h('label', { class: 'field' }, h('span', null, 'Überschrift'), st), h('label', { class: 'field' }, h('span', null, 'Haupttext'), sx),
        h('h4', { class: 'logcat' }, 'News zu Inhalten'), h('label', { class: 'field' }, h('span', null, 'Überschrift'), ct), h('label', { class: 'field' }, h('span', null, 'Haupttext'), cx),
        h('p', null, h('button', { class: 'btn', type: 'button', onclick: function () { api('/api/news.php?a=save', { day: day.value, site_title: st.value, site_text: sx.value, content_title: ct.value, content_text: cx.value, site_sources: '', content_sources: '' }).then(function (z) { if (z && z.ok) { say2('Gespeichert.'); load(); } else say2((z && z.error) || 'Das hat nicht geklappt.', true); }); } }, 'Speichern'), ' ', newsMsg),
        h('h4', { class: 'logcat' }, 'Bisherige News'), old);
      load();
    })();
    p.append(h('h3', { class: 'sec' }, 'News'), newsAdm);
    if (CAN('adm_faq')) p.append(h('h3', { class: 'sec' }, 'FAQ'), faqAdmin());

    var tickGh = function () {}, ghBox = h('div', { class: 'wide2 ghbox' }, h('p', { class: 'state pulse' }, 'Lade …'));
    (function () {
      var timer = null, skew = 0;
      function dur(ms) { var s = Math.max(0, Math.round(ms / 1000)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') + ' min'; }
      function ago(t) { var s = Math.max(0, (Date.now() + skew - new Date(t).getTime()) / 1000); return s < 90 ? 'gerade eben' : s < 3600 ? 'vor ' + Math.round(s / 60) + ' Min.' : s < 86400 ? 'vor ' + Math.round(s / 3600) + ' Std.' : 'vor ' + Math.round(s / 86400) + ' Tagen'; }
      function state(r) { if (r.status !== 'completed') return r.status === 'queued' || r.status === 'waiting' || r.status === 'pending' ? ['wait', 'Wartet auf Start'] : ['run', 'Läuft']; var c = r.conclusion; return c === 'success' ? ['ok', 'Fertig'] : (c === 'cancelled' ? ['cancel', 'Abgebrochen'] : ['fail', 'Fehlgeschlagen']); }
      function draw(r) {
        ghBox.textContent = '';
        if (!r || r.error) { ghBox.append(h('p', { class: 'warnbox' }, (r && r.error) || 'Das hat nicht geklappt.')); return 15000; }
        if (!r.configured) { ghBox.append(h('p', { class: 'hint' }, 'Noch nicht eingerichtet. Lege bei GitHub ein „Fine-grained personal access token“ nur für dieses Repo an (Berechtigung „Actions: Read-only“, mehr nicht) und trage es in der config.php als github_token ein. Das Token bleibt auf dem Server und wird nie angezeigt.')); return 0; }
        var runs = r.runs || []; skew = r.now ? new Date(r.now).getTime() - Date.now() : 0;
        if (!runs.length) { ghBox.append(h('p', { class: 'hint' }, 'Noch keine Läufe gefunden.')); return 20000; }
        var cur = runs[0], st = state(cur), live = st[0] === 'run' || st[0] === 'wait', t0 = new Date(cur.started).getTime();
        var big = h('div', { class: 'ghnow gh-' + st[0] }, h('span', { class: 'ghdot' }), h('div', null, h('b', null, live ? (st[0] === 'run' ? 'Update läuft gerade' : 'Update wartet auf Start') : 'Kein Update aktiv'), h('div', { class: 'ghsub' }, live ? h('span', { class: 'ghel' }, dur(Date.now() + skew - t0) + ' · ' + cur.title) : 'Letzter Lauf: ' + st[1].toLowerCase() + ' ' + ago(cur.updated || cur.started) + ' · ' + cur.title)));
        ghBox.append(big);
        var ul = h('ul', { class: 'ghlist' });
        runs.slice(live ? 1 : 0).forEach(function (x) { var s = state(x); ul.append(h('li', { class: 'gh-' + s[0] }, h('span', { class: 'ghdot' }), h('span', { class: 'ght' }, x.title || '(ohne Titel)'), h('span', { class: 'ghm' }, s[1] + ' · ' + ago(x.updated || x.started) + (x.status === 'completed' && x.started && x.updated ? ' · ' + dur(new Date(x.updated) - new Date(x.started)) : '')))); });
        if (ul.childNodes.length) ghBox.append(h('h4', { class: 'logcat' }, 'Frühere Läufe'), ul);
        return live ? 4000 : 12000;
      }
      function tick() { if (!ghBox.isConnected) return; if (document.hidden) { timer = setTimeout(tick, 5000); return; } api('/api/admin.php?a=gh_status').then(function (r) { var d = draw(r); if (d) timer = setTimeout(tick, d); }); }
      tickGh = tick;
    })();
    var ghAt = [].slice.call(p.children).filter(function (e) { return e.tagName === 'H3' && /^Rechte der Nutzergruppen/.test(e.textContent); })[0] || null;
    p.insertBefore(h('h3', { class: 'sec' }, 'Deployment (GitHub Actions)'), ghAt); p.insertBefore(ghBox, ghAt); setTimeout(tickGh, 0);
    sectionize(p, 'Amazon-Partner-Links');
    (function () { // Reiter: die Kästen nach Kategorie gruppieren
      var TABS = [['Briefing', ['Briefing']], ['Nutzer', ['Nutzerverwaltung', 'Registrierung', 'Wunschbilder', 'Gesperrte E-Mail-Adressen', 'Rechte der Nutzergruppen']], ['Statistik', ['Statistik']], ['Seitenadministration', ['Amazon-Partner-Links']], ['Tools', ['Testmail', 'Einladen', 'Systemnachrichten', 'Eintrag prüfen', 'Bilder der Anmeldeseite']], ['Infomails', ['Infomails']], ['News', ['News']], ['FAQ', ['FAQ']], ['Sicherheit', ['Deployment (GitHub Actions)', 'Protokoll der Admin-Zugriffe', 'Ansicht prüfen', 'Aktualisierung der Einträge']]];
      var OWN = { 'Briefing': 'adm_briefing', 'Nutzerverwaltung': 'adm_users', 'Wunschbilder': 'adm_users', 'Gesperrte E-Mail-Adressen': 'adm_users_danger', 'Statistik': 'adm_stats', 'Systemnachrichten': 'adm_news', 'News': 'adm_news', 'FAQ': 'adm_faq', 'Eintrag prüfen': 'adm_tools' };
      if (!D.admin) { // Moderatoren: nur die in der Rechtetabelle freigegebenen Kästen und Reiter
        TABS = TABS.map(function (t) { return [t[0], t[1].filter(function (n) { return OWN[n] && CAN(OWN[n]); })]; }).filter(function (t) { return t[1].length; });
        [].slice.call(p.querySelectorAll('.setstack>.setcard')).forEach(function (c) { var tt = c.querySelector('h3').textContent; if (!(OWN[tt] && CAN(OWN[tt]))) c.remove(); });
      }
      var cards = [].slice.call(p.querySelectorAll('.setstack>.setcard')), cur = 'Briefing', bar = h('div', { class: 'admtabs admnav', role: 'tablist' });
      try { var sv = sessionStorage.getItem('sd_admtab'); if (sv && TABS.some(function (t) { return t[0] === sv; })) cur = sv; } catch (_) {}
      if (!TABS.some(function (t) { return t[0] === cur; })) cur = TABS[0][0];
      admBadge = h('span', { class: 'admbadge', hidden: true });
      var btns = TABS.map(function (t) { var b = h('button', { class: 'admtab', type: 'button', role: 'tab', onclick: function () { cur = t[0]; try { sessionStorage.setItem('sd_admtab', cur); } catch (_) {} show(); } }, t[0]); if (t[0] === 'Nutzer') b.append(' ', admBadge); bar.append(b); return b; });
      function show() {
        TABS.forEach(function (t, i) { btns[i].setAttribute('aria-selected', String(t[0] === cur)); btns[i].className = 'admtab' + (t[0] === cur ? ' on' : ''); });
        cards.forEach(function (c) { var tt = c.querySelector('h3').textContent, own = TABS.filter(function (t) { return t[1].indexOf(tt) > -1; })[0]; c.hidden = own ? own[0] !== cur : cur !== 'Seitenadministration'; });
        if (cur === 'Infomails' && mailLoad) mailLoad();
        if (cur === 'Briefing' && !brBox.done) { brBox.done = true; brBox.load(); }
      }
      var tl = TABS.filter(function (t) { return t[0] === 'Tools'; })[0][1].map(function (tt) { return cards.filter(function (c) { return c.querySelector('h3').textContent === tt; })[0]; }).filter(Boolean); // Tools: Karten in dieser Reihenfolge
      tl.forEach(function (c) { c.parentNode.appendChild(c); });
      var nu = TABS.filter(function (t) { return t[0] === 'Nutzer'; })[0][1]; // „Rechte der Nutzergruppen“ steht ganz unten bei „Nutzer“
      var rgc = cards.filter(function (c) { return c.querySelector('h3').textContent === 'Rechte der Nutzergruppen'; })[0], lastNu = cards.filter(function (c) { return nu.indexOf(c.querySelector('h3').textContent) > -1 && c !== rgc; }).pop();
      if (rgc && lastNu) lastNu.after(rgc);
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
        h('button', { class: 'btn', type: 'button', onclick: function () { api('/api/auth.php?a=username', { username: inp.value }).then(function (r) { if (r.ok) { D.username = r.username; D.uname_left = 30; D.uname_free_until = r.free_min > 0 ? Date.now() + r.free_min * 60000 : 0; var w = $('who'); if (w && w.lastChild && w.lastChild.nodeType === 3) w.lastChild.textContent = r.username + ' · ' + (D.email || 'ohne E-Mail'); box.remove(); } else { msg.className = 'msg err'; msg.textContent = r.error || 'Das hat nicht geklappt.'; } }); } }, 'Speichern'),
        h('button', { class: 'btn ghost', type: 'button', onclick: function () { try { sessionStorage.setItem('unskip', '1'); } catch (_) {} box.remove(); } }, 'Später'))));
    document.body.append(box); inp.focus();
  }
  function askCommunity() {
    if (S.preview || D.community_asked) return;
    var box = h('div', { class: 'unmodal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'cmh' }, h('div', { class: 'unbox' },
      h('h2', { id: 'cmh', style: 'margin:0 0 6px;font-size:20px' }, 'Community: Deine Einträge teilen?'),
      h('p', { class: 'hint' }, 'Die Community-Seite zeigt Ranglisten und die aktivsten Nutzer. Wenn du zustimmst, können andere Teilnehmende mit deinem Benutzernamen deine Listen und deine zuletzt hinzugefügten Einträge sehen und Einträge übernehmen. Ohne Zustimmung bleibst du dort unsichtbar.'),
      h('p', { class: 'hint' }, 'Du kannst das jederzeit unter Einstellungen ändern („Meine Einträge für die Community nutzen“).'),
      h('div', { class: 'inline', style: 'margin-top:10px' },
        h('button', { class: 'btn', type: 'button', onclick: function () { choose(true); } }, 'Zustimmen'),
        h('button', { class: 'btn ghost', type: 'button', onclick: function () { choose(false); } }, 'Erstmal ablehnen'))));
    function choose(on) { api('/api/auth.php?a=community', { on: on }).then(function (r) { if (r.ok) { D.community = on; D.community_asked = true; box.remove(); } }); }
    document.body.append(box);
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
      var okv = { comm: commOn(), cal: 1, list: 1, set: 1, info: 1, log: 1, sup: 1, adm: !!(D.admin || (D.admsec || []).length), feat: true, bug: true, news: !!(D.dev && D.news) };
      if (okv[sv.v] && (sv.v !== 'list' || TYPES[sv.c])) { S.view = sv.v; S.cat = sv.v === 'list' ? sv.c : sv.v; S.sel = sv.v === 'list' && sv.s ? String(sv.s) : null; }
    }
  } catch (_) {}
  load(function () { render(); showNews(); askName(); askCommunity(); unreadPoll(); fbPoll(); setInterval(unreadPoll, 60000); setInterval(fbPoll, 60000); document.addEventListener('visibilitychange', function () { if (!document.hidden) { fbPoll(); unreadPoll(); } }); if (D.admin) api('/api/admin.php?a=users').then(function (r) { S.pend = (r.users || []).filter(function (x) { return x.role !== 'admin' && !x.approved_at; }).length; nav(); }); });
})();
