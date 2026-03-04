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

    /* ───────────────────────────────────────────────────────
       Map-Stile
       Index 0 = Standard (OSM Voyager nolabels)
    ─────────────────────────────────────────────────────── */
    const STYLES = [
        {
            /* ★ Standard: OSM-Daten, KEIN Text / Straßennamen ★ */
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
            attr:  'Tiles © Esri',
            overlay: 'https://stamen-tiles-{s}.a.ssl.fastly.net/toner-labels/{z}/{x}/{y}.png'
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

    function initObstaclesMap() {
        var el = document.getElementById('wcr-obstacles-map');
        if (!el) return;
        if (!window.L) { console.warn('Leaflet nicht geladen'); return; }

        var apiUrl = (window.wcrObstaclesMap && window.wcrObstaclesMap.apiUrl)
                     || el.getAttribute('data-api');

        /* ── Viewport: 16:9 oder 9:16 ermitteln ── */
        var vw = window.innerWidth  || document.documentElement.clientWidth  || 1920;
        var vh = window.innerHeight || document.documentElement.clientHeight || 1080;
        var isPortrait = vh > vw;

        /* Karte füllt den ganzen Container ── */
        el.innerHTML = '';
        el.style.cssText = [
            'position:relative',
            'width:100%',
            'height:100%',
            'overflow:hidden'
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

        /* ── Startlayer aus localStorage (Standard = Index 0) ── */
        var savedIdx = parseInt(localStorage.getItem(DEFAULT_STYLE_KEY) || '0', 10);
        if (isNaN(savedIdx) || savedIdx < 0 || savedIdx >= STYLES.length) savedIdx = 0;
        var currentStyleIdx = savedIdx;

        var currentLayer = L.tileLayer(STYLES[currentStyleIdx].url, {
            attribution: STYLES[currentStyleIdx].attr,
            maxZoom: 21,
            detectRetina: true
        }).addTo(map);

        /* ── Style-Switcher UI ── */
        var switcher = document.createElement('div');
        switcher.style.cssText = [
            'position:absolute',
            'top:12px',
            'right:12px',
            'z-index:1000',
            'display:flex',
            'flex-direction:column',
            'gap:6px',
            'pointer-events:all'
        ].join(';');

        /* Schriftgröße etwas größer im Portrait (9:16) */
        var btnFontSize = isPortrait ? '15px' : '13px';
        var btnPadding  = isPortrait ? '8px 16px' : '5px 12px';

        STYLES.forEach(function (s, idx) {
            var btn = document.createElement('button');
            btn.textContent = s.label;
            btn.dataset.idx = idx;
            btn.style.cssText = [
                'background:rgba(15,20,30,.82)',
                'border:1px solid rgba(255,255,255,.18)',
                'color:#e8eaf0',
                'font-size:' + btnFontSize,
                'font-weight:600',
                'padding:' + btnPadding,
                'border-radius:8px',
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
                    maxZoom: 21,
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

        el.appendChild(switcher);

        /* ── Karte nach Render invalidieren (wichtig für 100%-Höhe) ── */
        setTimeout(function () { map.invalidateSize(); }, 100);

        /* ── Obstacles laden ── */
        function renderObstacles(list) {
            list.forEach(function (o) {
                var lat = parseFloat(o.lat   || 0);
                var lon = parseFloat(o.lon   || 0);
                var px  = parseFloat(o.pos_x || 0);
                var py  = parseFloat(o.pos_y || 0);
                var rot = parseFloat(o.rotation || 0);
                var type  = (o.type || 'default').toLowerCase();
                var emoji = TYPE_ICONS[type] || TYPE_ICONS.default;
                var label = o.name || '';
                var ico   = o.icon_url || '';

                var iconSize = isPortrait ? 52 : 44;
                var fontSize = isPortrait ? '32px' : '28px';
                var lblSize  = isPortrait ? '13px' : '11px';

                if (lat !== 0 && lon !== 0) {
                    var iconHtml = '<div style="transform:rotate(' + rot + 'deg);display:flex;flex-direction:column;align-items:center;gap:3px;">';
                    if (ico) {
                        iconHtml += '<img src="' + ico + '" style="width:' + iconSize + 'px;height:' + iconSize + 'px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.7))"/>';
                    } else {
                        iconHtml += '<div style="font-size:' + fontSize + ';line-height:1;filter:drop-shadow(0 2px 5px rgba(0,0,0,.8))">' + emoji + '</div>';
                    }
                    if (label) {
                        iconHtml += '<span style="font-size:' + lblSize + ';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.9);white-space:nowrap;letter-spacing:.03em">' + label + '</span>';
                    }
                    iconHtml += '</div>';

                    var divIcon = L.divIcon({
                        html:       iconHtml,
                        className:  'wcr-leaflet-obstacle',
                        iconAnchor: [iconSize / 2, iconSize / 2]
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
                        'z-index:900',
                        'display:flex',
                        'flex-direction:column',
                        'align-items:center',
                        'gap:4px',
                        'pointer-events:none'
                    ].join(';');
                    var iconDiv = document.createElement('div');
                    iconDiv.style.cssText = 'font-size:' + fontSize + ';line-height:1;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))';
                    if (ico) {
                        iconDiv.style.backgroundImage = 'url(' + ico + ')';
                        iconDiv.style.width  = iconSize + 'px';
                        iconDiv.style.height = iconSize + 'px';
                        iconDiv.style.backgroundSize = 'contain';
                    } else {
                        iconDiv.textContent = emoji;
                    }
                    d.appendChild(iconDiv);
                    if (label) {
                        var lblEl = document.createElement('span');
                        lblEl.textContent = label;
                        lblEl.style.cssText = 'font-size:' + lblSize + ';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.8);white-space:nowrap';
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
