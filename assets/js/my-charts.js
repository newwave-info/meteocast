/* ============================================================================
 *  app.js – Gestione grafici meteo (Chart.js) con pan fluido e ottimizzato
 *  LagoonWeather · v.2025-07-31 - Pan Migliorato
 * ============================================================================
 */

// Registrazione plugin zoom con controllo
if (window.ChartZoom) {
  Chart.register(window.ChartZoom);
}

luxon.Settings.defaultLocale = 'it';
const tz = window.appTimezone || 'Europe/Rome';
luxon.Settings.defaultZone = tz;

let chartWind, chartRain, chartTemp, chartTide;

/* ========================= UTILITY ========================= */
const isObj = v => v && typeof v === 'object';

function getNowMillis() {
    return luxon.DateTime.now().setZone(tz).toMillis();
}

/* =========================
 *  WIND · UTIL (IT) – Coerente con helpers.php
 *  - Abbreviazioni in italiano (O = Ovest, SO, SSO, OSO, ONO, NO, NNO)
 *  - Rotazione freccia come getWindDirectionRotation(deg) => (deg + 180) % 360
 * ========================= */
const Wind = {
  norm(d) {
    if (d == null || isNaN(d)) return null;
    let x = Number(d) % 360;
    if (x < 0) x += 360;
    return Math.round(x);
},
  // 16 settori in IT, ordine coerente con helpers.php:getWindDirection()
toCompass16IT(deg) {
    const dirs = [
      'N','NNE','NE','ENE','E','ESE','SE','SSE',
      'S','SSO','SO','OSO','O','ONO','NO','NNO'
  ];
  const i = Math.round((deg % 360) / 22.5) % 16;
  return dirs[i];
},
  // Stessa formula del PHP (helpers.php)
rotationLikePHP(deg) { return (deg + 180) % 360; },

  // Prova più chiavi comuni: adegua se usi un nome diverso
degArray() {
    return (
      weatherData.wind_dir ??
      weatherData.wind_direction ??
      weatherData.wind_deg ??
      window.WIND_DIR ?? // eventuale globale
      null
      );
},
at(index) {
    const arr = this.degArray();
    if (!Array.isArray(arr)) return null;
    const deg = this.norm(arr[index]);
    if (deg == null) return null;
    return { deg, abbr: this.toCompass16IT(deg), rot: this.rotationLikePHP(deg) };
}
};

const getChartParams = cv => ({
    range: cv.dataset.range || 'custom',
    days: +(cv.dataset.days || 3),
    zoom: +(cv.dataset.zoom || 1),
    lock: cv.dataset.lock === 'true'
});

function getSnappedStart(buf = 2, fromTomorrow = false) {
    if (fromTomorrow) {
        const now = luxon.DateTime.now().setZone(tz);
        const tomorrow = now.plus({ days: 1 }).startOf('day');
        return tomorrow.toMillis();
    } else {
        const now = luxon.DateTime.now().setZone(tz);
        const raw = now.minus({ hours: buf }).toMillis();
        const list = weatherData.timestamps.map(ts => luxon.DateTime.fromISO(ts, { zone: tz }).toMillis());
        return list.find(t => t >= raw) ?? raw;
    }
}

function getXAxisRangeFromParams(days = 3, zoom = 1, buf = 2, forceToday = false, fromTomorrow = false) {
    if (forceToday) return getXAxisRangeTodayCustom();
    const start = getSnappedStart(buf, fromTomorrow);
    const full = days * 864e5;
    return {
        min: start,
        max: start + full * Math.max(zoom, .05),
        fullMin: start,
        fullMax: start + full
    };
}

function getXAxisRangeTodayCustom() {
    const hoursHistory = window.chartSettings?.hoursHistory ?? 4;
    const hoursForecast = window.chartSettings?.hoursForecast ?? 20;
    const now = luxon.DateTime.now().setZone(tz);
    const min = now.minus({ hours: hoursHistory }).toMillis();
    const max = now.plus({ hours: hoursForecast }).toMillis();
    return { min, max, fullMin: min, fullMax: max };
}

/* ---------- GRADIENT SAFE ---------- */
function safeGrad(ctx, rgbaTop) {
    const { chart } = ctx;
    const { ctx: cv, chartArea } = chart;
    if (!isObj(chartArea) || !chartArea.width) return 'rgba(0,0,0,0)';
    const g = cv.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    g.addColorStop(0, rgbaTop);
    g.addColorStop(1, rgbaTop.replace(/0\.[0-9]+/, '0'));
    return g;
}

