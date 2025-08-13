<?php
/**
 * ----------------------------------------------------------------------------
 *  api-fetch.php – Lettura (o cache) delle previsioni Open-Meteo
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Scarica le previsioni “core” (current, hourly, daily) per la località selezionata
 *   – Usa una cache disco (TTL 3h) per restare entro i limiti di quota API e migliorare performance
 *   – Fornisce tutte le variabili di stato pronte per i template PHP/HTML
 *
 * Flusso dettagliato:
 *   1. Costruisce l’URL API Open-Meteo in base a LATITUDE/LONGITUDE, giorni, timezone (da config.php)
 *   2. Calcola la chiave cache univoca e tenta il fetch tramite fetch_json_cached()
 *      – Se la cache è valida (<= 3h), viene usata la risposta locale
 *      – Altrimenti scarica live e salva in cache
 *   3. Esegue parsing strutturato per attuali, orari, giornalieri, ecc.
 *   4. Espone variabili pronte: $current_temp, $hourly_temperature, $daily_max_temps, ecc.
 *   5. Gestisce fallback robusto: se una chiave manca, lo script si interrompe con errore mirato
 *
 * Parametri cache:
 *   – Chiave: "forecast_<lat>_<lon>_<days>_<timezone>"
 *   – TTL: 3 ore (60*60*3)
 *   – Tutto gestito da fetch_json_cached() (cache.php)
 *
 * Dipendenze:
 *   – config/config.php     (costanti globali, user-locale, ecc.)
 *   – includes/cache.php    (API cache_get/put/fetch_json_cached)
 *
 * Output:
 *   – Variabili PHP per header, widget, grafici, ecc. (tutto ready-to-use)
 *   – Gestione centralizzata della quota, nessun doppio parsing JSON
 *
 * Uso:
 *   – Include questo file dove serve lo stato meteo della location corrente
 *   – Si adatta automaticamente alla posizione impostata (via sessione/cookie)
 * ----------------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';
require_once ROOT_PATH . '/includes/geocoding.php';

/* -------------------------------------------------
 *  Costruzione URL API
 * ------------------------------------------------- */
if (!defined('LATITUDE') || LATITUDE === null || !defined('LONGITUDE') || LONGITUDE === null) {
    return;
}

function buildOpenMeteoUrl(): string
{
    $params = [
        'latitude'      => LATITUDE,
        'longitude'     => LONGITUDE,
        'forecast_days' => FORECAST_DAYS,
        'timezone'      => TIMEZONE,
        'hourly'        => implode(',', [
            'weather_code','temperature_2m','apparent_temperature', 'wind_speed_10m','wind_gusts_10m','wind_direction_10m', 'precipitation','precipitation_probability','visibility', 'relative_humidity_2m','cloud_cover','uv_index','pressure_msl'
        ]),
        'daily'         => implode(',', [
            'temperature_2m_max','temperature_2m_min', 'sunrise','sunset','precipitation_sum','weather_code'
        ]),
        'current'       => implode(',', [
            'temperature_2m','apparent_temperature','weather_code', 'wind_speed_10m','wind_gusts_10m','wind_direction_10m', 'relative_humidity_2m','uv_index','pressure_msl'
        ]),
    ];

    if (defined('OPEN_METEO_MODEL') && OPEN_METEO_MODEL) {
        $params['models'] = OPEN_METEO_MODEL;
    }

    return 'https://api.open-meteo.com/v1/forecast?' . http_build_query($params);
}

/* -------------------------------------------------
 *  Download + cache (TTL)
 * ------------------------------------------------- */
$url  = buildOpenMeteoUrl();
$key  = 'forecast_' . cache_coord(LATITUDE) . '_' . cache_coord(LONGITUDE) . '_' . FORECAST_DAYS . '_' . TIMEZONE;

$data = fetch_json_cached($url, $key, CACHE_TTL_FORECAST);
if (!$data) die('Errore API / cache');

/* -------------------------------------------------
 *  Parsing dati meteo
 * ------------------------------------------------- */
$current = $data['current'] ?? die('Dati current mancanti');
$hourly  = $data['hourly']  ?? die('Dati hourly mancanti');
$daily   = $data['daily']   ?? die('Dati daily mancanti');

