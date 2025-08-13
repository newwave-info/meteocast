<?php
$vis_km_raw = $hourly_visibility[$now_index] ?? 0;
$vis_km = round($vis_km_raw / 1000, 1);
$vis_display = $vis_km > 50 ? '&rsaquo; 50' : $vis_km;

$cloud_cover_now = isset($hourly_cloud_cover[$now_index]) ? $hourly_cloud_cover[$now_index] : 0;
$uv_now = isset($hourly_uv_index[$now_index]) ? round($hourly_uv_index[$now_index], 1) : 0;

// Calcolo commento unificato
$visibility_comment = getVisibilityComment($vis_km, $cloud_cover_now, $uv_now);

$vis_index = match (true) {
  $vis_km < 2   => 0,
  $vis_km < 10  => 1,
  $vis_km < 30  => 2,
  default       => 3
};

$vis_icon = "bi-{$vis_index}-square";
$vis_color_class = "icon-visi-{$vis_index}";

$vis_text = match ($vis_index) {
  0 => 'Visibilità pessima',
  1 => 'Visibilità scarsa',
  2 => 'Visibilità moderata',
  3 => 'Visibilità ottima'
};

$tooltip_vis = htmlentities("
  <span>
  <strong>$vis_text</strong> ({$vis_index}/3)<br>
  <small>$visibility_comment</small>
  </span>
  ");

$uv_index_class = match (true) {
  $uv_now < 3   => 'uv-0',
  $uv_now < 6   => 'uv-1',
  $uv_now < 8   => 'uv-2',
  $uv_now < 11  => 'uv-3',
  default       => 'uv-4'
};
$uv_label_text = match (true) {
  $uv_now < 3   => 'Basso',
  $uv_now < 6   => 'Moderato',
  $uv_now < 8   => 'Alto',
  $uv_now < 11  => 'Molto alto',
  default       => 'Estremo'
};
?>



<!-- WIDGET VISIBILITÀ -->
<section class="widget">
  <header class="widget-header">
    <span class="widget-title">Visibilità</span>
    <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true" title="<?= $tooltip_vis ?>"></i>
  </header>
  
  <div class="widget-cont">
    <div class="widget-value"><strong><?= $vis_display ?> km</strong></div>
    <div class="widget-delta"><strong><i class="bi <?= $vis_icon ?> <?= $vis_color_class ?>"></i></strong></div>
  </div><!--widget-cont-->
  
  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Indice UV</span>
      <span class="widget-value <?= $uv_index_class ?>"><?= $uv_now ?> (<?= $uv_label_text ?>)</span>
    </div>

    <div class="data-row">
      <span class="widget-text">Nuvolosità</span>
      <span class="widget-value"><?= round($cloud_cover_now) ?>%</span>
    </div>
  </footer>

  </section><!--widget-->