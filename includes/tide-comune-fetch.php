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
            
            // Processa i dati delle maree dall'API
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
                    'type' => strtolower($typeField)
                ];
            }
            
            // Ordina i dati per tempo crescente
            usort($out, fn($a, $b) => strcmp($a['time'], $b['time']));
            
            $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
            
            // ============================================================================
            // SISTEMA DI BACKUP SEPARATO - NON SOVRASCRIVE MAI LO STORICO
            // ============================================================================
            
            $historicalFile = ROOT_PATH . '/cache/_tide_historical.json';
            $currentFile = ROOT_PATH . '/cache/_tide_current.json';
            
            // 1. SALVA SEMPRE i dati correnti (snapshot dell'API)
            $currentResult = [
                'data' => $out,
                'last_update' => $last_update,
                'station' => 'Punta della Salute',
                'updated_at' => date('Y-m-d H:i:s'),
                'source' => 'api_current_snapshot'
            ];
            @file_put_contents($currentFile, json_encode($currentResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 2. CARICA storico esistente (senza mai sovrascriverlo)
            $historicalData = [];
            if (file_exists($historicalFile)) {
                $historical = json_decode(file_get_contents($historicalFile), true);
                if (is_array($historical) && isset($historical['data']) && is_array($historical['data'])) {
                    $historicalData = $historical['data'];
                }
            }
            
            // 3. IDENTIFICA nuovi punti da aggiungere allo storico
            $newPastPoints = array_filter($out, function($row) use ($now) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                return $dt && $dt < $now;
            });
            
            // Punti che diventeranno presto passati (nelle prossime 6 ore invece di 2)
            // Questo copre meglio gli aggiornamenti API che avvengono 2-3 volte al giorno
            $transitionPoints = array_filter($out, function($row) use ($now) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                if (!$dt) return false;
                
                $diffHours = ($dt->getTimestamp() - $now->getTimestamp()) / 3600;
                // Salva punti che sono tra -2h e +6h (window più ampia)
                return $diffHours >= -2 && $diffHours <= 6;
            });
            
            $pointsToAdd = array_merge($newPastPoints, $transitionPoints);
            
            // 4. AGGIUNGI nuovi punti allo storico (senza duplicati)
            $addedCount = 0;
            foreach ($pointsToAdd as $newPoint) {
                $alreadyExists = false;
                foreach ($historicalData as $existingPoint) {
                    if ($existingPoint['time'] === $newPoint['time']) {
                        $alreadyExists = true;
                        break;
                    }
                }
                
                if (!$alreadyExists) {
                    $historicalData[] = $newPoint;
                    $addedCount++;
                }
            }
            
            // 5. PULIZIA e ordinamento dello storico
            if ($addedCount > 0 || !file_exists($historicalFile)) {
                // Riordina per tempo
                usort($historicalData, fn($a, $b) => strcmp($a['time'], $b['time']));
                
                // Mantieni solo ultimi 10 giorni (invece di 7, per più sicurezza)
                $cutoffDate = $now->sub(new DateInterval('P10D'));
                $historicalData = array_filter($historicalData, function($row) use ($cutoffDate) {
                    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                    return $dt && $dt >= $cutoffDate;
                });
                $historicalData = array_values($historicalData); // Reindizza
                
                // SALVA lo storico aggiornato
                $historicalResult = [
                    'data' => $historicalData,
                    'last_update' => $last_update,
                    'station' => 'Punta della Salute',
                    'updated_at' => date('Y-m-d H:i:s'),
                    'source' => 'historical_accumulated',
                    'total_points' => count($historicalData),
                    'past_points' => count($newPastPoints),
                    'transition_points' => count($transitionPoints)
                ];
                
                $writeResult = @file_put_contents($historicalFile, json_encode($historicalResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                if ($writeResult !== false) {
                    error_log("Tide historical: ✅ FILE SCRITTO " . $historicalFile . " - Aggiunti " . $addedCount . " nuovi punti. Totale storico: " . count($historicalData));
                } else {
                    error_log("Tide historical: ❌ ERRORE scrittura " . $historicalFile);
                }
            }
            
            // 6. COSTRUISCI dataset finale: STORICO + FUTURO
            $finalData = [];
            
            // A. Prendi ultimi 8 punti passati dallo storico (invece di 5)
            $historicalPast = array_filter($historicalData, function($row) use ($now) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                return $dt && $dt < $now;
            });
            $historicalPast = array_slice($historicalPast, -8); // Ultimi 8 per grafico più arioso
            $finalData = array_merge($finalData, $historicalPast);
            
            // B. Prendi previsioni future dall'API
            $futurePredictions = array_filter($out, function($row) use ($now) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                return $dt && $dt >= $now;
            });
            $finalData = array_merge($finalData, $futurePredictions);
            
            // C. Rimuovi duplicati e riordina
            $uniqueFinalData = [];
            $seenTimes = [];
            foreach ($finalData as $point) {
                if (!in_array($point['time'], $seenTimes)) {
                    $uniqueFinalData[] = $point;
                    $seenTimes[] = $point['time'];
                }
            }
            usort($uniqueFinalData, fn($a, $b) => strcmp($a['time'], $b['time']));
            
            // 7. RISULTATO FINALE
            $result = [
                'data' => $uniqueFinalData,
                'last_update' => $last_update,
                'station' => 'Punta della Salute',
                'updated_at' => date('Y-m-d H:i:s'),
                'source' => 'combined_historical_future'
            ];
            
            // Log statistiche finali
            $finalPastCount = count(array_filter($uniqueFinalData, function($row) use ($now) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                return $dt && $dt < $now;
            }));
            
            $finalFutureCount = count(array_filter($uniqueFinalData, function($row) use ($now) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $row['time'], new DateTimeZone(TIMEZONE));
                return $dt && $dt >= $now;
            }));
            
            error_log("Tide final: " . $finalPastCount . " punti passati + " . $finalFutureCount . " punti futuri = " . count($uniqueFinalData) . " totali");
            
            return $result;
        }
        
        return [
            'data' => [], 
            'last_update' => null, 
            'station' => 'Punta della Salute', 
            'updated_at' => date('Y-m-d H:i:s'),
            'source' => 'empty_fallback'
        ];
    }
}

// Carica solo se non già presente (evita overwrite da altre widget/partial)
if (!isset($GLOBALS['tide_salute_forecast'])) {
    $url_forecast = "https://dati.venezia.it/sites/default/files/dataset/opendata/previsione.json";
    $GLOBALS['tide_salute_forecast'] = get_tide_forecast($url_forecast);
}