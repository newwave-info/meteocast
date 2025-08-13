<?php
// includes/api-forecast-15min.php
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';
require_once ROOT_PATH . '/includes/geocoding.php';

if (!defined('LATITUDE') || LATITUDE === null || !defined('LONGITUDE') || LONGITUDE === null) return;

function buildOpenMeteo15minUrl(): string
{
    // Calcola gli slot necessari (4 slot per ora)
    $slots_needed = FORECAST_15MIN_HOURS * 4;

    $params = [
        'latitude' => LATITUDE,
        'longitude' => LONGITUDE,
        'timezone' => TIMEZONE,
        'minutely_15' => implode(',', [
            'precipitation',
            'weather_code',
            'wind_speed_10m',
            'wind_gusts_10m',
            'temperature_2m',
            //'apparent_temperature',
            'wind_direction_10m'
        ]),
        'forecast_days' => 2,
        'forecast_minutely_15' => $slots_needed, // Dinamico basato su costante config
    ];
    return 'https://api.open-meteo.com/v1/forecast?' . http_build_query($params);
}

$key = 'forecast15min_' . cache_coord(LATITUDE) . '_' . cache_coord(LONGITUDE) . '_' . TIMEZONE;
$url = buildOpenMeteo15minUrl();


$data15 = fetch_json_cached($url, $key, defined('CACHE_TTL_FORECAST_15MIN') ? CACHE_TTL_FORECAST_15MIN : 1200);

if ($data15) {
    if (isset($data15['minutely_15'])) {
        if (isset($data15['minutely_15']['time'])) {
        }
    }
}

if (!$data15 || !isset($data15['minutely_15'])) {
    $minutely_15_timestamps            = [];
    $minutely_15_temperature           = [];
    $minutely_15_apparent_temperature  = [];
    $minutely_15_precipitation         = [];
    $minutely_15_weather_code          = [];
    $minutely_15_wind_speed            = [];
    $minutely_15_wind_gusts            = [];
    $minutely_15_wind_direction        = [];
} else {
    $minutely_15 = $data15['minutely_15'];
    $minutely_15_timestamps            = $minutely_15['time'] ?? [];
    $minutely_15_temperature           = $minutely_15['temperature_2m'] ?? [];
    $minutely_15_apparent_temperature  = $minutely_15['apparent_temperature'] ?? [];
    $minutely_15_precipitation         = $minutely_15['precipitation'] ?? [];
    $minutely_15_weather_code          = $minutely_15['weather_code'] ?? [];
    $minutely_15_wind_speed            = $minutely_15['wind_speed_10m'] ?? [];
    $minutely_15_wind_gusts            = $minutely_15['wind_gusts_10m'] ?? [];
    $minutely_15_wind_direction        = $minutely_15['wind_direction_10m'] ?? [];
    
}