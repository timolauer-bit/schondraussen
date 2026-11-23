// Prüft die Import-Formate (Block IMPORTFORMATE in public/dev/app.js oder einer anderen app.js) gegen die Beispiele in tests/import/.
// Aufruf: node scripts/test_import.js [public/dev/app.js]
// Neues Format: Beispieldatei (ohne persönliche Daten) nach tests/import/ legen und unten einen Fall ergänzen.
'use strict';
const fs = require('fs'), path = require('path');
const root = path.join(__dirname, '..'), src = fs.readFileSync(path.join(root, process.argv[2] || 'public/dev/app.js'), 'utf8');
const m = src.match(/\/\/ IMPORTFORMATE-START[\s\S]*?\/\/ IMPORTFORMATE-ENDE/);
if (!m) { console.error('Block IMPORTFORMATE nicht gefunden'); process.exit(1); }
const IMPF = new Function(m[0] + '\nreturn IMPF;')();
const dir = path.join(root, 'tests/import');
async function load(names) {
  const out = [];
  for (const n of names) {
    const p = path.join(dir, n);
    if (/\.zip$/i.test(n)) { const b = fs.readFileSync(p); out.push(...await IMPF.zipRead(b.buffer.slice(b.byteOffset, b.byteOffset + b.byteLength), /\.(json|csv|tsv|txt|opml|xml)$/i)); }
    else out.push({ name: n, text: fs.readFileSync(p, 'utf8') });
  }
  return out;
}
const fmt = items => items.map(i => i.k + ':' + i.n + (i.eps ? '[' + i.eps.length + ']' : ''));
const CASES = [
  { files: ['goodreads_library_export.csv'], fmt: 'goodreads', items: ['author:Pierre Christin', 'author:Roger Leloup', 'author:Daniel Suarez', 'author:Stephen King'] }, // Aufbau aus einem echten Export (23.11.2026), Zeilen gekürzt
  { files: ['storygraph_export.csv'], fmt: 'storygraph', items: ['author:Terry Pratchett', 'author:Neil Gaiman'] },
  { files: ['StreamingHistory_music_0.json', 'StreamingHistory_podcast_0.json'], fmt: 'spotify', items: ['artist:Radiohead', 'podcast:Lage der Nation'], skipped: 1 }, // Nightwish nur ein Song
  { files: ['YourLibrary.json'], fmt: 'spotify', items: ['artist:Nightwish', 'artist:Kraftwerk', 'podcast:Fest & Flauschig'], skipped: 1 }, // Blind Guardian nur ein Lieblingssong
  { files: ['exportify_playlist.csv'], fmt: 'spotify', items: ['artist:Radiohead'], skipped: 1 },
  { files: ['lastfm_ghan.csv'], fmt: 'lastfm', items: ['artist:Radiohead'], skipped: 1 },
  { files: ['lastfm_scrobbles_noheader.csv'], fmt: 'lastfm', items: ['artist:Portishead'], skipped: 1 },
  { files: ['tunemymusic_applemusic.csv'], fmt: 'tunemymusic', items: ['artist:Blind Guardian'], skipped: 1 }, // Aufbau angenommen, noch ohne echten Export
  { files: ['imdb_watchlist.csv'], fmt: 'imdb', items: ['film:Der Pate', 'series:Dark'], skipped: 2 },
  { files: ['letterboxd-export.zip'], fmt: 'letterboxd', items: ['film:The Godfather', 'film:Alien', 'film:Dune: Part Two'] },
  { files: ['NetflixViewingHistory.csv'], fmt: 'netflix', items: ['series:Dark', 'series:Haus des Geldes', 'film:Star Wars: Episode IV – Eine neue Hoffnung', 'series:Stranger Things', 'film:Der Schuh des Manitu'] },
  { files: ['ViewingActivity.csv'], fmt: 'netflix', items: ['series:Dark', 'film:Alien'], skipped: 1 },
  { files: ['trakt_watched-shows.json', 'trakt_watchlist-movies.json'], fmt: 'trakt', items: ['series:Severance[2]', 'film:Alien'] },
  { files: ['SimklBackup.csv'], fmt: 'simkl', items: ['series:The Simpsons', 'series:Sousou no Frieren', 'film:The Dog Stars'] }, // echter Export (23.11.2026)
  { files: ['serializd-list.csv'], fmt: 'serializd', items: ['series:The Lord of the Rings: The Rings of Power'] }, // echter Export über Listizd (23.11.2026)
  { files: ['podcasts.opml'], fmt: 'opml', items: ['podcast:Lage der Nation', 'podcast:Fest & Flauschig'] },
  { files: ['eigene_liste.txt'], fmt: null, list: 'artist', items: ['artist:Radiohead', 'artist:Blind Guardian', 'artist:Nightwish'] },
];
(async () => {
  let bad = 0;
  for (const c of CASES) {
    const files = await load(c.files), d = IMPF.detect(files), id = d ? d.id : null;
    const f = d || IMPF.byId('list'), r = f.parse(files, c.list || 'artist'), got = fmt(r.items);
    const ok = id === c.fmt && JSON.stringify(got) === JSON.stringify(c.items) && (c.skipped == null || r.skipped === c.skipped);
    if (!ok) bad++;
    console.log((ok ? 'OK    ' : 'FEHLER') + ' ' + c.files.join(' + ') + ' → ' + (id || 'Liste') + (ok ? '' : '\n       erwartet ' + c.fmt + ' ' + JSON.stringify(c.items) + (c.skipped != null ? ' übersprungen ' + c.skipped : '') + '\n       erhalten ' + id + ' ' + JSON.stringify(got) + ' übersprungen ' + r.skipped + (r.note ? ' (' + r.note + ')' : '')));
  }
  console.log(bad ? bad + ' Fehler' : 'Alle ' + CASES.length + ' Fälle in Ordnung');
  process.exit(bad ? 1 : 0);
})();
