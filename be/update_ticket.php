<?php
/**
 * update_ticket.php — Proxy zu api/update_ticket.php
 *
 * SECURITY v9: Erfordert Login + edit_tickets Permission + CSRF Token
 *
 * FIX v6: Leitet direkt an die API weiter. Zusätzlich:
 *   Sendet nach erfolgreichem Update einen WP-REST-Request um den
 *   WordPress-Transient-Cache zu leeren, damit Preisänderungen
 *   sofort auf den Screens erscheinen (nicht erst nach 15 Min).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

// ── SECURITY: Login + Permission + CSRF erforderlich ──
wcr_require('edit_tickets');
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
    echo json_encode(['ok' => false, 'error' => 'Proxy-Fehler: ' . $err]);
    exit;
}

$data = json_decode($response, true);

// FIX: Nach erfolgreichem Update → WP-Transient-Cache leeren
// damit Screens sofort neue Preise zeigen (nicht nach 15 min warten)
if (!empty($data['ok'])) {
    $table  = $_POST['table']  ?? '';
    $nummer = $_POST['nummer'] ?? '';
    $mode   = $_POST['mode']   ?? '';

    if ($mode === 'price' || $mode === 'toggle') {
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

if ($httpCode === 403) {
    echo json_encode(['ok' => false, 'error' => 'Server blockiert Anfrage (403)']);
} else {
    echo $response;
}
