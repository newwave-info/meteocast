<?php
/**
 * ----------------------------------------------------------------------------
 * includes/stazioni-venezia-fetch.php - Fetch dati stazioni laguna Venezia
 * ----------------------------------------------------------------------------
 * - Unisce i dataset OpenData (maree, vento, temperature, onde, umidità, pressione)
 * - Merge per ID_stazione, con fallback per nome normalizzato
 * - Cache intelligente
 * ----------------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';

if (!function_exists('get_stazioni_venezia_data')) {

    function normalize_name($name) {
        $s = mb_strtolower(trim((string)$name), 'UTF-8');
        $s = strtr($s, ['à'=>'a','è'=>'e','é'=>'e','ì'=>'i','ò'=>'o','ó'=>'o','ù'=>'u','ç'=>'c']);
        $s = preg_replace('/[^a-z0-9]+/u', '', $s);
        return $s ?: null;
    }

    function get_stazioni_venezia_data() {
        $base = 'https://dati.venezia.it/sites/default/files/dataset/opendata';
        $urls = [
            'maree'      => "$base/livello.json",
            'venti'      => "$base/vento.json",
            'temparia'   => "$base/temparia.json",
            'tempacqua'  => "$base/tempacqua.json",
            'onde'       => "$base/onde_laguna.json",
            'umidita'    => "$base/umidita.json",
            'pressione'  => "$base/pressione.json",
            'onde_mare'  => "$base/onde_mare.json",
        ];

        $stations  = [];
        $cache_ttl = defined('CACHE_TTL_SENSORS') ? CACHE_TTL_SENSORS : 1800;

        $pickStationKey = function(array &$stations, array $record) {
            $id    = isset($record['ID_stazione']) ? (string)$record['ID_stazione'] : null;
            $nome  = isset($record['stazione']) ? (string)$record['stazione'] : null;
            $nName = normalize_name($nome);

            if ($id && isset($stations["ID:$id"])) return "ID:$id";
            if ($nName) {
                foreach ($stations as $k => $st) {
                    if (($st['_norm_name'] ?? null) === $nName) return $k;
                }
            }
            return $id ? "ID:$id" : ($nName ? "NM:$nName" : "NM:".spl_object_id((object)[]));
        };

        $mergeStationData = function(array &$stations, array $record, string $sensor_type, array $data) use ($pickStationKey) {
            $key = $pickStationKey($stations, $record);

            $id   = isset($record['ID_stazione']) ? (string)$record['ID_stazione'] : null;
            $nome = $record['stazione'] ?? 'Stazione';
            $lat  = $record['latDDN'] ?? ($record['lat'] ?? null);
            $lon  = $record['lonDDE'] ?? ($record['lon'] ?? null);

            $lat  = is_numeric($lat) ? (float)$lat : null;
            $lon  = is_numeric($lon) ? (float)$lon : null;

            $timestamp = isset($record['data']) ? date('Y-m-d H:i', strtotime($record['data'])) : null;

            if (!isset($stations[$key])) {
                $stations[$key] = [
                    'id'                  => $id,
                    'nome'                => $nome,
                    'latitudine'          => $lat,
                    'longitudine'         => $lon,
                    'sensori'             => [],
                    'ultimo_aggiornamento'=> $timestamp,
                    '_norm_name'          => normalize_name($nome),
                ];
            }

            if (!$stations[$key]['latitudine']  && $lat !== null) $stations[$key]['latitudine']  = $lat;
            if (!$stations[$key]['longitudine'] && $lon !== null) $stations[$key]['longitudine'] = $lon;
            if ($timestamp && $timestamp > ($stations[$key]['ultimo_aggiornamento'] ?? '')) {
                $stations[$key]['ultimo_aggiornamento'] = $timestamp;
            }

            $stations[$key]['sensori'][$sensor_type] = $data + ['timestamp' => $timestamp];
        };

        // MAREA
        foreach ((array)fetch_json_cached($urls['maree'], 'stazioni_maree', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $mergeStationData($stations, $r, 'marea', [
                'livello' => (float)str_replace(',', '.', str_replace([' m','m'], '', $val)),
                'unita'   => 'm',
            ]);
        }

        // VENTO
        foreach ((array)fetch_json_cached($urls['venti'], 'stazioni_venti', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $parts = array_map('trim', explode(',', $val));
            if (count($parts) < 3) continue;

            $dir    = (float)str_replace([' gradi','gradi'], '', $parts[0]);
            $int_ms = (float)str_replace(',', '.', str_replace([' m/s','m/s'], '', $parts[1]));
            $raf_ms = (float)str_replace(',', '.', str_replace([' m/s','m/s'], '', $parts[2]));

            $mergeStationData($stations, $r, 'vento', [
                'direzione' => $dir,
                'intensita' => $int_ms * 3.6,
                'raffica'   => $raf_ms * 3.6,
                'unita'     => 'km/h',
            ]);
        }

        // TEMP ARIA
        foreach ((array)fetch_json_cached($urls['temparia'], 'stazioni_temparia', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $mergeStationData($stations, $r, 'temp_aria', [
                'temperatura' => (float)str_replace(',', '.', str_replace([' °C','°C'], '', $val)),
                'unita'       => '°C',
            ]);
        }

        // TEMP ACQUA
        foreach ((array)fetch_json_cached($urls['tempacqua'], 'stazioni_tempacqua', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $mergeStationData($stations, $r, 'temp_acqua', [
                'temperatura' => (float)str_replace(',', '.', str_replace([' °C','°C'], '', $val)),
                'unita'       => '°C',
            ]);
        }

        // ONDE LAGUNA
        foreach ((array)fetch_json_cached($urls['onde'], 'stazioni_onde', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $p = array_map('trim', explode(',', $val));
            if (count($p) < 2) continue;
            $mergeStationData($stations, $r, 'onde_laguna', [
                'significativa' => (float)str_replace(',', '.', str_replace([' m','m'], '', $p[0])),
                'massima'       => (float)str_replace(',', '.', str_replace([' m','m'], '', $p[1])),
                'unita'         => 'm',
            ]);
        }

        // UMIDITÀ
        foreach ((array)fetch_json_cached($urls['umidita'], 'stazioni_umidita', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $mergeStationData($stations, $r, 'umidita', [
                'valore' => (float)str_replace(',', '.', str_replace([' %','%'], '', $val)),
                'unita'  => '%',
            ]);
        }

        // PRESSIONE
        foreach ((array)fetch_json_cached($urls['pressione'], 'stazioni_pressione', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $mergeStationData($stations, $r, 'pressione', [
                'valore' => (float)str_replace(',', '.', str_replace([' hPa','hPa'], '', $val)),
                'unita'  => 'hPa',
            ]);
        }

        // ONDE MARE
        foreach ((array)fetch_json_cached($urls['onde_mare'], 'stazioni_onde_mare', $cache_ttl) as $r) {
            $val = $r['valore'] ?? null;
            if (!$val) continue;
            $p = array_map('trim', explode(',', $val));
            if (count($p) < 2) continue;
            $mergeStationData($stations, $r, 'onde_mare', [
                'significativa' => (float)str_replace(',', '.', str_replace([' m','m'], '', $p[0])),
                'massima'       => (float)str_replace(',', '.', str_replace([' m','m'], '', $p[1])),
                'unita'         => 'm',
            ]);
        }

        // Filtra stazioni senza coordinate
        $stations = array_filter($stations, fn($s) => isset($s['latitudine'], $s['longitudine']) && $s['latitudine'] && $s['longitudine']);

        // Ordina per nome
        uasort($stations, fn($a,$b) => strcasecmp($a['nome'] ?? '', $b['nome'] ?? ''));

        error_log("Stazioni Venezia: ".count($stations)." stazioni caricate (senza visibilità)");

        return array_values($stations);
    }
}

if (!isset($GLOBALS['stazioni_venezia_data'])) {
    $GLOBALS['stazioni_venezia_data'] = get_stazioni_venezia_data();
}