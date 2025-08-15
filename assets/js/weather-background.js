// WEATHERFX FULL - Apple Style, 60fps Natural Animations, NO Canvas Clouds

class WeatherFX {
  constructor() {
    this.canvas = document.getElementById('weatherCanvas');
    this.ctx = this.canvas.getContext('2d');
    this.gradient = document.getElementById('weatherGradient');
    this.overlay = document.getElementById('weatherOverlay');

    this.width = 0;
    this.height = 0;
    this.dpr = window.devicePixelRatio || 1;
    this.lowPower = false;
    this.dprCap = 1.5; // default, ridotto in low-power

    this.weatherCode = 0;
    this.isNight = false;
    this.isSunset = false;
    this.isSunrise = false;
    this.effects = {};

    this.fps = 60;
    this.frameCount = 0;
    this.lastTime = 0;
    this.lastFpsUpdate = 0;
    this.lastDraw = performance.now();

    this.nextLightning = 0;
    this.lightingOpacity = 0;

    this.nextStar = 0;
    this.shootingStar = null;

    // Config pool
    this.maxRain = 50;
    this.maxSnow = 35;
    this.maxStars = 85;
    this.running = true;

    this.resize();
    window.addEventListener('resize', () => this.resize());

    this.loop = this.loop.bind(this);
    requestAnimationFrame(this.loop);

    window.updateWeatherBackground = (code, night, sunset = false, sunrise = false) => {
  console.log('>>> updateWeatherBackground', {code, night, sunset, sunrise});
  console.trace('CHIAMATA updateWeatherBackground'); // <-- Aggiungi questa riga!
  this.setWeather(code, night, sunset, sunrise);
};

// Prima init: NON inizializzare a 0! Attendi i dati veri.
  }

  setLowPower(flag) {
  this.lowPower = !!flag;
  this.dprCap = this.lowPower ? 1.0 : 1.5;  // riduce DPI per batteria
  this._initEffects();                      // ricalibra densità effetti
  this.resize();                            // reimposta canvas con DPR attuale
}

  setWeather(code, night = false, sunset = false, sunrise = false) {
  this.weatherCode = parseInt(code);
  this.isNight = !!night;
  this.isSunset = !!sunset;
  this.isSunrise = !!sunrise;
  let extraClass = '';
  if (this.isSunset) extraClass = ' sunset';
  if (this.isSunrise) extraClass = ' sunrise';
  this.gradient.className = `weather-gradient meteo-${code}${night ? ' night' : ''}${extraClass}`;
  this.overlay.className = `weather-overlay meteo-${code}${night ? ' night' : ''}${extraClass}`;
  if (night) document.body.classList.add('night');
  else document.body.classList.remove('night');
  this._initEffects();
}


  _initEffects() {
    this.effects = {};
    const rainCodes = [51,53,55,56,57,61,63,65,66,67,80,81,82,95,96,99];
    const snowCodes = [71,73,75,77,85,86];

    const mobileOrBattery = document.body.classList.contains('battery-saver') || window.innerWidth < 600;
    this.maxRain = mobileOrBattery ? 40 : 80;
    this.maxSnow = mobileOrBattery ? 28 : 60;
    this.maxStars = mobileOrBattery ? 30 : 80;

    if (rainCodes.includes(this.weatherCode)) this.effects.rain = this._makeRain();
    if (snowCodes.includes(this.weatherCode)) this.effects.snow = this._makeSnow();
    if (this.isNight && [0,1].includes(this.weatherCode)) this.effects.stars = this._makeStars();
    if ([95,96,99].includes(this.weatherCode)) {
      this.nextLightning = Date.now() + 2000 + Math.random() * 3500;
      this.lightingOpacity = 0;
    }
    if (!this.isNight && this.weatherCode === 0) {
      this.effects.sunFlare = this._makeSunFlare();
    }
  }