/* ===================== BASE OPTIONS OTTIMIZZATE ======================= */
function sharedOpts({
    min,
    max,
    fullMin,
    fullMax,
    lock = false,
    chartType = 'default',
    isToday = false
}) {
    return {
        locale: 'it',
        responsive: true,
        maintainAspectRatio: false,
        // Animazioni ottimizzate per il pan
        animation: { 
            duration: isToday ? 600 : 800, 
            easing: 'easeOutCubic',
            // Disabilita animazioni durante pan/zoom
            onProgress: function(animation) {
                if (this.$zoom && (this.$zoom.panning || this.$zoom.zooming)) {
                    animation.currentStep = animation.numSteps;
                }
            }
        },
        interaction: { 
            mode: 'index', 
            intersect: false,
            // Riduce il delay per interazioni più responsive
            axis: 'x'
        },
        _isToday: isToday,
        scales: {
            x: {
                type: 'time',
                min,
                max,
                offset: false,
                adapters: {
                    date: { zone: tz, locale: 'it' }
                },
                time: { 
                    unit: 'hour', 
                    tooltipFormat: 'EEEE d MMMM yyyy, HH:mm',
                    // Ottimizzazione per rendering più fluido
                    displayFormats: {
                        hour: 'HH',
                        day: 'ccc d'
                    }
                },
                ticks: {
                    color: 'rgba(255,255,255,.5)',
                    font: { size: 10 },
                    maxTicksLimit: 12,
                    callback: function(v) {
                        const dt = luxon.DateTime.fromMillis(v, { zone: tz });
                        return dt.toFormat('HH'); // Sempre solo orario a 2 cifre
                    }
                },
                grid: {
                    color: 'rgba(255,255,255,.05)',
                    borderColor: 'rgba(255,255,255,.25)',
                    borderWidth: 1
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
                align: 'end',
                labels: {
                    color: 'rgba(255,255,255,.8)',
                    font: { size: 10 },
                    boxWidth: 20,
                    boxHeight: 8,
                    padding: 4
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(20,20,20,.95)',
                borderColor: 'rgba(255,255,255,.15)',
                borderWidth: 1,
                titleFont: { size: 11, weight: '700', family: 'Inter, sans-serif' },
                bodyFont: { size: 10, family: 'Inter, sans-serif' },
                titleColor: '#fff',
                bodyColor: '#e0f7ff',
                padding: 8,
                caretPadding: 15,
                caretSize: 5,
                cornerRadius: 2,
                usePointStyle: true,
                displayColors: true,
                // Usa il chart dal contesto (ctx.chart) per evitare problemi con arrow function
                filter: (ctx) => !(ctx.chart.$zoom && (ctx.chart.$zoom.panning || ctx.chart.$zoom.zooming)),
                callbacks: {
                    title: (c) => {
                        const dt = luxon.DateTime.fromMillis(c[0].parsed.x, { zone: tz });
                        return dt.toFormat('ccc d MMM - HH:mm');
                    },
                    label: (c) => {
                        const chartType = c.chart?.options?.chartType;
                        const i = c.dataIndex;
                        if (chartType === 'rain') {
                            return [
                                `Precipitazioni: ${weatherData.precip?.[i] ?? 0} mm`,
                                `Probabilità: ${weatherData.precip_prob?.[i] ?? 0}%`
                            ];
                        }
                        return `${c.dataset.label}: ${c.parsed.y}`;
                    },
                    labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
                    labelColor: (c) => ({
                        borderColor: c.dataset.borderColor || '#fff',
                        backgroundColor: c.dataset.borderColor || '#fff'
                    })
                }
            },
            // CONFIGURAZIONE PAN CORRETTA
            zoom: {
                pan: {
                    enabled: true,
                    mode: 'x',
                    modifierKey: null,
                    threshold: 2
                },
                zoom: {
                    wheel: { enabled: false },
                    pinch: { enabled: false },
                    drag:  { enabled: false },
                    mode: 'x'
                },
                limits: {
                    x: {
                        min: fullMin,
                        max: fullMax,
                        minRange: 3600000 // 1h minimo
                    }
                }
            }
        },
        // Ottimizzazioni per performance
        elements: {
            point: {
                radius: 0,
                hoverRadius: 3, // Ridotto per meno interferenza
                hitRadius: 15   // Ridotto per permettere meglio il pan
            },
            line: {
                spanGaps: true,
                tension: 0.3
            }
        },
        // Parsing ottimizzato
        parsing: {
            xAxisKey: 'x',
            yAxisKey: 'y'
        },
        chartType
    };
}

function baseOpts(p) {
    const rng = getXAxisRangeFromParams(p.days, p.zoom, 2, p.range === 'today');
    return sharedOpts({
        ...p,
        ...rng,
        isToday: p.range === 'today'
    });
}

/* =========================
 *  WIND ARROW ICON (Canvas) – punta in SU, ruotiamo nel tooltip
 * ========================= */
const WindArrowIcon = (() => {
  const cache = new Map();
  function make(size = 14, stroke = '#fff') {
    const key = size + '|' + stroke;
    if (cache.has(key)) return cache.get(key);
    const s = Math.max(12, size);
    const c = document.createElement('canvas');
    c.width = c.height = s;
    const ctx = c.getContext('2d');

    ctx.strokeStyle = stroke;
    ctx.lineWidth = Math.max(1.6, s * 0.12);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Disegno una freccia verticale morbida (punta in SU)
    const mid = s * 0.5, top = s * 0.22, bot = s * 0.78, head = s * 0.28;

    // Asta
    ctx.beginPath();
    ctx.moveTo(mid, bot);
    ctx.lineTo(mid, top + head * 0.25);
    ctx.stroke();

    // Punte
    ctx.beginPath();
    ctx.moveTo(mid, top);
    ctx.lineTo(mid - head * 0.7, top + head * 0.9);
    ctx.moveTo(mid, top);
    ctx.lineTo(mid + head * 0.7, top + head * 0.9);
    ctx.stroke();

    cache.set(key, c);
    return c;
  }
  return { make };
})();


function getRainTooltipLabel(context) {
    const label = context.dataset.label;
    if (label === "Precipitazioni (mm)") {
        const mm = context.parsed.y;
        return (mm != null && !isNaN(mm)) ? `Precipitazioni: ${mm} mm` : null;
    }
    if (label === "Probabilità (%)") {
        const prob = context.parsed.y;
        return (prob != null && !isNaN(prob)) ? `Probabilità: ${prob}%` : null;
    }
    return null;
}

function getTideTooltipLabel(context) {
    const type = context.raw?.type;
    if (type === "max") return `Alta marea: ${context.parsed.y} cm`;
    if (type === "min") return `Bassa marea: ${context.parsed.y} cm`;
    return `Marea: ${context.parsed.y} cm`;
}

function normalizeTideData(raw) {
  const arr = Array.isArray(raw?.data) ? raw.data : (Array.isArray(raw) ? raw : []);
  return arr.map(d => {
    const fmt = (typeof d.time === 'string' && d.time.length === 19)
    ? 'yyyy-MM-dd HH:mm:ss'
    : 'yyyy-MM-dd HH:mm';
    return {
      x: luxon.DateTime.fromFormat(d.time, fmt, { zone: tz }).toMillis(),
      y: Number(d.val),
      type: d.type || null
  };
}).filter(p => Number.isFinite(p.x) && Number.isFinite(p.y));
}

const TIDE_RIGHT_PAD_MS = 15 * 60 * 1000; // 15 minuti a destra
const TIDE_LEFT_PAD_MS  = 15 * 60 * 1000; // 15 minuti a sinistra
const TIDE_SLACK_MS = 60 * 1000; // 1 min di tolleranza contro i micro-tagli

/* =============================================================
   BUILDERS OTTIMIZZATI
   ============================================================= */
function buildWindChart(cv) {
  const prm = getChartParams(cv);
  const isForecastView = prm.range === 'forecast';
  const rng = getXAxisRangeFromParams(prm.days, prm.zoom, 2, prm.range === 'today', isForecastView);

  // includo la direzione per ogni punto
  const data = weatherData.timestamps.map((ts, i) => ({
  x: luxon.DateTime.fromISO(ts, { zone: tz }).toMillis(),
  wind_speed: weatherData.wind_speed?.[i] ?? null,
  wind_gusts: weatherData.wind_gusts?.[i] ?? null,
  dir: Wind.at(i) // {deg, abbr, rot} | null
}));

  const yMax = Math.ceil(Math.max(...data.flatMap(d => [d.wind_speed, d.wind_gusts]).filter(v => v != null)) / 5) * 5 || 10;
  const opt = baseOpts({ ...prm, chartType: 'wind' });
  opt.scales.x.min = rng.min;
  opt.scales.x.max = rng.max;
  opt.scales.yWindLeft = {
    position: 'left',
    min: 0,
    max: yMax,
    title: { display: false },
    ticks: { color: '#e0f7ff', font: { size: 11 }, padding: 4, maxTicksLimit: 8 },
    grid: { color: 'rgba(255,255,255,.05)' }
};

  // Tooltip personalizzato (IT + freccia ruotata come in PHP)
opt.plugins.tooltip.callbacks = {
  title: function(c) {
    const dt = luxon.DateTime.fromMillis(c[0].parsed.x, { zone: tz });
    return dt.toFormat('ccc d MMM - HH:mm');
  },
  label: function(ctx) {
    const i = ctx.dataIndex;
    const d = ctx.chart.$windData?.[i] || data[i] || {};
    const out = [];
    if (d.wind_speed != null) out.push(`Vento: ${d.wind_speed} km/h`);
    if (d.wind_gusts != null) out.push(`Raffiche: ${d.wind_gusts} km/h`);
    if (d.dir) out.push(`Direzione: ${d.dir.abbr}`); // <-- niente gradi
    return out.length ? out : null;
  },
  // Freccia personalizzata, ruotata come in PHP
  labelPointStyle: function(ctx) {
    const i = ctx.dataIndex;
    const d = ctx.chart.$windData?.[i];
    const rot = d?.dir?.rot ?? 0; // gradi
    const col = ctx.dataset.borderColor || '#fff';
    return { pointStyle: WindArrowIcon.make(14, col), rotation: rot };
  },
  labelColor: c => ({
    borderColor: c.dataset.borderColor || '#fff',
    backgroundColor: c.dataset.borderColor || '#fff'
  })
};


const ch = new Chart(cv, {
    type: 'line',
    data: {
      datasets: [
        {
          label: 'Vento (km/h)',
          data: data.map(d => ({ x: d.x, y: d.wind_speed })),
          yAxisID: 'yWindLeft',
          borderColor: 'rgba(200,230,255,.9)',
          borderWidth: 2,
          tension: .3,
          fill: true,
          pointRadius: 0,
          backgroundColor: c => safeGrad(c, 'rgba(200,230,255,.4)'),
          pointHoverBackgroundColor: 'rgba(200,230,255,1)',
          pointHoverRadius: 5,
          pointHoverBorderColor: 'transparent'
      },
      {
          label: 'Raffiche (km/h)',
          data: data.map(d => ({ x: d.x, y: d.wind_gusts })),
          yAxisID: 'yWindLeft',
          borderColor: 'rgba(88,186,206,.9)',
          borderWidth: 1.2,
          tension: .3,
          fill: true,
          pointRadius: 0,
          borderDash: [4, 3],
          backgroundColor: c => safeGrad(c, 'rgba(88,186,206,.4)'),
          pointHoverBackgroundColor: 'rgba(88,186,206,1)',
          pointHoverRadius: 5,
          pointHoverBorderColor: 'transparent'
      }
  ]
},
options: opt,
plugins: [dayLinesPlugin, nightShadingPlugin, zeroLinePlugin]
});

  // ⬇️ memorizzo i meta per tooltip/rebuild
ch.$windData = data;
ch.fullMin = rng.fullMin;
ch.fullMax = rng.fullMax;
return ch;
}

function buildTempChart(cv) {
    const prm = getChartParams(cv);
    const isForecastView = prm.range === 'forecast';
    const rng = getXAxisRangeFromParams(prm.days, prm.zoom, 2, prm.range === 'today', isForecastView);

    const data = weatherData.timestamps.map((ts, i) => ({
        x: luxon.DateTime.fromISO(ts, { zone: tz }).toMillis(),
        temp: weatherData.temperature?.[i] ?? null,
        apparent: weatherData.apparent_temperature?.[i] ?? null
    }));

    const vals = data.flatMap(d => [d.temp, d.apparent]).filter(v => v != null);
    const yMin = Math.floor(Math.min(...vals) - 1);
    const yMax = Math.ceil(Math.max(...vals) + 1);
    const opt = baseOpts({ ...prm, chartType: 'temp' });
    opt.scales.x.min = rng.min;
    opt.scales.x.max = rng.max;
    opt.scales.yTemp = {
        position: 'left',
        min: yMin,
        max: yMax,
        title: { display: false },
        ticks: { 
            color: '#ffe4b3', 
            font: { size: 11 }, 
            padding: 4,
            maxTicksLimit: 8
        },
        grid: { color: 'rgba(255,255,255,.05)', borderColor: 'rgba(255,255,255,.25)', borderWidth: 1 }
    };

    const ch = new Chart(cv, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Temperatura (°C)',
                    data: data.map(d => ({ x: d.x, y: d.temp })),
                    yAxisID: 'yTemp',
                    borderColor: 'rgba(255,190,120,.9)',
                    borderWidth: 2,
                    tension: .3,
                    fill: true,
                    pointRadius: 0,
                    backgroundColor: c => safeGrad(c, 'rgba(255,190,120,.4)'),
                    pointHoverBackgroundColor: 'rgba(255,190,120,1)',
                    pointHoverRadius: 5,
                    pointHoverBorderColor: 'transparent'
                },
                {
                    label: 'Percepita (°C)',
                    data: data.map(d => ({ x: d.x, y: d.apparent })),
                    yAxisID: 'yTemp',
                    borderColor: 'rgba(255,120,140,.9)',
                    borderWidth: 1.2,
                    tension: .3,
                    fill: true,
                    pointRadius: 0,
                    borderDash: [4, 3],
                    backgroundColor: c => safeGrad(c, 'rgba(255,120,140,.4)'),
                    pointHoverBackgroundColor: 'rgba(255,120,140,1)',
                    pointHoverRadius: 5,
                    pointHoverBorderColor: 'transparent'
                }
            ]
        },
        options: opt,
        plugins: [dayLinesPlugin, nightShadingPlugin, zeroLinePlugin]
    });

    ch.fullMin = rng.fullMin;
    ch.fullMax = rng.fullMax;
    return ch;
}

