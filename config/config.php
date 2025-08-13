<?php
/**
 * ----------------------------------------------------------------------------
 *  config.php – Configurazione globale LagoonWeather
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Centralizza tutte le costanti chiave e i parametri di ambiente
 *   – Gestisce posizione utente (da sessione/cookie o default Venezia)
 *   – Fornisce costanti globali (ROOT_PATH, BASE_URL, LATITUDE, LONGITUDE, ecc.)
 *   – Imposta il fuso orario (TIMEZONE)
 *   – Controlla tutte le opzioni di cache per API, geocoding, meteo, ecc.
 *
 * Flusso:
 *   1. Sessione PHP sempre attiva per gestire lat/lon/nome utente
 *   2. LATITUDE/LONGITUDE e NAME/ELEVATION sono “sticky”:
 *      – prima cerca in $_SESSION, poi in $_COOKIE, altrimenti default Venezia
 *   3. Tutte le chiamate a Open-Meteo, Nominatim, ecc. usano queste costanti
 *   4. TTL cache modificabile da un solo punto (CACHE_DEFAULT_TTL)
 *   5. Tutta la struttura dei percorsi/dischi/dipendenze parte da qui (ROOT_PATH)
 *
 * Note:
 *   – Puoi cambiare il valore default delle coordinate per una nuova località di base
 *   – DEBUG_MODE: puoi usarlo per abilitare log, stampe debug, ecc.
 *   – CACHE_ENABLED: disattiva/attiva tutte le cache disco in una sola riga
 *   – CACHE_DIR: tutte le cache PHP puntano qui
 *   – Se modifichi il path base (BASE_URL), cambia qui e in tutti gli include di JS
 *
 * Sicurezza:
 *   – Nessuna password o chiave privata qui: solo config pubblica e parametri di ambiente
 *   – I percorsi sono sempre calcolati dinamicamente per evitare errori in produzione
 *
 * Uso:
 *   – Include questo file in ogni script PHP che deve conoscere posizione/config globale
 * ----------------------------------------------------------------------------
 */



/* ---------- percorsi base ---------- */
if (!defined('ROOT_PATH')) define('ROOT_PATH', realpath(dirname(__DIR__)));
define('BASE_URL',  '');
session_start();

// In config.php
define('TIMEZONEDB_API_KEY', '4FWPF6UOB85R');

/* ---------- posizione utente ---------- */

define('LATLON_PRECISION', 3); // Decimali per tutte le chiavi/cache/endpoint

function round_coord($c) {
    return round(floatval($c), LATLON_PRECISION);
}


$lat = isset($_SESSION['user_lat']) ? round_coord($_SESSION['user_lat'])
    : (isset($_COOKIE['user_lat']) ? round_coord($_COOKIE['user_lat']) : null);
$lon = isset($_SESSION['user_lon']) ? round_coord($_SESSION['user_lon'])
    : (isset($_COOKIE['user_lon']) ? round_coord($_COOKIE['user_lon']) : null);

define('LATITUDE',  $lat);
define('LONGITUDE', $lon);




define('LOCATION_NAME', $_SESSION['user_location_name'] ?? $_COOKIE['user_location_name'] ?? 'Venezia');

define('LOCATION_ELEVATION', is_numeric($_SESSION['user_elev'] ?? $_COOKIE['user_elev'] ?? null)
  ? floatval($_SESSION['user_elev'] ?? $_COOKIE['user_elev'])
  : null);



/* ---------- meteo ---------- */
define('OPEN_METEO_MODEL', 'best_match'); // 'best_match', 'icon_eu', 'icon_global', gem_global', 'ecmwf_ifs025', 'gfs_global', o null per default


/**
 * Fasce orarie per la suddivisione della giornata (mattina, pomeriggio, sera, notte).
 * Usate per narrativa, alert e organizzazione grafici/meteo-box.
 * Modifica qui per cambiare la logica di suddivisione "umana" delle ore.
 */
define('TIME_BUCKETS', [
    'mattina'    => [7, 12],    // 7:00 - 12:59
    'pomeriggio' => [13, 18],   // 13:00 - 18:59
    'sera'       => [19, 22],   // 19:00 - 22:59
    'notte'      => [23, 6]     // 23:00 - 06:59 (ciclo su due giorni)
]);

/**
 * Numero massimo di giorni per le previsioni meteo (orizzonte dati Open-Meteo).
 * Puoi aumentare fino a 14 se necessario, tenendo conto della quota API.
 */
