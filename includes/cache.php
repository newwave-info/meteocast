<?php
/**
 * ----------------------------------------------------------------------------
 *  cache.php – wrapper per cache su disco (gzip, chiave MD5)
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Fornisce API semplici e centralizzate per cache locale di risposte HTTP/JSON
 *   – Usata da tutto il progetto: geocoding, meteo, DEM, ecc.
 *   – Evita richieste ripetute verso endpoint esterni e mantiene la quota API bassa
 *
 * Funzioni:
 *   - cache_get($key, $ttl)
 *       ▸ Ritorna stringa decodificata dalla cache se presente & valida (gzip)
 *       ▸ $key: string (es: "elev_45.4_12.3"), $ttl: time-to-live in secondi
 *   - cache_put($key, $data)
 *       ▸ Salva la stringa (es. JSON) come gzip in /cache/
 *       ▸ Riscrive se la chiave già esiste
 *   - fetch_json_cached($url, $key, $ttl = CACHE_DEFAULT_TTL)
 *       ▸ Prova cache con chiave $key e TTL, altrimenti esegue la fetch remota
 *       ▸ Se fetch avvenuta, salva subito in cache
 *       ▸ Decodifica e ritorna sempre array PHP (o array vuoto)
 *
 * Parametri globali:
 *   – CACHE_ENABLED      (ON/OFF globale, config.php)
 *   – CACHE_DIR          (cartella unica, config.php)
 *   – CACHE_DEFAULT_TTL  (TTL fallback, config.php)
 *
 * Note:
 *   – Tutte le chiavi sono convertite in MD5 per evitare path troppo lunghi
 *   – Funziona sia per piccoli payload che per risposte JSON grandi
 *   – La cartella /cache/ viene creata all’avvio se non esiste
 *   – Non usa alcun DB; solo filesystem locale, molto veloce e semplice da pulire
 *
 * Cambio TTL:
 *   – Modifica il valore passato a $ttl nelle chiamate delle funzioni
 *   – Oppure cambia CACHE_DEFAULT_TTL in config.php
 *
 * Uso tipico:
 *   $arr = fetch_json_cached($url, 'my_key_'.$lat.'_'.$lon, 3600);
 *
 * Sicurezza:
 *   – Funzioni safe: non lanciano errori se la cache non esiste o la fetch fallisce
 *   – Gzip automatico per risparmiare spazio
 *
 * Output:
 *   – Tutte le funzioni sono pensate per essere “drop-in”: non servono try/catch
 * ----------------------------------------------------------------------------
 */

if (!defined('CACHE_DIR')) {
    require_once __DIR__ . '/../config/config.php';
}

/* ---------- inizializza cartella cache ---------- */
if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);

/* ---------- helper interni ---------- */
function _cache_path(string $key): string {
    return CACHE_DIR . md5($key) . '.gz';
}

/* ---------- API basso livello ---------- */
function cache_get(string $key, int $ttl): ?string {
    if (defined('DEBUG_MODE') && DEBUG_MODE) error_log("[CACHE] GET key=$key ttl=$ttl");

    if (!CACHE_ENABLED) return null;
    $file = _cache_path($key);
    return (is_file($file) && time() - filemtime($file) <= $ttl)
    ? gzdecode(file_get_contents($file))
    : null;
}
function cache_put(string $key, string $data): void {
    if (CACHE_ENABLED) @file_put_contents(_cache_path($key), gzencode($data, 9));
}

/* ---------- fetch remoto con cache JSON ---------- */
function fetch_json_cached(string $url, string $key, int $ttl = CACHE_DEFAULT_TTL): array {
    $raw = cache_get($key, $ttl);
    if ($raw === null) {
        $raw = @file_get_contents($url);
        cache_put($key, $raw ?: '');
    }
    $arr = json_decode($raw ?: 'null', true);
    return is_array($arr) ? $arr : [];
}