function buildRainChart(cv) {
    const prm = getChartParams(cv);
    const isForecastView = prm.range === 'forecast';
    const rng = getXAxisRangeFromParams(prm.days, prm.zoom, 2, prm.range === 'today', isForecastView);

    const data = weatherData.timestamps.map((ts, i) => ({
        x: luxon.DateTime.fromISO(ts, { zone: tz }).toMillis(),
        mm: weatherData.precip?.[i] ?? null,
        prob: weatherData.precip_prob?.[i] ?? null
    }));

    const yMax = Math.max(5, Math.ceil(Math.max(...data.map(d => d.mm)) * 1.2));
    const opt = baseOpts({ ...prm, chartType: 'rain' });
    opt.scales.x.min = rng.min;
    opt.scales.x.max = rng.max;
    opt.scales.yRain = {
        position: 'left',
        min: 0,
        max: yMax,
        title: { display: false },
        ticks: { 
            color: '#a8d8f0', 
            font: { size: 11 }, 
            padding: 4, 
            stepSize: 1,
            maxTicksLimit: 6
        },
        grid: { color: 'rgba(255,255,255,.04)', borderColor: 'rgba(255,255,255,.25)', borderWidth: 1 }
    };
    opt.scales.yProb = {
        position: 'right',
        min: 0,
        max: 100,
        display: false,
        grid: { display: false },
        ticks: { display: false }
    };

    opt.plugins.tooltip.callbacks = {
        title: function(c) {
            const dt = luxon.DateTime.fromMillis(c[0].parsed.x, { zone: tz });
            return dt.toFormat('ccc d MMM - HH:mm');
        },
        label: getRainTooltipLabel,
        labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
        labelColor: c => ({
            borderColor: c.dataset.borderColor || '#fff',
            backgroundColor: c.dataset.borderColor || '#fff'
        })
    };

    const ch = new Chart(cv, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Precipitazioni (mm)',
                    data: data.map(d => ({ x: d.x, y: d.mm })),
                    yAxisID: 'yRain',
                    borderColor: 'rgba(80,170,230,.9)',
                    backgroundColor: ctx => {
                        const i = ctx.dataIndex;
                        const p = data[i]?.prob ?? 0;
                        return `rgba(80,170,230,${0.15 + (p/100)*0.7})`;
                    },
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.35,
                    pointHoverBackgroundColor: 'rgba(80,170,230,1)',
                    pointHoverRadius: 5,
                    pointHoverBorderColor: 'transparent'
                },
                {
                    label: 'Probabilità (%)',
                    data: data.map(d => ({ x: d.x, y: d.prob })),
                    yAxisID: 'yProb',
                    borderColor: 'rgba(32,170,255,.8)',
                    borderWidth: 1.5,
                    borderDash: [4, 3],
                    fill: false,
                    pointRadius: 0,
                    tension: 0.25,
                    pointHoverBackgroundColor: 'rgba(32,170,255,1)',
                    pointHoverRadius: 4,
                    pointHoverBorderColor: 'transparent'
                }
            ]
        },
        options: opt,
        plugins: [dayLinesPlugin, nightShadingPlugin, zeroLinePlugin]
    });

    ch.fullMin = rng.fullMin;
    ch.fullMax = rng.fullMax;
    return ch;
}