define('FORECAST_DAYS', 8);   // ≤ 14

define('FORECAST_DAYS_CAROUSEL', 6); // Quanti giorni vuoi mostrare (es: 4 → dopodomani + altri 3)
define('MARINE_DAYS', 1); // 3 giorni di dati marini per ogni fetch


/**
 * Numero massimo di ore mostrate nella view "oggi" (forecast orario).
 * Modifica qui per cambiare la finestra "scorrevole" oraria della pagina principale.
 */
define('FORECAST_HOURS_TODAY', 24);
/**
 * Numero di ore per previsioni dettagliate a 15 minuti.
 * Determina la granularità temporale del widget forecast immediato.
 */
define('FORECAST_15MIN_HOURS', 2);  // ← AGGIUNGI QUI

/**
 * Numero massimo di ore mostrate nei semafori comfort/sicurezza.
 * Determina quante "bollini orari" vengono mostrati nel widget safety/comfort.
 */
define('SEMAFORI_HOURS_TODAY', 12);

/**
 * Ore di default quando NON viene passato $hoursAhead per la finestra “oggi”.
 * Manteniamo SEMAFORI_HOURS_TODAY come alias legacy per compatibilità.
 */
define('DEFAULT_HOURS_TODAY', SEMAFORI_HOURS_TODAY);


/**
 * Numero di ore in avanti da scandagliare per la ricerca di allerte meteo nella giornata odierna.
 * Imposta quanto il sistema deve essere "reattivo": valori bassi = più focus sul breve termine.
 */
//define('ALERT_HOURS_TODAY', 2);

/**
 * Ora di inizio della finestra "future alert" (giorni diversi da oggi).
 * Determina da che ora considerare i dati orari per la ricerca di allerte nei giorni successivi.
 */
define('ALERT_HOURS_FUTURE_START', 8);  // Es: dalle 08:00

/**
 * Ora di fine della finestra "future alert" (giorni diversi da oggi).
 * Determina fino a che ora includere i dati orari per la ricerca di allerte nei giorni successivi.
 */
define('ALERT_HOURS_FUTURE_END', 22);   // Es: fino alle 20:00

/**
 * Numero di ore storiche mostrate nei grafici (prima di "adesso").
 * Es: 4 = mostra le ultime 4 ore passate.
 */
define('CHART_HOURS_HISTORY', 4);

/**
 * Numero di ore in previsione mostrate nei grafici ("futuro" rispetto a ora).
 * Es: 20 = mostra le prossime 20 ore.
 */
define('CHART_HOURS_FORECAST', 24);



define('CHART_DAYS_FORECAST', 7);


// Dati sensori laguna, open-data Comune Venezia: aggiornamento tipico 15-30 min
define('CACHE_TTL_SENSORS', 30 * 60); // 30 minuti
/**
 * ----------------------------------------------------------------------------
 *  Configurazione frequenza aggiornamento posizione utente (geolocate.js)
 * ----------------------------------------------------------------------------
 *
 *  RECHECK_HOURS:
 *    - Intervallo (in ore) dopo il quale il client JS tenta un nuovo fix GPS automatico,
 *      se l'utente non è in modalità "manual".
 *    - Esempio: 0.5 = ogni 30 minuti, 1 = ogni ora, ecc.
 *
 *  MIN_DISTANCE_KM:
 *    - Distanza minima (in km) che l'utente deve percorrere rispetto all'ultimo fix
 *      per inviare un nuovo aggiornamento di posizione al server.
 *    - Serve per evitare aggiornamenti inutili se l'utente è fermo o si muove di poco.
 *
 *  Questi valori vengono passati al front-end tramite window.LAGOON_CONFIG,
 *  e gestiti dalla logica di geolocate.js.
 * ----------------------------------------------------------------------------
 */

define('RECHECK_HOURS', 0.5); // ogni 30 minuti tenta un nuovo fix GPS
define('MIN_DISTANCE_KM', 1); // aggiorna solo se ti sposti di almeno 1000 metri


define('DEBUG_MODE', true);




// define('TIDE_DAYS_FORWARD', 7);

