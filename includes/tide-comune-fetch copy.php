<?php
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';

// Definisci la funzione solo se non già presente (utile se includi più volte)
if (!function_exists('get_tide_forecast')) {
    function get_tide_forecast($url) {
        $data = fetch_json_cached($url, md5($url), CACHE_TTL_TIDE_FORECAST);
        $out = [];
        $last_update = null;
        
        if ($data && is_array($data)) {
            // Estrai informazioni di aggiornamento dal primo elemento (se presente)
            if (!empty($data[0])) {
                $first_row = $data[0];
                
                // Il campo DATA_PREVISIONE indica quando è stata generata la previsione
                if (isset($first_row['DATA_PREVISIONE']) && !empty($first_row['DATA_PREVISIONE'])) {
                    $update_dt = DateTimeImmutable::createFromFormat(
                        'Y-m-d H:i:s',
                        $first_row['DATA_PREVISIONE'],
                        new DateTimeZone('Europe/Rome')
                    );
                    if ($update_dt) {
                        $last_update = $update_dt->format('Y-m-d H:i:s');
                    }
                }
            }
            
            // Processa i dati delle maree come prima
            foreach ($data as $row) {
                $dateField = $row['DATA_ESTREMALE'] ?? null;
                $valField  = $row['VALORE'] ?? null;
                $typeField = $row['TIPO_ESTREMALE'] ?? null;
                
                if (!$dateField || !is_numeric($valField)) continue;
                
                // Parsing con Europe/Rome (gestisce ora legale)
                $dt = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    $dateField,
                    new DateTimeZone('Europe/Rome')
                );
                if (!$dt) continue;
                
                $out[] = [
                    'time' => $dt->format('Y-m-d H:i'),
                    'val'  => round($valField),
                    'type' => strtolower($typeField) // normalizza sempre in minuscolo!
                ];
            }
            
            // Ordina i dati per tempo crescente
            usort($out, fn($a, $b) => strcmp($a['time'], $b['time']));
            
            $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
            $has_past = false;
            foreach ($out as $row) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                if ($dt && $dt < $now) {
                    $has_past = true;
                    break;
                }
            }
            
            // Prepara il risultato finale con metadati
            $result = [
                'data' => $out,
                'last_update' => $last_update,
                'station' => 'Punta della Salute',
                'updated_at' => date('Y-m-d H:i:s') // quando abbiamo fatto il fetch
            ];
            
            // Salva backup se almeno un dato è nel passato
            if ($has_past) {
                @file_put_contents(
                    ROOT_PATH . '/cache/_tide_last.json',
                    json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
            }
            
            // Se mancano dati passati, prova a recuperarli dal backup
            if (!$has_past) {
                $backupFile = ROOT_PATH . '/cache/_tide_last.json';
                if (file_exists($backupFile)) {
                    $backup = json_decode(file_get_contents($backupFile), true);
                    if (is_array($backup) && isset($backup['data'])) {
                        $pastPoints = array_filter($backup['data'], function($row) use ($now) {
                            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                            return $dt && $dt < $now;
                        });
                        $pastPoints = array_slice(array_values($pastPoints), -2);
                        $result['data'] = array_merge($pastPoints, $result['data']);
                        
                        // Mantieni anche i metadati del backup se disponibili
                        if (!$result['last_update'] && isset($backup['last_update'])) {
                            $result['last_update'] = $backup['last_update'];
                        }
                    }
                }
            }
            
            return $result;
        }
        
        return ['data' => [], 'last_update' => null, 'station' => 'Punta della Salute', 'updated_at' => date('Y-m-d H:i:s')];
    }
}

// Carica solo se non già presente (evita overwrite da altre widget/partial)
if (!isset($GLOBALS['tide_salute_forecast'])) {
    $url_forecast = "https://dati.venezia.it/sites/default/files/dataset/opendata/previsione.json";
    $GLOBALS['tide_salute_forecast'] = get_tide_forecast($url_forecast);
}