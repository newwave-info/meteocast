// ==================== Loader ===========================
window.mostraLoader = function() {
  var loader = document.getElementById('meteocast-loader');
  if (loader) loader.style.display = 'flex';
}
window.hideLoader = function() {
  var loader = document.getElementById('meteocast-loader');
  if (loader) loader.style.display = 'none';
}

// ==================== Tooltip Bootstrap 5 ==============
window.initTooltips = function() {
  const THRESHOLD = 15;
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(trigger => {
    if (trigger.dataset.tooltipInitialized) return;
    trigger.dataset.tooltipInitialized = "1";
    const tt = new bootstrap.Tooltip(trigger, { trigger: 'manual', placement: 'top' });
    let isOpen = false, timeout = null, startX = 0, startY = 0;

    function closeTooltip(e){
      if (e?.type === 'click' && (e.target === trigger || trigger.contains(e.target))) return;
      if (!isOpen) return;
      isOpen = false;
      tt.hide();
      clearTimeout(timeout);
      window.removeEventListener('scroll',     closeTooltip, true);
      window.removeEventListener('wheel',      closeTooltip, true);
      window.removeEventListener('touchstart', closeTooltip, true);
      window.removeEventListener('keydown',    closeTooltip, true);
      window.removeEventListener('click',      closeTooltip, true);
      window.removeEventListener('mousemove',  onMouseMove,  true);
    }
    function onMouseMove(ev){
      const dx = ev.clientX - startX;
      const dy = ev.clientY - startY;
      if (Math.hypot(dx, dy) > THRESHOLD) closeTooltip();
    }
    trigger.addEventListener('click', e => {
      e.preventDefault();
      if (isOpen){ closeTooltip(); return; }
      isOpen = true;
      tt.show();
      startX = e.clientX; startY = e.clientY;
      timeout = setTimeout(closeTooltip, 4000);
      const opts = { capture:true };
      window.addEventListener('scroll',     closeTooltip, opts);
      window.addEventListener('wheel',      closeTooltip, opts);
      window.addEventListener('touchstart', closeTooltip, opts);
      window.addEventListener('keydown',    closeTooltip, opts);
      window.addEventListener('click',      closeTooltip, opts);
      window.addEventListener('mousemove',  onMouseMove,  true);
    });
  });
}

// ==================== SPA Routing / Tab logic ===============
window.loadView = function(view, push = true) {
  fetch('/partials/common/view.php?view=' + view)
    .then(res => res.text())
    .then(html => {
      document.getElementById('main-content').innerHTML = html;
      document.querySelectorAll('.nav-link-nav').forEach(link => {
        link.classList.toggle('active', link.dataset.view === view);
      });
      // NEW: Legge i dati marea dopo ogni cambio view
      var tideJsonTag = document.getElementById('tideDataJson');
      if (tideJsonTag) {
          try {
              window.tideData = JSON.parse(tideJsonTag.textContent);
              console.log('TIDE DATA (da JSON):', window.tideData);
          } catch(e) {
              window.tideData = [];
              console.warn('TIDE DATA JSON parsing error', e);
          }
      }
      if (typeof initTooltips === 'function') initTooltips();
      if (typeof initCharts === 'function') initCharts();
      if (push) history.pushState({view: view}, '', '/' + view);
    });
}


window.initTabs = function() {
  document.querySelectorAll('.nav-link-nav').forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const view = this.dataset.view;
      if (typeof loadView === 'function') loadView(view);
    });
  });
  window.addEventListener('popstate', function (e) {
    const view = (e.state && e.state.view) || 'oggi';
    if (typeof loadView === 'function') loadView(view, false);
  });
}

// ==================== Init globale su DOM ===================
document.addEventListener('DOMContentLoaded', function () {
  if (typeof initTabs === 'function') initTabs();
  // Carica la view corretta in base all’URL solo una volta!
  let initialView = location.pathname.replace('/', '') || 'oggi';
  if (typeof loadView === 'function') loadView(initialView, false);
});



