<?php
/**
 * Plugin Name: WCR Digital Signage
 * Description: Digital Signage System für Wake & Camp Ruhlsdorf
 * Version:     1.0.0
 * Author:      WCR
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/db.php';
require_once plugin_dir_path(__FILE__) . 'includes/enqueue.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';

/* ====================================================
   DEFAULTS
==================================================== */
function wcr_ds_defaults() {
    return array(
        'clr_green'         => '#679467',
        'clr_blue'          => '#019ee3',
        'clr_white'         => '#ffffff',
        'clr_text'          => '#eeeeee',
        'clr_muted'         => '#7a8a8a',
        'clr_bg'            => '#080808',
        'clr_bg_dark'       => '#0d0d0d',
        'clr_bg_glass'      => 'rgba(10,14,24,0.65)',
        'font_family'       => 'Segoe UI',
        'viewport_w'        => '1920',
        'viewport_h'        => '1080',
    );
}

/* ====================================================
   OPTION LESEN
==================================================== */
function wcr_ds_get( $key ) {
    $opts = get_option( 'wcr_ds_options', array() );
    $defs = wcr_ds_defaults();
    if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
        return $opts[ $key ];
    }
    return isset( $defs[ $key ] ) ? $defs[ $key ] : '';
}

/* ====================================================
   SANITIZE
==================================================== */
function wcr_ds_sanitize( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }
    $out = array();
    foreach ( $input as $k => $v ) {
        $out[ sanitize_key( $k ) ] = sanitize_text_field( $v );
    }
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
    add_menu_page(
        'WCR Digital Signage',
        'WCR Signage',
        'manage_options',
        'wcr-ds-settings',
        'wcr_ds_render_page',
        'dashicons-desktop',
        59
    );
    add_submenu_page(
        'wcr-ds-settings',
        'WCR Einstellungen',
        'Einstellungen',
        'manage_options',
        'wcr-ds-settings',
        'wcr_ds_render_page'
    );
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
   ADMIN SEITE RENDERN
