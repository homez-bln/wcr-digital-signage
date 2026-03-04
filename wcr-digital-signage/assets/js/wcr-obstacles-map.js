/* ═══════════════════════════════════════════════════════
   WCR Obstacles Map – wcr-obstacles-map.js
   Leaflet Karte + Obstacles als DivIcons
   Map-Style wechselbar per Switcher-UI
   Standard: OSM Voyager (keine Straßennamen)
   Koordinaten identisch mit wcr-windmap.js
═══════════════════════════════════════════════════════ */
(function () {

    const MAP_LAT = 52.821428251670844;
    const MAP_LON = 13.5770999960116;
    const ZOOM    = 17.9;

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

    /* ── Icon/Button-Größen: 16:9 vs 9:16 ── */
    var bodyW = document.documentElement.clientWidth  || 1920;
    var bodyH = document.documentElement.clientHeight || 1080;
    var IS_PORTRAIT = bodyH > bodyW;

    var ICON_SIZE = IS_PORTRAIT ? 60  : 44;
    var FONT_SIZE = IS_PORTRAIT ? '36px' : '28px';
    var LBL_SIZE  = IS_PORTRAIT ? '15px' : '11px';
    var BTN_FONT  = IS_PORTRAIT ? '18px' : '13px';
    var BTN_PAD   = IS_PORTRAIT ? '10px 20px' : '5px 12px';

    /* ── Leaflet Pane z-indexes per JS setzen ── */
    function fixPaneZIndexes(map) {
        var p = map.getPanes();
        if (p.mapPane)     p.mapPane.style.zIndex     = '1';
        if (p.tilePane)    p.tilePane.style.zIndex    = '2';
        if (p.overlayPane) p.overlayPane.style.zIndex = '3';
        if (p.shadowPane)  p.shadowPane.style.zIndex  = '4';
        if (p.markerPane)  p.markerPane.style.zIndex  = '500';
        if (p.tooltipPane) p.tooltipPane.style.zIndex = '501';
        if (p.popupPane)   p.popupPane.style.zIndex   = '502';
    }

    function initObstaclesMap() {
        var el = document.getElementById('wcr-obstacles-map');
        if (!el) return;
        if (!window.L) { console.warn('Leaflet nicht geladen'); return; }

        var apiUrl = (window.wcrObstaclesMap && window.wcrObstaclesMap.apiUrl)
                     || el.getAttribute('data-api');

        /* ── Container: 100% des Eltern-Elements (Elementor setzt die Größe) ── */
        el.innerHTML = '';
        el.style.cssText = [
            'position:relative',
            'width:100%',
            'height:100%',
            'min-height:400px',
            'overflow:hidden',
            'display:block'
        ].join(';');

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
        }).setView([MAP_LAT, MAP_LON], ZOOM);

        /* Panes sofort nach unten */
        fixPaneZIndexes(map);

        /* ── Tile-Layer ── */
        var savedIdx = parseInt(localStorage.getItem(DEFAULT_STYLE_KEY) || '0', 10);
        if (isNaN(savedIdx) || savedIdx < 0 || savedIdx >= STYLES.length) savedIdx = 0;
        var currentStyleIdx = savedIdx;

        var currentLayer = L.tileLayer(STYLES[currentStyleIdx].url, {
            attribution:  STYLES[currentStyleIdx].attr,
            maxZoom:      21,
            detectRetina: true,
            /* Sub-Pixel-Gap verhindern */
            className:    'wcr-tile-layer'
        }).addTo(map);

        /* ── Style-Switcher ── */
        var switcher = document.createElement('div');
        switcher.style.cssText = [
            'position:absolute',
            'top:20px',
            'right:20px',
            'z-index:1000',
            'display:flex',
            'flex-direction:column',
            'gap:8px',
            'pointer-events:all'
        ].join(';');

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
                'transition:background .15s,border-color .15s',
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
                    attribution: STYLES[newIdx].attr,
                    maxZoom:     21,
                    detectRetina: true,
                    className:   'wcr-tile-layer'
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

        el.appendChild(switcher);

        /* Nach Render: Panes nochmal fixieren + Größe korrekt setzen */
        setTimeout(function () {
            map.invalidateSize();
            fixPaneZIndexes(map);
        }, 150);

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
                    var d = document.createElement('div');
                    d.className = 'wcr-obstacle';
                    d.style.cssText = [
                        'position:absolute',
                        'left:' + px + '%',
                        'top:'  + py + '%',
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
                    el.appendChild(d);
                }
            });
        }

        if (apiUrl) {
            fetch(apiUrl)
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    if (!Array.isArray(data) || data.length === 0) return;
                    el.querySelectorAll('.wcr-obstacle').forEach(function (n) { n.remove(); });
                    var hint = el.querySelector('.wcr-obstacles-placeholder-hint');
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
