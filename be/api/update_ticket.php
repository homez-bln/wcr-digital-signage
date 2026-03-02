<?php
/**
 * api/update_ticket.php
 * FIX v6: Nutzt wcr_pdo() Singleton.
 *         Kein Klartext-Fehler bei ungültiger Tabelle.
 */
header('Content-Type: application/json; charset=utf-8');

$expectedToken = '5f581e2655f5b36d05a8ad3db821e5da4d0a0ea4dfe66314dcab1dd86bb64ed3';
if (!hash_equals($expectedToken, (string)($_POST['token'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'forbidden']));
}

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

$allowed = ['cable', 'drinks', 'food', 'ice', 'camping', 'extra', 'wp_food_gruppen'];
$table   = trim($_POST['table']  ?? '');
$nr      = $_POST['nummer'] ?? '';
$mode    = trim($_POST['mode']   ?? '');

// v7: Preisänderungen nur für admin/cernal
if ($mode === 'price' && (!is_logged_in() || !wcr_can('edit_prices'))) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Keine Berechtigung: Preise ändern erfordert admin oder cernal']));
}
$val     = $_POST['value']  ?? '';

if (!in_array($table, $allowed, true)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Ungültige Tabelle']));
}

try {
    if ($mode === 'price') {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET preis = ? WHERE nummer = ?");
        $stmt->execute([(float)$val, (int)$nr]);

    } elseif ($mode === 'toggle') {
        $stock = ($val === '1' || $val === 'true') ? 1 : 0;
        $stmt  = $pdo->prepare("UPDATE `{$table}` SET stock = ? WHERE nummer = ?");
        $stmt->execute([$stock, (int)$nr]);

    } elseif ($mode === 'gruppe') {
        $typ   = trim($nr);
        $aktiv = ($val === '1') ? 1 : 0;
        $stmt  = $pdo->prepare("UPDATE wp_food_gruppen SET aktiv = ? WHERE typ = ?");
        $stmt->execute([$aktiv, $typ]);

    } else {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Unbekannter Modus']));
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB-Fehler']);
}