==================================================== */
function wcr_ds_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $d = wcr_ds_defaults();

    $palette = array(
        'clr_green'   => 'Gruen',
        'clr_blue'    => 'Blau',
        'clr_white'   => 'Weiss',
        'clr_text'    => 'Text',
        'clr_muted'   => 'Grau',
        'clr_bg'      => 'BG',
        'clr_bg_dark' => 'BG Dunkel',
    );

    $color_fields = array(
        'clr_green'   => 'Primaerfarbe (Gruen)',
        'clr_blue'    => 'Akzentfarbe (Blau)',
        'clr_white'   => 'Weiss (Preise)',
        'clr_text'    => 'Textfarbe',
        'clr_muted'   => 'Gedimmter Text (Grau)',
        'clr_bg'      => 'Hintergrund (Schwarz)',
        'clr_bg_dark' => 'Hintergrund dunkel',
    );

    $fonts = array(
        'Segoe UI'   => 'Segoe UI (Standard)',
        'Inter'      => 'Inter',
        'Roboto'     => 'Roboto',
        'Montserrat' => 'Montserrat',
        'Poppins'    => 'Poppins',
        'Oswald'     => 'Oswald',
        'Raleway'    => 'Raleway',
        'Open Sans'  => 'Open Sans',
        'Lato'       => 'Lato',
        'Ubuntu'     => 'Ubuntu',
    );

    $layout_fields = array(
        'viewport_w' => array( 'Viewport Breite (px)', '800', '3840', '1' ),
        'viewport_h' => array( 'Viewport Hoehe (px)',  '600', '2160', '1' ),
    );

    $shortcodes = array(
        '/getraenke/'    => '[wcr_getraenke]',
        '/softdrinks/'   => '[wcr_softdrinks]',
        '/essen/'        => '[wcr_essen]',
        '/kaffee/'       => '[wcr_kaffee]',
        '/windmap/'      => '[wcr_windmap]',
        '/wetter/'       => '[wcr_wetter]',
        '/starter-pack/' => '[wcr_starter_pack]',
    );

    echo '<div class="wrap">';
    echo '<h1 style="display:flex;align-items:center;gap:10px;">&#128250; WCR Digital Signage &ndash; Einstellungen</h1>';
    echo '<p style="color:#555;margin-bottom:20px;">Alle Aenderungen wirken sofort auf alle DS-Seiten.</p>';

    if ( isset( $_GET['wcr_reset'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>Einstellungen auf Standard zurueckgesetzt.</p></div>';
    }
    if ( isset( $_GET['settings-updated'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
    }

    // Farbpalette Vorschau
    echo '<div style="background:#111;border-radius:8px;padding:14px 20px;margin-bottom:24px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">';
    echo '<span style="color:#666;font-size:0.72rem;text-transform:uppercase;letter-spacing:2px;width:100%;">Live Farbpalette</span>';
    foreach ( $palette as $key => $label ) {
        $val = wcr_ds_get( $key );
        echo '<div style="text-align:center;">';
        echo '<div style="width:42px;height:42px;border-radius:8px;background:' . esc_attr( $val ) . ';border:1px solid rgba(255,255,255,0.1);margin:0 auto 4px;"></div>';
        echo '<div style="color:#888;font-size:0.68rem;">' . esc_html( $label ) . '</div>';
        echo '</div>';
    }
    echo '</div>';

    echo '<form method="post" action="options.php">';
    settings_fields( 'wcr_ds_group' );

    // FARBEN
    echo '<h2 style="border-top:1px solid #ddd;padding-top:14px;">&#127912; Farben</h2>';
    echo '<table class="form-table"><tbody>';
    foreach ( $color_fields as $key => $label ) {
        $val = wcr_ds_get( $key );
        echo '<tr>';
        echo '<th scope="row">' . esc_html( $label ) . '</th>';
        echo '<td>';
        echo '<input type="color" name="wcr_ds_options[' . esc_attr( $key ) . ']" id="cp_' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" style="width:48px;height:32px;cursor:pointer;vertical-align:middle;">';
        echo '&nbsp;<input type="text" id="ct_' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" style="width:90px;font-family:monospace;vertical-align:middle;padding:3px 6px;">';
        echo '&nbsp;<span style="color:#aaa;font-size:0.82em;">Standard: <code>' . esc_html( $d[ $key ] ) . '</code></span>';
        echo '</td></tr>';
    }
    $bgg = wcr_ds_get( 'clr_bg_glass' );
    echo '<tr><th scope="row">Glassmorphism BG</th><td>';
    echo '<input type="text" name="wcr_ds_options[clr_bg_glass]" value="' . esc_attr( $bgg ) . '" style="width:230px;font-family:monospace;padding:3px 6px;">';
    echo '&nbsp;<span style="color:#aaa;font-size:0.82em;">z.B. <code>rgba(10,18,40,0.60)</code></span>';
    echo '</td></tr>';
    echo '</tbody></table>';

    // SCHRIFTART
    echo '<h2 style="border-top:1px solid #ddd;padding-top:14px;">&#128300; Schriftart</h2>';
    echo '<table class="form-table"><tbody>';
    $cur_font = wcr_ds_get( 'font_family' );
    echo '<tr><th scope="row">Schriftart</th><td>';
    echo '<select name="wcr_ds_options[font_family]" id="wcr_font_sel" style="padding:4px 8px;">';
    foreach ( $fonts as $fv => $fl ) {
        $sel = ( $cur_font === $fv ) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr( $fv ) . '"' . $sel . '>' . esc_html( $fl ) . '</option>';
    }
    echo '</select>';
    echo '<div id="wcr_font_prev" style="margin-top:8px;padding:8px 14px;background:#111;color:#eee;border-radius:5px;font-size:1.3rem;font-family:' . esc_attr( $cur_font ) . ';display:inline-block;">WCR Signage &ndash; 12,50 &euro;</div>';
    echo '<p style="color:#888;font-size:0.8em;">Google Fonts werden automatisch geladen.</p>';
    echo '</td></tr>';
    echo '</tbody></table>';

    // VIEWPORT
    echo '<h2 style="border-top:1px solid #ddd;padding-top:14px;">&#128207; Viewport</h2>';
    echo '<table class="form-table"><tbody>';
    foreach ( $layout_fields as $key => $cfg ) {
        $val = wcr_ds_get( $key );
        echo '<tr><th scope="row">' . esc_html( $cfg[0] ) . '</th><td>';
        echo '<input type="number" name="wcr_ds_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" min="' . esc_attr( $cfg[1] ) . '" max="' . esc_attr( $cfg[2] ) . '" step="' . esc_attr( $cfg[3] ) . '" style="width:80px;padding:3px 6px;">';
        echo '&nbsp;<span style="color:#aaa;font-size:0.82em;">Standard: <code>' . esc_html( $d[ $key ] ) . '</code></span>';
        echo '</td></tr>';
    }
    echo '</tbody></table>';

    // SPEICHERN
    echo '<p style="margin-top:20px;">';
    submit_button( 'Einstellungen speichern', 'primary', 'submit', false );
    echo '</p>';
    echo '</form>';

    // RESET
    echo '<form method="post" style="margin-top:4px;">';
    wp_nonce_field( 'wcr_ds_reset_action', 'wcr_ds_reset_nonce' );
    echo '<input type="hidden" name="wcr_ds_reset" value="1">';
    echo '<button type="submit" style="background:#b91c1c;color:#fff;border:none;padding:5px 14px;border-radius:4px;cursor:pointer;" onclick="return confirm(\'Standard zuruecksetzen?\')">&#128260; Auf Standard zuruecksetzen</button>';
    echo '</form>';

    // SHORTCODE REFERENZ
    echo '<h2 style="border-top:1px solid #ddd;padding-top:14px;margin-top:20px;">&#128203; Shortcode-Referenz</h2>';
    echo '<table class="widefat" style="max-width:480px;">';
    echo '<thead><tr><th>Seite</th><th>Shortcode</th></tr></thead><tbody>';
    foreach ( $shortcodes as $pg => $sc ) {
        echo '<tr>';
        echo '<td><a href="' . esc_url( home_url( $pg ) ) . '" target="_blank">' . esc_html( $pg ) . '</a></td>';
        echo '<td><code>' . esc_html( $sc ) . '</code></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // JS
    echo '<script type="text/javascript">';
    echo 'document.querySelectorAll("input[type=color]").forEach(function(cp){';
    echo '  cp.addEventListener("input",function(){';
    echo '    var txt=document.getElementById("ct_"+this.id.replace("cp_",""));';
    echo '    if(txt){txt.value=this.value;}';
    echo '  });';
    echo '});';
    echo 'document.querySelectorAll("input[type=text][id^=ct_]").forEach(function(ct){';
    echo '  ct.addEventListener("input",function(){';
    echo '    var cp=document.getElementById("cp_"+this.id.replace("ct_",""));';
    echo '    if(cp&&/^#[0-9a-fA-F]{6}$/.test(this.value)){cp.value=this.value;}';
    echo '  });';
    echo '});';
    echo 'var fs=document.getElementById("wcr_font_sel");';
    echo 'if(fs){fs.addEventListener("change",function(){';
    echo '  var p=document.getElementById("wcr_font_prev");';
    echo '  if(p){p.style.fontFamily=this.value;}';
    echo '});}';
    echo '</script>';

    echo '</div>';
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
    if ( ! in_array( $theme, array( 'glass', 'flat', 'aurora' ), true ) ) {
        $theme = 'glass';
    }

    // Google Font laden
    $sys = array( 'Segoe UI', 'Arial', 'Helvetica', 'Georgia', 'Verdana', 'Tahoma' );
    if ( ! in_array( $ff, $sys, true ) ) {
        $gf = 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $ff ) . ':wght@400;600;700;800;900&display=swap';
        echo '<link rel="stylesheet" href="' . esc_url( $gf ) . '">' . "\n";
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
    echo '.ds-dot      {background:var(--clr-green);box-shadow:0 0 10px var(--clr-green);}' . "\n";
    echo '.ds-live-dot {background:var(--clr-blue);box-shadow:0 0 10px var(--clr-blue);}' . "\n";

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
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if ( strlen( $hex ) !== 6 ) return '128,128,128';
        return hexdec( substr($hex,0,2) ) . ',' . hexdec( substr($hex,2,2) ) . ',' . hexdec( substr($hex,4,2) );
    }
}

/* ====================================================
   ASSETS
==================================================== */
add_action( 'wp_enqueue_scripts', 'wcr_ds_enqueue' );
function wcr_ds_enqueue() {
    wp_enqueue_style(  'wcr-ds-global', plugin_dir_url( __FILE__ ) . 'assets/css/wcr-ds-global.css', array(), '3.1.0' );
    wp_enqueue_script( 'wcr-ds-utils',  plugin_dir_url( __FILE__ ) . 'assets/js/wcr-ds-utils.js',   array(), '3.1.0', true );
}
function wcr_ds_load_gsap() {
    if ( ! wp_script_is( 'gsap', 'enqueued' ) ) {
        wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', array(), '3.12.5', true );
    }
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
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",[';
    $out .= '{label:"Bier",types:["bier","weissbier","wein"]},';
    $out .= '{label:"Mix",types:["bier-mix","brlo","weinmix"]},';
    $out .= '{label:"Drinks",types:["longdrink","shots"]}';
    $out .= '],"/wp-json/wakecamp/v1/drinks");});</script>' . "\n";
    return $out;
}

function wcr_sc_softdrinks( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",[';
    $out .= '{label:"Softdrinks",types:["fritz-kola","fritz-limo","fritz-spritz","energy"]},';
    $out .= '{label:"Cold",types:["homemade","wasser"]},';
    $out .= '{label:"Hot",types:["tee","chocolate"]}';
    $out .= '],"/wp-json/wakecamp/v1/drinks");});</script>' . "\n";
    return $out;
}

function wcr_sc_essen( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",[';
    $out .= '{label:"Bar",types:["bar"]},';
    $out .= '{label:"Grill",types:["grill"]},';
    $out .= '{label:"Kueche",types:["kueche"]}';
    $out .= '],"/wp-json/wakecamp/v1/food","/wp-json/wakecamp/v1/food/gruppen");});</script>' . "\n";
    return $out;
}

function wcr_sc_kaffee( $atts ) {
    wp_enqueue_script( 'wcr-kaffee', plugin_dir_url( __FILE__ ) . 'assets/js/wcr-kaffee.js', array(), '3.1.0', true );
    $out  = '<div class="ds-header">';
    $out .= '<div class="ds-header-line"></div>';
    $out .= '<div class="ds-header-inner"><div class="ds-dot"></div>Kaffeekarte<div class="ds-dot"></div></div>';
    $out .= '<div class="ds-header-line right"></div>';
    $out .= '</div>' . "\n";
    $out .= '<div class="kaffee-grid" id="kaffee-grid"></div>' . "\n";
    return $out;
}

function wcr_sc_windmap( $atts ) {
    wcr_ds_load_leaflet();
    wp_enqueue_script( 'wcr-windmap', plugin_dir_url( __FILE__ ) . 'assets/js/wcr-windmap.js', array( 'leaflet-js' ), '3.1.0', true );
    $out  = '<div id="wcr-windmap-wrap">' . "\n";
    $out .= '<div id="map"></div>' . "\n";
    $out .= '<canvas id="wind-canvas"></canvas>' . "\n";
    $out .= '<canvas id="gust-canvas"></canvas>' . "\n";
    $out .= '<div class="glass" id="spot-header">';
    $out .= '<div><div class="ds-live-dot"></div></div>';
    $out .= '<div><div class="spot-name">📍 Wake &amp; Camp Ruhlsdorf</div>';
    $out .= '<div class="spot-coords">52.8213° N · 13.5754° E</div></div>';
    $out .= '</div>' . "\n";
    $out .= '<div class="glass" id="time-card">';
    $out .= '<div id="tc-forecast">–</div>';
    $out .= '<div id="tc-realtime">–</div>';
    $out .= '<div id="tc-badge">Jetzt</div>';
    $out .= '</div>' . "\n";
    $out .= '<div id="right-panel">';
    $out .= '<div id="kn-box"><div class="kn-icon">💨</div><div class="kn-val" id="kn-speed">–</div><div class="kn-unit">Knoten</div><div class="kn-label">Windstärke</div></div>';
    $out .= '<div id="windrose-box">';
    $out .= '<svg width="100" height="100" viewBox="0 0 100 100">';
    $out .= '<circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/>';
    $out .= '<text x="50" y="12" text-anchor="middle" fill="#7a9abc" font-size="11">N</text>';
    $out .= '<text x="50" y="96" text-anchor="middle" fill="#7a9abc" font-size="11">S</text>';
    $out .= '<text x="92" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">O</text>';
    $out .= '<text x="8"  y="54" text-anchor="middle" fill="#7a9abc" font-size="11">W</text>';
    $out .= '<g id="wr-arrow" transform="rotate(0 50 50)"><polygon points="50,14 55,42 50,38 45,42" fill="#00c8ff" opacity="0.9"/><polygon points="50,86 55,58 50,62 45,58" fill="rgba(0,200,255,0.2)"/></g>';
    $out .= '</svg><div id="wr-degs">–°</div><div id="wr-label">Windrichtung</div>';
    $out .= '</div></div>' . "\n";
    $out .= '<div id="timeline"><div id="tl-current-time"><span id="tl-label-text">–</span><span id="tl-delta"></span></div>';
    $out .= '<div id="tl-track-wrap"><div id="tl-labels"></div><div id="tl-rail"><div id="tl-fill"></div><div id="tl-cursor"></div></div></div></div>' . "\n";
    $out .= '</div>' . "\n";
    return $out;
}

function wcr_sc_wetter( $atts ) {
    wp_enqueue_script( 'wcr-wetter', plugin_dir_url(__FILE__) . 'assets/js/wcr-wetter.js', array(), '3.2.0', true );
    $out  = '<div id="wetter-wrap">';
    $out .= '<header>';
    $out .= '<div><div id="w-location">Ruhlsdorf</div><div style="font-size:1.2rem;color:#64748b;">Aktuelles Wetter</div></div>';
    $out .= '<div><div id="clock-time">00:00</div><div id="clock-date">Montag, 1. Januar</div></div>';
    $out .= '</header>';
    $out .= '<main>';
    $out .= '<div class="current-hero"><div class="hero-icon" id="cur-icon"></div>';
    $out .= '<div><div class="hero-temp" id="cur-temp">--<span class="hero-unit">&deg;</span></div>';
    $out .= '<div class="hero-desc" id="cur-desc">Laden...</div></div></div>';
    $out .= '<div class="current-details">';
    $out .= '<div class="detail-card glass"><div class="dc-label">Gef&uuml;hlt</div><div class="dc-value" id="cur-feel">--&deg;</div></div>';
    $out .= '<div class="detail-card glass"><div class="dc-label">Wind</div><div class="dc-value" id="cur-wind">-- <span class="dc-sub">km/h</span></div></div>';
    $out .= '<div class="detail-card glass"><div class="dc-label">Regenwahrsch.</div><div class="dc-value" id="cur-rain">-- <span class="dc-sub">%</span></div></div>';
    $out .= '<div class="detail-card glass"><div class="dc-label">Sonnenuntergang</div><div class="dc-value" id="cur-sunset">--:--</div></div>';
    $out .= '</div></main>';
    $out .= '<footer id="forecast-grid"></footer>';
    $out .= '</div>';
    return $out;
}

function wcr_sc_starter_pack( $atts ) {
    wcr_ds_load_gsap();
    wp_enqueue_script( 'wcr-starter-pack', plugin_dir_url( __FILE__ ) . 'assets/js/wcr-starter-pack.js', array( 'gsap' ), '3.1.0', true );
    return '<div id="sp-display"></div>' . "\n";
}
