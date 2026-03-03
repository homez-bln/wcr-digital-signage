<?php
/**
 * ctrl/ds-seiten.php — Digital Signage Screen-Vorschau
 * Gruppen-Sortierung + Portrait 9:16 / Landscape 16:9 iFrame
 * fix: ResizeObserver statt einmaliges dsScale() beim Init
 */
require_once __DIR__ . '/../inc/auth.php';
wcr_require('view_ds');

$PAGE_TITLE = 'DS-Seiten – Vorschau';

$gruppen = [
    [
        'label' => '📸 Social Media',
        'seiten' => [
            ['title' => 'Instagram Grid',       'url' => 'https://wcr-webpage.de/insta',       'icon' => '📸', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => true],
            ['title' => 'Instagram Reels',      'url' => 'https://wcr-webpage.de/insta-reel',  'icon' => '🎥', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => true],
        ],
    ],
    [
        'label' => '🕐 Info & Status',
        'seiten' => [
            ['title' => 'Starter Pack',         'url' => 'https://wcr-webpage.de/starter-pack',          'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Öffnungszeiten Story', 'url' => 'https://wcr-webpage.de/oeffnungszeiten-story', 'icon' => '🕐', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => true],
            ['title' => 'Wetter',               'url' => 'https://wcr-webpage.de/wetter',                 'icon' => '🌤', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Wind Map',             'url' => 'https://wcr-webpage.de/windmap',                'icon' => '💨', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
        ],
    ],
    [
        'label' => '🏄 Aktivitäten',
        'seiten' => [
            ['title' => 'Tickets / Cable',      'url' => 'https://wcr-webpage.de/tickets',       'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Stand Up Paddle',      'url' => 'https://wcr-webpage.de/sup',           'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Obstacles',            'url' => 'https://wake-and-camp.de/obst/',       'icon' => '🤸', 'badge' => '500',   'badge_color' => '#ff3b30', 'portrait' => false],
            ['title' => 'Kino',                 'url' => 'https://wcr-webpage.de/kino',          'icon' => '🎬', 'badge' => '404',   'badge_color' => '#ff9500', 'portrait' => false],
        ],
    ],
    [
        'label' => '🍺 Speisen & Getränke',
        'seiten' => [
            ['title' => 'Speisekarte',          'url' => 'https://wcr-webpage.de/essen',        'icon' => '🍔', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Getränke',             'url' => 'https://wcr-webpage.de/getraenke',   'icon' => '🍺', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Softdrinks',           'url' => 'https://wcr-webpage.de/soft',         'icon' => '🥤', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
            ['title' => 'Kaffee',               'url' => 'https://wcr-webpage.de/kaffee',       'icon' => '☕',      'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
        ],
    ],
    [
        'label' => '👕 Shop',
        'seiten' => [
            ['title' => 'Merchandise',          'url' => 'https://wcr-webpage.de/merchandise',  'icon' => '👕', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
        ],
    ],
];

// Flache Liste mit globalen Indizes für JS
$alle_seiten = [];
foreach ($gruppen as &$g) {
    foreach ($g['seiten'] as &$s) {
        $s['_idx'] = count($alle_seiten);
        $alle_seiten[] = &$s;
    }
}
unset($g, $s);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .ds-group { margin-bottom: 36px; }
    .ds-group-label {
      font-size: .8rem; font-weight: 800; text-transform: uppercase;
      letter-spacing: 1.5px; color: #6b7280;
      padding: 0 0 10px; border-bottom: 2px solid #e5e7eb; margin-bottom: 16px;
      display: flex; align-items: center; gap: 8px;
    }
    .ds-group-label span.cnt {
      font-size: .7rem; font-weight: 600;
      background: #f3f4f6; color: #9ca3af;
      border: 1px solid #e5e7eb; border-radius: 20px;
      padding: 1px 8px; letter-spacing: 0; text-transform: none;
    }

    /* Grid: Portrait-Karten bekommen min 220px, Landscape min 420px */
    .ds-gallery {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
      gap: 20px;
      align-items: start;
    }
    .ds-card.is-portrait {
      /* Portrait-Karten nehmen natürlich weniger Breite —
         wir setzen max-width damit das Grid sie nicht
         auf volle Track-Breite streckt */
      max-width: 300px;
    }

    .ds-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; transition:transform .2s,box-shadow .2s; }
    .ds-card:hover { transform:translateY(-3px); box-shadow:0 10px 32px rgba(0,0,0,.1); }
    .ds-card-header { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #e5e7eb; background:#f9fafb; gap: 8px; }
    .ds-card-title  { display:flex; align-items:center; gap:8px; font-size:.9rem; font-weight:700; color:#111; min-width:0; }
    .ds-card-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }
    .ds-badge { display:inline-flex; align-items:center; gap:5px; font-size:.68rem; font-weight:600; padding:3px 10px; border-radius:20px; border:1px solid transparent; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
    .ds-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; animation:ds-blink 2s infinite; }
    @keyframes ds-blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .ds-btn { display:inline-flex; align-items:center; gap:4px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; color:#555; font-size:.78rem; padding:4px 9px; cursor:pointer; text-decoration:none; transition:background .15s,color .15s; white-space:nowrap; }
    .ds-btn:hover { background:#e5e7eb; color:#111; }
    .ds-btn.primary { background:#e8f5ff; border-color:#bdd9f5; color:#1a6fb5; }
    .ds-btn.primary:hover { background:#d0eaff; }
    .ds-orient-tag { font-size:.58rem; font-weight:700; padding:2px 6px; border-radius:8px; background:#f3f0ff; color:#7c3aed; border:1px solid #ddd6fe; white-space:nowrap; flex-shrink:0; }

    /* ── iFrame-Wrapper ──
       Höhe wird per JS gesetzt (ResizeObserver).
       iframe ist absolut positioniert und wird per scale() skaliert. */
    .ds-frame-wrap {
      position: relative;
      width: 100%;
      background: #111;
      overflow: hidden;
      /* Höhe wird durch JS gesetzt — Fallback damit kein 0px */
      min-height: 60px;
    }
    .ds-frame-wrap iframe {
      display: block;
      position: absolute;
      top: 0; left: 0;
      border: none;
      opacity: 0;
      transition: opacity .5s;
      pointer-events: none;
      transform-origin: top left;
      /* Größe = native Auflösung, wird per JS gesetzt */
    }
    .ds-frame-wrap iframe.loaded { opacity: 1; }

    .ds-spin-wrap { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; font-size:.8rem; color:#9ca3af; z-index:2; background:#1a1a2e; transition:opacity .3s; }
    .ds-spin-wrap.hidden { opacity:0; pointer-events:none; }
    .ds-spinner { width:28px; height:28px; border:2.5px solid rgba(255,255,255,.12); border-top-color:#3b82f6; border-radius:50%; animation:ds-spin .75s linear infinite; }
    @keyframes ds-spin { to{transform:rotate(360deg)} }

    .ds-card-footer { display:flex; align-items:center; justify-content:space-between; padding:8px 16px; border-top:1px solid #e5e7eb; background:#f9fafb; }
    .ds-url  { font-size:.65rem; color:#9ca3af; font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:65%; }
    .ds-time { font-size:.65rem; color:#9ca3af; white-space:nowrap; }
  </style>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🖥 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <button class="btn-upload" onclick="dsReloadAll()">↺ Alle neu laden</button>
</div>

<?php foreach ($gruppen as $g): ?>
<div class="ds-group">
  <div class="ds-group-label">
    <?= htmlspecialchars($g['label']) ?>
    <span class="cnt"><?= count($g['seiten']) ?></span>
  </div>
  <div class="ds-gallery">
    <?php foreach ($g['seiten'] as $s):
      $i        = $s['_idx'];
      $portrait = !empty($s['portrait']);
      $nW       = $portrait ? 1080 : 1920;
      $nH       = $portrait ? 1920 : 1080;
    ?>
    <div class="ds-card <?= $portrait ? 'is-portrait' : '' ?>" id="ds-card-<?= $i ?>">

      <div class="ds-card-header">
        <div class="ds-card-title">
          <span><?= htmlspecialchars($s['icon']) ?></span>
          <span><?= htmlspecialchars($s['title']) ?></span>
          <?php if ($portrait): ?><span class="ds-orient-tag">9:16</span><?php endif; ?>
        </div>
        <div class="ds-card-actions">
          <span class="ds-badge" style="background:<?= htmlspecialchars($s['badge_color']) ?>22;color:<?= htmlspecialchars($s['badge_color']) ?>;border-color:<?= htmlspecialchars($s['badge_color']) ?>55;">
            <span class="ds-dot" style="background:<?= htmlspecialchars($s['badge_color']) ?>"></span>
            <?= htmlspecialchars($s['badge']) ?>
          </span>
          <button class="ds-btn" onclick="dsReload(<?= $i ?>)">↺</button>
          <a class="ds-btn primary" href="<?= htmlspecialchars($s['url']) ?>" target="_blank">↗ Öffnen</a>
        </div>
      </div>

      <div class="ds-frame-wrap" id="ds-wrap-<?= $i ?>">
        <div class="ds-spin-wrap" id="ds-spin-<?= $i ?>">
          <div class="ds-spinner"></div>
          <span style="color:#555;font-size:.75rem">Lädt…</span>
        </div>
        <iframe
          id="ds-frame-<?= $i ?>"
          data-src="<?= htmlspecialchars($s['url']) ?>"
          data-nw="<?= $nW ?>"
          data-nh="<?= $nH ?>"
          scrolling="no"
          style="width:<?= $nW ?>px;height:<?= $nH ?>px;"
        ></iframe>
      </div>

      <div class="ds-card-footer">
        <span class="ds-url"><?= htmlspecialchars($s['url']) ?></span>
        <span class="ds-time" id="ds-time-<?= $i ?>">-</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<script>
var dsStartTimes = {};

// Skaliert einen einzelnen Wrap auf Basis seiner aktuellen Breite
function dsScaleWrap(wrap) {
    var iframe = wrap.querySelector('iframe');
    if (!iframe) return;
    var nW    = parseInt(iframe.dataset.nw, 10) || 1920;
    var nH    = parseInt(iframe.dataset.nh, 10) || 1080;
    var scale = wrap.offsetWidth / nW;
    iframe.style.transform = 'scale(' + scale + ')';
    wrap.style.height      = Math.round(nH * scale) + 'px';
}

// ResizeObserver: reagiert auf jede Größenveränderung des Wrappers
var ro = new ResizeObserver(function(entries) {
    entries.forEach(function(e) { dsScaleWrap(e.target); });
});

function dsLoaded(idx) {
    var frame = document.getElementById('ds-frame-' + idx);
    var spin  = document.getElementById('ds-spin-'  + idx);
    var time  = document.getElementById('ds-time-'  + idx);
    if (!frame || !spin) return;
    frame.classList.add('loaded');
    spin.classList.add('hidden');
    if (time && dsStartTimes[idx]) {
        var ms = Date.now() - dsStartTimes[idx];
        time.textContent = '✓ ' + (ms / 1000).toFixed(1) + 's';
        time.style.color  = ms < 2000 ? '#16a34a' : ms < 5000 ? '#d97706' : '#dc2626';
    }
    // Nochmal skalieren nachdem iFrame geladen
    var wrap = document.getElementById('ds-wrap-' + idx);
    if (wrap) dsScaleWrap(wrap);
}

function dsReload(idx) {
    var frame = document.getElementById('ds-frame-' + idx);
    var spin  = document.getElementById('ds-spin-'  + idx);
    var time  = document.getElementById('ds-time-'  + idx);
    if (!frame) return;
    frame.classList.remove('loaded');
    if (spin) { spin.classList.remove('hidden'); spin.innerHTML = '<div class="ds-spinner"></div><span style="color:#555;font-size:.75rem">Lädt…</span>'; }
    if (time) { time.textContent = '-'; time.style.color = ''; }
    dsStartTimes[idx] = Date.now();
    frame.onload = function() { dsLoaded(idx); };
    frame.src = frame.dataset.src + '?t=' + Date.now();
}

function dsReloadAll() {
    for (var i = 0; i < <?= count($alle_seiten) ?>; i++) dsReload(i);
}

document.addEventListener('DOMContentLoaded', function() {
    // ResizeObserver auf alle Wraps registrieren
    document.querySelectorAll('.ds-frame-wrap').forEach(function(wrap) {
        ro.observe(wrap);
        dsScaleWrap(wrap); // sofort einmal skalieren
    });

    // iFrames mit kleinem Delay laden (Layout muss stabil sein)
    setTimeout(function() {
        document.querySelectorAll('.ds-frame-wrap iframe').forEach(function(frame) {
            var idx = parseInt(frame.id.replace('ds-frame-', ''), 10);
            dsStartTimes[idx] = Date.now();
            frame.onload = function() { dsLoaded(idx); };
            frame.src = frame.dataset.src;
        });
    }, 200);
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
