<?php
// Suchmaschinen-Schalter. Standard: Seite ist NICHT öffentlich (noindex).
// Zum Start in config.php eintragen:  'seo_public' => true,
function seo_cfg(): array {
    if (!isset($GLOBALS['CFG'])) $GLOBALS['CFG'] = is_file(__DIR__ . '/../config.php') ? require __DIR__ . '/../config.php' : [];
    return $GLOBALS['CFG'];
}
function seo_public(): bool { return !empty(seo_cfg()['seo_public']); }
function seo_base(): string { return rtrim(seo_cfg()['base_url'] ?? 'https://schondraussen.de', '/'); }

const SEO_TITLE = 'Schondraussen.de – Neuerscheinungen deiner Musiker, Autoren, Filme und Serien';
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
            . '<meta name="twitter:card" content="summary_large_image">';
    }
    return $o;
}
