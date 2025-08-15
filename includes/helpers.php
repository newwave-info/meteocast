<?php
/**
 * ----------------------------------------------------------------------------
 *  helpers.php – Utility meteo, icone, descrizioni, semafori comfort/safety, narrativa
 * ----------------------------------------------------------------------------
 *
 * Ruolo:
 *   – Mappa codici meteo Open-Meteo → icone, descrizioni e SVG (sia day che night)
 *   – Espone funzioni per:
 *       • Restituire l’icona/meteo/descrizione CSS giusta (day/night/custom class)
 *       • Produrre narrative testuali e alert meteo intelligenti (per fasce orarie)
 *       • Calcolare etichette comfort/safety (semafori) e motivazioni per grafici
 *       • Utility per orari, vento, temperatura, punto di rugiada, luna, ecc.
 *
 * Funzioni principali:
 *   – getWeatherIcon(), getWeatherClass(), getWeatherDescription(), getWeatherSvgIcon()
 *   – getFriendlyUpdateTimeFromDateTime(), getWindDirection(), getMoonPhase()
 *   – getAlertStatus(), buildMeteoBox(), getUnifiedMeteoAlert(), generateNarrativeParagraphUnified()
 *   – getTrafficLightLevels(): calcolo livelli safety/comfort + ragioni
 *   – calculateDewPoint(): punto di rugiada orario da T e RH
 *
 * Mapping icone:
 *   – WEATHER_ICON_CLASSES_DAY / NIGHT:  codici → classi weather-icons
 *   – WEATHER_SVG_DAY / NIGHT:           codici → SVG animati
 *   – WI_TO_SVG_ICON:                    compat vecchie classi → SVG
 *
 * Policy di estensibilità:
 *   – Aggiungi/espandi mapping semplicemente nelle costanti in testa al file
 *   – Tutte le funzioni sono autonome e “safe” (fallback se il dato manca)
 *
 * Performance/cache:
 *   – Nessuna cache disco: solo mapping in memoria a runtime
 *   – Le funzioni sono pensate per uso intensivo nei template
 *
 * Output:
 *   – Stringhe pronte per HTML/CSS/JS, array associativi, label e commenti
 *   – Narrative meteo pronte da mostrare nei box principali/grafici
 *
 * Sicurezza:
 *   – Nessuna esecuzione di codice lato utente, tutti i dati sono puliti
 *   – Tutto codice PHP puro, senza dipendenze esterne
 *
 * Nota:
 *   – Se aggiorni codici Open-Meteo o vuoi nuove icone, aggiorna solo i mapping
 * ----------------------------------------------------------------------------
 */


declare(strict_types=1);

// =============================
// COSTANTI MAPPING METEO/ICONE
// =============================
const WEATHER_ICON_CLASSES_DAY = [
    0 => "wi-day-sunny", 1 => "wi-day-sunny-overcast", 2 => "wi-day-cloudy", 3 => "wi-cloudy",
    45 => "wi-fog", 48 => "wi-fog", 51 => "wi-showers", 53 => "wi-showers", 55 => "wi-showers",
    56 => "wi-rain-mix", 57 => "wi-rain-mix", 61 => "wi-rain", 63 => "wi-rain", 65 => "wi-rain",
    66 => "wi-rain-mix", 67 => "wi-rain-mix", 71 => "wi-snow", 73 => "wi-snow", 75 => "wi-snow",
    77 => "wi-snowflake-cold", 80 => "wi-showers", 81 => "wi-showers", 82 => "wi-showers",
    85 => "wi-snow", 86 => "wi-snow", 95 => "wi-thunderstorm",
    96 => "wi-thunderstorm", 99 => "wi-thunderstorm"
];
const WEATHER_ICON_CLASSES_NIGHT = [
    0 => "wi-night-clear", 1 => "wi-night-alt-partly-cloudy", 2 => "wi-night-alt-cloudy", 3 => "wi-cloudy",
    45 => "wi-fog", 48 => "wi-fog", 51 => "wi-showers", 53 => "wi-showers", 55 => "wi-showers",
    56 => "wi-rain-mix", 57 => "wi-rain-mix", 61 => "wi-rain", 63 => "wi-rain", 65 => "wi-rain",
    66 => "wi-rain-mix", 67 => "wi-rain-mix", 71 => "wi-snow", 73 => "wi-snow", 75 => "wi-snow",
    77 => "wi-snowflake-cold", 80 => "wi-showers", 81 => "wi-showers", 82 => "wi-showers",
    85 => "wi-snow", 86 => "wi-snow", 95 => "wi-night-alt-thunderstorm",
    96 => "wi-night-alt-thunderstorm", 99 => "wi-night-alt-thunderstorm"
];
const WI_TO_SVG_ICON = [
    'wi-sunrise' => 'climacon-sunrise.svg',
    'wi-sunset' => 'climacon-sunset.svg',
    'wi-moon-full' => 'climacon-moon-full.svg',
    // ... aggiungine altri se vuoi
];
const WEATHER_DESCRIPTIONS = [
    0 => "Sereno", 1 => "Prevalentemente sereno", 2 => "Parzialmente nuvoloso", 3 => "Coperto",
    45 => "Nebbia", 48 => "Nebbia con brina", 51 => "Pioggerella leggera", 53 => "Pioggerella moderata",
    55 => "Pioggerella intensa", 56 => "Pioggerella gelata leggera", 57 => "Pioggerella gelata intensa",
    61 => "Pioggia leggera", 63 => "Pioggia moderata", 65 => "Pioggia intensa",
    66 => "Pioggia gelata leggera", 67 => "Pioggia gelata intensa", 71 => "Neve leggera",
    73 => "Neve moderata", 75 => "Neve intensa", 77 => "Nevischio", 80 => "Rovesci leggeri",
    81 => "Rovesci moderati", 82 => "Rovesci forti", 85 => "Rovesci di neve leggeri",
    86 => "Rovesci di neve forti", 95 => "Temporale", 96 => "Temporale con grandine",
    99 => "Temporale violento con grandine"
];

const WEATHER_SVG_DAY = [
    0  => "clear-day.svg",
    1  => "clear-day.svg",
    2  => "partly-cloudy-day.svg",
    3  => "overcast-day.svg",

    45 => "fog-day.svg",
    48 => "extreme-day-fog.svg",

    51 => "partly-cloudy-day-drizzle.svg",
    53 => "overcast-day-drizzle.svg",
    55 => "extreme-day-drizzle.svg",

    56 => "partly-cloudy-day-sleet.svg",     // Esiste come "freezing-drizzle.svg"
    57 => "overcast-day-sleet.svg",

    61 => "partly-cloudy-day-rain.svg",
    63 => "overcast-day-rain.svg",
    65 => "extreme-day-rain.svg",

    66 => "partly-cloudy-day-sleet.svg",        // Esiste come "freezing-rain.svg"
    67 => "extreme-day-sleet.svg",

    71 => "partly-cloudy-day-snow.svg",
    73 => "overcast-day-snow.svg",
    75 => "extreme-day-snow.svg",

    77 => "overcast-day-sleet.svg",            // Esiste come "snowflake.svg"
    80 => "partly-cloudy-day-rain.svg",              // Esiste come "showers.svg"
    81 => "overcast-day-rain.svg",
    82 => "extreme-day-rain.svg",

    85 => "partly-cloudy-day-snow.svg",         // Esiste come "snow-showers.svg"
    86 => "extreme-day-snow.svg",

    95 => "thunderstorms-day.svg",    // Esiste come "thunderstorms-day.svg"
    96 => "thunderstorms-day-rain.svg",   // Esiste come "thunderstorms-day-hail.svg"
    99 => "thunderstorms-day-extreme-rain.svg" // Esiste come "thunderstorms-day-extreme.svg"
];

const WEATHER_SVG_NIGHT = [
    0  => "starry-night.svg",
    1  => "clear-night.svg",
    2  => "partly-cloudy-night.svg",
    3  => "overcast-night.svg",

    45 => "fog-night.svg",
    48 => "extreme-night-fog.svg",

    51 => "partly-cloudy-night-drizzle.svg",              // Non c'è variante night, si usa generico
    53 => "overcast-night-drizzle.svg",
    55 => "extreme-night-drizzle.svg",

    56 => "partly-cloudy-night-sleet.svg",     // Come sopra
    57 => "overcast-night-sleets.svg",

    61 => "partly-cloudy-night-rain.svg",
    63 => "overcast-night-rain.svg",
    65 => "extreme-night-rain.svg",

    66 => "partly-cloudy-night-sleet.svg",
    67 => "extreme-night-sleet.svg",

    71 => "partly-cloudy-night-snow.svg",
    73 => "overcast-night-snow.svg",
    75 => "extreme-night-snow.svg",

    77 => "overcast-night-sleet.svg",
    80 => "partly-cloudy-night-rain.svg",
    81 => "overcast-night-rain.svg",
    82 => "extreme-night-rain.svg",

    85 => "partly-cloudy-night-snow.svg",
    86 => "extreme-night-snow.svg",

    95 => "thunderstorms-night.svg",        // Esiste come "thunderstorms-night.svg"
    96 => "thunderstorms-night-rain.svg",   // Esiste come "thunderstorms-night-hail.svg"
    99 => "thunderstorms-night-extreme-rain.svg" // Esiste come "thunderstorms-night-extreme.svg"
];


function getWeatherSvgIcon(int $code, bool $isNight = false, bool $animated = true): string {
    // Scegli il mapping giusto
    $map = $isNight ? WEATHER_SVG_NIGHT : WEATHER_SVG_DAY;
    // Prendi il file corrispondente, o un fallback
    $filename = $map[$code] ?? 'not-available.svg';
    // Scegli la cartella giusta (animata/statica)
    $folder = $animated ? 'assets/icons/svg/' : 'assets/icons/svg-static/';
    // Costruisci il percorso completo
    return $folder . $filename;
}


function getWeatherSvgFromClass($wi_class) {
    // Mapping dei tuoi vecchi wi-XX con il file SVG nuovo
    return [
        'wi-sunrise' => 'assets/icons/svg/sunrise.svg',
        'wi-sunset'  => 'assets/icons/svg/sunset.svg'
    ][$wi_class] ?? 'assets/icons/svg-static/not-available.svg';
}



/**
 * Converte i gradi di direzione vento in icona Weather Icons
 * @param float $degrees Gradi direzione vento (0-360)
 * @return string Classe icona Weather Icons
 */
function getWindDirectionRotation(float $degrees): float {
    return ($degrees + 180) % 360;
}

// =============================
// ORA AGGIORNAMENTO FRIENDLY
// =============================

/**
 * Ritorna una stringa "friendly" relativa all'ultimo aggiornamento.
 */
function getFriendlyUpdateTimeFromDateTime(DateTimeInterface $dt_update, DateTimeInterface $now): string {
    $diff = $now->getTimestamp() - $dt_update->getTimestamp();

    if ($diff < 60) {
        return "Aggiornato ora";
    } elseif ($diff < 3600) {
        return "Aggiornato " . floor($diff / 60) . " min fa";
    }

    $oggi = $now->format('Y-m-d');
    $ieri = (clone $now)->modify('-1 day')->format('Y-m-d');
    $data = $dt_update->format('Y-m-d');
    $ora = $dt_update->format('H:i');

    if ($data === $oggi) {
        return "Aggiornato oggi alle $ora";
    } elseif ($data === $ieri) {
        return "Aggiornato ieri alle $ora";
    } else {
        return "Aggiornato il " . $dt_update->format('d/m/Y') . " alle $ora";
    }
}


