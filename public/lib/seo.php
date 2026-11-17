<?php
// Suchmaschinen-Schalter. Standard: Seite ist NICHT öffentlich (noindex).
// Zum Start in config.php eintragen:  'seo_public' => true,
function seo_cfg(): array {
    if (!isset($GLOBALS['CFG'])) $GLOBALS['CFG'] = is_file(__DIR__ . '/../config.php') ? require __DIR__ . '/../config.php' : [];
    return $GLOBALS['CFG'];
}
// Gibt es eine Amazon-Partner-ID? Nur dann erscheinen Hinweise auf Partnerlinks/Werbung.
function amz_active(): bool {
    try { if (!function_exists('db')) require_once __DIR__ . '/bootstrap.php'; $q = db()->prepare('SELECT v FROM site_settings WHERE k=?'); $q->execute(['tag']); return trim((string) $q->fetchColumn()) !== ''; } catch (Throwable $e) { return false; }
}
function seo_public(): bool { return !empty(seo_cfg()['seo_public']); }
function seo_base(): string { return rtrim(seo_cfg()['base_url'] ?? 'https://schondraussen.de', '/'); }

const SEO_TITLE = 'Schondraussen.de – Nie wieder eine Neuerscheinung verpassen';
const SEO_DESC  = 'Alle Neuerscheinungen deiner Lieblingsmusiker, Autor:innen, Filme und Serien an einem Ort: Werkübersicht, Kalender und Erinnerungen per Mail. Jetzt registrieren.';

// Header senden und Head-Tags (robots, ggf. Canonical/Description/Open Graph) ausgeben.
// $indexable=false erzwingt noindex (z. B. für eingeloggte Nutzer).
function seo_head(string $path, string $title, ?string $desc = null, bool $indexable = true): string {
    $pub = $indexable && seo_public();
    if (!$pub) {
        header('X-Robots-Tag: noindex, nofollow');
        return '<meta name="robots" content="noindex,nofollow">';
    }
    $url = seo_base() . $path;
    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $o = '<link rel="canonical" href="' . $e($url) . '">';
    if ($desc) {
        $o .= '<meta name="description" content="' . $e($desc) . '">'
            . '<meta property="og:type" content="website"><meta property="og:locale" content="de_DE">'
            . '<meta property="og:site_name" content="Schondraussen.de">'
            . '<meta property="og:title" content="' . $e($title) . '"><meta property="og:description" content="' . $e($desc) . '">'
            . '<meta property="og:url" content="' . $e($url) . '">'
            . '<meta property="og:image" content="' . $e(seo_base() . '/shots/shot1.webp') . '">'
            . '<meta property="og:image:width" content="1228"><meta property="og:image:height" content="992"><meta property="og:image:type" content="image/webp">'
            . '<meta name="twitter:card" content="summary_large_image">';
    }
    return $o;
}

/** Eigenes Farbschema „Individuell“: geprüfte Farben (nur #rrggbb) als CSS für :root[data-skin=custom], sonst leer. */
function custom_skin_css(?string $json): string {
    $c = json_decode((string) $json, true); if (!is_array($c)) return '';
    $map = ['bg' => '--bg', 'surface' => '--surface', 'ink' => '--ink', 'muted' => '--muted', 'line' => '--line', 'accent' => '--accent', 'accentInk' => '--accent-ink'];
    $o = '';
    foreach ($map as $k => $var) { $v = (string) ($c[$k] ?? ''); if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) return ''; $o .= "$var:$v;"; }
    $o .= '--accent-soft:color-mix(in srgb,' . $c['accent'] . ' 18%,' . $c['surface'] . ');--frame:' . $c['ink'] . ';';
    if (!empty($c['dark'])) $o .= '--danger:#f6655a;--c-author:#f09f42;--c-artist:#31c4b0;--c-film:#9b81f8;--c-series:#f36892;--c-podcast:#5aa7f0;color-scheme:dark;';
    else $o .= 'color-scheme:light;';
    return ':root[data-skin=custom]{' . $o . '}';
}

