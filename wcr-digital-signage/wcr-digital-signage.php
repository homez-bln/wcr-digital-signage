<?php
/**
 * Plugin Name: WCR Digital Signage
 * Description: Digital Signage System für Wake & Camp Ruhlsdorf
 * Version:     1.1.0
 * Author:      WCR
 */

if (!defined('ABSPATH')) exit;

define( 'WCR_DS_VERSION', '1.1.0' );
define( 'WCR_DS_URL',     plugin_dir_url( __FILE__ ) );
define( 'WCR_DS_PATH',    plugin_dir_path( __FILE__ ) );

require_once WCR_DS_PATH . 'includes/db.php';
require_once WCR_DS_PATH . 'includes/instagram.php';
require_once WCR_DS_PATH . 'includes/enqueue.php';
require_once WCR_DS_PATH . 'includes/rest-api.php';
require_once WCR_DS_PATH . 'includes/shortcodes.php';
require_once WCR_DS_PATH . 'includes/screenshot.php'; // Self-Screenshot für Öffnungszeiten

function wcr_ds_defaults() {
    return array(
        'clr_green'    => '#679467',
        'clr_blue'     => '#019ee3',
        'clr_white'    => '#ffffff',
        'clr_text'     => '#eeeeee',
        'clr_muted'    => '#7a8a8a',
        'clr_bg'       => '#080808',
        'clr_bg_dark'  => '#0d0d0d',
        'clr_bg_glass' => 'rgba(10,14,24,0.65)',
        'font_family'  => 'Segoe UI',
        'viewport_w'   => '1920',
        'viewport_h'   => '1080',
    );
}

function wcr_ds_get( $key ) {
    $raw  = get_option( 'wcr_ds_options', array() );
    $opts = is_array( $raw ) ? $raw : array();
    $defs = wcr_ds_defaults();
    if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) return $opts[ $key ];
    return isset( $defs[ $key ] ) ? $defs[ $key ] : '';
}

add_action( 'wp_head', 'wcr_ds_dynamic_css', 99 );
function wcr_ds_dynamic_css() {
    $g   = wcr_ds_get( 'clr_green' );
    $b   = wcr_ds_get( 'clr_blue' );
    $w   = wcr_ds_get( 'clr_white' );
    $t   = wcr_ds_get( 'clr_text' );
    $m   = wcr_ds_get( 'clr_muted' );
    $bg  = wcr_ds_get( 'clr_bg' );
    $bgd = wcr_ds_get( 'clr_bg_dark' );
    $bgg = wcr_ds_get( 'clr_bg_glass' );
    $ff  = wcr_ds_get( 'font_family' );
    $vw  = wcr_ds_get( 'viewport_w' );
    $vh  = wcr_ds_get( 'viewport_h' );

    $theme = get_option( 'wcr_ds_theme', 'glass' );
    if ( ! in_array( $theme, array( 'glass', 'flat', 'aurora' ), true ) ) $theme = 'glass';

    $sys = array( 'Segoe UI', 'Arial', 'Helvetica', 'Georgia', 'Verdana', 'Tahoma' );
    if ( ! in_array( $ff, $sys, true ) ) {
        echo '<link rel="stylesheet" href="' . esc_url( 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $ff ) . ':wght@400;600;700;800;900&display=swap' ) . ">\n";
    }

    $green_rgb = wcr_ds_hex_to_rgb( $g );
    $blue_rgb  = wcr_ds_hex_to_rgb( $b );

    echo '<style id="wcr-ds-css">' . "\n";
    echo ':root {' . "\n";
    echo '  --clr-green:         ' . esc_attr($g)   . ";\n";
    echo '  --clr-green-dim:     rgba(' . $green_rgb . ',0.70)' . ";\n";
    echo '  --clr-green-glow:    rgba(' . $green_rgb . ',0.08)' . ";\n";
    echo '  --clr-blue:          ' . esc_attr($b)   . ";\n";
    echo '  --clr-blue-dim:      rgba(' . $blue_rgb  . ',0.18)' . ";\n";
    echo '  --clr-white:         ' . esc_attr($w)   . ";\n";
    echo '  --clr-text:          ' . esc_attr($t)   . ";\n";
    echo '  --clr-text-muted:    ' . esc_attr($m)   . ";\n";
    echo '  --clr-bg:            ' . esc_attr($bg)  . ";\n";
    echo '  --clr-bg-dark:       ' . esc_attr($bgd) . ";\n";
    echo '  --clr-border:        rgba(255,255,255,0.09)' . ";\n";
    echo '  --font-main:         \'' . esc_attr($ff) . '\', system-ui, sans-serif' . ";\n";
    echo '  --viewport-w:        ' . esc_attr($vw)  . "px;\n";
    echo '  --viewport-h:        ' . esc_attr($vh)  . "px;\n";
    echo '}' . "\n";
    echo 'html,body{width:var(--viewport-w);height:var(--viewport-h);font-family:var(--font-main);background:var(--clr-bg);color:var(--clr-text);}' . "\n";
    echo '.ds-dot      {background:var(--clr-green);box-shadow:0 0 10px var(--clr-green);}' . "\n";
    echo '.ds-live-dot {background:var(--clr-blue);box-shadow:0 0 10px var(--clr-blue);}' . "\n";
    if ( $theme === 'glass' ) {
        echo '.glass{background:' . esc_attr($bgg) . ';backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);border-radius:18px;}' . "\n";
        echo '.k-card,.ds-card{backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);}' . "\n";
    } elseif ( $theme === 'flat' ) {
        echo '.glass,.k-card,.ds-card{background:' . esc_attr($bgd) . ';backdrop-filter:none;}' . "\n";
        echo 'body::before{display:none;}' . "\n";
    } elseif ( $theme === 'aurora' ) {
        echo '.glass,.k-card,.ds-card{background:rgba(' . $blue_rgb . ',0.06);backdrop-filter:none;border:1px solid rgba(' . $blue_rgb . ',0.18);}' . "\n";
    }
    echo '</style>' . "\n";
}

