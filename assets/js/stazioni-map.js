/**
 * stazioni-map.js - Mappa Leaflet per le stazioni meteo di Venezia
 * Integrata nel sistema MeteoCast
 */

// Variabili globali per la mappa
let stazioniMap = null;
let stazioniMarkers = null;
let userMarker = null;
let userAccuracyCircle = null;
let locateInterval = null;
let locateActive = false;

// Formatta la data in formato italiano con +1 ora (come nel PHP)
function formatStationDateItalian(dateString) {
    if (!dateString) return 'N/D';
    
    // Parse del timestamp e aggiungi +1 ora (come nel PHP)
    const d = new Date(dateString);
    d.setHours(d.getHours() + 1); // +1 ora come nel PHP
    
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = String(d.getFullYear()).slice(-2);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} - ${hh}:${mm}`;
}

// Converte solo l'orario (HH:MM) con +1 ora
function formatStationTimeOnly(dateString) {
    if (!dateString) return 'N/D';
    
    const d = new Date(dateString);
    d.setHours(d.getHours() + 1); // +1 ora come nel PHP
    
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${hh}:${mm}`;
}

// Converte gradi in punto cardinale
function degToCardinal(deg) {
    deg = parseFloat(deg);
    const directions = ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSO", "SO", "OSO", "O", "ONO", "NO", "NNO"];
    const index = Math.floor((deg + 22.5) / 22.5) % 16;
    return directions[index];
}

// Freccia vento SVG
function generateWindArrow(deg) {
    const rotation = parseFloat(deg) + 180;
    return `<svg viewBox="0 0 32 32" style="transform: rotate(${rotation}deg); width: 1.2em; height: 1.2em;">
              <path fill="#007bff" d="M16 4 L12 12 L15 12 L15 28 L17 28 L17 12 L20 12 Z"/>
            </svg>`;
}

// Inizializza la mappa Leaflet
function initStazioniMap() {
    const container = document.getElementById('map-container');
    if (!container) {
        console.error('Container #map-container non trovato!');
        return;
    }

    // Mostra loading
    container.innerHTML = `
        <div class="d-flex align-items-center justify-content-center h-100 text-primary">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            Inizializzazione mappa...
        </div>
    `;

    // Inizializza mappa solo se non già presente
    if (stazioniMap) {
        console.log('Mappa già inizializzata');
        return;
    }

    setTimeout(() => {
        try {
            // Base layers
            const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            });
            
            const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri'
            });

            // Crea mappa centrata sulla Laguna di Venezia
            stazioniMap = L.map('map-container', {
                layers: [osmLayer]
            }).setView([45.44, 12.33], 11);

            // Controlli layer
            const baseLayers = {
                "OpenStreetMap": osmLayer,
                "Vista Satellite": satelliteLayer
            };
            L.control.layers(baseLayers).addTo(stazioniMap);

            // Scala
            L.control.scale().addTo(stazioniMap);

            // MarkerCluster
            stazioniMarkers = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true
            });

            // Carica dati stazioni
            loadStazioniData();

        } catch (error) {
            console.error('Errore inizializzazione mappa:', error);
            container.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100 text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Errore caricamento mappa
                </div>
            `;
        }
    }, 500);
}

// Carica i dati delle stazioni (usa i dati PHP già caricati o fa fetch)
function loadStazioniData() {
    console.log('loadStazioniData() chiamata');
    
    // Prova a usare i dati già presenti nel DOM/globali
    if (typeof window.stazioniVeneziaData !== 'undefined' && window.stazioniVeneziaData) {
        console.log('Uso dati stazioni già caricati:', window.stazioniVeneziaData);
        processStazioniData(window.stazioniVeneziaData);
        return;
    }

    console.warn('Dati stazioni non trovati in window.stazioniVeneziaData, provo con fetch...');

    // Fallback: prova a caricare direttamente dal PHP
    fetch(window.location.href + '&ajax=stazioni')
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Dati ricevuti via fetch:', data);
            processStazioniData(data);
        })
        .catch(error => {
            console.error('Errore caricamento dati stazioni:', error);
            
            // Fallback finale: crea dati di test
            console.warn('Carico dati di test per debug...');
            const testData = [
                {
                    id: 'test1',
                    nome: 'Stazione Test Venezia',
                    latitudine: 45.4408,
                    longitudine: 12.3155,
                    sensori: {
                        marea: { livello: 0.5, unita: 'm', timestamp: '2024-01-15 20:00' },
                        temp_aria: { temperatura: 15, unita: '°C', timestamp: '2024-01-15 20:00' }
                    }
                },
                {
                    id: 'test2', 
                    nome: 'Stazione Test Lido',
                    latitudine: 45.4067,
                    longitudine: 12.3756,
                    sensori: {
                        vento: { direzione: 180, intensita: 5, raffica: 8, timestamp: '2024-01-15 20:00' }
                    }
                }
            ];
            
            const container = document.getElementById('map-container');
            if (container) {
                container.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center h-100 text-warning">
                        <div class="text-center">
                            <i class="bi bi-wifi-off me-2"></i>
                            <div>Dati stazioni non disponibili</div>
                            <small>Carico dati di test...</small>
                        </div>
                    </div>
                `;
            }
            
            setTimeout(() => {
                processStazioniData(testData);
            }, 1000);
        });
}