function buildTideChart(cv) {
    const prm = getChartParams(cv);

    // Normalizza sempre allo stesso modo
    const data = normalizeTideData(window.tideData).sort((a,b) => a.x - b.x);
    if (data.length === 0) {
        console.error('buildTideChart: Nessun dato valido per il grafico');
        return null;
    }

    // Range: per marea NON forzare fromTomorrow
    const rng = getXAxisRangeFromParams(prm.days, prm.zoom, 2, prm.range === 'today', false);

    // Mantieni un punto prima del min per continuità
    const prev = data.filter(d => d.x < rng.min).sort((a,b) => b.x - a.x)[0];
    if (prev) rng.min = Math.min(rng.min, prev.x);

    // fullMin/fullMax robusti
    if (!rng.fullMin || !rng.fullMax) {
        const xs = data.map(d => d.x);
        rng.fullMin = Math.min(...xs);
        rng.fullMax = Math.max(...xs);
    }

    // Padding ai bordi per evitare clipping durante il pan
    rng.min     = rng.min     - TIDE_LEFT_PAD_MS;
    rng.max     = rng.max     + TIDE_RIGHT_PAD_MS;
    rng.fullMin = rng.fullMin - TIDE_LEFT_PAD_MS;
    rng.fullMax = rng.fullMax + TIDE_RIGHT_PAD_MS;

    const opt = sharedOpts({
        ...prm,
        ...rng,
        lock: false,
        chartType: 'tide',
        isToday: prm.range === 'today'
    });

    // abilita un po’ di margine logico a sinistra/destra sui tick
    opt.scales.x.offset = true;
    opt.scales.x.bounds = 'data';

    // Calcola min/max y
    const yValues = data.map(d => d.y).filter(y => !isNaN(y));
    const yMin = yValues.length > 0 ? Math.min(...yValues) - 10 : -50;
    const yMax = yValues.length > 0 ? Math.max(...yValues) + 10 : 100;

    opt.scales.yTide = {
        position: 'left',
        min: yMin,
        max: yMax,
        title: { display: false },
        ticks: {
            color: '#bae0ff',
            font: { size: 11 },
            padding: 4,
            maxTicksLimit: 7
        },
        grid: { color: 'rgba(70,160,255,.08)' }
    };

    // PERSONALIZZAZIONE ASSE X: solo ora con 2 cifre
    opt.scales.x.ticks = {
        color: 'rgba(255,255,255,.5)',
        font: { size: 10 },
        maxTicksLimit: 12,
        callback: function(v) {
            const dt = luxon.DateTime.fromMillis(v, { zone: tz });
            return dt.toFormat('HH'); // Solo ora con 2 cifre
        }
    };

    // Configurazione zoom per pan (+ callback per estendere i limiti al bisogno)
    opt.plugins.zoom = {
        pan: {
            enabled: !prm.lock,
            mode: 'x',
            modifierKey: null,
            threshold: 5,
            onPan: ({ chart }) => {
              const x = chart.scales.x;
              const data = chart.data?.datasets?.[0]?.data || [];
              if (!data.length) return;

      // trova il punto immediatamente precedente al bordo sinistro attuale
              const idx = data.findIndex(p => p.x >= x.min);
              const prev = (idx > 0) ? data[idx - 1] : null;

      // se stiamo "tagliando" il prev, riporta il min un filo indietro
              if (prev && x.min <= (prev.x + TIDE_SLACK_MS)) {
                const newMin = prev.x - TIDE_LEFT_PAD_MS;
                if (newMin < chart.options.plugins.zoom.limits.x.min) {
                  chart.options.plugins.zoom.limits.x.min = newMin;
              }
              chart.options.scales.x.min = newMin;
              chart.update('none');
          }
      },
      onPanComplete: ({ chart }) => {
          const x = chart.scales.x;
          const data = chart.data?.datasets?.[0]?.data || [];
          if (!data.length) return;

      // estendi dinamicamente i limiti a dx come già facevi
          const dataMax = Math.max(...data.map(p => p.x));
          const paddedMax = dataMax + TIDE_RIGHT_PAD_MS;
          if (x.max < paddedMax) {
            chart.options.plugins.zoom.limits.x.max = paddedMax;
            if (x.max === chart.options.scales.x.max) {
              chart.options.scales.x.max = paddedMax;
          }
      }

      // e assicurati che il limite sinistro non “strizzi” il prev
      const dataMin = Math.min(...data.map(p => p.x));
      const paddedMin = dataMin - TIDE_LEFT_PAD_MS;
      if (x.min > paddedMin) {
        chart.options.plugins.zoom.limits.x.min = paddedMin;
        if (x.min === chart.options.scales.x.min) {
          chart.options.scales.x.min = paddedMin;
      }
  }
  chart.update('none');
}
},
zoom: { wheel:{enabled:false}, pinch:{enabled:false}, drag:{enabled:false}, mode:'x' },
limits: { x: { min: rng.fullMin, max: rng.fullMax, minRange: 3600000 } }
};

opt.plugins.tooltip.callbacks = {
    title: function(c) {
        const dt = luxon.DateTime.fromMillis(c[0].parsed.x, { zone: tz });
        return dt.toFormat('ccc d MMM - HH:mm');
    },
    label: getTideTooltipLabel,
    labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
    labelColor: c => ({
        borderColor: c.raw.type === "max" ? '#1bcaff' : '#0e5c8a',
        backgroundColor: c.raw.type === "max" ? '#bae0ff' : '#acecff'
    })
};

const ch = new Chart(cv, {
    type: 'line',
    data: {
        datasets: [
            {
                label: 'Marea prevista (cm)',
                data: data,
                yAxisID: 'yTide',
                borderColor: 'rgba(20,200,255,.85)',
                borderWidth: 2,
                tension: .3,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 6,
                    pointStyle: 'circle', // SEMPRE CERCHIO
                    pointBackgroundColor: 'rgba(20,200,255,.9)',
                    pointBorderColor: 'rgba(20,200,255,1)',
                    pointHoverBackgroundColor: 'rgba(20,200,255,1)',
                    pointHoverBorderColor: '#fff',
                    backgroundColor: c => safeGrad(c, 'rgba(20,200,255,.18)'),
                    clip: 10 // disegna 10px oltre chartArea: evita sparizioni a bordo
                }
            ]
        },
        options: opt,
        plugins: [dayLinesPlugin, nightShadingPlugin, zeroLinePlugin]
    });

ch.fullMin = rng.fullMin;
ch.fullMax = rng.fullMax;
return ch;
}

