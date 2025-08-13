<!--
 -----------------------------------------------------------------------------
  Esportazione variabili meteo (PHP → JS) per Chart.js e UI dinamica
 -----------------------------------------------------------------------------
  - Passa tutte le variabili orarie/giornalieri (popolate da api-fetch.php) a JavaScript
  - Usa oggetto globale weatherData (unico punto d’accesso per i grafici e widget)
  - Lato JS, weatherData viene usato in app.js per Chart.js e logica interattiva

  Nota:
    - Le chiavi JS corrispondono ai nomi usati nei template/chart
    - Usi JSON_UNESCAPED_SLASHES per massima compatibilità

  Sicurezza:
    - Nessun dato sensibile, array già sanitizzati da PHP
 -----------------------------------------------------------------------------
-->

<script>
window.weatherData = <?= json_encode([
    'timestamps'     => $timestamps,
    'temperature'    => $hourly_temperature,
    'apparent_temperature' => $hourly_apparent_temperature,
    'precip'         => $hourly_precip,
    'precip_prob'    => $hourly_precip_prob,
    'humidity'       => $hourly['relative_humidity_2m'],
    'sunrise'        => $daily_sunrise_times,
    'sunset'         => $daily_sunset_times,
    'dailyCount'     => count($daily['time']),
    'visibility'     => $hourly_visibility,
    'wind_speed'     => $hourly_wind_speed,
    'wind_gusts'     => $hourly_wind_gusts,
    'wind_dir'       => $hourly_wind_direction,
    'cloud_cover'    => $hourly_cloud_cover,
    'uv_index'       => $hourly_uv_index
], JSON_UNESCAPED_SLASHES) ?>;

window.appTimezone = <?= json_encode(TIMEZONE) ?>;

window.chartSettings = {
    hoursHistory: <?= (int)CHART_HOURS_HISTORY ?>,
    hoursForecast: <?= (int)CHART_HOURS_FORECAST ?>
};

</script>
