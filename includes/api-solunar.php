<?php
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/solunar.php';
require_once ROOT_PATH . '/includes/cache.php';

header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 45.43;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : 12.33;
$days = isset($_GET['days']) ? min((int)$_GET['days'], 14) : 7;

$key = md5(round($lat, 4) . '-' . round($lon, 4) . '-solunar');
$cacheFile = ROOT_PATH . '/cache/' . $key . '.json';
$ttl = 12 * 3600; // 12h

// Cache first
if (file_exists($cacheFile) && (filemtime($cacheFile) > time() - $ttl)) {
    echo file_get_contents($cacheFile);
    exit;
}

$now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
$data = [];

for ($i = 0; $i < $days; $i++) {
    $date = $now->modify("+$i days")->format('Y-m-d');
    $entry = calculate_solunar_periods($lat, $lon, $date);
    $entry['date'] = $date;
    $data[] = $entry;
}

file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
echo json_encode($data);