// =============================
// UTILITY FINESTRA FORECAST
// =============================

/**
 * Restituisce gli indici delle ore comprese nella finestra richiesta.
 *
 * ▸ Se passi $hoursAhead → [now, now + hoursAhead]  
 * ▸ Se passi $start e $end → [start, end] (assoluti)  
 * ▸ Se nessun parametro → usa DEFAULT_HOURS_TODAY come fallback per “oggi”.
 *
 * Il risultato è ordinato cronologicamente e può essere campionato
 * (stepHours = 1 ⇒ tutte le ore, 3 ⇒ tri-orario, ecc.).
 *
 * @param array              $timestamps   Array ISO/UTC proveniente da Open-Meteo.
 * @param DateTimeInterface  $now          Ora corrente (Time-zone già impostata).
 * @param ?int               $hoursAhead   Ore in avanti da includere (relativo).
 * @param ?DateTimeInterface $start        Inizio assoluto finestra (alternativo).
 * @param ?DateTimeInterface $end          Fine assoluta finestra (richiesto se $start).
 * @param int                $stepHours    Campionamento (1 = tutte, 3 = ogni 3 ore…).
 * @return int[]                           Indici validi nell’array orario globale.
 */
function getForecastWindowIndices(
    array $timestamps,
    DateTimeInterface $now,
    ?int $hoursAhead = null,
    ?DateTimeInterface $start = null,
    ?DateTimeInterface $end   = null,
    int $stepHours = 1
): array {

    $tz = $now->getTimezone();

    // --- Determina la finestra temporale ----------------------------------
    if ($start && $end) {
        // Assoluta
        $windowStart = (clone $start)->setTimezone($tz);
        $windowEnd   = (clone $end)->setTimezone($tz);
    } else {
        // Relativa (+N ore)  — se N è null usa la costante di fallback
        $hours = $hoursAhead
        ?? ( ($now->format('Y-m-d') === (new DateTimeImmutable('now', $tz))->format('Y-m-d'))
                 ? DEFAULT_HOURS_TODAY   // fallback “oggi”
                 : 24 );                 // per altri giorni, default 24 h complete

        $windowStart = $now;
        $windowEnd   = (clone $now)->modify("+{$hours} hours");
    }

    // --- Estrae indici che ricadono nella finestra ------------------------
    $indices = [];
    foreach ($timestamps as $i => $ts) {
        $dt = new DateTimeImmutable($ts, $tz);
        if ($dt >= $windowStart && $dt <= $windowEnd) {
            $indices[] = $i;
        }
    }

    // --- Campionamento orario ---------------------------------------------
    if ($stepHours > 1 && count($indices) > 1) {
        $firstIdx = $indices[0];
        $indices = array_values(array_filter(
            $indices,
            fn ($i) => ( ($i - $firstIdx) % $stepHours ) === 0
        ));
    }

    return $indices;
}




// =============================
// UTILITÀ GENERALI
// =============================

/**
 * Converte i gradi in direzione del vento testuale.
 */
function getWindDirection(float $degrees): string {
    $directions = [
        'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
        'S', 'SSO', 'SO', 'OSO', 'O', 'ONO', 'NO', 'NNO'
    ];
    $index = (int) round(($degrees % 360) / 22.5) % 16;
    return $directions[$index];
}

/**
 * Ritorna l'emoji di un semaforo/meteo a seconda della classe.
 */
function getColorCode(string $class): string {
    return match ($class) {
        'dot-green' => '🟢',
        'dot-yellow' => '🟡',
        'dot-orange' => '🟠',
        'dot-red' => '🔴',
        default => '🟢'
    };
}

/**
 * Restituisce il livello del vento su scala custom.
 */
function getWindUnifiedLevel(float $speed): string {
    return match (true) {
        $speed < 4    => 'lvl-00',
        $speed < 11   => 'lvl-04',
        $speed < 18   => 'lvl-11',
        $speed < 25   => 'lvl-18',
        $speed < 32   => 'lvl-25',
        $speed < 40   => 'lvl-32',
        $speed < 47   => 'lvl-40',
        $speed < 54   => 'lvl-47',
        $speed < 61   => 'lvl-54',
        $speed < 68   => 'lvl-61',
        $speed < 76   => 'lvl-68',
        $speed < 86   => 'lvl-76',
        $speed < 97   => 'lvl-86',
        $speed < 104  => 'lvl-97',
        $speed < 130  => 'lvl-104',
        default       => 'lvl-130'
    };
}



/**
 * Restituisce un'etichetta per il livello del vento.
 */
function getWindLabel(float $speed): string {
    return match (true) {
        $speed < 4    => 'Calma piatta',
        $speed < 11   => 'Brezza leggera',
        $speed < 18   => 'Brezza tesa',
        $speed < 25   => 'Moderato',
        $speed < 32   => 'Teso',
        $speed < 40   => 'Molto teso',
        $speed < 47   => 'Forte',
        $speed < 54   => 'Molto forte',
        $speed < 61   => 'Molto forte e rafficato',
        $speed < 68   => 'Burrasca debole',
        $speed < 76   => 'Burrasca',
        $speed < 86   => 'Burrasca forte',
        $speed < 97   => 'Tempesta',
        $speed < 104  => 'Tempesta violenta',
        $speed < 130  => 'Tempesta estrema',
        default       => 'Condizioni estreme'
    };
}

// =============================
// METEO - ICONE & DESCRIZIONI
// =============================

/**
 * Restituisce la classe icona meteo.
 */
function getWeatherIcon(int $code, bool $isNight = false): string {
    $map = $isNight ? WEATHER_ICON_CLASSES_NIGHT : WEATHER_ICON_CLASSES_DAY;
    return $map[$code] ?? 'wi-na';
}

/**
 * Restituisce la descrizione testuale del meteo.
 */
function getWeatherDescription(int $code): string {
    return WEATHER_DESCRIPTIONS[$code] ?? 'Condizioni variabili';
}

/**
 * Restituisce la classe CSS custom per il meteo.
 */
function getWeatherClass(int $code, bool $isNight = false): string {
    if (in_array($code, [0, 1])) return ($isNight ? 'is-night ' : '') . 'weather-sun';
    if (in_array($code, [2, 3, 45, 48])) return ($isNight ? 'is-night ' : '') . 'weather-cloud';
    if (in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82])) return ($isNight ? 'is-night ' : '') . 'weather-rain';
    if (in_array($code, [71, 73, 75, 77, 85, 86])) return ($isNight ? 'is-night ' : '') . 'weather-snow';
    if (in_array($code, [95, 96, 99])) return ($isNight ? 'is-night ' : '') . 'weather-storm';
    return '';
}


// =============================
// ALERT METEO
// =============================

/**
 * Ritorna true se bisogna escludere le ore passate (solo per oggi, prima delle 23).
 */
function shouldExcludePastHours(string $targetDate, DateTimeZone $tz): bool {
    $now = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $hour = (int)$now->format('H');

    // Solo per oggi, e solo prima delle 23
    return $targetDate === $today && $hour < 23;
}

/**
 * Calcola lo stato di allerta meteo (danger/warning/ok) e i motivi.
 *
 */
/**
 * Calcola lo stato di allerta meteo MIGLIORATO (danger/warning/ok) e i motivi.
 * Evita ridondanze e genera messaggi più precisi e contestualizzati.
 *
 * @return array{type:string, icon:string, lines:array}
 */
function getAlertStatus(
    float $wind_speed,
    float $wind_gusts,
    array $precip_mm,
    array $precip_prob,
    float $wind_direction,
    float $humidity,
    float $uv_index,
    array $visibility,
    float $pressure,
    bool $isNight,
    DateTimeInterface $now,
    string $valid_from = '',
    string $valid_to = ''
): array {
    $dir_text = getWindDirection($wind_direction);

    // Slice e safe check su array
    $slice_precip = array_slice($precip_mm, 0, 6);
    $slice_prob = array_slice($precip_prob, 0, 6);
    $slice_visib = array_slice($visibility, 0, 6);

    $max_precip = max($slice_precip ?: [0]);
    $avg_precip = round(array_sum($slice_precip) / max(1, count($slice_precip)), 1);
    $max_prob = max($slice_prob ?: [0]);
    $low_visibility = min($slice_visib ?: [99999]);
    $visib_km = round($low_visibility / 1000, 1);

    $interval = $valid_from && $valid_to ? "$valid_from - $valid_to" : "";
    $isToday = $now->format('Y-m-d') === (new DateTimeImmutable('now', $now->getTimezone()))->format('Y-m-d');

    // === ANALISI FATTORI DI RISCHIO ===
    $risk_factors = [];
    $warning_factors = [];
    
    // VENTO E RAFFICHE - Analisi combinata
    if ($wind_gusts >= 60) {
        $risk_factors[] = "Raffiche violente fino a " . round($wind_gusts) . " km/h da {$dir_text}";
    } elseif ($wind_gusts >= 45) {
        $risk_factors[] = "Raffiche forti fino a " . round($wind_gusts) . " km/h da {$dir_text}";
    } elseif ($wind_speed >= 35 || $wind_gusts >= 35) {
        $warning_factors[] = "Vento forte: " . round($wind_speed) . " km/h con raffiche " . round($wind_gusts) . " km/h";
    } elseif ($wind_speed >= 25 || $wind_gusts >= 30) {
        $warning_factors[] = "Vento sostenuto da {$dir_text}: " . round($wind_speed) . "-" . round($wind_gusts) . " km/h";
    }

    // PRECIPITAZIONI - Analisi intensità e probabilità
    if ($max_precip >= 8) {
        $risk_factors[] = "Pioggia molto intensa: previsti " . round($max_precip, 1) . " mm/h";
    } elseif ($max_precip >= 5) {
        $risk_factors[] = "Pioggia intensa: previsti " . round($max_precip, 1) . " mm/h";
    } elseif ($max_precip >= 3 && $max_prob >= 70) {
        $warning_factors[] = "Pioggia moderata molto probabile: " . round($max_precip, 1) . " mm/h";
    } elseif ($max_precip >= 2 && $max_prob >= 60) {
        $warning_factors[] = "Possibili rovesci: fino a " . round($max_precip, 1) . " mm";
    } elseif ($max_prob >= 80 && $avg_precip >= 1) {
        $warning_factors[] = "Piogge diffuse molto probabili";
    }

    // VISIBILITÀ
    if ($low_visibility < 500) {
        $risk_factors[] = "Visibilità gravemente ridotta: sotto " . $visib_km . " km";
    } elseif ($low_visibility < 1500) {
        $warning_factors[] = "Visibilità ridotta: circa " . $visib_km . " km";
    }

    // PRESSIONE - Solo se combinata con altri fattori
    if ($pressure < 995 && (!empty($risk_factors) || !empty($warning_factors))) {
        $warning_factors[] = "Pressione molto bassa: " . round($pressure) . " hPa";
    } elseif ($pressure < 1005 && $max_precip >= 2) {
        $warning_factors[] = "Pressione in calo: " . round($pressure) . " hPa";
    }

    // UV - Solo di giorno
    if (!$isNight) {
        if ($uv_index >= 8) {
            $warning_factors[] = "Indice UV molto elevato: " . round($uv_index, 1);
        } elseif ($uv_index >= 6.5) {
            $warning_factors[] = "Indice UV elevato: protezione solare raccomandata";
        }
    }

    // === DETERMINA LIVELLO ALLERTA ===
    
    // DANGER - Condizioni critiche
    if (!empty($risk_factors)) {
        $headline = $isToday 
        ? pick([
            "Allerta meteo in corso",
            "Condizioni critiche nelle prossime ore",
            "Situazione meteorologica avversa"
        ])
        : pick([
            "Allerta meteo per la giornata", 
            "Condizioni critiche previste",
            "Giornata con meteo avverso"
        ]);

        return [
            'type' => 'danger',
            'icon' => $isNight ? 'bi-cloud-moon' : 'bi-exclamation-triangle-fill',
            'lines' => array_merge(
                [$headline],
                $interval ? [$interval] : [],
                $risk_factors,
                array_slice($warning_factors, 0, 2) // Max 2 fattori aggiuntivi
            )
        ];
    }

    // WARNING - Condizioni da monitorare  
    if (!empty($warning_factors)) {
        $headline = $isToday
        ? pick([
            "Condizioni da monitorare oggi",
            "Situazione meteo variabile", 
            "Possibili disturbi meteorologici"
        ])
        : pick([
            "Giornata da seguire",
            "Meteo variabile previsto",
            "Possibili fenomeni isolati"
        ]);

        // Prioritizza i fattori più importanti
        $sorted_factors = [];
        foreach ($warning_factors as $factor) {
            if (stripos($factor, 'vento') !== false || stripos($factor, 'raffiche') !== false) {
                array_unshift($sorted_factors, $factor); // Vento in primo piano
            } elseif (stripos($factor, 'pioggia') !== false || stripos($factor, 'rovesci') !== false) {
                array_unshift($sorted_factors, $factor); // Pioggia importante
            } else {
                $sorted_factors[] = $factor; // Altri fattori dopo
            }
        }

        return [
            'type' => 'warning', 
            'icon' => $isNight ? 'bi-cloud-moon' : 'bi-exclamation-triangle',
            'lines' => array_merge(
                [$headline],
                $interval ? [$interval] : [],
                array_slice($sorted_factors, 0, 3) // Max 3 fattori
            )
        ];
    }

    // OK - Nessuna criticità
    $headline = $isNight
    ? pick([
        'Notte serena e tranquilla', 
        'Condizioni notturne stabili',
        'Meteo favorevole nella notte'
    ])
    : pick($isToday
        ? [
            'Ottime condizioni per oggi',
            'Giornata con meteo favorevole', 
            'Tempo ideale per attività all\'aperto'
        ]
        : [
            'Previsioni favorevoli',
            'Domani tempo stabile',
            'Condizioni meteo ideali'
        ]
    );

    return [
        'type' => 'ok',
        'icon' => $isNight ? 'bi-moon-stars-fill' : 'bi-sun-fill',
        'lines' => array_merge(
            [$headline],
            $interval ? [$interval] : []
        )
    ];
}


