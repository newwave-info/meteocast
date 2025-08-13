/**
 * ----------------------------------------------------------------------------
 *  geolocate.js – Gestione automatica posizione utente via Geolocation API
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Ottiene (quando consentito) la posizione GPS utente al primo accesso o su richiesta
 *   – Invoca l’API set-location.php per aggiornare coordinate/sessione/cookie sul server
 *   – Usa localStorage/sessionStorage per evitare richieste ripetute e consumi eccessivi di batteria
 *   – Previene fix GPS automatici se l’utente ha selezionato manualmente una località (flag “loc_mode”)
 *
 * Flusso:
 *   1. Se la modalità è “manual” (selezione da ricerca), il fix automatico è **saltato**
 *   2. Se **non** manual, controlla se è già stato fatto un fix di recente (<6h): se sì, non rifà il fix
 *   3. Se non c’è fix recente, chiama la Geolocation API del browser e invia le coordinate a set-location.php (via fetch POST)
 *   4. Se lo spostamento è minore di 3 km, nessun update (per ridurre traffico/chiamate)
 *   5. Salva ogni fix in localStorage/sessionStorage per uso futuro e per la gestione offline
 *   6. Gestisce il click sul pulsante “Usa GPS” (riabilita il fix automatico se l’utente torna al GPS)
 *
 * Dati chiave salvati lato client:
 *   – user_lat, user_lon: ultimi fix ottenuti (localStorage)
 *   – loc_time: timestamp ultimo fix (localStorage)
 *   – loc_mode: “manual” se selezione da ricerca, assente o altro per fix GPS
 *   – has_location: sessionStorage flag per evitare ripetizioni nel ciclo pagina
 *
 * Sicurezza/robustezza:
 *   – Se la geolocalizzazione fallisce, viene segnalato in console ma l’app resta funzionante
 *   – Solo fix significativi (>3 km) vengono inviati al server
 *
 * Uso:
 *   – Incluso automaticamente nelle pagine dove serve posizione aggiornata
 *   – Il pulsante #loc-gps-btn permette all’utente di ripristinare la localizzazione automatica
 * ----------------------------------------------------------------------------
 */

(() => {
  const RECHECK_HOURS   = window.LAGOON_CONFIG?.RECHECK_HOURS   ?? 6;
  const MIN_DISTANCE_KM = window.LAGOON_CONFIG?.MIN_DISTANCE_KM ?? 3;
  const LS_LAT = 'user_lat';
  const LS_LON = 'user_lon';
  const LS_TS  = 'loc_time';
  const SS_DONE= 'has_location';

  // ≡ 1) CONTROLLA SE L'UTENTE È IN MODALITÀ "MANUAL" ≡
  const isManual = localStorage.getItem('loc_mode') === 'manual';

  // ≡ 2) SE **NON** È MANUAL, ESEGUI IL FIX GPS AUTOMATICO ≡
  if (!isManual) {
    // Se abbiamo già fix recente → esci
    const lastTs = Number(localStorage.getItem(LS_TS) || 0);
    const ageH   = (Date.now() - lastTs) / 3.6e6;
    if (!(sessionStorage.getItem(SS_DONE) && ageH < RECHECK_HOURS)) {

      // --- util haversine ---
      const haversine = (lat1, lon1, lat2, lon2) => {
        const toRad = d => d * Math.PI / 180;
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat/2)**2 +
        Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
        return 2 * R * Math.asin(Math.sqrt(a));
      };

      const sendLocation = (lat, lon) => {
        const oldLat = +localStorage.getItem(LS_LAT);
        const oldLon = +localStorage.getItem(LS_LON);
        if (oldLat && oldLon && haversine(oldLat, oldLon, lat, lon) < MIN_DISTANCE_KM) {
          console.info('Spostamento < 3 km – non invio.');
          sessionStorage.setItem(SS_DONE, '1');
          return;
        }

        fetch('set-location.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ lat, lon })
        })
        .then(r => {
          if (!r.ok) throw new Error('POST ' + r.status);
          localStorage.setItem(LS_LAT, lat);
          localStorage.setItem(LS_LON, lon);
          localStorage.setItem(LS_TS, Date.now());
          sessionStorage.setItem(SS_DONE, '1');
          location.reload();
        })
        .catch(err => console.warn('GPS POST failed', err));
      };

      navigator.geolocation.getCurrentPosition(
        p => sendLocation(p.coords.latitude, p.coords.longitude),
        e => {
          console.warn('Geolocation error', e);
          sessionStorage.setItem(SS_DONE, '1');
        },
        { enableHighAccuracy:false, timeout:8000, maximumAge:180000 }
        );
    }
  }

  // ≡ 3) LISTENER SUL PULSANTE “USA GPS”  ≡
  document.getElementById('loc-gps-btn')?.addEventListener('click', () => {
    localStorage.removeItem('loc_mode');        // esce dalla modalità manuale
    sessionStorage.removeItem('has_location');  // forza nuovo fix al reload
    location.reload();
  });

})();