/* =============================================================
   RESTO DEL CODICE INVARIATO
   ============================================================= */

const getChartInstance = id => ({
    chartWind,
    chartRain,
    chartTemp,
    chartTide
}[id] || null);

function disableChartInteractionsIfToday(cv) {
    const { range } = getChartParams(cv);
    const w = cv.closest('.widget-lg');
    if (!w) return;
    w.querySelectorAll('button.chart-btn').forEach(btn => {
        const keep = /adesso|oggi/i.test(btn.title || '');
        btn.classList.toggle('d-none', range === 'today' && !keep);
    });
}

/* PLUGINS */
const dayLinesPlugin = {
    id: 'dayLinesPlugin',
    beforeDatasetsDraw(chart) {
        const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
        ctx.save();
        const now = luxon.DateTime.now().setZone(tz).toJSDate();
        const xNow = x.getPixelForValue(now);
        if (!isNaN(xNow)) {
            ctx.strokeStyle = 'rgba(255,255,0,.7)';
            ctx.lineWidth = 2;
            ctx.setLineDash([7, 3]);
            ctx.beginPath();
            ctx.moveTo(xNow, top);
            ctx.lineTo(xNow, bottom);
            ctx.stroke();
            ctx.setLineDash([]);
        }
        ctx.restore();
    },
    afterDraw(chart) {
        const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
        ctx.save();
        let lastDay = null;
        const ds = chart.data?.datasets?.[0]?.data || [];
        ds.forEach(point => {
            const date = new Date(point.x);
            const day = date.getDate();
            if (day !== lastDay) {
                const xPos = x.getPixelForValue(point.x);
                ctx.strokeStyle = 'rgba(255,255,255,.1)';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.moveTo(xPos, top);
                ctx.lineTo(xPos, bottom);
                ctx.stroke();
                ctx.fillStyle = 'rgba(255,255,255,.4)';
                ctx.font = 'bold 10px Inter';
                ctx.fillText(date.toLocaleDateString('it-IT', { weekday: 'short' }), xPos + 3, top + 12);
                lastDay = day;
            }
        });
        ctx.restore();
    }
};

