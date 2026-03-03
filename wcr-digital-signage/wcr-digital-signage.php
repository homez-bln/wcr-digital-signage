<?php
/**
 * Plugin Name: WCR Digital Signage
 * Description: Digital Signage System für Wake & Camp Ruhlsdorf
 * Version:     1.2.0
 * Author:      WCR
 */

if (!defined('ABSPATH')) exit;

define('WCR_DS_VERSION', '1.2.0');
define('WCR_DS_URL',     plugin_dir_url(__FILE__));
define('WCR_DS_PATH',    plugin_dir_path(__FILE__));

require_once WCR_DS_PATH . 'includes/db.php';
require_once WCR_DS_PATH . 'includes/enqueue.php';
require_once WCR_DS_PATH . 'includes/rest-api.php';
require_once WCR_DS_PATH . 'includes/shortcodes.php';
require_once WCR_DS_PATH . 'includes/instagram.php';

/* ====================================================
   DEFAULTS
==================================================== */
function wcr_ds_defaults() {
    return array(
        'clr_green'          => '#679467',
        'clr_blue'           => '#019ee3',
        'clr_white'          => '#ffffff',
        'clr_text'           => '#eeeeee',
        'clr_muted'          => '#7a8a8a',
        'clr_bg'             => '#080808',
        'clr_bg_dark'        => '#0d0d0d',
        'clr_bg_glass'       => 'rgba(10,14,24,0.65)',
        'font_family'        => 'Segoe UI',
        'viewport_w'         => '1920',
        'viewport_h'         => '1080',
        'viewport_portrait_w'=> '1080',
        'viewport_portrait_h'=> '1920',
    );
}

/* ====================================================
   OPTION LESEN
==================================================== */
function wcr_ds_get( $key ) {
    $raw  = get_option( 'wcr_ds_options', array() );
    $opts = is_array( $raw ) ? $raw : array();
    $defs = wcr_ds_defaults();
    if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) return $opts[ $key ];
    return isset( $defs[ $key ] ) ? $defs[ $key ] : '';
}

/* ====================================================
   SANITIZE
==================================================== */
function wcr_ds_sanitize( $input ) {
    if ( ! is_array( $input ) ) return array();
    $out = array();
    foreach ( $input as $k => $v ) $out[ sanitize_key( $k ) ] = sanitize_text_field( $v );
    return $out;
}

/* ====================================================
   SETTINGS REGISTRIEREN
==================================================== */
add_action( 'admin_init', 'wcr_ds_register_settings' );
function wcr_ds_register_settings() {
    register_setting( 'wcr_ds_group', 'wcr_ds_options', 'wcr_ds_sanitize' );
}

/* ====================================================
   ADMIN MENU
==================================================== */
add_action( 'admin_menu', 'wcr_ds_add_menu' );
function wcr_ds_add_menu() {
    add_menu_page( 'WCR Digital Signage', 'WCR Signage', 'manage_options', 'wcr-ds-settings', 'wcr_ds_render_page', 'dashicons-desktop', 59 );
    add_submenu_page( 'wcr-ds-settings', 'WCR Einstellungen', 'Einstellungen', 'manage_options', 'wcr-ds-settings', 'wcr_ds_render_page' );
}

