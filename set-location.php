<?php
/**
 * ----------------------------------------------------------------------------
 *  API – Aggiornamento posizione utente (set-location.php)
 * ----------------------------------------------------------------------------
 *   – Riceve coordinate (e nome opzionale) via POST JSON da JS client
 *   – Risolve nome, quota DEM e timezone server-side (cacheata)
 *   – Salva tutto in sessione/cookie per 7 giorni
 *   – Imposta modalità posizione ('manual' o 'gps')
 * ----------------------------------------------------------------------------
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/geocoding.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !isset($payload['lat'], $payload['lon'])) {
    http_response_code(400); echo '{"error":"missing lat/lon"}'; exit;
}

$lat = (float)$payload['lat'];
$lon = (float)$payload['lon'];
if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    http_response_code(422); echo '{"error":"coords out of range"}'; exit;
}

$data    = get_location_data_from_coords($lat, $lon);

$locName = $data['name'];
$elev    = $data['elev'];
$tz      = $data['timezone'] ?? 'Europe/Rome';

$_SESSION['user_lat'] = round_coord($lat);
$_SESSION['user_lon'] = round_coord($lon);
$_SESSION['user_location_name']  = $locName;
$_SESSION['user_elev']           = $elev;
$_SESSION['user_timezone']       = $tz;
$_SESSION['loc_mode'] = !empty($payload['name']) ? 'manual' : 'gps';

setcookie('user_lat', round_coord($lat), time() + USER_LOCATION_TTL, '/');
setcookie('user_lon', round_coord($lon), time() + USER_LOCATION_TTL, '/');
setcookie('user_location_name', $locName, time() + USER_LOCATION_TTL, '/');
setcookie('user_elev', $elev ?? '', time() + USER_LOCATION_TTL, '/');
setcookie('user_timezone', $tz, time() + USER_LOCATION_TTL, '/');