/**
 * Crea una frase unica e coerente di allerta, fondendo ragioni simili.
 * Restituisce array di righe ottimizzate per meteoBox['lines']
 *
 * @param array $reasons  array di motivi di alert generati da getAlertStatus()
 * @param string $type    'danger', 'warning', 'ok'
 * @param string $interval (opzionale) per mostrare l'intervallo orario
 * @return array
 */
function composeAlertLines(array $reasons, string $type, string $interval = ''): array {
    if (empty($reasons)) {
        // Messaggi standard "ok"
        return [];
    }

    // Arrotonda tutti i valori numerici nei motivi di alert
    $reasons = array_map(function($r) {
        // Sostituisci tutti i numeri decimali con interi (in km/h, mm, hPa, ecc.)
        return preg_replace_callback('/(\d+)[\.,](\d+)/', function($m) {
            return (string)round(floatval($m[0]));
        }, $r);
    }, $reasons);

    // Filtra motivi che contengono esattamente l'intervallo come testo separato
    if ($interval) {
        $reasons = array_filter($reasons, function($r) use ($interval) {
            return !preg_match('/^'.preg_quote($interval, '/').'\b/', $r)
            && !preg_match('/\b'.preg_quote($interval, '/').'$/', $r);
        });
    }

    $main = [];
    $venti = [];
    $precip = [];
    $visib = [];
    $uv = [];
    $pressione = [];
    foreach ($reasons as $r) {
        if (stripos($r, 'raffiche') !== false || stripos($r, 'vento') !== false) $venti[] = $r;
        elseif (stripos($r, 'pioggia') !== false || stripos($r, 'rovesci') !== false) $precip[] = $r;
        elseif (stripos($r, 'visibil') !== false) $visib[] = $r;
        elseif (stripos($r, 'UV') !== false) $uv[] = $r;
        elseif (stripos($r, 'Pressione') !== false) $pressione[] = $r;
        else $main[] = $r;
    }

    // Sintesi "umana"
    $header = match($type) {
        'danger' => '<strong>Allerta meteo</strong>',
        'warning' => '<strong>Condizioni da monitorare</strong>',
        default => '<strong>Meteo favorevole</strong>',
    };

    $phrases = [];
    if ($venti)     $phrases[] = implode('. ', $venti);
    if ($precip)    $phrases[] = implode('. ', $precip);
    if ($visib)     $phrases[] = implode('. ', $visib);
    if ($uv)        $phrases[] = implode('. ', $uv);
    if ($pressione) $phrases[] = implode('. ', $pressione);
    if ($main)      $phrases[] = implode('. ', $main);

    // Metti punto dopo ogni frase (se manca)
    $phrases = array_map(function($p) {
        $p = trim($p);
        if ($p === '') return '';
        // Metti maiuscola all'inizio (dopo punto e spazi)
        $p = ucfirst($p);
        // Assicurati che finisca con un punto
        if (!preg_match('/[.!?]$/', $p)) $p .= '.';
        return $p;
    }, $phrases);

    // Componi il corpo della frase (frasi separate da spazio)
    $body = '';
    if ($phrases) {
        $bodyText = implode(' ', $phrases);
        if ($interval) {
            // Dopo i due punti, la frase prosegue con maiuscola e punto dopo ogni frase
            $body = $interval . ': ' . $bodyText;
        } else {
            $body = $bodyText;
        }
    }

    return [$header, $body];
}



// =============================
// ANALISI INTELLIGENTE BOLLETTINO METEO
// =============================

/**
 * Analizza le condizioni meteo complessive per determinare il "tema dominante"
 * della fascia oraria, evitando contraddizioni nella narrativa.
 * VERSIONE MIGLIORATA con migliore rilevamento temporali e precipitazioni intense.
 */
function analyzeWeatherPattern(array $codes, array $precip, array $precip_prob): array {
    if (empty($codes)) return ['pattern' => 'stable', 'intensity' => 'light', 'confidence' => 0];
    
    // Categorizza i codici meteo
    $clear_codes = [0, 1];
    $cloudy_codes = [2, 3];
    $fog_codes = [45, 48];
    $light_precip = [51, 53, 61, 80];
    $moderate_precip = [55, 63, 81];
    $heavy_precip = [65, 82];
    $storm_codes = [95, 96, 99];
    $snow_codes = [71, 73, 75, 77, 85, 86];
    $freezing_codes = [56, 57, 66, 67];
    
    $patterns = [
        'clear' => 0, 'cloudy' => 0, 'fog' => 0, 'light_rain' => 0, 'moderate_rain' => 0,
        'heavy_rain' => 0, 'storms' => 0, 'snow' => 0, 'freezing' => 0
    ];
    
    // Conta i pattern
    foreach ($codes as $code) {
        if (in_array($code, $storm_codes)) $patterns['storms']++;
        elseif (in_array($code, $heavy_precip)) $patterns['heavy_rain']++;
        elseif (in_array($code, $moderate_precip)) $patterns['moderate_rain']++;
        elseif (in_array($code, $light_precip)) $patterns['light_rain']++;
        elseif (in_array($code, $freezing_codes)) $patterns['freezing']++;
        elseif (in_array($code, $snow_codes)) $patterns['snow']++;
        elseif (in_array($code, $fog_codes)) $patterns['fog']++;
        elseif (in_array($code, $clear_codes)) $patterns['clear']++;
        elseif (in_array($code, $cloudy_codes)) $patterns['cloudy']++;
    }
    
    // Calcola statistiche precipitazioni
    $max_precip = max($precip ?: [0]);
    $avg_precip = count($precip) > 0 ? array_sum($precip) / count($precip) : 0;
    $max_prob = max($precip_prob ?: [0]);
    $avg_prob = count($precip_prob) > 0 ? array_sum($precip_prob) / count($precip_prob) : 0;
    
    // PRIORITÀ GERARCHICA: Temporali e piogge intense hanno precedenza
    $dominant_pattern = 'stable';
    $max_count = 0;
    
    // 1. PRIORITÀ MASSIMA: Temporali
    if ($patterns['storms'] > 0) {
        $dominant_pattern = 'storms';
        $max_count = $patterns['storms'];
    }
    // 2. PRIORITÀ ALTA: Precipitazioni intense (anche senza codice temporale)
    elseif ($patterns['heavy_rain'] > 0 || $max_precip >= 6) {
        $dominant_pattern = 'heavy_rain';
        $max_count = max($patterns['heavy_rain'], 1);
    }
    // 3. PRIORITÀ MEDIA: Altre precipitazioni significative
    elseif ($patterns['moderate_rain'] > 0 || ($max_precip >= 3 && $max_prob >= 60)) {
        $dominant_pattern = 'moderate_rain';
        $max_count = max($patterns['moderate_rain'], 1);
    }
    elseif ($patterns['light_rain'] > 0 || ($max_precip >= 1 && $max_prob >= 70)) {
        $dominant_pattern = 'light_rain';
        $max_count = max($patterns['light_rain'], 1);
    }
    // 4. PRIORITÀ SPECIALI: Neve e gelo
    elseif ($patterns['snow'] > 0) {
        $dominant_pattern = 'snow';
        $max_count = $patterns['snow'];
    }
    elseif ($patterns['freezing'] > 0) {
        $dominant_pattern = 'freezing';
        $max_count = $patterns['freezing'];
    }
    // 5. PRIORITÀ BASSA: Condizioni asciutte
    else {
        // Trova il pattern più comune tra i rimanenti
        foreach (['fog', 'cloudy', 'clear'] as $pattern_type) {
            if ($patterns[$pattern_type] > $max_count) {
                $max_count = $patterns[$pattern_type];
                $dominant_pattern = $pattern_type;
            }
        }
    }
    
    // Determina l'intensità con logica migliorata
    $intensity = 'light';
    if ($patterns['storms'] > 0 || $max_precip >= 8) {
        $intensity = 'extreme';
    } elseif ($patterns['heavy_rain'] > 0 || $max_precip >= 5 || ($max_precip >= 3 && $max_prob >= 80)) {
        $intensity = 'heavy';
    } elseif ($patterns['moderate_rain'] > 0 || $max_precip >= 2 || ($max_precip >= 1 && $max_prob >= 70)) {
        $intensity = 'moderate';
    }
    
    // Calcola la confidenza del pattern (quanto è dominante)
    $total_codes = count($codes);
    $confidence = $total_codes > 0 ? $max_count / $total_codes : 0;
    
    // BOOST confidenza per temporali e piogge intense anche se sporadici
    if ($dominant_pattern === 'storms' || ($dominant_pattern === 'heavy_rain' && $max_precip >= 6)) {
        $confidence = max($confidence, 0.8); // Forza alta confidenza per eventi estremi
    }
    
    return [
        'pattern' => $dominant_pattern, 'intensity' => $intensity, 'confidence' => $confidence,
        'max_precip' => $max_precip, 'avg_precip' => $avg_precip, 'max_prob' => $max_prob,
        'avg_prob' => $avg_prob, 'patterns' => $patterns
    ];
}

