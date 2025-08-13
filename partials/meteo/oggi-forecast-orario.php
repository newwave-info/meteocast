<div class="widget" style="padding: 0;">
  <div class="forecast">
    <?php
    // Controlli di sicurezza per TUTTE le variabili necessarie
    $timestamps = $timestamps ?? [];
    $now_index = $now_index ?? 0;
    
    // Fix per scope delle variabili 15min - re-include se necessario
    if (!isset($minutely_15_timestamps) || count($minutely_15_timestamps) == 0) {
        if (file_exists(ROOT_PATH . '/includes/api-forecast-15min.php')) {
            include ROOT_PATH . '/includes/api-forecast-15min.php';
        }
    }
    
    // Le mappe sunrise/sunset dovrebbero già essere definite da oggi-current.php
    
    if (isset($sunset_map)) {
        $today = (new DateTime())->format('Y-m-d');
        $sunset_today = $sunset_map[$today] ?? null;
    }
    
    // Controlli di sicurezza per variabili 15min
    if (!isset($minutely_15_timestamps)) $minutely_15_timestamps = [];
    if (!isset($minutely_15_temperature)) $minutely_15_temperature = [];
    if (!isset($minutely_15_apparent_temperature)) $minutely_15_apparent_temperature = [];
    if (!isset($minutely_15_wind_speed)) $minutely_15_wind_speed = [];
    if (!isset($minutely_15_wind_direction)) $minutely_15_wind_direction = [];
    if (!isset($minutely_15_wind_gusts)) $minutely_15_wind_gusts = [];
    if (!isset($minutely_15_weather_code)) $minutely_15_weather_code = [];
    if (!isset($minutely_15_precipitation)) $minutely_15_precipitation = [];
    
    // Limiti di sicurezza per dati orari
    $num_timestamps = is_array($timestamps) ? count($timestamps) : 0;
    $max_forecast = ($num_timestamps && isset($now_index))
        ? min(FORECAST_HOURS_TODAY ?? 24, $num_timestamps - $now_index)
        : 0;

    if ($num_timestamps < 1) {
        return;
    }

    $prev_day = (new DateTimeImmutable($timestamps[$now_index], new DateTimeZone(TIMEZONE)))->format('Y-m-d');
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));

    ?>

    <!-- Item "Adesso" (current weather) -->
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

    <div class="forecast-item current <?= $isNight ? 'is-night' : '' ?>"
         data-bs-toggle="tooltip"
         data-bs-html="true"
         data-bs-placement="top"
         title="<?= htmlspecialchars($tooltip_now, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="hour">Adesso</div>
        <img src="<?= htmlspecialchars(getWeatherSvgIcon($current_code, $isNight, true)) ?>" class="weather-svg-icon " alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
        <div class="temp"><?= isset($current_temp) ? round($current_temp) . '°' : '-' ?></div>
        <div class="wind-box <?= htmlspecialchars($wind_level) ?> <?= htmlspecialchars($gust_level) ?>">
            <div class="wind-line"><?= isset($current_wind_speed, $current_wind_gusts) ? round($current_wind_speed) . ' / ' . round($current_wind_gusts) : '-' ?></div>
            <?php 
            $wind_icon_now = isset($current_wind_direction) ? getWindDirectionRotation($current_wind_direction) : 0;
            ?>
            <div class="wind-line">
                <i class="wi wi-direction-up" 
                   style="transform: rotate(<?= $wind_icon_now ?>deg);" 
                   title="<?= htmlspecialchars($wind_dir) ?>"></i>
                <span class="wind-dir-text"><?= htmlspecialchars($wind_dir) ?></span>
            </div>
        </div>
    </div>

    <?php
    // === SEZIONE DATI 15MIN ===
    // Controlli di sicurezza per variabili 15min
    $minutely_15_timestamps = $minutely_15_timestamps ?? [];
    $minutely_15_temperature = $minutely_15_temperature ?? [];
    $minutely_15_apparent_temperature = $minutely_15_apparent_temperature ?? [];
    $minutely_15_wind_speed = $minutely_15_wind_speed ?? [];
    $minutely_15_wind_direction = $minutely_15_wind_direction ?? [];
    $minutely_15_wind_gusts = $minutely_15_wind_gusts ?? [];
    $minutely_15_weather_code = $minutely_15_weather_code ?? [];
    $minutely_15_precipitation = $minutely_15_precipitation ?? [];
    
    // Mostra i prossimi dati 15min (escludendo il primo che potrebbe essere troppo vicino al "now")
    $num_15min = is_array($minutely_15_timestamps) ? count($minutely_15_timestamps) : 0;
    
    if ($num_15min > 0) {
        // Trova il primo timestamp 15min che sia nel futuro (anche di 1 minuto)
        $start_15min = 0;
        for ($i = 0; $i < $num_15min; $i++) {
            if (!isset($minutely_15_timestamps[$i])) continue;
            
            $time_15min = new DateTime($minutely_15_timestamps[$i], new DateTimeZone(TIMEZONE));
            $diff_minutes = ($time_15min->getTimestamp() - $now->getTimestamp()) / 60;
            
            
            if ($diff_minutes >= 1) { // Ridotto a 1 minuto
                $start_15min = $i;
                break;
            }
        }
        
        // Mostra TUTTI gli slot 15min disponibili invece di limitare a 8
        $max_15min_slots = $num_15min - $start_15min;
        
        for ($i = $start_15min; $i < $start_15min + $max_15min_slots; $i++) {
            if (!isset($minutely_15_timestamps[$i])) continue;
            
            $time_15min = new DateTime($minutely_15_timestamps[$i], new DateTimeZone(TIMEZONE));
            $hour_15min = $time_15min->format('H:i');
            
            // Dati 15min
            $temp_15 = isset($minutely_15_temperature[$i]) ? round($minutely_15_temperature[$i]) : null;
            $apparent_15 = isset($minutely_15_apparent_temperature[$i]) ? round($minutely_15_apparent_temperature[$i]) : null;
            $wind_speed_15 = isset($minutely_15_wind_speed[$i]) ? round($minutely_15_wind_speed[$i]) : null;
            $wind_dir_15 = isset($minutely_15_wind_direction[$i]) ? getWindDirection($minutely_15_wind_direction[$i]) : '-';
            $gust_15 = isset($minutely_15_wind_gusts[$i]) ? round($minutely_15_wind_gusts[$i]) : null;
            $code_15 = $minutely_15_weather_code[$i] ?? null;
            $precip_15 = $minutely_15_precipitation[$i] ?? null;
            
            // Determina se è notte per questo orario
            $sunrise = $sunrise_map[$time_15min->format('Y-m-d')] ?? null;
            $sunset = $sunset_map[$time_15min->format('Y-m-d')] ?? null;
            $is_night_15 = false;
            if ($sunrise && $sunset) {
                $is_night_15 = ($time_15min < $sunrise || $time_15min >= $sunset);
            }
            
            $wind_level_15 = $wind_speed_15 !== null ? getWindUnifiedLevel($wind_speed_15) : '';
            $gust_level_15 = $gust_15 !== null ? getWindUnifiedLevel($gust_15) : '';
            $night_class_15 = $is_night_15 ? 'is-night' : '';
            
            // Tooltip per 15min
            $desc_15 = $code_15 !== null ? getWeatherDescription($code_15) : '';
            $wind_name_15 = $wind_speed_15 !== null ? getWindLabel($wind_speed_15) : '';
            $show_gust_15 = ($gust_15 !== null && $wind_speed_15 !== null && abs($gust_15 - $wind_speed_15) > 5);
            
            $tooltip_15 = "<strong>$desc_15</strong><br><small>"
                . "$wind_name_15 " . ($wind_speed_15 ?? '-') . " km/h - $wind_dir_15";
            if ($show_gust_15) {
                $tooltip_15 .= "<br>Raffiche $gust_15 km/h";
            }
            if ($precip_15 !== null && $precip_15 >= 0.1) {
                $tooltip_15 .= "<br>Precipitazioni: " . round($precip_15, 1) . " mm";
            }
            if ($apparent_15 !== null && $temp_15 !== null && abs($apparent_15 - $temp_15) >= 1) {
                $tooltip_15 .= "<br>Percepita " . $apparent_15 . "°";
            }
            $tooltip_15 .= "</small>";
            ?>

            <div class="forecast-item forecast-15min <?= $night_class_15 ?>"
                 data-bs-toggle="tooltip"
                 data-bs-html="true"
                 data-bs-placement="top"
                 title="<?= htmlspecialchars($tooltip_15, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <div class="hour"><?= htmlspecialchars($hour_15min) ?></div>
                <img src="<?= htmlspecialchars(getWeatherSvgIcon($code_15, $is_night_15, true)) ?>" class="weather-svg-icon" alt="<?= htmlspecialchars($desc_15) ?>" loading="lazy" />
                <div class="temp"><?= isset($temp_15) ? htmlspecialchars($temp_15) . '°' : '-' ?></div>
                <div class="wind-box <?= htmlspecialchars($wind_level_15) ?> <?= htmlspecialchars($gust_level_15) ?>">
                    <div class="wind-line"><?= ($wind_speed_15 ?? '-') ?> / <?= ($gust_15 ?? '-') ?></div>
                    <?php 
                    $wind_icon_15 = isset($minutely_15_wind_direction[$i]) ? getWindDirectionRotation($minutely_15_wind_direction[$i]) : 0;
                    ?>
                    <div class="wind-line">
                        <i class="wi wi-direction-up" 
                           style="transform: rotate(<?= $wind_icon_15 ?>deg);" 
                           title="<?= htmlspecialchars($wind_dir_15) ?>"></i>
                        <span class="wind-dir-text"><?= htmlspecialchars($wind_dir_15) ?></span>
                    </div>
                </div>
            </div>

            <!-- Overlay Alba/Tramonto per slot 15min -->
            <?php
            $next_time_15min = clone $time_15min;
            $next_time_15min->add(new DateInterval('PT15M')); // +15 minuti
            $day_15min = $time_15min->format('Y-m-d');
            $sunrise_15 = $sunrise_map[$day_15min] ?? null;
            $sunset_15 = $sunset_map[$day_15min] ?? null;
            
            // Debug per capire cosa succede
            if ($time_15min->format('H:i') == '20:30') {
                echo "<!-- DEBUG SUNSET SLOT 20:30: sunset_15=" . ($sunset_15 ? $sunset_15->format('H:i') : 'NULL') . 
                     ", time_15min=" . $time_15min->format('H:i') . 
                     ", next_time=" . $next_time_15min->format('H:i') . " -->";
            }
            
            $show_sunrise_15 = $sunrise_15 && $sunrise_15 >= $time_15min && $sunrise_15 < $next_time_15min;
            $show_sunset_15 = $sunset_15 && $sunset_15 >= $time_15min && $sunset_15 < $next_time_15min;

            if ($show_sunrise_15 || $show_sunset_15):
                $event_time_15 = $show_sunrise_15 ? $sunrise_15 : $sunset_15;
                $event_icon_15 = $show_sunrise_15 ? 'wi-sunrise' : 'wi-sunset';
                $event_label_15 = $show_sunrise_15 ? 'Alba' : 'Tramonto';
                $event_class_15 = $show_sunrise_15 ? 'alba-icon' : 'tramonto-icon';
                $event_type_class_15 = $show_sunrise_15 ? 'alba' : 'tramonto';
                ?>
                <div class="forecast-item <?= $event_type_class_15 ?>">
                    <div class="hour"><?= htmlspecialchars($event_time_15->format('H:i')) ?></div>
                    <img src="<?= htmlspecialchars(getWeatherSvgFromClass($event_icon_15)) ?>" class="weather-svg-icon <?= $event_class_15 ?>" alt="<?= htmlspecialchars($event_label_15) ?>" loading="lazy" />
                    <div class="temp">&nbsp;</div>
                    <div class="wind-box">&nbsp;</div>
                </div>
            <?php endif; ?>
        <?php
        }
    }
    ?>

    <?php
    // === SEZIONE DATI ORARI ===
    // Inizia dai dati orari subito dopo l'ultimo slot 15min
    $hourly_start_time = clone $now;
    
    // Se abbiamo dati 15min, partiamo dall'ultimo + 15min per continuità
    if ($num_15min > 0 && isset($minutely_15_timestamps[$num_15min - 1])) {
        $last_15min = new DateTime($minutely_15_timestamps[$num_15min - 1], new DateTimeZone(TIMEZONE));
        $hourly_start_time = $last_15min->add(new DateInterval('PT15M')); // +15min dall'ultimo 15min
    } else {
        // Fallback: se non ci sono dati 15min, inizia tra 1 ora
        $hourly_start_time->add(new DateInterval('PT1H'));
    }
    
    // Trova il primo indice orario valido
    $hourly_start_index = $now_index + 1;
    for ($i = $now_index + 1; $i < $num_timestamps; $i++) {
        $ts = $timestamps[$i] ?? null;
        if (!$ts) continue;
        
        $time_hourly = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        
        if ($time_hourly >= $hourly_start_time) {
            $hourly_start_index = $i;
            break;
        }
    }
    
    $last = $now_index + $max_forecast - 1;
    for ($i = $hourly_start_index; $i <= $last; $i++):
        $ts = $timestamps[$i] ?? null;
        if (!$ts) continue;
        $time = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        $day = $time->format('Y-m-d');
        $hour = $time->format('H:i');

        // Intestazione cambio giorno
        if ($day !== $prev_day):
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
        endif;

        // Dati previsione orari (stesso codice originale)
        $temp = isset($hourly_temperature[$i]) ? round($hourly_temperature[$i]) : null;
        $apparent = $hourly_apparent_temperature[$i] ?? null;
        $wind_speed = isset($hourly_wind_speed[$i]) ? round($hourly_wind_speed[$i]) : null;
        $wind = $wind_speed ?? '-';
        $wind_dir_val = isset($hourly_wind_direction[$i]) ? getWindDirection($hourly_wind_direction[$i]) : '-';
        $gust_speed = isset($hourly_wind_gusts[$i]) ? round($hourly_wind_gusts[$i]) : null;
        $gust = $gust_speed ?? '-';
        $code = $hourly_weather_codes[$i] ?? null;
        $precip = $hourly_precip[$i] ?? null;
        $humidity = $hourly_humidity[$i] ?? null;
        $dew = $hourly_dew_point[$i] ?? null;

        // Semaforo comfort, motivazione comfort (se già calcolati orari)
        $show_comfort = $comfort[$i] ?? 'dot-green';
        $comfort_motivo = $comfort_reasons[$i][0] ?? null;

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

        // Tooltip intelligente (stesso codice originale)
        $show_gust = ($gust_speed !== null && $wind_speed !== null && abs($gust_speed - $wind_speed) > 5);
        $desc = $code !== null ? getWeatherDescription($code) : '';
        $wind_name = $wind_speed !== null ? getWindLabel($wind_speed) : '';
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
            $extra_lines[] = "Afa (punto di rugiada " . round($dew) . "°)";
        }
        // Vento sostenuto solo se >= 25 km/h
        if ($wind_speed !== null && $wind_speed >= 25) {
            $extra_lines[] = "Vento sostenuto (" . round($wind_speed) . " km/h)";
        }
        // Raffiche forti solo se >= 40 km/h
        if ($gust_speed !== null && $gust_speed >= 40) {
            $extra_lines[] = "Raffiche forti (" . round($gust_speed) . " km/h)";
        }
        // Comfort semaforo: solo se giallo/rosso, mostra motivo
        if (in_array($show_comfort, ['dot-yellow-light', 'dot-yellow-dark', 'dot-red']) && $comfort_motivo) {
            $emoji = [
                'dot-yellow-light' => '🟡',
                'dot-yellow-dark'  => '🟠',
                'dot-red'          => '🔴',
            ][$show_comfort] ?? '🟡';
            $extra_lines[] = "$emoji " . strip_tags($comfort_motivo);
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
                <?php 
                $wind_icon = isset($hourly_wind_direction[$i]) ? getWindDirectionRotation($hourly_wind_direction[$i]) : 0;
                ?>
                <div class="wind-line">
                    <i class="wi wi-direction-up" 
                       style="transform: rotate(<?= $wind_icon ?>deg);" 
                       title="<?= htmlspecialchars($wind_dir_val) ?>"></i>
                    <span class="wind-dir-text"><?= htmlspecialchars($wind_dir_val) ?></span>
                </div>
            </div>
        </div>

        <!-- Overlay Alba/Tramonto (stesso codice originale) -->
        <?php
        $next_time = $time->modify('+1 hour');
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
        <?php endif; ?>
    <?php endfor; ?>

  </div>
</div>