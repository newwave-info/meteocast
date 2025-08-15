(function () {
  let map, cluster, userMarker;

  function formatSensorTime(ts) {
    if (!ts) return '';
    try {
      if (window.luxon && window.luxon.DateTime) {
        const { DateTime } = window.luxon;
        const dt = DateTime.fromFormat(ts, 'yyyy-LL-dd HH:mm', { zone: 'UTC' }).plus({ hours: 1 });
        return dt.isValid ? dt.toFormat('HH:mm') : '';
      }
      const m = ts.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/);
      if (!m) return '';
      const d = new Date(Date.UTC(+m[1], +m[2]-1, +m[3], +m[4], +m[5]));
      d.setUTCHours(d.getUTCHours() + 1);
      return `${String(d.getUTCHours()).padStart(2,'0')}:${String(d.getUTCMinutes()).padStart(2,'0')}`;
    } catch(e) { return ''; }
  }

  function popupHtml(st) {
    let rows = '';
    if (st.sensori) {
      Object.keys(st.sensori).forEach(k => {
        const v = st.sensori[k];
        if (!v) return;
        const t = formatSensorTime(v.timestamp);
        const timeHtml = t ? ` <small class="text-muted">ore ${t}</small>` : '';

        if (k === 'vento') {
          const i = Math.round(Number(v.intensita));
          const g = Math.round(Number(v.raffica));
          const dir = (v.direzione != null) ? `${v.direzione}°` : '—';
          rows += `<div class="sensor-popup-item"><strong>Vento:</strong> ${i}/${g} km/h • ${dir}${timeHtml}</div>`;
        } else if (k === 'marea') {
          rows += `<div class="sensor-popup-item"><strong>Marea:</strong> ${v.livello} ${v.unita || ''}${timeHtml}</div>`;
        } else if (k === 'temp_aria') {
          rows += `<div class="sensor-popup-item"><strong>T. Aria:</strong> ${v.temperatura}${v.unita || '°C'}${timeHtml}</div>`;
        } else if (k === 'temp_acqua') {
          rows += `<div class="sensor-popup-item"><strong>T. Acqua:</strong> ${v.temperatura}${v.unita || '°C'}${timeHtml}</div>`;
        } else if (k === 'onde_laguna' || k === 'onde_mare') {
          rows += `<div class="sensor-popup-item"><strong>Onde:</strong> ${v.significativa} ${v.unita || 'm'} (sig.) — max ${v.massima} ${v.unita || 'm'}${timeHtml}</div>`;
        } else if (k === 'umidita') {
          rows += `<div class="sensor-popup-item"><strong>Umidità:</strong> ${v.valore}${v.unita || '%'}${timeHtml}</div>`;
        } else if (k === 'pressione') {
          rows += `<div class="sensor-popup-item"><strong>Pressione:</strong> ${v.valore} ${v.unita || 'hPa'}${timeHtml}</div>`;
        }
      });
    }
    return `<div class="station-popup">
      <div class="station-name">${st.nome || 'Stazione'}</div>
      ${rows || '<div class="text-muted">Nessun dato sensori</div>'}
    </div>`;
  }

  function getStazioniData() {
    const el = document.getElementById('stazioni-map');
    if (!el) return [];
    const raw = el.getAttribute('data-stazioni');
    if (!raw) return [];
    try { return JSON.parse(raw); } catch(e) { return []; }
  }

  function getUserPos() {
    const el = document.getElementById('stazioni-map');
    if (el) {
      const latAttr = el.getAttribute('data-user-lat');
      const lonAttr = el.getAttribute('data-user-lon');
      const lat = latAttr ? Number(latAttr) : NaN;
      const lon = lonAttr ? Number(lonAttr) : NaN;
      if (Number.isFinite(lat) && Number.isFinite(lon)) return [lat, lon];
    }
    const wd = window.weatherData || {};
    const lat2 = (wd.location && wd.location.latitude) ?? wd.latitude;
    const lon2 = (wd.location && wd.location.longitude) ?? wd.longitude;
    if (typeof lat2 === 'number' && typeof lon2 === 'number') return [lat2, lon2];
    return null;
  }

  function addUserMarker() {
    const pos = getUserPos();
    if (!pos || !window.L) return null;
    const userIcon = L.divIcon({
      className: 'user-marker',
      html: '<div class="um-dot"></div>',
      iconSize: [18,18],
      iconAnchor: [9,9]
    });
    userMarker = L.marker(pos, { icon: userIcon, title: 'La tua posizione' });
    userMarker.addTo(map);
    return pos;
  }

  function init() {
  if (map || !window.L) return;

  // usa lo stesso elemento da cui leggi i data-attributes
  const container = document.getElementById('stazioni-map'); // ← cambia qui se il tuo container ha un id diverso
  if (!container) return;

  container.innerHTML = '';

  const low = (window.POWER && window.POWER.isLow());

  map = L.map(container, {
    zoomControl: true,
    attributionControl: true,
    preferCanvas: true,   // meno layout/repaint
    updateWhenIdle: true  // meno ricalcoli in pan/zoom
  });
  map.setView([45.439, 12.33], 11); // ← FIX: niente “.” orfano

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap',
    detectRetina: !low // evita @2x in low-power
  }).addTo(map);

  // cluster fallback se il plugin non è presente
  cluster = (L.markerClusterGroup
    ? L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        disableClusteringAtZoom: 15
      })
    : L.layerGroup());

  const data = getStazioniData();
  data.forEach(st => {
    const lat = Number(st.latitudine);
    const lon = Number(st.longitudine);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
    const m = L.marker([lat, lon], { title: st.nome || '' });
    m.bindPopup(popupHtml(st));
    cluster.addLayer(m);
  });

  map.addLayer(cluster);

  const userPos = addUserMarker();

  let bounds = null;
  if (cluster.getLayers && cluster.getLayers().length > 0) bounds = cluster.getBounds();
  if (userPos) {
    const userLL = L.latLng(userPos[0], userPos[1]);
    bounds = bounds ? bounds.extend(userLL) : L.latLngBounds([userLL, userLL]);
  }
  if (bounds) {
    try { map.fitBounds(bounds.pad(0.12)); } catch(e) {}
  } else {
    map.setView([45.439, 12.33], 11);
  }

  requestAnimationFrame(() => map.invalidateSize());
}


  function mountWhenReady() {
    if (document.getElementById('map-container')) { init(); return; }
    const obs = new MutationObserver(() => {
      if (document.getElementById('map-container')) {
        obs.disconnect(); init();
      }
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  function waitLeaflet() {
    if (window.L) { mountWhenReady(); return; }
    const t = setInterval(() => {
      if (window.L) { clearInterval(t); mountWhenReady(); }
    }, 120);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', waitLeaflet);
  } else {
    waitLeaflet();
  }
})();