<?php
/**
 * ctrl/obstacles.php — Obstacles-Verwaltung + Karten-Einstellungen
 */

$PAGE_TITLE = 'Obstacles';

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_media');

$db = $pdo;

// Tabelle erstellen
$db->exec("CREATE TABLE IF NOT EXISTS obstacles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50)  NOT NULL,
    icon_url VARCHAR(500) NULL,
    pos_x DECIMAL(6,3) NOT NULL DEFAULT 0,
    pos_y DECIMAL(6,3) NOT NULL DEFAULT 0,
    rotation DECIMAL(6,2) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// lat/lon Spalten nachträglich hinzufügen — MySQL 5.7 kompatibel (kein IF NOT EXISTS)
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
    AND TABLE_NAME = 'obstacles'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('lat', $cols)) {
    $pdo->exec("ALTER TABLE obstacles ADD COLUMN lat DECIMAL(10,7) NULL DEFAULT NULL");
}
if (!in_array('lon', $cols)) {
    $pdo->exec("ALTER TABLE obstacles ADD COLUMN lon DECIMAL(10,7) NULL DEFAULT NULL");
}

define('OBS_WP_API',  'https://wcr-webpage.de/wp-json/wakecamp/v1/obstacles/map-config');
define('OBS_SECRET',  'WCR_DS_2026');

function obs_curl(string $url, ?array $postData = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ];
    if ($postData !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($postData);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [
        'ok'   => ($code === 200 && !$err),
        'code' => $code,
        'json' => json_decode($body ?: '', true),
        'err'  => $err ?: ($code !== 200 ? "HTTP $code" : ''),
        'body' => $body,
    ];
}

$STYLE_OPTIONS = [
    'voyager-nolabels'  => ['label' => '🗺️ OSM (clean)',    'preview_bg' => '#f2ede4'],
    'satellite'         => ['label' => '🛰️ Satellite',      'preview_bg' => '#2c3e2d'],
    'dark'              => ['label' => '🌑 Dark',            'preview_bg' => '#1a1a2e'],
    'light'             => ['label' => '☀️ Light',           'preview_bg' => '#f8f8f8'],
    'satellite-labels'  => ['label' => '🛰️ Sat + Labels',   'preview_bg' => '#2c3e2d'],
];

// ── Mode (Portrait/Landscape) ──
$mode = $_POST['mode'] ?? ($_GET['mode'] ?? 'landscape');
$mode = strtolower(trim((string)$mode));
if (!in_array($mode, ['landscape', 'portrait'], true)) $mode = 'landscape';
$modeLabel = ($mode === 'portrait') ? 'Portrait (1080×1920)' : 'Landscape (1920×1080)';

// ── Aktuelle Map-Config laden ──
$mapCfg = ['lat' => 52.821428, 'lon' => 13.577100, 'zoom' => 17.9, 'rot' => 0, 'style' => 'voyager-nolabels'];
$r = obs_curl(OBS_WP_API . '?mode=' . rawurlencode($mode));
if ($r['ok'] && is_array($r['json']) && isset($r['json']['lat'])) {
    $mapCfg = array_merge($mapCfg, $r['json']);
}
$currentStyle = $mapCfg['style'] ?? 'voyager-nolabels';
if (!isset($STYLE_OPTIONS[$currentStyle])) $currentStyle = 'voyager-nolabels';

// ── Map-Config speichern (POST) ──
$cfgMsg  = '';
$cfgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map_config'])) {
    $lat   = (float)str_replace(',', '.', $_POST['map_lat']   ?? '');
    $lon   = (float)str_replace(',', '.', $_POST['map_lon']   ?? '');
    $zoom  = (float)str_replace(',', '.', $_POST['map_zoom']  ?? '');
    $rot   = (float)str_replace(',', '.', $_POST['map_rot']   ?? '0');
    $style = $_POST['map_style'] ?? 'voyager-nolabels';
    if (!isset($STYLE_OPTIONS[$style])) $style = 'voyager-nolabels';

    $r2 = obs_curl(OBS_WP_API, [
        'mode'       => $mode,
        'lat'        => $lat,
        'lon'        => $lon,
        'zoom'       => $zoom,
        'rot'        => $rot,
        'style'      => $style,
        'wcr_secret' => OBS_SECRET,
    ]);

    if ($r2['ok'] && !empty($r2['json']['ok'])) {
        $cfgMsg       = '✓ Gespeichert — ' . $mode . ' · zoom ' . number_format($zoom, 1) . ' · rot ' . number_format($rot, 1) . '° · Style: ' . htmlspecialchars($STYLE_OPTIONS[$style]['label']);
        $cfgType      = 'ok';
        $mapCfg       = ['lat' => $lat, 'lon' => $lon, 'zoom' => $zoom, 'rot' => $rot, 'style' => $style];
        $currentStyle = $style;
    } else {
        $debug   = $r2['body'] ? ' — ' . htmlspecialchars(substr($r2['body'], 0, 120)) : '';
        $cfgMsg  = '✗ Fehler: ' . ($r2['err'] ?: 'Unbekannt') . $debug;
        $cfgType = 'error';
    }
}

