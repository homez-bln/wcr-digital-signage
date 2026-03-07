<?php
/**
 * inc/auth.php — Session + Rollen-System v8
 * Rollen: cernal | admin | user
 *
 * Berechtigungsmatrix:
 *  edit_prices   → cernal, admin      (Preise ändern)
 *  edit_products → cernal, admin      (Produkte verwalten: Drinks, Food, Cable, etc.)
 *  edit_content  → cernal, admin      (Content verwalten: Kino, Obstacles, etc.)
 *  edit_tickets  → cernal, admin      (Tickets bearbeiten)
 *  view_times    → cernal, admin      (Öffnungszeiten-Seite)
 *  view_media    → cernal, admin      (Media-Seite)
 *  view_ds       → cernal, admin      (DS-Seiten-Vorschau)
 *  manage_users  → cernal, admin      (Benutzer anlegen/verwalten)
 *  debug         → cernal only        (Debug-Panel)
 *  toggle        → cernal, admin, user (An/Aus schalten)
 *
 * DB: be_users braucht Spalte `role` VARCHAR(20) DEFAULT 'user'
 *     SQL: ALTER TABLE be_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

define('WCR_SESSION_TIMEOUT', 8 * 3600);

const WCR_ROLES = ['cernal', 'admin', 'user'];

const WCR_PERMISSIONS = [
    // Preis-Management
    'edit_prices'   => ['cernal', 'admin'],
    
    // Content-Management
    'edit_products' => ['cernal', 'admin'],  // Drinks, Food, Cable, Camping, Ice, Extra
    'edit_content'  => ['cernal', 'admin'],  // Kino, Obstacles, etc.
    'edit_tickets'  => ['cernal', 'admin'],  // Ticket-Verwaltung
    
    // View-Permissions
    'view_times'    => ['cernal', 'admin'],  // Öffnungszeiten-Seite
    'view_media'    => ['cernal', 'admin'],  // Media-Verwaltung
    'view_ds'       => ['cernal', 'admin'],  // Digital Signage Seiten
    
    // System-Permissions
    'manage_users'  => ['cernal', 'admin'],  // User-Management
    'debug'         => ['cernal'],           // Debug-Panel (nur Cernal)
    'toggle'        => ['cernal', 'admin', 'user'], // An/Aus schalten (alle)
];

function login_user(int $user_id, string $role = 'user'): void {
    session_regenerate_id(true);
    $_SESSION['be_user_id']   = $user_id;
    $_SESSION['be_role']      = in_array($role, WCR_ROLES, true) ? $role : 'user';
    $_SESSION['be_last_seen'] = time();
}

function is_logged_in(): bool {
    if (empty($_SESSION['be_user_id']) || !is_int($_SESSION['be_user_id'])) return false;
    if ((time() - ($_SESSION['be_last_seen'] ?? 0)) > WCR_SESSION_TIMEOUT) {
        session_unset(); session_destroy(); return false;
    }
    $_SESSION['be_last_seen'] = time();
    return true;
}

function require_login(): void {
    if (!is_logged_in()) { header('Location: /be/login.php'); exit; }
}

function wcr_role(): string {
    return $_SESSION['be_role'] ?? 'user';
}

function wcr_user_id(): int {
    return (int)($_SESSION['be_user_id'] ?? 0);
}

function wcr_can(string $action): bool {
    $allowed = WCR_PERMISSIONS[$action] ?? [];
    return in_array(wcr_role(), $allowed, true);
}

function wcr_require(string $action): void {
    require_login();
    if (!wcr_can($action)) {
        http_response_code(403);
        $pageTitle = 'Kein Zugriff';
        include __DIR__ . '/403.php';
        exit;
    }
}

function wcr_is_admin(): bool {
    return in_array(wcr_role(), ['cernal', 'admin'], true);
}

function wcr_is_cernal(): bool {
    return wcr_role() === 'cernal';
}

function wcr_role_badge(string $role = ''): string {
    if ($role === '') $role = wcr_role();
    $cfg = [
        'cernal' => ['#7c3aed', 'Cernal'],
        'admin'  => ['#0071e3', 'Admin'],
        'user'   => ['#34c759', 'User'],
    ];
    $icons = ['cernal' => '🔧', 'admin' => '👑', 'user' => '👤'];
    $c = $cfg[$role] ?? ['#999', $role];
    $icon = $icons[$role] ?? '?';
    return '<span class="role-badge" data-role="' . htmlspecialchars($role) . '">'
         . $icon . ' ' . htmlspecialchars($c[1]) . '</span>';
}
