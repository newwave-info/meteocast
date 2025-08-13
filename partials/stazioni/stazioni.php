<?php
/**
 * ----------------------------------------------------------------------------
 * partials/stazioni/stazioni.php - Vista principale stazioni meteo Venezia
 * ----------------------------------------------------------------------------
 * 
 * Layout:
 *   - Header con mappa toggle
 *   - Lista accordion stazioni (struttura come previsioni-day-forecast-hours.php)
 *   - Ogni stazione espandibile con dettagli sensori
 * ----------------------------------------------------------------------------
 */

require_once ROOT_PATH . '/includes/stazioni-venezia-fetch.php';

$stazioni = $GLOBALS['stazioni_venezia_data'] ?? [];
$now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));

// Helper per formattare timestamp in orario assoluto (timezone locale)
function formatStationTimestamp($timestamp) {
    if (!$timestamp) return 'N/D';
    
    // I dati del Comune di Venezia sono sempre UTC+1 (senza gestione ora legale)
    // Aggiungiamo semplicemente +1 ora manualmente
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $timestamp);
    if (!$dt) return 'N/D';
    
    // Aggiungi manualmente +1 ora
    $local_dt = $dt->modify('+1 hour');
    
    return $local_dt->format('H:i');
}

// Helper per icona sensore
function getSensorIcon($sensor_type) {
    return match($sensor_type) {
        'marea' => '🌊',
        'vento' => '💨',
        'temp_aria' => '🌡️',
        'temp_acqua' => '💧',
        'onde_laguna' => '〰️',
        'onde_mare' => '🌊',
        'umidita' => '💦',
        'pressione' => '📊',
        default => '📡'
    };
}

// Helper per nome sensore
function getSensorName($sensor_type) {
    return match($sensor_type) {
        'marea' => 'Livello Mare',
        'vento' => 'Vento',
        'temp_aria' => 'Temp. Aria',
        'temp_acqua' => 'Temp. Acqua',
        'onde_laguna' => 'Onde Laguna',
        'onde_mare' => 'Onde Mare',
        'umidita' => 'Umidità',
        'pressione' => 'Pressione',
        default => ucfirst(str_replace('_', ' ', $sensor_type))
    };
}

// Usa la funzione già definita in helpers.php per evitare conflitti
// getWindDirection() è già disponibile da helpers.php
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-compass text-primary me-2"></i>
                Stazioni Meteo Laguna
            </h5>
            <button class="btn btn-outline-primary btn-sm" id="toggle-map-btn" onclick="testClick()">
                <i class="bi bi-map"></i> Mappa
            </button>
        </div>
        <p class="text-muted small mb-0">
            Dati in tempo reale dal Comune di Venezia • <?= count($stazioni) ?> stazioni attive
        </p>
    </div>
</div>

<!-- Mappa (inizialmente nascosta) -->
<div id="stazioni-map" class="mb-3" style="display: none;">
    <div class="card border-primary">
        <div class="card-header bg-primary text-white py-2">
            <h6 class="mb-0">
                <i class="bi bi-geo-alt-fill me-2"></i>
                Localizzazione Stazioni Meteo
            </h6>
        </div>
        <div class="card-body p-3">
            <div id="map-container" style="height: 400px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; border: 2px dashed #2196f3;">
                <div class="d-flex align-items-center justify-content-center h-100 text-primary">
                    <div class="text-center">
                        <i class="bi bi-map me-2" style="font-size: 2rem;"></i>
                        <div class="fw-bold">Mappa stazioni in caricamento...</div>
                        <small class="text-muted">Posizioni nella Laguna di Venezia</small>
                    </div>
                </div>
            </div>
            <div class="mt-2 text-center">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    <?= count($stazioni) ?> stazioni meteo monitorate in tempo reale
                </small>
            </div>
        </div>
    </div>
</div>