// ── Obstacles speichern (POST) ──
$saveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_obstacles'])) {
    $ids     = $_POST['id'] ?? [];
    $names   = $_POST['name'] ?? [];
    $types   = $_POST['type'] ?? [];
    $icons   = $_POST['icon_url'] ?? [];
    $posXs   = $_POST['pos_x'] ?? [];
    $posYs   = $_POST['pos_y'] ?? [];
    $rots    = $_POST['rotation'] ?? [];
    $actives = $_POST['active'] ?? [];

    $stmtIns = $db->prepare("INSERT INTO obstacles
        (id, name, type, icon_url, pos_x, pos_y, rotation, active)
        VALUES (:id, :name, :type, :icon_url, :pos_x, :pos_y, :rotation, :active)
        ON DUPLICATE KEY UPDATE
            name     = VALUES(name),
            type     = VALUES(type),
            icon_url = VALUES(icon_url),
            pos_x    = VALUES(pos_x),
            pos_y    = VALUES(pos_y),
            rotation = VALUES(rotation),
            active   = VALUES(active)");

    $count = 0;
    foreach ($names as $idx => $n) {
        $n = trim((string)$n);
        $t = trim((string)($types[$idx] ?? ''));
        if ($n === '' && $t === '') continue;
        $id     = (int)($ids[$idx] ?? 0);
        $icon   = trim((string)($icons[$idx] ?? ''));
        $x      = (float)str_replace(',', '.', (string)($posXs[$idx] ?? 0));
        $y      = (float)str_replace(',', '.', (string)($posYs[$idx] ?? 0));
        $rot    = (float)str_replace(',', '.', (string)($rots[$idx] ?? 0));
        if ($x < 0) $x = 0; if ($x > 100) $x = 100;
        if ($y < 0) $y = 0; if ($y > 100) $y = 100;
        $active = isset($actives[$idx]) ? 1 : 0;
        $stmtIns->execute([
            ':id'       => $id ?: null,
            ':name'     => $n,
            ':type'     => ($t !== '' ? $t : 'default'),
            ':icon_url' => $icon,
            ':pos_x'    => $x,
            ':pos_y'    => $y,
            ':rotation' => $rot,
            ':active'   => $active,
        ]);
        $count++;
    }
    $loc = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $loc . '?saved=' . $count . '&mode=' . rawurlencode($mode));
    exit;
}

if (isset($_GET['saved'])) {
    $saveMsg = '✓ ' . (int)$_GET['saved'] . ' Obstacle(s) gespeichert';
}

