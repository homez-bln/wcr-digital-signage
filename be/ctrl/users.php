<?php
/**
 * ctrl/users.php v7 — Benutzerverwaltung
 * Komplett neu geschrieben: defensiv, jede DB-Query in try/catch
 */

// Fehler in Log schreiben, NICHT anzeigen (verhindert 500 durch fatale Fehler)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('manage_users');

// ── Sicherstellen dass role-Spalte existiert ─────────────────────
// Kein IF NOT EXISTS (MySQL < 8.0 unterstützt das nicht)
try {
    $pdo->exec("ALTER TABLE be_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
} catch (Exception $e) {
    // Spalte existiert bereits – kein Problem
}

$message = '';
$msgType = '';

// ── Benutzer anlegen ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        $newUser = trim((string)($_POST['new_username'] ?? ''));
        $newPass = (string)($_POST['new_password'] ?? '');
        $newRole = (string)($_POST['new_role'] ?? 'user');

        $allowedRoles = wcr_is_cernal() ? ['cernal','admin','user'] : ['admin','user'];
        if (!in_array($newRole, $allowedRoles, true)) $newRole = 'user';

        if (strlen($newUser) < 3) {
            $message = 'Benutzername muss mind. 3 Zeichen haben.';
            $msgType = 'error';
        } elseif (strlen($newPass) < 8) {
            $message = 'Passwort muss mind. 8 Zeichen haben.';
            $msgType = 'error';
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM be_users WHERE username = ?');
            $chk->execute([$newUser]);
            if ((int)$chk->fetchColumn() > 0) {
                $message = "Benutzer \"{$newUser}\" existiert bereits.";
                $msgType = 'error';
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $ins  = $pdo->prepare('INSERT INTO be_users (username, password_hash, role, active) VALUES (?, ?, ?, 1)');
                $ins->execute([$newUser, $hash, $newRole]);
                $message = "Benutzer \"{$newUser}\" ({$newRole}) wurde angelegt.";
                $msgType = 'ok';
            }
        }
    } catch (Exception $e) {
        $message = 'Datenbankfehler: ' . $e->getMessage();
        $msgType = 'error';
    }
}

// ── Toggle active ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    try {
        $toggleId = (int)($_POST['user_id'] ?? 0);
        if ($toggleId === wcr_user_id()) {
            $message = 'Du kannst dich nicht selbst deaktivieren.';
            $msgType = 'error';
        } else {
            $pdo->prepare('UPDATE be_users SET active = 1 - active WHERE id = ?')->execute([$toggleId]);
            $message = 'Status geändert.';
            $msgType = 'ok';
        }
    } catch (Exception $e) {
        $message = 'Datenbankfehler: ' . $e->getMessage();
        $msgType = 'error';
    }
}

// ── Passwort zurücksetzen ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pw') {
    try {
        $resetId = (int)($_POST['user_id'] ?? 0);
        $newPw   = (string)($_POST['new_pw'] ?? '');
        if (strlen($newPw) < 8) {
            $message = 'Neues Passwort zu kurz (min. 8 Zeichen).';
            $msgType = 'error';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE be_users SET password_hash = ? WHERE id = ?')->execute([$hash, $resetId]);
            $message = 'Passwort geändert.';
            $msgType = 'ok';
        }
    } catch (Exception $e) {
        $message = 'Datenbankfehler: ' . $e->getMessage();
        $msgType = 'error';
    }
}

// ── Alle User laden ───────────────────────────────────────────────
// Zuerst prüfen ob role-Spalte existiert, dann Query anpassen
$users = [];
$roleColumnExists = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM be_users LIKE 'role'");
    $roleColumnExists = ($colCheck->rowCount() > 0);
} catch (Exception $e) {
    // ignorieren
}

