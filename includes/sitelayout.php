<?php
/* Website header & footer settings (1.6.0) — edited in Admin › Header & Footer.
   Stored in settings.site_header and settings.site_footer (JSON). Missing values fall back to the defaults. */

function header_nav_defaults()
{
    return [
        'home' => 'Home', 'rent' => 'Condos for Rent', 'sale' => 'Condos for Sale', 'locations' => 'Locations',
        'list' => 'List Your Property', 'about' => 'About Us', 'contact' => 'Contact Us',
    ];
}

function site_header_cfg()
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $s = json_decode((string) setting('site_header', '{}'), true);
    $c = site_header_clean(is_array($s) ? $s : []);
    return $c;
}

function site_header_clean(array $s)
{
    $nav = [];
    $i = 0;
    $saved = isset($s['nav']) && is_array($s['nav']) ? $s['nav'] : [];
    foreach (header_nav_defaults() as $k => $label) {
        $x = isset($saved[$k]) && is_array($saved[$k]) ? $saved[$k] : [];
        $nav[$k] = [
            'label' => isset($x['label']) && trim($x['label']) !== '' ? mb_substr(trim($x['label']), 0, 40) : $label,
            'show' => !isset($x['show']) || !empty($x['show']),
            'order' => isset($x['order']) ? (int) $x['order'] : ($i + 1) * 10,
        ];
        $i++;
    }
    uasort($nav, function ($a, $b) { return $a['order'] <=> $b['order']; });
    $links = [];
    foreach ((isset($s['links']) && is_array($s['links']) ? $s['links'] : []) as $l) {
        $label = mb_substr(trim((string) ($l['label'] ?? '')), 0, 40);
        $u = mb_substr(trim((string) ($l['url'] ?? '')), 0, 255);
        if ($label !== '' && $u !== '') {
            $links[] = ['label' => $label, 'url' => $u, 'new' => !empty($l['new'])];
        }
    }
    $ann = isset($s['ann']) && is_array($s['ann']) ? $s['ann'] : [];
    return [
        'nav' => $nav,
        'links' => array_slice($links, 0, 6),
        'call_show' => !isset($s['call_show']) || !empty($s['call_show']),
        'call_label' => isset($s['call_label']) && trim($s['call_label']) !== '' ? mb_substr(trim($s['call_label']), 0, 30) : 'Call Us',
        'call_number' => mb_substr(trim((string) ($s['call_number'] ?? '')), 0, 40),
        'sticky' => !isset($s['sticky']) || !empty($s['sticky']),
        'ann' => [
            'show' => !empty($ann['show']),
            'text' => mb_substr(trim((string) ($ann['text'] ?? '')), 0, 200),
            'link' => mb_substr(trim((string) ($ann['link'] ?? '')), 0, 255),
            'link_text' => mb_substr(trim((string) ($ann['link_text'] ?? '')), 0, 40),
            'style' => in_array($ann['style'] ?? '', ['gold', 'teal', 'navy', 'red'], true) ? $ann['style'] : 'gold',
        ],
    ];
}

function footer_link_defaults()
{
    return [
        ['label' => 'Condos for Rent', 'url' => 'properties.php?type=rent'],
        ['label' => 'Condos for Sale', 'url' => 'properties.php?type=sale'],
        ['label' => 'List Your Property', 'url' => 'list-property.php'],
        ['label' => 'About Us', 'url' => 'about.php'],
        ['label' => 'Contact Us', 'url' => 'contact.php'],
    ];
}

function site_footer_cfg()
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $s = json_decode((string) setting('site_footer', '{}'), true);
    $c = site_footer_clean(is_array($s) ? $s : []);
    return $c;
}

function site_footer_clean(array $s)
{
    $txt = function ($k, $def, $max) use ($s) { return isset($s[$k]) && trim((string) $s[$k]) !== '' ? mb_substr(trim((string) $s[$k]), 0, $max) : $def; };
    $links = [];
    if (isset($s['links']) && is_array($s['links'])) {
        foreach ($s['links'] as $l) {
            $label = mb_substr(trim((string) ($l['label'] ?? '')), 0, 40);
            $u = mb_substr(trim((string) ($l['url'] ?? '')), 0, 255);
            if ($label !== '' && $u !== '') {
                $links[] = ['label' => $label, 'url' => $u];
            }
        }
    } else {
        $links = footer_link_defaults();
    }
    return [
        'show_logo' => !isset($s['show_logo']) || !empty($s['show_logo']),
        'show_social' => !isset($s['show_social']) || !empty($s['show_social']),
        'col1' => $txt('col1', 'Explore', 40),
        'links' => array_slice($links, 0, 10),
        'show_locations' => !isset($s['show_locations']) || !empty($s['show_locations']),
        'col2' => $txt('col2', 'Locations', 40),
        'loc_count' => max(1, min(20, (int) ($s['loc_count'] ?? 6) ?: 6)),
        'show_contact' => !isset($s['show_contact']) || !empty($s['show_contact']),
        'col3' => $txt('col3', 'Contact', 40),
        'copyright' => $txt('copyright', '© {year} {site}. All rights reserved.', 200),
        'tagline' => isset($s['tagline']) ? mb_substr(trim((string) $s['tagline']), 0, 200) : 'Condominium Rentals • Property Sales • Metro Manila, Philippines',
        'admin_link' => !empty($s['admin_link']),
    ];
}

/** {year} and {site} in footer texts. */
function footer_text($t)
{
    return strtr($t, ['{year}' => date('Y'), '{site}' => setting('site_name', 'K&M Urban Properties')]);
}
