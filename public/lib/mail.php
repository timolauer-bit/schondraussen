<?php
// Versand über den Mailserver des Webspace. Absender kommt aus config.php (mail.from).

/** Macht aus dem Klartext der Mail eine schlichte HTML-Fassung: Schlagzeilen werden zu Links, lange Adressen verschwinden hinter dem Text. */
function mail_html(string $text): string {
    $e = fn($t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $link = fn($u, $label = null) => '<a href="' . $e($u) . '" style="color:#a23f1c">' . $e($label ?? (mb_strlen($u) > 48 ? preg_replace('~^https?://(www\.)?~', '', mb_substr($u, 0, 44)) . '…' : preg_replace('~^https?://(www\.)?~', '', rtrim($u, '/')))) . '</a>';
    $withLinks = function (string $t) use ($e, $link) {
        $r = ''; foreach (preg_split('~(https?://[^\s<>"]+)~', $t, -1, PREG_SPLIT_DELIM_CAPTURE) as $k => $p) $r .= $k % 2 ? $link($p) : $e($p);
        return $r;
    };
    $L = explode("\n", $text); $o = []; $n = count($L);
    for ($i = 0; $i < $n; $i++) {
        $l = rtrim($L[$i]);
        if ($l === '') { continue; }
        if (preg_match('/^  Presse: (.*)$/u', $l, $m) && isset($L[$i + 1]) && preg_match('~^  (https?://\S+)\s*$~', $L[$i + 1], $u)) {
            $t = $m[1]; $src = ''; if (preg_match('/^(.*) \(([^()]+)\)$/u', $t, $q)) { $t = $q[1]; $src = $q[2]; }
            $o[] = '<div style="margin:2px 0 0 14px;font-size:14px">Presse: ' . $link($u[1], $t) . ($src !== '' ? ' <span style="color:#777">(' . $e($src) . ')</span>' : '') . '</div>'; $i++; continue;
        }
        if ($l === '--') { $o[] = '<hr style="border:0;border-top:1px solid #ddd;margin:22px 0 8px">'; continue; }
        if (preg_match('~^Keine Mails mehr: (https?://\S+)$~', $l, $m)) { $o[] = '<div style="margin:8px 0 0;font-size:13px;color:#555">Keine Mails mehr: ' . $link($m[1], 'abmelden') . '</div>'; continue; }
        if (preg_match('/^[A-ZÄÖÜ][A-ZÄÖÜ \-]{3,}$/u', $l)) { $o[] = '<h3 style="margin:20px 0 4px;font-size:13px;letter-spacing:.06em;color:#a23f1c">' . $e($l) . '</h3>'; continue; }
        if (strpos($l, '- ') === 0) { $o[] = '<div style="margin:10px 0 0;font-weight:600">' . $e(substr($l, 2)) . '</div>'; continue; }
        if (strpos($l, '  ') === 0) { // eingerückte Kurzinfo: umgebrochene Zeilen wieder zusammenfügen
            $t = ltrim($l);
            while (isset($L[$i + 1]) && strpos($L[$i + 1], '  ') === 0 && !preg_match('/^  Presse: /u', $L[$i + 1])) { $i++; $t .= ' ' . trim($L[$i]); }
            $o[] = '<div style="margin:2px 0 0 14px;font-size:14px;color:#444">' . $withLinks($t) . '</div>'; continue;
        }
        $o[] = '<div style="margin:8px 0 0">' . $withLinks($l) . '</div>';
    }
    return '<!doctype html><html><body style="margin:0;padding:16px;background:#fff;color:#222;font:16px/1.45 -apple-system,Segoe UI,Roboto,Arial,sans-serif"><div style="max-width:620px">' . implode("\n", $o) . '</div></body></html>';
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
    $body = $part('text/plain', $text) . $part('text/html', mail_html($text)) . "--$b--\r\n";
    return mail($to, $enc($subject), $body, implode("\r\n", $headers), '-f' . $from);
}
