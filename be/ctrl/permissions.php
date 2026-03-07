<?php
/**
 * ctrl/permissions.php — Rechte-Matrix-Verwaltung (nur für cernal)
 * 
 * ZWECK:
 *  cernal kann granular steuern, welche Rollen welche Permissions haben.
 *  Änderungen werden sofort aktiv (Cache-Invalidierung).
 *  Sichere Fallbacks: cernal behält IMMER Vollzugriff (hardcoded).
 * 
 * SICHERHEIT:
 *  - require_login() + wcr_is_cernal() Check
 *  - CSRF-Schutz für alle POST-Aktionen
 *  - Validierung: Nur bekannte Permissions + Rollen
 *  - Hardcoded: cernal immer in allen Permissions
 *  - Matrix wird via REST API in wp_options gespeichert
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// API-Konstanten definieren (benötigt für wcr_load_permissions)
if (!defined('DSC_WP_API_BASE')) {
    define('DSC_WP_API_BASE', 'https://wcr-webpage.de/wp-json/wakecamp/v1');
}
if (!defined('DSC_WP_SECRET')) {
    define('DSC_WP_SECRET', 'WCR_DS_2026');
}

require_login();

// ── NUR CERNAL DARF DIESE SEITE SEHEN ──
if (!wcr_is_cernal()) {
    http_response_code(403);
    $pageTitle = 'Kein Zugriff';
    include __DIR__ . '/../inc/403.php';
    exit;
}

$message = '';
$msgType = '';

// ── POST-Handler mit CSRF-Schutz ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wcr_verify_csrf(false)) {
        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Rechte-Matrix speichern ──────────────────────────────────────────
        if ($action === 'save') {
            $newMatrix = [];
            
            // Alle Permissions durchgehen
            foreach (array_keys(WCR_DEFAULT_PERMISSIONS) as $perm) {
                $roles = [];
                
                // Checkboxen auslesen (Format: perm_PERMISSION_ROLE)
                foreach (WCR_ROLES as $role) {
                    $checkboxName = 'perm_' . $perm . '_' . $role;
                    if (isset($_POST[$checkboxName])) {
                        $roles[] = $role;
                    }
                }
                
                // SICHERHEIT: cernal IMMER hinzufügen (verhindert Aussperren)
                if (!in_array('cernal', $roles, true)) {
                    $roles[] = 'cernal';
                }
                
                $newMatrix[$perm] = $roles;
            }
            
            // Matrix speichern
            if (wcr_save_permissions($newMatrix)) {
                $message = '✅ Rechte-Matrix gespeichert. Änderungen sind sofort aktiv.';
                $msgType = 'ok';
            } else {
                $message = '❌ Fehler beim Speichern der Rechte-Matrix.';
                $msgType = 'error';
            }
        }

        // ── Auf Standard zurücksetzen ──────────────────────────────────────
        if ($action === 'reset') {
            // Custom-Matrix löschen (setzt Fallback auf Standard)
            $ch = curl_init(DSC_WP_API_BASE . '/options');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode([
                    'wcr_secret' => DSC_WP_SECRET,
                    'key'        => 'wcr_permissions_matrix',
                    'value'      => null, // Löschen durch null
                ], JSON_UNESCAPED_UNICODE),
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code === 200) {
                $message = '♻️ Rechte-Matrix auf Standard zurückgesetzt.';
                $msgType = 'ok';
            } else {
                $message = '❌ Fehler beim Zurücksetzen.';
                $msgType = 'error';
            }
        }
    }
}

// ── Aktuelle Matrix laden ──────────────────────────────────────────────────
$currentMatrix = wcr_load_permissions();
$isCustom      = wcr_has_custom_permissions();

// ── Permission-Metadaten für UI ──────────────────────────────────────────
$permissionMeta = [
    'edit_prices'   => ['icon' => '💶', 'label' => 'Preise bearbeiten',    'desc' => 'Preise ändern',                    'critical' => false],
    'edit_products' => ['icon' => '🍔', 'label' => 'Produkte verwalten',   'desc' => 'Drinks, Food, Cable, etc.',      'critical' => false],
    'edit_content'  => ['icon' => '🎥', 'label' => 'Content verwalten',    'desc' => 'Kino, Obstacles, etc.',          'critical' => false],
    'edit_tickets'  => ['icon' => '🎫', 'label' => 'Tickets bearbeiten',   'desc' => 'Ticket-Verwaltung',              'critical' => false],
    'view_times'    => ['icon' => '🕒', 'label' => 'Öffnungszeiten',       'desc' => 'Öffnungszeiten-Seite anzeigen',   'critical' => false],
    'view_media'    => ['icon' => '🖼️', 'label' => 'Media-Verwaltung',     'desc' => 'Media-Seite anzeigen',            'critical' => false],
    'view_ds'       => ['icon' => '📺', 'label' => 'Digital Signage',     'desc' => 'DS-Seiten-Vorschau',             'critical' => false],
    'manage_users'  => ['icon' => '👥', 'label' => 'Benutzerverwaltung',   'desc' => 'Benutzer anlegen/verwalten',     'critical' => true],
    'debug'         => ['icon' => '🔧', 'label' => 'Debug-Panel',          'desc' => 'Nur Cernal (hardcoded)',         'critical' => true],
    'toggle'        => ['icon' => '⏻️', 'label' => 'An/Aus schalten',      'desc' => 'Toggle-Funktion',                'critical' => false],
];

// ── Rollen-Metadaten ─────────────────────────────────────────────────────────
$roleMeta = [
    'cernal' => ['icon' => '🔧', 'color' => '#7c3aed', 'label' => 'Cernal', 'desc' => 'Vollzugriff (hardcoded)'],
    'admin'  => ['icon' => '👑', 'color' => '#0071e3', 'label' => 'Admin',  'desc' => 'Erweiterte Rechte'],
    'user'   => ['icon' => '👤', 'color' => '#34c759', 'label' => 'User',   'desc' => 'Basis-Rechte'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>🔐 Rechte-Matrix</title>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🔐 Rechte-Matrix</h1>
</div>

<?php if ($message): ?>
  <div class="status-banner <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Info-Banner -->
<div class="permissions-info-banner">
  <div class="pib-icon">🛡️</div>
  <div class="pib-content">
    <div class="pib-title">
      <?php if ($isCustom): ?>
        ⚙️ Custom-Rechte-Matrix aktiv
      <?php else: ?>
        ♻️ Standard-Rechte-Matrix (Fallback)
      <?php endif; ?>
    </div>
    <div class="pib-desc">
      <?php if ($isCustom): ?>
        Du hast individuelle Rechte konfiguriert. Änderungen werden sofort aktiv.
      <?php else: ?>
        Es ist keine Custom-Matrix gespeichert. Die Standard-Rechte sind aktiv.
      <?php endif; ?>
      <br>
      <strong>🔒 Sicherheit:</strong> Cernal behält immer Vollzugriff — auch wenn Checkboxen deaktiviert sind.
    </div>
  </div>
</div>

<!-- Rechte-Matrix -->
<form method="POST" class="permissions-form">
  <?= wcr_csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="permissions-table-wrapper">
    <table class="permissions-table">
      <thead>
        <tr>
          <th class="perm-col-permission">Berechtigung</th>
          <?php foreach ($roleMeta as $role => $rm): ?>
            <th class="perm-col-role" data-role="<?= htmlspecialchars($role) ?>">
              <span class="role-badge" data-role="<?= htmlspecialchars($role) ?>">
                <?= $rm['icon'] ?> <?= htmlspecialchars($rm['label']) ?>
              </span>
              <?php if ($role === 'cernal'): ?>
                <div class="role-hint">🔒 Hardcoded</div>
              <?php endif; ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($permissionMeta as $perm => $pm): 
          $isCritical = $pm['critical'];
          $currentRoles = $currentMatrix[$perm] ?? [];
        ?>
          <tr class="<?= $isCritical ? 'perm-row-critical' : '' ?>">
            <td class="perm-name-cell">
              <div class="perm-icon"><?= $pm['icon'] ?></div>
              <div class="perm-info">
                <div class="perm-label">
                  <?= htmlspecialchars($pm['label']) ?>
                  <?php if ($isCritical): ?>
                    <span class="critical-badge">⚠️ Kritisch</span>
                  <?php endif; ?>
                </div>
                <div class="perm-desc"><?= htmlspecialchars($pm['desc']) ?></div>
              </div>
            </td>
            <?php foreach (WCR_ROLES as $role): 
              $isChecked = in_array($role, $currentRoles, true);
              $isCernal  = ($role === 'cernal');
              $checkboxName = 'perm_' . $perm . '_' . $role;
            ?>
              <td class="perm-checkbox-cell" data-role="<?= htmlspecialchars($role) ?>">
                <label class="perm-checkbox-wrapper <?= $isCernal ? 'checkbox-locked' : '' ?>">
                  <input 
                    type="checkbox" 
                    name="<?= htmlspecialchars($checkboxName) ?>" 
                    <?= $isChecked ? 'checked' : '' ?>
                    <?= $isCernal ? 'disabled' : '' ?>
                    class="perm-checkbox"
                  >
                  <span class="perm-checkmark"><?= $isChecked ? '✅' : '❌' ?></span>
                  <?php if ($isCernal): ?>
                    <span class="locked-hint">🔒</span>
                  <?php endif; ?>
                </label>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Actions -->
  <div class="permissions-actions">
    <button type="submit" class="btn-upload btn-save">
      💾 Rechte-Matrix speichern
    </button>
    <button type="button" class="btn-secondary" onclick="if(confirm('Standard-Rechte wiederherstellen?')) { document.getElementById('reset-form').submit(); }">
      ♻️ Auf Standard zurücksetzen
    </button>
  </div>
</form>

<!-- Verstecktes Reset-Formular -->
<form method="POST" id="reset-form" style="display:none">
  <?= wcr_csrf_field() ?>
  <input type="hidden" name="action" value="reset">
</form>

<!-- Legende -->
<div class="permissions-legend">
  <h3>Legende</h3>
  <div class="legend-grid">
    <div class="legend-item">
      <span class="legend-icon">✅</span>
      <span class="legend-text">Berechtigung erteilt</span>
    </div>
    <div class="legend-item">
      <span class="legend-icon">❌</span>
      <span class="legend-text">Berechtigung verweigert</span>
    </div>
    <div class="legend-item">
      <span class="legend-icon">🔒</span>
      <span class="legend-text">Hardcoded (nicht änderbar)</span>
    </div>
    <div class="legend-item">
      <span class="legend-icon">⚠️</span>
      <span class="legend-text">Kritische Berechtigung</span>
    </div>
  </div>
</div>

<style>
/* ── Permissions-Seite Styles ─────────────────────────────────────────── */