<?php if (empty($stazioni)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-wifi-off me-2"></i>
        Dati stazioni temporaneamente non disponibili. Riprova tra qualche minuto.
    </div>
<?php else: ?>

<!-- Lista accordion stazioni con struttura come previsioni-day-forecast-hours.php -->
<div class="day-forecast-accordion-list">
    <?php foreach ($stazioni as $index => $stazione): ?>
        <?php
            $accordionId = "stazioneAccordion_" . $stazione['id'];
            $sensori_count = count($stazione['sensori']);
            $last_update = formatStationTimestamp($stazione['ultimo_aggiornamento']);
            
            // Trova sensori principali per anteprima
            $preview_data = [];
            if (isset($stazione['sensori']['marea'])) {
                $preview_data[] = $stazione['sensori']['marea']['livello'] . ' m';
            }
            if (isset($stazione['sensori']['vento'])) {
                $vento = $stazione['sensori']['vento'];
                $preview_data[] = round($vento['intensita']) . ' km/h ' . getWindDirection($vento['direzione']);
            }
            if (isset($stazione['sensori']['temp_aria'])) {
                $preview_data[] = $stazione['sensori']['temp_aria']['temperatura'] . '°C';
            }
            $preview_text = !empty($preview_data) ? implode(' • ', $preview_data) : "$sensori_count sensori attivi";
        ?>

        <section class="widget widget-riga">
            <button class="widget-header btn-accordion" type="button"
                    data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>"
                    aria-expanded="false" aria-controls="<?= $accordionId ?>">

                <div class="widget-cont">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-broadcast-pin text-primary me-2" style="font-size: 1.5rem;"></i>
                        <span class="widget-title"><?= htmlspecialchars($stazione['nome']) ?></span>
                    </div>
                </div>

                <div class="widget-cont">
                    <span class="widget-data-preview">
                        <strong><?= $sensori_count ?> sensori</strong><br>
                        <small class="text-muted"><?= $last_update ?></small>
                    </span>
                    <span class="widget-action">
                        <i class="bi bi-chevron-down arrow-accordion"></i>
                    </span>
                </div>
            </button>

            <!-- DETTAGLIO SENSORI con struttura simile a day-hourly-list -->
            <div class="collapse" id="<?= $accordionId ?>">
                <?php if (empty($stazione['sensori'])): ?>
                    <div class="widget-body p-3">
                        <div class="text-muted text-center py-3">
                            <i class="bi bi-wifi-off"></i>
                            Nessun dato disponibile per questa stazione
                        </div>
                    </div>
                <?php else: ?>
                    <ul class="day-hourly-list">
                        <?php foreach ($stazione['sensori'] as $sensor_type => $sensor_data): ?>
                            <li class="day-hourly-row">
                                <div class="list-container">
                                    <span class="sensor-icon me-2" style="font-size: 1.2em;">
                                        <?= getSensorIcon($sensor_type) ?>
                                    </span>
                                    <span class="hour-desc">
                                        <strong><?= getSensorName($sensor_type) ?></strong>
                                        <small class="text-muted ms-2"><?= formatStationTimestamp($sensor_data['timestamp']) ?></small>
                                    </span>
                                </div>
                                <div class="list-container bottom">
                                    <div class="sensor-value-display">
                                        <?php 
                                        // Formatta i valori per tipo sensore
                                        switch ($sensor_type) {
                                            case 'marea':
                                                echo '<span class="badge bg-info text-dark"><i class="bi bi-water"></i> ' . $sensor_data['livello'] . ' ' . $sensor_data['unita'] . '</span>';
                                                break;
                                            
                                            case 'vento':
                                                $dir = getWindDirection($sensor_data['direzione']);
                                                echo '<div class="windbar-box">';
                                                echo '<span class="windbar-label">' . round($sensor_data['intensita']) . ' / ' . round($sensor_data['raffica']) . ' km/h ' . $dir . '</span>';
                                                echo '<span class="windbar-bar" style="background:' . windGradientBar($sensor_data['intensita'], $sensor_data['raffica']) . ';"></span>';
                                                echo '</div>';
                                                break;
                                            
                                            case 'temp_aria':
                                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-thermometer"></i> ' . $sensor_data['temperatura'] . $sensor_data['unita'] . '</span>';
                                                break;
                                                
                                            case 'temp_acqua':
                                                echo '<span class="badge bg-primary"><i class="bi bi-droplet"></i> ' . $sensor_data['temperatura'] . $sensor_data['unita'] . '</span>';
                                                break;
                                            
                                            case 'onde_laguna':
                                            case 'onde_mare':
                                                echo '<span class="badge bg-secondary"><i class="bi bi-water"></i> ' . $sensor_data['significativa'] . ' ' . $sensor_data['unita'] . ' (sig.)</span>';
                                                echo '<small class="text-muted ms-2">Max: ' . $sensor_data['massima'] . ' ' . $sensor_data['unita'] . '</small>';
                                                break;
                                            
                                            case 'umidita':
                                                echo '<span class="badge bg-light text-dark"><i class="bi bi-moisture"></i> ' . $sensor_data['valore'] . $sensor_data['unita'] . '</span>';
                                                break;
                                                
                                            case 'pressione':
                                                echo '<span class="badge bg-success"><i class="bi bi-speedometer2"></i> ' . $sensor_data['valore'] . ' ' . $sensor_data['unita'] . '</span>';
                                                break;
                                            
                                            default:
                                                echo '<span class="badge bg-light text-dark">Dato disponibile</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        
                        <!-- Info stazione come ultimo elemento della lista -->
                        <li class="day-hourly-row border-top pt-2 mt-2">
                            <div class="list-container">
                                <i class="bi bi-info-circle text-muted me-2"></i>
                                <span class="hour-desc text-muted small">
                                    <strong>Info Stazione</strong>
                                </span>
                            </div>
                            <div class="list-container bottom">
                                <div class="station-info">
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-geo-alt"></i> 
                                        <?= number_format($stazione['latitudine'], 4) ?>, <?= number_format($stazione['longitudine'], 4) ?>
                                    </span>
                                    <span class="badge bg-light text-dark ms-1">
                                        <i class="bi bi-database"></i> 
                                        ID: <?= htmlspecialchars($stazione['id']) ?>
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.sensor-value-display {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.station-info {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.sensor-icon {
    display: inline-block;
    width: 24px;
    text-align: center;
}

/* Adatta gli stili esistenti per i sensori */
.day-hourly-row .sensor-value-display .badge {
    font-size: 0.85em;
}

.day-hourly-row .windbar-box {
    min-width: 120px;
}

#stazioni-map.show {
    display: block !important;
}

/* Stili per la mappa Leaflet */
#map-container {
    position: relative;
    height: 400px;
    border-radius: 8px;
    overflow: hidden;
}

#map-container .leaflet-container {
    height: 100%;
    border-radius: 8px;
}

/* Popup stazioni personalizzato */
.station-popup {
    max-width: 300px;
}

.station-popup .station-name {
    font-size: 1.1em;
    color: #007bff;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 0.5rem;
    margin-bottom: 0.75rem;
}

.sensor-popup-item {
    padding: 0.25rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.sensor-popup-item:last-child {
    border-bottom: none;
}

.sensor-popup-item .sensor-name {
    font-weight: 500;
    font-size: 0.9em;
    color: #495057;
}

/* Marker personalizzati */
.station-marker div {
    transition: transform 0.2s ease;
}

.station-marker:hover div {
    transform: scale(1.2);
}

.user-marker div {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(255, 0, 0, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
}

/* Controlli mappa */
.leaflet-control-layers {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
}

.leaflet-control-scale-line {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(5px);
}

/* Responsive per la mappa */
@media (max-width: 768px) {
    #map-container {
        height: 300px;
    }
    
    .station-popup {
        max-width: 250px;
    }
    
    .leaflet-popup-content {
        margin: 8px 12px;
    }
}
</style>

<script>
// Assicuriamo che le funzioni siano definite dopo il DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Event listener per il toggle mappa
    const toggleBtn = document.getElementById('toggle-map-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            toggleStazioniMap();
        });
    }
});

