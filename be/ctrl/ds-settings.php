<?php
/**
 * ctrl/ds-settings.php — DS Zentraler Controller v3
 * BE ist Master — schreibt/liest wp_options direkt per PDO.
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_ds');

$THEMES = [
    'glass'  => ['icon'=>'🪟', 'name'=>'Glass',  'sub'=>'Glassmorphism',   'desc'=>'Blur-Effekte, frosted Cards, subtile Tiefe durch Transparenz'],
    'flat'   => ['icon'=>'▪',  'name'=>'Flat',   'sub'=>'Modern Flat',     'desc'=>'Kein Blur, solide Flächen, scharfe Kanten — klares Minimaldesign'],
    'aurora' => ['icon'=>'🌌', 'name'=>'Aurora', 'sub'=>'Aurora Gradient', 'desc'=>'Animierter Gradient-Mesh Hintergrund, Farb-Borders pro Karte'],
];

$DEFAULTS = [
    'clr_green'    => '#679467',
    'clr_blue'     => '#019ee3',
    'clr_white'    => '#ffffff',
    'clr_text'     => '#eeeeee',
    'clr_muted'    => '#7a8a8a',
    'clr_bg'       => '#080808',
    'clr_bg_dark'  => '#0d0d0d',
    'clr_bg_glass' => 'rgba(10,14,24,0.65)',
    'font_family'  => 'Segoe UI',
    'viewport_w'   => '1920',
    'viewport_h'   => '1080',
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

function dsc_maybe_unserialize(string $raw): array {
    if (!preg_match('/^[aOsid]:\d+/', $raw)) {
        return [null, false];
    }
    $v1 = @unserialize($raw);
    if (is_array($v1)) return [$v1, false];
    if (is_string($v1) && preg_match('/^[aOsid]:\d+/', $v1)) {
        $v2 = @unserialize($v1);
        if (is_array($v2)) return [$v2, true];
    }
    return [null, false];
}

function dsc_load_opts(PDO $pdo, array $defaults): array {
    $raw = dsc_option_get($pdo, 'wcr_ds_options');
    if ($raw === '') return $defaults;
    [$data, $wasDouble] = dsc_maybe_unserialize($raw);
    if (!is_array($data)) {
        dsc_option_set($pdo, 'wcr_ds_options', serialize($defaults));
        return $defaults;
    }
    $merged = array_merge($defaults, $data);
    if ($wasDouble) dsc_option_set($pdo, 'wcr_ds_options', serialize($merged));
    return $merged;
}

function dsc_save_opts(PDO $pdo, array $data): bool {
    return dsc_option_set($pdo, 'wcr_ds_options', serialize($data));
}

function dsc_get_theme(PDO $pdo): string {
    $t = dsc_option_get($pdo, 'wcr_ds_theme', 'glass');
    return in_array($t, ['glass','flat','aurora'], true) ? $t : 'glass';
}

// ── RAW DEBUG ─────────────────────────────────────────────────
// Lese roh aus DB BEVOR POST-Handler läuft — zeigt was wirklich in DB steht
$_raw_db_value = dsc_option_get($pdo, 'wcr_ds_options');
$_raw_db_len   = strlen($_raw_db_value);
$_v1           = @unserialize($_raw_db_value);
$_v1_type      = gettype($_v1);
$_post_dump    = !empty($_POST) ? json_encode($_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'kein POST';

// Test-Schreiben: kann BE überhaupt in DB schreiben?
$_write_test_ok = dsc_option_set($pdo, 'wcr_ds_debug_write_test', 'ok_' . time());
$_write_test_val = dsc_option_get($pdo, 'wcr_ds_debug_write_test');

// ── POST-Handler ──────────────────────────────────────────────
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'theme') {
        $t = trim($_POST['theme'] ?? '');
        if (isset($THEMES[$t])) {
            if (dsc_option_set($pdo, 'wcr_ds_theme', $t)) {
                $msg = 'Theme „' . $THEMES[$t]['name'] . '" aktiviert.'; $msgType = 'ok';
            } else { $msg = 'Fehler beim Speichern des Themes.'; $msgType = 'error'; }
        }
    }

    if ($action === 'save') {
        $new = [];
        foreach (array_keys($COLORS) as $k) {
            $v = trim($_POST[$k] ?? '');
            $new[$k] = (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) || preg_match('/^rgba?\([\d,.\s]+\)$/', $v))
                ? $v : $DEFAULTS[$k];
        }
        $glass = trim($_POST['clr_bg_glass'] ?? '');
        $new['clr_bg_glass'] = preg_match('/^rgba?\([\d,.\s]+\)$/', $glass) ? $glass : $DEFAULTS['clr_bg_glass'];
        $new['font_family']  = in_array($_POST['font_family'] ?? '', $FONTS, true) ? $_POST['font_family'] : $DEFAULTS['font_family'];
        $existing = dsc_load_opts($pdo, $DEFAULTS);
        $new['viewport_w']   = $existing['viewport_w'] ?? $DEFAULTS['viewport_w'];
        $new['viewport_h']   = $existing['viewport_h'] ?? $DEFAULTS['viewport_h'];
        $toSave = array_merge($DEFAULTS, $new);
        $serialized = serialize($toSave);
        $saved = dsc_option_set($pdo, 'wcr_ds_options', $serialized);
        if ($saved) {
            // Sofort zurücklesen und prüfen
            $verify_raw   = dsc_option_get($pdo, 'wcr_ds_options');
            $verify_arr   = @unserialize($verify_raw);
            $verify_match = (is_array($verify_arr) && ($verify_arr['clr_green'] ?? '') === ($toSave['clr_green'] ?? 'x'));
            if ($verify_match) {
                $msg = '✅ Gespeichert & verifiziert — DB-Wert stimmt mit gespeichertem überein.'; $msgType = 'ok';
            } else {
                $msg = '⚠️ Schreiben OK aber Verify schlüg fehl! DB hat: ' . htmlspecialchars(substr($verify_raw, 0, 200)); $msgType = 'error';
            }
            try { $pdo->exec("DELETE FROM wp_options WHERE option_name LIKE '_transient%wcr%'"); } catch (Exception $e) {}
        } else {
            $msg = '❌ Fehler beim Speichern in DB.'; $msgType = 'error';
        }
    }

    if ($action === 'reset') {
        if (dsc_save_opts($pdo, $DEFAULTS)) { $msg = 'Zurückgesetzt.'; $msgType = 'ok'; }
        else { $msg = 'Fehler beim Zurücksetzen.'; $msgType = 'error'; }
    }
}

$opts        = dsc_load_opts($pdo, $DEFAULTS);
$activeTheme = dsc_get_theme($pdo);

if (!function_exists('ov')) {
    function ov(array $o, string $k): string { return htmlspecialchars($o[$k] ?? ''); }
}
$dbStatus = ($opts !== $DEFAULTS) ? 'ok' : 'defaults';
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
<div class="status-banner <?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- 🔍 RAW DEBUG BANNER -->
<div style="background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:14px 18px;margin-bottom:18px;font-family:monospace;font-size:11px;color:#8b949e;">
  <div style="color:#58a6ff;font-weight:700;margin-bottom:8px;">🔍 RAW DB DEBUG (temporär)</div>
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">DB Write-Test:</td>
        <td style="color:#fff"><?= $_write_test_ok ? '✅ OK — Wert: '.htmlspecialchars($_write_test_val) : '❌ FEHLER — kann nicht schreiben!' ?></td></tr>
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">option_value Länge:</td>
        <td style="color:#fff"><?= $_raw_db_len ?> bytes <?= $_raw_db_len === 0 ? '⚠️ LEER!' : '' ?></td></tr>
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">unserialize() Typ:</td>
        <td style="color:#<?= $_v1_type === 'array' ? '7ee787' : 'ff7b72' ?>"><?= $_v1_type ?> <?= $_v1_type !== 'array' ? '❌ (kein Array!)' : '✅' ?></td></tr>
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">Raw (erste 300 Zeichen):</td>
        <td><code style="color:#e3b341;word-break:break-all"><?= htmlspecialchars(substr($_raw_db_value, 0, 300)) ?></code></td></tr>
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">POST Data:</td>
        <td><code style="color:#cba6f7;white-space:pre"><?= htmlspecialchars($_post_dump) ?></code></td></tr>
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">$opts[clr_green]:</td>
        <td style="color:#fff"><?= ov($opts,'clr_green') ?></td></tr>
    <tr><td style="color:#7ee787;padding:2px 8px 2px 0;white-space:nowrap">dbStatus:</td>
        <td style="color:#<?= $dbStatus==='ok'?'7ee787':'ff7b72' ?>"><?= $dbStatus ?></td></tr>
  </table>
</div>

<!-- BLOCK 1 — THEME -->
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
        <div class="tc-preview" style="background:<?= $bg ?>; font-family:'Segoe UI',system-ui">
          <?php if ($key==='aurora'): ?>
          <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 55% at 8% 15%,rgba(1,158,227,.28) 0%,transparent 58%),radial-gradient(ellipse 50% 50% at 92% 85%,rgba(103,148,103,.22) 0%,transparent 54%);pointer-events:none"></div>
          <?php elseif ($key==='glass'): ?>
          <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 65% at 15% 50%,rgba(103,148,103,.10) 0%,transparent 68%);pointer-events:none"></div>
          <?php endif; ?>
          <div style="position:relative;z-index:1;height:17%;display:flex;align-items:center;gap:5px;padding:0 7%;border-bottom:1px solid rgba(255,255,255,.05)">
            <div style="flex:1;height:1px;background:rgba(103,148,103,.4)"></div>
            <span style="font-size:5px;font-weight:700;letter-spacing:3px;white-space:nowrap;color:<?= $key==='aurora'?'#019ee3':'#679467' ?>">● WCR ●</span>
            <div style="flex:1;height:1px;background:rgba(103,148,103,.4)"></div>
          </div>
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
          <div style="position:relative;z-index:1;padding:3px 7%">
            <?php foreach(['Erdinger · 4,50 €','Corona · 4,00 €','Augustiner · 4,20 €'] as $li): ?>
            <div style="display:flex;justify-content:space-between;font-size:5px;color:rgba(238,238,238,.65);padding:1.5px 0"><?= $li ?></div>
            <?php endforeach; ?>
          </div>
        </div>
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

<!-- BLOCK 2 — EINSTELLUNGEN + VORSCHAU -->
<form method="POST" id="dsc-form">
<input type="hidden" name="action" value="save">
<div class="dsc-2col">
  <div class="dsc-left">
    <div class="dsc-block">
      <div class="dsc-block-title">🎨 Farben</div>
      <div class="dsc-block-sub">Gelten für alle drei Themes</div>
      <div class="clr-grid">
        <?php foreach ($COLORS as $k => [$label, $desc]): ?>
        <div class="clr-row">
          <div class="clr-sw-wrap">
            <input type="color" id="cp_<?= $k ?>" value="<?= ov($opts,$k) ?>" onchange="syncClr('<?= $k ?>',this.value)">
            <div class="clr-sw" id="sw_<?= $k ?>" style="background:<?= ov($opts,$k) ?>" onclick="document.getElementById('cp_<?= $k ?>').click()"></div>
          </div>
          <div class="clr-info">
            <div class="clr-label"><?= htmlspecialchars($label) ?></div>
            <div class="clr-desc"><?= htmlspecialchars($desc) ?></div>
          </div>
          <input type="text" class="clr-hex" id="ct_<?= $k ?>" name="<?= $k ?>" value="<?= ov($opts,$k) ?>" placeholder="<?= $DEFAULTS[$k] ?>" oninput="syncClrTxt('<?= $k ?>',this.value)">
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
          <input type="text" class="clr-hex" style="width:190px" name="clr_bg_glass" value="<?= ov($opts,'clr_bg_glass') ?>" placeholder="rgba(10,14,24,0.65)">
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
        <div class="font-prev" id="font-prev" style="font-family:'<?= ov($opts,'font_family') ?>',system-ui">Wake &amp; Camp — 12,50 €</div>
      </div>
    </div>
    <div class="dsc-actions">
      <button type="submit" class="btn-save">💾 Einstellungen speichern</button>
      <button type="button" class="btn-reset" onclick="if(confirm('Alle Farben auf Standard zurücksetzen?'))document.getElementById('rst-form').submit()">↩ Zurücksetzen</button>
    </div>
  </div>
  <div class="dsc-right">
    <div class="dsc-preview-wrap">
      <div class="prev-lbl">CSS (aktive Variablen)</div>
      <pre class="pv-css" id="pv-css"></pre>
    </div>
  </div>
</div>
</form>
<form method="POST" id="rst-form" style="display:none"><input type="hidden" name="action" value="reset"></form>

<style>
.dsc-block{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px;}
.dsc-block-title{font-size:14px;font-weight:700;margin:0 0 3px;}
.dsc-block-sub{font-size:12px;color:var(--text-muted);margin:0 0 16px;}
.theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.theme-card{all:unset;cursor:pointer;display:flex;flex-direction:column;border:2px solid var(--border-light);border-radius:12px;overflow:hidden;background:var(--bg-subtle);transition:border-color .18s,transform .15s;width:100%;}
.theme-card:hover{border-color:var(--border);transform:translateY(-2px);}
.theme-card--on{border-color:var(--primary)!important;}
.tc-preview{aspect-ratio:16/9;overflow:hidden;position:relative;}
.tc-info{display:flex;align-items:center;gap:10px;padding:10px 14px 4px;}
.tc-icon{font-size:18px;flex-shrink:0;}
.tc-name{font-size:14px;font-weight:700;color:var(--text-main);}
.tc-sub{font-size:11px;color:var(--text-muted);}
.tc-badge{margin-left:auto;padding:3px 10px;border-radius:20px;background:rgba(52,199,89,.12);color:#1a7a30;border:1px solid rgba(52,199,89,.3);font-size:10px;font-weight:700;flex-shrink:0;}
.tc-desc{font-size:11px;color:var(--text-muted);padding:0 14px 12px;line-height:1.45;}
.dsc-2col{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;margin-top:20px;}
.dsc-left{display:flex;flex-direction:column;}
.dsc-right{position:sticky;top:76px;}
.clr-grid{display:flex;flex-direction:column;gap:9px;}
.clr-row{display:flex;align-items:center;gap:11px;padding:8px 11px;border-radius:8px;background:var(--bg-subtle);}
.clr-sw-wrap{position:relative;flex-shrink:0;}
.clr-sw-wrap input[type=color]{position:absolute;opacity:0;width:0;height:0;}
.clr-sw{width:36px;height:36px;border-radius:7px;cursor:pointer;border:2px solid rgba(0,0,0,.08);box-shadow:0 2px 6px rgba(0,0,0,.14);}
.clr-info{flex:1;min-width:0;}
.clr-label{font-size:13px;font-weight:600;color:var(--text-main);}
.clr-desc{font-size:11px;color:var(--text-muted);}
.clr-hex{width:100px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-family:monospace;font-size:12px;background:var(--bg-card);color:var(--text-main);}
.font-row{display:flex;gap:12px;align-items:center;}
.font-row select{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-main);}
.font-prev{flex:1;padding:11px 14px;background:#12121e;color:#eee;border-radius:8px;font-size:18px;text-align:center;}
.dsc-actions{display:flex;gap:12px;}
.btn-save{flex:1;padding:13px;font-size:15px;font-weight:700;background:var(--primary);color:#fff;border:none;border-radius:10px;cursor:pointer;}
.btn-reset{padding:13px 16px;font-size:13px;font-weight:600;background:var(--bg-subtle);color:var(--text-main);border:1px solid var(--border);border-radius:10px;cursor:pointer;}
.pv-css{background:#0d1117;color:#7ee787;font-size:9px;padding:10px 12px;border-radius:7px;overflow-x:auto;white-space:pre;line-height:1.6;max-height:160px;overflow-y:auto;margin:0;}
</style>

<script>
var G={green:"<?= addslashes($opts['clr_green']) ?>",blue:"<?= addslashes($opts['clr_blue']) ?>",white:"<?= addslashes($opts['clr_white']) ?>",text:"<?= addslashes($opts['clr_text']) ?>",muted:"<?= addslashes($opts['clr_muted']) ?>",bg:"<?= addslashes($opts['clr_bg']) ?>",bgDark:"<?= addslashes($opts['clr_bg_dark']) ?>",bgGlass:"<?= addslashes($opts['clr_bg_glass']) ?>",font:"<?= addslashes($opts['font_family']) ?>"};
var KEY_MAP={clr_green:'green',clr_blue:'blue',clr_white:'white',clr_text:'text',clr_muted:'muted',clr_bg:'bg',clr_bg_dark:'bgDark'};
function syncClr(k,v){if(KEY_MAP[k])G[KEY_MAP[k]]=v;var sw=document.getElementById('sw_'+k);if(sw)sw.style.background=v;var ct=document.getElementById('ct_'+k);if(ct)ct.value=v;render();}
function syncClrTxt(k,v){if(KEY_MAP[k])G[KEY_MAP[k]]=v;try{var sw=document.getElementById('sw_'+k);if(sw)sw.style.background=v;}catch(e){}if(/^#[0-9a-fA-F]{6}$/.test(v)){var cp=document.getElementById('cp_'+k);if(cp)cp.value=v;}render();}
function updFont(f){G.font=f;document.getElementById('font-prev').style.fontFamily="'"+f+"',system-ui";render();}
function render(){var out=document.getElementById('pv-css');if(out)out.textContent=':root {\n  --clr-green:  '+G.green+';\n  --clr-blue:   '+G.blue+';\n  --clr-text:   '+G.text+';\n  --clr-bg:     '+G.bg+';\n}';}
document.addEventListener('DOMContentLoaded',function(){render();});
</script>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