  // === GENERATORS ===
  _makeRain() {
    const intMap = {51:0.3,53:0.6,55:1,61:0.4,63:0.7,65:1,80:0.6,81:0.8,82:1,95:1.2,96:1.3,99:1.5};
    let intensity = intMap[this.weatherCode] || 0.5;
    let multiplier = intensity < 0.85 ? 1 : 1 + ((intensity-0.8)*4.5);
    let drops = Math.floor(this.maxRain * intensity * multiplier);
    drops = Math.min(drops, 170);

    const arr = [];
    for(let i=0;i<drops;i++){
      arr.push({
        x: Math.random()*this.width,
        y: Math.random()*this.height,
        l: 13+Math.random()*(22+18*intensity),
        speed: 7+Math.random()*7+intensity*10,
        opacity: 0.23+Math.random()*0.7
      });
    }
    if(intensity > 1) {
      for(let i=0; i<Math.floor(drops*0.20); i++){
        arr.push({
          x: Math.random()*this.width,
          y: this.height*0.98+Math.random()*this.height*0.02,
          l: 3+Math.random()*7,
          speed: 10+Math.random()*2,
          opacity: 0.15+Math.random()*0.2
        });
      }
    }
    return arr;
  }

  _makeSnow() {
    const intMap = {71:0.4,73:0.7,75:1,77:0.5,85:0.7,86:1.2};
    let intensity = intMap[this.weatherCode] || 0.5;
    let multiplier = intensity < 0.85 ? 1 : 1 + ((intensity-0.8)*3.5);
    let flakes = Math.floor(this.maxSnow * intensity * multiplier);
    flakes = Math.min(flakes, 100);

    const arr = [];
    for(let i=0;i<flakes;i++){
      arr.push({
        x: Math.random()*this.width,
        y: Math.random()*this.height,
        r: (intensity > 0.9 ? 2+Math.random()*4.5 : 1.6+Math.random()*2.1),
        speed: 0.8+Math.random()*1.1+intensity*1.2,
        drift: (Math.random()-0.5)*0.9,
        phase: Math.random()*Math.PI*2,
        opacity: 0.45+Math.random()*0.5
      });
    }
    if(intensity > 1) {
      for(let i=0; i<Math.floor(flakes*0.10); i++){
        arr.push({
          x: Math.random()*this.width,
          y: Math.random()*this.height,
          r: 0.9+Math.random()*1.3,
          speed: 0.4+Math.random()*0.8,
          drift: (Math.random()-0.5)*1.4,
          phase: Math.random()*Math.PI*2,
          opacity: 0.09+Math.random()*0.08
        });
      }
    }
    return arr;
  }

  _makeStars() {
    const arr = [];
    for(let i=0;i<this.maxStars;i++){
      arr.push({
        x: Math.random()*this.width,
        y: Math.random()*this.height*0.52,
        r: 0.7+Math.random()*1.6,
        blink: Math.random()*2*Math.PI,
        speed: 0.35+Math.random()*0.95
      });
    }
    this.nextStar = Date.now() + 6500 + Math.random()*8000;
    return arr;
  }

  _makeSunRays() {
    const rays = [];
    let n = 5 + Math.floor(Math.random()*2);
    for(let i=0; i<n; i++){
      rays.push({
        angle: (Math.PI/4)+(i*(Math.PI/15))+Math.random()*0.08,
        len: 90+Math.random()*35,
        opacity: 0.13+Math.random()*0.10,
        phase: Math.random()*Math.PI*2
      });
    }
    return rays;
  }

  _makeSunFlare() {
    return [
      {x: this.width*0.60, y: this.height*0.17, r: 64+Math.random()*14, alpha: 0.24+Math.random()*0.06, speed: 0.04+Math.random()*0.05, phase: Math.random()*10},
      {x: this.width*0.50, y: this.height*0.31, r: 19+Math.random()*7, alpha: 0.09+Math.random()*0.04, speed: 0.02+Math.random()*0.04, phase: Math.random()*10}
    ];
  }

