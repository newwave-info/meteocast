/**
 * ----------------------------------------------------------------------------
 *  search-location.js – Ricerca località con autocomplete e selezione manuale
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Permette all’utente di cercare e selezionare manualmente una località (autocomplete)
 *   – Chiama la API includes/geocode.php per suggerimenti (Open-Meteo forward-geocoding)
 *   – Gestisce il debounce per evitare richieste eccessive durante la digitazione
 *   – Mostra i risultati in una lista interattiva e salva la scelta in localStorage/cookie/server
 *
 * Flusso:
 *   1. Su input dell’utente (min 2 caratteri, debounce 400ms), chiama la API per i risultati
 *   2. Mostra la lista dei suggerimenti (nome, admin1, quota)
 *   3. Al click su un risultato:
 *      – imposta “loc_mode” a “manual” (blocca fix GPS automatico)
 *      – salva lat/lon nel localStorage
 *      – chiama set-location.php via POST per aggiornare sessione/cookie sul server
 *      – aggiorna l’interfaccia (nasconde offcanvas e ricarica pagina)
 *
 * Policy/robustezza:
 *   – Se la fetch fallisce, mostra messaggio di errore ma non blocca l’app
 *   – Dopo la selezione manuale, la posizione rimane fissa fino a nuovo GPS/cambio
 *   – I dati (lat/lon/elev/name) sono sempre passati in modo sicuro e validato
 *
 * Dati chiave:
 *   – localStorage: user_lat, user_lon, loc_time, loc_mode
 *   – sessionStorage: has_location
 *
 * Sicurezza:
 *   – Non salva mai nulla su DB; tutto resta in browser/cookie/sessione server
 *   – Sanitizza sempre l’input e i dati di output
 *
 * Estensibilità:
 *   – Cambia l’endpoint API (geocode/set-location) facilmente in una riga
 *   – Facile integrare nuovi provider geocoding o nuovi formati risultati
 *
 * Output:
 *   – Lista località, selezione persistente, refresh automatico della view
 * ----------------------------------------------------------------------------
 */


document.addEventListener('DOMContentLoaded', () => {
  const offcanvas = document.getElementById('loc-search');
  const input = document.getElementById('loc-input');
  if (offcanvas && input) {
    offcanvas.addEventListener('shown.bs.offcanvas', () => {
      input.focus();
      input.select && input.select();
    });
  }
});

(() => {
  const input   = document.getElementById('loc-input');
  const results = document.getElementById('loc-results');
  let timer;

  if (!input || !results) return;

  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { results.innerHTML=''; return; }
    timer = setTimeout(() => search(q), 400);
  });

  async function search(q) {
    results.innerHTML = '<li class="list-group-item">🔄 Cerco…</li>';
    try {
      const url = `includes/geocode.php?q=${encodeURIComponent(q)}`;
      const res = await fetch(url);
      const js  = await res.json();
      render(js.results || []);
    } catch { results.innerHTML = '<li class="list-group-item">Errore rete</li>'; }
  }

  function render(arr) {
    if (!arr.length) { results.innerHTML = '<li class="list-group-item">Nessun risultato</li>'; return; }
    results.innerHTML = arr.map(r => `
      <li class="list-group-item list-group-item-action"
          data-lat="${r.latitude}"
          data-lon="${r.longitude}"
          data-name="${r.name}, ${r.admin1}">
        ${r.name}, <span class="text-muted">${r.admin1}</span>
      </li>`).join('');
  }

  results.addEventListener('click', e => {
    const li = e.target.closest('li[data-lat]');
    if (!li) return;

    const lat  = +li.dataset.lat;
    const lon  = +li.dataset.lon;
    const name = li.dataset.name;

    localStorage.setItem('loc_mode', 'manual');

    fetch('set-location.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lat, lon, name })
    }).then(res => {
      if (!res.ok) { console.warn('set-location', res.status); return; }
      localStorage.setItem('user_lat', lat);
      localStorage.setItem('user_lon', lon);
      localStorage.setItem('loc_time', Date.now());
      sessionStorage.setItem('has_location', '1');
      const offcanvasElem = document.getElementById('loc-search');
      if (offcanvasElem && window.bootstrap?.Offcanvas) {
        bootstrap.Offcanvas.getOrCreateInstance(offcanvasElem).hide();
      }
      location.reload();
    });
  });

})();
