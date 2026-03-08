<?php
/**
 * update_ticket.php — Proxy zu api/update_ticket.php
 *
 * SECURITY v9: Erfordert Login + edit_products/edit_tickets Permission + CSRF Token
 *
 * FIX v6: Leitet direkt an die API weiter. Zusätzlich:
 *   Sendet nach erfolgreichem Update einen WP-REST-Request um den
 *   WordPress-Transient-Cache zu leeren, damit Preisänderungen
 *   sofort auf den Screens erscheinen (nicht erst nach 15 Min).
 *
 * FIX v10: Neues CSRF-Token wird IMMER zurückgegeben (auch bei Fehler),
 *          damit Token-Rotation nicht abbricht.
 *
 * FIX v11: Gruppen-Tabellen (wp_food_gruppen, wp_drinks_gruppen) benötigen
 *          edit_products Permission.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

// ── SECURITY: Login + Permission + CSRF erforderlich ──
// FIX: Permission je nach Tabelle prüfen
$table = $_POST['table'] ?? '';
if (in_array($table, ['ice', 'cable', 'camping', 'extra', 'food', 'drinks', 'wp_food_gruppen', 'wp_drinks_gruppen'], true)) {
    wcr_require('edit_products');
} else {
    wcr_require('edit_tickets');
}

// CSRF-Prüfung (rotiert Token automatisch)
wcr_verify_csrf(); // Exit mit 403 bei ungültigem Token

header('Content-Type: application/json; charset=utf-8');

$API_URL = 'https://www.wcr-webpage.de/be/api/update_ticket.php';
$TOKEN   = '5f581e2655f5b36d05a8ad3db821e5da4d0a0ea4dfe66314dcab1dd86bb64ed3';

$postData          = $_POST;
$postData['token'] = $TOKEN;

$ch = curl_init($API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
]);
$response = curl_exec($ch);
$err      = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode([
        'ok' => false, 
        'error' => 'Proxy-Fehler: ' . $err,
        'csrf_token' => wcr_csrf_token() // Token auch bei Fehler zurückgeben
    ]);
    exit;
}

$data = json_decode($response, true);

// FIX: Nach erfolgreichem Update → WP-Transient-Cache leeren
// damit Screens sofort neue Preise zeigen (nicht nach 15 min warten)
if (!empty($data['ok'])) {
    $nummer = $_POST['nummer'] ?? '';
    $mode   = $_POST['mode']   ?? '';

    if ($mode === 'price' || $mode === 'toggle' || $mode === 'gruppe') {
        // REST-Endpoint im WP-Plugin aufrufen (wcr/v1/flush-cache)
        $flush = curl_init('https://www.wcr-webpage.de/wp-json/wcr/v1/flush-cache');
        curl_setopt_array($flush, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['table' => $table, 'nummer' => $nummer]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['X-WCR-Token: ' . $TOKEN],
        ]);
        curl_exec($flush); // Ergebnis ignorieren – non-blocking ist genug
        curl_close($flush);
    }
}

// FIX v10: Neues CSRF-Token IMMER zurückgeben (auch bei API-Fehler)
// wcr_verify_csrf() hat Token bereits rotiert, Frontend muss es speichern.
$data['csrf_token'] = wcr_csrf_token();

if ($httpCode === 403) {
    echo json_encode(['ok' => false, 'error' => 'Server blockiert Anfrage (403)', 'csrf_token' => wcr_csrf_token()]);
} else {
    echo json_encode($data);
}
