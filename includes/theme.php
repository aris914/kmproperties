<?php
/* Admin › Theme & layout: colours, menu layout, page width and light/dark mode for the admin panel
   (modelled on the RBJocsing portal), and — unless turned off — the same colours on the public website.
   Stored in settings.admin_theme (JSON). */

function theme_defaults()
{
    return ['primary' => '#002c4c', 'accent' => '#0b7f72', 'highlight' => '#f4c25b', 'info' => '#2f6fb0',
            'danger' => '#c0392b', 'neutral' => '#7f8c9a', 'bg1' => '#001d33', 'bg2' => '#0a3d5f'];
}

function theme_labels()
{
    return [
        'primary' => ['Header & primary', 'Header bar, dark sidebar, main buttons'],
        'accent' => ['Accent', 'Save buttons, links and highlights'],
        'highlight' => ['Active menu item', 'The selected tab / menu item'],
        'info' => ['Info', 'Edit and other blue buttons'],
        'danger' => ['Danger', 'Delete and cancel buttons'],
        'neutral' => ['Neutral', 'Inactive tabs and secondary buttons'],
        'bg1' => ['Background top', 'Start of the page gradient'],
        'bg2' => ['Background bottom', 'End of the page gradient'],
    ];
}

function theme_presets()
{
    return [
        'K&M Navy Gold' => [],
        'Ocean' => ['primary' => '#0b3c5d', 'accent' => '#1d7fb0', 'highlight' => '#5bc0eb', 'info' => '#328cc1', 'danger' => '#d9534f', 'neutral' => '#7f8c9a', 'bg1' => '#0a2540', 'bg2' => '#12385c'],
        'Teal' => ['primary' => '#0f5c5c', 'accent' => '#9f6808', 'highlight' => '#f2c14e', 'info' => '#257e8a', 'danger' => '#c0392b', 'neutral' => '#6f8a8a', 'bg1' => '#0a2b2b', 'bg2' => '#134747'],
        'Forest' => ['primary' => '#1e4d2b', 'accent' => '#3a853d', 'highlight' => '#a8d672', 'info' => '#2c7fa2', 'danger' => '#c0392b', 'neutral' => '#849486', 'bg1' => '#14281b', 'bg2' => '#1f3d2a'],
        'Midnight' => ['primary' => '#1b2a5a', 'accent' => '#0b7f72', 'highlight' => '#8fa8ff', 'info' => '#4a63c9', 'danger' => '#cc4949', 'neutral' => '#7d86a3', 'bg1' => '#0b1230', 'bg2' => '#18255a'],
        'Royal' => ['primary' => '#3b2a6b', 'accent' => '#8e44ad', 'highlight' => '#d2b4f5', 'info' => '#5b7bd5', 'danger' => '#e74c3c', 'neutral' => '#9a97ad', 'bg1' => '#1c1633', 'bg2' => '#2c2250'],
        'Crimson' => ['primary' => '#8c1c2e', 'accent' => '#a7610d', 'highlight' => '#f5b971', 'info' => '#b5495b', 'danger' => '#b3261e', 'neutral' => '#8e7f82', 'bg1' => '#2a0f15', 'bg2' => '#4a1823'],
        'Terracotta' => ['primary' => '#a4472b', 'accent' => '#2d7f8a', 'highlight' => '#f3c58e', 'info' => '#af5d36', 'danger' => '#9e2a2b', 'neutral' => '#8e7f74', 'bg1' => '#2e150d', 'bg2' => '#52281a'],
        'Coffee' => ['primary' => '#5a3d2b', 'accent' => '#2f7d77', 'highlight' => '#e0b88a', 'info' => '#8a6246', 'danger' => '#b03a2e', 'neutral' => '#8e7f6f', 'bg1' => '#201510', 'bg2' => '#3f2a1e'],
        'Graphite' => ['primary' => '#2b2d33', 'accent' => '#b16317', 'highlight' => '#f0a35e', 'info' => '#4a6fa5', 'danger' => '#cc3b3b', 'neutral' => '#7b7f88', 'bg1' => '#14161a', 'bg2' => '#23262d'],
        'Slate' => ['primary' => '#2c3e50', 'accent' => '#17806f', 'highlight' => '#1abc9c', 'info' => '#3b78b8', 'danger' => '#c9403a', 'neutral' => '#7a8b8d', 'bg1' => '#1b2838', 'bg2' => '#243447'],
        'Purple (RBJ)' => ['primary' => '#3a1a5b', 'accent' => '#16a085', 'highlight' => '#1abc9c', 'info' => '#3498db', 'danger' => '#e74c3c', 'neutral' => '#95a5a6', 'bg1' => '#1b2838', 'bg2' => '#243447'],
    ];
}

