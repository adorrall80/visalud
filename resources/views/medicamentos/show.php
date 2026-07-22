<?php if($message=$view->flash('success')):?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>
<header class="profile-header medication-header">
    <div class="medication-detail-icon">Rx</div>
    <div class="profile-header__main"><p class="eyebrow">Tratamiento de <?= $view->escape($medicamento['persona_nombre']) ?></p><h1><?= $view->escape($medicamento['nombre']) ?></h1><p><?= $view->escape($medicamento['dosis']) ?> · <?= $view->escape($medicamento['estado_nombre']) ?></p></div>
    <a class="button button--compact button--secondary" href="/medicamentos/<?= $view->escape($medicamento['id']) ?>/edit">Editar medicamento</a>
</header>

<section class="profile-grid"><article class="panel"><p class="eyebrow">Uso indicado</p><div class="detail-list"><div><span>Dosis</span><strong><?= $view->escape($medicamento['dosis']) ?></strong></div><div><span>Frecuencia</span><strong><?= $view->escape($medicamento['frecuencia'] ?: 'No informada') ?></strong></div><div><span>Indicaciones</span><strong><?= $view->escape($medicamento['indicaciones'] ?: 'No informadas') ?></strong></div></div></article><article class="panel"><p class="eyebrow">Período</p><div class="detail-list"><div><span>Inicio</span><strong><?= $view->escape($medicamento['fecha_inicio']) ?></strong></div><div><span>Término</span><strong><?= $view->escape($medicamento['fecha_termino'] ?: 'Sin fecha definida') ?></strong></div><div><span>Estado</span><strong><?= $view->escape($medicamento['estado_nombre']) ?></strong></div></div></article></section>

<section class="schedule-panel"><div><p class="eyebrow">Horarios diarios</p><h2><?= $medicamento['horarios'] === [] ? 'Sin horarios específicos' : 'Recordatorio de administración' ?></h2></div><div class="hour-pills"><?php foreach($medicamento['horarios'] as $hora): ?><span><?= $view->escape($hora) ?></span><?php endforeach; ?></div></section>

<div class="record-links"><a href="/personas/<?= $view->escape($medicamento['persona_id']) ?>">Ver ficha de la persona</a><?php if($medicamento['atencion_id']): ?><a href="/atenciones/<?= $view->escape($medicamento['atencion_id']) ?>">Ver atención de origen</a><?php endif; ?></div>