// Processa i dati delle stazioni e crea i marker
function processStazioniData(stazioni) {
    console.log('processStazioniData chiamata con:', stazioni);
    
    if (!stazioni || !Array.isArray(stazioni)) {
        console.error('Dati stazioni non validi:', stazioni);
        const container = document.getElementById('map-container');
        if (container) {
            container.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100 text-warning">
                    <div class="text-center">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <div>Dati stazioni non validi</div>
                        <small>Controlla la console per dettagli</small>
                    </div>
                </div>
            `;
        }
        return;
    }

    if (stazioni.length === 0) {
        console.warn('Array stazioni vuoto');
        const container = document.getElementById('map-container');
        if (container) {
            container.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100 text-info">
                    <div class="text-center">
                        <i class="bi bi-info-circle me-2"></i>
                        <div>Nessuna stazione disponibile</div>
                        <small>Riprova più tardi</small>
                    </div>
                </div>
            `;
        }
        return;
    }

    // Icona personalizzata per le stazioni
    const stationIcon = L.divIcon({
        className: 'station-marker',
        html: '<div style="background: #007bff; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white;"></div>',
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });

    let validStations = 0;
    let invalidStations = 0;

    stazioni.forEach((stazione, index) => {
        console.log(`Stazione ${index}:`, stazione);
        
        if (!stazione.latitudine || !stazione.longitudine) {
            console.warn(`Stazione ${stazione.nome || index} senza coordinate:`, {
                lat: stazione.latitudine,
                lon: stazione.longitudine
            });
            invalidStations++;
            return;
        }

        // Verifica che le coordinate siano numeriche
        const lat = parseFloat(stazione.latitudine);
        const lon = parseFloat(stazione.longitudine);
        
        if (isNaN(lat) || isNaN(lon)) {
            console.warn(`Stazione ${stazione.nome || index} con coordinate non valide:`, {
                lat: stazione.latitudine,
                lon: stazione.longitudine
            });
            invalidStations++;
            return;
        }

        validStations++;
        console.log(`Creando marker per ${stazione.nome} a [${lat}, ${lon}]`);

        // Crea contenuto popup
        const popupContent = createStationPopup(stazione);

        // Crea marker
        const marker = L.marker([lat, lon], { 
            icon: stationIcon 
        }).bindPopup(popupContent, {
            maxWidth: 320,
            keepInView: true,
            autoPanPadding: [20, 20]
        });

        stazioniMarkers.addLayer(marker);
    });

    // Aggiungi cluster alla mappa
    stazioniMap.addLayer(stazioniMarkers);

    console.log(`Risultato: ${validStations} stazioni valide, ${invalidStations} invalide`);

    // Fit bounds se ci sono stazioni valide
    if (validStations > 0) {
        try {
            const bounds = stazioniMarkers.getBounds();
            console.log('Bounds calcolati:', bounds);
            stazioniMap.fitBounds(bounds, { padding: [20, 20] });
        } catch (error) {
            console.error('Errore fitBounds:', error);
            // Fallback: centra su Venezia
            stazioniMap.setView([45.44, 12.33], 11);
        }
    } else {
        // Nessuna stazione valida, centra su Venezia
        console.warn('Nessuna stazione valida, centro su Venezia');
        stazioniMap.setView([45.44, 12.33], 11);
        
        const container = document.getElementById('map-container');
        if (container) {
            // Aggiungi messaggio overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255,255,255,0.9);
                padding: 1rem;
                border-radius: 8px;
                text-align: center;
                z-index: 1000;
                pointer-events: none;
            `;
            overlay.innerHTML = `
                <div class="text-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <div>Stazioni senza coordinate valide</div>
                    <small>${invalidStations} stazioni trovate</small>
                </div>
            `;
            container.style.position = 'relative';
            container.appendChild(overlay);
        }
    }

    console.log(`Mappa inizializzata con ${validStations} stazioni`);
}

// Crea il contenuto del popup per una stazione
function createStationPopup(stazione) {
    let content = `
        <div class="station-popup">
            <div class="station-name mb-2">
                <strong>${stazione.nome}</strong>
            </div>
    `;

    if (!stazione.sensori || Object.keys(stazione.sensori).length === 0) {
        content += '<div class="text-muted">Nessun dato disponibile</div>';
    } else {
        // Ordine di visualizzazione sensori
        const sensorOrder = ['marea', 'vento', 'temp_aria', 'temp_acqua', 'onde_laguna', 'onde_mare', 'umidita', 'pressione'];
        
        sensorOrder.forEach(sensorType => {
            if (stazione.sensori[sensorType]) {
                content += createSensorPopupItem(sensorType, stazione.sensori[sensorType]);
            }
        });
    }

    content += `
            <div class="station-info mt-2 pt-2 border-top">
                <small class="text-muted">
                    📍 ${stazione.latitudine.toFixed(4)}, ${stazione.longitudine.toFixed(4)}<br>
                    🆔 ${stazione.id}
                </small>
            </div>
        </div>
    `;

    return content;
}

// Crea un elemento sensore per il popup
function createSensorPopupItem(sensorType, sensorData) {
    const icons = {
        'marea': '🌊',
        'vento': '💨',
        'temp_aria': '🌡️',
        'temp_acqua': '💧',
        'onde_laguna': '〰️',
        'onde_mare': '🌊',
        'umidita': '💦',
        'pressione': '📊'
    };

    const names = {
        'marea': 'Livello Mare',
        'vento': 'Vento',
        'temp_aria': 'Temp. Aria',
        'temp_acqua': 'Temp. Acqua',
        'onde_laguna': 'Onde Laguna',
        'onde_mare': 'Onde Mare',
        'umidita': 'Umidità',
        'pressione': 'Pressione'
    };

    const icon = icons[sensorType] || '📡';
    const name = names[sensorType] || sensorType;
    const time = formatStationTimeOnly(sensorData.timestamp);

    let valueHtml = '';

    switch (sensorType) {
        case 'marea':
            valueHtml = `<strong>${sensorData.livello} m</strong>`;
            break;
            
        case 'vento':
            const dir = degToCardinal(sensorData.direzione);
            const intensityKmh = (sensorData.intensita * 3.6).toFixed(1);
            const gustKmh = (sensorData.raffica * 3.6).toFixed(1);
            const arrow = generateWindArrow(sensorData.direzione);
            valueHtml = `
                <div><strong>${dir} ${arrow}</strong></div>
                <small>Int: ${intensityKmh} km/h, Raff: ${gustKmh} km/h</small>
            `;
            break;
            
        case 'temp_aria':
        case 'temp_acqua':
            valueHtml = `<strong>${sensorData.temperatura}°C</strong>`;
            break;
            
        case 'onde_laguna':
        case 'onde_mare':
            valueHtml = `
                <div><strong>Sig: ${sensorData.significativa} m</strong></div>
                <small>Max: ${sensorData.massima} m</small>
            `;
            break;
            
        case 'umidita':
        case 'pressione':
            const unit = sensorType === 'umidita' ? '%' : 'hPa';
            valueHtml = `<strong>${sensorData.valore} ${unit}</strong>`;
            break;
            
        default:
            valueHtml = '<strong>Dato disponibile</strong>';
    }

    return `
        <div class="sensor-popup-item mb-2">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <span class="me-2">${icon}</span>
                    <div>
                        <div class="sensor-name">${name}</div>
                        ${valueHtml}
                    </div>
                </div>
                <small class="text-muted">${time}</small>
            </div>
        </div>
    `;
}

// Geolocalizzazione utente
function toggleUserLocation() {
    if (!locateActive) {
        startUserLocation();
    } else {
        stopUserLocation();
    }
}

function startUserLocation() {
    if (!stazioniMap) return;
    
    locateActive = true;
    locateUser();
    locateInterval = setInterval(locateUser, 10000); // Ogni 10 secondi
}

function stopUserLocation() {
    locateActive = false;
    if (locateInterval) {
        clearInterval(locateInterval);
        locateInterval = null;
    }
    
    // Rimuovi marker utente
    if (userMarker) {
        stazioniMap.removeLayer(userMarker);
        userMarker = null;
    }
    if (userAccuracyCircle) {
        stazioniMap.removeLayer(userAccuracyCircle);
        userAccuracyCircle = null;
    }
}

function locateUser() {
    if (!stazioniMap) return;
    
    stazioniMap.locate({
        setView: false,
        watch: false,
        enableHighAccuracy: true
    });
}

// Event handlers geolocalizzazione
if (typeof L !== 'undefined') {
    // Definisci gli handler solo se Leaflet è caricato
    function setupMapEvents() {
        if (!stazioniMap) return;
        
        stazioniMap.on('locationfound', function(e) {
            console.log("Posizione utente trovata:", e.latlng);
            
            const userIcon = L.divIcon({
                className: 'user-marker',
                html: '<div style="width: 16px; height: 16px; background: #ff0000; border-radius: 50%; border: 2px solid white;"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            
            if (!userMarker) {
                userMarker = L.marker(e.latlng, { icon: userIcon }).addTo(stazioniMap);
            } else {
                userMarker.setLatLng(e.latlng);
            }
            
            if (!userAccuracyCircle) {
                userAccuracyCircle = L.circle(e.latlng, {
                    radius: e.accuracy,
                    color: '#ff0000',
                    fillColor: '#ff0000',
                    fillOpacity: 0.1,
                    weight: 1
                }).addTo(stazioniMap);
            } else {
                userAccuracyCircle.setLatLng(e.latlng);
                userAccuracyCircle.setRadius(e.accuracy);
            }
        });
        
        stazioniMap.on('locationerror', function(e) {
            console.error("Errore geolocalizzazione:", e.message);
        });
    }
}

// Cleanup quando si cambia vista
function cleanupStazioniMap() {
    stopUserLocation();
    
    if (stazioniMap) {
        stazioniMap.remove();
        stazioniMap = null;
    }
    
    stazioniMarkers = null;
    userMarker = null;
    userAccuracyCircle = null;
}

// Export funzioni globali
window.initStazioniMap = initStazioniMap;
window.cleanupStazioniMap = cleanupStazioniMap;
window.toggleUserLocation = toggleUserLocation;

// Setup automatico se la pagina è già carica
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('stazioni-map.js caricato - pronto per inizializzazione');
    });
} else {
    console.log('stazioni-map.js caricato - DOM già pronto');
}