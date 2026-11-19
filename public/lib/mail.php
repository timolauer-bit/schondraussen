<?php
// Versand über den Mailserver des Webspace. Absender kommt aus config.php (mail.from).
//
// Mails werden als Klartext zusammengestellt und hier in eine HTML-Fassung im Stil der Seite umgesetzt (Design „Karte“, 19.11.2026):
// Tabellenlayout mit Inline-CSS (Gmail, Outlook, Apple Mail), serifenlose Systemschriften, Dark Mode per <style>, barrierefrei
// (lang/dir, <title>, eine h1, h2 je Abschnitt, role="presentation" an Layout-Tabellen, Kontraste mind. 4,5:1).
// Klartext-Regeln, die mail_html() erkennt:
//   - {series} Name – Titel (20.11.2026)   Eintrag mit Kategorie (author, artist, film, series, podcast); Kategorie und Datum optional
//     eingerückte Zeilen darunter          Kurzinfo zum Eintrag ("  Presse: Schlagzeile (Quelle)" + Folgezeile mit Adresse = Schlagzeile mit Link)
//   GROSSBUCHSTABEN-ZEILE / --- Text ---    Zwischenüberschrift (h2)
//   [[Beschriftung|https://…]]            Knopf;  "Alles im Überblick: https://…" wird ebenfalls ein Knopf
//   Beschriftung: https://…               Link mit der Beschriftung als Text
//   ---------- (3+ Striche)               Trennlinie;  "--" allein beginnt den Fußbereich (Abmeldelink, Impressum)
// Der Betreff wird zur Hauptüberschrift. Die Klartext-Fassung der Mail bekommt die Markierungen nicht zu sehen (mail_plain).

/** Klartext-Fassung: Kategorie-Markierungen entfernen, Knöpfe als nackte Adresse. */
function mail_plain(string $text): string {
    $text = preg_replace('~^- \{(?:author|artist|film|series|podcast)\} ~mu', '- ', $text);
    return preg_replace('~^\[\[(.+)\|(https?://\S+)\]\]$~mu', '$2', $text);
}