/* Info-Banner */
.permissions-info-banner {
    background: rgba(var(--primary-rgb), var(--alpha-05));
    border: 1px solid rgba(var(--primary-rgb), var(--alpha-15));
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.pib-icon {
    font-size: 28px;
    line-height: 1;
    flex-shrink: 0;
}
.pib-content {
    flex: 1;
}
.pib-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 6px;
}
.pib-desc {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
}

/* Permissions-Tabelle */
.permissions-table-wrapper {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 20px;
}
.permissions-table {
    width: 100%;
    border-collapse: collapse;
}
.permissions-table thead {
    background: var(--bg-subtle);
}
.permissions-table th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    border-bottom: 2px solid var(--border);
}
.permissions-table th.perm-col-role {
    text-align: center;
}
.permissions-table th .role-hint {
    font-size: 10px;
    color: var(--text-light);
    font-weight: 500;
    margin-top: 4px;
    text-transform: none;
    letter-spacing: 0;
}

.permissions-table tbody tr {
    border-bottom: 1px solid var(--border-light);
    transition: background 0.15s;
}
.permissions-table tbody tr:hover {
    background: var(--bg-subtle);
}
.permissions-table tbody tr:last-child {
    border-bottom: none;
}

/* Kritische Zeilen */
.permissions-table tbody tr.perm-row-critical {
    background: rgba(var(--warning-rgb), var(--alpha-03));
}
.permissions-table tbody tr.perm-row-critical:hover {
    background: rgba(var(--warning-rgb), var(--alpha-05));
}

