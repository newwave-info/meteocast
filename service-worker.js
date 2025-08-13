// MeteoCast Service Worker v1.2.2

const CACHE_NAME = "meteocast-v1.3.0"; // 🔹 Cambia versione per aggiornare la cache
const urlsToCache = [
  // CSS e JS core
  "/assets/css/style.css",
  "/assets/js/chartjs-core.js",
  "/assets/js/custom.js",
  "/assets/js/geolocate.js",
  "/assets/js/my-charts.js",
  "/assets/js/search-location.js",
  "/assets/js/weather-background.js",

  // Icone PNG e SVG
  "/assets/icons/icon-192.png",
  "/assets/icons/icon-512.png",
  "/assets/icons/svg/alert-avalanche-danger.svg",
  "/assets/icons/svg/alert-falling-rocks.svg",
  "/assets/icons/svg/barometer.svg",
  "/assets/icons/svg/beanie.svg",
  "/assets/icons/svg/celsius.svg",
  "/assets/icons/svg/clear-day.svg",
  "/assets/icons/svg/clear-night.svg",
  "/assets/icons/svg/cloud-down.svg",
  "/assets/icons/svg/cloud-up.svg",
  "/assets/icons/svg/cloudy.svg",
  "/assets/icons/svg/code-green.svg",
  "/assets/icons/svg/code-orange.svg",
  "/assets/icons/svg/code-red.svg",
  "/assets/icons/svg/code-yellow.svg",
  "/assets/icons/svg/compass.svg",
  "/assets/icons/svg/drizzle.svg",
  "/assets/icons/svg/dust-day.svg",
  "/assets/icons/svg/dust-night.svg",
  "/assets/icons/svg/dust-wind.svg",
  "/assets/icons/svg/dust.svg",
  "/assets/icons/svg/extreme-day-drizzle.svg",
  "/assets/icons/svg/extreme-day-fog.svg",
  "/assets/icons/svg/extreme-day-hail.svg",
  "/assets/icons/svg/extreme-day-haze.svg",
  "/assets/icons/svg/extreme-day-rain.svg",
  "/assets/icons/svg/extreme-day-sleet.svg",
  "/assets/icons/svg/extreme-day-smoke.svg",
  "/assets/icons/svg/extreme-day-snow.svg",
  "/assets/icons/svg/extreme-day.svg",
  "/assets/icons/svg/extreme-drizzle.svg",
  "/assets/icons/svg/extreme-fog.svg",
  "/assets/icons/svg/extreme-hail.svg",
  "/assets/icons/svg/extreme-haze.svg",
  "/assets/icons/svg/extreme-night-drizzle.svg",
  "/assets/icons/svg/extreme-night-fog.svg",
  "/assets/icons/svg/extreme-night-hail.svg",
  "/assets/icons/svg/extreme-night-haze.svg",
  "/assets/icons/svg/extreme-night-rain.svg",
  "/assets/icons/svg/extreme-night-sleet.svg",
  "/assets/icons/svg/extreme-night-smoke.svg",
  "/assets/icons/svg/extreme-night-snow.svg",

  // Background images
   "/assets/img/cloud1.png",
   "/assets/img/cloud2.png",
   "/assets/img/cloud3.png",
   "/assets/img/cloud4.png",
   "/assets/img/cloud5.png",
   "/assets/img/cloud6.png",
   "/assets/img/cloud7.png",
   "/assets/img/cloud8.png",
   "/assets/img/cloud9.png",
   "/assets/img/cloud10.png",
   "/assets/img/cloud11.png",
   "/assets/img/cloud12.png",
   "/assets/img/fog.png"
  
];

// INSTALLAZIONE: cache di tutti gli asset statici
self.addEventListener("install", (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache);
    })
  );
});

// ATTIVA: elimina cache vecchie e prendi subito il controllo
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// FETCH: shell cache-first, endpoint dati network-first
self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // Asset statici (shell): CACHE-FIRST
  if (urlsToCache.includes(url.pathname)) {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        return cachedResponse ||
          fetch(event.request).then((response) => {
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, response.clone());
            });
            return response;
          });
      })
    );
    return;
  }

  // Endpoint dati (modifica se cambi il nome del file)
  if (url.pathname === "/includes/api-fetch.php") {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, response.clone());
          });
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Default: lascia il browser gestire (SPA/route, ecc.)
});
