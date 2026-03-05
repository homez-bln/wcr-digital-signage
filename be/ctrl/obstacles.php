<?php
/**
 * ctrl/obstacles.php — Obstacles-Verwaltung + Karten-Einstellungen
 */

$PAGE_TITLE = 'Obstacles';

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_media');

$db = $pdo;

$db->exec("CREATE TABLE IF NOT EXISTS obstacles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50)  NOT NULL,
    icon_url VARCHAR(500) NULL,
    pos_x DECIMAL(6,3) NOT NULL,
    pos_y DECIMAL(6,3) NOT NULL,
    rotation DECIMAL(6,2) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

// ── Aktuelle Map-Config laden ──
$mapCfg = ['lat' => 52.821428, 'lon' => 13.577100, 'zoom' => 17.9];
$r = obs_curl(OBS_WP_API);
if ($r['ok'] && is_array($r['json']) && isset($r['json']['lat'])) {
    $mapCfg = $r['json'];
}

// ── Map-Config speichern (POST) ──
$cfgMsg  = '';
$cfgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map_config'])) {
    $lat  = (float)str_replace(',', '.', $_POST['map_lat']  ?? '');
    $lon  = (float)str_replace(',', '.', $_POST['map_lon']  ?? '');
    $zoom = (float)str_replace(',', '.', $_POST['map_zoom'] ?? '');

    $r2 = obs_curl(OBS_WP_API, [
        'lat'        => $lat,
        'lon'        => $lon,
        'zoom'       => $zoom,
        'wcr_secret' => OBS_SECRET,   // ← Auth-Key
    ]);

    if ($r2['ok'] && !empty($r2['json']['ok'])) {
        $cfgMsg  = '✓ Karten-Config gespeichert (zoom ' . number_format($zoom, 1) . ' · ' . $lat . ', ' . $lon . ')';
        $cfgType = 'ok';
        $mapCfg  = ['lat' => $lat, 'lon' => $lon, 'zoom' => $zoom];
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
    header('Location: ' . $loc . '?saved=' . $count);
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
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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
    #mcp-map { width:100%; height:320px; border-radius:10px; border:1px solid #e5e5ea; overflow:hidden; }
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
    <p class="sub">Zoom + Mittelpunkt für <code>[wcr_obstacles_map]</code>. Karte ziehen/klicken → <strong>Karten-Config speichern</strong>.</p>

    <form method="POST">
      <input type="hidden" name="save_map_config" value="1">

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

          <div class="mcp-actions">
            <button type="button" id="btn-mcp-reset" class="btn-secondary">↩ Standard</button>
            <button type="submit" class="btn-primary" style="flex:1;">💾 Karten-Config speichern</button>
          </div>
        </div>

        <div>
          <div id="mcp-map"></div>
          <div class="mcp-hint-bar">Klick auf Karte = neues Zentrum &nbsp;·&nbsp; <span id="mcp-coords"><?= hv($mapCfg['lat']) ?>, <?= hv($mapCfg['lon']) ?> · zoom <?= hv($mapCfg['zoom']) ?></span></div>
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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var DEF = { lat: 52.821428, lon: 13.577100, zoom: 17.9 };
    var slZ=document.getElementById('sl-zoom'), slLat=document.getElementById('sl-lat'), slLon=document.getElementById('sl-lon');
    var lblZ=document.getElementById('lbl-zoom'), lblLt=document.getElementById('lbl-lat'), lblLn=document.getElementById('lbl-lon');
    var cords=document.getElementById('mcp-coords');
    var hZ=document.getElementById('map_zoom'), hLat=document.getElementById('map_lat'), hLon=document.getElementById('map_lon');

    var map = L.map('mcp-map',{zoomControl:true,dragging:true,scrollWheelZoom:true,zoomSnap:0.1,zoomDelta:0.1})
               .setView([parseFloat(slLat.value),parseFloat(slLon.value)],parseFloat(slZ.value));
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png',
        {attribution:'© OpenStreetMap © CARTO',maxZoom:21}).addTo(map);
    var cross=L.divIcon({html:'<svg width="22" height="22" viewBox="0 0 22 22"><line x1="11" y1="0" x2="11" y2="22" stroke="#0071e3" stroke-width="2"/><line x1="0" y1="11" x2="22" y2="11" stroke="#0071e3" stroke-width="2"/><circle cx="11" cy="11" r="3" fill="#0071e3"/></svg>',className:'',iconAnchor:[11,11]});
    var marker=L.marker([parseFloat(slLat.value),parseFloat(slLon.value)],{icon:cross,interactive:false}).addTo(map);

    function grad(sl){var p=(sl.value-sl.min)/(sl.max-sl.min)*100;sl.style.background='linear-gradient(90deg,#0071e3 '+p+'%,#e5e5ea '+p+'%)';}
    [slZ,slLat,slLon].forEach(grad);

    function sync(){
        var z=parseFloat(slZ.value),lt=parseFloat(slLat.value),ln=parseFloat(slLon.value);
        lblZ.textContent=z.toFixed(1); lblLt.textContent=lt.toFixed(6); lblLn.textContent=ln.toFixed(6);
        cords.textContent=lt.toFixed(6)+', '+ln.toFixed(6)+'  zoom: '+z.toFixed(1);
        hZ.value=z.toFixed(1); hLat.value=lt.toFixed(6); hLon.value=ln.toFixed(6);
        map.setView([lt,ln],z); marker.setLatLng([lt,ln]);
        [slZ,slLat,slLon].forEach(grad);
    }
    slZ.addEventListener('input',sync); slLat.addEventListener('input',sync); slLon.addEventListener('input',sync);
    map.on('moveend zoomend',function(){var c=map.getCenter();slLat.value=c.lat.toFixed(6);slLon.value=c.lng.toFixed(6);slZ.value=map.getZoom().toFixed(1);sync();});
    map.on('click',function(e){slLat.value=e.latlng.lat.toFixed(6);slLon.value=e.latlng.lng.toFixed(6);sync();map.panTo([parseFloat(slLat.value),parseFloat(slLon.value)]);});
    document.getElementById('btn-mcp-reset').addEventListener('click',function(){slZ.value=DEF.zoom;slLat.value=DEF.lat;slLon.value=DEF.lon;sync();});
    sync();
})();
</script>

<?php
function hv($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
?>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