  // === TIME-BASED LOOP & DRAW ===
  loop(now) {
    if (!this.running) return;

    const targetFPS = this.lowPower ? 30 : 60;
    const minFrameTime = 1000 / targetFPS;
    const delta = now - this.lastDraw;

    if (delta >= minFrameTime) {
      const speedFactor = delta / minFrameTime;

      this.frameCount++;
      this.ctx.clearRect(0,0,this.width,this.height);

      if (this.effects.rain) this._drawRain(speedFactor);
      if (this.effects.snow) this._drawSnow(speedFactor);
      if (this.effects.stars) this._drawStars(speedFactor);
      if (this.effects.sunFlare) this._drawSunFlare(speedFactor);
      if ([95,96,99].includes(this.weatherCode)) this._drawLightning(now);

      if (now - this.lastFpsUpdate > 1000) {
        this.fps = this.frameCount;
        this.frameCount = 0;
        this.lastFpsUpdate = now;
      }
      this.lastDraw = now;
    }
    requestAnimationFrame(this.loop);
  }

  _drawRain(speedFactor=1) {
    const arr = this.effects.rain;
    this.ctx.save();
    this.ctx.strokeStyle = 'rgba(160,200,255,0.65)';
    this.ctx.lineWidth = this.dpr * 1.3;
    for(let d of arr){
      this.ctx.globalAlpha = d.opacity;
      this.ctx.beginPath();
      this.ctx.moveTo(d.x, d.y);
      this.ctx.lineTo(d.x, d.y+d.l);
      this.ctx.stroke();

      d.y += d.speed * speedFactor * 1.4;
      if (d.y > this.height) {
        d.x = Math.random()*this.width;
        d.y = -10 - Math.random()*30;
        d.l = 10+Math.random()*18;
      }
    }
    this.ctx.restore();
    this.ctx.globalAlpha = 1;
  }

  _drawSnow(speedFactor=1) {
    const arr = this.effects.snow;
    this.ctx.save();
    this.ctx.fillStyle = 'rgba(255,255,255,0.86)';
    for(let f of arr){
      this.ctx.globalAlpha = f.opacity;
      this.ctx.beginPath();
      this.ctx.arc(f.x, f.y, f.r, 0, Math.PI*2);
      this.ctx.fill();

      f.y += f.speed * speedFactor;
      f.x += Math.sin(f.phase + f.y*0.013)*f.drift * speedFactor;
      if (f.y > this.height) {
        f.y = -10;
        f.x = Math.random()*this.width;
      }
    }
    this.ctx.restore();
    this.ctx.globalAlpha = 1;
  }

  _drawStars(speedFactor=1) {
    const arr = this.effects.stars;
    this.ctx.save();
    for(let s of arr){
      const blink = 0.7 + Math.abs(Math.sin(Date.now()/700 + s.blink)) * 0.7;
      this.ctx.globalAlpha = blink * 0.8;
      this.ctx.beginPath();
      this.ctx.arc(s.x, s.y, s.r, 0, Math.PI*2);
      this.ctx.fillStyle = '#fff';
      this.ctx.shadowColor = '#fff';
      this.ctx.shadowBlur = 6;
      this.ctx.fill();
      this.ctx.shadowBlur = 0;
    }
    this.ctx.restore();
    this.ctx.globalAlpha = 1;
    // Shooting star
    if (Date.now() > (this.nextStar||0)) {
      this._shootingStar();
      this.nextStar = Date.now() + 6500 + Math.random()*8000;
    }
    if (this.shootingStar) {
      let s = this.shootingStar;
      this.ctx.save();
      this.ctx.globalAlpha = s.alpha;
      this.ctx.strokeStyle = 'rgba(255,255,255,0.86)';
      this.ctx.lineWidth = this.dpr * 1.1;
      this.ctx.beginPath();
      this.ctx.moveTo(s.x, s.y);
      this.ctx.lineTo(s.x + s.vx*12, s.y + s.vy*10);
      this.ctx.stroke();
      this.ctx.restore();

      s.x += s.vx*7 * speedFactor;
      s.y += s.vy*4.5 * speedFactor;
      s.alpha -= 0.024 * speedFactor;
      if (s.alpha <= 0.02) this.shootingStar = null;
    }
  }

  _shootingStar() {
    this.shootingStar = {
      x: Math.random()*this.width*0.7,
      y: Math.random()*this.height*0.4+20,
      vx: 2.8+Math.random()*1.6,
      vy: 1.1+Math.random()*1.3,
      alpha: 1
    };
  }

