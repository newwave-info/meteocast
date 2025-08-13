<?php
// Patch robusta indici: calcola now_index e max_forecast rispetto all’array orario “tagliato”
$now_index = 0;
if (!empty($timestamps)) {
    $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
    foreach ($timestamps as $i => $ts) {
        $dt = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        if ($dt >= $now) {
            $now_index = $i;
            break;
        }
    }
}
$num_timestamps = is_array($timestamps) ? count($timestamps) : 0;
$max_forecast = ($num_timestamps && isset($now_index))
    ? min(FORECAST_HOURS_TODAY, $num_timestamps - $now_index)
    : 0;

// Inizializza start_hourly e last per ciclo orario
$start_hourly = $now_index + 1;
$last = $now_index + $max_forecast - 1;

// PATCH 1: Trova il primo indice 15min >= adesso
$first_15m_future = 0;
if (!empty($minutely_15_timestamps)) {
    $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
    foreach ($minutely_15_timestamps as $k => $ts) {
        $dt = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        if ($dt >= $now) {
            $first_15m_future = $k;
            break;
        }
    }
}

// DEBUG: stampa gli indici in HTML (rimuovi in produzione)
echo "<!-- DEBUG: now_index=$now_index, start_hourly=$start_hourly, last=$last, num_timestamps=$num_timestamps, first_15m_future=$first_15m_future -->";
?>