const nightShadingPlugin = {
    id: 'nightShadingPlugin',
    beforeDatasetsDraw(chart) {
        const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
        if (!weatherData.sunrise || !weatherData.sunset) return;
        ctx.save();
        for (let i = 0; i < weatherData.sunrise.length - 1; i++) {
            const srNext = weatherData.sunrise[i + 1];
            const ss = weatherData.sunset[i];
            if (!srNext || !ss) continue;
            const nStart = luxon.DateTime.fromISO(ss, { zone: tz }).plus({ hours: 1 }).toJSDate();
            const nEnd = luxon.DateTime.fromISO(srNext, { zone: tz }).minus({ hours: 1 }).toJSDate();
            const xs = x.getPixelForValue(nStart);
            const xe = x.getPixelForValue(nEnd);
            const w = xe - xs;
            const fade = Math.min(50, w * .3);
            ctx.fillStyle = 'rgba(0,0,0,.35)';
            ctx.fillRect(xs, top, w, bottom - top);
            const grad1 = ctx.createLinearGradient(xs - fade, 0, xs, 0);
            grad1.addColorStop(0, 'rgba(255,179,71,0)');
            grad1.addColorStop(1, 'rgba(0,0,0,.35)');
            ctx.fillStyle = grad1;
            ctx.fillRect(xs - fade, top, fade, bottom - top);
            const grad2 = ctx.createLinearGradient(xe, 0, xe + fade, 0);
            grad2.addColorStop(0, 'rgba(0,0,0,.35)');
            grad2.addColorStop(1, 'rgba(255,111,145,0)');
            ctx.fillStyle = grad2;
            ctx.fillRect(xe, top, fade, bottom - top);
        }
        ctx.restore();
    }
};

const zeroLinePlugin = {
    id: 'zeroLinePlugin',
    afterDraw(chart) {
        const { ctx, chartArea: { left, right }, scales } = chart;
        Object.values(scales).filter(s => s.axis === 'y').forEach(s => {
            const y = s.getPixelForValue(0);
            if (!isFinite(y)) return;
            ctx.save();
            ctx.setLineDash([6, 4]);
            ctx.strokeStyle = 'rgba(255,255,255,.2)';
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(right, y);
            ctx.stroke();
            ctx.restore();
        });
    }
};

/* LAZY LOADER E INIT */
function initChartForCanvas(cv) {
    if (cv.dataset.chartReady) return;
    switch (cv.dataset.chart) {
    case 'wind': chartWind = buildWindChart(cv); break;
    case 'rain': chartRain = buildRainChart(cv); break;
    case 'temp': chartTemp = buildTempChart(cv); break;
    case 'tide': chartTide = buildTideChart(cv); break;
    }
    disableChartInteractionsIfToday(cv);
    cv.dataset.chartReady = '1';
}

