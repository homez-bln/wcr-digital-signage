<?php
/**
 * ctrl/obstacles.php — Obstacles-Verwaltung (Karte)
 *
 * Verwaltung der Hindernisse für die Obstacle-Map:
 *   - Name / Typ
 *   - Position (pos_x, pos_y) in Prozent (0–100) bezogen auf die Kartenbreite/-höhe
 *   - Rotation in Grad
 *   - Icon-URL (Top-View PNG)
 *   - Aktiv-Flag
 *
 * Nutzt die gleiche externe IONOS-DB wie die REST-API (/wakecamp/v1/obstacles).
 */

$PAGE_TITLE = 'Obstacles';

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_media'); // gleiche Rolle wie Media-Verwaltung

$db = $pdo;

// Tabelle sicherstellen (Schema passend zum REST-Endpoint)
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

// Speichern-Handling
$saveMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_obstacles'])) {
    $ids       = $_POST['id'] ?? [];
    $names     = $_POST['name'] ?? [];
    $types     = $_POST['type'] ?? [];
    $icons     = $_POST['icon_url'] ?? [];
    $posXs     = $_POST['pos_x'] ?? [];
    $posYs     = $_POST['pos_y'] ?? [];
    $rots      = $_POST['rotation'] ?? [];
    $actives   = $_POST['active'] ?? [];

    $stmtIns = $db->prepare("INSERT INTO obstacles
        (id, name, type, icon_url, pos_x, pos_y, rotation, active)
        VALUES (:id, :name, :type, :icon_url, :pos_x, :pos_y, :rotation, :active)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            type = VALUES(type),
            icon_url = VALUES(icon_url),
            pos_x = VALUES(pos_x),
            pos_y = VALUES(pos_y),
            rotation = VALUES(rotation),
            active = VALUES(active)");

    $count = 0;
    foreach ($names as $idx => $n) {
        $n = trim((string)$n);
        $t = trim((string)($types[$idx] ?? ''));
        if ($n === '' && $t === '') continue; // komplett leere Zeilen ignorieren

        $id   = (int)($ids[$idx] ?? 0);
        $icon = trim((string)($icons[$idx] ?? ''));
        $x    = (float)str_replace(',', '.', (string)($posXs[$idx] ?? 0));
        $y    = (float)str_replace(',', '.', (string)($posYs[$idx] ?? 0));
        $rot  = (float)str_replace(',', '.', (string)($rots[$idx] ?? 0));

        // Begrenzen auf 0–100 für Position
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

    $saveMsg = '✓ ' . $count . ' Obstacle(s) gespeichert';
    $loc = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $loc . '?saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $saveMsg = '✓ Obstacles gespeichert';
}

// Daten laden
$rows = $db->query("SELECT * FROM obstacles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$maxRows = max(20, count($rows) + 3); // 20 Ziel, ein paar Extra-Zeilen zum Anlegen

?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../inc/style.css">
  <style>
    .obs-wrapper {
      max-width: 1180px;
      margin: 0 auto;
      padding: 24px 20px;
    }
    .obs-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      background: #fff;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0,0,0,0.04);
    }
    .obs-table th,
    .obs-table td {
      padding: 6px 8px;
      border-bottom: 1px solid #f0f0f3;
      text-align: left;
      vertical-align: middle;
    }
    .obs-table th {
      background: #f5f5f7;
      font-weight: 600;
      font-size: 12px;
      color: #6e6e73;
    }
    .obs-table tr:last-child td {
      border-bottom: none;
    }
    .obs-table input[type="text"],
    .obs-table input[type="number"] {
      width: 100%;
      box-sizing: border-box;
      padding: 4px 6px;
      border-radius: 6px;
      border: 1px solid #d2d2d7;
      font-size: 12px;
    }
    .obs-table input[type="number"] {
      text-align: right;
    }
    .obs-table .obs-id {
      width: 40px;
      color: #9f9fa5;
      font-size: 11px;
    }
    .obs-active {
      text-align: center;
      width: 60px;
    }
    .obs-save-bar {
      margin-top: 14px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .btn-primary {
      padding: 7px 16px;
      border-radius: 999px;
      border: none;
      background: #0071e3;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
    }
    .obs-msg {
      font-size: 12px;
      color: #1d1d1f;
    }
    .obs-hint {
      font-size: 11px;
      color: #6e6e73;
      margin-top: 4px;
    }
  </style>
</head>
<body class="bo">

<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="obs-wrapper">
  <div class="header-controls">
    <h1>🧱 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
    <p class="obs-hint">Verwalte bis zu 20 Obstacles. Position in Prozent bezogen auf die Hintergrundkarte (0–100 links/rechts, 0–100 oben/unten).</p>
  </div>

  <?php if ($saveMsg): ?>
    <div class="obs-msg"><?= htmlspecialchars($saveMsg) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="save_obstacles" value="1">

    <table class="obs-table">
      <thead>
        <tr>
          <th class="obs-id">ID</th>
          <th>Name</th>
          <th>Typ</th>
          <th style="width:90px;">Pos X %</th>
          <th style="width:90px;">Pos Y %</th>
          <th style="width:80px;">Rotation</th>
          <th>Icon‑URL (Top‑View)</th>
          <th class="obs-active">Aktiv</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $i = 0;
        foreach ($rows as $row):
          $i++;
        ?>
        <tr>
          <td class="obs-id">
            <input type="hidden" name="id[]" value="<?= (int)$row['id'] ?>">
            #<?= (int)$row['id'] ?>
          </td>
          <td><input type="text" name="name[]" value="<?= htmlspecialchars($row['name']) ?>"></td>
          <td><input type="text" name="type[]" value="<?= htmlspecialchars($row['type']) ?>"></td>
          <td><input type="number" name="pos_x[]" value="<?= htmlspecialchars($row['pos_x']) ?>" min="0" max="100" step="0.1"></td>
          <td><input type="number" name="pos_y[]" value="<?= htmlspecialchars($row['pos_y']) ?>" min="0" max="100" step="0.1"></td>
          <td><input type="number" name="rotation[]" value="<?= htmlspecialchars($row['rotation']) ?>" step="1"></td>
          <td><input type="text" name="icon_url[]" value="<?= htmlspecialchars($row['icon_url']) ?>"></td>
          <td class="obs-active">
            <input type="checkbox" name="active[<?= $i - 1 ?>]" value="1" <?= $row['active'] ? 'checked' : '' ?>>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php for (; $i < $maxRows; $i++): ?>
        <tr>
          <td class="obs-id">
            <input type="hidden" name="id[]" value="0">
            #neu
          </td>
          <td><input type="text" name="name[]" value=""></td>
          <td><input type="text" name="type[]" value=""></td>
          <td><input type="number" name="pos_x[]" value="" min="0" max="100" step="0.1"></td>
          <td><input type="number" name="pos_y[]" value="" min="0" max="100" step="0.1"></td>
          <td><input type="number" name="rotation[]" value="0" step="1"></td>
          <td><input type="text" name="icon_url[]" value=""></td>
          <td class="obs-active">
            <input type="checkbox" name="active[<?= $i ?>]" value="1" checked>
          </td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>

    <div class="obs-save-bar">
      <button type="submit" class="btn-primary">Speichern</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