$rows    = $db->query("SELECT * FROM obstacles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$maxRows = max(20, count($rows) + 3);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../inc/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@10.6.1/ol.css">
  <style>
    .mcp-card {
      background:#fff; border-radius:14px; box-shadow:0 8px 20px rgba(0,0,0,.05);
      padding:22px 24px; margin-bottom:28px; max-width:1180px;
    }
    .mcp-card h2 { margin:0 0 4px; font-size:16px; font-weight:700; }
    .mcp-card .sub { font-size:12px; color:#86868b; margin-bottom:18px; }
    .mcp-grid { display:grid; grid-template-columns:320px 1fr; gap:20px; align-items:start; }
    .mcp-sliders { display:flex; flex-direction:column; gap:16px; }
    .sl-row label { display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#1d1d1f; margin-bottom:5px; }
    .sl-row label span { font-family:monospace; font-size:12px; background:#f0f4ff; color:#0057d9; padding:1px 8px; border-radius:20px; }
    .sl-row input[type=range] { width:100%; height:5px; -webkit-appearance:none; appearance:none; border-radius:4px; outline:none; cursor:pointer; }
    .sl-row input[type=range]::-webkit-slider-thumb { -webkit-appearance:none; width:16px; height:16px; border-radius:50%; background:#0071e3; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.2); cursor:pointer; }
    .sl-hint { font-size:10px; color:#aeaeb2; margin-top:3px; }
    #mcp-map {
      width:100%; height:320px; border-radius:10px; border:1px solid #e5e5ea; overflow:hidden;
      position:relative; background:#f2ede4;
    }
    .mcp-crosshair {
      position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
      width:22px; height:22px; pointer-events:none; z-index:10;
    }
    .style-switcher { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
    .style-btn {
      display:flex; align-items:center; gap:6px;
      padding:6px 14px; border-radius:20px;
      border:2px solid #e5e7eb; background:#f9fafb;
      font-size:12px; font-weight:600; color:#374151;
      cursor:pointer; transition:all .15s; white-space:nowrap;
    }
    .style-btn:hover { border-color:#bdd9f5; background:#e8f5ff; color:#1a6fb5; }
    .style-btn.active { border-color:#0071e3; background:#e8f5ff; color:#0057d9; }
    .style-btn .style-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .mcp-actions { display:flex; gap:10px; margin-top:16px; }
    .mcp-hint-bar { font-size:11px; color:#86868b; margin-top:8px; display:flex; align-items:center; gap:6px; }
    #mcp-coords { font-family:monospace; font-size:11px; color:#0057d9; background:#f0f4ff; padding:2px 8px; border-radius:12px; }
    .cfg-msg { font-size:12px; margin-top:10px; padding:5px 10px; border-radius:6px; }
    .cfg-msg.ok    { background:rgba(52,199,89,.10); color:#1a7a30; border:1px solid rgba(52,199,89,.25); }
    .cfg-msg.error { background:rgba(255,59,48,.08); color:#c0392b; border:1px solid rgba(255,59,48,.2); word-break:break-all; }
    .obs-wrapper { max-width:1180px; }
    .obs-table { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 20px rgba(0,0,0,.04); }
    .obs-table th, .obs-table td { padding:6px 8px; border-bottom:1px solid #f0f0f3; text-align:left; vertical-align:middle; }
    .obs-table th { background:#f5f5f7; font-weight:600; font-size:12px; color:#6e6e73; }
    .obs-table tr:last-child td { border-bottom:none; }
    .obs-table input[type="text"], .obs-table input[type="number"] { width:100%; box-sizing:border-box; padding:4px 6px; border-radius:6px; border:1px solid #d2d2d7; font-size:12px; }
    .obs-table input[type="number"] { text-align:right; }
    .obs-id { width:40px; color:#9f9fa5; font-size:11px; }
    .obs-active { text-align:center; width:60px; }
    .obs-save-bar { margin-top:14px; display:flex; align-items:center; gap:12px; }
    .btn-primary   { padding:7px 16px; border-radius:999px; border:none; background:#0071e3; color:#fff; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-secondary { padding:7px 16px; border-radius:999px; border:1px solid #d2d2d7; background:#fff; color:#1d1d1f; font-size:13px; cursor:pointer; }
    .btn-secondary.is-active { background:#f0f4ff; border-color:#bfd7ff; color:#0057d9; }
    .obs-msg  { font-size:12px; color:#1d1d1f; margin-bottom:10px; }
    .obs-hint { font-size:11px; color:#6e6e73; margin-top:4px; }
  </style>
</head>
<body class="bo">

<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="obs-wrapper" style="padding:24px 20px;">

  <div class="header-controls">
    <h1>🏄 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  </div>

  <!-- SEKTION 1: KARTEN-CONFIG -->
  <div class="mcp-card">
    <h2>🗺️ Karten-Einstellungen</h2>
    <p class="sub">Aktuell: <strong><?= htmlspecialchars($modeLabel) ?></strong></p>

    <div class="mcp-actions" style="margin-top:0;margin-bottom:16px;">
      <?php $self = strtok($_SERVER['REQUEST_URI'], '?'); ?>
      <a class="btn-secondary <?= $mode === 'landscape' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($self) ?>?mode=landscape">Landscape</a>
      <a class="btn-secondary <?= $mode === 'portrait'  ? 'is-active' : '' ?>" href="<?= htmlspecialchars($self) ?>?mode=portrait">Portrait</a>
    </div>

    <form method="POST">
      <input type="hidden" name="save_map_config" value="1">
      <input type="hidden" name="mode" value="<?= hv($mode) ?>">
      <input type="hidden" id="map_style" name="map_style" value="<?= hv($currentStyle) ?>">

      <div style="margin-bottom:16px;">
        <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">🎨 Kartenstil</div>
        <div class="style-switcher">
          <?php
          $styleDots = [
            'voyager-nolabels' => '#e8dcc8',
            'satellite'        => '#4a6741',
            'dark'             => '#1a1a2e',
            'light'            => '#f0f0f0',
            'satellite-labels' => '#3d6b50',
          ];
          foreach ($STYLE_OPTIONS as $key => $opt): ?>
          <button type="button"
            class="style-btn <?= $key === $currentStyle ? 'active' : '' ?>"
            data-style="<?= hv($key) ?>"
            onclick="setMapStyle('<?= hv($key) ?>')">
            <span class="style-dot" style="background:<?= hv($styleDots[$key] ?? '#ccc') ?>"></span>
            <?= htmlspecialchars($opt['label']) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mcp-grid">
        <div class="mcp-sliders">

          <div class="sl-row">
            <label>🔍 Zoom <span id="lbl-zoom"><?= number_format((float)$mapCfg['zoom'], 1) ?></span></label>
            <input type="range" id="sl-zoom" min="10" max="21" step="0.1" value="<?= hv($mapCfg['zoom']) ?>">
            <input type="hidden" id="map_zoom" name="map_zoom" value="<?= hv($mapCfg['zoom']) ?>">
            <div class="sl-hint">Empfohlen: 16–19 · Standard: 17.9</div>
          </div>

          <div class="sl-row">
            <label>📍 Latitude <span id="lbl-lat"><?= hv($mapCfg['lat']) ?></span></label>
            <input type="range" id="sl-lat" min="52.75" max="52.90" step="0.0001" value="<?= hv($mapCfg['lat']) ?>">
            <input type="hidden" id="map_lat" name="map_lat" value="<?= hv($mapCfg['lat']) ?>">
            <div class="sl-hint">Nord–Süd</div>
          </div>

          <div class="sl-row">
            <label>📍 Longitude <span id="lbl-lon"><?= hv($mapCfg['lon']) ?></span></label>
            <input type="range" id="sl-lon" min="13.50" max="13.65" step="0.0001" value="<?= hv($mapCfg['lon']) ?>">
            <input type="hidden" id="map_lon" name="map_lon" value="<?= hv($mapCfg['lon']) ?>">
            <div class="sl-hint">West–Ost</div>
          </div>

          <div class="sl-row">
            <label>🧭 Rotation <span id="lbl-rot"><?= number_format((float)($mapCfg['rot'] ?? 0), 1) ?>°</span></label>
            <input type="range" id="sl-rot" min="-180" max="180" step="0.5" value="<?= hv((float)($mapCfg['rot'] ?? 0)) ?>">
            <input type="hidden" id="map_rot" name="map_rot" value="<?= hv((float)($mapCfg['rot'] ?? 0)) ?>">
            <div class="sl-hint">Alt+Shift+Drag geht auch (OpenLayers)</div>
          </div>

          <div class="mcp-actions">
            <button type="button" id="btn-mcp-reset" class="btn-secondary">↩ Standard</button>
            <button type="submit" class="btn-primary" style="flex:1;">💾 Karten-Config speichern</button>
          </div>
        </div>

        <div>
          <div id="mcp-map">
            <div class="mcp-crosshair" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 22 22">
                <line x1="11" y1="0" x2="11" y2="22" stroke="#0071e3" stroke-width="2"/>
                <line x1="0" y1="11" x2="22" y2="11" stroke="#0071e3" stroke-width="2"/>
                <circle cx="11" cy="11" r="3" fill="#0071e3"/>
              </svg>
            </div>
          </div>
          <div class="mcp-hint-bar">Klick auf Karte = neues Zentrum &nbsp;·&nbsp; <span id="mcp-coords"><?= hv($mapCfg['lat']) ?>, <?= hv($mapCfg['lon']) ?> · zoom <?= hv($mapCfg['zoom']) ?> · rot <?= hv((float)($mapCfg['rot'] ?? 0)) ?>°</span></div>
        </div>
      </div>

      <?php if ($cfgMsg): ?>
        <div class="cfg-msg <?= $cfgType ?>"><?= $cfgMsg ?></div>
      <?php endif; ?>

    </form>
  </div>

  <!-- SEKTION 2: OBSTACLES-TABELLE -->
  <p class="obs-hint">Verwalte bis zu 20 Obstacles. Position in Prozent (0–100) bezogen auf die Hintergrundkarte.</p>

  <?php if ($saveMsg): ?>
    <div class="obs-msg"><?= htmlspecialchars($saveMsg) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="save_obstacles" value="1">
    <input type="hidden" name="mode" value="<?= hv($mode) ?>">
    <table class="obs-table">
      <thead>
        <tr>
          <th class="obs-id">ID</th><th>Name</th><th>Typ</th>
          <th style="width:90px;">Pos X %</th><th style="width:90px;">Pos Y %</th>
          <th style="width:80px;">Rotation</th><th>Icon‑URL (Top‑View)</th>
          <th class="obs-active">Aktiv</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($rows as $row): $i++; ?>
        <tr>
          <td class="obs-id"><input type="hidden" name="id[]" value="<?= (int)$row['id'] ?>">#<?= (int)$row['id'] ?></td>
          <td><input type="text"   name="name[]"     value="<?= htmlspecialchars($row['name']) ?>"></td>
          <td><input type="text"   name="type[]"     value="<?= htmlspecialchars($row['type']) ?>"></td>
          <td><input type="number" name="pos_x[]"    value="<?= htmlspecialchars($row['pos_x']) ?>"    min="0" max="100" step="0.1"></td>
          <td><input type="number" name="pos_y[]"    value="<?= htmlspecialchars($row['pos_y']) ?>"    min="0" max="100" step="0.1"></td>
          <td><input type="number" name="rotation[]" value="<?= htmlspecialchars($row['rotation']) ?>" step="1"></td>
          <td><input type="text"   name="icon_url[]" value="<?= htmlspecialchars($row['icon_url']) ?>"></td>
          <td class="obs-active"><input type="checkbox" name="active[<?= $i-1 ?>]" value="1" <?= $row['active'] ? 'checked' : '' ?>></td>
        </tr>
        <?php endforeach; ?>
        <?php for (; $i < $maxRows; $i++): ?>
        <tr>
          <td class="obs-id"><input type="hidden" name="id[]" value="0">#neu</td>
          <td><input type="text"   name="name[]"     value=""></td>
          <td><input type="text"   name="type[]"     value=""></td>
          <td><input type="number" name="pos_x[]"    value=""  min="0" max="100" step="0.1"></td>
          <td><input type="number" name="pos_y[]"    value=""  min="0" max="100" step="0.1"></td>
          <td><input type="number" name="rotation[]" value="0" step="1"></td>
          <td><input type="text"   name="icon_url[]" value=""></td>
          <td class="obs-active"><input type="checkbox" name="active[<?= $i ?>]" value="1" checked></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <div class="obs-save-bar">
      <button type="submit" class="btn-primary">Obstacles speichern</button>
    </div>
  </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/ol@10.6.1/dist/ol.js"></script>
<script>
(function () {
    var DEF = { lat: 52.821428, lon: 13.577100, zoom: 17.9, rot: 0 };

    var TILE_URLS = {
        'voyager-nolabels': ['https://a.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
                             'https://b.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
                             'https://c.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png'],
        'satellite':        ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
        'dark':             ['https://a.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}.png',
                             'https://b.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}.png',
                             'https://c.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}.png'],
        'light':            ['https://a.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}.png',
                             'https://b.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}.png',
                             'https://c.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}.png'],
        'satellite-labels': ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}']
    };

    var slZ    = document.getElementById('sl-zoom');
    var slLat  = document.getElementById('sl-lat');
    var slLon  = document.getElementById('sl-lon');
    var slRot  = document.getElementById('sl-rot');
    var lblZ   = document.getElementById('lbl-zoom');
    var lblLt  = document.getElementById('lbl-lat');
    var lblLn  = document.getElementById('lbl-lon');
    var lblR   = document.getElementById('lbl-rot');
    var cords  = document.getElementById('mcp-coords');
    var hZ     = document.getElementById('map_zoom');
    var hLat   = document.getElementById('map_lat');
    var hLon   = document.getElementById('map_lon');
    var hRot   = document.getElementById('map_rot');
    var hStyle = document.getElementById('map_style');

    function deg2rad(d){ return d * Math.PI / 180; }
    function rad2deg(r){ return r * 180 / Math.PI; }
    function grad(sl){
        var p=(sl.value-sl.min)/(sl.max-sl.min)*100;
        sl.style.background='linear-gradient(90deg,#0071e3 '+p+'%,#e5e5ea '+p+'%)';
    }

    var currentStyleKey = hStyle.value || 'voyager-nolabels';

    var view = new ol.View({
        center:   ol.proj.fromLonLat([parseFloat(slLon.value), parseFloat(slLat.value)]),
        zoom:     parseFloat(slZ.value),
        rotation: deg2rad(parseFloat(slRot.value || '0'))
    });

    var tileSource = new ol.source.XYZ({
        urls:    TILE_URLS[currentStyleKey] || TILE_URLS['voyager-nolabels'],
        maxZoom: 21
    });
    var tileLayer = new ol.layer.Tile({
        preload:                Infinity,
        updateWhileAnimating:   true,
        updateWhileInteracting: true,
        source:                 tileSource
    });

    var map = new ol.Map({
        target:   'mcp-map',
        layers:   [tileLayer],
        view:     view,
        controls: [new ol.control.Zoom(), new ol.control.Rotate()]
    });

    window.setMapStyle = function(key) {
        var urls = TILE_URLS[key] || TILE_URLS['voyager-nolabels'];
        tileLayer.setSource(new ol.source.XYZ({ urls: urls, maxZoom: 21 }));
        currentStyleKey = key;
        hStyle.value = key;
        document.querySelectorAll('.style-btn').forEach(function(b) {
            b.classList.toggle('active', b.dataset.style === key);
        });
    };

    function syncUIFromView() {
        var z = view.getZoom();
        var c = ol.proj.toLonLat(view.getCenter());
        var r = rad2deg(view.getRotation());
        slZ.value   = (+z).toFixed(1);
        slLat.value = (+c[1]).toFixed(6);
        slLon.value = (+c[0]).toFixed(6);
        slRot.value = (+r).toFixed(1);
        lblZ.textContent  = (+z).toFixed(1);
        lblLt.textContent = (+c[1]).toFixed(6);
        lblLn.textContent = (+c[0]).toFixed(6);
        lblR.textContent  = (+r).toFixed(1) + '°';
        cords.textContent = (+c[1]).toFixed(6) + ', ' + (+c[0]).toFixed(6)
                          + ' · zoom ' + (+z).toFixed(1)
                          + ' · rot '  + (+r).toFixed(1) + '°';
        hZ.value   = (+z).toFixed(1);
        hLat.value = (+c[1]).toFixed(6);
        hLon.value = (+c[0]).toFixed(6);
        hRot.value = (+r).toFixed(1);
        [slZ, slLat, slLon, slRot].forEach(grad);
    }

    function syncViewFromUI() {
        view.setZoom(parseFloat(slZ.value));
        view.setCenter(ol.proj.fromLonLat([parseFloat(slLon.value), parseFloat(slLat.value)]));
        view.setRotation(deg2rad(parseFloat(slRot.value || '0')));
        syncUIFromView();
    }

    map.on('click',   function(evt) { view.setCenter(evt.coordinate); syncUIFromView(); });
    map.on('moveend', function()    { syncUIFromView(); });
    slZ.addEventListener('input',   syncViewFromUI);
    slLat.addEventListener('input', syncViewFromUI);
    slLon.addEventListener('input', syncViewFromUI);
    slRot.addEventListener('input', syncViewFromUI);

    document.getElementById('btn-mcp-reset').addEventListener('click', function() {
        slZ.value   = DEF.zoom;
        slLat.value = DEF.lat;
        slLon.value = DEF.lon;
        slRot.value = DEF.rot;
        syncViewFromUI();
    });

    syncUIFromView();
})();
</script>

<?php
function hv($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
?>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