/**
 * Genera frasi più coerenti per le condizioni meteorologiche
 * basate sull'analisi del pattern dominante.
 * VERSIONE MIGLIORATA con narrativa notturna e variazioni linguistiche.
 */
function generateSmartWeatherPhrase(array $weather_analysis, bool $isNight): array {
    $pattern = $weather_analysis['pattern'];
    $intensity = $weather_analysis['intensity'];
    $confidence = $weather_analysis['confidence'];
    $max_prob = $weather_analysis['max_prob'];
    
    $phrases = [];
    
    switch ($pattern) {
        case 'clear':
        if ($confidence >= 0.7) {
            $phrases[] = $isNight 
            ? pick([
                'cielo sereno e stellato', 'notte limpida e tranquilla', 'condizioni stabili senza disturbi',
                'atmosfera serena e silenziosa', 'cielo notturno privo di nuvole', 'serenità nelle ore buie'
            ])
            : pick([
                'cielo sereno e soleggiato', 'tempo stabile e asciutto', 'condizioni ideali e luminose',
                'sole protagonista della giornata', 'atmosfera limpida e radiosa', 'bel tempo garantito'
            ]);
        } else {
            $phrases[] = $isNight
            ? pick([
                'prevalentemente sereno con lievi velature', 'schiarite nella notte con qualche nuvola',
                'condizioni generalmente tranquille', 'cielo variabile ma stabile'
            ])
            : pick([
                'tempo variabile con ampie schiarite', 'alternanza di sole e nuvole sparse',
                'condizioni prevalentemente buone', 'clima instabile ma tendenzialmente sereno'
            ]);
        }
        break;

        case 'cloudy':
        if ($confidence >= 0.7) {
            $phrases[] = $isNight
            ? pick([
                'cielo coperto e nuvoloso', 'notte con copertura nuvolosa estesa',
                'atmosfera grigia e ombrosa', 'cielo plumbeo e compatto'
            ])
            : pick([
                'cielo coperto e grigio', 'nuvolosità diffusa e persistente', 
                'copertura nuvolosa estesa', 'atmosfera velata e ombrosa'
            ]);
        } else {
            $phrases[] = pick([
                'nuvolosità variabile e irregolare', 'nubi sparse e discontinue', 
                'cielo parzialmente coperto', 'alternanza di nuvole e schiarite'
            ]);
        }
        break;

        case 'fog':
        $phrases[] = $isNight
        ? pick([
            'nebbia notturna o foschie diffuse', 'atmosfera offuscata e umida',
            'visibilità compromessa da banchi nebbiosi', 'condizioni nebbiose nelle ore buie'
        ])
        : pick([
            'nebbia mattutina o foschia persistente', 'visibilità ridotta da fenomeni nebbiosi',
            'atmosfera velata e umida', 'condizioni di scarsa visibilità'
        ]);
        break;

        case 'light_rain':
        case 'moderate_rain':
        case 'heavy_rain':
        if ($intensity === 'heavy') {
            $rain_desc = pick(['piogge intense e abbondanti', 'precipitazioni forti e consistenti', 'rovesci di notevole intensità']);
        } elseif ($intensity === 'moderate') {
            $rain_desc = pick(['piogge moderate e costanti', 'precipitazioni di media intensità', 'rovesci regolari']);
        } else {
            $rain_desc = pick(['piogge leggere e intermittenti', 'precipitazioni deboli', 'pioviggine o pioggerella']);
        }

        if ($max_prob >= 80) {
            $phrases[] = $rain_desc . ' ' . pick(['diffuse e probabili', 'estese su tutto il territorio', 'generalizzate']);
        } elseif ($max_prob >= 60) {
            $phrases[] = $rain_desc . ' ' . pick(['molto probabili', 'attese con buona probabilità', 'previste']);
        } else {
            $phrases[] = pick(['possibili', 'probabili', 'non escluse']) . ' ' . $rain_desc . ' ' . pick(['a tratti', 'isolate', 'sparse']);
        }
        break;

        case 'storms':
        $phrases[] = $intensity === 'extreme' 
        ? pick([
            'temporali intensi con possibile grandine', 'attività temporalesca violenta e pericolosa',
            'fenomeni convettivi estremi', 'temporali di forte intensità con rischio grandine'
        ])
        : pick([
            'attività temporalesca in sviluppo', 'temporali isolati ma intensi',
            'fenomeni convettivi sparsi', 'possibili temporali pomeridiani'
        ]);
        break;

        case 'snow':
        if ($intensity === 'heavy') {
            $phrases[] = pick(['nevicate intense e abbondanti', 'precipitazioni nevose forti', 'neve copiosa']);
        } elseif ($intensity === 'moderate') {
            $phrases[] = pick(['nevicate moderate e regolari', 'neve di media intensità', 'precipitazioni nevose costanti']);
        } else {
            $phrases[] = pick(['nevicate leggere e sparse', 'neve debole o nevischio', 'precipitazioni nevose intermittenti']);
        }
        break;

        case 'freezing':
        $phrases[] = pick([
            'precipitazioni gelate con rischio ghiaccio', 'fenomeni di freezing rain pericolosi',
            'pioggia gelata e formazione di ghiaccio al suolo', 'condizioni di gelo con precipitazioni'
        ]);
        break;

        default:
        $phrases[] = $isNight 
        ? pick(['condizioni meteorologiche stabili', 'situazione notturna tranquilla', 'atmosfera serena'])
        : pick(['tempo variabile e incerto', 'condizioni meteorologiche mutevoli', 'situazione instabile']);
    }
    
    return $phrases;
}

/**
 * Genera descrizioni del vento più precise e contestualizzate
 * VERSIONE MIGLIORATA con maggiori variazioni linguistiche
 */
function generateSmartWindPhrase(float $wind, float $gust, bool $hasStrongPrecip = false): array {
    $phrases = [];
    
    // Se c'è maltempo forte, il vento è meno rilevante nella descrizione
    if ($hasStrongPrecip && $wind < 30 && $gust < 40) return [];
    
    if ($gust >= 60) {
        $phrases[] = pick([
            "raffiche violente fino a " . round($gust) . " km/h",
            "vento tempestoso con punte di " . round($gust) . " km/h",
            "condizioni di burrasca con raffiche estreme"
        ]);
    } elseif ($gust >= 45) {
        $phrases[] = pick([
            "raffiche forti fino a " . round($gust) . " km/h",
            "vento intenso con punte significative",
            "condizioni ventose di forte intensità"
        ]);
    } elseif ($gust >= 30 && $wind >= 20) {
        $phrases[] = pick([
            "vento sostenuto con raffiche fino a " . round($gust) . " km/h",
            "ventilazione costante e rafficata",
            "condizioni di vento teso e irregolare"
        ]);
    } elseif ($wind >= 25) {
        $phrases[] = pick([
            "vento moderato e costante",
            "ventilazione sostenuta ma regolare",
            "condizioni di brezza tesa persistente"
        ]);
    } elseif ($wind >= 15) {
        $phrases[] = pick([
            "ventilazione presente e gradevole",
            "brezza moderata e piacevole",
            "aria in movimento con leggero vento"
        ]);
    } elseif ($wind < 8) {
        $phrases[] = pick([
            "aria calma e ferma",
            "condizioni di bonaccia totale",
            "assenza di ventilazione significativa",
            "atmosfera silenziosa e immobile"
        ]);
    }
    
    return $phrases;
}

/**
 * Genera descrizioni della temperatura più contestualizzate
 * VERSIONE MIGLIORATA con narrativa notturna specifica e variazioni
 */
function generateSmartTempPhrase(array $temps, ?array $apparent_temps = null, bool $isNight = false): array {
    if (empty($temps)) return [];
    
    $min_temp = min($temps);
    $max_temp = max($temps);
    $avg_temp = array_sum($temps) / count($temps);
    
    // Se abbiamo temperature percepite, usale per il comfort
    if ($apparent_temps && count($apparent_temps) === count($temps)) {
        $avg_apparent = array_sum($apparent_temps) / count($apparent_temps);
        $temp_diff = abs($avg_apparent - $avg_temp);
        
        // Temperatura percepita significativamente diversa
        if ($temp_diff >= 3) {
            if ($avg_apparent > $avg_temp + 2) {
                if ($avg_apparent >= 32) return [pick(["clima torrido e opprimente", "caldo estremo e soffocante", "temperatura elevata con afa marcata"])];
                if ($avg_apparent >= 26) return [pick(["temperatura elevata con sensazione di caldo", "clima caldo e umido", "calore percepito intenso"])];
            } elseif ($avg_apparent < $avg_temp - 2) {
                return [pick(["temperatura mitigata dal vento", "sensazione più fresca per la ventilazione", "calore attenuato dalla brezza"])];
            }
        }
    }
    
    $phrases = [];
    
    if ($isNight) {
        // NARRATIVA NOTTURNA SPECIFICA
        if ($avg_temp >= 25) {
            $phrases[] = pick([
                "notte calda e afosa", "temperatura notturna elevata", "calore persistente nelle ore buie",
                "notte tropicale e soffocante", "aria notturna calda e umida"
            ]);
        } elseif ($avg_temp >= 20) {
            $phrases[] = pick([
                "notte mite e gradevole", "temperatura notturna piacevole", "clima notturno temperato",
                "aria tiepida nelle ore serali", "notte dal clima dolce"
            ]);
        } elseif ($avg_temp >= 15) {
            $phrases[] = pick([
                "notte fresca ma confortevole", "temperatura notturna frizzante", "aria fresca e rigenerante",
                "clima notturno vivace", "frescura serale piacevole"
            ]);
        } elseif ($avg_temp >= 5) {
            $phrases[] = pick([
                "notte fredda e pungente", "temperatura notturna rigida", "aria gelida nelle ore buie",
                "clima notturno severo", "frescura intensa e penetrante"
            ]);
        } elseif ($avg_temp >= 0) {
            $phrases[] = pick([
                "notte gelida e tagliente", "temperatura sotto zero imminente", "aria glaciale e penetrante",
                "clima notturno glaciale", "gelo nelle ore più fredde"
            ]);
        } else {
            $phrases[] = pick([
                "notte di gelo intenso", "temperatura sottozero marcata", "condizioni di ghiaccio notturno",
                "clima polare nelle ore buie"
            ]);
        }
    } else {
        // NARRATIVA DIURNA CON VARIAZIONI
        if ($avg_temp >= 35) {
            $phrases[] = pick([
                "temperature torride e opprimenti", "caldo estremo e pericoloso", "clima desertico e arido",
                "temperatura da record", "calore insopportabile"
            ]);
        } elseif ($avg_temp >= 30) {
            $phrases[] = pick([
                "clima caldo e soleggiato", "temperature estive elevate", "calore intenso ma piacevole",
                "atmosfera calda e luminosa", "temperatura ideale per il mare"
            ]);
        } elseif ($avg_temp >= 25) {
            $phrases[] = pick([
                "temperature gradevoli e miti", "clima primaverile perfetto", "calore moderato e confortevole",
                "atmosfera tiepida e accogliente", "temperatura ideale per attività esterne"
            ]);
        } elseif ($avg_temp >= 20) {
            $phrases[] = pick([
                "clima mite e temperato", "temperature primaverili dolci", "atmosfera fresca e piacevole",
                "condizioni climatiche ideali", "temperatura equilibrata"
            ]);
        } elseif ($avg_temp >= 15) {
            $phrases[] = pick([
                "temperature fresche e vivaci", "clima frizzante ma gradevole", "aria fresca e tonificante",
                "atmosfera autunnale piacevole", "frescura rigenerante"
            ]);
        } elseif ($avg_temp >= 10) {
            $phrases[] = pick([
                "clima fresco e pungente", "temperature invernali moderate", "aria fredda ma sopportabile",
                "frescura intensa ma accettabile"
            ]);
        } elseif ($avg_temp >= 5) {
            $phrases[] = pick([
                "temperature fredde e rigide", "clima invernale severo", "aria gelida e tagliente",
                "condizioni di freddo intenso"
            ]);
        } else {
            $phrases[] = pick([
                "clima rigido e polare", "temperature glaciali", "condizioni di gelo estremo",
                "freddo pungente e pericoloso"
            ]);
        }
        
        // Aggiungi variazioni significative nella fascia
        $temp_range = $max_temp - $min_temp;
        if ($temp_range >= 10) {
            $phrases[] = pick([
                "con forti escursioni termiche", "e notevoli variazioni di temperatura",
                "con sbalzi termici significativi", "e ampie oscillazioni durante la giornata"
            ]);
        } elseif ($temp_range >= 8) {
            $phrases[] = pick([
                "con moderate escursioni termiche", "e lievi variazioni di temperatura",
                "con qualche oscillazione termica"
            ]);
        }
    }
    
    return $phrases;
}