/* ---------- fuso orario ---------- */
// define('TIMEZONE', 'Europe/Rome');
// date_default_timezone_set(TIMEZONE);
// Recupera timezone da sessione/cookie, altrimenti fallback Europe/Rome
$tz = $_SESSION['user_timezone'] ?? $_COOKIE['user_timezone'] ?? 'Europe/Rome';
define('TIMEZONE', $tz);
date_default_timezone_set(TIMEZONE);


/* ---------- debug ---------- */
// define('DEBUG_MODE', false);

/* ---------- impostazioni cache ---------- */
define('CACHE_ENABLED',     true);                   // ON/OFF globale
define('CACHE_DIR',         ROOT_PATH . '/cache/');  // cartella unica

/* ===========================================================================
 *  TTL Cache & Persistenza – Consigli pratici su ogni costante
 * ===========================================================================
 *
 * CACHE_DEFAULT_TTL
 *   → Fallback generico per la cache se non specificato altrove (default 1h).
 *     Non serve toccarlo se usi le costanti modulo-specifiche qui sotto.
 *
 * CACHE_TTL_FORECAST
 *   → Previsioni meteo principali (Open-Meteo core).
 *     - Consigliato: 30-60 min per uso personale; 1-3h per produzione pubblica.
 *     - Più basso = dati più freschi, ma più chiamate API.
 *
 * CACHE_TTL_GEOCODE
 *   → Cache per suggerimenti/autocomplete città/località (search Open-Meteo).
 *     - Consigliato: 7-14 giorni. I nomi cambiano raramente.
 *     - Più lungo = meno chiamate, risposta quasi sempre immediata.
 *
 * CACHE_TTL_GEOCODING
 *   → Cache per reverse geocoding (da coordinate a nome/quota).
 *     - Consigliato: 30-90 giorni o più. La geografia non cambia quasi mai.
 *     - Lungo TTL evita hit ripetuti sulle stesse coordinate.
 *
 * USER_LOCATION_TTL
 *   → Quanto a lungo ricordare la posizione utente (cookie + sessione).
 *     - Consigliato: 7 giorni, ma puoi alzare/abbassare liberamente.
 *     - Zero privacy issue: tutto solo lato browser/server, mai in DB.
 *
 * CACHE_TTL_MARINE
 *   → Previsioni marine/onde/SST.
 *     - Consigliato: 1h (tipico aggiornamento dati marine).
 *     - Scendi a 30min solo se davvero ti serve il dato “istantaneo”.
 *
 * CACHE_TTL_SENSORS
 *   → Dati sensori laguna, open-data Comune.
 *     - Consigliato: 30-60 min (aggiornamento reale dipende dal sensore).
 *
 * CACHE_TTL_SOLUNAR
 *   → Dati solunari e fasi lunari per pesca.
 *     - Consigliato: 12-24h. Questi dati cambiano pochissimo di giorno in giorno.
 *
 * --- Regola generale ---
 *  • Dati "statici" (geocoding, DEM, solunare): TTL lungo.
 *  • Dati "live" (forecast, marine, sensori): TTL = frequenza reale di aggiornamento dati provider + margine.
 *  • Sessione/cookie utente: quanto vuoi che la posizione resti "sticky".
 *
 *  Modifica qui per cambiare durata cache e persistenza su tutto il progetto!
 * ===========================================================================
 */

define('CACHE_DEFAULT_TTL',    60 * 60 * 1);             // 1h fallback generico
define('CACHE_TTL_FORECAST',   60 * 5);    // 10 min forecast meteo core
define('CACHE_TTL_FORECAST_15MIN', 1200); // 20 minuti
define('CACHE_TTL_GEOCODE',    60 * 60 * 24 * 10); // 10 giorni autocomplete ricerca
define('CACHE_TTL_GEOCODING',  60 * 60 * 24 * 30);       // 30 giorni reverse geocoding
define('USER_LOCATION_TTL',    60 * 60 * 24 * 7); // 7 gg posizione utente


// ======================
//  CACHE TTL - MAREA
// ======================
define('CACHE_TTL_MARINE', 60 * 60); // 1 ora per le maree

// Estremali di marea (Punta Salute, CNR): tipico update 15 min (Comune Venezia)
define('CACHE_TTL_TIDE_EXTREMES', 15 * 60); // 15 minuti

// Serie storica/5-minuti (dato quasi real-time)
define('CACHE_TTL_TIDE_SERIES', 10 * 60);   // 10 minuti

// Previsione marea (estremali futuri)
define('CACHE_TTL_TIDE_FORECAST', 30 * 60); // 30 minuti



