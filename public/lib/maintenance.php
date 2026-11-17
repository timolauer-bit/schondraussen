<?php
// Wartungsmodus für die Zeit eines Deployments. Der Deploy legt vorab die Datei maintenance.flag in den Webroot und löscht sie am Ende wieder.
// Solange sie existiert (höchstens 10 Minuten, danach gilt sie als abgelaufen), zeigen alle PHP-Seiten und die API eine Wartungsmeldung mit Status 503.
// Ausnahmen: migrate.php, und status.php meldet für die Überwachung „OK“. Admin-Zugang: Adresse mit ?wartung=<cron_token> aufrufen (Cookie für 2 Stunden).
const MAINTENANCE_MAX_AGE = 600;
function maintenance_pages(): array {
    return [
        'v1' => <<<'NOWDOC'
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kleine Pause – Schondraussen.de</title>
<style>
:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#d84a1b}
@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;padding:20px}
.card{width:100%;max-width:520px;background:var(--surface);border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.08)}
.body{padding:20px 22px 24px;text-align:center}h1{margin:0 0 6px;font-size:22px}p{margin:0;color:var(--muted)}
.bar{height:6px;border-radius:3px;background:var(--line);overflow:hidden;margin:16px auto 0;max-width:300px}.bar i{display:block;height:100%;width:40%;background:var(--accent);border-radius:3px;animation:slide 1.8s ease-in-out infinite}
@keyframes slide{0%{transform:translateX(-100%)}100%{transform:translateX(260%)}}
.fine{font-size:13px;margin-top:12px}
@media(prefers-reduced-motion:reduce){*{animation:none!important}}