<div class="widget" style="padding: 0;">
  <div class="forecast">
    <?php
    // Sicurezza
    if ($num_timestamps < 1 || !isset($now_index)) {
      echo '<div class="alert alert-warning my-2">Dati previsionali non disponibili.</div>';
      return;
    }

    $prev_day = (new DateTimeImmutable($timestamps[$now_index], new DateTimeZone(TIMEZONE)))->format('Y-m-d');
    ?>

    <!-- Item "Adesso" -->
    <?php
    $desc = isset($current_code) ? getWeatherDescription($current_code) : '-';
    $icon_class = isset($current_code) ? getWeatherIcon($current_code, $isNight) : '';
    $wind_label = isset($current_wind_speed) ? getWindLabel($current_wind_speed) : '-';
    $wind_dir = isset($current_wind_direction) ? getWindDirection($current_wind_direction) : '-';
    $wind_level = isset($current_wind_speed) ? getWindUnifiedLevel($current_wind_speed) : '';
    $gust_level = isset($current_wind_gusts) ? getWindUnifiedLevel($current_wind_gusts) : '';

    // Tooltip intelligente
    $show_gust = (isset($current_wind_gusts) && isset($current_wind_speed) && abs($current_wind_gusts - $current_wind_speed) > 5);
    $tooltip_now = "<strong>$desc</strong><br><small>"
      . "$wind_label " . (isset($current_wind_speed) ? round($current_wind_speed) : '-') . " km/h - $wind_dir";
    if ($show_gust) {
      $tooltip_now .= "<br>Raffiche " . round($current_wind_gusts) . " km/h";
    }
    $tooltip_now .= "</small>";
    ?>

    <div class="forecast-item <?= $isNight ? 'is-night' : '' ?>"
      data-bs-toggle="tooltip"
      data-bs-html="true"
      data-bs-placement="top"
      title="<?= htmlspecialchars($tooltip_now, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <div class="hour">Adesso</div>
      <img src="<?= htmlspecialchars(getWeatherSvgIcon($current_code, $isNight, true)) ?>" class="weather-svg-icon " alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
      <div class="temp"><?= isset($current_temp) ? round($current_temp) . '°' : '-' ?></div>
      <div class="wind-box <?= htmlspecialchars($wind_level) ?> <?= htmlspecialchars($gust_level) ?>">
        <div class="wind-line"><?= isset($current_wind_speed, $current_wind_gusts) ? round($current_wind_speed) . ' / ' . round($current_wind_gusts) : '-' ?></div>
        <div class="wind-line"><?= htmlspecialchars($wind_dir) ?></div>
      </div>
    </div>

    <?php
    // --- INTEGRA DATI A 15 MINUTI (SOLO FUTURI), POI CONTINUA ORARIO ---
    $minutely_steps = isset($minutely_15_timestamps) ? count($minutely_15_timestamps) : 0;
    $hybrid_steps = min($minutely_steps, 24); // Mostra fino a 6h (24*15min), cambia se vuoi più/meno
    $end_15min_dt = null;

    // PATCH 2: Solo dati a 15m FUTURI rispetto a now!
    if ($hybrid_steps > 0 && $first_15m_future < $hybrid_steps) {
      for ($i = $first_15m_future; $i < $hybrid_steps; $i++) {
        $ts = $minutely_15_timestamps[$i] ?? null;
        if (!$ts) continue;
        $time = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        $day = $time->format('Y-m-d');
        $hour = $time->format('H:i');

        // Intestazione cambio giorno
        if ($day !== $prev_day) {
          $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::FULL, IntlDateFormatter::NONE, TIMEZONE, IntlDateFormatter::GREGORIAN, 'EEEE d');
          $giorno_settimana = ucfirst($formatter->format($time));
          $giorno_label = mb_substr(explode(' ', $giorno_settimana)[0], 0, 3);
          $day_num = $time->format('d');
          ?>
          <div class='forecast-day-label'>
            <div class='giorno-label'><?= htmlspecialchars($giorno_label) ?></div>
            <div class='divider'></div>
            <div class='giorno-num'><?= htmlspecialchars($day_num) ?></div>
          </div>
          <?php
          $prev_day = $day;
        }

        // Dati previsione 15m
        $temp = isset($minutely_15_temperature[$i]) ? round($minutely_15_temperature[$i]) : null;
        $apparent = $minutely_15_apparent_temperature[$i] ?? null;
        $wind_speed = isset($minutely_15_wind_speed[$i]) ? round($minutely_15_wind_speed[$i]) : null;
        $wind = $wind_speed ?? '-';
        $gust_speed = isset($minutely_15_wind_gusts[$i]) ? round($minutely_15_wind_gusts[$i]) : null;
        $gust = $gust_speed ?? '-';
        $code = $minutely_15_weather_code[$i] ?? null;
        $precip = $minutely_15_precipitation[$i] ?? null;
        $humidity = null; // non disponibile nei dati 15m (fallback orario dopo)
        $dew = null; // non disponibile nei dati 15m

        // Direzione vento 15min
        $wind_dir_val = (isset($minutely_15_wind_direction[$i]) && $minutely_15_wind_direction[$i] !== null)
          ? getWindDirection($minutely_15_wind_direction[$i])
          : '-';

        // Comfort/tooltip/semaforo: fallback "verde"
        $show_comfort = 'dot-green';
        $comfort_motivo = null;

        // Alba/tramonto
        $sunrise = $sunrise_map[$day] ?? null;
        $sunset = $sunset_map[$day] ?? null;
        $is_night_hour = false;
        if ($sunrise && $sunset) {
          $is_night_hour = ($time < $sunrise || $time >= $sunset);
        }
        $icon = $code !== null ? getWeatherIcon($code, $is_night_hour) : '';
        $night_class = $is_night_hour ? 'is-night' : '';
        $wind_level = $wind_speed !== null ? getWindUnifiedLevel($wind_speed) : '';
        $gust_level = $gust_speed !== null ? getWindUnifiedLevel($gust_speed) : '';

        // Tooltip 15min
        $show_gust = ($gust_speed !== null && $wind_speed !== null && abs($gust_speed - $wind_speed) > 5);
        $desc = $code !== null ? getWeatherDescription($code) : '';
        $wind_name = $wind_speed !== null ? getWindLabel($wind_speed) : '';
        $extra_lines = [];
        if ($precip !== null && $precip >= 0.5) {
          $extra_lines[] = "Precipitazioni: " . round($precip, 1) . " mm/h";
        }
        if ($apparent !== null && $temp !== null && abs($apparent - $temp) >= 2) {
          $extra_lines[] = "Percepita " . round($apparent) . "°";
        }
        if ($wind_speed !== null && $wind_speed >= 25) {
          $extra_lines[] = "Vento sostenuto (" . round($wind_speed) . " km/h)";
        }
        if ($gust_speed !== null && $gust_speed >= 40) {
          $extra_lines[] = "Raffiche forti (" . round($gust_speed) . " km/h)";
        }

        $tooltip = "<strong>$desc</strong><br><small>"
          . "$wind_name $wind_speed km/h - $wind_dir_val";
        if ($show_gust) {
          $tooltip .= "<br>Raffiche $gust_speed km/h";
        }
        if (count($extra_lines)) {
          $tooltip .= "<br>" . implode("<br>", $extra_lines);
        }
        $tooltip .= "</small>";
        ?>

        <div class="forecast-item <?= $night_class ?>"
          data-bs-toggle="tooltip"
          data-bs-html="true"
          data-bs-placement="top"
          title="<?= htmlspecialchars($tooltip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <div class="hour"><?= htmlspecialchars($hour) ?></div>
          <img src="<?= htmlspecialchars(getWeatherSvgIcon($code, $is_night_hour, true)) ?>" class="weather-svg-icon" alt="<?= htmlspecialchars(getWeatherDescription($code)) ?>" loading="lazy" />
          <div class="temp"><?= isset($temp) ? htmlspecialchars($temp) . '°' : '-' ?></div>
          <div class="wind-box <?= htmlspecialchars($wind_level) ?> <?= htmlspecialchars($gust_level) ?>">
            <div class="wind-line"><?= htmlspecialchars($wind) ?> / <?= htmlspecialchars($gust) ?></div>
            <div class="wind-line"><?= htmlspecialchars($wind_dir_val) ?></div>
          </div>
        </div>
        <?php

        // Alba/Tramonto su dati 15min
        $next_time = $time->modify('+15 minutes');
        $show_sunrise = $sunrise && $sunrise >= $time && $sunrise < $next_time;
        $show_sunset  = $sunset  && $sunset  >= $time && $sunset  < $next_time;
        if ($show_sunrise || $show_sunset):
          $event_time = $show_sunrise ? $sunrise : $sunset;
          $event_icon = $show_sunrise ? 'wi-sunrise' : 'wi-sunset';
          $event_label = $show_sunrise ? 'Alba' : 'Tramonto';
          $event_class = $show_sunrise ? 'alba-icon' : 'tramonto-icon';
          $event_type_class = $show_sunrise ? 'alba' : 'tramonto';
          ?>
          <div class="forecast-item <?= $event_type_class ?>">
            <div class="hour"><?= htmlspecialchars($event_time->format('H:i')) ?></div>
            <img src="<?= htmlspecialchars(getWeatherSvgFromClass($event_icon)) ?>" class="weather-svg-icon <?= $event_class ?>" alt="<?= htmlspecialchars($event_label) ?>" loading="lazy" />
            <div class="temp">&nbsp;</div>
            <div class="wind">&nbsp;</div>
          </div>
        <?php endif;
        $end_15min_dt = $time; // Ultimo timestamp dei dati 15min
      }
    }

    // --- ORARIO: DAL PRIMO TIMESTAMP OLTRE IL RANGE DEI 15 MINUTI ---
    if ($last < $start_hourly) {
      echo '<div class="alert alert-warning my-2">Nessun dato orario disponibile dopo la timeline a 15 minuti.</div>';
    } else {
      for ($i = $start_hourly; $i <= $last; $i++):
        $ts = $timestamps[$i] ?? null;
        if (!$ts) continue;
        $time = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        $day = $time->format('Y-m-d');
        $hour = $time->format('H:i');
        // (tutto il tuo ciclo orario esistente qui: intestazione, dati previsione, tooltip ecc.)
      endfor;
    }
    ?>
  </div>
</div>
