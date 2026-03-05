/* ═══════════════════════════════════════════════════════
   WCR Obstacles Map – wcr-obstacles-map.js
   Unterstützt Portrait (1080×1920) und Landscape (1920×1080)
   Wird via data-mode="portrait|landscape" gesteuert
═══════════════════════════════════════════════════════ */
(function () {

    const DEFAULT_CFG = {
        lat:  52.821428251670844,
        lon:  13.5770999960116,
        zoom: 17.9,
        rot:  0
    };

    const STYLES = [
        {
            id:    'voyager-nolabels',
            label: '🗺️ OSM (clean)',
            url:   'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png',
            attr:  '© OpenStreetMap © CARTO'
        },
        {
            id:    'satellite',
            label: '🛰️ Satellite',
            url:   'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            attr:  'Tiles © Esri'
        },
        {
            id:    'dark',
            label: '🌑 Dark',
            url:   'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png',
            attr:  '© OpenStreetMap © CARTO'
        },
        {
            id:    'light',
            label: '☀️ Light',
            url:   'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png',
            attr:  '© OpenStreetMap © CARTO'
        },
        {
            id:    'satellite-labels',
            label: '🛰️ Sat+Labels',
            url:   'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            attr:  'Tiles © Esri'
        },
    ];

    const DEFAULT_STYLE_KEY = 'wcr-obstacles-style';

    const TYPE_ICONS = {
        kicker:  '🚀',
        rail:    '🟧',
        box:     '🟦',
        fun:     '⭐',
        slider:  '🟩',
        default: '🟣'
    };

    function getMode(wrap) {
        var m = (window.wcrObstaclesMap && window.wcrObstaclesMap.mode) || '';
        m = (m || '').toString().toLowerCase().trim();
        if (m === 'portrait' || m === 'landscape') return m;
        if (wrap && wrap.classList.contains('portrait')) return 'portrait';
        return 'landscape';
    }

    function getMapConfigUrl() {
        return (window.wcrObstaclesMap && window.wcrObstaclesMap.mapConfigUrl) || '';
    }

    function setStageRotation(stage, deg) {
        if (!stage) return;
        if (!isFinite(deg)) deg = 0;
        stage.style.transform = 'rotate(' + deg + 'deg)';
    }

    function initObstaclesMap() {
        var wrap  = document.getElementById('wcr-obstacles-map-wrap');
        var stage = document.getElementById('wcr-obstacles-stage');
        var el    = document.getElementById('wcr-obstacles-map');
        if (!el || !window.L) return;

        /* ── Portrait oder Landscape? ── */
        var IS_PORTRAIT = wrap && wrap.classList.contains('portrait');
        var W = IS_PORTRAIT ? 1080 : 1920;
        var H = IS_PORTRAIT ? 1920 : 1080;

        var ICON_SIZE = IS_PORTRAIT ? 60   : 44;
        var FONT_SIZE = IS_PORTRAIT ? '36px' : '28px';
        var LBL_SIZE  = IS_PORTRAIT ? '15px' : '11px';
        var BTN_FONT  = IS_PORTRAIT ? '18px' : '13px';
        var BTN_PAD   = IS_PORTRAIT ? '10px 20px' : '5px 12px';

        var apiUrl = (window.wcrObstaclesMap && window.wcrObstaclesMap.apiUrl)
                     || el.getAttribute('data-api');

        var mode = getMode(wrap);

        /* ── Leaflet Map ── */
        var map = L.map(el, {
            zoomControl:        false,
            attributionControl: true,
            dragging:           false,
            scrollWheelZoom:    false,
            doubleClickZoom:    false,
            touchZoom:          false,
            zoomSnap:           0,
            preferCanvas:       true
        }).setView([DEFAULT_CFG.lat, DEFAULT_CFG.lon], DEFAULT_CFG.zoom);

        setStageRotation(stage, DEFAULT_CFG.rot);

        /* ── Map-Config laden (mode-spezifisch) ── */
        var cfgUrl = getMapConfigUrl();
        if (cfgUrl) {
            var u = cfgUrl + (cfgUrl.indexOf('?') >= 0 ? '&' : '?') + 'mode=' + encodeURIComponent(mode);
            fetch(u, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (cfg) {
                    if (!cfg) return;
                    var lat  = parseFloat(cfg.lat);
                    var lon  = parseFloat(cfg.lon);
                    var zoom = parseFloat(cfg.zoom);
                    var rot  = parseFloat(cfg.rot);
                    if (isFinite(lat) && isFinite(lon) && isFinite(zoom)) {
                        map.setView([lat, lon], zoom);
                    }
                    if (isFinite(rot)) {
                        setStageRotation(stage, rot);
                        setTimeout(function () { map.invalidateSize(); }, 50);
                    }
                })
                .catch(function () {});
        }

        /* ── Tile-Layer ── */
        var savedIdx = parseInt(localStorage.getItem(DEFAULT_STYLE_KEY) || '0', 10);
        if (isNaN(savedIdx) || savedIdx < 0 || savedIdx >= STYLES.length) savedIdx = 0;
        var currentStyleIdx = savedIdx;

        var currentLayer = L.tileLayer(STYLES[currentStyleIdx].url, {
            attribution:  STYLES[currentStyleIdx].attr,
            maxZoom:      21,
            detectRetina: true
        }).addTo(map);

        /* ── Style-Switcher ── */
        var switcher = document.createElement('div');
        switcher.className = 'wcr-style-switcher';

        STYLES.forEach(function (s, idx) {
            var btn = document.createElement('button');
            btn.textContent = s.label;
            btn.dataset.idx = idx;
            btn.style.cssText = [
                'background:rgba(15,20,30,.82)',
                'border:1px solid rgba(255,255,255,.18)',
                'color:#e8eaf0',
                'font-size:'  + BTN_FONT,
                'font-weight:600',
                'padding:'    + BTN_PAD,
                'border-radius:10px',
                'cursor:pointer',
                'text-align:left',
                'white-space:nowrap',
                'letter-spacing:.02em',
                'backdrop-filter:blur(6px)',
                '-webkit-backdrop-filter:blur(6px)'
            ].join(';');

            if (idx === currentStyleIdx) {
                btn.style.background  = 'rgba(59,130,246,.80)';
                btn.style.borderColor = 'rgba(99,179,255,.65)';
            }

            btn.addEventListener('click', function () {
                var newIdx = parseInt(btn.dataset.idx, 10);
                if (newIdx === currentStyleIdx) return;
                map.removeLayer(currentLayer);
                currentLayer = L.tileLayer(STYLES[newIdx].url, {
                    attribution:  STYLES[newIdx].attr,
                    maxZoom:      21,
                    detectRetina: true
                }).addTo(map);
                currentLayer.bringToBack();
                currentStyleIdx = newIdx;
                localStorage.setItem(DEFAULT_STYLE_KEY, newIdx);
                switcher.querySelectorAll('button').forEach(function (b) {
                    b.style.background  = 'rgba(15,20,30,.82)';
                    b.style.borderColor = 'rgba(255,255,255,.18)';
                });
                btn.style.background  = 'rgba(59,130,246,.80)';
                btn.style.borderColor = 'rgba(99,179,255,.65)';
            });
            switcher.appendChild(btn);
        });

        if (wrap) wrap.appendChild(switcher);
        else el.appendChild(switcher);

        setTimeout(function () { map.invalidateSize(); }, 150);

        /* ── Obstacles rendern ── */
        function renderObstacles(list) {
            list.forEach(function (o) {
                var lat = parseFloat(o.lat   || 0);
                var lon = parseFloat(o.lon   || 0);
                var px  = parseFloat(o.pos_x || 0);
                var py  = parseFloat(o.pos_y || 0);
                var rot = parseFloat(o.rotation || 0);
                var type  = (o.type || 'default').toLowerCase();
                var emoji = TYPE_ICONS[type] || TYPE_ICONS.default;
                var label = o.name     || '';
                var ico   = o.icon_url || '';

                if (lat !== 0 && lon !== 0) {
                    /* ── Geo-Koordinaten via Leaflet Marker ── */
                    var iconHtml = '<div style="transform:rotate(' + rot + 'deg);display:flex;flex-direction:column;align-items:center;gap:4px;">';
                    if (ico) {
                        iconHtml += '<img src="' + ico + '" style="width:' + ICON_SIZE + 'px;height:' + ICON_SIZE + 'px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.7))"/>';
                    } else {
                        iconHtml += '<div style="font-size:' + FONT_SIZE + ';line-height:1;filter:drop-shadow(0 2px 5px rgba(0,0,0,.8))">' + emoji + '</div>';
                    }
                    if (label) {
                        iconHtml += '<span style="font-size:' + LBL_SIZE + ';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.9);white-space:nowrap;letter-spacing:.03em">' + label + '</span>';
                    }
                    iconHtml += '</div>';
                    var divIcon = L.divIcon({
                        html:       iconHtml,
                        className:  'wcr-leaflet-obstacle',
                        iconAnchor: [ICON_SIZE / 2, ICON_SIZE / 2]
                    });
                    L.marker([lat, lon], { icon: divIcon }).addTo(map);

                } else if (px !== 0 || py !== 0) {
                    /* ── Prozent-Position → absolute px im Stage ── */
                    var container = stage || wrap || el;
                    var d = document.createElement('div');
                    d.className = 'wcr-obstacle';
                    d.style.cssText = [
                        'position:absolute',
                        'left:' + (px / 100 * W) + 'px',
                        'top:'  + (py / 100 * H) + 'px',
                        'transform:translate(-50%,-50%)' + (rot ? ' rotate(' + rot + 'deg)' : ''),
                        'z-index:500',
                        'display:flex',
                        'flex-direction:column',
                        'align-items:center',
                        'gap:4px',
                        'pointer-events:none'
                    ].join(';');
                    var iconDiv = document.createElement('div');
                    iconDiv.style.cssText = 'font-size:' + FONT_SIZE + ';line-height:1;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))';
                    if (ico) {
                        iconDiv.style.backgroundImage = 'url(' + ico + ')';
                        iconDiv.style.width           = ICON_SIZE + 'px';
                        iconDiv.style.height          = ICON_SIZE + 'px';
                        iconDiv.style.backgroundSize  = 'contain';
                    } else {
                        iconDiv.textContent = emoji;
                    }
                    d.appendChild(iconDiv);
                    if (label) {
                        var lblEl = document.createElement('span');
                        lblEl.textContent = label;
                        lblEl.style.cssText = 'font-size:' + LBL_SIZE + ';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.8);white-space:nowrap';
                        d.appendChild(lblEl);
                    }
                    container.appendChild(d);
                }
            });
        }

        if (apiUrl) {
            fetch(apiUrl)
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    if (!Array.isArray(data) || data.length === 0) return;
                    var container = stage || wrap || el;
                    container.querySelectorAll('.wcr-obstacle').forEach(function (n) { n.remove(); });
                    var hint = (wrap || el).querySelector('.wcr-obstacles-placeholder-hint');
                    if (hint) hint.remove();
                    renderObstacles(data);
                })
                .catch(function () {});
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initObstaclesMap);
    } else {
        initObstaclesMap();
    }

})();
