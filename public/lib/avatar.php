<?php
// Bilder von DiceBear (api.dicebear.com): Der Server holt das SVG einmal selbst, prüft es streng und speichert es. Das Bild wird nie von außen eingebunden.
function avatar_svg_clean(string $svg): ?string {
    $svg = trim($svg); $svg = preg_replace('/^\xEF\xBB\xBF/', '', $svg); $svg = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg);
    $svg = preg_replace('~<metadata\b.*?</metadata>~is', '', $svg); // Lizenzangaben (RDF) stehen in der Quelladresse, nicht im Bild
    if (strlen($svg) > 80000 || stripos($svg, '<svg') !== 0) return null;
    if (preg_match('/<script|<foreignObject|<iframe|<object|<embed|<audio|<video|<image|<a[\s>]|<!ENTITY|<!DOCTYPE|\son[a-z]+\s*=|javascript:|data:|@import|(?:xlink:)?href\s*=\s*["\']?(?!#)|url\(\s*["\']?(?!#)/i', $svg)) return null;
    $prev = libxml_use_internal_errors(true); $x = simplexml_load_string($svg, 'SimpleXMLElement', LIBXML_NONET); libxml_clear_errors(); libxml_use_internal_errors($prev);
    if (!$x || strtolower($x->getName()) !== 'svg') return null;
    return $svg;
}
/** Prüft einen DiceBear-Link und gibt die neu aufgebaute Adresse zurück (nur api.dicebear.com, Pfad /<Version>.x/<Stil>/svg, einfache Optionen) oder null. */
function dicebear_url(string $in): ?string {
    $in = trim($in); if ($in === '' || strlen($in) > 900 || preg_match('/[\s"\'<>\\\\]/', $in)) return null;
    $p = parse_url($in); if (!$p || ($p['scheme'] ?? '') !== 'https' || strtolower($p['host'] ?? '') !== 'api.dicebear.com' || isset($p['port']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])) return null;
    $path = $p['path'] ?? ''; if (!preg_match('~^/\d{1,2}\.x/[a-z0-9][a-z0-9-]{1,40}/svg$~', $path)) return null;
    $q = $p['query'] ?? ''; if (!preg_match('/^[A-Za-z0-9_.,=&%~+\-]{0,800}$/', $q)) return null;
    return 'https://api.dicebear.com' . $path . ($q !== '' ? '?' . $q : '');
}
/** Holt und prüft das SVG. Rückgabe [svg|null, Fehlertext, geprüfte Adresse]. */
function dicebear_fetch(string $in): array {
    $url = dicebear_url($in); if ($url === null) return [null, 'Das ist kein gültiger DiceBear-Link. Er muss mit https://api.dicebear.com/ beginnen und auf /svg enden (z. B. https://api.dicebear.com/9.x/avataaars/svg?seed=Felix).', null];
    $buf = ''; $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$buf) { $buf .= $d; return strlen($buf) > 150000 ? 0 : strlen($d); }]);
    curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($code !== 200 || $buf === '') return [null, 'DiceBear hat das Bild nicht geliefert (Status ' . $code . '). Prüfe den Link oder versuche es später noch einmal.', null];
    if (stripos($ct, 'svg') === false) return [null, 'Der Link liefert kein SVG-Bild.', null];
    $svg = avatar_svg_clean($buf);
    if ($svg === null) return [null, 'Dieses Bild enthält Teile, die wir aus Sicherheitsgründen nicht übernehmen (z. B. eingebettete Fotos). Wähle bitte einen anderen Stil.', null];
    return [$svg, '', $url];
}
