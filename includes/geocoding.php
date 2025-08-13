<?php
/**
 * ---------------------------------------------------------------------------
 * Geocoding centralizzato con cache disco e fallback multiplo
 * ---------------------------------------------------------------------------
 *
 * Funzioni chiave:
 *   - get_location_data_from_coords(lat, lon, lang)
 *       → [ 'name' => ..., 'elev' => ... ]
 *         ▸ Cache prioritaria (locdata_*)
 *         ▸ Nome via Open-Meteo reverse, fallback Nominatim
 *         ▸ Quota solo DEM Open-Meteo
 *         ▸ Timezone via Open-Meteo reverse, fallback TimezoneDB
 *         ▸ Cache 30 giorni
 *
 *   - get_elevation_only(lat, lon)
 *       → float|null
 *         ▸ Solo quota DEM (Open-Meteo), cache 30 giorni.
 *
 *   - get_location_name_from_coords(lat, lon, lang)
 *       → string|null
 *         ▸ Solo nome (Nominatim), cache 30 giorni.
 *         ▸ Usa sempre header User-Agent per compatibilità API.
 *
 * Gestione cache:
 *   ▸ Tutte le risposte sono cache-ate come file gzip in /cache/
 *   ▸ TTL standard: 30 giorni (modificabile via CACHE_TTL_GEOCODING in config.php)
 *   ▸ Chiavi: locdata_<lat>_<lon>_<lang>, nominatim_<lat>_<lon>_<lang>, elev_<lat>_<lon>
 *   ▸ Nessun cookie coinvolto nella cache: i cookie sono usati solo per salvare 
 *     la posizione utente (browser, gestito altrove).
 *
 * Flusso:
 *   - JS invia lat/lon (e opzionalmente nome/elev) a set-location.php
 *   - set-location.php richiama get_location_data_from_coords() se necessario
 *   - Il risultato viene cache-ato (nessun hit API per 30gg per la stessa posizione)
 *
 * Cambio TTL:
 *   - Modifica il valore di CACHE_TTL_GEOCODING in config.php
 *   - Oppure cambia il terzo argomento passato a fetch_json_cached()
 *
 * Fallback robusto:
 *   - Se una fonte non restituisce nulla, si prova la successiva.
 *   - "Località sconosciuta" viene mostrata solo se tutti i servizi falliscono.
 */

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';


/**
 * ----------------------------------------------------------------------------
 *  Precisione coordinate e cache (lat, lon) – Linee guida
 * ----------------------------------------------------------------------------
 *
 *  Arrotondamento delle coordinate e impatto sulla granularità della cache:
 *
 *    - 2 cifre decimali  ≈   1 km di precisione  (~1.1 km a latitudine media)
 *    - 3 cifre decimali  ≈ 100 m di precisione
 *    - 4 cifre decimali  ≈  10 m di precisione
 *    - 5 cifre decimali  ≈   1 m di precisione
 *
 *  Nota: per uso outdoor (DEM, laguna, montagna) si consiglia 4 cifre decimali.
 *  Arrotondare a più cifre significa cache più granulare e precisa (ma più file).
 *  Arrotondare a meno cifre significa cache più "larga" e condivisa (meno file).
 * ----------------------------------------------------------------------------
 */

function cache_coord($coord) {
    return number_format((float)$coord, LATLON_PRECISION, '.', '');
}

/* ==========================================================
 *  Elevation DEM (30-arc-sec)  –  sempre disponibile
 * ========================================================== */
function get_elevation_only(float $lat, float $lon): ?float
{
    $url = 'https://api.open-meteo.com/v1/elevation?' .
    http_build_query(['latitude' => $lat, 'longitude' => $lon]);
    $json = fetch_json_cached(
        $url,
        "elev_" . cache_coord($lat) . "_" . cache_coord($lon),
        CACHE_TTL_GEOCODING
    );

    // PATCH!
    if (
        isset($json['elevation'])
        && is_array($json['elevation'])
        && isset($json['elevation'][0])
        && is_numeric($json['elevation'][0])
    ) {
        return (float)$json['elevation'][0];
    }
    return null;
}




