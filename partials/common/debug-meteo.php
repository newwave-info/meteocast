<style>
.weather-debug-panel-min {
  position: fixed;
  bottom: 16px;
  right: 16px;
  background: rgba(18,24,34,0.97);
  border-radius: 12px;
  padding: 14px 18px 10px 18px;
  z-index: 99999;
  box-shadow: 0 6px 24px rgba(16,28,48,0.16);
  min-width: 185px;
  font-size: 1em;
  pointer-events: auto;
  user-select: auto;
  color: #f0f2fa;
  border: 1.5px solid rgba(60,80,120,0.11);
  transition: box-shadow 0.19s, background 0.22s;
}
.weather-debug-panel-min select,
.weather-debug-panel-min button {
  margin-bottom: 0;
  font-size: 0.98em;
  border-radius: 8px;
}

.weather-debug-panel-min label {
  font-weight: 500;
  font-size: 0.97em;
  cursor: pointer;
  color: #c4d2ea;
  margin-right: 10px;
  user-select: none;
}

.weather-debug-panel-min .btn {
  margin-top: 10px;
  background: linear-gradient(90deg, #2678d4 0%, #41a6ef 100%);
  border: none;
  color: #fff;
  font-weight: 500;
  box-shadow: 0 2px 10px rgba(44,132,210,0.09);
  transition: background 0.13s;
}
.weather-debug-panel-min .btn:hover {
  background: linear-gradient(90deg, #175d9e 0%, #3194cb 100%);
}

.weather-debug-panel-min .status-row {
  font-size: 0.87em;
  color: #a0bad2;
  margin-top: 9px;
}

.weather-debug-panel-min select {
  background: #273149;
  color: #ddeaff;
  border: 1px solid #253147;
}

.weather-debug-panel-min input[type="radio"],
.weather-debug-panel-min input[type="checkbox"] {
  accent-color: #4ec7fa;
  margin-right: 3px;
}

.weather-debug-panel-min .fps-high { color: #38e688; }
.weather-debug-panel-min .fps-med  { color: #ffc700; }
.weather-debug-panel-min .fps-low  { color: #ff7676; }
.weather-debug-panel-min .debug-row {
  margin-top: 6px;
  margin-bottom: 2px;
}
</style>

<div id="weatherDebugPanel" class="weather-debug-panel-min">
  <select id="weatherCodeSelect" class="form-select form-select-sm"></select>
  <div class="debug-row">
    <label><input type="radio" name="night" value="0" checked> Giorno</label>
    <label><input type="radio" name="night" value="1"> Notte</label>
  </div>
  <div class="debug-row">
    <label><input type="checkbox" id="chkSunset"> Sunset</label>
    <label><input type="checkbox" id="chkSunrise"> Sunrise</label>
  </div>
  <div class="debug-row">
    <label><input type="checkbox" id="chkBattery"> Battery Saver</label>
  </div>
  <button id="weatherDebugApply" class="btn btn-primary btn-sm w-100">Applica</button>
  <div class="status-row">
    Meteo: <span id="weatherDebugNow">0</span><br>
    FPS: <span id="weatherDebugFps">--</span>
  </div>
</div>

<script>
(function() {
  const panel = document.getElementById('weatherDebugPanel');
  const select = document.getElementById('weatherCodeSelect');
  const radioDay = panel.querySelector('input[name="night"][value="0"]');
  const radioNight = panel.querySelector('input[name="night"][value="1"]');
  const btn = document.getElementById('weatherDebugApply');
  const nowCode = document.getElementById('weatherDebugNow');
  const fps = document.getElementById('weatherDebugFps');
  const chkSunset = document.getElementById('chkSunset');
  const chkSunrise = document.getElementById('chkSunrise');
  const chkBattery = document.getElementById('chkBattery');

  const WEATHER_DESCRIPTIONS = {
  0: "Sereno", 1: "Prevalentemente sereno", 2: "Parzialmente nuvoloso", 3: "Coperto",
  45: "Nebbia", 48: "Nebbia con brina", 51: "Pioggerella leggera", 53: "Pioggerella moderata",
  55: "Pioggerella intensa", 56: "Pioggerella gelata leggera", 57: "Pioggerella gelata intensa",
  61: "Pioggia leggera", 63: "Pioggia moderata", 65: "Pioggia intensa",
  66: "Pioggia gelata leggera", 67: "Pioggia gelata intensa", 71: "Neve leggera",
  73: "Neve moderata", 75: "Neve intensa", 77: "Nevischio", 80: "Rovesci leggeri",
  81: "Rovesci moderati", 82: "Rovesci forti", 85: "Rovesci di neve leggeri",
  86: "Rovesci di neve forti", 95: "Temporale", 96: "Temporale con grandine",
  99: "Temporale violento con grandine"
};

// Popola dinamicamente le opzioni del menu meteo
Object.entries(WEATHER_DESCRIPTIONS).sort(([a], [b]) => a - b).forEach(([code, label]) => {
  const opt = document.createElement("option");
  opt.value = code;
  opt.textContent = `${code} - ${label}`;
  select.appendChild(opt);
});

  // ---- PATCH: sync iniziale con il METEO reale ----
  let firstInit = true;
  function syncPanelWithMeteoCast() {
    if (window.METEOCAST) {
      select.value = window.METEOCAST.code || 0;
      (window.METEOCAST.isNight ? radioNight : radioDay).checked = true;
      chkSunset.checked = !!window.METEOCAST.isSunset;
      chkSunrise.checked = !!window.METEOCAST.isSunrise;
    }
  }

  function applyWeather() {
    const code = parseInt(select.value,10);
    const night = radioNight.checked;
    // Flag sunset/sunrise
    window.isSunset = !!chkSunset.checked;
    window.isSunrise = !!chkSunrise.checked;
    if(window.isSunset && window.isSunrise) {
      window.isSunrise = false;
      chkSunrise.checked = false;
    }
    // Battery saver
    if (chkBattery.checked) {
      document.body.classList.add('battery-saver');
      if(window.weatherFX) window.weatherFX.running = false;
    } else {
      document.body.classList.remove('battery-saver');
      if(window.weatherFX) {
        window.weatherFX.running = true;
        requestAnimationFrame(window.weatherFX.loop);
      }
    }
    // Applica effetti e sfondo
    if(window.updateWeatherBackground) {
      window.updateWeatherBackground(code, night, window.isSunset, window.isSunrise);
    }
    nowCode.textContent = code + (night ? ' (notte)' : ' (giorno)');
  }

  // Inizializza il debug panel all'avvio, solo una volta
  document.addEventListener('DOMContentLoaded', function() {
    if(firstInit) {
      syncPanelWithMeteoCast();
      firstInit = false;
    }
    applyWeather();
  });

  // Alt+W mostra/nasconde il pannello e risync se riaperto
  document.addEventListener('keydown', e=>{
    if(e.altKey && e.key.toLowerCase()==='w'){
      panel.style.display = (panel.style.display==='none'?'':'none');
      if(panel.style.display !== 'none') syncPanelWithMeteoCast();
    }
  });

  // Eventi UI
  btn.onclick = applyWeather;
  select.onchange = applyWeather;
  radioDay.onchange = applyWeather;
  radioNight.onchange = applyWeather;
  chkSunset.onchange = applyWeather;
  chkSunrise.onchange = applyWeather;
  chkBattery.onchange = applyWeather;

  // FPS live colorato
  setInterval(()=>{
    if(window.weatherFX && typeof window.weatherFX.fps!=='undefined') {
      const val = window.weatherFX.fps;
      fps.textContent = val;
      fps.className = val>=48 ? "fps-high" : val>=28 ? "fps-med" : "fps-low";
    }
  }, 650);

})();
</script>