// =============================
// COMMENTO DESCRITTIVO GIORNALIERO
// =============================

/**
 * Estrae casualmente una frase da un array.
 */
function pick(array $frasi): string {
    return $frasi[array_rand($frasi)];
}

/**
 * Rimuove frasi simili sopra una soglia di percentuale.
 */
function filterSimilarPhrases(array $lines, int $threshold = 85): array {
    $out = [];
    foreach ($lines as $line) {
        $too_similar = false;
        foreach ($out as $existing) {
            similar_text($line, $existing, $perc);
            if ($perc > $threshold) {
                $too_similar = true;
                break;
            }
        }
        if (!$too_similar) $out[] = $line;
    }
    return $out;
}

/**
 * Rimuove frasi duplicate all'interno di un testo, mantenendo solo la prima occorrenza.
 */
function rimuoviFrasiRipetute(string $text): string {
    $frasi = preg_split('/\.\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $clean = [];
    foreach ($frasi as $f) {
        $f_trim = trim($f);
        if (!in_array($f_trim, $clean)) $clean[] = $f_trim;
    }
    return implode('. ', $clean) . (count($clean) > 0 ? '.' : '');
}

/**
 * Genera un paragrafo narrativo unificato MIGLIORATO per una fascia oraria.
 * Evita contraddizioni analizzando prima i pattern meteorologici dominanti.
 */
function generateNarrativeParagraphUnified(
    string $fasciaLabel,
    array $codes,
    array $temps,
    array $gusts,
    array $winds,
    array $uvs,
    array $pressures,
    array $precip,
    array $precip_prob
): string {
    // Safe fallback su array vuoti
    if (empty($codes) || empty($temps)) {
        return ucfirst(str_replace('_early', '', $fasciaLabel)) . " con condizioni variabili.";
    }
    
    $fascia = str_replace('_early', '', $fasciaLabel);
    $isNight = in_array($fascia, ['sera', 'notte']);
    
    // Analisi pattern meteorologico INTELLIGENTE
    $weather_analysis = analyzeWeatherPattern($codes, $precip, $precip_prob);
    
    // Calcoli base (safe)
    $avg_temp = array_sum($temps) / count($temps);
    $max_gust = max($gusts);
    $avg_wind = array_sum($winds) / count($winds);
    $avg_uv = $isNight ? 0 : (count($uvs) > 0 ? array_sum($uvs) / count($uvs) : 0);
    $avg_pressure = count($pressures) > 0 ? array_sum($pressures) / count($pressures) : 1015;
    
    // Introduzione fascia - versione compatibile
    if ($fascia === 'mattina') {
        $intro = pick(["In mattinata", "Durante le prime ore del giorno", "La mattina"]);
    } elseif ($fascia === 'pomeriggio') {
        $intro = pick(["Nel pomeriggio", "Durante il pomeriggio", "Al pomeriggio"]);
    } elseif ($fascia === 'sera') {
        $intro = pick(["In serata", "Verso sera", "La sera"]);
    } elseif ($fascia === 'notte') {
        $intro = pick(["Durante la notte", "Nelle ore notturne", "La notte"]);
    } else {
        $intro = ucfirst($fasciaLabel);
    }
    
    $blocchi = [];
    
    // 1. CONDIZIONI METEOROLOGICHE (principale)
    $meteo_phrases = generateSmartWeatherPhrase($weather_analysis, $isNight);
    if ($meteo_phrases) {
        $blocchi[] = $meteo_phrases[0];
    }
    
    // 2. VENTO (solo se rilevante o non mascherato dal maltempo)
    $hasStrongWeather = $weather_analysis['intensity'] === 'extreme' || 
    $weather_analysis['intensity'] === 'heavy';
    $wind_phrases = generateSmartWindPhrase($avg_wind, $max_gust, $hasStrongWeather);
    if ($wind_phrases) {
        $blocchi = array_merge($blocchi, $wind_phrases);
    }
    
    // 3. TEMPERATURA (solo se significativa)
    $temp_phrases = generateSmartTempPhrase($temps, null, $isNight);
    if ($temp_phrases && !$isNight) { // Di notte meno enfasi sulla temperatura
        $blocchi = array_merge($blocchi, $temp_phrases);
    }
    
    // 4. FATTORI AGGIUNTIVI (UV, pressione) solo se non c'è già troppo contenuto
    if (count($blocchi) <= 2) {
        if (!$isNight && $avg_uv >= 7) {
            $blocchi[] = "indice UV elevato";
        }
        
        if ($avg_pressure < 1005 && $weather_analysis['pattern'] !== 'clear') {
            $blocchi[] = "pressione in calo";
        }
    }
    
    // Limita a 3 elementi per evitare sovraccarico
    $blocchi = array_slice($blocchi, 0, 3);
    
    // Costruzione frase finale
    if (empty($blocchi)) {
        return $intro . " condizioni stabili.";
    }
    
    // Connessione intelligente degli elementi
    if (count($blocchi) === 1) {
        return $intro . " " . $blocchi[0] . ".";
    } elseif (count($blocchi) === 2) {
        return $intro . " " . $blocchi[0] . " con " . $blocchi[1] . ".";
    } else {
        $ultimo = array_pop($blocchi);
        return $intro . " " . implode(", ", $blocchi) . " e " . $ultimo . ".";
    }
}










/**
 * Crea alert e commento descrittivo giornaliero, con controllo su fasce orarie.
 * Restituisce null se tutte le fasce sono passate.
 *
 * @param ?array $forced_alert_indices  Se fornito, usa direttamente questi indici
 *                                      per calcolare l’alert (sincronizzato con
 *                                      semafori/finestra esterna).
 */
function getUnifiedMeteoAlert(
    array $timestamps,
    array $codes,
    array $temps,
    array $gusts,
    array $winds,
    array $uvs,
    array $pressures,
    array $precip,
    array $precip_prob,
    array $visibility,
    array $humidity,
    array $dew_point,
    array $wind_directions,
    string $date_str,
    DateTimeZone $timezone,
    DateTimeImmutable $now,
    bool $escludiFascePassate = true,
    ?int $daily_code = null,
    ?array $forced_alert_indices = null      // 🆕
): array|null {

    /* ---------- 1. Narrative per fasce (come prima) ---------- */
    $fasce = TIME_BUCKETS;

    // Se la giornata è già “chiusa” (oggi dopo le 22) esci subito
    $oggi = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
    if ($date_str === $oggi && $now->format('H') > 22) return null;

    // Helper per escludere fasce già passate (solo oggi)
    $include_fascia = function (string $fascia) use ($date_str, $timezone) : bool {
        $nowReal = new DateTimeImmutable('now', $timezone);
        $current_hour = (int)$nowReal->format('H');
        $isToday = $nowReal->format('Y-m-d') === $date_str;

        if (!$isToday) return true;
        return match ($fascia) {
            'mattina'    => $current_hour < 12,
            'pomeriggio' => $current_hour < 18,
            'sera'       => $current_hour < 22,
            'notte'      => true,
            default      => false
        };
    };

    $paragrafi        = [];
    $paragrafo_notte  = null;

    foreach ($fasce as $fascia => [$h_start, $h_end]) {
        if (!$include_fascia($fascia)) continue;

        $idx = [];
        foreach ($timestamps as $i => $ts) {
            $dt  = new DateTimeImmutable($ts, $timezone);
            $hr  = (int) $dt->format('H');
            $day = $dt->format('Y-m-d');

            $in_fascia = ($fascia === 'notte')
            ? ($hr >= 23 || $hr <= 6)
            : ($hr >= $h_start && $hr <= $h_end);

            if ($day === $date_str && $in_fascia) $idx[] = $i;
        }

        if (!empty($idx)) {
            $p = generateNarrativeParagraphUnified(
                $fascia,
                array_map(fn($i)=>$codes[$i],          $idx),
                array_map(fn($i)=>$temps[$i],          $idx),
                array_map(fn($i)=>$gusts[$i],          $idx),
                array_map(fn($i)=>$winds[$i],          $idx),
                array_map(fn($i)=>$uvs[$i],            $idx),
                array_map(fn($i)=>$pressures[$i],      $idx),
                array_map(fn($i)=>$precip[$i],         $idx),
                array_map(fn($i)=>$precip_prob[$i],    $idx)
            );

            if ($fascia === 'notte') {
                $clean  = preg_replace('/^(Durante la notte|Nelle ore notturne|La notte|Dopo il tramonto)[\s,]*/iu', '', $p);
                $clean  = ucfirst(ltrim($clean));
                $clean  = rimuoviFrasiRipetute($clean);
                $paragrafo_notte = $paragrafo_notte
                ? rimuoviFrasiRipetute($paragrafo_notte.' '.$clean)
                : $p;
            } else {
                $paragrafi[] = $p;
            }
        }
    }
    if ($paragrafo_notte) $paragrafi[] = $paragrafo_notte;

    $narrative = implode("\n\n", $paragrafi);

    /* ---------- 2. Scelta indici per l’alert sintetico ---------- */
    $alert_indices = [];

    if (is_array($forced_alert_indices) && count($forced_alert_indices) > 0) {
        // Finestra esterna già decisa (sincronizzata con semafori)
        $alert_indices = $forced_alert_indices;

    } else {
        // Logica precedente (prossime N ore o fascia 07-22)
        foreach ($timestamps as $i => $ts) {
            $dt   = new DateTimeImmutable($ts, $timezone);
            $day  = $dt->format('Y-m-d');
            $hour = (int)$dt->format('H');

            if ($day !== $date_str) continue;

            if ($escludiFascePassate) {
                // Oggi → prossime 8 ore (ALERT_HOURS_TODAY)
                $nowHour = (int)$now->format('H');
                if ($hour >= $nowHour && $hour < $nowHour + ALERT_HOURS_TODAY) {
                    $alert_indices[] = $i;
                }
            } else {
                // Giorni futuri → 07-22
                if ($hour >= ALERT_HOURS_FUTURE_START && $hour <= ALERT_HOURS_FUTURE_END) {
                    $alert_indices[] = $i;
                }
            }
        }
    }

    if (empty($alert_indices)) return null;

    $idx_slice  = $alert_indices;
    $valid_from = (new DateTimeImmutable($timestamps[$idx_slice[0]],      $timezone))->format('H:i');
    $valid_to   = (new DateTimeImmutable($timestamps[end($idx_slice)],    $timezone))->format('H:i');

    // Primo indice come riferimento
    $index      = $alert_indices[0];
    $alert_hour = (int)(new DateTimeImmutable($timestamps[$index], $timezone))->format('H');
    $is_night   = $alert_hour >= 21 || $alert_hour < 7;

    /* ---------- 3. Costruzione dell’alert (come prima) ---------- */
    $alert = getAlertStatus(
        $winds[$index],
        $gusts[$index],
        array_map(fn($i)=>$precip[$i],       $idx_slice),
        array_map(fn($i)=>$precip_prob[$i],  $idx_slice),
        $wind_directions[$index] ?? 0,
        $humidity[$index]       ?? 0,
        $uvs[$index]            ?? 0,
        array_map(fn($i)=>$visibility[$i],   $idx_slice),
        $pressures[$index]      ?? 1015,
        $is_night,
        $now,
        $valid_from,
        $valid_to
    );

    /* -----------------------------------------------------------------
 *   WARNING extra solo se i codici critici compaiono
 *   NELLA FINESTRA dell’alert oppure (opz.) nel daily_code.
 * ----------------------------------------------------------------- */
    $codes_window = array_map(fn($i) => $codes[$i], $alert_indices);
    $all_codes    = array_merge(
        $codes_window,
        $daily_code !== null ? [$daily_code] : []
    );

    $critici      = [95, 96, 99, 65];
    $found        = array_intersect($critici, $all_codes);

    if ($found && $alert['type'] !== 'danger') {
        $alert['type'] = 'warning';
        $alert['icon'] = 'bi-exclamation-triangle';

        if (array_intersect([95, 96, 99], $found)) {
            $alert['lines'][] = "Possibili temporali intensi o grandine previsti";
        }
        if (in_array(65, $found)) {
            $alert['lines'][] = "Pioggia intensa prevista";
        }
        $alert['lines'] = array_unique($alert['lines']);
    }

    return array_merge($alert, ['narrative' => $narrative]);
}









/**
 * Restituisce alert e narrativa per la data richiesta usando
 * la stessa finestra oraria dei semafori.
 */
function buildMeteoBox(string $targetDate, bool $escludiPassate): ?array {

    $tz       = new DateTimeZone(TIMEZONE);
    $now      = new DateTimeImmutable('now', $tz);
    $isToday  = $targetDate === $now->format('Y-m-d');
    $effectiveNow = $isToday ? $now
    : new DateTimeImmutable($targetDate.' 00:00:00', $tz);

    /* ---------- Daily code (solo per giorni futuri) ---------- */
    $daily_code = null;
    if (
        !$isToday &&
        isset($GLOBALS['daily_weather_codes'], $GLOBALS['daily_sunrise_times']) &&
        is_array($GLOBALS['daily_weather_codes']) && is_array($GLOBALS['daily_sunrise_times'])
    ) {
        foreach ($GLOBALS['daily_sunrise_times'] as $i => $ts) {
            $dt = new DateTimeImmutable($ts, $tz);
            if ($dt->format('Y-m-d') === $targetDate) {
                $daily_code = $GLOBALS['daily_weather_codes'][$i] ?? null;
                break;
            }
        }
    }

    /* ---------- Verifica variabili globali ---------- */
    $required = [
        'timestamps','hourly_weather_codes','hourly_temperature','hourly_wind_gusts',
        'hourly_wind_speed','hourly_uv_index','hourly_pressure','hourly_precip',
        'hourly_precip_prob','hourly_visibility','hourly_humidity',
        'hourly_dew_point','hourly_wind_direction'
    ];
    foreach ($required as $g) {
        if (!isset($GLOBALS[$g]) || !is_array($GLOBALS[$g])) return null;
    }

    /* ---------- Finestra oraria condivisa ---------- */
    if ($isToday) {
        // oggi → da adesso a +DEFAULT_HOURS_TODAY
        $alert_idx = getForecastWindowIndices(
            $GLOBALS['timestamps'],
            $now,
            hoursAhead: DEFAULT_HOURS_TODAY,   // fallback da config
            stepHours: 1
        );
    } else {
        // date future → 07:00 – 22:00 (costanti ALERT_HOURS_FUTURE_* )
        $start = new DateTimeImmutable($targetDate.' '.ALERT_HOURS_FUTURE_START.':00', $tz);
        $end   = new DateTimeImmutable($targetDate.' '.ALERT_HOURS_FUTURE_END  .':00', $tz);

        $alert_idx = getForecastWindowIndices(
            $GLOBALS['timestamps'],
            $now,            // solo per timezone
            hoursAhead: null,
            start: $start,
            end:   $end,
            stepHours: 1
        );
    }

    /* ---------- Costruzione meteoBox ---------- */
    return getUnifiedMeteoAlert(
        $GLOBALS['timestamps'],
        $GLOBALS['hourly_weather_codes'],
        $GLOBALS['hourly_temperature'],
        $GLOBALS['hourly_wind_gusts'],
        $GLOBALS['hourly_wind_speed'],
        $GLOBALS['hourly_uv_index'],
        $GLOBALS['hourly_pressure'],
        $GLOBALS['hourly_precip'],
        $GLOBALS['hourly_precip_prob'],
        $GLOBALS['hourly_visibility'],
        $GLOBALS['hourly_humidity'],
        $GLOBALS['hourly_dew_point'],
        $GLOBALS['hourly_wind_direction'],
        $targetDate,
        $tz,
        $effectiveNow,
        $isToday,           // ancora usato per la narrativa
        $daily_code,
        $alert_idx          // 🆕  indici sincronizzati con semafori
    );
}










/**
 * Calcola i livelli di semaforo safety/comfort MIGLIORATI con motivazioni per ciascuna ora.
 * Logica più precisa, soglie ottimizzate e messaggi contestualizzati.
 *
 * @param array $temp           Temperature reali per ora
 * @param array $apparent_temp  Temperature percepite per ora
 * @param array $humidity       Umidità per ora
 * @param array $wind           Vento per ora
 * @param array $gusts          Raffiche per ora
 * @param array $precip         Precipitazione per ora (mm/h)
 * @param array $visibility     Visibilità per ora (metri)
 * @param array $uv_index       UV per ora
 * @param array $dew_point      Punto di rugiada per ora
 * @param float $pressure       Pressione (costante o media periodo)
 * @param int $hours            Numero ore da considerare
 * @return array                Livelli safety/comfort e motivazioni per ogni ora
 */
function getTrafficLightLevels(
    array $temp,
    array $apparent_temp,
    array $humidity,
    array $wind,
    array $gusts,
    array $precip,
    array $visibility,
    array $uv_index,
    array $dew_point,
    float $pressure,
    int $hours = 8
): array {
    $safety = [];
    $comfort = [];
    $safety_reasons = [];
    $comfort_reasons = [];

    // Mappa rischio vento per safety (ottimizzata)
    $wind_score_map_safety = [
        'lvl-00'  => 0.0, 'lvl-04'  => 0.3, 'lvl-11'  => 0.7, 'lvl-18'  => 1.2,
        'lvl-25'  => 1.8, 'lvl-32'  => 2.4, 'lvl-40'  => 3.0, 'lvl-47'  => 3.7,
        'lvl-54'  => 4.2, 'lvl-61'  => 4.8, 'lvl-68'  => 5.5, 'lvl-76'  => 6.2,
        'lvl-86'  => 7.0, 'lvl-97'  => 7.8, 'lvl-104' => 8.5, 'lvl-130' => 9.0,
    ];

    for ($i = 0; $i < $hours; $i++) {
        // Safe check con fallback
        $t      = $temp[$i]           ?? null;
        $ta     = $apparent_temp[$i]  ?? $t;
        $hum    = $humidity[$i]       ?? null;
        $w      = $wind[$i]           ?? null;
        $g      = $gusts[$i]          ?? null;
        $p      = $precip[$i]         ?? null;
        $vis    = $visibility[$i]     ?? null;
        $uv     = $uv_index[$i]       ?? null;
        $dew    = $dew_point[$i]      ?? null;

        // ===== SAFETY MIGLIORATO =====
        $rs = [];
        $safety_scores = [
          'precip'     => 0.0,
          'wind'       => 0.0,
          'visibility' => 0.0,
          'pressure'   => 0.0,
      ];

        // PRECIPITAZIONI - Logica migliorata
      if (!is_null($p) && $p > 0) {
        if ($p >= 8.0) {
            $safety_scores['precip'] = 4.0;
            $rs[] = "Pioggia molto intensa: " . round($p, 1) . " mm/h";
        } elseif ($p >= 5.0) {
            $safety_scores['precip'] = 3.0;
            $rs[] = "Pioggia intensa: " . round($p, 1) . " mm/h";
        } elseif ($p >= 2.5) {
            $safety_scores['precip'] = 2.0;
            $rs[] = "Pioggia consistente: " . round($p, 1) . " mm/h";
        } elseif ($p >= 1.0) {
            $safety_scores['precip'] = 1.0;
                // Solo se combinata con altri fattori
            if (!is_null($w) && $w >= 20) $rs[] = "Pioggia con vento forte";
        }
    } else {
        $safety_scores['precip'] = 0.0;
    }

        // VENTO E RAFFICHE - Analisi combinata migliorata
    $wind_safety = 0.0;
    $wind_reasons = [];
    
    if (!is_null($w)) {
        $ws = $wind_score_map_safety[getWindUnifiedLevel($w)] ?? 0;
        $wind_safety += $ws;
        if ($ws >= 3.0) $wind_reasons[] = "vento molto forte (" . round($w) . " km/h)";
        elseif ($ws >= 2.0) $wind_reasons[] = "vento forte (" . round($w) . " km/h)";
    }
    
    if (!is_null($g)) {
        $gs = $wind_score_map_safety[getWindUnifiedLevel($g)] ?? 0;
            $gust_bonus = max(0, $gs - $wind_safety) * 0.6; // Bonus raffiche oltre vento medio
            $wind_safety += $gust_bonus;
            if ($gs >= 4.0) $wind_reasons[] = "raffiche violente (" . round($g) . " km/h)";
            elseif ($gs >= 3.0) $wind_reasons[] = "raffiche intense (" . round($g) . " km/h)";
            elseif ($gs >= 2.0 && $gs > $ws + 1) $wind_reasons[] = "raffiche significative (" . round($g) . " km/h)";
        }
        
        $safety_scores['wind'] = min($wind_safety, 5.0); // Cap massimo
        if ($wind_reasons) $rs = array_merge($rs, $wind_reasons);

        // VISIBILITÀ - Soglie ottimizzate
        $vis_score = 0.0;
        if (!is_null($vis)) {
            $vis_km = round($vis / 1000, 1);
            if ($vis_km < 0.5) {
                $vis_score = 4.0;
                $rs[] = "visibilità critica: " . $vis_km . " km";
            } elseif ($vis_km < 2.0) {
                $vis_score = 3.0;
                $rs[] = "visibilità molto ridotta: " . $vis_km . " km";
            } elseif ($vis_km < 5.0) {
                $vis_score = 2.0;
                $rs[] = "visibilità ridotta: " . $vis_km . " km";
            } elseif ($vis_km < 10.0) {
                $vis_score = 1.0;
                // Solo se ci sono altri fattori
                if (($safety_scores['precip'] ?? 0) >= 1.0) $rs[] = "visibilità limitata con pioggia";
            }
        }
        $safety_scores['visibility'] = $vis_score;

        // PRESSIONE - Solo se davvero rilevante
        $pressure_score = 0.0;
        if ($pressure < 995 && (($safety_scores['precip'] ?? 0) >= 2.0 || ($safety_scores['wind'] ?? 0) >= 2.0)) {
            $pressure_score = 1.5;
            $rs[] = "pressione critica: " . round($pressure) . " hPa";
        } elseif ($pressure < 1000 && (($safety_scores['precip'] ?? 0) >= 1.0 || ($safety_scores['wind'] ?? 0) >= 1.5)) {
            $pressure_score = 0.8;
            $rs[] = "pressione molto bassa: " . round($pressure) . " hPa";
        }
        $safety_scores['pressure'] = $pressure_score;

        // CALCOLO SAFETY FINALE - Pesi ottimizzati
        $precip_score = $safety_scores['precip'] ?? 0;
        $wind_score = $safety_scores['wind'] ?? 0;
        $visibility_score = $safety_scores['visibility'] ?? 0;
        $pressure_score = $safety_scores['pressure'] ?? 0;

        $total_safety = (
    $precip_score * 2.2 +      // Pioggia priorità massima
    $wind_score * 1.0 +        // Vento importante
    $visibility_score * 1.5 +  // Visibilità critica
    $pressure_score * 0.8      // Pressione supporto
) / 5.5;

// LIVELLI SAFETY con soglie calibrate
        if ($total_safety >= 2.2) {
            $lv_s = 'dot-red';
        } elseif ($total_safety >= 1.4) {
            $lv_s = 'dot-yellow-dark';
        } elseif ($total_safety >= 0.7) {
            $lv_s = 'dot-yellow-light';
        } else {
            $lv_s = 'dot-green';
        }

// Override per eventi estremi singoli
        if ($precip_score >= 3.5 || $wind_score >= 4.0 || $visibility_score >= 3.5) {
            $lv_s = 'dot-red';
        }

        $safety[] = $lv_s;
        $safety_reasons[] = $rs;

        // ===== COMFORT MIGLIORATO =====
        $rc = [];
        $comfort_scores = [];

        // TEMPERATURA - Logica migliorata con temperatura percepita
        $temp_score = 0.0;
        if (!is_null($ta)) {
            if ($ta >= 38) {
                $temp_score = 4.0;
                $rc[] = "caldo estremo: " . round($ta) . "° percepiti";
            } elseif ($ta >= 32) {
                $temp_score = 3.0;
                $rc[] = "molto caldo: " . round($ta) . "° percepiti";
            } elseif ($ta >= 28) {
                $temp_score = 2.0;
                $rc[] = "caldo intenso: " . round($ta) . "° percepiti";
            } elseif ($ta <= -5) {
                $temp_score = 3.5;
                $rc[] = "freddo polare: " . round($ta) . "° percepiti";
            } elseif ($ta <= 2) {
                $temp_score = 2.5;
                $rc[] = "freddo intenso: " . round($ta) . "° percepiti";
            } elseif ($ta <= 8) {
                $temp_score = 1.5;
                $rc[] = "temperatura fredda: " . round($ta) . "°";
            }
        }
        $comfort_scores['temperature'] = $temp_score;

        // UMIDITÀ - Soglie ottimizzate
        $humidity_score = 0.0;
        if (!is_null($hum)) {
            if ($hum >= 95) {
                $humidity_score = 3.0;
                $rc[] = "umidità opprimente: " . round($hum) . "%";
            } elseif ($hum >= 85) {
                $humidity_score = 2.0;
                $rc[] = "molto umido: " . round($hum) . "%";
            } elseif ($hum <= 15) {
                $humidity_score = 2.5;
                $rc[] = "aria molto secca: " . round($hum) . "%";
            } elseif ($hum <= 25) {
                $humidity_score = 1.5;
                $rc[] = "aria secca: " . round($hum) . "%";
            }
        }
        $comfort_scores['humidity'] = $humidity_score;

        // PUNTO DI RUGIADA - Afa e disagio
        $dew_score = 0.0;
        if (is_numeric($dew)) {
            if ($dew >= 25) {
                $dew_score = 4.0;
                $rc[] = "afa torrida insopportabile";
            } elseif ($dew >= 22) {
                $dew_score = 3.0;
                $rc[] = "afa molto intensa";
            } elseif ($dew >= 18) {
                $dew_score = 2.0;
                $rc[] = "sensazione di afa";
            }
        }
        $comfort_scores['dewpoint'] = $dew_score;

        // VENTO - Comfort (diverso da safety)
        $wind_comfort_score = 0.0;
        if (!is_null($w) && $w >= 30) {
            $wind_comfort_score = 2.5;
            $rc[] = "vento fastidioso: " . round($w) . " km/h";
        } elseif (!is_null($g) && $g >= 40) {
            $wind_comfort_score = 2.0;
            $rc[] = "raffiche disturbanti: " . round($g) . " km/h";
        }
        $comfort_scores['wind_comfort'] = $wind_comfort_score;

        // UV - Solo di giorno
        $uv_score = 0.0;
        if (!is_null($uv) && $uv > 0) {
            if ($uv >= 9) {
                $uv_score = 3.0;
                $rc[] = "UV pericoloso: " . round($uv, 1);
            } elseif ($uv >= 7) {
                $uv_score = 2.0;
                $rc[] = "UV molto alto: " . round($uv, 1);
            } elseif ($uv >= 5) {
                $uv_score = 1.0;
                $rc[] = "UV moderato-alto: " . round($uv, 1);
            }
        }
        $comfort_scores['uv'] = $uv_score;

        // PRECIPITAZIONI comfort
        $precip_comfort_score = 0.0;
        if (!is_null($p) && $p >= 2.0) {
            $precip_comfort_score = 2.0;
            $rc[] = "pioggia fastidiosa: " . round($p, 1) . " mm/h";
        } elseif (!is_null($p) && $p >= 0.5) {
            $precip_comfort_score = 1.0;
            $rc[] = "pioggia leggera disturbante";
        }
        $comfort_scores['precip_comfort'] = $precip_comfort_score;

        // CALCOLO COMFORT FINALE
        $total_comfort = (
            ($comfort_scores['temperature'] ?? 0) * 1.3 +
            ($comfort_scores['humidity'] ?? 0) * 1.0 +
            ($comfort_scores['dewpoint'] ?? 0) * 1.2 +
            ($comfort_scores['wind_comfort'] ?? 0) * 0.8 +
            ($comfort_scores['uv'] ?? 0) * 0.9 +
            ($comfort_scores['precip_comfort'] ?? 0) * 1.1
        ) / 6.3;

        // LIVELLI COMFORT
        if ($total_comfort >= 2.0) {
            $lv_c = 'dot-red';
        } elseif ($total_comfort >= 1.3) {
            $lv_c = 'dot-yellow-dark';
        } elseif ($total_comfort >= 0.6) {
            $lv_c = 'dot-yellow-light';
        } else {
            $lv_c = 'dot-green';
        }

        $comfort[] = $lv_c;
        $comfort_reasons[] = $rc;
    }

    return [
        'safety_levels'   => $safety,
        'comfort_levels'  => $comfort,
        'safety_reasons'  => $safety_reasons,
        'comfort_reasons' => $comfort_reasons,
    ];
}







// PUNTO DI RUGIADA
/**
 * Calcola il punto di rugiada (in °C) a partire da temperatura e umidità relativa.
 * Se i parametri sono fuori range, restituisce null.
 *
 * @param float $temperature   Temperatura in °C
 * @param float $humidity      Umidità relativa in percentuale (0-100)
 * @return float|null
 */
function calculateDewPoint(float $temperature, float $humidity): ?float {
    if ($humidity <= 0 || $humidity > 100) {
        return null; // Umidità fuori range, niente punto di rugiada
    }
    $a = 17.27;
    $b = 237.7;
    $alpha = (($a * $temperature) / ($b + $temperature)) + log($humidity / 100);
    return round(($b * $alpha) / ($a - $alpha), 1);
}

// Calcolo array punto di rugiada orario
$hourly_dew_point = [];
if (isset($hourly_temperature) && is_array($hourly_temperature)) {
    foreach ($hourly_temperature as $i => $t) {
        $humidity = $hourly_humidity[$i] ?? null;
        $hourly_dew_point[] = (is_numeric($t) && is_numeric($humidity))
        ? calculateDewPoint((float)$t, (float)$humidity)
        : null;
    }
}




function getHumidityDewPointComment(float $temp, float $humidity, ?float $dew_point): string {
    if (!is_numeric($dew_point)) return 'Dato non disponibile.';
    if ($dew_point >= 24) {
        return 'Clima torrido e molto afoso.';
    } elseif ($dew_point >= 20) {
        return 'Aria molto umida, sensazione di afa.';
    } elseif ($dew_point >= 15) {
        if ($humidity > 70 && $temp > 23) return 'Umido e un po\' afoso, possibile disagio.';
        if ($temp >= 15 && $temp <= 23) return 'Clima mite e umido, in genere confortevole.';
        if ($temp < 15) return 'Aria umida ma fresca.';
        return 'Clima umido.';
    } elseif ($dew_point >= 10) {
        return 'Clima confortevole.';
    } else {
        if ($humidity < 40) return 'Aria secca e fresca.';
        if ($temp < 10) return 'Clima fresco o freddo, aria secca.';
        return 'Clima fresco.';
    }
}



function getVisibilityComment(float $vis_km, float $cloud_cover, float $uv_now): string
{
    if ($vis_km < 2) {
        if ($cloud_cover > 80) $main = 'Visibilità pessima, cielo molto coperto.';
        elseif ($cloud_cover > 40) $main = 'Visibilità molto ridotta, foschia o nubi basse.';
        else $main = 'Visibilità gravemente limitata.';
    } elseif ($vis_km < 10) {
        if ($cloud_cover < 30) $main = 'Visibilità scarsa, ma cielo sereno.';
        elseif ($uv_now > 8) $main = 'Visibilità scarsa con UV elevato: attenzione!';
        else $main = 'Visibilità scarsa, possibile foschia o pioviggine.';
    } elseif ($vis_km < 30) {
        if ($cloud_cover > 85) $main = 'Visibilità moderata, ma cielo molto coperto.';
        elseif ($cloud_cover > 60) $main = 'Visibilità buona, ma nuvolosità abbondante.';
        elseif ($cloud_cover < 20) $main = 'Visibilità buona, cielo limpido.';
        else $main = 'Visibilità discreta, qualche nuvola.';
    } else {
        if ($cloud_cover > 85) $main = 'Ottima visibilità orizzontale, ma cielo molto coperto.';
        elseif ($cloud_cover > 60) $main = 'Ottima visibilità, ma prevalenza di nubi.';
        elseif ($cloud_cover < 20) $main = 'Ottima visibilità, cielo sereno.';
        else $main = 'Ottima visibilità.';
    }

    $uv_comment = match (true) {
        $uv_now < 3   => null,
        $uv_now < 6   => 'UV moderato, attenzione nelle ore centrali.',
        $uv_now < 8   => 'UV alto: protezione solare consigliata.',
        $uv_now < 11  => 'UV molto alto: evitare esposizione prolungata.',
        default       => 'UV estremo: protezione totale indispensabile.'
    };

    return $uv_comment ? "$main $uv_comment" : $main;
}






function getTempLabel(float $apparent, ?float $dewPoint = null): string
{
    // Se il dato dew point manca, fallback classico
    if (!is_numeric($dewPoint)) {
        return match (true) {
            $apparent >= 38   => 'Caldo estremo',
            $apparent >= 32   => 'Molto caldo',
            $apparent >= 26   => 'Caldo',
            $apparent >= 18   => 'Mite',
            $apparent >= 10   => 'Fresco',
            $apparent >= 0    => 'Freddo',
            $apparent >= -10  => 'Gelido',
            default           => 'Gelo intenso',
        };
    }

    // --- Logica combinata umana ---
    // 1. Super torrido/afoso: dew point altissimo
    if ($dewPoint >= 25 && $apparent >= 32)    return 'Caldo estremo e torrido';
    if ($dewPoint >= 24)                       return 'Torrido e afoso';
    if ($dewPoint >= 21 && $apparent >= 30)    return 'Molto caldo e afoso';
    if ($dewPoint >= 20)                       return 'Afa marcata, clima umido';
    if ($apparent >= 38)                       return 'Caldo estremo, rischio colpo di calore';
    if ($apparent >= 32)                       return 'Molto caldo, attenzione alla disidratazione';
    if ($apparent >= 26) {
        if ($dewPoint >= 17)                   return 'Caldo, sensazione afosa';
        else                                   return 'Caldo ma non afoso';
    }
    if ($apparent >= 18) {
        if ($dewPoint < 7)                     return 'Clima mite, aria secca';
        else                                   return 'Mite e gradevole';
    }
    if ($apparent >= 10) {
        if ($dewPoint < 5)                     return 'Fresco e secco';
        else                                   return 'Fresco';
    }
    if ($apparent >= 0)                        return 'Freddo';
    if ($apparent >= -10)                      return 'Gelido';
    return 'Gelo intenso';
}




// // Funzione per generare il gradiente vento/raffiche (modifica colori/limiti come preferisci)
// function windGradientBar($wind, $gust) {
//   $max = 70;
//   $wind = max(0, min($max, (int)$wind));
//   $gust = max(0, min($max, (int)$gust));
//   // Colori personalizzabili
//   $from = $wind < 15 ? '#b2fefa' : ($wind < 30 ? '#27ae60' : ($wind < 45 ? '#e67e22' : '#e74c3c'));
//   $to   = $gust < 15 ? '#b2fefa' : ($gust < 30 ? '#27ae60' : ($gust < 45 ? '#e67e22' : '#e74c3c'));
//   return "linear-gradient(90deg, $from 0%, $to 100%)";
// }

/**
 * Gradiente vento ↔︎ raffiche
 * ------------------------------------------------------------------
 * Usa la palette definita in CSS per .wind-box.lvl-XX
 * (00,04,11,18,25,32,40,47,54,61,68,76,86,97,104,130)
 *
 * @param float $wind  velocità media (km/h)
 * @param float $gust  raffica di picco (km/h)
 * @return string      CSS linear-gradient pronto per style=
 */
function windGradientBar(float $wind, float $gust): string
{
    // palette allineata alle classi CSS ---------------------------
    $palette = [
        'lvl-00'  => 'rgba(178, 245, 234, 0.20)',  // 0-3 kn  / 0-10 km/h
        'lvl-04'  => 'rgba(150, 230, 220, 0.40)',  // 4-6 kn  / 11-17 km/h
        'lvl-11'  => 'rgba(120, 215, 205, 0.40)',  // 7-9 kn  / 18-24 km/h
        'lvl-18'  => 'rgba( 90, 200, 180, 0.55)',  // 10-13 kn / 25-31 km/h
        'lvl-25'  => 'rgba( 72, 187, 150, 0.90)',  // 14-17 kn / 32-39 km/h
        'lvl-32'  => 'rgba( 56, 161, 105, 0.90)',  // 18-21 kn / 40-46 km/h
        'lvl-40'  => 'rgba(158, 113, 255, 0.90)',  // 22-25 kn / 47-53 km/h
        'lvl-47'  => 'rgba(140,  88, 255, 0.95)',  // 26-29 kn / 54-60 km/h
        'lvl-54'  => 'rgba(120,  70, 255, 0.95)',  // 30-33 kn / 61-67 km/h
        'lvl-61'  => 'rgba(105,  58, 255, 1.00)',  // 34-37 kn / 68-75 km/h
        'lvl-68'  => 'rgba( 90,  50, 220, 1.00)',  // 38-41 kn / 76-85 km/h
        'lvl-76'  => 'rgba(170,  50, 180, 1.00)',  // 42-48 kn / 86-96 km/h
        'lvl-86'  => 'rgba(200,  45, 130, 1.00)',  // 49-56 kn / 97-103 km/h
        'lvl-97'  => 'rgba(230,  30,  90, 1.00)',  // 57-63 kn / 104-129 km/h
        'lvl-104' => 'rgba(245,  25,  60, 1.00)',  // 64-70 kn / 130-145 km/h
        'lvl-130' => 'rgba(255,  20,  40, 1.00)',  // ≥71 kn   / ≥146 km/h
    ];

    // Limiti di sicurezza
    $wind = max(0, min($wind, 160));
    $gust = max(0, min($gust, 160));

    // ► helper che converte km/h → chiave lvl-XX già usata in helpers.php
    $toLevel = static function (float $v): string {
        return getWindUnifiedLevel($v);   // usa la tua funzione esistente
    };

    $fromColor = $palette[$toLevel($wind)] ?? $palette['lvl-00'];
    $toColor   = $palette[$toLevel($gust)] ?? $palette['lvl-00'];

    return "linear-gradient(90deg, {$fromColor} 0%, {$toColor} 100%)";
}




/**
LUNA
*/

/**
 * Calcola la fase lunare precisa [0..1] per una data (timezone già impostata).
 * Restituisce frazione ciclica (0=nuova, 0.5=piena, 1=nuova).
 */
function getMoonPhaseAccurate(DateTimeInterface $date): float {
    // Algoritmo di base Meeus
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n');
    $day = (int)$date->format('j') + 
    ((int)$date->format('H')/24) +
    ((int)$date->format('i')/1440) +
    ((int)$date->format('s')/86400);

    if ($month < 3) {
        $year--;
        $month += 12;
    }
    ++$month;

    $c = 365.25 * $year;
    $e = 30.6 * $month;
    $jd = $c + $e + $day - 694039.09; // giorni dalla luna nuova
    $cycles = $jd / 29.53058867;
    $phase = $cycles - floor($cycles);
    return $phase;
}


/**
 * Converte una frazione fase lunare [0..1] in nome + SVG.
 * Puoi personalizzare la mappatura icone.
 */
function moonPhaseOM(float $phase): array {
    $phases = [
        [0.00, 'Luna Nuova',         'moon-new.svg'],
        [0.03, 'Luna crescente',     'moon-waxing-crescent.svg'],
        [0.24, 'Primo Quarto',       'moon-first-quarter.svg'],
        [0.27, 'Gibbosa crescente',  'moon-waxing-gibbous.svg'],
        [0.48, 'Luna Piena',         'moon-full.svg'],
        [0.52, 'Gibbosa calante',    'moon-waning-gibbous.svg'],
        [0.74, 'Ultimo Quarto',      'moon-last-quarter.svg'],
        [0.77, 'Luna calante',       'moon-waning-crescent.svg'],
        [1.00, 'Luna Nuova',         'moon-new.svg'],
    ];
    foreach ($phases as $i => [$val, $name, $icon]) {
        if ($phase < $val) {
            return [
                'name' => $phases[max($i-1,0)][1],
                'icon' => $phases[max($i-1,0)][2]
            ];
        }
    }
    return ['name' => 'Luna', 'icon' => 'moon-new.svg'];
}

/**
 * Stima la distanza Terra-Luna in km (oscilla tra ~356.500 e 406.700 km).
 */
function getMoonDistance(DateTimeInterface $date): int {
    // Algoritmo semplificato (non astronomico!)
    $synodic_month = 29.53058867; // giorni
    $perigee = 356500;  // km (minima)
    $apogee = 406700;   // km (massima)
    $epoch = new DateTimeImmutable('2000-01-06 18:14:00', new DateTimeZone('UTC')); // luna nuova nota
    $days = ($date->getTimestamp() - $epoch->getTimestamp()) / 86400;
    $phase = fmod($days, $synodic_month) / $synodic_month;
    // Approssima con una sinusoide
    $distance = ($apogee + $perigee)/2 + cos(2 * pi() * $phase) * ($apogee - $perigee)/2;
    return (int)round($distance); // <-- fix qui!
}


/**
 * Calcolo orari sorgere/tramonto luna – algoritmo semplificato (±10min).
 * Restituisce ['moonrise' => 'HH:MM', 'moonset' => 'HH:MM']
 */
function getMoonRiseSet(DateTimeInterface $date, float $lat, float $lon): array {
    // Approccio semplificato: la luna ritarda il suo sorgere di ~50min al giorno
    // rispetto al giorno precedente (varia tra 30 e 70 minuti).
    $base = new DateTimeImmutable($date->format('Y-m-d').' 06:00:00', $date->getTimezone());
    $days_since_epoch = (int)$date->format('z'); // giorno dell'anno
    $moonrise = $base->modify('+' . ($days_since_epoch * 50 % 1440) . ' minutes');
    $moonset  = $moonrise->modify('+12 hours 25 minutes');
    return [
        'moonrise' => $moonrise->format('H:i'),
        'moonset'  => $moonset->format('H:i')
    ];
}


function getNextMoonPhase(DateTimeInterface $start, float $targetPhase, float $threshold = 0.03, int $maxDays = 40) {
    $date = $start;
    $prevPhase = getMoonPhaseAccurate($date);
    for ($i = 1; $i <= $maxDays; $i++) {
        $date = $date->modify('+1 day');
        $phase = getMoonPhaseAccurate($date);
        if (
            ($prevPhase < $targetPhase && $phase >= $targetPhase) ||
            (abs($phase - $targetPhase) < $threshold)
        ) {
            return $date;
        }
        $prevPhase = $phase;
    }
    return null;
}