/* === Attuali === */
$current_datetime        = str_replace('T', ' ', $current['time'] ?? '');
$current_apparent_temp   = $current['apparent_temperature'] ?? null;
$current_temp            = $current['temperature_2m'] ?? null;
$current_code            = $current['weather_code'] ?? null;
$current_wind_speed      = $current['wind_speed_10m'] ?? null;
$current_wind_gusts      = $current['wind_gusts_10m'] ?? null;
$current_wind_direction  = $current['wind_direction_10m'] ?? null;
$current_humidity        = $current['relative_humidity_2m'] ?? null;
$current_uv_index        = $current['uv_index'] ?? null;
$current_pressure        = $current['pressure_msl'] ?? null;

/* === Orari === */
$timestamps                   = $hourly['time']                    ?? [];
$hourly_temperature           = $hourly['temperature_2m']          ?? [];
$hourly_apparent_temperature  = $hourly['apparent_temperature']    ?? [];
$hourly_wind_speed            = $hourly['wind_speed_10m']          ?? [];
$hourly_wind_direction        = $hourly['wind_direction_10m']      ?? [];
$hourly_weather_codes         = $hourly['weather_code']            ?? [];
$hourly_precip                = $hourly['precipitation']           ?? [];
$hourly_precip_prob           = $hourly['precipitation_probability'] ?? [];
$hourly_wind_gusts            = $hourly['wind_gusts_10m']          ?? [];
$hourly_cloud_cover           = $hourly['cloud_cover']             ?? [];
$hourly_visibility            = $hourly['visibility']              ?? [];
$hourly_uv_index              = $hourly['uv_index']                ?? [];
$hourly_humidity              = $hourly['relative_humidity_2m']    ?? [];
$hourly_pressure              = $hourly['pressure_msl']            ?? [];

/* === Giornalieri === */
$daily_max_temps      = $daily['temperature_2m_max'] ?? [];
$daily_min_temps      = $daily['temperature_2m_min'] ?? [];
$daily_sunrise_times  = $daily['sunrise']            ?? [];
$daily_sunset_times   = $daily['sunset']             ?? [];
$daily_daily_precip   = $daily['precipitation_sum']  ?? [];
$daily_weather_codes  = $daily['weather_code']       ?? [];

/* === Giorno o notte (solo oggi) === */
$isNight = false;
if ($daily_sunrise_times && $daily_sunset_times) {
    $now     = new DateTime('now', new DateTimeZone(TIMEZONE));
    $sunrise = new DateTime($daily_sunrise_times[0], new DateTimeZone(TIMEZONE));
    $sunset  = new DateTime($daily_sunset_times[0],  new DateTimeZone(TIMEZONE));
    $isNight = ($now < $sunrise || $now > $sunset);
}

/* -------------------------------------------------
 *  TAGLIO DINAMICO: orari solo dopo gli ultimi 15min
 * ------------------------------------------------- */
if (isset($minutely_15_timestamps) && !empty($minutely_15_timestamps)) {
    $last_15min_time = end($minutely_15_timestamps); // es: '2024-08-01T16:45'
    $start_index = null;
    foreach ($timestamps as $i => $ts) {
        if ($ts > $last_15min_time) {
            $start_index = $i;
            break;
        }
    }
    if ($start_index !== null) {
        $timestamps                  = array_slice($timestamps, $start_index);
        $hourly_temperature          = array_slice($hourly_temperature, $start_index);
        $hourly_apparent_temperature = array_slice($hourly_apparent_temperature, $start_index);
        $hourly_wind_speed           = array_slice($hourly_wind_speed, $start_index);
        $hourly_wind_direction       = array_slice($hourly_wind_direction, $start_index);
        $hourly_weather_codes        = array_slice($hourly_weather_codes, $start_index);
        $hourly_precip               = array_slice($hourly_precip, $start_index);
        $hourly_precip_prob          = array_slice($hourly_precip_prob, $start_index);
        $hourly_wind_gusts           = array_slice($hourly_wind_gusts, $start_index);
        $hourly_cloud_cover          = array_slice($hourly_cloud_cover, $start_index);
        $hourly_visibility           = array_slice($hourly_visibility, $start_index);
        $hourly_uv_index             = array_slice($hourly_uv_index, $start_index);
        $hourly_humidity             = array_slice($hourly_humidity, $start_index);
        $hourly_pressure             = array_slice($hourly_pressure, $start_index);
        // ...aggiungi qui eventuali altri array hourly che usi!
    }
}

// Calcola il nuovo now_index, sempre rispetto ai dati "tagliati"
$now_index = 0;
if (!empty($timestamps)) {
    $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
    foreach ($timestamps as $i => $ts) {
        $dt = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        if ($dt >= $now) {
            $now_index = $i;
            break;
        }
    }
}

