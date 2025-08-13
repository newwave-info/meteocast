<?php
/**
 * ----------------------------------------------------------------------------
 *  api-tide.php – Lettura e cache variabili marine Open-Meteo (nuova versione)
 * ----------------------------------------------------------------------------
 * Scarica e cache i dati marine hourly (livello, onde, SST, direzione, periodo)
 * - End-point: /v1/marine
 * - Dati chiave: sea_level_height_msl, sea_surface_temperature, wave_height, wave_direction, wave_period (tutti hourly)
 * ----------------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';

// Check lat/lon
if (!defined('LATITUDE') || LATITUDE === null || !defined('LONGITUDE') || LONGITUDE === null) {
    $sea_level = $sea_temp = $wave_height = $wave_dir = $wave_period = '—';
    return;
}

// --- Costruzione URL API Open-Meteo Marine ---
function buildOpenMeteoMarineUrl(): string
{
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+".(MARINE_DAYS-1)." days"));
    $params = [
        'latitude'  => LATITUDE,
        'longitude' => LONGITUDE,
        'hourly'    => implode(',', [
            'sea_level_height_msl',
            'sea_surface_temperature',
            'wave_height',
            'wave_direction',
            'wave_period'
        ]),
        'start_date' => $start_date,
        'end_date'   => $end_date,
        'timezone'   => TIMEZONE
    ];
    return 'https://marine-api.open-meteo.com/v1/marine?' . http_build_query($params);
}

// --- Cache separata per la marea/marine! ---
$key = 'marine_' . cache_coord(LATITUDE) . '_' . cache_coord(LONGITUDE) . '_' . TIMEZONE;
$url = buildOpenMeteoMarineUrl();
$data = fetch_json_cached($url, $key, CACHE_TTL_MARINE);

// Debug: mostra sempre l’URL chiamato come commento HTML (puoi rimuovere dopo i test)
echo "<!-- TIDE API: $url -->";

// Se la risposta è vuota o malformata, fallback
if (
    !$data ||
    !isset($data['hourly']) ||
    !isset($data['hourly']['time'])
) {
    $sea_level = $sea_temp = $wave_height = $wave_dir = $wave_period = '—';
    return;
}

// --- Estrai dati orari: prendi il valore più vicino all’ora corrente ---
$curr_time = date('Y-m-d\TH:00', time());
$times = $data['hourly']['time'];

function value_now($arr_times, $arr_values, $curr_time) {
    $index = array_search($curr_time, $arr_times);
    if ($index === false) {
        // Prendi la più vicina nel passato
        $now_ts = strtotime($curr_time);
        $found = false;
        for ($i = count($arr_times) - 1; $i >= 0; $i--) {
            if (strtotime($arr_times[$i]) <= $now_ts) {
                $index = $i;
                $found = true;
                break;
            }
        }
        if (!$found) $index = 0;
    }
    return (isset($arr_values[$index]) && is_numeric($arr_values[$index]))
        ? $arr_values[$index]
        : '—';
}

// Variabili chiave marine
$sea_level    = isset($data['hourly']['sea_level_height_msl']) ? value_now($times, $data['hourly']['sea_level_height_msl'], $curr_time) : '—';
$sea_temp     = isset($data['hourly']['sea_surface_temperature']) ? value_now($times, $data['hourly']['sea_surface_temperature'], $curr_time) : '—';
$wave_height  = isset($data['hourly']['wave_height']) ? value_now($times, $data['hourly']['wave_height'], $curr_time) : '—';
$wave_dir     = isset($data['hourly']['wave_direction']) ? value_now($times, $data['hourly']['wave_direction'], $curr_time) : '—';
$wave_period  = isset($data['hourly']['wave_period']) ? value_now($times, $data['hourly']['wave_period'], $curr_time) : '—';

// Ora hai le variabili: $sea_level, $sea_temp, $wave_height, $wave_dir, $wave_period
// Pronte per essere usate nei tuoi widget/partial

// Esempio (puoi togliere questa parte, serve solo per test veloce):
/*
echo "<pre>";
echo "Livello mare attuale: $sea_level m\n";
echo "Temp. superficie mare: $sea_temp °C\n";
echo "Altezza onda: $wave_height m\n";
echo "Direzione onda: $wave_dir °\n";
echo "Periodo onda: $wave_period s\n";
echo "</pre>";
*/