function toggleStazioniMap() {
    const mapDiv = document.getElementById('stazioni-map');
    const toggleBtn = document.getElementById('toggle-map-btn');
    
    if (!mapDiv) {
        console.error('Elemento #stazioni-map non trovato!');
        return;
    }
    
    // Debug: mostra stato attuale
    console.log('Map div display before:', mapDiv.style.display);
    console.log('Map div computed style:', window.getComputedStyle(mapDiv).display);
    
    const isCurrentlyVisible = window.getComputedStyle(mapDiv).display !== 'none';
    
    if (isCurrentlyVisible) {
        // Nascondi mappa
        mapDiv.style.display = 'none';
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="bi bi-map"></i> Mappa';
        }
        console.log('Mappa nascosta');
    } else {
        // Mostra mappa
        mapDiv.style.display = 'block';
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="bi bi-map-fill"></i> Nascondi';
        }
        console.log('Mappa mostrata');
        // Inizializza mappa se non già fatto
        initStazioniMap();
    }
}

function initStazioniMap() {
    const container = document.getElementById('map-container');
    if (!container) return;
    
    // Mostra loading durante l'inizializzazione
    container.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-primary"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Caricamento mappa...</div>';
    
    // Simula caricamento mappa
    setTimeout(() => {
        if (container) {
            container.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100 text-info">
                    <div class="text-center">
                        <i class="bi bi-geo-alt-fill me-2" style="font-size: 2rem;"></i>
                        <div>Mappa stazioni Laguna di Venezia</div>
                        <small class="text-muted">(Implementazione in corso)</small>
                    </div>
                </div>
            `;
        }
    }, 500);
}

// Aggiorna timestamp automaticamente ogni minuto se necessario
setInterval(function() {
    // TODO: Implementare aggiornamento automatico timestamp se necessario
    // Per ora i timestamp sono statici (orario assoluto)
}, 60000);
</script>