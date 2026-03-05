<?php
/**
 * be/api/save_map_config.php
 * Proxy: sendet lat/lon/zoom + wcr_secret per cURL
 * an den WP REST-Endpoint /wakecamp/v1/obstacles/map-config
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/auth.php';
require_login();

$WP_API  = 'https://wcr-webpage.de/wp-json/wakecamp/v1/obstacles/map-config';
$SECRET  = 'WCR_DS_2026';

$lat  = isset($_POST['lat'])  ? (float)$_POST['lat']  : null;
$lon  = isset($_POST['lon'])  ? (float)$_POST['lon']  : null;
$zoom = isset($_POST['zoom']) ? (float)$_POST['zoom'] : null;

if ($lat === null || $lon === null || $zoom === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fehlende Parameter']);
    exit;
}

$payload = json_encode(['lat' => $lat, 'lon' => $lon, 'zoom' => $zoom, 'wcr_secret' => $SECRET]);

$ch = curl_init($WP_API);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err || $code !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $err ?: "HTTP $code", 'body' => $body]);
    exit;
}

$json = json_decode($body, true);
echo json_encode($json && isset($json['ok']) ? $json : ['ok' => true]);