function theme_layouts()
{
    return [
        'classic' => ['Classic', 'Tabs across the top of the page.'],
        'modern' => ['Modern', 'Rounded pill tabs in a soft bar, with a roomier page.'],
        'side' => ['Sidebar', 'The menu as a list on the left, with your name on top. On phones the menu opens from ☰.'],
        'panel' => ['Modern sidebar', 'Menu with icons on the left, in a soft panel joined to the page. ☰ collapses it to icons only.'],
        'dark' => ['Dark sidebar', 'Menu with icons on the left in your Header & primary colour. ☰ collapses it to icons only.'],
        'float' => ['Floating sidebar', 'Menu and page as two separate rounded cards side by side. ☰ collapses the menu.'],
        'rail' => ['Icon rail', 'A slim icon-only menu that leaves the most room for the page; ☰ opens it to show the names.'],
    ];
}

function theme_widths()
{
    return ['1180' => 'Normal', '1400' => 'Wide', '1680' => 'Extra wide', 'full' => 'Full screen width'];
}

function theme_cfg()
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }
    $s = json_decode((string) setting('admin_theme', '{}'), true);
    $t = theme_clean(is_array($s) ? $s : []);
    return $t;
}

function theme_clean(array $s)
{
    $out = [];
    foreach (theme_defaults() as $k => $def) {
        $v = isset($s[$k]) ? strtolower((string) $s[$k]) : '';
        $out[$k] = preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : $def;
    }
    $out['layout'] = isset($s['layout']) && isset(theme_layouts()[$s['layout']]) ? $s['layout'] : 'panel';
    $out['width'] = isset($s['width']) && isset(theme_widths()[(string) $s['width']]) ? (string) $s['width'] : '1180';
    $out['mode'] = isset($s['mode']) && in_array($s['mode'], ['auto', 'light', 'dark'], true) ? $s['mode'] : 'auto';
    $out['site'] = !isset($s['site']) || !empty($s['site']); // use the colours on the public website too
    return $out;
}

/** Black or white text for a background colour. */
function theme_on($hex)
{
    $n = hexdec(ltrim($hex, '#'));
    $l = (0.299 * (($n >> 16) & 255) + 0.587 * (($n >> 8) & 255) + 0.114 * ($n & 255)) / 255;
    return $l > 0.6 ? '#14202b' : '#ffffff';
}

/** <style> with the theme colours, plus the script that applies light / dark before the page paints. */
function theme_head()
{
    $t = theme_cfg();
    $css = ':root{--primary:' . $t['primary'] . ';--onp:' . theme_on($t['primary']) . ';--accent:' . $t['accent'] . ';--ona:' . theme_on($t['accent'])
        . ';--hl:' . $t['highlight'] . ';--onhl:' . theme_on($t['highlight']) . ';--info:' . $t['info'] . ';--danger:' . $t['danger']
        . ';--neutral:' . $t['neutral'] . ';--bg1:' . $t['bg1'] . ';--bg2:' . $t['bg2']
        . ';--mainw:' . ($t['width'] === 'full' ? '100%' : $t['width'] . 'px') . '}';
    return '<style id="themeVars">' . $css . '</style>'
        . '<script>(function(){var m=' . json_encode($t['mode']) . ';try{var o=localStorage.getItem("kmup_mode");if(o)m=o}catch(e){}'
        . 'var d=m==="dark"||(m==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);'
        . 'document.documentElement.setAttribute("data-bs-theme",d?"dark":"light")})();</script>';
}

/**
 * Public website colours: the theme's primary / accent / highlight / background replace the
 * K&M navy, teal and gold in assets/css/style.css. Empty when "Use on the website" is off.
 */
function theme_site_css()
{
    $t = theme_cfg();
    if (!$t['site']) {
        return '';
    }
    return '<style id="siteTheme">:root{--navy:' . $t['primary'] . ';--navy-2:color-mix(in srgb,' . $t['primary'] . ' 82%,#fff);--navy-deep:' . $t['bg1']
        . ';--teal:' . $t['accent'] . ';--teal-2:color-mix(in srgb,' . $t['accent'] . ' 82%,#fff);--gold:' . $t['highlight']
        . ';--gold-2:color-mix(in srgb,' . $t['highlight'] . ' 82%,#000);--on-gold:' . theme_on($t['highlight'])
        . ';--on-navy:' . theme_on($t['primary']) . '}</style>';
}
