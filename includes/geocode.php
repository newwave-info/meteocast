<?php
/**
 * ----------------------------------------------------------------------------
 *  API – Forward-geocoding per ricerca località/autocomplete (geocode.php)
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Riceve query (GET ?q=…) dal front-end per ricerca rapida città/località
 *   – Interroga l’endpoint Open-Meteo “/search” (forward-geocoding)
 *   – Restituisce il JSON originale, opportunamente cache-ato, per ridurre le chiamate API
 *   – Usato solo per suggerimenti/autocomplete: la selezione finale passa a set-location.php
 *
 * Flusso dettagliato:
 *   1. Riceve una query string “q” (es: “Milano”, “Burano”)
 *   2. Se la query è vuota, restituisce subito { "results": [] }
 *   3. Costruisce la chiamata all’endpoint Open-Meteo Geocoding (max 8 risultati)
 *   4. Passa tutto da fetch_json_cached() (cache disco, TTL 7gg)
 *   5. Restituisce il JSON (anche se array vuoto), sempre come Content-Type: application/json
 *
 * Dipendenze:
 *   – config/config.php    (costanti globali per la cache)
 *   – includes/cache.php   (gestione cache, fetch_json_cached)
 *
 * Parametri cache:
 *   – Chiave: "search_<q>"
 *   – TTL: 7 giorni (60*60*24*7)
 *
 * Sicurezza/Robustezza:
 *   – Non esegue SQL, nessun dato sensibile lato server
 *   – Anche se la fetch fallisce, ritorna sempre un JSON valido
 *
 * Output:
 *   – { "results": [ … ] }    (direttamente da Open-Meteo oppure array vuoto)
 * ----------------------------------------------------------------------------
 */


header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/cache.php';

/* -------------------------------------------------
 *  Parametri & validazione input
 * ------------------------------------------------- */
$q = trim($_GET['q'] ?? '');
if ($q === '') {           // input vuoto → array vuoto
    echo '{"results":[]}';
    exit;
}

/* -------------------------------------------------
 *  Costruzione URL API Open-Meteo
 * ------------------------------------------------- */
$url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
    'name'     => $q,
    'count'    => 8,
    'language' => 'it',
]);

/* -------------------------------------------------
 *  Download + cache (TTL 7 giorni)
 * ------------------------------------------------- */
$results = fetch_json_cached($url, 'search_' . strtolower($q), CACHE_TTL_GEOCODE);
echo json_encode($results ?: ['results' => []]);
