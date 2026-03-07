<?php
/**
 * Shortcode Content Functions
 * 
 * Enthält die Callback-Funktionen für Content-Shortcodes.
 * Wird von wcr-digital-signage.php geladen BEVOR add_shortcode() aufgerufen wird.
 * 
 * WICHTIG: Keine add_shortcode() hier – Registrierung bleibt in Hauptdatei!
 */

if (!defined('ABSPATH')) exit;

/* ════════════════════════════════════════════════════════════════════════════
   GETRÄNKE & SPEISEN SHORTCODES
   Nutzen WCR.renderDrinksList() aus wcr-ds-utils.js
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_getraenke] – Alkoholische Getränke
 * Zeigt Bier, Weißbier, Wein, Mix-Getränke, Longdrinks, Shots
 */
function wcr_sc_getraenke( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Bier",types:["bier","weissbier","wein"]},';
    $out .= '{label:"Mix",types:["bier-mix","brlo","weinmix"]},';
    $out .= '{label:"Drinks",types:["longdrink","shots"]}],';
    $out .= '"/wp-json/wakecamp/v1/drinks"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_softdrinks] – Alkoholfreie Getränke
 * Zeigt Fritz-Kola, Fritz-Limo, Energy, Wasser, Tee, Chocolate
 */
function wcr_sc_softdrinks( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Softdrinks",types:["fritz-kola","fritz-limo","fritz-spritz","energy"]},';
    $out .= '{label:"Cold",types:["homemade","wasser"]},';
    $out .= '{label:"Hot",types:["tee","chocolate"]}],';
    $out .= '"/wp-json/wakecamp/v1/drinks"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_essen] – Speisekarte
 * Zeigt Bar, Grill, Küche mit Food-Gruppen-Status
 */
function wcr_sc_essen( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Bar",types:["bar"]},';
    $out .= '{label:"Grill",types:["grill"]},';
    $out .= '{label:"Kueche",types:["kueche"]}],';
    $out .= '"/wp-json/wakecamp/v1/food",';
    $out .= '"/wp-json/wakecamp/v1/food/gruppen"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_eis] – Eiskarte
 * Zeigt Ice-Produkte aus Tabelle `ice`
 */
function wcr_sc_eis( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Eis",types:["obenlinks","obenrechts","untenlinks","untenrechts"]}],';
    $out .= '"/wp-json/wakecamp/v1/ice"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_cable] – Cablepark-Preise
 * Zeigt Tickets, Verleih, Spezial aus Tabelle `cable`
 */
function wcr_sc_cable( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Tickets",types:["Ticket","ticket","ticket-s","ticket-e","ticket-ed","ticket-a","ticket-d"]},';
    $out .= '{label:"Verleih",types:["board","hardware"]},';
    $out .= '{label:"Spezial",types:["spezial"]}],';
    $out .= '"/wp-json/wakecamp/v1/cable"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_camping] – Camping-Preise
 * Zeigt Personen, Übernachtung, Extras aus Tabelle `camping`
 */
function wcr_sc_camping( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Personen",types:["personen"]},';
    $out .= '{label:"Fahrzeuge & Übernachtung",types:["night"]},';
    $out .= '{label:"Extras",types:["extra"]}],';
    $out .= '"/wp-json/wakecamp/v1/camping"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/* ════════════════════════════════════════════════════════════════════════════
   KAFFEE SHORTCODE
   Nutzt wcr-kaffee.js für dynamisches Rendering
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_kaffee] – Kaffeekarte
 * Lädt Kaffeekarten-Daten aus REST-API und rendert mit wcr-kaffee.js
 */
function wcr_sc_kaffee( $atts ) {
    wp_enqueue_script( 'wcr-kaffee', WCR_DS_URL . 'assets/js/wcr-kaffee.js', array(), WCR_DS_VERSION, true );
    
    $out  = '<div class="ds-header">';
    $out .= '<div class="ds-header-line"></div>';
    $out .= '<div class="ds-header-inner">';
    $out .= '<div class="ds-dot"></div>Kaffeekarte<div class="ds-dot"></div>';
    $out .= '</div>';
    $out .= '<div class="ds-header-line right"></div>';
    $out .= '</div>' . "\n";
    $out .= '<div class="kaffee-grid" id="kaffee-grid"></div>' . "\n";
    
    return $out;
}

/* ════════════════════════════════════════════════════════════════════════════
   WETTER & WIND SHORTCODES
   Nutzen externe APIs (Open-Meteo) für Live-Wetterdaten
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_windmap] – Wind-Karte mit Live-Daten
 * Zeigt Windstärke, Windrichtung, Timeline mit Leaflet-Karte
 * Lädt Leaflet-Bibliothek dynamisch
 */
function wcr_sc_windmap( $atts ) {
    wcr_ds_load_leaflet();
    wp_enqueue_script( 'wcr-windmap', WCR_DS_URL . 'assets/js/wcr-windmap.js', array('leaflet-js'), WCR_DS_VERSION, true );
    
    $out  = '<div id="wcr-windmap-wrap">';
    $out .= '<div id="map"></div>';
    $out .= '<canvas id="wind-canvas"></canvas>';
    $out .= '<canvas id="gust-canvas"></canvas>';
    
    // Spot-Header
    $out .= '<div class="glass" id="spot-header">';
    $out .= '<div><div class="ds-live-dot"></div></div>';
    $out .= '<div><div class="spot-name">📍 Wake &amp; Camp Ruhlsdorf</div>';
    $out .= '<div class="spot-coords">52.8213° N · 13.5754° E</div></div>';
    $out .= '</div>';
    
    // Time-Card
    $out .= '<div class="glass" id="time-card">';
    $out .= '<div id="tc-forecast">–</div>';
    $out .= '<div id="tc-realtime">–</div>';
    $out .= '<div id="tc-badge">Jetzt</div>';
    $out .= '</div>';
    
    // Right-Panel
    $out .= '<div id="right-panel">';
    $out .= '<div id="kn-box">';
    $out .= '<div class="kn-icon">💨</div>';
    $out .= '<div class="kn-val" id="kn-speed">–</div>';
    $out .= '<div class="kn-unit">Knoten</div>';
    $out .= '<div class="kn-label">Windstärke</div>';
    $out .= '</div>';
    $out .= '<div id="windrose-box">';
    $out .= '<svg width="100" height="100" viewBox="0 0 100 100">';
    $out .= '<circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/>';
    $out .= '<text x="50" y="12" text-anchor="middle" fill="#7a9abc" font-size="11">N</text>';
    $out .= '<text x="50" y="96" text-anchor="middle" fill="#7a9abc" font-size="11">S</text>';
    $out .= '<text x="92" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">O</text>';
    $out .= '<text x="8" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">W</text>';
    $out .= '<g id="wr-arrow" transform="rotate(0 50 50)">';
    $out .= '<polygon points="50,14 55,42 50,38 45,42" fill="#00c8ff" opacity="0.9"/>';
    $out .= '<polygon points="50,86 55,58 50,62 45,58" fill="rgba(0,200,255,0.2)"/>';
    $out .= '</g></svg>';
    $out .= '<div id="wr-degs">–°</div>';
    $out .= '<div id="wr-label">Windrichtung</div>';
    $out .= '</div>';
    $out .= '</div>';
    
    // Timeline
    $out .= '<div id="timeline">';
    $out .= '<div id="tl-current-time">';
    $out .= '<span id="tl-label-text">–</span>';
    $out .= '<span id="tl-delta"></span>';
    $out .= '</div>';
    $out .= '<div id="tl-track-wrap">';
    $out .= '<div id="tl-labels"></div>';
    $out .= '<div id="tl-rail">';
    $out .= '<div id="tl-fill"></div>';
    $out .= '<div id="tl-cursor"></div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';
    
    $out .= '</div>'; // #wcr-windmap-wrap
    
    return $out;
}

/**
 * [wcr_wetter] – Wetter-Anzeige
 * Zeigt aktuelles Wetter und 7-Tage-Vorhersage
 */
function wcr_sc_wetter( $atts ) {
    wp_enqueue_script( 'wcr-wetter', WCR_DS_URL . 'assets/js/wcr-wetter.js', array(), '3.2.0', true );
    
    $out  = '<div id="wetter-wrap">';
    
    // Header
    $out .= '<header>';
    $out .= '<div>';
    $out .= '<div id="w-location">Ruhlsdorf</div>';
    $out .= '<div style="font-size:1.2rem;color:#64748b;">Aktuelles Wetter</div>';
    $out .= '</div>';
    $out .= '<div>';
    $out .= '<div id="clock-time">00:00</div>';
    $out .= '<div id="clock-date">Montag, 1. Januar</div>';
    $out .= '</div>';
    $out .= '</header>';
    
    // Main
    $out .= '<main>';
    $out .= '<div class="current-hero">';
    $out .= '<div class="hero-icon" id="cur-icon"></div>';
    $out .= '<div>';
    $out .= '<div class="hero-temp" id="cur-temp">--<span class="hero-unit">&deg;</span></div>';
    $out .= '<div class="hero-desc" id="cur-desc">Laden...</div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '<div class="current-details">';
    $out .= '<div class="detail-card glass"><div class="dc-label">Gefühlt</div><div class="dc-value" id="cur-feel">--&deg;</div></div>';
    $out .= '<div class="detail-card glass"><div class="dc-label">Wind</div><div class="dc-value" id="cur-wind">-- <span class="dc-sub">km/h</span></div></div>';
    $out .= '<div class="detail-card glass"><div class="dc-label">Regenwahrsch.</div><div class="dc-value" id="cur-rain">-- <span class="dc-sub">%</span></div></div>';
    $out .= '<div class="detail-card glass"><div class="dc-label">Sonnenuntergang</div><div class="dc-value" id="cur-sunset">--:--</div></div>';
    $out .= '</div>';
    $out .= '</main>';
    
    // Footer (Forecast)
    $out .= '<footer id="forecast-grid"></footer>';
    
    $out .= '</div>'; // #wetter-wrap
    
    return $out;
}

/* ════════════════════════════════════════════════════════════════════════════
   SPECIAL SHORTCODES
   Komplexe Animationen und Interaktionen
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_starter_pack] – Starter-Pack Animation
 * Zeigt animierte Produkt-Kombination mit GSAP
 * Lädt GSAP-Bibliothek dynamisch
 */
function wcr_sc_starter_pack( $atts ) {
    wcr_ds_load_gsap();
    wp_enqueue_script( 'wcr-starter-pack', WCR_DS_URL . 'assets/js/wcr-starter-pack.js', array('gsap'), WCR_DS_VERSION, true );
    
    return '<div id="sp-display"></div>' . "\n";
}