/* Permission-Name-Cell */
.perm-name-cell {
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.perm-icon {
    font-size: 24px;
    flex-shrink: 0;
}
.perm-info {
    flex: 1;
}
.perm-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 8px;
}
.perm-desc {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.critical-badge {
    font-size: 10px;
    background: rgba(var(--warning-rgb), var(--alpha-15));
    color: #a67c00;
    padding: 2px 8px;
    border-radius: var(--radius-pill);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Checkbox-Cells */
.perm-checkbox-cell {
    text-align: center;
    padding: 16px;
}
.perm-checkbox-cell[data-role="cernal"] {
    background: rgba(123, 58, 237, 0.03);
}
.perm-checkbox-cell[data-role="admin"] {
    background: rgba(0, 113, 227, 0.03);
}
.perm-checkbox-cell[data-role="user"] {
    background: rgba(52, 199, 89, 0.03);
}

/* Checkbox-Wrapper */
.perm-checkbox-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
}
.perm-checkbox-wrapper.checkbox-locked {
    cursor: not-allowed;
    opacity: 0.6;
}
.perm-checkbox {
    display: none;
}
.perm-checkmark {
    font-size: 20px;
    transition: transform 0.15s;
}
.perm-checkbox-wrapper:hover .perm-checkmark {
    transform: scale(1.15);
}
.perm-checkbox-wrapper.checkbox-locked:hover .perm-checkmark {
    transform: none;
}
.locked-hint {
    font-size: 14px;
    opacity: 0.5;
}

/* Actions */
.permissions-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
}
.btn-save {
    background: var(--success);
}
.btn-secondary {
    padding: 10px 20px;
    background: var(--bg-card);
    color: var(--text-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}
.btn-secondary:hover {
    background: var(--bg-subtle);
    border-color: var(--text-muted);
}

/* Legende */
.permissions-legend {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px;
    margin-top: 24px;
}
.permissions-legend h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
    margin: 0 0 14px;
}
.legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--text-muted);
}
.legend-icon {
    font-size: 18px;
}

/* Responsive */
@media (max-width: 900px) {
    .permissions-table-wrapper {
        overflow-x: auto;
    }
    .permissions-table {
        min-width: 700px;
    }
}
</style>

<script>
// Checkbox-Toggle mit visueller Bestätigung
document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const checkmark = this.parentElement.querySelector('.perm-checkmark');
        checkmark.textContent = this.checked ? '✅' : '❌';
    });
});

// Warnung bei kritischen Rechten
const criticalCheckboxes = document.querySelectorAll('.perm-row-critical .perm-checkbox');
criticalCheckboxes.forEach(cb => {
    cb.addEventListener('change', function() {
        if (!this.checked && !this.parentElement.classList.contains('checkbox-locked')) {
            const permName = this.closest('tr').querySelector('.perm-label').textContent.trim();
            if (!confirm(`⚠️ WARNUNG: Du entfernst eine kritische Berechtigung (${permName}). Fortfahren?`)) {
                this.checked = true;
                this.dispatchEvent(new Event('change'));
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
