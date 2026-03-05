<?php
if (!defined('ABSPATH')) exit;

/* ════════════════════════════════════════════════════════════
   WCR Obstacles – Admin-Seite
   Zoom-Faktor + Map-Center per Slider einstellbar
   Werte werden via WP-Options gespeichert:
     wcr_obstacles_map_lat   (float)
     wcr_obstacles_map_lon   (float)
     wcr_obstacles_map_zoom  (float)
════════════════════════════════════════════════════════════ */

function wcr_obstacles_admin_page() {
    $lat  = (float) get_option('wcr_obstacles_map_lat',  52.821428251670844);
    $lon  = (float) get_option('wcr_obstacles_map_lon',  13.5770999960116);
    $zoom = (float) get_option('wcr_obstacles_map_zoom', 17.9);
    $nonce = wp_create_nonce('wcr_obstacles_map_config');
    ?>
    <div class="wrap" id="wcr-obs-admin">
    <h1>🏄 Obstacles – Karten-Einstellungen</h1>
    <p style="color:#aaa;margin-top:-6px;">Zoom-Faktor und Kartenausschnitt für <code>[wcr_obstacles_map]</code> einstellen.</p>

    <div style="display:grid;grid-template-columns:380px 1fr;gap:24px;margin-top:24px;">

        <!-- ── Slider-Panel ── -->
        <div style="background:#1a1a2e;border:1px solid #333;border-radius:12px;padding:24px;">

            <div class="wcr-obs-field">
                <label>🔍 Zoom <span id="lbl-zoom"><?= number_format($zoom, 1) ?></span></label>
                <input type="range" id="sl-zoom"
                       min="10" max="21" step="0.1"
                       value="<?= esc_attr($zoom) ?>">
                <div class="wcr-obs-sub">Empfohlen: 16 – 19 · Standard: 17.9</div>
            </div>

            <div class="wcr-obs-field">
                <label>📍 Latitude <span id="lbl-lat"><?= $lat ?></span></label>
                <input type="range" id="sl-lat"
                       min="52.75" max="52.90" step="0.0001"
                       value="<?= esc_attr($lat) ?>">
                <div class="wcr-obs-sub">N–S verschieben</div>
            </div>

            <div class="wcr-obs-field">
                <label>📍 Longitude <span id="lbl-lon"><?= $lon ?></span></label>
                <input type="range" id="sl-lon"
                       min="13.50" max="13.65" step="0.0001"
                       value="<?= esc_attr($lon) ?>">
                <div class="wcr-obs-sub">W–O verschieben</div>
            </div>

            <hr style="border-color:#333;margin:20px 0;">

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button id="btn-reset" class="button">
                    ↩ Auf Standard zurücksetzen
                </button>
                <button id="btn-save" class="button button-primary" style="flex:1;">
                    💾 Speichern
                </button>
            </div>

            <div id="wcr-obs-msg" style="margin-top:14px;font-size:13px;min-height:20px;"></div>
        </div>

        <!-- ── Live-Vorschau ── -->
        <div style="position:relative;">
            <div style="font-size:12px;color:#666;margin-bottom:8px;">Live-Vorschau (klick auf Karte = neues Zentrum setzen)</div>
            <div id="wcr-obs-preview"
                 style="width:100%;height:480px;border-radius:10px;border:1px solid #333;overflow:hidden;background:#1a1a2e;">
            </div>
            <div id="wcr-obs-coords"
                 style="position:absolute;bottom:14px;left:14px;background:rgba(0,0,0,.7);color:#0ff;
                        font-size:11px;padding:4px 10px;border-radius:20px;font-family:monospace;pointer-events:none;">
                –
            </div>
        </div>

    </div>
    </div>

    <!-- Leaflet -->
    <link  rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
    #wcr-obs-admin { max-width: 1400px; }
    #wcr-obs-admin h1 { font-size: 22px; color: #eee; }
    .wcr-obs-field { margin-bottom: 22px; }
    .wcr-obs-field label {
        display: block; font-size: 13px; font-weight: 600;
        color: #ccc; margin-bottom: 7px;
    }
    .wcr-obs-field label span {
        font-family: monospace; color: #4af;
        background: rgba(0,180,255,.12); padding: 1px 7px;
        border-radius: 6px; margin-left: 8px;
    }
    .wcr-obs-field input[type=range] {
        width: 100%; height: 6px;
        -webkit-appearance: none; appearance: none;
        background: linear-gradient(90deg, #4af 0%, #333 0%);
        border-radius: 4px; outline: none; cursor: pointer;
    }
    .wcr-obs-field input[type=range]::-webkit-slider-thumb {
        -webkit-appearance: none; width: 18px; height: 18px;
        border-radius: 50%; background: #4af;
        border: 2px solid #111; cursor: pointer;
    }
    .wcr-obs-sub { font-size: 11px; color: #555; margin-top: 4px; }
    #wcr-obs-msg.ok  { color: #4f4; }
    #wcr-obs-msg.err { color: #f44; }
    </style>

    <script>
    (function(){
        var LAT_DEFAULT  = 52.821428251670844;
        var LON_DEFAULT  = 13.5770999960116;
        var ZOOM_DEFAULT = 17.9;
        var NONCE        = '<?= esc_js($nonce) ?>';
        var REST_URL     = '<?= esc_js(rest_url('wakecamp/v1/obstacles/map-config')) ?>';

        var slZoom = document.getElementById('sl-zoom');
        var slLat  = document.getElementById('sl-lat');
        var slLon  = document.getElementById('sl-lon');
        var lblZ   = document.getElementById('lbl-zoom');
        var lblLat = document.getElementById('lbl-lat');
        var lblLon = document.getElementById('lbl-lon');
        var msg    = document.getElementById('wcr-obs-msg');
        var coords = document.getElementById('wcr-obs-coords');

        /* ── Leaflet initialisieren ── */
        var map = L.map('wcr-obs-preview', {
            zoomControl:     true,
            dragging:        true,
            scrollWheelZoom: true,
            zoomSnap:        0.1,
            zoomDelta:       0.1
        }).setView([parseFloat(slLat.value), parseFloat(slLon.value)], parseFloat(slZoom.value));

        L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png',
            { attribution: '© OpenStreetMap © CARTO', maxZoom: 21 }
        ).addTo(map);

        /* Fadenkreuz-Marker für Mittelpunkt */
        var crossIcon = L.divIcon({
            html: '<div style="width:24px;height:24px;transform:translate(-50%,-50%);">'
                + '<svg viewBox="0 0 24 24" width="24" height="24">'
                + '<line x1="12" y1="0" x2="12" y2="24" stroke="#0ff" stroke-width="2"/>'
                + '<line x1="0" y1="12" x2="24" y2="12" stroke="#0ff" stroke-width="2"/>'
                + '<circle cx="12" cy="12" r="3" fill="#0ff"/>'
                + '</svg></div>',
            className: '', iconAnchor: [12, 12]
        });
        var crossMarker = L.marker(
            [parseFloat(slLat.value), parseFloat(slLon.value)],
            { icon: crossIcon, interactive: false }
        ).addTo(map);

        /* Slider-Gradient updaten */
        function updateGradient(input) {
            var pct = (input.value - input.min) / (input.max - input.min) * 100;
            input.style.background =
                'linear-gradient(90deg, #4af ' + pct + '%, #333 ' + pct + '%)';
        }
        [slZoom, slLat, slLon].forEach(updateGradient);

        /* ── Slider → Karte ── */
        function applySliders() {
            var z   = parseFloat(slZoom.value);
            var lat = parseFloat(slLat.value);
            var lon = parseFloat(slLon.value);
            lblZ.textContent   = z.toFixed(1);
            lblLat.textContent = lat.toFixed(6);
            lblLon.textContent = lon.toFixed(6);
            coords.textContent = lat.toFixed(6) + ', ' + lon.toFixed(6) + '  zoom: ' + z.toFixed(1);
            map.setView([lat, lon], z);
            crossMarker.setLatLng([lat, lon]);
            [slZoom, slLat, slLon].forEach(updateGradient);
        }

        slZoom.addEventListener('input', applySliders);
        slLat.addEventListener('input',  applySliders);
        slLon.addEventListener('input',  applySliders);

        /* ── Karte ziehen → Slider updaten ── */
        map.on('moveend zoomend', function () {
            var c = map.getCenter();
            var z = map.getZoom();
            slLat.value  = c.lat.toFixed(6);
            slLon.value  = c.lng.toFixed(6);
            slZoom.value = z.toFixed(1);
            applySliders();
        });

        /* ── Klick auf Karte = neues Zentrum ── */
        map.on('click', function (e) {
            slLat.value = e.latlng.lat.toFixed(6);
            slLon.value = e.latlng.lng.toFixed(6);
            applySliders();
            map.panTo([parseFloat(slLat.value), parseFloat(slLon.value)]);
        });

        /* ── Reset ── */
        document.getElementById('btn-reset').addEventListener('click', function () {
            slZoom.value = ZOOM_DEFAULT;
            slLat.value  = LAT_DEFAULT;
            slLon.value  = LON_DEFAULT;
            applySliders();
        });

        /* ── Speichern ── */
        document.getElementById('btn-save').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            msg.className = '';
            msg.textContent = 'Speichern …';

            fetch(REST_URL, {
                method:  'POST',
                headers: {
                    'Content-Type':   'application/json',
                    'X-WP-Nonce':     NONCE
                },
                body: JSON.stringify({
                    lat:  parseFloat(slLat.value),
                    lon:  parseFloat(slLon.value),
                    zoom: parseFloat(slZoom.value)
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d && d.ok) {
                    msg.textContent = '✅ Gespeichert!';
                    msg.className   = 'ok';
                } else {
                    msg.textContent = '❌ Fehler: ' + (d.message || JSON.stringify(d));
                    msg.className   = 'err';
                }
            })
            .catch(function(e) {
                msg.textContent = '❌ ' + e.message;
                msg.className   = 'err';
            })
            .finally(function() { btn.disabled = false; });
        });

        applySliders();
    })();
    </script>
    <?php
}
