/* ============================================================================
 * power-manager.js — Gestione Low Power/Background centralizzata
 *  - Stato: window.POWER.isLow(), .isHidden(), .setOverride('auto'|'low'|'normal')
 *  - Evento: document.dispatchEvent(new CustomEvent('power:change',{detail:{low,hidden}}))
 *  - Scrive su <html> gli attributi: data-power="low|normal", data-visibility="hidden|visible"
 *  - Aggiunge <body>.battery-saver (compat per codice esistente)
 * ============================================================================ */
(function () {
  const html = document.documentElement;
  const body = document.body;

  const mqlPRM = window.matchMedia?.('(prefers-reduced-motion: reduce)');
  const net = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;

  let override = 'auto'; // 'auto' | 'low' | 'normal'
  let last = { low: false, hidden: document.visibilityState === 'hidden' };

  function hidden() { return document.visibilityState === 'hidden'; }

  function computeLow() {
    if (override === 'low') return true;
    if (override === 'normal') return false;

    const prm = !!mqlPRM?.matches;
    const saveData = !!net?.saveData;
    const lowMem = (navigator.deviceMemory && navigator.deviceMemory <= 2) ? true : false;

    // Battery API opzionale
    let batteryHint = false;
    if (navigator.getBattery && window.__batteryLevel__ != null && window.__batteryCharging__ != null) {
      batteryHint = (!window.__batteryCharging__ && window.__batteryLevel__ <= 0.15);
    }
    return prm || saveData || lowMem || batteryHint;
  }

  function applyState() {
    const st = { low: computeLow(), hidden: hidden() };
    html.setAttribute('data-power', st.low || st.hidden ? 'low' : 'normal');
    html.setAttribute('data-visibility', st.hidden ? 'hidden' : 'visible');
    body.classList.toggle('battery-saver', !!(st.low || st.hidden));
    if (st.low !== last.low || st.hidden !== last.hidden) {
      last = st;
      document.dispatchEvent(new CustomEvent('power:change', { detail: st }));
    }
  }

  // Battery API (best-effort)
  if (navigator.getBattery) {
    navigator.getBattery().then(b => {
      window.__batteryLevel__ = b.level;
      window.__batteryCharging__ = b.charging;
      b.addEventListener('levelchange', () => { window.__batteryLevel__ = b.level; applyState(); });
      b.addEventListener('chargingchange', () => { window.__batteryCharging__ = b.charging; applyState(); });
      applyState();
    }).catch(applyState);
  }

  // Listeners
  document.addEventListener('visibilitychange', applyState, { passive: true });
  mqlPRM?.addEventListener?.('change', applyState, { passive: true });
  net?.addEventListener?.('change', applyState, { passive: true });
  window.addEventListener('pageshow', applyState, { passive: true });
  window.addEventListener('pagehide', applyState, { passive: true });

  // API pubblica
  window.POWER = {
    isLow: () => (last.low || last.hidden),
    isHidden: () => last.hidden,
    setOverride: (mode = 'auto') => { override = mode; applyState(); },
    _apply: applyState
  };

  applyState();
})();