  <?php
// --- Dati Precipitazioni (già calcolati sopra) ---
$N_ORE = 12; // puoi impostare qui quante ore vuoi visualizzare
$precip_probs = array_slice($hourly_precip_prob ?? [], $now_index, $N_ORE);
$precip_vals  = array_slice($hourly_precip ?? [], $now_index, $N_ORE);
$hours_range  = array_slice($timestamps, $now_index, $N_ORE);

$hours_start = isset($hours_range[0]) ? (new DateTime($hours_range[0]))->format('H:i') : '--:--';
$hours_end   = isset($hours_range[$N_ORE-1]) ? (new DateTime($hours_range[$N_ORE-1]))->format('H:i') : '--:--';

$prob_avg = count($precip_probs) ? round(array_sum($precip_probs) / count($precip_probs)) : 0;
$precip_total = count($precip_vals) ? round(array_sum($precip_vals), 1) : 0;

$precip_comment = match (true) {
  $precip_total == 0 => 'Nessuna precipitazione attesa',
  $precip_total < 1  => 'Piogge leggere possibili',
  $precip_total < 5  => 'Precipitazioni moderate',
  default            => 'Pioggia intensa in arrivo'
};

$prob_comment = match (true) {
  $prob_avg < 20 => 'Probabilità bassa',
  $prob_avg < 50 => 'Possibilità variabile',
  $prob_avg < 80 => 'Alta probabilità di pioggia',
  default        => 'Pioggia quasi certa'
};


  $accordionId = 'rainAccordion';
  ?>



  <section class="widget widget-riga">

    <button class="widget-header btn-accordion" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
      <div class="widget-cont">
        <!-- <img src="assets/icons/svg/raindrops.svg" alt="Precipitazioni" class="weather-svg-icon" loading="lazy" /> -->
        <i class="wi wi-raindrop me-2"></i>
        <span class="widget-title"><?= $precip_comment ?></span>
      </div>

      <div class="widget-cont">
        <!-- <span><strong><?= htmlspecialchars($precip_total) ?></strong> mm | <strong><?= htmlspecialchars($prob_avg) ?></strong> %</span>  -->
        <span class="widget-data-preview"><?= $hours_start ?> - <?= $hours_end ?></span> 
        <span class="widget-action"><i class="bi bi-chevron-down arrow-accordion"></i></span>
      </div>
    </button>


    <!-- ACCORDION: Chart o dettagli -->
    <div class="collapse" id="<?= $accordionId ?>">
      <?php include ROOT_PATH . '/partials/meteo/oggi-chart-precipitazioni.php'; ?>
    </div>

</section><!--widget-->