try {
    if ($roleColumnExists) {
        $users = $pdo->query("SELECT id, username, role, active FROM be_users ORDER BY id ASC")->fetchAll();
    } else {
        $rows  = $pdo->query("SELECT id, username, active FROM be_users ORDER BY id ASC")->fetchAll();
        // Rolle als 'user' annehmen wenn Spalte fehlt
        foreach ($rows as &$r) $r['role'] = 'user';
        $users = $rows;
    }
} catch (Exception $e) {
    $message = 'Fehler beim Laden der Benutzer: ' . $e->getMessage();
    $msgType = 'error';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Benutzerverwaltung</title>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>👥 Benutzerverwaltung</h1>
</div>

<?php if ($message): ?>
  <div class="status-banner <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="user-mgmt-layout">

  <!-- LINKE SPALTE: Neuen User anlegen -->
  <div class="user-create-panel">
    <h3>➕ Neuen Benutzer anlegen</h3>
    <form method="POST" class="user-form">
      <input type="hidden" name="action" value="create">

      <label>Benutzername</label>
      <input type="text" name="new_username" minlength="3" required placeholder="z.B. jan">

      <label>Passwort</label>
      <input type="password" name="new_password" minlength="8" required placeholder="mind. 8 Zeichen">

      <label>Rolle</label>
      <select name="new_role">
        <?php if (wcr_is_cernal()): ?>
          <option value="cernal">🔧 Cernal (Vollzugriff + Debug)</option>
        <?php endif; ?>
        <option value="admin">👑 Admin (alles außer Debug)</option>
        <option value="user" selected>👤 User (nur Toggle)</option>
      </select>

      <button type="submit" class="btn-upload">Benutzer anlegen</button>
    </form>

    <div class="role-info">
      <div class="ri-title">Rollen-Übersicht</div>
      <div class="ri-row">
        <span class="role-badge" data-role="cernal">🔧 Cernal</span>
        <span>Vollzugriff + Debug-Panel</span>
      </div>
      <div class="ri-row">
        <span class="role-badge" data-role="admin">👑 Admin</span>
        <span>Preise, Zeiten, Media, Users</span>
      </div>
      <div class="ri-row">
        <span class="role-badge" data-role="user">👤 User</span>
        <span>Nur An/Aus-Toggle</span>
      </div>
    </div>
  </div>

  <!-- RECHTE SPALTE: Benutzerliste -->
  <div class="user-list-panel">
    <h3>Aktuelle Benutzer (<?= count($users) ?>)</h3>
    <?php if (empty($users) && $msgType !== 'error'): ?>
      <p style="color:#86868b; padding:20px 0;">Keine Benutzer gefunden.</p>
    <?php else: ?>
    <div class="user-table-wrap">
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Benutzername</th>
            <th>Rolle</th>
            <th>Status</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
              $uId      = (int)$u['id'];
              $uRole    = (string)($u['role'] ?? 'user');
              $isActive = (bool)$u['active'];
              $isSelf   = ($uId === wcr_user_id());
              // Admins sehen keine cernal-Accounts
              if ($uRole === 'cernal' && !wcr_is_cernal()) continue;
          ?>
          <tr class="<?= !$isActive ? 'user-inactive' : '' ?>">
            <td class="u-id"><?= $uId ?></td>
            <td class="u-name">
              <?= htmlspecialchars((string)$u['username']) ?>
              <?php if ($isSelf): ?><span class="u-self-badge">Du</span><?php endif; ?>
            </td>
            <td><?= wcr_role_badge($uRole) ?></td>
            <td>
              <?php if ($isActive): ?>
                <span class="u-status-on">● Aktiv</span>
              <?php else: ?>
                <span class="u-status-off">● Inaktiv</span>
              <?php endif; ?>
            </td>
            <td class="u-actions">
              <?php if (!$isSelf): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="toggle">
                <input type="hidden" name="user_id" value="<?= $uId ?>">
                <button type="submit" class="u-btn <?= $isActive ? 'danger' : 'success' ?>">
                  <?= $isActive ? 'Deaktivieren' : 'Aktivieren' ?>
                </button>
              </form>
              <?php endif; ?>
              <button class="u-btn secondary"
                onclick="showPwReset(<?= $uId ?>, '<?= htmlspecialchars(addslashes((string)$u['username'])) ?>')">
                🔑 PW
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- PW-Reset Modal -->
<div id="pw-modal" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <h3>🔑 Passwort zurücksetzen</h3>
    <p id="pw-modal-user" style="color:#86868b; font-size:13px; margin-bottom:16px;"></p>
    <form method="POST">
      <input type="hidden" name="action"  value="reset_pw">
      <input type="hidden" name="user_id" id="pw-modal-id">
      <input type="password" name="new_pw" placeholder="Neues Passwort (min. 8 Zeichen)"
             minlength="8" required
             style="width:100%;padding:10px;border:1px solid #d2d2d7;border-radius:8px;font-size:14px;margin-bottom:14px;box-sizing:border-box;">
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-upload" style="flex:1">Speichern</button>
        <button type="button"
                onclick="document.getElementById('pw-modal').style.display='none'"
                style="flex:1;padding:10px;border:1px solid #d2d2d7;border-radius:8px;background:#fff;cursor:pointer;font-size:14px;">
          Abbrechen
        </button>
      </div>
    </form>
  </div>
</div>

<style>
.user-mgmt-layout   { display:grid; grid-template-columns:340px 1fr; gap:24px; align-items:start; }
.user-create-panel,
.user-list-panel    { background:var(--bg-card); border-radius:var(--radius); box-shadow:var(--shadow); padding:24px; }
.user-create-panel h3,
.user-list-panel h3 { font-size:16px; font-weight:700; margin:0 0 20px; }
.user-form label    { display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#555; margin:14px 0 6px; }
.user-form label:first-of-type { margin-top:0; }
.user-form input,
.user-form select   { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; outline:none; box-sizing:border-box; }
.user-form input:focus,
.user-form select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,113,227,.1); }
.user-form .btn-upload { width:100%; margin-top:18px; }
.role-info         { margin-top:24px; padding-top:18px; border-top:1px solid var(--border-light); }
.ri-title          { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--text-light); margin-bottom:12px; }
.ri-row            { display:flex; align-items:center; gap:10px; padding:8px 0; font-size:13px; border-bottom:1px solid var(--border-xlight); }
.ri-row:last-child { border-bottom:none; }
.user-table-wrap   { overflow-x:auto; }
.user-table        { width:100%; border-collapse:collapse; }
.user-table th     { text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); padding:8px 12px; border-bottom:2px solid var(--border-light); }
.user-table td     { padding:10px 12px; border-bottom:1px solid var(--border-xlight); vertical-align:middle; font-size:14px; }
tr.user-inactive td { opacity:.45; }
.u-id              { color:var(--text-muted); font-size:12px; width:40px; }
.u-name            { font-weight:600; }
.u-self-badge      { font-size:10px; background:#0071e322; color:#0071e3; border-radius:4px; padding:1px 6px; margin-left:6px; font-weight:700; }
.u-status-on       { color:#34c759; font-size:13px; font-weight:600; }
.u-status-off      { color:#ff3b30; font-size:13px; font-weight:600; }
.u-actions         { display:flex; gap:6px; align-items:center; white-space:nowrap; }
.u-btn             { padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:opacity .15s; white-space:nowrap; }
.u-btn.danger      { background:#fff0f0; color:#c0392b; border-color:#ffd0cc; }
.u-btn.success     { background:#f0fff4; color:#1c7c34; border-color:#b7f5c4; }
.u-btn.secondary   { background:var(--bg-subtle); color:var(--text-main); border-color:var(--border); }
.u-btn:hover       { opacity:.75; }
.modal-overlay     { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:9000; }
.modal-box         { background:#fff; border-radius:14px; padding:28px; width:380px; max-width:90vw; box-shadow:0 8px 40px rgba(0,0,0,.2); }
.modal-box h3      { margin:0 0 8px; font-size:17px; }
@media (max-width: 900px) { .user-mgmt-layout { grid-template-columns:1fr; } }
</style>

<script>
function showPwReset(id, name) {
    document.getElementById('pw-modal-id').value = id;
    document.getElementById('pw-modal-user').textContent = 'Passwort für: ' + name;
    document.getElementById('pw-modal').style.display = 'flex';
}
document.getElementById('pw-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
