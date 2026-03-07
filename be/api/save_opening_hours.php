<?php
ob_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . "/../inc/db.php";
require_login();

ob_end_clean();
header('Content-Type: application/json');

// ── CSRF-Schutz ──
if (!wcr_verify_csrf_silent()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'CSRF-Token ungültig']));
}

// ── Input ──────────────────────────────────────────────────────────────────────
$body  = json_decode(file_get_contents('php://input'), true);
$datum = trim($body['datum'] ?? '');
$field = trim($body['field'] ?? '');
$value =      $body['value'] ?? '';
$preserveStart = trim($body['preserve_start_time'] ?? '');

// ── Validierung ──────────────────────────────────────────────────────────────────────
$allowedFields = ['start_time', 'end_time', 'course1', 'course2',
                  'course1_text', 'course2_text', 'is_closed'];

if (empty($datum) || !in_array($field, $allowedFields, true)) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
    exit;
}

// ── DB ──────────────────────────────────────────────────────────────────────
$db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);

if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Keine DB']);
    exit;
}

// PDO: Exceptions aktivieren damit Fehler sichtbar werden
if ($db instanceof PDO) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// ── SQL ──────────────────────────────────────────────────────────────────────
try {

    if ($db instanceof PDO) {

        if ($field === 'start_time' || $field === 'end_time') {
            // Zeitfelder: nur dieses Feld setzen
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`)
                     VALUES (:datum, :value)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $ok   = $stmt->execute([':datum' => $datum, ':value' => $value]);

        } else {
            // Kursfelder: start_time beim INSERT mitgeben (end_time NICHT)
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`, start_time)
                     VALUES (:datum, :value, :start_time)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $ok   = $stmt->execute([
                ':datum'      => $datum,
                ':value'      => $value,
                ':start_time' => $preserveStart,
            ]);
        }

        // ── Token nach erfolgreicher Rotation zurückgeben ──
        // wcr_verify_csrf_silent() hat bereits neues Token generiert,
        // Frontend muss es für nächsten Request aktualisieren
        echo json_encode([
            'success' => (bool)$ok,
            'csrf_token' => wcr_csrf_token()
        ]);

    } elseif ($db instanceof mysqli) {

        if ($field === 'start_time' || $field === 'end_time') {
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ss', $datum, $value);

        } else {
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`, start_time)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('sss', $datum, $value, $preserveStart);
        }

        $ok = $stmt->execute();
        
        // ── Token nach erfolgreicher Rotation zurückgeben ──
        echo json_encode([
            'success' => $ok,
            'csrf_token' => wcr_csrf_token(),
            'mysqli_error' => $db->error ?: null
        ]);
    }

} catch (Exception $e) {
    // Gibt den genauen SQL-Fehler zurück statt 500
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'sql'     => $sql ?? 'n/a'
    ]);
}
