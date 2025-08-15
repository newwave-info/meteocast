/* ============================================================================
 * power-debug.js — Pannello di debug Power/Performance
 * Requisiti: power-manager.js (espone window.POWER)
 * - Toggle override: Auto / Low / Normal
 * - Pausa/riprendi canvas meteo
 * - Blur ON/OFF (forza senza cambiare la logica di POWER)
 * - Stato live: visibilità, reduced motion, saveData, memoria, batteria
 * - FPS meter leggero (attivo solo a pannello aperto)
 * ============================================================================ */
(function () {
  if (window.__PowerDebug) return; // singleton
  window.__PowerDebug = true;

  const html = document.documentElement;
  const body = document.body;

  // ---- style injection ------------------------------------------------------
  const CSS = `
  .power-debug-toggle{
    position:fixed; right:14px; bottom:14px; z-index:99998;
    width:44px; height:44px; border-radius:50%;
    background:rgba(18,24,34,.9); color:#e8f2ff; border:1px solid rgba(80,110,150,.25);
    display:flex; align-items:center; justify-content:center; cursor:pointer;
    box-shadow:0 8px 24px rgba(0,0,0,.25); backdrop-filter: blur(6px);
  }
  .power-debug-toggle:hover{ background:rgba(18,24,34,.96); }
  .power-debug-panel{
    position:fixed; right:14px; bottom:68px; z-index:99999; width:300px;
    background:rgba(18,24,34,.97); color:#e8f2ff; border-radius:14px;
    border:1px solid rgba(80,110,150,.25); box-shadow:0 8px 28px rgba(0,0,0,.35);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
    overflow:hidden; display:none;
  }
  .power-debug-panel.open{ display:block; }
  .pdbg-hd{ display:flex; align-items:center; justify-content:space-between; padding:10px 12px; background:rgba(255,255,255,.03); border-bottom:1px solid rgba(255,255,255,.06); }
  .pdbg-hd h6{ margin:0; font-size:13px; letter-spacing:.3px; font-weight:600; }
  .pdbg-bd{ padding:10px 12px 12px; font-size:12.5px; }
  .pdbg-row{ display:flex; gap:8px; margin:6px 0; align-items:center; flex-wrap:wrap; }
  .pdbg-badge{ padding:2px 6px; border-radius:6px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); font-variant-numeric: tabular-nums; }
  .pdbg-radio{ display:flex; gap:6px; }
  .pdbg-radio label{ display:inline-flex; gap:6px; align-items:center; cursor:pointer; padding:4px 6px; border-radius:8px; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); }
  .pdbg-radio input{ margin:0; }
  .pdbg-sw{ display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
  .pdbg-sw input{ margin:0; }
  .pdbg-kv{ display:grid; grid-template-columns: 1fr auto; gap:6px 10px; }
  .pdbg-kv div{ padding:2px 0; }
  .pdbg-muted{ color:#a9b9d0; }
  .pdbg-fps{ font-weight:600; }
  `;
  const style = document.createElement('style');
  style.id = 'powerDebugCss';
  style.textContent = CSS;
  document.head.appendChild(style);

  // ---- DOM ------------------------------------------------------------------
  const btn = document.createElement('button');
  btn.className = 'power-debug-toggle';
  btn.type = 'button';
  btn.title = 'Power Debug';
  btn.innerHTML = '⛁'; // icona semplice

  const panel = document.createElement('div');
  panel.className = 'power-debug-panel';
  panel.innerHTML = `
    <div class="pdbg-hd">
      <h6>⚡ Power Debug</h6>
      <button type="button" id="pdbgClose" style="background:transparent;border:0;color:#e8f2ff;font-size:18px;line-height:1">×</button>
    </div>
    <div class="pdbg-bd">
      <div class="pdbg-row">
        <strong>Override:</strong>
        <div class="pdbg-radio" id="pdbgOverride">
          <label><input type="radio" name="pdbgOv" value="auto" checked>Auto</label>
          <label><input type="radio" name="pdbgOv" value="low">Low</label>
          <label><input type="radio" name="pdbgOv" value="normal">Normal</label>
        </div>
      </div>

      <div class="pdbg-row">
        <label class="pdbg-sw"><input type="checkbox" id="pdbgPauseCanvas"> Pausa canvas meteo</label>
        <label class="pdbg-sw"><input type="checkbox" id="pdbgNoBlur"> Disattiva blur UI</label>
      </div>

      <div class="pdbg-row pdbg-kv">
        <div class="pdbg-muted">Visibilità</div><div id="pdbgVis" class="pdbg-badge">?</div>
        <div class="pdbg-muted">Power</div><div id="pdbgPower" class="pdbg-badge">?</div>
        <div class="pdbg-muted">Reduced motion</div><div id="pdbgRM" class="pdbg-badge">?</div>
        <div class="pdbg-muted">Data Saver</div><div id="pdbgDS" class="pdbg-badge">?</div>
        <div class="pdbg-muted">Memoria</div><div id="pdbgMem" class="pdbg-badge">?</div>
        <div class="pdbg-muted">Batteria</div><div id="pdbgBat" class="pdbg-badge">?</div>
        <div class="pdbg-muted">FPS</div><div id="pdbgFps" class="pdbg-badge pdbg-fps">--</div>
      </div>
    </div>
  `;

  document.body.appendChild(btn);
  document.body.appendChild(panel);

  // ---- Stato & util ---------------------------------------------------------
  const mqlPRM = window.matchMedia?.('(prefers-reduced-motion: reduce)');
  const net = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;

  function getOverride() {
    return localStorage.getItem('powerOverride') || 'auto';
  }
  function setOverride(mode) {
    localStorage.setItem('powerOverride', mode);
    window.POWER?.setOverride(mode);
    refresh();
  }

  // Blur manuale (senza intaccare POWER): toggla una classe che forza le var
  function applyNoBlur(flag) {
    body.classList.toggle('debug-no-blur', !!flag);
    if (flag) {
      html.style.setProperty('--ui-blur', '0px');
      html.style.setProperty('--card-blur', '0px');
    } else {
      html.style.removeProperty('--ui-blur');
      html.style.removeProperty('--card-blur');
    }
  }

  // Pausa canvas meteo
  function setCanvasPaused(paused) {
    const fx = window.weatherFX;
    if (!fx) return;
    if (paused) {
      fx.running = false;
    } else {
      if (!fx.running) {
        fx.running = true;
        requestAnimationFrame(fx.loop);
      }
    }
  }

  // ---- Listeners UI ---------------------------------------------------------
  btn.addEventListener('click', () => {
    const open = !panel.classList.contains('open');
    panel.classList.toggle('open', open);
    btn.style.transform = open ? 'scale(0.92)' : 'scale(1)';
    fpsToggle(open);
    refresh();
  });
  panel.querySelector('#pdbgClose').addEventListener('click', () => {
    panel.classList.remove('open'); btn.style.transform = 'scale(1)'; fpsToggle(false);
  });

  panel.querySelectorAll('input[name="pdbgOv"]').forEach(r => {
    r.addEventListener('change', e => setOverride(e.target.value));
  });

  const elPause = panel.querySelector('#pdbgPauseCanvas');
  const elNoBlur = panel.querySelector('#pdbgNoBlur');
  elPause.addEventListener('change', () => setCanvasPaused(elPause.checked));
  elNoBlur.addEventListener('change', () => applyNoBlur(elNoBlur.checked));

  // ---- Agganci al sistema ---------------------------------------------------
  document.addEventListener('power:change', refresh);
  document.addEventListener('visibilitychange', refresh);
  mqlPRM?.addEventListener?.('change', refresh);
  net?.addEventListener?.('change', refresh);

  if (navigator.getBattery) {
    navigator.getBattery().then(b => {
      b.addEventListener('levelchange', refresh);
      b.addEventListener('chargingchange', refresh);
      refresh();
    }).catch(refresh);
  }

  // ---- FPS meter ------------------------------------------------------------
  let fpsRAF = null, fpsLast = 0, fpsSamples = 0, fpsAccum = 0;
  const elFps = panel.querySelector('#pdbgFps');
  function fpsLoop(ts) {
    if (!fpsLast) { fpsLast = ts; }
    const dt = ts - fpsLast;
    fpsLast = ts;
    const fps = dt > 0 ? 1000 / dt : 0;
    fpsAccum += fps; fpsSamples++;
    if (fpsSamples >= 10) { // aggiorna ~ogni 10 frame
      const avg = Math.round(fpsAccum / fpsSamples);
      elFps.textContent = isFinite(avg) ? String(avg) : '--';
      fpsSamples = 0; fpsAccum = 0;
    }
    fpsRAF = requestAnimationFrame(fpsLoop);
  }
  function fpsToggle(on) {
    if (on) {
      if (!fpsRAF) { fpsLast = 0; fpsSamples = 0; fpsAccum = 0; fpsRAF = requestAnimationFrame(fpsLoop); }
    } else {
      if (fpsRAF) { cancelAnimationFrame(fpsRAF); fpsRAF = null; elFps.textContent = '--'; }
    }
  }

  // ---- Refresh UI -----------------------------------------------------------
  function refresh() {
    // override
    const ov = getOverride();
    panel.querySelectorAll('input[name="pdbgOv"]').forEach(r => r.checked = (r.value === ov));

    // stato
    const low = window.POWER?.isLow?.() || false;
    const hidden = document.visibilityState === 'hidden';
    const prm = !!mqlPRM?.matches;
    const ds = !!net?.saveData;
    const mem = navigator.deviceMemory ? `${navigator.deviceMemory} GB` : 'n/d';

    const elVis = panel.querySelector('#pdbgVis');
    const elPow = panel.querySelector('#pdbgPower');
    const elRM  = panel.querySelector('#pdbgRM');
    const elDS  = panel.querySelector('#pdbgDS');
    const elMem = panel.querySelector('#pdbgMem');
    const elBat = panel.querySelector('#pdbgBat');

    elVis.textContent = hidden ? 'hidden' : 'visible';
    elPow.textContent = low ? 'LOW' : 'NORMAL';
    elRM.textContent  = prm ? 'reduce' : 'no-preference';
    elDS.textContent  = ds ? 'on' : 'off';
    elMem.textContent = mem;

    if (navigator.getBattery && window.__batteryLevel__ != null) {
      const pct = Math.round((window.__batteryLevel__ || 0) * 100);
      const chg = window.__batteryCharging__ ? '↯' : '';
      elBat.textContent = `${pct}% ${chg}`;
    } else {
      elBat.textContent = 'n/d';
    }

    // sincronia toggle blur & canvas
    elNoBlur.checked = body.classList.contains('debug-no-blur');
    elPause.checked = !!window.weatherFX && !window.weatherFX.running;
  }

  // ---- Persistenza override al boot ----------------------------------------
  const bootOv = getOverride();
  if (bootOv && window.POWER?.setOverride) {
    window.POWER.setOverride(bootOv);
  }

  // mostra il bottone sempre; pannello apribile on-demand
})();