  _drawLightning(now) {
    if (Date.now() > this.nextLightning) {
      this.lightingOpacity = 0.74 + Math.random()*0.42;
      this.nextLightning = Date.now() + 1400 + Math.random()*3200;
    }
    if (this.lightingOpacity > 0.02) {
      this.ctx.save();
      this.ctx.globalAlpha = this.lightingOpacity;
      this.ctx.fillStyle = 'rgba(220,240,255,0.92)';
      this.ctx.fillRect(0, 0, this.width, this.height);
      this.ctx.restore();
      this.lightingOpacity *= 0.72 - Math.random()*0.09;
    }
  }

  

 _drawSunFlare(speedFactor = 1) {
  const t = Date.now() / 3000;

  // Offset posizionato in basso a destra rispetto all’icona sole
  const flareBaseX = this.width * 0.29;
  const flareBaseY = this.height * 0.17;

  // === FLARE PRINCIPALE (vibrazione morbida) ===
  const x = flareBaseX + Math.sin(t * 1.3) * 6;
  const y = flareBaseY + Math.cos(t * 1.1) * 6;
  const rad = this.width * 0.08 + Math.sin(t * 1.5) * 8;

  const grad = this.ctx.createRadialGradient(x, y, 0, x, y, rad);
  grad.addColorStop(0, `rgba(255,235,200,0.35)`);
  grad.addColorStop(0.2, `rgba(255,220,170,0.12)`);
  grad.addColorStop(0.5, `rgba(255,210,150,0.08)`);
  grad.addColorStop(1, `rgba(255,200,140,0)`);

  this.ctx.save();
  this.ctx.globalAlpha = 1;
  this.ctx.beginPath();
  this.ctx.arc(x, y, rad, 0, Math.PI * 2);
  this.ctx.fillStyle = grad;
  this.ctx.fill();
  this.ctx.restore();

  // === FLARE SECONDARIO ===
  const x2 = flareBaseX + 70 + Math.sin(t * 0.9) * 18;
  const y2 = flareBaseY + 50 + Math.cos(t * 0.6) * 13;
  const rad2 = this.width * 0.03 + Math.sin(t * 0.7) * 2;

  const grad2 = this.ctx.createRadialGradient(x2, y2, 0, x2, y2, rad2);
  grad2.addColorStop(0, `rgba(255,220,180,0.20)`);
  grad2.addColorStop(0.4, `rgba(255,210,160,0.08)`);
  grad2.addColorStop(1, `rgba(255,210,160,0)`);

  this.ctx.save();
  this.ctx.globalAlpha = 0.5;
  this.ctx.beginPath();
  this.ctx.arc(x2, y2, rad2, 0, Math.PI * 2);
  this.ctx.fillStyle = grad2;
  this.ctx.fill();
  this.ctx.restore();
}



  resize() {
    this.dpr = Math.min(window.devicePixelRatio || 1, this.dprCap);
    this.width = window.innerWidth * this.dpr;
    this.height = window.innerHeight * this.dpr;
    this.canvas.width = this.width;
    this.canvas.height = this.height;
    this.canvas.style.width = window.innerWidth + 'px';
    this.canvas.style.height = window.innerHeight + 'px';
    this._initEffects();
  }
}

window.weatherFX = new WeatherFX();


function syncWeatherFXPower() {
  if (!window.weatherFX) return;
  const low = (window.POWER && window.POWER.isLow()) || document.hidden;
  window.weatherFX.setLowPower(low);
  if (low) {
    window.weatherFX.running = false;
  } else if (!window.weatherFX.running) {
    window.weatherFX.running = true;
    requestAnimationFrame(window.weatherFX.loop);
  }
}
document.addEventListener('power:change', syncWeatherFXPower);
document.addEventListener('visibilitychange', syncWeatherFXPower);
window.addEventListener('pageshow', syncWeatherFXPower);
window.addEventListener('pagehide', syncWeatherFXPower);
syncWeatherFXPower();