.pic{position:relative;height:320px;overflow:hidden;background:#222}
.pic img{position:absolute;left:0;width:100%;top:-300px;animation:zoom 9s ease-in-out infinite alternate;transform-origin:35% 38%}
@keyframes zoom{from{transform:scale(1)}to{transform:scale(1.3)}}
.bub{position:absolute;left:50%;top:14px;transform:translateX(-50%) scale(.8);background:#fff;color:#11171d;border-radius:14px;padding:8px 14px;font-weight:600;font-size:15px;opacity:0;box-shadow:0 4px 14px rgba(0,0,0,.25);white-space:nowrap}
.bub:after{content:"";position:absolute;left:30%;bottom:-8px;border:8px solid transparent;border-top-color:#fff;border-bottom:0}
.b1{animation:pop 12s infinite}.b2{animation:pop 12s infinite 4s}.b3{animation:pop 12s infinite 8s}
@keyframes pop{0%{opacity:0;transform:translateX(-50%) scale(.8)}4%,28%{opacity:1;transform:translateX(-50%) scale(1)}33%,100%{opacity:0;transform:translateX(-50%) scale(.8)}}
</style></head><body><main class="card"><div class="pic"><img alt="Matz und Lucy schauen aus einem Karton" src="/about/katzen-karton.webp">
<div class="bub b1">Pssst … wir bauen gerade um.</div><div class="bub b2">Nicht reinschauen!</div><div class="bub b3">Gleich sind wir fertig.</div></div>
<div class="body"><h1>Kleine Pause</h1><p>Matz und Lucy packen gerade ein Update aus. Die Seite ist gleich wieder da.</p><div class="bar"><i></i></div><p class="fine">Diese Seite lädt sich automatisch neu.</p></div></main><script>setTimeout(function(){location.reload()},20000)</script></body></html>
NOWDOC,
        'v2' => <<<'NOWDOC'
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kleine Pause – Schondraussen.de</title>
<style>
:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#d84a1b}
@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;padding:20px}
.card{width:100%;max-width:520px;background:var(--surface);border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.08)}
.body{padding:20px 22px 24px;text-align:center}h1{margin:0 0 6px;font-size:22px}p{margin:0;color:var(--muted)}
.bar{height:6px;border-radius:3px;background:var(--line);overflow:hidden;margin:16px auto 0;max-width:300px}.bar i{display:block;height:100%;width:40%;background:var(--accent);border-radius:3px;animation:slide 1.8s ease-in-out infinite}
@keyframes slide{0%{transform:translateX(-100%)}100%{transform:translateX(260%)}}
.fine{font-size:13px;margin-top:12px}
@media(prefers-reduced-motion:reduce){*{animation:none!important}}

.pic{position:relative;line-height:0}.pic img{width:100%;display:block}
.scr{position:absolute;left:5%;top:22.5%;width:81%;height:30%;clip-path:polygon(0 0,100% 15%,100% 84%,0 100%);background:#0e1620;border-radius:3px;padding:3.2% 4%;line-height:1.5;color:#8fe38f;font:600 clamp(9px,2.3vw,13px)/1.55 ui-monospace,Menlo,Consolas,monospace;overflow:hidden;box-shadow:0 0 24px rgba(80,200,120,.35) inset}
.scr div{white-space:nowrap;overflow:hidden;width:0;animation:type 8s steps(28) infinite}
.scr .l2{animation-delay:1.6s}.scr .l3{animation-delay:3.2s}.scr .l4{animation-delay:4.8s;color:#ffd166}
@keyframes type{0%{width:0}18%,92%{width:100%}100%{width:0}}
.pb{height:8px;border-radius:4px;background:#1f2d3a;margin-top:6px;overflow:hidden}.pb i{display:block;height:100%;background:#8fe38f;animation:fill 8s infinite}
@keyframes fill{0%{width:0}85%,92%{width:100%}100%{width:0}}
</style></head><body><main class="card"><div class="pic"><img alt="Matz und Lucy sitzen vor dem Monitor" src="/about/katzen-monitor.webp">
<div class="scr"><div class="l1">&gt; Update wird eingespielt …</div><div class="l2">&gt; Katzen prüfen die Qualität</div><div class="l3">&gt; Kabel wird nicht angeknabbert</div><div class="l4">&gt; fast fertig …</div><div class="pb"><i></i></div></div></div>
<div class="body"><h1>Kleine Pause</h1><p>Matz und Lucy sichern gerade die Qualität. Die Seite ist gleich wieder da.</p><p class="fine">Diese Seite lädt sich automatisch neu.</p></div></main><script>setTimeout(function(){location.reload()},20000)</script></body></html>
NOWDOC,
        's2' => <<<'NOWDOC'
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wartungsarbeiten – Schondraussen.de</title>
<style>
:root{--bg:#f2f2f0;--surface:#fff;--ink:#11171d;--muted:#58616a;--line:#d6d6d1;--accent:#d84a1b;--ok:#2e7d32}
@media(prefers-color-scheme:dark){:root{--bg:#101419;--surface:#171d23;--ink:#e8ecee;--muted:#9aa3ac;--line:#2e353e;--ok:#66bb6a}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--ink);font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;padding:20px}
h1{margin:0 0 8px;font-size:24px}p{margin:0;color:var(--muted)}a{color:var(--accent)}
.bar{height:6px;border-radius:3px;background:var(--line);overflow:hidden;margin:20px auto 0;max-width:320px}.bar i{display:block;height:100%;width:40%;background:var(--accent);border-radius:3px;animation:slide 1.8s ease-in-out infinite}
@keyframes slide{0%{transform:translateX(-100%)}100%{transform:translateX(260%)}}
@keyframes spin{to{transform:rotate(360deg)}}
@media(prefers-reduced-motion:reduce){*{animation:none!important}}

.card{width:100%;max-width:500px;background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:24px 26px;box-shadow:0 8px 30px rgba(0,0,0,.07)}
.hd{display:flex;gap:14px;align-items:center;margin-bottom:16px}.hd img{width:44px;height:44px;border-radius:10px}.hd h1{margin:0;font-size:20px}
ul{list-style:none;margin:0;padding:0;border-top:1px solid var(--line)}li{display:flex;gap:12px;align-items:center;padding:12px 2px;border-bottom:1px solid var(--line)}
.ic{flex:none;width:22px;height:22px;display:grid;place-items:center;border-radius:50%;font-size:13px;font-weight:700}
.done .ic{background:var(--ok);color:#fff}.run .ic{border:2.5px solid var(--line);border-top-color:var(--accent);animation:spin .9s linear infinite}.wait{color:var(--muted)}.wait .ic{border:2px dashed var(--line)}
li span.t{flex:1}li small{color:var(--muted)}
.ft{margin-top:16px;font-size:13px;color:var(--muted);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
</style></head><body><main class="card"><div class="hd"><img alt="" src="/favicon.svg"><div><h1>Wartungsarbeiten</h1><p>Wir sind in wenigen Minuten wieder da.</p></div></div>
<ul><li class="done"><span class="ic">✓</span><span class="t">Wartungsmodus eingeschaltet</span><small>fertig</small></li><li class="run"><span class="ic"></span><span class="t">Neue Dateien werden hochgeladen</span><small>läuft</small></li><li class="wait"><span class="ic"></span><span class="t">Seite wieder freigeben</span><small>danach</small></li></ul>
<div class="ft"><span>Nächster Versuch in <b id="c">15</b> s</span><a href="https://stats.uptimerobot.com/hWI2Kjc0jq">Statusseite</a></div></main>
<script>var n=15;setInterval(function(){n--;if(n<=0){location.reload();return}document.getElementById('c').textContent=n},1000)</script></body></html>
NOWDOC,
    ];
}
function maintenance_gate(): void {
    $flag = __DIR__ . '/../maintenance.flag';
    if (!is_file($flag)) return;
    if (time() - (int) @filemtime($flag) > MAINTENANCE_MAX_AGE) return; // Sicherheitsnetz: nach 10 Minuten verschwindet der Wartungsmodus von selbst
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'migrate.php' || $script === 'cron.php') return; // Cron und Migrationen laufen auch während der Wartung
    $key = (string) cfg('cron_token', ''); $ck = $key !== '' ? hash_hmac('sha256', 'wartung', $key) : '';
    if ($key !== '' && isset($_GET['wartung']) && hash_equals($key, (string) $_GET['wartung'])) { // Admin-Zugang per geheimem Link
        setcookie('sd_maint', $ck, ['expires' => time() + 7200, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?')); exit;
    }
    if ($ck !== '' && hash_equals($ck, (string) ($_COOKIE['sd_maint'] ?? ''))) return;
    header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
    if ($script === 'status.php') { header('Content-Type: text/plain; charset=utf-8'); echo "OK Wartung\n"; exit; } // Überwachung soll bei geplanter Wartung nicht anschlagen
    http_response_code(503); header('Retry-After: 120');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($uri, '/api/') !== false || stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') === 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Die Seite wird gerade aktualisiert. Bitte versuche es in ein paar Minuten noch einmal.', 'maintenance' => true], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($script === 'ics.php') { header('Content-Type: text/plain; charset=utf-8'); echo "Wartung\n"; exit; } // Kalender-Apps behalten ihren letzten Stand
    $p = maintenance_pages(); $k = array_keys($p); $html = $p[$k[random_int(0, count($k) - 1)]];
    header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
}