function mail_html(string $text, string $subject = ''): string {
    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    $base = rtrim((string) (function_exists('cfg') ? cfg('base_url', 'https://schondraussen.de') : 'https://schondraussen.de'), '/');
    $F = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    // Farben der Seite (hell). Dunkle Werte stehen unten im <style>.
    $C = ['bg' => '#f2f2f0', 'card' => '#ffffff', 'ink' => '#11171d', 'muted' => '#58616a', 'line' => '#d6d6d1', 'acc' => '#bf4416'];
    // Kategorie: Bezeichnung, Farbe für Streifen (wie auf der Seite), Schrift hell (abgedunkelt, mind. 5,5:1), Schrift dunkel
    $CAT = ['series' => ['Serie', '#d53f6c', '#b52a55', '#f36892'], 'artist' => ['Musik', '#179788', '#0f7568', '#31c4b0'],
            'author' => ['Buch', '#c77523', '#9a5410', '#f09f42'], 'film' => ['Film', '#7a5de5', '#6243cf', '#9b81f8'],
            'podcast' => ['Podcast', '#2f7fd1', '#2468b0', '#5aa2ec']];
    $HEAD = ['HEUTIGE VERÖFFENTLICHUNGEN' => 'Heute erscheint', 'NEU ERSCHIENEN' => 'Neu erschienen', 'NEU ANGEKÜNDIGT' => 'Neu angekündigt', 'NEUE PODCAST-FOLGEN' => 'Neue Podcast-Folgen'];
    $short = fn($u) => mb_strlen($u) > 48 ? preg_replace('~^https?://(www\.)?~', '', mb_substr($u, 0, 44)) . '…' : preg_replace('~^https?://(www\.)?~', '', rtrim($u, '/'));
    $a = fn($u, $label, $cls = 'sd-acc', $col = null) => '<a href="' . $e($u) . '" class="' . $cls . '" style="color:' . ($col ?? $C['acc']) . ';text-decoration:underline">' . $e($label) . '</a>';
    $withLinks = function (string $t) use ($e, $a, $short) {
        $r = ''; foreach (preg_split('~(https?://[^\s<>"]+)~', $t, -1, PREG_SPLIT_DELIM_CAPTURE) as $k => $p) $r .= $k % 2 ? $a($p, $short($p)) : $e($p);
        return $r;
    };
    $wd = function (string $d) { // 20.11.2026 → Fr, 20.11.2026
        if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $d, $m) || !checkdate((int) $m[2], (int) $m[1], (int) $m[3])) return $d;
        return ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][(int) date('N', mktime(12, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3])) - 1] . ', ' . $d;
    };

    // ---- Klartext in Bausteine zerlegen ----
    $B = []; $foot = []; $inFoot = false; $cur = null; $sec = null; $items = [];
    $L = explode("\n", str_replace(["\r\n", "\r"], "\n", $text)); $n = count($L);
    for ($i = 0; $i < $n; $i++) {
        $l = rtrim($L[$i]);
        if ($inFoot) { if ($l !== '') $foot[] = $l; continue; }
        if ($l === '') continue;
        if ($l === '--') { $inFoot = true; $cur = null; continue; }
        if (preg_match('/^  Presse: (.*)$/u', $l, $m) && isset($L[$i + 1]) && preg_match('~^  (https?://\S+)\s*$~', $L[$i + 1], $u)) {
            $t = $m[1]; $src = ''; if (preg_match('/^(.*) \(([^()]+)\)$/u', $t, $q)) { $t = $q[1]; $src = $q[2]; }
            $h = 'Presse: ' . $a($u[1], $t) . ($src !== '' ? ' (' . $e($src) . ')' : ''); $i++;
            if ($cur !== null) $B[$cur]['info'][] = $h; else $B[] = ['t' => 'p', 'h' => $h, 'muted' => true];
            continue;
        }
        if (strpos($l, '  ') === 0) { // eingerückte Kurzinfo: umgebrochene Zeilen wieder zusammenfügen
            $t = ltrim($l);
            while (isset($L[$i + 1]) && strpos($L[$i + 1], '  ') === 0 && !preg_match('/^  Presse: /u', $L[$i + 1])) { $i++; $t .= ' ' . trim($L[$i]); }
            if ($cur !== null) $B[$cur]['info'][] = $withLinks($t); else $B[] = ['t' => 'p', 'h' => $withLinks($t), 'muted' => true];
            continue;
        }
        $cur = null;
        if (preg_match('~^\[\[(.+)\|(https?://\S+)\]\]$~u', $l, $m)) { $B[] = ['t' => 'btn', 'label' => $m[1], 'url' => $m[2]]; continue; }
        if (preg_match('~^Alles im Überblick: (https?://\S+)$~u', $l, $m)) { $B[] = ['t' => 'btn', 'label' => 'Alles im Überblick ansehen', 'url' => $m[1]]; continue; }
        if (preg_match('/^-{3,}$/', $l)) { $B[] = ['t' => 'hr']; continue; }
        if (preg_match('/^--- (.+) ---$/u', $l, $m)) { $B[] = ['t' => 'h2', 'text' => $m[1], 'n' => 0]; $sec = count($B) - 1; continue; }
        if (preg_match('/^[A-ZÄÖÜ][A-ZÄÖÜ \-]{3,}$/u', $l)) { $B[] = ['t' => 'h2', 'text' => $HEAD[$l] ?? mb_convert_case(mb_strtolower($l), MB_CASE_TITLE), 'n' => 0]; $sec = count($B) - 1; continue; }
        if (strpos($l, '- ') === 0) {
            $t = substr($l, 2); $cat = '';
            if (preg_match('/^\{(author|artist|film|series|podcast)\} (.*)$/u', $t, $m)) { $cat = $m[1]; $t = $m[2]; }
            $date = ''; if (preg_match('/^(.*) \((\d{2}\.\d{2}\.\d{4}|\d{2}\.\d{4}|\d{4})\)$/u', $t, $m)) { $t = $m[1]; $date = $m[2]; }
            $name = ''; if (($p = mb_strpos($t, ' – ')) !== false) { $name = mb_substr($t, 0, $p); $t = mb_substr($t, $p + 3); }
            $B[] = ['t' => 'item', 'cat' => $cat, 'name' => $name, 'title' => $t, 'date' => $date, 'info' => []];
            $cur = count($B) - 1; if ($sec !== null) $B[$sec]['n']++;
            $items[] = $name !== '' ? $name . ' – ' . $t : $t;
            continue;
        }
        if (preg_match('~^(.{2,70}?):\s+(https?://\S+)$~u', $l, $m)) { $B[] = ['t' => 'p', 'h' => $a($m[2], $m[1])]; continue; }
        $B[] = ['t' => 'p', 'h' => $withLinks($l)];
    }

    // ---- HTML ----
    $title = trim(preg_replace('/^Schondraussen\.de:\s*/u', '', $subject));
    $pre = $items ? implode(' · ', array_slice($items, 0, 3)) . (count($items) > 3 ? ' …' : '') : '';
    if ($pre === '') foreach ($B as $b) if ($b['t'] === 'p' && !preg_match('/^Hallo\b/u', strip_tags($b['h']))) { $pre = html_entity_decode(strip_tags($b['h']), ENT_QUOTES, 'UTF-8'); break; }
    $pre = mb_substr($pre, 0, 110);
    $pad = 'padding-left:32px;padding-right:32px';
    $T = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
    $rows = '';
    if ($title !== '') $rows .= '<tr><td class="sd-pad" style="padding-top:28px;padding-bottom:4px;' . $pad . ';font-family:' . $F . '"><h1 class="sd-ink sd-h1" style="margin:0;font-size:26px;line-height:1.25;letter-spacing:-.01em;font-weight:700;color:' . $C['ink'] . '">' . $e($title) . '</h1></td></tr>';
    $first = true; $prev = '';
    foreach ($B as $b) {
        $top = $first ? ($title !== '' ? 8 : 28) : ($b['t'] === 'h2' ? 22 : ($b['t'] === 'item' && $prev === 'item' ? 0 : ($b['t'] === 'item' ? 10 : 10)));
        $first = false; $prev = $b['t'];
        $td = '<tr><td class="sd-pad" style="padding-top:' . $top . 'px;' . $pad . ';font-family:' . $F . '">';
        switch ($b['t']) {
            case 'h2':
                $rows .= $td . '<h2 class="sd-ink" style="margin:0;font-size:18px;line-height:1.3;font-weight:700;color:' . $C['ink'] . '">' . $e($b['text']) . ($b['n'] ? ' <span class="sd-muted" style="font-weight:400;color:' . $C['muted'] . '">(' . $b['n'] . ')</span>' : '') . '</h2></td></tr>';
                break;
            case 'item':
                $c = $CAT[$b['cat']] ?? null;
                $info = '';
                foreach ($b['info'] as $x) $info .= '<p class="sd-muted" style="margin:6px 0 0;font-size:14px;line-height:1.5;color:' . $C['muted'] . '">' . $x . '</p>';
                $rows .= $td . $T . '<tr><td style="padding:0 0 12px">' . $T . '<tr>'
                    . '<td width="4" style="width:4px;background:' . ($c[1] ?? $C['line']) . ';border-radius:2px;font-size:0;line-height:0">&nbsp;</td>'
                    . '<td style="padding:2px 0 2px 14px;font-family:' . $F . '">' . $T . '<tr>'
                    . '<td class="sd-stack" style="font-family:' . $F . ';vertical-align:top">'
                    . ($c ? '<p class="sd-c-' . $b['cat'] . '" style="margin:0 0 2px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:' . $c[2] . '">' . $c[0] . '</p>' : '')
                    . '<p class="sd-ink" style="margin:0;font-size:16px;line-height:1.4;color:' . $C['ink'] . '">' . ($b['name'] !== '' ? '<strong>' . $e($b['name']) . '</strong> – ' : '') . $e($b['title']) . '</p></td>'
                    . ($b['date'] !== '' ? '<td class="sd-stack sd-muted" align="right" style="font-family:' . $F . ';vertical-align:top;white-space:nowrap;padding-left:12px;padding-top:' . ($c ? 18 : 1) . 'px;font-size:14px;line-height:1.4;color:' . $C['muted'] . '">' . $e($wd($b['date'])) . '</td>' : '')
                    . '</tr></table>' . $info . '</td></tr></table></td></tr></table></td></tr>';
                break;
            case 'btn':
                $rows .= '<tr><td class="sd-pad" style="padding-top:' . max($top, 14) . 'px;padding-bottom:6px;' . $pad . '">'
                    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td class="sd-btn" style="background:' . $C['acc'] . ';border-radius:8px">'
                    . '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="' . $e($b['url']) . '" style="height:44px;v-text-anchor:middle;width:' . (60 + 9 * mb_strlen($b['label'])) . 'px" arcsize="18%" fillcolor="' . $C['acc'] . '" stroke="f"><center style="color:#ffffff;font-family:Segoe UI,Arial,sans-serif;font-size:16px;font-weight:bold">' . $e($b['label']) . '</center></v:roundrect><![endif]-->'
                    . '<!--[if !mso]><!--><a href="' . $e($b['url']) . '" class="sd-btn" style="display:inline-block;padding:12px 22px;font-family:' . $F . ';font-size:16px;font-weight:700;line-height:20px;color:#ffffff;text-decoration:none;border-radius:8px;background:' . $C['acc'] . '">' . $e($b['label']) . '</a><!--<![endif]-->'
                    . '</td></tr></table></td></tr>';
                break;
            case 'hr':
                $rows .= $td . '<div class="sd-line" style="border-top:1px solid ' . $C['line'] . ';height:1px;line-height:1px;font-size:0;margin:6px 0">&nbsp;</div></td></tr>';
                break;
            default:
                $m = !empty($b['muted']);
                $rows .= $td . '<p class="' . ($m ? 'sd-muted' : 'sd-ink') . '" style="margin:0;font-size:' . ($m ? 14 : 16) . 'px;line-height:1.5;color:' . ($m ? $C['muted'] : $C['ink']) . '">' . $b['h'] . '</p></td></tr>';
        }
    }
    $rows .= '<tr><td style="height:30px;line-height:30px;font-size:0">&nbsp;</td></tr>';

    // Fußbereich: Hinweistext plus Links (Einstellungen, Abmelden, Impressum) in einer Zeile
    $fl = []; $ft = []; $mc = $C['muted'];
    foreach ($foot as $l) {
        if (preg_match('~^Keine Mails mehr: (https?://\S+)$~u', $l, $m)) $fl[] = $a($m[1], 'Keine Mails mehr (abmelden)', 'sd-muted', $mc);
        elseif (preg_match('~^(.{2,70}?):\s+(https?://\S+)$~u', $l, $m)) $fl[] = $a($m[2], $m[1], 'sd-muted', $mc);
        elseif (preg_match('~^https?://\S+impressum\.php$~', $l)) $fl[] = $a($l, 'Impressum', 'sd-muted', $mc);
        elseif (preg_match('~^https?://\S+$~', $l)) $fl[] = $a($l, $short($l), 'sd-muted', $mc);
        else $ft[] = $e($l);
    }
    if (!$fl) $fl[] = $a($base . '/impressum.php', 'Impressum', 'sd-muted', $mc);
    $fp = fn($h, $mb) => '<p class="sd-muted" style="margin:0 0 ' . $mb . 'px;font-family:' . $F . ';font-size:13px;line-height:1.55;color:' . $mc . '">' . $h . '</p>';
    $footer = ($ft ? $fp(implode('<br>', $ft), 8) : '') . $fp(implode(' &nbsp;·&nbsp; ', $fl), 0);

    $dark = ['.sd-bg{background:#101419!important}', '.sd-card{background:#171d23!important;border-color:#2e353e!important}',
             '.sd-ink{color:#e8ecee!important}', '.sd-muted{color:#9aa3ac!important}', '.sd-line{border-color:#2e353e!important}',
             '.sd-acc{color:#ff7842!important}', '.sd-btn{background:#ff7842!important;color:#101419!important}', '.sd-name{color:#e8ecee!important}'];
    foreach ($CAT as $k => $c) $dark[] = '.sd-c-' . $k . '{color:' . $c[3] . '!important}';
    $css = '@media (prefers-color-scheme: dark){' . implode('', $dark) . '}' . implode('', array_map(fn($r) => '[data-ogsc] ' . $r, $dark));
    $logo = $base . '/appicons/icon-192.png';

    return '<!doctype html><html lang="de" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><meta name="x-apple-disable-message-reformatting">'
        . '<title>' . $e($subject !== '' ? $subject : 'Schondraussen.de') . '</title>'
        . '<style>:root{color-scheme:light dark;supported-color-schemes:light dark}body{margin:0!important;padding:0!important;width:100%!important;-webkit-text-size-adjust:100%}h1,h2,p{margin:0}'
        . '@media (max-width:620px){.sd-pad{padding-left:18px!important;padding-right:18px!important}.sd-stack{display:block!important;width:100%!important;text-align:left!important;padding-left:0!important;padding-top:3px!important}.sd-h1{font-size:24px!important}}'
        . $css . '</style></head>'
        . '<body class="sd-bg" style="margin:0;padding:0;background:' . $C['bg'] . '">'
        . '<div lang="de" dir="ltr" class="sd-bg" style="background:' . $C['bg'] . ';font-family:' . $F . '">'
        . ($pre !== '' ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">' . $e($pre) . str_repeat('&#847;&zwnj;&nbsp;', 30) . '</div>' : '')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="sd-bg" style="background:' . $C['bg'] . '"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px">'
        . '<tr><td style="padding:0 4px 14px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td width="36" style="width:36px"><img src="' . $e($logo) . '" width="36" height="36" alt="" style="display:block;border:0;border-radius:8px"></td>'
        . '<td class="sd-ink" style="padding-left:10px;font-family:' . $F . ';font-size:17px;font-weight:800;letter-spacing:-.01em;color:' . $C['ink'] . '">Schondraussen.de</td></tr></table></td></tr>'
        . '<tr><td class="sd-card" style="background:' . $C['card'] . ';border:1px solid ' . $C['line'] . ';border-radius:14px;overflow:hidden">' . $T
        . '<tr><td class="sd-btn" style="height:5px;line-height:5px;font-size:0;background:' . $C['acc'] . '">&nbsp;</td></tr>'
        . $rows . '</table></td></tr>'
        . '<tr><td style="padding:18px 8px 8px">' . $footer . '</td></tr>'
        . '</table></td></tr></table></div></body></html>';
}
function send_mail(string $to, string $subject, string $text, array $extra = []): bool {
    $m = cfg('mail', []);
    $from = $m['from'] ?? 'benachrichtigung@schondraussen.de';
    $name = $m['from_name'] ?? 'Schondraussen.de';
    $enc = fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'From: ' . $enc($name) . ' <' . $from . '>',
        'MIME-Version: 1.0',
    ];
    $b = '=_sd_' . bin2hex(random_bytes(8));
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
    $hasReply = false; foreach ($extra as $h) { $headers[] = $h; if (stripos($h, 'Reply-To:') === 0) $hasReply = true; }
    if (!$hasReply) $headers[] = 'Reply-To: ' . $from;
    $part = fn($type, $body) => "--$b\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
    $body = $part('text/plain', mail_plain($text)) . $part('text/html', mail_html($text, $subject)) . "--$b--\r\n";
    return mail($to, $enc($subject), $body, implode("\r\n", $headers), '-f' . $from);
}
