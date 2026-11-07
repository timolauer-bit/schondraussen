<?php
// Versand über den Mailserver des Webspace. Absender kommt aus config.php (mail.from).
function send_mail(string $to, string $subject, string $text, array $extra = []): bool {
    $m = cfg('mail', []);
    $from = $m['from'] ?? 'benachrichtigung@schondraussen.de';
    $name = $m['from_name'] ?? 'Schondraussen.de';
    $enc = fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'From: ' . $enc($name) . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $hasReply = false; foreach ($extra as $h) { $headers[] = $h; if (stripos($h, 'Reply-To:') === 0) $hasReply = true; }
    if (!$hasReply) $headers[] = 'Reply-To: ' . $from;
    return mail($to, $enc($subject), $text, implode("\r\n", $headers), '-f' . $from);
}
