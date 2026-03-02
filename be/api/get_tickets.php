<?php
/**
 * api/get_tickets.php
 * Extern zugänglicher Endpunkt (für das WP-Plugin).
 * FIX v6: Nutzt wcr_pdo() Singleton statt neue PDO-Verbindung.
 */
header('Content-Type: application/json; charset=utf-8');

$expectedToken = '5f581e2655f5b36d05a8ad3db821e5da4d0a0ea4dfe66314dcab1dd86bb64ed3';
if (!hash_equals($expectedToken, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'forbidden']));
}

require_once __DIR__ . '/../inc/db.php';

$allowed = ['cable', 'drinks', 'food', 'ice', 'camping', 'extra'];
$table   = trim($_GET['table'] ?? '');

if (!in_array($table, $allowed, true)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Ungültige Tabelle']));
}

try {
    $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY typ ASC, nummer ASC")->fetchAll();
    echo json_encode(['ok' => true, 'table' => $table, 'count' => count($rows), 'tickets' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB-Fehler']);
}
