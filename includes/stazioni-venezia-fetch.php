<?php
/**
 * ----------------------------------------------------------------------------
 * includes/stazioni-venezia-fetch.php - Fetch dati stazioni laguna Venezia
 * ----------------------------------------------------------------------------
 * 
 * Ruolo:
 *   - Recupera dati da tutte le API del Comune di Venezia (maree, vento, temperature, ecc.)
 *   - Organizza i dati per stazione con cache intelligente
 *   - Restituisce array strutturato per la vista accordion
 * 
 * Cache:
 *   - TTL: 30 minuti (dati abbastanza frequenti)
 *   - Chiave: stazioni_venezia_combined
 * ----------------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';

// Define the function only if not already present
if (!function_exists('get_stazioni_venezia_data')) {
    function get_stazioni_venezia_data() {
        // URL delle fonti dati del Comune di Venezia
        $urls = [
            'maree'         => "https://dati.venezia.it/sites/default/files/dataset/opendata/livello.json",
            'venti'         => "https://dati.venezia.it/sites/default/files/dataset/opendata/vento.json", 
            'temparia'      => "https://dati.venezia.it/sites/default/files/dataset/opendata/temparia.json",
            'tempacqua'     => "https://dati.venezia.it/sites/default/files/dataset/opendata/tempacqua.json",
            'onde'          => "https://dati.venezia.it/sites/default/files/dataset/opendata/onde_laguna.json",
            'umidita'       => "https://dati.venezia.it/sites/default/files/dataset/opendata/umidita.json",
            'pressione'     => "https://dati.venezia.it/sites/default/files/dataset/opendata/pressione.json",
            'onde_mare'     => "https://dati.venezia.it/sites/default/files/dataset/opendata/onde_mare.json"
        ];
        
        $stations = [];
        $cache_ttl = CACHE_TTL_SENSORS ?? 1800; // 30 minuti default
        
        // Helper per unire i dati per stazione
        function mergeStationData(&$stations, $record, $sensor_type, $data) {
            $id = $record['ID_stazione'] ?? null;
            $nome = $record['stazione'] ?? 'Stazione sconosciuta';
            $lat = isset($record['latDDN']) ? floatval($record['latDDN']) : null;
            $lon = isset($record['lonDDE']) ? floatval($record['lonDDE']) : null;
            $timestamp = isset($record['data']) ? date('Y-m-d H:i', strtotime($record['data'])) : null;
            
            if (!$id) return;
            
            if (!isset($stations[$id])) {
                $stations[$id] = [
                    'id' => $id,
                    'nome' => $nome,
                    'latitudine' => $lat,
                    'longitudine' => $lon,
                    'sensori' => [],
                    'ultimo_aggiornamento' => $timestamp
                ];
            }
            
            // Aggiorna coordinate se mancanti
            if (!$stations[$id]['latitudine'] && $lat) $stations[$id]['latitudine'] = $lat;
            if (!$stations[$id]['longitudine'] && $lon) $stations[$id]['longitudine'] = $lon;
            
            // Aggiorna timestamp se più recente
            if ($timestamp && $timestamp > $stations[$id]['ultimo_aggiornamento']) {
                $stations[$id]['ultimo_aggiornamento'] = $timestamp;
            }
            
            $stations[$id]['sensori'][$sensor_type] = array_merge($data, ['timestamp' => $timestamp]);
        }
        
        // MAREE
        $marea_data = fetch_json_cached($urls['maree'], 'stazioni_maree', $cache_ttl);
        foreach ($marea_data as $record) {
            if (!isset($record['valore'])) continue;
            mergeStationData($stations, $record, 'marea', [
                'livello' => floatval(str_replace(' m', '', $record['valore'])),
                'unita' => 'm'
            ]);
        }
        
        // VENTO
        $vento_data = fetch_json_cached($urls['venti'], 'stazioni_venti', $cache_ttl);
        foreach ($vento_data as $record) {
            if (!isset($record['valore'])) continue;
            $valori_vento = explode(', ', $record['valore']);
            if (count($valori_vento) !== 3) continue;
            
            mergeStationData($stations, $record, 'vento', [
                'direzione' => floatval(str_replace(' gradi', '', $valori_vento[0])),
                'intensita' => floatval(str_replace(' m/s', '', $valori_vento[1])) * 3.6, // Convert to km/h
                'raffica' => floatval(str_replace(' m/s', '', $valori_vento[2])) * 3.6,
                'unita' => 'km/h'
            ]);
        }
        
        // TEMPERATURA ARIA
        $temparia_data = fetch_json_cached($urls['temparia'], 'stazioni_temparia', $cache_ttl);
        foreach ($temparia_data as $record) {
            if (!isset($record['valore'])) continue;
            mergeStationData($stations, $record, 'temp_aria', [
                'temperatura' => floatval(str_replace(' °C', '', $record['valore'])),
                'unita' => '°C'
            ]);
        }
        
        // TEMPERATURA ACQUA
        $tempacqua_data = fetch_json_cached($urls['tempacqua'], 'stazioni_tempacqua', $cache_ttl);
        foreach ($tempacqua_data as $record) {
            if (!isset($record['valore'])) continue;
            mergeStationData($stations, $record, 'temp_acqua', [
                'temperatura' => floatval(str_replace(' °C', '', $record['valore'])),
                'unita' => '°C'
            ]);
        }
        
        // ONDE LAGUNA
        $onde_data = fetch_json_cached($urls['onde'], 'stazioni_onde', $cache_ttl);
        foreach ($onde_data as $record) {
            if (!isset($record['valore'])) continue;
            $valori_onda = explode(', ', $record['valore']);
            if (count($valori_onda) !== 2) continue;
            
            mergeStationData($stations, $record, 'onde_laguna', [
                'significativa' => floatval(str_replace(' m', '', $valori_onda[0])),
                'massima' => floatval(str_replace(' m', '', $valori_onda[1])),
                'unita' => 'm'
            ]);
        }
        
        // UMIDITÀ
        $umidita_data = fetch_json_cached($urls['umidita'], 'stazioni_umidita', $cache_ttl);
        foreach ($umidita_data as $record) {
            if (!isset($record['valore'])) continue;
            mergeStationData($stations, $record, 'umidita', [
                'valore' => floatval(str_replace(' %', '', $record['valore'])),
                'unita' => '%'
            ]);
        }
        
        // PRESSIONE
        $pressione_data = fetch_json_cached($urls['pressione'], 'stazioni_pressione', $cache_ttl);
        foreach ($pressione_data as $record) {
            if (!isset($record['valore'])) continue;
            mergeStationData($stations, $record, 'pressione', [
                'valore' => floatval(str_replace(' hPa', '', $record['valore'])),
                'unita' => 'hPa'
            ]);
        }
        
        // ONDE MARE
        $onde_mare_data = fetch_json_cached($urls['onde_mare'], 'stazioni_onde_mare', $cache_ttl);
        foreach ($onde_mare_data as $record) {
            if (!isset($record['valore'])) continue;
            $valori_onda = explode(', ', $record['valore']);
            if (count($valori_onda) !== 2) continue;
            
            mergeStationData($stations, $record, 'onde_mare', [
                'significativa' => floatval(str_replace(' m', '', $valori_onda[0])),
                'massima' => floatval(str_replace(' m', '', $valori_onda[1])),
                'unita' => 'm'
            ]);
        }
        
        // Filtra stazioni senza coordinate (non possono essere mostrate su mappa)
        $stations = array_filter($stations, function($station) {
            return $station['latitudine'] && $station['longitudine'];
        });
        
        // Ordina per nome stazione
        uasort($stations, function($a, $b) {
            return strcasecmp($a['nome'], $b['nome']);
        });
        
        error_log("Stazioni Venezia: Caricate " . count($stations) . " stazioni con dati");
        
        return array_values($stations); // Reindex array
    }
}

// Carica dati solo se non già presenti
if (!isset($GLOBALS['stazioni_venezia_data'])) {
    $GLOBALS['stazioni_venezia_data'] = get_stazioni_venezia_data();
}