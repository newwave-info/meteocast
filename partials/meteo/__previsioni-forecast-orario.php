<?php
$index = 1; // 0=oggi, 1=domani
$timezone = new DateTimeZone(TIMEZONE);
$date = isset($daily_sunrise_times[$index]) ? new DateTimeImmutable($daily_sunrise_times[$index], $timezone) : (new DateTimeImmutable('tomorrow', $timezone));
$date_str = $date->format('Y-m-d');

$sunrise = $daily_sunrise_times[$index] ?? null;
$sunset  = $daily_sunset_times[$index]  ?? null;
$sunrise_dt = $sunrise ? new DateTimeImmutable($sunrise, $timezone) : null;
$sunset_dt  = $sunset  ? new DateTimeImmutable($sunset, $timezone)  : null;

// Trova indici tri-orari di domani: 01, 04, ..., 22
$indices = [];
$alba_index = null;
$min_diff = null;
foreach ($timestamps as $i => $ts) {
    $dt = new DateTimeImmutable($ts, $timezone);
    $h = (int)$dt->format('H');
    if ($dt->format('Y-m-d') === $date_str && (($h - 1) % 3 === 0)) {
        $indices[] = $i;
        if ($sunrise_dt) {
            $diff = abs($dt->getTimestamp() - $sunrise_dt->getTimestamp());
            if ($min_diff === null || $diff < $min_diff) {
                $min_diff = $diff;
                $alba_index = count($indices) - 1;
            }
        }
    }
}
if ($alba_index === null) $alba_index = 0; // fallback
?>
<div class="widget" style="padding: 0;">
  <div class="forecast" data-carousel-start="<?= $alba_index ?>">
    <?php
    foreach ($indices as $idx_count => $i):
      $ts = $timestamps[$i];
      $dt = new DateTimeImmutable($ts, $timezone);
      $hour = $dt->format('H:i');
      $is_night_hour = false;
      if ($sunrise_dt && $sunset_dt) {
        $is_night_hour = ($dt < $sunrise_dt || $dt >= $sunset_dt);
      }
      $code = $hourly_weather_codes[$i] ?? null;
      $weather_class = $code !== null ? getWeatherClass($code, $is_night_hour) : '';
      $desc = $code !== null ? getWeatherDescription($code) : '';
      $temp = isset($hourly_temperature[$i]) ? round($hourly_temperature[$i]) : '-';
      $apparent = $hourly_apparent_temperature[$i] ?? null;
      $wind_speed = isset($hourly_wind_speed[$i]) ? round($hourly_wind_speed[$i]) : null;
      $gust_speed = isset($hourly_wind_gusts[$i]) ? round($hourly_wind_gusts[$i]) : null;
      $wind_dir_val = isset($hourly_wind_direction[$i]) ? getWindDirection($hourly_wind_direction[$i]) : '-';
      $humidity = $hourly_humidity[$i] ?? null;
      $dew = $hourly_dew_point[$i] ?? null;
      $precip = $hourly_precip[$i] ?? null;

      // CLASSI GRADIENTE VENTO/GUST
      $wind_level = $wind_speed !== null ? getWindUnifiedLevel($wind_speed) : '';
      $gust_level = $gust_speed !== null ? getWindUnifiedLevel($gust_speed) : '';

      // TOOLTIP "INTELLIGENTE"
      $show_gust = ($gust_speed !== null && $wind_speed !== null && abs($gust_speed - $wind_speed) > 5);
      $wind_name = $wind_speed !== null ? getWindLabel($wind_speed) : '-';
      $extra_lines = [];
      // Precipitazioni solo se almeno 0.5 mm/h
      if ($precip !== null && $precip >= 0.5) {
        $extra_lines[] = "Precipitazioni: " . round($precip, 1) . " mm/h";
      }
      // Temperatura percepita solo se differenza ≥ 2°
      if ($apparent !== null && $temp !== null && abs($apparent - $temp) >= 2) {
        $extra_lines[] = "Percepita " . round($apparent) . "°";
      }
      // Umidità solo se molto alta o molto bassa
      if ($humidity !== null && ($humidity > 90 || $humidity < 30)) {
        $extra_lines[] = "Umidità: " . round($humidity) . "%";
      }
      // Dew point solo se > 21° (afa marcata)
      if ($dew !== null && $dew > 21) {
        $extra_lines[] = "Afa (dew point " . round($dew) . "°)";
      }
      // Vento sostenuto solo se >= 25 km/h
      if ($wind_speed !== null && $wind_speed >= 25) {
        $extra_lines[] = "Vento sostenuto (" . round($wind_speed) . " km/h)";
      }
      // Raffiche forti solo se >= 40 km/h
      if ($gust_speed !== null && $gust_speed >= 40) {
        $extra_lines[] = "Raffiche forti (" . round($gust_speed) . " km/h)";
      }

      $tooltip = "<strong>$desc</strong><br><small>"
        . "$wind_name " . ($wind_speed !== null ? "$wind_speed" : "-") . " km/h - $wind_dir_val";
      if ($show_gust) {
        $tooltip .= "<br>Raffiche $gust_speed km/h";
      }
      if (count($extra_lines)) {
        $tooltip .= "<br>" . implode("<br>", $extra_lines);
      }
      $tooltip .= "</small>";
    ?>
      <div class="forecast-item <?= $is_night_hour ? 'is-night' : '' ?>"
        data-bs-toggle="tooltip"
        data-bs-html="true"
        data-bs-placement="top"
        title="<?= htmlspecialchars($tooltip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="hour"><?= htmlspecialchars($hour) ?></div>
        <img src="<?= htmlspecialchars(getWeatherSvgIcon($code, $is_night_hour, true)) ?>" class="weather-svg-icon <?= htmlspecialchars($weather_class) ?>" alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
        <div class="temp"><?= htmlspecialchars($temp) . '°' ?></div>
        <div class="wind-box <?= htmlspecialchars($wind_level) ?> <?= htmlspecialchars($gust_level) ?>">
          <div class="wind-line"><?= htmlspecialchars($wind_speed) ?> / <?= htmlspecialchars($gust_speed) ?></div>
          <div class="wind-line"><?= htmlspecialchars($wind_dir_val) ?></div>
        </div>
      </div>
      <!-- Overlay Alba/Tramonto -->
      <?php
      $next_dt = $dt->modify('+3 hours');
      $show_sunrise = $sunrise_dt && $sunrise_dt >= $dt && $sunrise_dt < $next_dt;
      $show_sunset  = $sunset_dt  && $sunset_dt  >= $dt && $sunset_dt  < $next_dt;
      if ($show_sunrise || $show_sunset):
        $event_time = $show_sunrise ? $sunrise_dt : $sunset_dt;
        $event_icon = $show_sunrise ? 'wi-sunrise' : 'wi-sunset';
        $event_label = $show_sunrise ? 'Alba' : 'Tramonto';
        $event_class = $show_sunrise ? 'alba-icon' : 'tramonto-icon';
      ?>
        <div class="forecast-item <?= $show_sunrise ? 'alba' : 'tramonto' ?>">
          <div class="hour"><?= htmlspecialchars($event_time->format('H:i')) ?></div>
          <img src="<?= htmlspecialchars(getWeatherSvgFromClass($event_icon)) ?>" class="weather-svg-icon <?= $event_class ?>" alt="<?= htmlspecialchars($event_label) ?>" loading="lazy" />
          <div class="temp">&nbsp;</div>
          <div class="wind">&nbsp;</div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
