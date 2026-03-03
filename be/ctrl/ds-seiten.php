<?php
/**
 * ctrl/ds-seiten.php — Digital Signage Screen-Vorschau
 * Portrait-Mode (9:16) für Story-Seiten, Instagram hinzugefügt.
 */
require_once __DIR__ . '/../inc/auth.php';
wcr_require('view_ds');

$PAGE_TITLE = 'DS-Seiten – Vorschau';

// portrait => true = 9:16 iFrame (1080x1920), false = 16:9 (1920x1080)
$seiten = [
    ['title' => 'Starter Pack',          'url' => 'https://wcr-webpage.de/starter-pack',          'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Wetter',                'url' => 'https://wcr-webpage.de/wetter',                 'icon' => '🌤', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Wind Map',              'url' => 'https://wcr-webpage.de/windmap',                'icon' => '💨', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Tickets / Cable',       'url' => 'https://wcr-webpage.de/tickets',                'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Öffnungszeiten Story',  'url' => 'https://wcr-webpage.de/oeffnungszeiten-story', 'icon' => '🕐', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => true],
    ['title' => 'Instagram Grid',        'url' => 'https://wcr-webpage.de/insta',                  'icon' => '📸', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => true],
    ['title' => 'Instagram Video',       'url' => 'https://wcr-webpage.de/instagram-video',        'icon' => '🎥', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => true],
    ['title' => 'Getränke',              'url' => 'https://wcr-webpage.de/getraenke',              'icon' => '🍺', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Softdrinks',            'url' => 'https://wcr-webpage.de/soft',                   'icon' => '🥤', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Speisekarte',           'url' => 'https://wcr-webpage.de/essen',                  'icon' => '🍔', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Kaffee',                'url' => 'https://wcr-webpage.de/kaffee',                 'icon' => '☕', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Obstacles',             'url' => 'https://wake-and-camp.de/obst/',                'icon' => '🤸', 'badge' => '500',   'badge_color' => '#ff3b30', 'portrait' => false],
    ['title' => 'Merchandise',           'url' => 'https://wcr-webpage.de/merchandise',            'icon' => '👕', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Stand Up Paddle',       'url' => 'https://wcr-webpage.de/sup',                   'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853', 'portrait' => false],
    ['title' => 'Kino',                  'url' => 'https://wcr-webpage.de/kino',                   'icon' => '🎬', 'badge' => '404',   'badge_color' => '#ff9500', 'portrait' => false],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .ds-gallery {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
      gap: 24px;
      padding: 0 0 40px;
    }
    /* Portrait-Karten etwas schmaler */
    .ds-card.is-portrait {
      grid-column: span 1;
      max-width: 380px;
    }

    .ds-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; transition:transform .2s,box-shadow .2s; }
    .ds-card:hover { transform:translateY(-3px); box-shadow:0 10px 32px rgba(0,0,0,.1); }

    .ds-card-header { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #e5e7eb; background:#f9fafb; }
    .ds-card-title  { display:flex; align-items:center; gap:8px; font-size:.95rem; font-weight:700; color:#111; }
    .ds-card-actions { display:flex; align-items:center; gap:8px; }

    .ds-badge { display:inline-flex; align-items:center; gap:5px; font-size:.68rem; font-weight:600; padding:3px 10px; border-radius:20px; border:1px solid transparent; text-transform:uppercase; letter-spacing:.5px; }
    .ds-dot   { width:6px; height:6px; border-radius:50%; animation:ds-blink 2s infinite; }
    @keyframes ds-blink { 0%,100%{opacity:1} 50%{opacity:.3} }

    .ds-btn { display:inline-flex; align-items:center; gap:5px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; color:#555; font-size:.8rem; padding:4px 10px; cursor:pointer; text-decoration:none; transition:background .15s,color .15s; white-space:nowrap; }
    .ds-btn:hover { background:#e5e7eb; color:#111; }
    .ds-btn.primary { background:#e8f5ff; border-color:#bdd9f5; color:#1a6fb5; }
    .ds-btn.primary:hover { background:#d0eaff; }

    /* ── 16:9 Landscape Frame ── */
    .ds-frame-wrap {
      position: relative;
      width: 100%;
      background: #f0f2f5;
      overflow: hidden;
    }
    .ds-frame-wrap iframe {
      display: block;
      position: absolute;
      top: 0; left: 0;
      border: none;
      opacity: 0;
      transition: opacity .4s;
      pointer-events: none;
      transform-origin: top left;
    }
    .ds-frame-wrap iframe.loaded { opacity: 1; }

    /* Landscape: iFrame ist 1920×1080 */
    .ds-frame-wrap.landscape iframe {
      width: 1920px;
      height: 1080px;
    }

    /* Portrait: iFrame ist 1080×1920 */
    .ds-frame-wrap.portrait iframe {
      width: 1080px;
      height: 1920px;
    }

    .ds-spin-wrap { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; font-size:.8rem; color:#9ca3af; z-index:2; background:#f0f2f5; transition:opacity .3s; }
    .ds-spin-wrap.hidden { opacity:0; pointer-events:none; }
    .ds-spinner { width:26px; height:26px; border:2px solid #e5e7eb; border-top-color:#3b82f6; border-radius:50%; animation:ds-spin .7s linear infinite; }
    @keyframes ds-spin { to{transform:rotate(360deg)} }

    .ds-card-footer { display:flex; align-items:center; justify-content:space-between; padding:8px 16px; border-top:1px solid #e5e7eb; background:#f9fafb; }
    .ds-url  { font-size:.68rem; color:#9ca3af; font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:65%; }
    .ds-time { font-size:.68rem; color:#9ca3af; }

    /* Portrait-Badge im Header */
    .ds-orient-tag { font-size:.62rem; font-weight:600; padding:2px 7px; border-radius:10px; background:#f3f0ff; color:#7c3aed; border:1px solid #ddd6fe; }
  </style>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🖥 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <button class="btn-upload" onclick="dsReloadAll()">&#8635; Alle neu laden</button>
</div>

<div class="ds-gallery">
  <?php foreach ($seiten as $i => $s):
    $portrait = !empty($s['portrait']);
  ?>
  <div class="ds-card <?= $portrait ? 'is-portrait' : '' ?>" id="ds-card-<?= $i ?>">

    <div class="ds-card-header">
      <div class="ds-card-title">
        <?= htmlspecialchars($s['icon']) ?>&nbsp;<?= htmlspecialchars($s['title']) ?>
        <?php if ($portrait): ?>
          <span class="ds-orient-tag">9:16</span>
        <?php endif; ?>
      </div>
      <div class="ds-card-actions">
        <span class="ds-badge" style="background:<?= htmlspecialchars($s['badge_color']) ?>22;color:<?= htmlspecialchars($s['badge_color']) ?>;border-color:<?= htmlspecialchars($s['badge_color']) ?>55;">
          <span class="ds-dot" style="background:<?= htmlspecialchars($s['badge_color']) ?>"></span>
          <?= htmlspecialchars($s['badge']) ?>
        </span>
        <button class="ds-btn" onclick="dsReload(<?= $i ?>)">&#8635; Reload</button>
        <a class="ds-btn primary" href="<?= htmlspecialchars($s['url']) ?>" target="_blank">↗ Öffnen</a>
      </div>
    </div>

    <div class="ds-frame-wrap <?= $portrait ? 'portrait' : 'landscape' ?>" id="ds-wrap-<?= $i ?>">
      <div class="ds-spin-wrap" id="ds-spin-<?= $i ?>">
        <div class="ds-spinner"></div><span>Lädt…</span>
      </div>
      <iframe id="ds-frame-<?= $i ?>"
              data-src="<?= htmlspecialchars($s['url']) ?>"
              data-portrait="<?= $portrait ? '1' : '0' ?>"
              scrolling="no"></iframe>
    </div>

    <div class="ds-card-footer">
      <span class="ds-url"><?= htmlspecialchars($s['url']) ?></span>
      <span class="ds-time" id="ds-time-<?= $i ?>">-</span>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
var dsStartTimes = {};

function dsScale() {
    document.querySelectorAll('.ds-frame-wrap').forEach(function(wrap) {
        var iframe  = wrap.querySelector('iframe');
        if (!iframe) return;
        var portrait = iframe.dataset.portrait === '1';
        var nativeW  = portrait ? 1080 : 1920;
        var nativeH  = portrait ? 1920 : 1080;
        var scale    = wrap.offsetWidth / nativeW;
        iframe.style.transform = 'scale(' + scale + ')';
        wrap.style.height = Math.round(nativeH * scale) + 'px';
    });
}

function dsLoaded(idx) {
    var frame = document.getElementById('ds-frame-' + idx);
    var spin  = document.getElementById('ds-spin-'  + idx);
    var time  = document.getElementById('ds-time-'  + idx);
    if (!frame || !spin || !time) return;
    frame.classList.add('loaded');
    spin.classList.add('hidden');
    if (dsStartTimes[idx]) {
        var ms = Date.now() - dsStartTimes[idx];
        time.textContent = '✓ ' + (ms / 1000).toFixed(1) + 's';
        time.style.color = ms < 2000 ? '#16a34a' : ms < 5000 ? '#d97706' : '#dc2626';
    }
    dsScale();
}

function dsReload(idx) {
    var frame = document.getElementById('ds-frame-' + idx);
    var spin  = document.getElementById('ds-spin-'  + idx);
    var time  = document.getElementById('ds-time-'  + idx);
    if (!frame) return;
    frame.classList.remove('loaded');
    if (spin) { spin.classList.remove('hidden'); spin.innerHTML = '<div class="ds-spinner"></div><span>Lädt…</span>'; }
    if (time) { time.textContent = '-'; time.style.color = ''; }
    dsStartTimes[idx] = Date.now();
    frame.onload = function() { dsLoaded(idx); };
    frame.src = frame.dataset.src + '?t=' + Date.now();
}

function dsReloadAll() {
    for (var i = 0; i < <?= count($seiten) ?>; i++) dsReload(i);
}

function dsInitFrames() {
    document.querySelectorAll('.ds-frame-wrap iframe').forEach(function(frame) {
        var idx = parseInt(frame.id.replace('ds-frame-', ''), 10);
        dsStartTimes[idx] = Date.now();
        frame.onload = function() { dsLoaded(idx); };
        frame.src = frame.dataset.src;
    });
}

window.addEventListener('resize', dsScale);
document.addEventListener('DOMContentLoaded', function() {
    dsScale();
    setTimeout(dsInitFrames, 100);
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