/* ====================================================
   RESET HANDLER
==================================================== */
add_action( 'admin_init', 'wcr_ds_reset_handler' );
function wcr_ds_reset_handler() {
    if ( ! isset( $_POST['wcr_ds_reset'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'wcr_ds_reset_action', 'wcr_ds_reset_nonce' );
    delete_option( 'wcr_ds_options' );
    wp_safe_redirect( admin_url( 'admin.php?page=wcr-ds-settings&wcr_reset=1' ) );
    exit;
}

/* ====================================================
   INSTAGRAM CACHE FLUSH HANDLER
==================================================== */
add_action( 'admin_init', 'wcr_ds_ig_flush_handler' );
function wcr_ds_ig_flush_handler() {
    if ( isset( $_GET['wcr_flush_ig'] ) && current_user_can( 'manage_options' ) ) {
        delete_transient( WCR_Instagram::CACHE_KEY );
        wp_safe_redirect( admin_url( 'admin.php?page=wcr-ds-settings&wcr_ig_flushed=1' ) );
        exit;
    }
}

/* ====================================================
   INSTAGRAM SETTINGS SPEICHERN
==================================================== */
add_action( 'admin_init', 'wcr_ds_ig_save_handler' );
function wcr_ds_ig_save_handler() {
    if ( ! isset( $_POST['wcr_ig_save'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'wcr_ig_save_action', 'wcr_ig_nonce' );

    $fields = [
        'wcr_instagram_token'          => 'sanitize_text_field',
        'wcr_instagram_user_id'        => 'sanitize_text_field',
        'wcr_instagram_hashtags'       => 'sanitize_textarea_field',
        'wcr_instagram_excluded'       => 'sanitize_textarea_field',
        'wcr_instagram_location_label' => 'sanitize_text_field',
        'wcr_instagram_cta_text'       => 'sanitize_text_field',
        'wcr_instagram_qr_url'         => 'esc_url_raw',
        'wcr_instagram_max_age_value'  => 'intval',
        'wcr_instagram_max_age_unit'   => 'sanitize_text_field',
        'wcr_instagram_max_posts'      => 'intval',
        'wcr_instagram_refresh'        => 'intval',
        'wcr_instagram_new_hours'      => 'intval',
        'wcr_instagram_video_pool'     => 'intval',
        'wcr_instagram_video_count'    => 'intval',
        'wcr_instagram_min_likes'      => 'intval',
    ];
    foreach ( $fields as $key => $fn ) {
        if ( isset( $_POST[ $key ] ) ) update_option( $key, $fn( $_POST[ $key ] ) );
    }
    $toggles = ['wcr_instagram_use_tagged','wcr_instagram_use_hashtag','wcr_instagram_show_user',
                 'wcr_instagram_cta_active','wcr_instagram_qr_active','wcr_instagram_weekly_best'];
    foreach ( $toggles as $t ) update_option( $t, isset( $_POST[ $t ] ) ? 1 : 0 );

    delete_transient( WCR_Instagram::CACHE_KEY );
    wp_safe_redirect( admin_url( 'admin.php?page=wcr-ds-settings&wcr_ig_saved=1#instagram' ) );
    exit;
}

/* ====================================================
   DYNAMISCHES CSS
==================================================== */
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
        echo '<link rel="stylesheet" href="' . esc_url( 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $ff ) . ':wght@400;600;700;800;900&display=swap' ) . '">' . "\n";
    }

    $green_rgb = wcr_ds_hex_to_rgb( $g );
    $blue_rgb  = wcr_ds_hex_to_rgb( $b );

    echo '<style id="wcr-ds-css">' . "\n";
    echo ':root {' . "\n";
    echo '  --clr-green:         ' . esc_attr( $g )   . ";\n";
    echo '  --clr-green-dim:     rgba(' . $green_rgb . ',0.70)' . ";\n";
    echo '  --clr-green-glow:    rgba(' . $green_rgb . ',0.08)' . ";\n";
    echo '  --clr-blue:          ' . esc_attr( $b )   . ";\n";
    echo '  --clr-blue-dim:      rgba(' . $blue_rgb  . ',0.18)' . ";\n";
    echo '  --clr-white:         ' . esc_attr( $w )   . ";\n";
    echo '  --clr-text:          ' . esc_attr( $t )   . ";\n";
    echo '  --clr-text-muted:    ' . esc_attr( $m )   . ";\n";
    echo '  --clr-bg:            ' . esc_attr( $bg )  . ";\n";
    echo '  --clr-bg-dark:       ' . esc_attr( $bgd ) . ";\n";
    echo '  --clr-border:        rgba(255,255,255,0.09)' . ";\n";
    echo '  --font-main:         \'' . esc_attr( $ff ) . '\', system-ui, sans-serif' . ";\n";
    echo '  --viewport-w:        ' . esc_attr( $vw )  . "px;\n";
    echo '  --viewport-h:        ' . esc_attr( $vh )  . "px;\n";
    echo '}' . "\n";
    echo 'html,body{width:var(--viewport-w);height:var(--viewport-h);font-family:var(--font-main);background:var(--clr-bg);color:var(--clr-text);}' . "\n";
    echo '.ds-dot{background:var(--clr-green);box-shadow:0 0 10px var(--clr-green);}' . "\n";
    echo '.ds-live-dot{background:var(--clr-blue);box-shadow:0 0 10px var(--clr-blue);}' . "\n";
    if ( $theme === 'glass' ) {
        echo '.glass{background:' . esc_attr( $bgg ) . ';backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);border-radius:18px;}' . "\n";
        echo '.k-card,.ds-card{backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);background:rgba(255,255,255,0.05);}' . "\n";
    } elseif ( $theme === 'flat' ) {
        echo '.glass,.k-card,.ds-card{background:' . esc_attr( $bgd ) . ';backdrop-filter:none;-webkit-backdrop-filter:none;}' . "\n";
        echo 'body::before{display:none;}' . "\n";
    } elseif ( $theme === 'aurora' ) {
        echo '.glass,.k-card,.ds-card{background:rgba(' . $blue_rgb . ',0.06);backdrop-filter:none;-webkit-backdrop-filter:none;border:1px solid rgba(' . $blue_rgb . ',0.18);}' . "\n";
        echo '.ds-dot{animation:wcr-aurora-pulse 2.5s ease-in-out infinite;}' . "\n";
        echo '@keyframes wcr-aurora-pulse{0%,100%{background:' . esc_attr( $g ) . ';box-shadow:0 0 10px ' . esc_attr( $g ) . ';}50%{background:' . esc_attr( $b ) . ';box-shadow:0 0 16px ' . esc_attr( $b ) . ';}}' . "\n";
    }
    echo '</style>' . "\n";
}

if ( ! function_exists( 'wcr_ds_hex_to_rgb' ) ) {
    function wcr_ds_hex_to_rgb( string $hex ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( strlen( $hex ) !== 6 ) return '128,128,128';
        return hexdec( substr($hex,0,2) ) . ',' . hexdec( substr($hex,2,2) ) . ',' . hexdec( substr($hex,4,2) );
    }
}

/* ====================================================
   ASSETS
==================================================== */
add_action( 'wp_enqueue_scripts', 'wcr_ds_enqueue' );
function wcr_ds_enqueue() {
    wp_enqueue_style(  'wcr-ds-global', plugin_dir_url( __FILE__ ) . 'assets/css/wcr-ds-global.css', array(), WCR_DS_VERSION );
    wp_enqueue_script( 'wcr-ds-utils',  plugin_dir_url( __FILE__ ) . 'assets/js/wcr-ds-utils.js',   array(), WCR_DS_VERSION, true );
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

/* ====================================================
   SHORTCODES
==================================================== */
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
    wp_enqueue_script( 'wcr-kaffee', plugin_dir_url( __FILE__ ) . 'assets/js/wcr-kaffee.js', array(), WCR_DS_VERSION, true );
    $out  = '<div class="ds-header"><div class="ds-header-line"></div><div class="ds-header-inner"><div class="ds-dot"></div>Kaffeekarte<div class="ds-dot"></div></div><div class="ds-header-line right"></div></div>' . "\n";
    $out .= '<div class="kaffee-grid" id="kaffee-grid"></div>' . "\n";
    return $out;
}
function wcr_sc_windmap( $atts ) {
    wcr_ds_load_leaflet();
    wp_enqueue_script( 'wcr-windmap', plugin_dir_url( __FILE__ ) . 'assets/js/wcr-windmap.js', array( 'leaflet-js' ), WCR_DS_VERSION, true );
    $out  = '<div id="wcr-windmap-wrap">' . "\n";
    $out .= '<div id="map"></div><canvas id="wind-canvas"></canvas><canvas id="gust-canvas"></canvas>' . "\n";
    $out .= '<div class="glass" id="spot-header"><div><div class="ds-live-dot"></div></div><div><div class="spot-name">📍 Wake &amp; Camp Ruhlsdorf</div><div class="spot-coords">52.8213° N · 13.5754° E</div></div></div>' . "\n";
    $out .= '<div class="glass" id="time-card"><div id="tc-forecast">–</div><div id="tc-realtime">–</div><div id="tc-badge">Jetzt</div></div>' . "\n";
    $out .= '<div id="right-panel"><div id="kn-box"><div class="kn-icon">💨</div><div class="kn-val" id="kn-speed">–</div><div class="kn-unit">Knoten</div><div class="kn-label">Windstärke</div></div><div id="windrose-box"><svg width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/><text x="50" y="12" text-anchor="middle" fill="#7a9abc" font-size="11">N</text><text x="50" y="96" text-anchor="middle" fill="#7a9abc" font-size="11">S</text><text x="92" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">O</text><text x="8" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">W</text><g id="wr-arrow" transform="rotate(0 50 50)"><polygon points="50,14 55,42 50,38 45,42" fill="#00c8ff" opacity="0.9"/><polygon points="50,86 55,58 50,62 45,58" fill="rgba(0,200,255,0.2)"/></g></svg><div id="wr-degs">–°</div><div id="wr-label">Windrichtung</div></div></div>' . "\n";
    $out .= '<div id="timeline"><div id="tl-current-time"><span id="tl-label-text">–</span><span id="tl-delta"></span></div><div id="tl-track-wrap"><div id="tl-labels"></div><div id="tl-rail"><div id="tl-fill"></div><div id="tl-cursor"></div></div></div></div></div>' . "\n";
    return $out;
}
function wcr_sc_wetter( $atts ) {
    wp_enqueue_script( 'wcr-wetter', plugin_dir_url(__FILE__) . 'assets/js/wcr-wetter.js', array(), WCR_DS_VERSION, true );
    $out  = '<div id="wetter-wrap"><header><div><div id="w-location">Ruhlsdorf</div><div style="font-size:1.2rem;color:#64748b;">Aktuelles Wetter</div></div><div><div id="clock-time">00:00</div><div id="clock-date">Montag, 1. Januar</div></div></header><main><div class="current-hero"><div class="hero-icon" id="cur-icon"></div><div><div class="hero-temp" id="cur-temp">--<span class="hero-unit">&deg;</span></div><div class="hero-desc" id="cur-desc">Laden...</div></div></div><div class="current-details"><div class="detail-card glass"><div class="dc-label">Gefühlt</div><div class="dc-value" id="cur-feel">--&deg;</div></div><div class="detail-card glass"><div class="dc-label">Wind</div><div class="dc-value" id="cur-wind">-- <span class="dc-sub">km/h</span></div></div><div class="detail-card glass"><div class="dc-label">Regenwahrsch.</div><div class="dc-value" id="cur-rain">-- <span class="dc-sub">%</span></div></div><div class="detail-card glass"><div class="dc-label">Sonnenuntergang</div><div class="dc-value" id="cur-sunset">--:--</div></div></div></main><footer id="forecast-grid"></footer></div>';
    return $out;
}
function wcr_sc_starter_pack( $atts ) {
    wcr_ds_load_gsap();
    wp_enqueue_script( 'wcr-starter-pack', plugin_dir_url( __FILE__ ) . 'assets/js/wcr-starter-pack.js', array( 'gsap' ), WCR_DS_VERSION, true );
    return '<div id="sp-display"></div>' . "\n";
}

/* ====================================================
   ADMIN SEITE RENDERN
==================================================== */
function wcr_ds_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $shortcodes_meta = [
        '/getraenke/'       => [ '[wcr_getraenke]',       '1920×1080', 'Landscape' ],
        '/softdrinks/'      => [ '[wcr_softdrinks]',      '1920×1080', 'Landscape' ],
        '/essen/'           => [ '[wcr_essen]',           '1920×1080', 'Landscape' ],
        '/kaffee/'          => [ '[wcr_kaffee]',          '1920×1080', 'Landscape' ],
        '/windmap/'         => [ '[wcr_windmap]',         '1920×1080', 'Landscape' ],
        '/wetter/'          => [ '[wcr_wetter]',          '1920×1080', 'Landscape' ],
        '/starter-pack/'    => [ '[wcr_starter_pack]',    '1920×1080', 'Landscape' ],
        '/instagram/'       => [ '[wcr_instagram]',       '1080×1920', '↑ Portrait' ],
        '/instagram-video/' => [ '[wcr_instagram_video]', '1080×1920', '↑ Portrait' ],
    ];

    // Token-Live-Check
    $token   = get_option('wcr_instagram_token', '');
    $user_id = get_option('wcr_instagram_user_id', '');
    $token_status = '⚪ Kein Token hinterlegt';
    if ( $token && $user_id ) {
        $check = wp_remote_get( "https://graph.instagram.com/me?fields=id,username&access_token={$token}", ['timeout'=>8] );
        if ( ! is_wp_error( $check ) ) {
            $data = json_decode( wp_remote_retrieve_body( $check ), true );
            $token_status = ! empty( $data['id'] ) ? '✅ Verbunden als @' . ($data['username'] ?? $data['id']) : '❌ Token ungültig';
        } else {
            $token_status = '⚠️ Verbindung fehlgeschlagen';
        }
    }

    $cache_info = get_transient( WCR_Instagram::CACHE_KEY );
    $cache_count = is_array( $cache_info ) ? count( $cache_info ) : 0;
    $flush_url   = admin_url( 'admin.php?page=wcr-ds-settings&wcr_flush_ig=1' );

    ?>
    <div class="wrap" style="max-width:900px;">
        <h1 style="font-size:22px;margin-bottom:24px;">⚡ WCR Digital Signage</h1>

        <?php if ( isset( $_GET['wcr_reset'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Einstellungen zurückgesetzt.</p></div>'; ?>
        <?php if ( isset( $_GET['wcr_ig_saved'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Instagram-Einstellungen gespeichert & Cache geleert.</p></div>'; ?>
        <?php if ( isset( $_GET['wcr_ig_flushed'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Instagram-Cache geleert.</p></div>'; ?>

        <?php /* ── DS-Seiten Übersicht ── */ ?>
        <div style="background:#1e1e1e;border-radius:12px;padding:24px 28px;margin-bottom:24px;">
            <h2 style="color:#fff;font-size:16px;margin:0 0 16px;">📺 DS-Seiten</h2>
            <table class="widefat" style="border-collapse:collapse;background:transparent;">
                <thead><tr style="border-bottom:1px solid #333;">
                    <th style="color:#7a9abc;font-weight:600;padding:8px 12px;">Seite</th>
                    <th style="color:#7a9abc;font-weight:600;padding:8px 12px;">Shortcode</th>
                    <th style="color:#7a9abc;font-weight:600;padding:8px 12px;">Viewport</th>
                    <th style="color:#7a9abc;font-weight:600;padding:8px 12px;">Orientierung</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $shortcodes_meta as $page => $meta ): ?>
                <tr style="border-bottom:1px solid #2a2a2a;">
                    <td style="padding:8px 12px;color:#ccc;"><?= esc_html($page) ?></td>
                    <td style="padding:8px 12px;"><code style="background:#111;border-radius:4px;padding:2px 8px;color:#16a86f;"><?= esc_html($meta[0]) ?></code></td>
                    <td style="padding:8px 12px;color:#aaa;"><?= esc_html($meta[1]) ?></td>
                    <td style="padding:8px 12px;color:<?= $meta[2] === 'Landscape' ? '#7a9abc' : '#fcb045' ?>;"><?= esc_html($meta[2]) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php /* ── Instagram Settings ── */ ?>
        <div id="instagram" style="background:#1e1e1e;border-radius:12px;padding:24px 28px;margin-bottom:24px;">
            <h2 style="color:#fff;font-size:16px;margin:0 0 20px;">📸 Instagram Feed</h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'wcr_ig_save_action', 'wcr_ig_nonce' ); ?>

                <?php
                $row_style  = 'display:flex;align-items:flex-start;gap:20px;margin-bottom:16px;';
                $label_style = 'color:#7a9abc;font-size:13px;min-width:200px;padding-top:6px;';
                $input_style = 'background:#111;border:1px solid #333;border-radius:6px;color:#eee;padding:6px 10px;width:100%;box-sizing:border-box;';
                $ta_style    = $input_style . 'height:80px;resize:vertical;font-family:monospace;font-size:12px;';

                function wcr_ig_row($label, $content, $rs, $ls) {
                    echo '<div style="' . $rs . '"><label style="' . $ls . '">' . $label . '</label><div style="flex:1;">' . $content . '</div></div>';
                }

                $token_val = esc_attr(get_option('wcr_instagram_token',''));
                wcr_ig_row('Access Token', "<input type='password' name='wcr_instagram_token' value='{$token_val}' style='{$input_style}' placeholder='Einfügen...'>", $row_style, $label_style);

                $uid_val = esc_attr(get_option('wcr_instagram_user_id',''));
                wcr_ig_row('Instagram User ID', "<input type='text' name='wcr_instagram_user_id' value='{$uid_val}' style='{$input_style}' placeholder='z.B. 17841400000000'>", $row_style, $label_style);

                // Toggles
                $use_tagged  = get_option('wcr_instagram_use_tagged', 1);
                $use_hashtag = get_option('wcr_instagram_use_hashtag', 1);
                $show_user   = get_option('wcr_instagram_show_user', 1);
                $cta_active  = get_option('wcr_instagram_cta_active', 1);
                $qr_active   = get_option('wcr_instagram_qr_active', 0);
                $weekly      = get_option('wcr_instagram_weekly_best', 0);

                wcr_ig_row('Quellen', "
                    <label style='margin-right:20px;color:#ccc;'><input type='checkbox' name='wcr_instagram_use_tagged' " . checked(1,$use_tagged,false) . "> Tagged (@mention)</label>
                    <label style='color:#ccc;'><input type='checkbox' name='wcr_instagram_use_hashtag' " . checked(1,$use_hashtag,false) . "> Hashtag-Feed</label>
                ", $row_style, $label_style);

                $hashtags_val = esc_textarea(get_option('wcr_instagram_hashtags','wakecampruhlsdorf'));
                wcr_ig_row('Hashtags (je Zeile)', "<textarea name='wcr_instagram_hashtags' style='{$ta_style}'>{$hashtags_val}</textarea><small style='color:#555;'>Ohne #, ein Hashtag pro Zeile</small>", $row_style, $label_style);

                $excluded_val = esc_textarea(get_option('wcr_instagram_excluded',''));
                wcr_ig_row('Ausgeschlossene Accounts', "<textarea name='wcr_instagram_excluded' style='{$ta_style}'>{$excluded_val}</textarea><small style='color:#555;'>Ein Username pro Zeile, ohne @</small>", $row_style, $label_style);

                // Altersfilter
                $age_val  = (int)get_option('wcr_instagram_max_age_value', 30);
                $age_unit = get_option('wcr_instagram_max_age_unit', 'days');
                $age_opts = '';
                foreach (['days'=>'Tage','weeks'=>'Wochen','months'=>'Monate'] as $v=>$l)
                    $age_opts .= "<option value='{$v}'" . selected($age_unit,$v,false) . ">{$l}</option>";
                wcr_ig_row('Max. Alter', "<div style='display:flex;gap:10px;'><input type='number' name='wcr_instagram_max_age_value' value='{$age_val}' min='0' style='{$input_style}max-width:80px;'><select name='wcr_instagram_max_age_unit' style='{$input_style}max-width:120px;'>{$age_opts}</select></div>", $row_style, $label_style);

                // Mindest-Likes
                $min_likes = (int)get_option('wcr_instagram_min_likes', 0);
                wcr_ig_row('Mindest-Likes', "<input type='number' name='wcr_instagram_min_likes' value='{$min_likes}' min='0' style='{$input_style}max-width:80px;'><small style='color:#555;display:block;margin-top:4px;'>0 = alle Posts anzeigen</small>", $row_style, $label_style);

                // Max Posts & Refresh
                $max_posts = (int)get_option('wcr_instagram_max_posts', 8);
                $refresh   = (int)get_option('wcr_instagram_refresh', 10);
                $new_hours = (int)get_option('wcr_instagram_new_hours', 2);

                $posts_opts = '';
                foreach ([4,6,8] as $v) $posts_opts .= "<option value='{$v}'" . selected($max_posts,$v,false) . ">{$v} Posts</option>";
                wcr_ig_row('Max. Posts im Grid', "<select name='wcr_instagram_max_posts' style='{$input_style}max-width:140px;'>{$posts_opts}</select>", $row_style, $label_style);

                $refresh_opts = '';
                foreach ([5,10,15,30] as $v) $refresh_opts .= "<option value='{$v}'" . selected($refresh,$v,false) . ">{$v} Min</option>";
                wcr_ig_row('Auto-Refresh', "<select name='wcr_instagram_refresh' style='{$input_style}max-width:140px;'>{$refresh_opts}</select>", $row_style, $label_style);

                wcr_ig_row('NEU-Badge (Stunden)', "<input type='number' name='wcr_instagram_new_hours' value='{$new_hours}' min='1' max='72' style='{$input_style}max-width:80px;'>", $row_style, $label_style);

                wcr_ig_row('Username-Overlay', "<label style='color:#ccc;'><input type='checkbox' name='wcr_instagram_show_user' " . checked(1,$show_user,false) . "> @Username + Zeit anzeigen</label>", $row_style, $label_style);

                // Video
                $vpool  = (int)get_option('wcr_instagram_video_pool',  10);
                $vcount = (int)get_option('wcr_instagram_video_count', 3);
                $pool_opts = '';
                foreach ([5,10,15,20] as $v) $pool_opts .= "<option value='{$v}'" . selected($vpool,$v,false) . ">{$v} Videos</option>";
                wcr_ig_row('Video-Pool', "<select name='wcr_instagram_video_pool' style='{$input_style}max-width:140px;'>{$pool_opts}</select><small style='color:#555;display:block;margin-top:4px;'>Aus den X neuesten Videos wird zufällig gewählt</small>", $row_style, $label_style);

                $count_opts = '';
                for ($v=1;$v<=6;$v++) $count_opts .= "<option value='{$v}'" . selected($vcount,$v,false) . ">{$v} Clips</option>";
                wcr_ig_row('Clips pro Session', "<select name='wcr_instagram_video_count' style='{$input_style}max-width:140px;'>{$count_opts}</select>", $row_style, $label_style);

                // CTA
                $cta_text = esc_attr(get_option('wcr_instagram_cta_text','Markiere uns auf Instagram und erscheine hier! 📸'));
                wcr_ig_row('CTA-Text', "<input type='text' name='wcr_instagram_cta_text' value='{$cta_text}' style='{$input_style}'>", $row_style, $label_style);
                wcr_ig_row('CTA anzeigen', "<label style='color:#ccc;'><input type='checkbox' name='wcr_instagram_cta_active' " . checked(1,$cta_active,false) . "> CTA-Leiste einblenden</label>", $row_style, $label_style);

                // QR
                $qr_url = esc_attr(get_option('wcr_instagram_qr_url',''));
                wcr_ig_row('QR-Code Ziel-URL', "<input type='url' name='wcr_instagram_qr_url' value='{$qr_url}' style='{$input_style}' placeholder='https://instagram.com/...'>", $row_style, $label_style);
                wcr_ig_row('QR-Code anzeigen', "<label style='color:#ccc;'><input type='checkbox' name='wcr_instagram_qr_active' " . checked(1,$qr_active,false) . "> QR-Code auf Grid-Screen</label>", $row_style, $label_style);

                // Wochenbest
                wcr_ig_row('Post der Woche', "<label style='color:#ccc;'><input type='checkbox' name='wcr_instagram_weekly_best' " . checked(1,$weekly,false) . "> Sonntags automatisch Fullscreen-Highlight</label>", $row_style, $label_style);

                // Standort
                $loc = esc_attr(get_option('wcr_instagram_location_label',''));
                wcr_ig_row('Standort-Label', "<input type='text' name='wcr_instagram_location_label' value='{$loc}' style='{$input_style}' placeholder='z.B. Wake & Camp Ruhlsdorf'>", $row_style, $label_style);
                ?>

                <div style="margin-top:24px;padding-top:20px;border-top:1px solid #2a2a2a;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <button type="submit" name="wcr_ig_save" class="button button-primary">💾 Speichern</button>
                    <a href="<?= esc_url($flush_url) ?>" class="button">🔄 Cache leeren</a>
                    <span style="color:<?= strpos($token_status,'✅') !== false ? '#16a86f' : (strpos($token_status,'❌') !== false ? '#ff3b30' : '#7a9abc') ?>;font-size:13px;">
                        <?= esc_html($token_status) ?>
                    </span>
                    <?php if ($cache_count > 0): ?>
                    <span style="color:#7a9abc;font-size:13px;">📦 <?= $cache_count ?> Posts im Cache</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php /* ── Reset Block ── */ ?>
        <div style="background:#1e1e1e;border-radius:12px;padding:20px 28px;">
            <h2 style="color:#fff;font-size:15px;margin:0 0 12px;">⚠️ DS-Einstellungen zurücksetzen</h2>
            <form method="post">
                <?php wp_nonce_field('wcr_ds_reset_action','wcr_ds_reset_nonce'); ?>
                <button type="submit" name="wcr_ds_reset" class="button button-secondary" onclick="return confirm('Wirklich zurücksetzen?');">Auf Standardwerte zurücksetzen</button>
            </form>
        </div>
    </div>
    <?php
}
