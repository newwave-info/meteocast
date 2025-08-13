<?php
// includes/solunar.php

function calculate_solunar_periods(float $lat, float $lon, string $date): array {
    $tz = new DateTimeZone('Europe/Rome');
    $dt = new DateTimeImmutable($date . ' 12:00:00', $tz);
    $timestamp = $dt->getTimestamp();

    // Fase lunare: 0 (nuova) - 100 (piena) approx
    $moon_phase = get_lunar_phase($timestamp);
    $lunar_day = floor($moon_phase * 29.53); // 0 - 29

    // Calcola finestre major (culmine e nadir)
    $culmination = moon_transit($dt, $lat, $lon);
    $opposition = $culmination->modify('+12 hours');

    $major = [
        [format_period($culmination, 60)],
        [format_period($opposition, 60)]
    ];

    // Finestre minor: alba e tramonto lunare (approssimate)
    $minor = [
        [format_period($culmination->modify('-6 hours'), 30)],
        [format_period($culmination->modify('+6 hours'), 30)]
    ];

    // Fase testuale (grezza)
    $phase_name = get_phase_name($moon_phase);

    // Punteggio pesca (semplificato)
    $score = ($moon_phase > 0.45 && $moon_phase < 0.55) ? 5 : (($moon_phase < 0.1 || $moon_phase > 0.9) ? 4 : 3);

    return [
        'major' => $major,
        'minor' => $minor,
        'lunar_phase' => $phase_name,
        'lunar_day' => $lunar_day,
        'score' => $score
    ];
}

function format_period(DateTimeImmutable $center, int $duration): array {
    $start = $center->modify("-{$duration} minutes");
    $end = $center->modify("+{$duration} minutes");
    return [$start->format('H:i'), $end->format('H:i')];
}

function get_lunar_phase(int $timestamp): float {
    // Approx moon phase based on known new moon (2000-01-06)
    $known_new_moon = 947182440; // timestamp UTC
    $synodic_month = 2551443; // in seconds
    $phase = fmod(($timestamp - $known_new_moon) / $synodic_month, 1);
    if ($phase < 0) $phase += 1;
    return round($phase, 3);
}

function get_phase_name(float $p): string {
    if ($p < 0.03 || $p > 0.97) return 'Luna Nuova';
    if ($p < 0.22) return 'Luna Crescente';
    if ($p < 0.28) return 'Primo Quarto';
    if ($p < 0.47) return 'Gibbosa Crescente';
    if ($p < 0.53) return 'Luna Piena';
    if ($p < 0.72) return 'Gibbosa Calante';
    if ($p < 0.78) return 'Ultimo Quarto';
    return 'Luna Calante';
}

function moon_transit(DateTimeImmutable $dt, float $lat, float $lon): DateTimeImmutable {
    // Approx transit = moonrise + 6h (very rough)
    $offset = (int)(($lon / 15) * 3600); // seconds offset
    return $dt->setTime(12, 0)->modify(($offset > 0 ? '-' : '+') . abs($offset) . ' seconds');
}