function initLazyCharts() {
    document.querySelectorAll('canvas[data-chart]:not([data-chart-ready])').forEach(cv => {
        if (!cv.closest('.collapse')) initChartForCanvas(cv);
    });
    document.querySelectorAll('.collapse.show canvas[data-chart]').forEach(initChartForCanvas);
    document.querySelectorAll('.collapse').forEach(col => {
        col.addEventListener('shown.bs.collapse', () => {
            col.querySelectorAll('canvas[data-chart]').forEach(cv => {
                initChartForCanvas(cv);
                const ch = getChartInstance(cv.id);
                if (ch) ch.resize();
            });
        });
    });
}

/* FUNZIONI INTERAZIONE */
function rebuild(chart, id, range = 'custom') {
    if (id === 'chartWind') {
      const data = weatherData.timestamps.map((ts, i) => ({
  x: luxon.DateTime.fromISO(ts, { zone: tz }).toMillis(),
  wind_speed: weatherData.wind_speed?.[i] ?? null,
  wind_gusts: weatherData.wind_gusts?.[i] ?? null,
  dir: Wind.at(i)
}));
chart.data.datasets[0].data = data.map(d => ({ x: d.x, y: d.wind_speed }));
chart.data.datasets[1].data = data.map(d => ({ x: d.x, y: d.wind_gusts }));
chart.$windData = data;
} else if (id === 'chartTemp') {
    const data = weatherData.timestamps.map((ts, i) => ({
        x: luxon.DateTime.fromISO(ts, { zone: tz }).toMillis(),
        temp: weatherData.temperature?.[i] ?? null,
        apparent: weatherData.apparent_temperature?.[i] ?? null
    }));
    chart.data.datasets[0].data = data.map(d => ({ x: d.x, y: d.temp }));
    chart.data.datasets[1].data = data.map(d => ({ x: d.x, y: d.apparent }));

} else if (id === 'chartRain') {
    const data = weatherData.timestamps.map((ts, i) => ({
        x: luxon.DateTime.fromISO(ts, { zone: tz }).toMillis(),
        mm: weatherData.precip?.[i] ?? null,
        prob: weatherData.precip_prob?.[i] ?? null
    }));
    chart.data.datasets[0].data = data.map(d => ({ x: d.x, y: d.mm }));
    chart.data.datasets[1].data = data.map(d => ({ x: d.x, y: d.prob }));

} else if (id === 'chartTide') {
        // Dati marea normalizzati
    const data = normalizeTideData(window.tideData).sort((a,b) => a.x - b.x);
    chart.data.datasets[0].data = data;

    const cv  = document.getElementById(id);
    const prm = getChartParams(cv);

        // Range X base (no fromTomorrow)
    let { min, max, fullMin, fullMax } =
    getXAxisRangeFromParams(prm.days, prm.zoom, 2, (prm.range === 'today'), false);

        // Includi SEMPRE il punto precedente (se esiste)
    const prev = data.filter(d => d.x < min).sort((a,b) => b.x - a.x)[0];
    if (prev) min = Math.min(min, prev.x);

        // Padding sinistro/destro come in build
    min     = min     - TIDE_LEFT_PAD_MS;
    max     = max     + TIDE_RIGHT_PAD_MS;
    fullMin = (Number.isFinite(fullMin) ? fullMin : min) - TIDE_LEFT_PAD_MS;
    fullMax = (Number.isFinite(fullMax) ? fullMax : max) + TIDE_RIGHT_PAD_MS;

        // Applica alle scale
    chart.options.scales.x.min = min;
    chart.options.scales.x.max = max;
    chart.options.scales.x.offset = true;
    chart.options.scales.x.bounds = 'data';

        // Limiti di pan adeguati
    chart.options.plugins.zoom.limits.x.min = fullMin;
    chart.options.plugins.zoom.limits.x.max = fullMax;

        // Aggiorna anche i bounds Y
    const yVals = data.map(d => d.y).filter(Number.isFinite);
    if (yVals.length) {
        chart.options.scales.yTide.min = Math.min(...yVals) - 10;
        chart.options.scales.yTide.max = Math.max(...yVals) + 10;
    }
}
}

function resetZoom(id) {
    const ch = getChartInstance(id);
    if (!ch) return;
    const cv = document.getElementById(id);
    const prm = getChartParams(cv);
    let rangeObj;
    if (prm.range === 'today') {
        rangeObj = getXAxisRangeTodayCustom();
    } else {
        rangeObj = getXAxisRangeFromParams(prm.days, prm.zoom);
    }
    const { min, max, fullMin, fullMax } = rangeObj;
    ch.options.scales.x.min = min;
    ch.options.scales.x.max = max;
    ch.options.plugins.zoom.limits.x.min = fullMin;
    ch.options.plugins.zoom.limits.x.max = fullMax;
    rebuild(ch, id, prm.range);
    ch.update('none'); // Update senza animazione per reset più rapido
}

function resetToToday(id) {
    const ch = getChartInstance(id);
    if (!ch) return;
    const { min, max, fullMin, fullMax } = getXAxisRangeTodayCustom();
    ch.options.scales.x.min = min;
    ch.options.scales.x.max = max;
    ch.options.plugins.zoom.limits.x.min = fullMin;
    ch.options.plugins.zoom.limits.x.max = fullMax;
    rebuild(ch, id, 'today');
    ch.update('none'); // Update senza animazione
}

window.resetZoom = resetZoom;

