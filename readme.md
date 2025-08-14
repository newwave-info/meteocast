# MeteoCast
Web-app **mobile-first** per meteo, luna, maree (Venezia) e sensori lagunari.  
Stack: **PHP 8.2** (senza framework), **Bootstrap 5**, **Chart.js** (+ Luxon), **Leaflet**, **PWA**.

Dominio: `https://meteocast.newwave-media.it`

---

## Obiettivo
Fornire una UX moderna e leggera per condizioni attuali e previsioni, con add-on locali (marea/sensori Venezia) e calcoli astronomici/solunari **in locale**. Progettata per scalare su qualsiasi lat/lon.

---

## Stack
- **Back-end:** PHP 8.2 (no framework)
- **Front-end:** Bootstrap 5, Chart.js (+ Luxon time scale), Bootstrap Icons, weather-icons, SVG meteo custom
- **Mappe:** Leaflet + MarkerCluster (stazioni Venezia)
- **PWA:** `service-worker.js` + `manifest.json` (display `standalone`, cache versionata)
- **Design:** mobile-first, minimale (palette blu scuro/acqua/bianco/grigi; font Inter/Rubik/Poppins)

---

## Struttura
/assets/{css,js,icons}   # UI, grafici, mappe, background animati
/cache/                  # cache JSON gzip su disco (scrivibile)
/config/                 # costanti globali (lat/lon, TTL, modelli, ecc.)
/includes/               # fetch Open-Meteo, solunare, geocode, cache utils, helpers
/partials/               # viste modulari (oggi, previsioni, luna-marea, stazioni)
index.php                # shell + header/footer; SPA client-side
partials/common/view.php # router viste (oggi|previsioni|luna-marea|stazioni)
manifest.json            # PWA manifest
service-worker.js        # precache + runtime cache
set-location.php         # salva posizione (sessione/cookie), loc_mode gps|manual

---

## Viste
- **Oggi** — attuale + prossime ore (mini-grafici: vento, temperatura, precipitazioni).
- **Previsioni** — daily + tri-orario (7–8 giorni).
- **Luna & Maree**
  - **Luna:** fase/illuminazione + sorge/tramonta (calcolo locale, no quote).
  - **Marea Venezia (Punta della Salute):** ingest open-data Comune → ultimo estremo passato + **3 prossimi**, con trend/valori.
  - *(Grafico marea opzionale se `window.tideData` presente).*
- **Stazioni (Laguna Venezia)** — elenco + mappa Leaflet con cluster e popup sensori.

> **Nota:** codice per **Marine Open-Meteo** (`/v1/marine`) presente ma **disattivato** (non incluso in nessuna vista).

---

## Fonti & Dati
- **Meteo core:** Open-Meteo `/v1/forecast` (current/hourly/daily). Modello configurabile: `OPEN_METEO_MODEL='best_match'`.
- **Minutely 15' (opzionale):** Open-Meteo `minutely_15` per alta risoluzione (quando abilitato).
- **Luna/Solunare:** calcolo locale in PHP (SunCalc + logica solunare).
- **Marea Venezia:** open-data **Comune di Venezia** (previsione estremali normalizzata).
- **Geocoding/Timezone/DEM:** Open-Meteo geocoding + reverse + DEM; timezone con fallback; autocomplete ricerca.

---

## Flusso dati (quota-friendly)
1. Client → `partials/common/view.php` (router PHP)
2. `config/config.php` → `includes/api-fetch.php` → `includes/helpers.php`
3. Cache server-side (`includes/cache.php`, file gzip con chiavi MD5 e TTL)
4. Export verso JS (`partials/common/js-weather-data.php`) → `window.weatherData`, `window.appTimezone`, `window.chartSettings`
5. Grafici Chart.js in `assets/js/my-charts.js` (distruzione/ricreazione sicura al cambio vista)

### Linee guida quote
- **/v1/forecast:** ≤10 variabili, ≤14 giorni → 1 call (TTL 30–60′)
- **/v1/marine:** disabilitato
- **Geocoding:** on-demand (cache lunga)
- **Solunare:** calcolo locale (nessuna quota)

---

## Caching & TTL
- Forecast core: **30–60 min**
- Minutely 15': **~20 min** (se abilitato)
- Marea/Sensori Venezia: **30–60 min**
- Geocoding/Reverse/DEM: **10–30 giorni**
- **Chiavi cache:** `md5(round(lat,4).'-'.round(lon,4).'-'+endpoint+params)`

---

## Geolocalizzazione & preferenze
- Fix GPS condizionato (distanza minima/TTL locale), disattivato se **manual mode** attivo.
- Persistenza server: `set-location.php` salva lat/lon/nome/DEM/timezone in **sessione + cookie** (7gg).

---

## PWA
- **Manifest (`manifest.json`)**: icone, `start_url: "/"`, display `standalone`, tema scuro.
- **Service Worker (`service-worker.js`)**:
  - **Precache** asset core
  - **Runtime**: **network-first** per JSON/API con fallback a cache; **cache-first** per statici
  - Cleanup cache versionato

---

## Requisiti & Avvio locale
- PHP 8.2, estensioni standard
- Directory `/cache` **scrivibile** dal web server

Avvio rapido:
```bash
php -S localhost:8080 -t .