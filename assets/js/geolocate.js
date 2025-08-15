/**
 * ----------------------------------------------------------------------------
 *  geolocate.js – Gestione automatica posizione utente via Geolocation API
 * ----------------------------------------------------------------------------
 *  Flusso riassunto:
 *   - Se loc_mode = 'manual' → non fa nulla
 *   - Altrimenti, se non c'è fix recente (< RECHECK_HOURS) e non abbiamo già tentato
 *     in questa sessione → prova un getCurrentPosition con opts dipendenti dal power state
 *   - Se lo spostamento < MIN_DISTANCE_KM → non invia nulla al server
 *   - Al successo salva lat/lon/ts e ricarica la pagina (per aggiornare meteo, ecc.)
 *   - Se il tab è nascosto → aspetta che torni visibile prima di chiedere il permesso
 * ----------------------------------------------------------------------------
 */

(() => {
  'use strict';

  const RECHECK_HOURS   = window.LAGOON_CONFIG?.RECHECK_HOURS   ?? 6;
  const MIN_DISTANCE_KM = window.LAGOON_CONFIG?.MIN_DISTANCE_KM ?? 3;

  const LS_LAT = 'user_lat';
  const LS_LON = 'user_lon';
  const LS_TS  = 'loc_time';
  const SS_DONE= 'has_location';

  // 1) Esci se non supportato
  if (!('geolocation' in navigator)) {
    console.info('[geo] Geolocation non supportata.');
    return;
  }

  // 2) Esci se l’utente è in modalità manuale
  const isManual = localStorage.getItem('loc_mode') === 'manual';
  if (isManual) return;

  // 3) Evita richieste ripetute nella stessa sessione e rispetta il TTL
  const lastTs = Number(localStorage.getItem(LS_TS) || 0);
  const ageH   = (Date.now() - lastTs) / 3.6e6;
  const alreadyTriedThisSession = !!sessionStorage.getItem(SS_DONE);
  if (alreadyTriedThisSession && ageH < RECHECK_HOURS) {
    // Hai già provato in questa sessione e il fix è “fresco” → esci
    return;
  }

  // 4) Helpers
  const toRad = d => d * Math.PI / 180;
  const haversine = (lat1, lon1, lat2, lon2) => {
    const R = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2)**2 +
              Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
    return 2 * R * Math.asin(Math.sqrt(a));
  };

  const sendLocation = (lat, lon) => {
    const oldLat = Number(localStorage.getItem(LS_LAT));
    const oldLon = Number(localStorage.getItem(LS_LON));

    if (Number.isFinite(oldLat) && Number.isFinite(oldLon)) {
      const d = haversine(oldLat, oldLon, lat, lon);
      if (d < MIN_DISTANCE_KM) {
        console.info(`[geo] Spostamento < ${MIN_DISTANCE_KM} km – non invio.`);
        sessionStorage.setItem(SS_DONE, '1');
        return;
      }
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
    .catch(err => {
      console.warn('[geo] Invio coordinate fallito:', err);
      sessionStorage.setItem(SS_DONE, '1'); // evita loop nella sessione corrente
    });
  };

  // 5) Callback richieste dal browser (FIX: erano mancanti)
  function onOk(position) {
    try {
      const { latitude, longitude } = position.coords || {};
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        throw new Error('Coordinate non valide');
      }
      sendLocation(Number(latitude.toFixed(6)), Number(longitude.toFixed(6)));
    } catch (e) {
      console.warn('[geo] Errore lettura posizione:', e);
      sessionStorage.setItem(SS_DONE, '1');
    }
  }

  function onErr(err) {
    console.warn('[geo] getCurrentPosition error:', err?.code, err?.message);
    // Segna come “tentato” per non riprovare in loop in questa sessione
    sessionStorage.setItem(SS_DONE, '1');
    // Non ricarichiamo, l’app resta sulle coordinate correnti/cached
  }

  // 6) Non chiedere permessi con tab/PWA in background: aspetta che torni visibile
  function requestWhenVisible() {
    // Power-aware options
    const low = (window.POWER && window.POWER.isLow());
    const geoOpts = low
      ? { enableHighAccuracy:false, timeout:8000, maximumAge:300000 } // 5 min cache
      : { enableHighAccuracy:true,  timeout:8000, maximumAge:60000  }; // 1 min cache

    navigator.geolocation.getCurrentPosition(onOk, onErr, geoOpts);
  }

  if (document.visibilityState === 'hidden') {
    const once = () => {
      document.removeEventListener('visibilitychange', once);
      requestWhenVisible();
    };
    document.addEventListener('visibilitychange', once, { once: true });
  } else {
    // Facoltativo: se il browser espone Permissions API e lo stato è "denied", evita di chiedere
    if (navigator.permissions?.query) {
      navigator.permissions.query({ name: 'geolocation' }).then(p => {
        if (p.state === 'denied') {
          sessionStorage.setItem(SS_DONE, '1');
          return;
        }
        requestWhenVisible();
      }).catch(requestWhenVisible);
    } else {
      requestWhenVisible();
    }
  }

  // 7) Pulsante “Usa GPS” – ripristina modalità automatica e forza nuovo fix
  document.getElementById('loc-gps-btn')?.addEventListener('click', () => {
    localStorage.removeItem('loc_mode');        // esce dalla modalità manuale
    sessionStorage.removeItem(SS_DONE);         // permetti nuovo tentativo
    location.reload();
  });

})();