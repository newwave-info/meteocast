  <?php
// --- Dati Precipitazioni (già calcolati sopra) ---
  $precip_probs = array_slice($hourly_precip_prob ?? [], $now_index, 4);
  $precip_vals = array_slice($hourly_precip ?? [], $now_index, 4);

  $hours_range = array_slice($timestamps, $now_index, 4);
  $hours_start = isset($hours_range[0]) ? (new DateTime($hours_range[0]))->format('H:i') : '--:--';
  $hours_end = isset($hours_range[3]) ? (new DateTime($hours_range[3]))->format('H:i') : '--:--';

  $prob_avg = count($precip_probs) ? round(array_sum($precip_probs) / count($precip_probs)) : 0;
  $precip_total = count($precip_vals) ? round(array_sum($precip_vals), 1) : 0;

  $precip_comment = match (true) {
    $precip_total == 0 => 'Nessuna precipitazione attesa.',
    $precip_total < 1  => 'Piogge leggere possibili.',
    $precip_total < 5  => 'Precipitazioni moderate.',
    default            => 'Pioggia intensa in arrivo.'
  };

  $prob_comment = match (true) {
    $prob_avg < 20 => 'Probabilità bassa.',
    $prob_avg < 50 => 'Possibilità variabile.',
    $prob_avg < 80 => 'Alta probabilità di pioggia.',
    default        => 'Pioggia quasi certa.'
  };

  $tooltip_prec = htmlentities("
    <span>
    <strong>Previsioni prossime 4h</strong><br>
    <small>$precip_comment<br>$prob_comment</small>
    </span>
    ");
    ?>

<!-- WIDGET PRECIPITAZIONI -->
<section class="widget">
  
  <header class="widget-header">
    <span class="widget-title">Precipitazioni</span>
    <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true" title="<?= $tooltip_prec ?>"></i>
  </header>
  
  <div class="widget-cont">
    
    <div class="widget-value"><strong><?= htmlspecialchars($precip_total) ?></strong> mm</div>
    <div class="widget-delta"><strong><?= htmlspecialchars($prob_avg) ?></strong> %</div>

  </div><!--widget-cont-->
  
  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Finestra oraria</span>
      <span class="widget-value"><?= $hours_start ?> - <?= $hours_end ?></span>
    </div>
  </footer>

  </section><!--widget-->