if ( ! function_exists( 'wcr_ds_hex_to_rgb' ) ) {
    function wcr_ds_hex_to_rgb( string $hex ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( strlen($hex) !== 6 ) return '128,128,128';
        return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
    }
}

add_action( 'wp_enqueue_scripts', 'wcr_ds_enqueue' );
function wcr_ds_enqueue() {
    wp_enqueue_style(  'wcr-ds-global', WCR_DS_URL . 'assets/css/wcr-ds-global.css', array(), WCR_DS_VERSION );
    wp_enqueue_script( 'wcr-ds-utils',  WCR_DS_URL . 'assets/js/wcr-ds-utils.js',   array(), WCR_DS_VERSION, true );
}
function wcr_ds_load_gsap() {
    if ( ! wp_script_is( 'gsap', 'enqueued' ) )
        wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', array(), '3.12.5', true );
}
function wcr_ds_load_leaflet() {
    if ( ! wp_style_is( 'leaflet-css', 'enqueued' ) ) {
        wp_enqueue_style(  'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
        wp_enqueue_script( 'leaflet-js',  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',  array(), '1.9.4', true );
    }
}

add_shortcode( 'wcr_getraenke',    'wcr_sc_getraenke' );
add_shortcode( 'wcr_softdrinks',   'wcr_sc_softdrinks' );
add_shortcode( 'wcr_essen',        'wcr_sc_essen' );
add_shortcode( 'wcr_kaffee',       'wcr_sc_kaffee' );
add_shortcode( 'wcr_windmap',      'wcr_sc_windmap' );
add_shortcode( 'wcr_wetter',       'wcr_sc_wetter' );
add_shortcode( 'wcr_starter_pack', 'wcr_sc_starter_pack' );

function wcr_sc_getraenke( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){WCR.renderDrinksList("drinks-display",[{label:"Bier",types:["bier","weissbier","wein"]},{label:"Mix",types:["bier-mix","brlo","weinmix"]},{label:"Drinks",types:["longdrink","shots"]}],"/wp-json/wakecamp/v1/drinks");});</script>' . "\n";
    return $out;
}
function wcr_sc_softdrinks( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){WCR.renderDrinksList("drinks-display",[{label:"Softdrinks",types:["fritz-kola","fritz-limo","fritz-spritz","energy"]},{label:"Cold",types:["homemade","wasser"]},{label:"Hot",types:["tee","chocolate"]}],"/wp-json/wakecamp/v1/drinks");});</script>' . "\n";
    return $out;
}
function wcr_sc_essen( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){WCR.renderDrinksList("drinks-display",[{label:"Bar",types:["bar"]},{label:"Grill",types:["grill"]},{label:"Kueche",types:["kueche"]}],"/wp-json/wakecamp/v1/food","/wp-json/wakecamp/v1/food/gruppen");});</script>' . "\n";
    return $out;
}
function wcr_sc_kaffee( $atts ) {
    wp_enqueue_script( 'wcr-kaffee', WCR_DS_URL . 'assets/js/wcr-kaffee.js', array(), WCR_DS_VERSION, true );
    return '<div class="ds-header"><div class="ds-header-line"></div><div class="ds-header-inner"><div class="ds-dot"></div>Kaffeekarte<div class="ds-dot"></div></div><div class="ds-header-line right"></div></div>' . "\n" . '<div class="kaffee-grid" id="kaffee-grid"></div>' . "\n";
}
function wcr_sc_windmap( $atts ) {
    wcr_ds_load_leaflet();
    wp_enqueue_script( 'wcr-windmap', WCR_DS_URL . 'assets/js/wcr-windmap.js', array('leaflet-js'), WCR_DS_VERSION, true );
    $out  = '<div id="wcr-windmap-wrap">';
    $out .= '<div id="map"></div><canvas id="wind-canvas"></canvas><canvas id="gust-canvas"></canvas>';
    $out .= '<div class="glass" id="spot-header"><div><div class="ds-live-dot"></div></div><div><div class="spot-name">📍 Wake &amp; Camp Ruhlsdorf</div><div class="spot-coords">52.8213° N · 13.5754° E</div></div></div>';
    $out .= '<div class="glass" id="time-card"><div id="tc-forecast">–</div><div id="tc-realtime">–</div><div id="tc-badge">Jetzt</div></div>';
    $out .= '<div id="right-panel"><div id="kn-box"><div class="kn-icon">💨</div><div class="kn-val" id="kn-speed">–</div><div class="kn-unit">Knoten</div><div class="kn-label">Windstärke</div></div><div id="windrose-box"><svg width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/><text x="50" y="12" text-anchor="middle" fill="#7a9abc" font-size="11">N</text><text x="50" y="96" text-anchor="middle" fill="#7a9abc" font-size="11">S</text><text x="92" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">O</text><text x="8" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">W</text><g id="wr-arrow" transform="rotate(0 50 50)"><polygon points="50,14 55,42 50,38 45,42" fill="#00c8ff" opacity="0.9"/><polygon points="50,86 55,58 50,62 45,58" fill="rgba(0,200,255,0.2)"/></g></svg><div id="wr-degs">–°</div><div id="wr-label">Windrichtung</div></div></div>';
    $out .= '<div id="timeline"><div id="tl-current-time"><span id="tl-label-text">–</span><span id="tl-delta"></span></div><div id="tl-track-wrap"><div id="tl-labels"></div><div id="tl-rail"><div id="tl-fill"></div><div id="tl-cursor"></div></div></div></div>';
    $out .= '</div>';
    return $out;
}
function wcr_sc_wetter( $atts ) {
    wp_enqueue_script( 'wcr-wetter', WCR_DS_URL . 'assets/js/wcr-wetter.js', array(), '3.2.0', true );
    $out  = '<div id="wetter-wrap"><header><div><div id="w-location">Ruhlsdorf</div><div style="font-size:1.2rem;color:#64748b;">Aktuelles Wetter</div></div><div><div id="clock-time">00:00</div><div id="clock-date">Montag, 1. Januar</div></div></header><main><div class="current-hero"><div class="hero-icon" id="cur-icon"></div><div><div class="hero-temp" id="cur-temp">--<span class="hero-unit">&deg;</span></div><div class="hero-desc" id="cur-desc">Laden...</div></div></div><div class="current-details"><div class="detail-card glass"><div class="dc-label">Gef&uuml;hlt</div><div class="dc-value" id="cur-feel">--&deg;</div></div><div class="detail-card glass"><div class="dc-label">Wind</div><div class="dc-value" id="cur-wind">-- <span class="dc-sub">km/h</span></div></div><div class="detail-card glass"><div class="dc-label">Regenwahrsch.</div><div class="dc-value" id="cur-rain">-- <span class="dc-sub">%</span></div></div><div class="detail-card glass"><div class="dc-label">Sonnenuntergang</div><div class="dc-value" id="cur-sunset">--:--</div></div></div></main><footer id="forecast-grid"></footer></div>';
    return $out;
}
function wcr_sc_starter_pack( $atts ) {
    wcr_ds_load_gsap();
    wp_enqueue_script( 'wcr-starter-pack', WCR_DS_URL . 'assets/js/wcr-starter-pack.js', array('gsap'), WCR_DS_VERSION, true );
    return '<div id="sp-display"></div>' . "\n";
}
