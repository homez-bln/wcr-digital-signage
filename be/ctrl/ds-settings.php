<?php
/**
 * ctrl/ds-settings.php — DS Zentraler Controller v3
 * Theme-Switcher + Farben + Typografie + Layout in einer Seite.
 * Schreibt direkt in wp_options (wcr_ds_theme + wcr_ds_options).
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_ds');

// ── Konfiguration ─────────────────────────────────────────────
$THEMES = [
    'glass'  => ['icon'=>'🪟', 'name'=>'Glass',  'sub'=>'Glassmorphism',    'desc'=>'Blur-Effekte, frosted Cards, subtile Tiefe durch Transparenz'],
    'flat'   => ['icon'=>'▪',  'name'=>'Flat',   'sub'=>'Modern Flat',      'desc'=>'Kein Blur, solide Flächen, scharfe Kanten — klares Minimaldesign'],
    'aurora' => ['icon'=>'🌌', 'name'=>'Aurora', 'sub'=>'Aurora Gradient',  'desc'=>'Animierter Gradient-Mesh Hintergrund, Farb-Borders pro Karte'],
];

$DEFAULTS = [
    'clr_green'         => '#679467',
    'clr_blue'          => '#019ee3',
    'clr_white'         => '#ffffff',
    'clr_text'          => '#eeeeee',
    'clr_muted'         => '#7a8a8a',
    'clr_bg'            => '#080808',
    'clr_bg_dark'       => '#0d0d0d',
    'clr_bg_glass'      => 'rgba(10,14,24,0.65)',
    'font_family'       => 'Segoe UI',
    'font_size_product' => '2',
    'font_size_price'   => '2',
    'font_size_header'  => '0.72',
    'font_size_subhead' => '2',
    'letter_spacing'    => '6',
    'radius_card'       => '18',
    'radius_large'      => '26',
    'blur_amount'       => '20',
    'header_height'     => '96',
    'padding_global'    => '40',
];

$FONTS = ['Segoe UI','Inter','Roboto','Montserrat','Poppins','Oswald','Raleway','Open Sans','Lato','Ubuntu'];

$COLORS = [
    'clr_green'   => ['Primärfarbe',       'Akzente, Punkte, Kategorien'],
    'clr_blue'    => ['Sekundärfarbe',      'Windmap, Timeline, Badges'],
    'clr_white'   => ['Weiß',              'Preise, Zahlen'],
    'clr_text'    => ['Textfarbe',          'Produktnamen, Fließtext'],
    'clr_muted'   => ['Grau',              'Labels, Nebeninfos'],
    'clr_bg'      => ['Hintergrund',        'Body-Hintergrund'],
    'clr_bg_dark' => ['Hintergrund Dunkel', 'Karten-Hintergrund'],
];

$TYPO = [
    'font_size_product' => ['Produktname',    '1',   '4',   '0.1',  'rem'],
    'font_size_price'   => ['Preis',          '1',   '4',   '0.1',  'rem'],
    'font_size_header'  => ['Header Label',   '0.5', '2',   '0.01', 'rem'],
    'font_size_subhead' => ['Kategorie',      '1',   '4',   '0.1',  'rem'],
    'letter_spacing'    => ['Letter-Spacing', '0',   '20',  '1',    'px'],
];

$LAYOUT = [
    'radius_card'    => ['Border-Radius Karte', '0',  '40',  '1', 'px'],
    'radius_large'   => ['Border-Radius Groß',  '0',  '60',  '1', 'px'],
    'blur_amount'    => ['Blur-Stärke (Glass)',  '0',  '60',  '1', 'px'],
    'header_height'  => ['Header Höhe',         '40', '200', '1', 'px'],
    'padding_global' => ['Globales Padding',     '0',  '120', '1', 'px'],
];

// ── DB-Hilfsfunktionen ────────────────────────────────────────
function dsc_option_get(PDO $pdo, string $name, string $default = ''): string {
    try {
        $st = $pdo->prepare("SELECT option_value FROM wp_options WHERE option_name = ? LIMIT 1");
        $st->execute([$name]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['option_value'] !== '') ? (string)$row['option_value'] : $default;
    } catch (Exception $e) { return $default; }
}

function dsc_option_set(PDO $pdo, string $name, string $value): bool {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM wp_options WHERE option_name = ?");
        $st->execute([$name]);
        if ((int)$st->fetchColumn() > 0) {
            return $pdo->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = ?")->execute([$value, $name]);
        } else {
            return $pdo->prepare("INSERT INTO wp_options (option_name, option_value, autoload) VALUES (?, ?, 'yes')")->execute([$name, $value]);
        }
    } catch (Exception $e) { return false; }
}

function dsc_load_opts(PDO $pdo, array $defaults): array {
    $raw = dsc_option_get($pdo, 'wcr_ds_options');
    if ($raw) {
        $saved = @unserialize($raw);
        if (is_array($saved)) return array_merge($defaults, $saved);
    }
    return $defaults;
}

function dsc_save_opts(PDO $pdo, array $data): bool {
    return dsc_option_set($pdo, 'wcr_ds_options', serialize($data));
}

function dsc_get_theme(PDO $pdo): string {
    $t = dsc_option_get($pdo, 'wcr_ds_theme', 'glass');
    return in_array($t, ['glass','flat','aurora'], true) ? $t : 'glass';
}

// ── POST-Handler ──────────────────────────────────────────────
$msg     = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // Theme aktivieren
    if ($action === 'theme') {
        $t = trim($_POST['theme'] ?? '');
        if (isset($THEMES[$t])) {
            if (dsc_option_set($pdo, 'wcr_ds_theme', $t)) {
                $msg     = 'Theme „' . $THEMES[$t]['name'] . '" aktiviert — alle DS-Screens zeigen sofort den neuen Stil.';
                $msgType = 'ok';
            } else {
                $msg     = 'Fehler beim Speichern des Themes.';
                $msgType = 'error';
            }
        }
    }

    // Farben + Typografie + Layout speichern
    if ($action === 'save') {
        $new = [];

        foreach (array_keys($COLORS) as $k) {
            $v = trim($_POST[$k] ?? '');
            $new[$k] = (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) || preg_match('/^rgba?\([\d,.\s]+\)$/', $v))
                ? $v : $DEFAULTS[$k];
        }
        $glass = trim($_POST['clr_bg_glass'] ?? '');
        $new['clr_bg_glass'] = preg_match('/^rgba?\([\d,.\s]+\)$/', $glass) ? $glass : $DEFAULTS['clr_bg_glass'];

        $new['font_family'] = in_array($_POST['font_family'] ?? '', $FONTS, true)
            ? $_POST['font_family'] : $DEFAULTS['font_family'];

        foreach ($TYPO as $k => [$l,$min,$max,$step,$u]) {
            $v = (float)($_POST[$k] ?? $DEFAULTS[$k]);
            $new[$k] = (string)max((float)$min, min((float)$max, $v));
        }
        foreach ($LAYOUT as $k => [$l,$min,$max,$step,$u]) {
            $v = (int)($_POST[$k] ?? $DEFAULTS[$k]);
            $new[$k] = (string)max((int)$min, min((int)$max, $v));
        }

        if (dsc_save_opts($pdo, array_merge($DEFAULTS, $new))) {
            $msg     = 'Gespeichert — Änderungen sind sofort auf allen DS-Seiten aktiv.';
            $msgType = 'ok';
            try { $pdo->exec("DELETE FROM wp_options WHERE option_name LIKE '_transient%wcr%'"); } catch (Exception $e) {}
        } else {
            $msg     = 'Fehler beim Speichern.';
            $msgType = 'error';
        }
    }

    // Reset
    if ($action === 'reset') {
        if (dsc_save_opts($pdo, $DEFAULTS)) {
            $msg     = 'Einstellungen auf Standard zurückgesetzt.';
            $msgType = 'ok';
        } else {
            $msg     = 'Fehler beim Zurücksetzen.';
            $msgType = 'error';
        }
    }
}

$opts        = dsc_load_opts($pdo, $DEFAULTS);
$activeTheme = dsc_get_theme($pdo);
if (!function_exists('ov')) {
    function ov(array $o, string $k): string { return htmlspecialchars($o[$k] ?? ''); }
}
// DB-Schreib-Test
$dbWritable = true;
try {
    $t = $pdo->prepare("SELECT COUNT(*) FROM wp_options WHERE option_name IN ('wcr_ds_options','wcr_ds_theme')");
    $t->execute();
    $dbWritable = ($t->fetchColumn() !== false);
} catch (Exception $e) { $dbWritable = false; }
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>DS Controller</title>
<link id="gf-link" rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= rawurlencode($opts['font_family']==='Segoe UI'?'Inter':$opts['font_family']) ?>:wght@400;600;700;800&display=swap">
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🖥️ DS Controller</h1>
  <div class="header-right">
    <a href="/be/ctrl/ds-seiten.php" class="btn-secondary">← DS-Seiten</a>
  </div>
</div>

<?php if ($msg): ?>
<div class="status-banner <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if (!$dbWritable): ?>
<div class="status-banner error">⚠️ Datenbankverbindung fehlerhaft — Einstellungen können nicht gespeichert werden. Bitte Seite neu laden.</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     BLOCK 1 — THEME
     ═══════════════════════════════════════ -->
<div class="dsc-block">
  <div class="dsc-block-title">🎨 Theme wählen</div>
  <div class="dsc-block-sub">Bestimmt das visuelle Erscheinungsbild aller DS-Seiten</div>

  <div class="theme-grid">
    <?php foreach ($THEMES as $key => $t):
      $isActive = ($key === $activeTheme);
      $bg   = $key==='aurora' ? '#06080e' : ($key==='flat' ? '#0a0a0a' : '#080808');
      $cbg  = $key==='glass'  ? 'rgba(255,255,255,0.07)' : ($key==='aurora' ? 'rgba(11,14,22,0.92)' : '#141414');
      $blur = $key==='glass'  ? 'blur(8px)' : 'none';
    ?>
    <form method="POST">
      <input type="hidden" name="action" value="theme">
      <input type="hidden" name="theme"  value="<?= $key ?>">
      <button type="submit" class="theme-card <?= $isActive ? 'theme-card--on' : '' ?>">

        <!-- Mini-Vorschau -->
        <div class="tc-preview" style="background:<?= $bg ?>; font-family:'Segoe UI',system-ui">
          <?php if ($key==='aurora'): ?>
          <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 55% at 8% 15%,rgba(1,158,227,.28) 0%,transparent 58%),radial-gradient(ellipse 50% 50% at 92% 85%,rgba(103,148,103,.22) 0%,transparent 54%);pointer-events:none"></div>
          <?php elseif ($key==='glass'): ?>
          <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 65% at 15% 50%,rgba(103,148,103,.10) 0%,transparent 68%);pointer-events:none"></div>
          <?php endif; ?>

          <!-- Header -->
          <div style="position:relative;z-index:1;height:17%;display:flex;align-items:center;gap:5px;padding:0 7%;border-bottom:1px solid rgba(255,255,255,.05)">
            <div style="flex:1;height:1px;background:rgba(103,148,103,.4)"></div>
            <span style="font-size:5px;font-weight:700;letter-spacing:3px;white-space:nowrap;color:<?= $key==='aurora'?'#019ee3':'#679467' ?>">● WCR ●</span>
            <div style="flex:1;height:1px;background:rgba(103,148,103,.4)"></div>
          </div>

          <!-- 3 Karten -->
          <div style="position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,1fr);gap:3px;padding:4px 4%">
            <?php foreach(['Espresso','Latte','Cappuccino'] as $ci => $cn):
              $bar = $key==='aurora' ? ($ci%2===0 ? 'linear-gradient(90deg,#679467,#019ee3)' : 'linear-gradient(90deg,#019ee3,#679467)') : '#679467';
            ?>
            <div style="background:<?= $cbg ?>;border:1px solid rgba(255,255,255,.09);border-radius:5px;padding:5px 3px;display:flex;flex-direction:column;align-items:center;gap:2px;position:relative;overflow:hidden;<?= $blur!=='none'?'backdrop-filter:'.$blur.';-webkit-backdrop-filter:'.$blur.';':'' ?>">
              <div style="position:absolute;top:0;left:0;right:0;height:2px;background:<?= $bar ?>"></div>
              <div style="width:18px;height:18px;border-radius:50%;background:rgba(103,148,103,.12);border:1px solid rgba(103,148,103,.28);display:flex;align-items:center;justify-content:center;font-size:9px;margin-top:2px">☕</div>
              <div style="font-size:4.5px;color:rgba(238,238,238,.85)"><?= $cn ?></div>
              <div style="font-size:7px;font-weight:800;color:#fff"><?= ['2,80','3,20','3,00'][$ci] ?> €</div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Liste -->
          <div style="position:relative;z-index:1;padding:3px 7%">
            <?php foreach(['Erdinger · 4,50 €','Corona · 4,00 €','Augustiner · 4,20 €'] as $li): ?>
            <div style="display:flex;justify-content:space-between;font-size:5px;color:rgba(238,238,238,.65);padding:1.5px 0"><?= $li ?></div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Info -->
        <div class="tc-info">
          <span class="tc-icon"><?= $t['icon'] ?></span>
          <div class="tc-text">
            <div class="tc-name"><?= $t['name'] ?></div>
            <div class="tc-sub"><?= $t['sub'] ?></div>
          </div>
          <?php if ($isActive): ?><span class="tc-badge">✓ Aktiv</span><?php endif; ?>
        </div>
        <div class="tc-desc"><?= $t['desc'] ?></div>

      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════
     BLOCK 2 — EINSTELLUNGEN + VORSCHAU
     ═══════════════════════════════════════ -->
<form method="POST" id="dsc-form">
<input type="hidden" name="action" value="save">
<div class="dsc-2col">

  <!-- Linke Spalte -->
  <div class="dsc-left">

    <div class="dsc-block">
      <div class="dsc-block-title">🎨 Farben</div>
      <div class="dsc-block-sub">Gelten für alle drei Themes</div>
      <div class="clr-grid">
        <?php foreach ($COLORS as $k => [$label, $desc]): ?>
        <div class="clr-row">
          <div class="clr-sw-wrap">
            <input type="color" id="cp_<?= $k ?>" value="<?= ov($opts,$k) ?>"
                   onchange="syncClr('<?= $k ?>',this.value)">
            <div class="clr-sw" id="sw_<?= $k ?>"
                 style="background:<?= ov($opts,$k) ?>"
                 onclick="document.getElementById('cp_<?= $k ?>').click()"></div>
          </div>
          <div class="clr-info">
            <div class="clr-label"><?= htmlspecialchars($label) ?></div>
            <div class="clr-desc"><?= htmlspecialchars($desc) ?></div>
          </div>
          <input type="text" class="clr-hex" id="ct_<?= $k ?>" name="<?= $k ?>"
                 value="<?= ov($opts,$k) ?>" placeholder="<?= $DEFAULTS[$k] ?>"
                 oninput="syncClrTxt('<?= $k ?>',this.value)">
        </div>
        <?php endforeach; ?>
        <div class="clr-row">
          <div class="clr-sw-wrap">
            <div class="clr-sw" style="position:relative;overflow:hidden;background:#111">
              <div style="position:absolute;inset:0;background:repeating-conic-gradient(#555 0% 25%,#333 0% 50%) 0 0/8px 8px;opacity:.5"></div>
            </div>
          </div>
          <div class="clr-info">
            <div class="clr-label">Glas-Hintergrund</div>
            <div class="clr-desc">rgba() — für Glass-Theme</div>
          </div>
          <input type="text" class="clr-hex" style="width:190px" name="clr_bg_glass"
                 value="<?= ov($opts,'clr_bg_glass') ?>" placeholder="rgba(10,14,24,0.65)">
        </div>
      </div>
    </div>

    <div class="dsc-block">
      <div class="dsc-block-title">🔤 Schriftart</div>
      <div class="font-row">
        <select name="font_family" id="font-sel" onchange="updFont(this.value)">
          <?php foreach ($FONTS as $f): ?>
          <option value="<?= htmlspecialchars($f) ?>" <?= $opts['font_family']===$f?'selected':'' ?>><?= htmlspecialchars($f) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="font-prev" id="font-prev" style="font-family:'<?= ov($opts,'font_family') ?>',system-ui">
          Wake &amp; Camp — 12,50 €
        </div>
      </div>
    </div>

    <div class="dsc-block">
      <div class="dsc-block-title">📐 Typografie</div>
      <div class="sl-grid">
        <?php foreach ($TYPO as $k => [$lbl,$min,$max,$step,$u]): ?>
        <div class="sl-row">
          <span class="sl-lbl"><?= htmlspecialchars($lbl) ?></span>
          <input type="range" id="sl_<?= $k ?>" name="<?= $k ?>"
                 min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= ov($opts,$k) ?>"
                 oninput="slUpd('<?= $k ?>','<?= $u ?>')">
          <span class="sl-val" id="sv_<?= $k ?>"><?= ov($opts,$k) ?> <?= $u ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dsc-block">
      <div class="dsc-block-title">📏 Layout</div>
      <div class="sl-grid">
        <?php foreach ($LAYOUT as $k => [$lbl,$min,$max,$step,$u]): ?>
        <div class="sl-row">
          <span class="sl-lbl"><?= htmlspecialchars($lbl) ?></span>
          <input type="range" id="sl_<?= $k ?>" name="<?= $k ?>"
                 min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= ov($opts,$k) ?>"
                 oninput="slUpd('<?= $k ?>','<?= $u ?>')">
          <span class="sl-val" id="sv_<?= $k ?>"><?= ov($opts,$k) ?> <?= $u ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dsc-actions">
      <button type="submit" class="btn-save">💾 Einstellungen speichern</button>
      <button type="button" class="btn-reset"
              onclick="if(confirm('Alle Farben und Größen auf Standard zurücksetzen?'))document.getElementById('rst-form').submit()">
        ↩ Zurücksetzen
      </button>
    </div>

  </div><!-- /.dsc-left -->

  <!-- Rechte Spalte: Vorschau -->
  <div class="dsc-right">
    <div class="dsc-preview-wrap">

      <div class="prev-lbl">Landscape · 1920×1080</div>
      <div class="pv-screen" id="pv-ls">
        <div class="pv-bg" id="pv-bg"></div>
        <div class="pv-hdr" id="pv-hdr">
          <div class="pv-line" id="pv-l1"></div>
          <div class="pv-hi" id="pv-hi"><div class="pv-dot" id="pv-dot"></div><span>WCR · WAKE &amp; CAMP</span></div>
          <div class="pv-line" id="pv-l2"></div>
        </div>
        <div class="pv-cards" id="pv-cards">
          <?php foreach(['Espresso','Latte Macchiato','Cappuccino'] as $i=>$n): ?>
          <div class="pv-card" id="pc-<?= $i ?>">
            <div class="pv-cbar" id="pb-<?= $i ?>"></div>
            <div class="pv-circ" id="pcirc-<?= $i ?>">☕</div>
            <div class="pv-cname"  id="pcn-<?= $i ?>"><?= $n ?></div>
            <div class="pv-cprice" id="pcp-<?= $i ?>"><?= ['2,80','3,20','3,00'][$i] ?> <small>€</small></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="pv-list">
          <?php foreach(['Erdinger Weißbier','Corona Extra','Augustiner'] as $j=>$n): ?>
          <div class="pv-item">
            <span class="pv-pn" id="ppn-<?= $j ?>"><?= $n ?></span>
            <span class="pv-pp" id="ppp-<?= $j ?>"><?= ['4,50','4,00','4,20'][$j] ?> €</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="prev-lbl" style="margin-top:12px">Portrait · Öffnungszeiten</div>
      <div class="pv-screen pv-portrait" id="pv-pt">
        <div class="pv-bg" id="pv-bg-p"></div>
        <div class="pv-glass" id="pv-glass">
          <?php foreach(['Mo:','Di:','Mi:','Do:','Fr:'] as $day): ?>
          <div class="pv-oh-row">
            <span class="pv-oh-day"><?= $day ?></span>
            <span class="pv-oh-time" id="pv-oh-t">14 – 20</span>
            <span class="pv-oh-unit">UHR</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="prev-lbl" style="margin-top:12px">CSS (aktive Variablen)</div>
      <pre class="pv-css" id="pv-css"></pre>

    </div>
  </div>

</div>
</form>

<form method="POST" id="rst-form" style="display:none"><input type="hidden" name="action" value="reset"></form>

<!-- ═══════════════════════════════════════ STYLES ═══ -->
<style>
.dsc-block       { background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px; }
.dsc-block-title { font-size:14px;font-weight:700;margin:0 0 3px; }
.dsc-block-sub   { font-size:12px;color:var(--text-muted);margin:0 0 16px; }

/* Theme Grid */
.theme-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px; }
.theme-card {
  all:unset;cursor:pointer;display:flex;flex-direction:column;
  border:2px solid var(--border-light);border-radius:12px;overflow:hidden;
  background:var(--bg-subtle);transition:border-color .18s,box-shadow .18s,transform .15s;
  width:100%;
}
.theme-card:hover { border-color:var(--border);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.10); }
.theme-card--on   { border-color:var(--primary) !important;box-shadow:0 0 0 3px rgba(0,113,227,.12) !important; }
.tc-preview { aspect-ratio:16/9;overflow:hidden;position:relative; }
.tc-info    { display:flex;align-items:center;gap:10px;padding:10px 14px 4px; }
.tc-icon    { font-size:18px;flex-shrink:0; }
.tc-name    { font-size:14px;font-weight:700;color:var(--text-main); }
.tc-sub     { font-size:11px;color:var(--text-muted); }
.tc-badge   { margin-left:auto;padding:3px 10px;border-radius:20px;background:rgba(52,199,89,.12);color:#1a7a30;border:1px solid rgba(52,199,89,.3);font-size:10px;font-weight:700;flex-shrink:0; }
.tc-desc    { font-size:11px;color:var(--text-muted);padding:0 14px 12px;line-height:1.45; }

/* 2-Spalten */
.dsc-2col  { display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;margin-top:20px; }
.dsc-left  { display:flex;flex-direction:column; }
.dsc-right { position:sticky;top:76px; }
.dsc-preview-wrap { display:flex;flex-direction:column; }
.prev-lbl  { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:5px; }

/* Farben */
.clr-grid  { display:flex;flex-direction:column;gap:9px; }
.clr-row   { display:flex;align-items:center;gap:11px;padding:8px 11px;border-radius:8px;background:var(--bg-subtle); }
.clr-row:hover { background:var(--border-light); }
.clr-sw-wrap   { position:relative;flex-shrink:0; }
.clr-sw-wrap input[type=color] { position:absolute;opacity:0;width:0;height:0; }
.clr-sw    { width:36px;height:36px;border-radius:7px;cursor:pointer;border:2px solid rgba(0,0,0,.08);box-shadow:0 2px 6px rgba(0,0,0,.14);transition:transform .14s; }
.clr-sw:hover { transform:scale(1.1); }
.clr-info  { flex:1;min-width:0; }
.clr-label { font-size:13px;font-weight:600;color:var(--text-main); }
.clr-desc  { font-size:11px;color:var(--text-muted);margin-top:1px; }
.clr-hex   { width:100px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-family:monospace;font-size:12px;background:var(--bg-card);color:var(--text-main); }
.clr-hex:focus { outline:none;border-color:var(--primary); }

/* Font */
.font-row  { display:flex;gap:12px;align-items:center; }
.font-row select { padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-main); }
.font-prev { flex:1;padding:11px 14px;background:#12121e;color:#eee;border-radius:8px;font-size:18px;text-align:center;transition:font-family .3s;overflow:hidden;white-space:nowrap; }

/* Slider */
.sl-grid   { display:flex;flex-direction:column;gap:13px; }
.sl-row    { display:grid;grid-template-columns:160px 1fr 62px;gap:10px;align-items:center; }
.sl-lbl    { font-size:13px;font-weight:500;color:var(--text-main); }
.sl-val    { font-size:12px;font-weight:700;color:var(--primary);text-align:right;font-variant-numeric:tabular-nums; }
input[type=range] { accent-color:var(--primary);cursor:pointer;width:100%; }

/* Buttons */
.dsc-actions { display:flex;gap:12px;padding-bottom:4px; }
.btn-save  { flex:1;padding:13px;font-size:15px;font-weight:700;background:var(--primary);color:#fff;border:none;border-radius:10px;cursor:pointer;transition:opacity .18s; }
.btn-save:hover { opacity:.86; }
.btn-reset { padding:13px 16px;font-size:13px;font-weight:600;background:var(--bg-subtle);color:var(--text-main);border:1px solid var(--border);border-radius:10px;cursor:pointer; }
.btn-reset:hover { background:#fff0f0;color:#c0392b;border-color:#ffd0cc; }

/* Preview Screens */
.pv-screen  { position:relative;width:100%;aspect-ratio:16/9;border-radius:8px;overflow:hidden;border:2px solid var(--border);box-shadow:var(--shadow); }
.pv-portrait { aspect-ratio:9/16;max-height:220px;width:auto; }
.pv-bg      { position:absolute;inset:0;z-index:0; }
.pv-hdr     { position:relative;z-index:1;height:17%;display:flex;align-items:center;gap:5px;padding:0 7%;border-bottom:1px solid rgba(255,255,255,.06); }
.pv-line    { flex:1;height:1px; }
.pv-hi      { display:flex;align-items:center;gap:4px;font-size:5.5px;font-weight:700;text-transform:uppercase;letter-spacing:3px;white-space:nowrap; }
.pv-dot     { width:5px;height:5px;border-radius:50%;flex-shrink:0; }
.pv-cards   { position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,1fr);gap:3px;padding:4px 4%; }
.pv-card    { border-radius:5px;padding:5px 3px;display:flex;flex-direction:column;align-items:center;gap:2px;position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08); }
.pv-cbar    { position:absolute;top:0;left:0;right:0;height:2px;border-radius:5px 5px 0 0; }
.pv-circ    { width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;margin-top:2px; }
.pv-cname   { font-size:4.5px;text-align:center; }
.pv-cprice  { font-size:7px;font-weight:800; }
.pv-list    { position:relative;z-index:1;padding:3px 6%;display:flex;flex-direction:column;gap:2px; }
.pv-item    { display:flex;justify-content:space-between;font-size:5.5px; }
.pv-pp      { font-weight:700; }
.pv-glass   { position:relative;z-index:1;margin:8px;border-radius:6px;padding:8px 10px; }
.pv-oh-row  { display:flex;align-items:baseline;gap:5px;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.06); }
.pv-oh-row:last-child { border-bottom:none; }
.pv-oh-day  { font-size:8px;font-weight:800;width:20px;text-align:right;opacity:.55; }
.pv-oh-time { font-size:10px;font-weight:600;flex:1;text-align:center; }
.pv-oh-unit { font-size:5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.5; }
.pv-css     { background:#0d1117;color:#7ee787;font-size:9px;padding:10px 12px;border-radius:7px;overflow-x:auto;white-space:pre;line-height:1.6;max-height:160px;overflow-y:auto;margin:0; }

@media(max-width:1200px) { .dsc-2col{grid-template-columns:1fr;} .dsc-right{position:static;} }
@media(max-width:900px)  { .theme-grid{grid-template-columns:1fr;} }
</style>

<!-- ═══════════════════════════════════════ SCRIPT ═══ -->
<script>
var G = {
  green:   "<?= addslashes($opts['clr_green']) ?>",
  blue:    "<?= addslashes($opts['clr_blue']) ?>",
  white:   "<?= addslashes($opts['clr_white']) ?>",
  text:    "<?= addslashes($opts['clr_text']) ?>",
  muted:   "<?= addslashes($opts['clr_muted']) ?>",
  bg:      "<?= addslashes($opts['clr_bg']) ?>",
  bgDark:  "<?= addslashes($opts['clr_bg_dark']) ?>",
  bgGlass: "<?= addslashes($opts['clr_bg_glass']) ?>",
  font:    "<?= addslashes($opts['font_family']) ?>",
  rc:      "<?= addslashes($opts['radius_card']) ?>",
  blur:    "<?= addslashes($opts['blur_amount']) ?>",
  prodSz:  <?= (float)$opts['font_size_product'] ?>,
  priceSz: <?= (float)$opts['font_size_price'] ?>,
  ls:      <?= (float)$opts['letter_spacing'] ?>,
};
var THEME = "<?= $activeTheme ?>";

var KEY_MAP = {
  clr_green:'green', clr_blue:'blue', clr_white:'white',
  clr_text:'text', clr_muted:'muted', clr_bg:'bg', clr_bg_dark:'bgDark'
};
var SL_MAP = {
  radius_card:'rc', blur_amount:'blur',
  font_size_product:'prodSz', font_size_price:'priceSz', letter_spacing:'ls'
};

function syncClr(k, v) {
  if (KEY_MAP[k]) G[KEY_MAP[k]] = v;
  var sw = document.getElementById('sw_'+k);
  if (sw) sw.style.background = v;
  var ct = document.getElementById('ct_'+k);
  if (ct) ct.value = v;
  render();
}
function syncClrTxt(k, v) {
  if (KEY_MAP[k]) G[KEY_MAP[k]] = v;
  try { var sw = document.getElementById('sw_'+k); if (sw) sw.style.background = v; } catch(e) {}
  if (/^#[0-9a-fA-F]{6}$/.test(v)) {
    var cp = document.getElementById('cp_'+k);
    if (cp) cp.value = v;
  }
  render();
}
function slUpd(k, u) {
  var el = document.getElementById('sl_'+k);
  if (!el) return;
  document.getElementById('sv_'+k).textContent = el.value + ' ' + u;
  if (SL_MAP[k]) G[SL_MAP[k]] = parseFloat(el.value);
  render();
}
function updFont(f) {
  G.font = f;
  document.getElementById('font-prev').style.fontFamily = "'"+f+"',system-ui";
  if (f !== 'Segoe UI') {
    var l = document.getElementById('gf-link');
    if (l) l.href = 'https://fonts.googleapis.com/css2?family='+encodeURIComponent(f)+':wght@400;600;700;800&display=swap';
  }
  render();
}

function h2r(hex, a) {
  if (!hex || hex[0]!=='#') return 'rgba(128,128,128,'+a+')';
  var r=parseInt(hex.slice(1,3),16)||0, g=parseInt(hex.slice(3,5),16)||0, b=parseInt(hex.slice(5,7),16)||0;
  return 'rgba('+r+','+g+','+b+','+a+')';
}

function render() {
  var font  = "'"+G.font+"',system-ui,sans-serif";
  var rc    = G.rc+'px';
  var blur  = parseFloat(G.blur)>0 ? 'blur('+G.blur+'px) saturate(140%)' : 'none';
  var glass = THEME==='glass';
  var aurora = THEME==='aurora';
  var flat  = THEME==='flat';

  // BG
  var bg = document.getElementById('pv-bg');
  if (bg) {
    bg.style.background = G.bg;
    bg.style.backgroundImage = aurora
      ? 'radial-gradient(ellipse 55% 55% at 5% 10%,'+h2r(G.blue,.2)+' 0%,transparent 58%),radial-gradient(ellipse 45% 45% at 92% 88%,'+h2r(G.green,.15)+' 0%,transparent 54%)'
      : (glass ? 'radial-gradient(ellipse 65% 65% at 15% 50%,'+h2r(G.green,.08)+' 0%,transparent 68%)' : 'none');
  }
  var bgp = document.getElementById('pv-bg-p');
  if (bgp) { bgp.style.background = G.bg; bgp.style.backgroundImage = 'none'; }

  // Header
  var hi = document.getElementById('pv-hi');
  if (hi) { hi.style.color = aurora ? G.blue : G.green; hi.style.fontFamily = font; }
  var dot = document.getElementById('pv-dot');
  if (dot) { dot.style.background = aurora ? G.blue : G.green; dot.style.boxShadow = glass ? '0 0 6px '+(aurora?G.blue:G.green) : 'none'; }
  ['pv-l1','pv-l2'].forEach(function(id,i) {
    var el=document.getElementById(id);
    var c = aurora ? G.blue : G.green;
    if (el) el.style.background = i===0 ? 'linear-gradient(90deg,transparent,'+c+'55)' : 'linear-gradient(90deg,'+c+'55,transparent)';
  });

  // Cards
  for (var i=0;i<3;i++) {
    var card = document.getElementById('pc-'+i);
    var cardBg = glass ? 'rgba(255,255,255,0.06)' : (aurora ? h2r(G.bgDark,.92) : G.bgDark);
    if (card) {
      card.style.background = cardBg;
      card.style.backdropFilter = blur;
      card.style.webkitBackdropFilter = blur;
      card.style.borderRadius = rc;
    }
    var bar = document.getElementById('pb-'+i);
    if (bar) bar.style.background = aurora
      ? (i%2===0 ? 'linear-gradient(90deg,'+G.green+','+G.blue+')' : 'linear-gradient(90deg,'+G.blue+','+G.green+')')
      : G.green;
    var circ = document.getElementById('pcirc-'+i);
    if (circ) { circ.style.background=h2r(G.green,.1); circ.style.borderColor=h2r(G.green,.25); }
    var cn = document.getElementById('pcn-'+i);
    if (cn) { cn.style.color=G.text; cn.style.fontFamily=font; }
    var cp = document.getElementById('pcp-'+i);
    if (cp) { cp.style.color=G.white; cp.style.fontFamily=font; cp.style.fontSize=Math.max(6,G.priceSz*4.5)+'px'; }
  }

  // List
  for (var j=0;j<3;j++) {
    var pn=document.getElementById('ppn-'+j), pp=document.getElementById('ppp-'+j);
    if (pn) { pn.style.color=G.text; pn.style.fontFamily=font; pn.style.fontSize=Math.max(5,G.prodSz*4)+'px'; }
    if (pp) { pp.style.color=G.white; pp.style.fontFamily=font; }
  }

  // Portrait
  var gl = document.getElementById('pv-glass');
  if (gl) {
    gl.style.background = glass
      ? 'radial-gradient(circle at top left,'+h2r(G.green,.24)+' 0%,'+h2r(G.blue,.14)+' 40%,rgba(0,0,0,.45) 100%)'
      : (aurora ? h2r(G.bgDark,.9) : G.bgDark);
    gl.style.backdropFilter = blur;
    gl.style.webkitBackdropFilter = blur;
    gl.style.borderRadius = rc;
    gl.style.border = '1px solid '+(aurora ? h2r(G.blue,.22) : 'rgba(255,255,255,0.09)');
  }
  document.querySelectorAll('.pv-oh-day').forEach(function(el) { el.style.color=G.muted; });
  document.querySelectorAll('.pv-oh-time').forEach(function(el) { el.style.color=G.white; el.style.fontFamily=font; });

  // CSS
  var out = document.getElementById('pv-css');
  if (out) out.textContent = ':root {\n'
    + '  --clr-green:     '+G.green+';\n'
    + '  --clr-blue:      '+G.blue+';\n'
    + '  --clr-text:      '+G.text+';\n'
    + '  --clr-text-muted:'+G.muted+';\n'
    + '  --clr-bg:        '+G.bg+';\n'
    + '  --radius-card:   '+rc+';\n'
    + '  --blur-glass:    '+(parseFloat(G.blur)>0?'blur('+G.blur+'px)':'none')+';\n'
    + '  --font-main:     '+font+';\n'
    + '}';
}

document.addEventListener('DOMContentLoaded', function() {
  var units = {radius_card:'px',radius_large:'px',blur_amount:'px',font_size_product:'rem',
               font_size_price:'rem',font_size_header:'rem',font_size_subhead:'rem',
               letter_spacing:'px',header_height:'px',padding_global:'px'};
  Object.keys(units).forEach(function(k) {
    var el = document.getElementById('sl_'+k);
    if (el) el.addEventListener('input', function() { slUpd(k, units[k]); });
  });
  render();
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
