<?php
 $accordionId = 'rainAccordion'; // id univoco se hai più di un widget in pagina
 $forecast_days = defined('FORECAST_DAYS_CAROUSEL') ? CHART_DAYS_FORECAST : 3; // Usa la costante del config, fallback a 3
?>
<section class="widget widget-riga">

  <button class="widget-header btn-accordion" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
    <div class="widget-cont">
      <i class="wi wi-raindrop me-2"></i>
      <span class="widget-title">Precipitazioni</span>
    </div>
    
    <div class="widget-cont">
      <span class="widget-data-preview">andamento a <strong><?= $forecast_days ?> gg</strong></span>
      <span class="widget-action"><i class="bi bi-chevron-down arrow-accordion"></i></span>
    </div>
  </button>

  <!-- ACCORDION: Chart o dettagli -->
  <div class="collapse" id="<?= $accordionId ?>">
    <?php include ROOT_PATH . '/partials/meteo/previsioni-chart-precipitazioni.php'; ?>
  </div>

</section><!--widget-->