/* INIT OTTIMIZZATO */
function initWeatherApp() {
    // Performance observer per monitorare la fluidità
    if ('PerformanceObserver' in window) {
        try {
            const observer = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                entries.forEach(entry => {
                    if (entry.entryType === 'measure' && entry.name.includes('chart')) {
                        if (entry.duration > 16.67) { // Oltre 60fps
                            console.warn(`Chart performance: ${entry.name} took ${entry.duration.toFixed(2)}ms`);
                        }
                    }
                });
            });
            observer.observe({ entryTypes: ['measure'] });
        } catch (e) {
            console.log('Performance monitoring not available');
        }
    }
}

// Gestione cursore personalizzata per desktop pan
function setupDesktopPanCursor() {
    document.querySelectorAll('canvas[data-chart]').forEach(cv => {
        if (cv.dataset.cursorSetup) return;
        cv.dataset.cursorSetup = 'true';
        
        let isMouseDown = false;
        let isPanning = false;
        
        // Mouse down - inizio potenziale pan
        cv.addEventListener('mousedown', function(e) {
            isMouseDown = true;
            isPanning = false;
            cv.style.cursor = 'grabbing';
        });
        
        // Mouse move - rileva se stiamo effettivamente pannando
        cv.addEventListener('mousemove', function(e) {
            if (isMouseDown) {
                isPanning = true;
                cv.style.cursor = 'grabbing';
            } else if (!isPanning) {
                cv.style.cursor = 'grab';
            }
        });
        
        // Mouse up - fine pan
        cv.addEventListener('mouseup', function(e) {
            isMouseDown = false;
            isPanning = false;
            cv.style.cursor = 'grab';
        });
        
        // Mouse leave - reset stato
        cv.addEventListener('mouseleave', function(e) {
            isMouseDown = false;
            isPanning = false;
            cv.style.cursor = 'grab';
        });
        
        // Imposta cursore iniziale
        cv.style.cursor = 'grab';
    });
}

// Event listeners ottimizzati per dispositivi touch E DESKTOP
function addOptimizedEventListeners() {
    document.querySelectorAll('canvas[data-chart]').forEach(cv => {
        if (cv.dataset.eventsAdded) return;
        cv.dataset.eventsAdded = 'true';
        
        let lastTap = 0;
        let touchStartTime = 0;
        let lastClick = 0;
        
        // Desktop: doppio click per reset
        cv.addEventListener('dblclick', function(e) {
            e.preventDefault();
            const id = cv.id;
            if (window.resetZoom) window.resetZoom(id);
        });

        // Mobile: gestione touch ottimizzata
        cv.addEventListener('touchstart', function(e) {
            touchStartTime = Date.now();
        }, { passive: true });

        cv.addEventListener('touchend', function(e) {
            const touchEndTime = Date.now();
            const touchDuration = touchEndTime - touchStartTime;
            
            // Doppio tap per reset (solo se non è stato un pan)
            if (touchDuration < 200) { // Tap rapido
                const now = Date.now();
                if (now - lastTap < 350) {
                    e.preventDefault();
                    const id = cv.id;
                    if (window.resetZoom) window.resetZoom(id);
                }
                lastTap = now;
            }
        }, { passive: false });

        // Previeni il comportamento di default del browser su alcuni gesti
        cv.addEventListener('gesturestart', e => e.preventDefault());
        cv.addEventListener('gesturechange', e => e.preventDefault());
        cv.addEventListener('gestureend', e => e.preventDefault());
    });
    
    // Setup cursore desktop separato
    setupDesktopPanCursor();
}

document.addEventListener('DOMContentLoaded', () => {
    initWeatherApp();
    initLazyCharts();
    addOptimizedEventListeners();
});

// Funzione globale migliorata
window.initCharts = function() {
    // Performance mark per debugging
    if (performance.mark) performance.mark('charts-init-start');
    
    // Batch DOM operations per migliori performance
    const canvasesToInit = [];
    const accordionsToSetup = [];
    
    // (1) Raccogli canvas da inizializzare
    document.querySelectorAll('canvas[data-chart]:not([data-chart-ready])').forEach(cv => {
        if (!cv.closest('.collapse')) {
            canvasesToInit.push(cv);
        }
    });
    
    // (2) Raccogli accordion aperti
    document.querySelectorAll('.collapse.show canvas[data-chart]').forEach(cv => {
        if (!cv.dataset.chartReady) {
            canvasesToInit.push(cv);
        }
    });
    
    // (3) Raccogli accordion da configurare
    document.querySelectorAll('.collapse').forEach(col => {
        if (!col.dataset.accordionChartsInited) {
            accordionsToSetup.push(col);
        }
    });
    
    // Inizializza tutti i canvas in batch
    canvasesToInit.forEach(cv => {
        requestAnimationFrame(() => initChartForCanvas(cv));
    });
    
    // Configura accordion
    accordionsToSetup.forEach(col => {
        col.dataset.accordionChartsInited = '1';
        col.addEventListener('shown.bs.collapse', () => {
            col.querySelectorAll('canvas[data-chart]').forEach(cv => {
                requestAnimationFrame(() => {
                    initChartForCanvas(cv);
                    const ch = getChartInstance(cv.id);
                    if (ch) ch.resize();
                });
            });
        });
    });
    
    // Aggiungi event listeners ottimizzati
    addOptimizedEventListeners();
    
    if (performance.mark && performance.measure) {
        performance.mark('charts-init-end');
        performance.measure('charts-init-duration', 'charts-init-start', 'charts-init-end');
    }
};

// Avvia all'avvio pagina
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.initCharts === "function") {
        // Usa requestAnimationFrame per inizializzazione non bloccante
        requestAnimationFrame(() => window.initCharts());
    }
});

// Cleanup listener per performance
window.addEventListener('beforeunload', () => {
    // Distruggi chart instances per liberare memoria
    [chartWind, chartRain, chartTemp, chartTide].forEach(chart => {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
    });
});