/* ==========================================================
 *  Solo nome – Nominatim
 * ========================================================== */
function get_location_name_from_coords(float $lat, float $lon, string $lang = 'it'): ?string
{
    $cacheKey = "nominatim_" . cache_coord($lat) . "_" . cache_coord($lon) . "_{$lang}";
    $ttl      = CACHE_TTL_GEOCODING;

    $raw = cache_get($cacheKey, $ttl);
    if ($raw === null) {
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lon,
            'zoom' => 13, 'addressdetails' => 1, 'accept-language' => $lang
        ]);
        $ctx = stream_context_create(['http' => ['header' => 'User-Agent: LagoonWeather/1.0']]);
        $raw = @file_get_contents($url, false, $ctx) ?: '';
        cache_put($cacheKey, $raw);
    }

    $data = json_decode($raw, true);
    if (!$data) return null;

    $addr = $data['address'] ?? [];
    $city = $addr['city'] ?? $addr['town'] ?? $addr['village']
    ?? $addr['municipality'] ?? $addr['locality'] ?? null;
    $state = $addr['state'] ?? $addr['region'] ?? null;

    if ($city) {
        return $state && stripos($state, $city) === false ? "$city, $state" : $city;
    }

    $name = $data['display_name'] ?? null;
    if ($name) {
        $name = preg_replace('/,\s*\d{4,5}\s*/', ',', $name);
        $name = preg_replace('/,\s*(italia|italy)\s*$/i', '', $name);
        $name = preg_replace('/\s+,/', ',', $name);
        $name = trim($name, ' ,');
    }
    return $name;
}

/* ==========================================================
 *  Nome + altitudine + timezone – Open-Meteo → fallback Nominatim + DEM
 * ========================================================== */
function get_location_data_from_coords(float $lat, float $lon, string $lang = 'it'): array
{
    $cache_key = "locdata_" . cache_coord($lat) . "_" . cache_coord($lon) . "_{$lang}";
    $ttl = CACHE_TTL_GEOCODING;
    $ready = fetch_json_cached('data:,', $cache_key, $ttl);
    if ($ready && isset($ready['name']) && isset($ready['elev']) && isset($ready['timezone'])) return $ready;

    // --- Open-Meteo reverse per nome e timezone ---
    $url = 'https://geocoding-api.open-meteo.com/v1/reverse?' .
    http_build_query(['latitude' => $lat, 'longitude' => $lon, 'language' => $lang, 'count' => 1]);
    $rev = fetch_json_cached(
        $url,
        "rev_" . cache_coord($lat) . "_" . cache_coord($lon) . "_{$lang}",
        $ttl
    );
    $row = $rev['results'][0] ?? null;

    $name     = $row['name']      ?? null;
    $timezone = $row['timezone']  ?? null;

    // Prendi sempre quota DEM
    $elev = get_elevation_only($lat, $lon);

    // Fallback nome con Nominatim se Open-Meteo non lo trova
    if ($name === null || $name === '') {
        $name = get_location_name_from_coords($lat, $lon, $lang) ?? 'Località sconosciuta';
    }

    if ($timezone === null) $timezone = 'Europe/Rome';

    $out = ['name' => $name, 'elev' => $elev, 'timezone' => $timezone];
    cache_put($cache_key, json_encode($out));
    return $out;
}



function get_timezone_from_timezonedb(float $lat, float $lon, string $api_key): ?string
{
    $url = 'https://api.timezonedb.com/v2.1/get-time-zone?' . http_build_query([
        'key'    => $api_key,
        'format' => 'json',
        'by'     => 'position',
        'lat'    => $lat,
        'lng'    => $lon,
    ]);
    $json = @file_get_contents($url);
    $data = json_decode($json, true);

    if (!$data || $data['status'] !== 'OK') return null;
    return $data['zoneName'] ?? null; // esempio: "Europe/Rome"
}
