<?php $medicamentoAgregado=$view->flash('medicamento_agregado'); $documentoAgregado=$view->flash('documento_agregado'); if($message=$view->flash('success')):?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>
<header class="profile-header attention-header">
    <div class="attention-type-icon"><?= $view->escape(mb_substr($atencion['tipo_nombre'],0,1)) ?></div>
    <div class="profile-header__main"><p class="eyebrow"><?= $view->escape($atencion['tipo_nombre']) ?></p><h1><?= $view->escape($atencion['persona_nombre']) ?></h1><p><?= $view->escape($atencion['fecha_hora_formato']) ?> · <?= $view->escape($atencion['estado_nombre']) ?></p></div>
    <a class="button button--compact button--secondary" href="/atenciones/<?= $view->escape($atencion['id']) ?>/ficha-pdf">Generar ficha PDF</a>
    <a class="button button--compact button--secondary" href="/atenciones/<?= $view->escape($atencion['id']) ?>/edit">Editar atención</a>
</header>

<section class="profile-grid"><article class="panel"><p class="eyebrow">Atención</p><div class="detail-list"><div><span>Profesional</span><strong><?= $view->escape($atencion['profesional'] ?: 'No informado') ?></strong></div><div><span>Especialidad</span><strong><?= $view->escape($atencion['especialidad'] ?: 'No informada') ?></strong></div><div><span>Centro o lugar</span><strong><?= $view->escape($atencion['centro_medico'] ?: 'No informado') ?></strong></div><div><span>Próximo control o sesión</span><strong><?= $view->escape($atencion['proxima_fecha'] ?: 'No informado') ?></strong></div></div></article><article class="panel"><p class="eyebrow">Registro</p><div class="detail-list"><div><span>Registrado por</span><strong><?= $view->escape($atencion['registrado_por_nombre']) ?></strong></div><div><span>Estado</span><strong><?= $view->escape($atencion['estado_nombre']) ?></strong></div></div></article></section>

<section class="clinical-grid">
    <article class="clinical-card"><span>Motivo</span><p><?= nl2br($view->escape($atencion['motivo'] ?: 'Sin información')) ?></p></article>
    <article class="clinical-card"><span>Diagnóstico o resultado</span><p><?= nl2br($view->escape($atencion['diagnostico_resultado'] ?: 'Sin información')) ?></p></article>
    <article class="clinical-card"><span>Indicaciones</span><p><?= nl2br($view->escape($atencion['indicaciones'] ?: 'Sin información')) ?></p></article>
    <?php if($atencion['tipo_codigo']==='TERAPIA'): ?><article class="clinical-card"><span>Temas abordados</span><p><?= nl2br($view->escape($atencion['temas_abordados'] ?: 'Sin información')) ?></p></article><article class="clinical-card"><span>Acuerdos y tareas</span><p><?= nl2br($view->escape($atencion['acuerdos'] ?: 'Sin información')) ?></p></article><?php endif; ?>
</section>

<section class="section-heading treatments-heading"><div><p class="eyebrow">Tratamiento indicado</p><h2>Medicamentos de esta atención<?= $medicamentos!==[]?' ('.count($medicamentos).')':'' ?></h2></div><a href="/medicamentos/create?atencion_id=<?= $view->escape($atencion['id']) ?>">+ Agregar medicamento</a></section>
<?php if($medicamentos===[]): ?>
<section class="empty-panel"><div class="empty-panel__icon">Rx</div><div><h2>Sin medicamentos asociados</h2><p>Cuando agregues un medicamento desde esta atención aparecerá en esta sección.</p></div><a class="button button--compact button--primary" href="/medicamentos/create?atencion_id=<?= $view->escape($atencion['id']) ?>">Agregar medicamento</a></section>
<?php else: ?>
<section class="medication-grid"><?php foreach($medicamentos as $medicamento): ?><a class="medication-card" href="/medicamentos/<?= $view->escape($medicamento['id']) ?>"><div class="medication-card__icon">Rx</div><div class="medication-card__body"><div class="medication-card__top"><span class="status status--<?= $view->escape(strtolower($medicamento['estado_codigo'])) ?>"><?= $view->escape($medicamento['estado_nombre']) ?></span><span><?= $view->escape($medicamento['fecha_inicio']) ?></span></div><h2><?= $view->escape($medicamento['nombre']) ?></h2><p><?= $view->escape($medicamento['dosis']) ?><?= $medicamento['frecuencia']?' · '.$view->escape($medicamento['frecuencia']):'' ?></p><?php if($medicamento['horarios_texto']): ?><div class="schedule-line">Horarios: <?= $view->escape($medicamento['horarios_texto']) ?></div><?php endif; ?></div><span class="arrow">→</span></a><?php endforeach; ?></section>
<?php endif; ?>

<section class="section-heading treatments-heading"><div><p class="eyebrow">Archivos de respaldo</p><h2>Documentos de esta atención<?= $documentos!==[]?' ('.count($documentos).')':'' ?></h2></div><a href="/documentos/create?atencion_id=<?= $view->escape($atencion['id']) ?>">+ Cargar documento</a></section>
<?php if($documentos===[]): ?>
<section class="empty-panel"><div class="empty-panel__icon">D</div><div><h2>Sin documentos asociados</h2><p>Cuando cargues un documento desde esta atención aparecerá en esta sección.</p></div><a class="button button--compact button--primary" href="/documentos/create?atencion_id=<?= $view->escape($atencion['id']) ?>">Cargar documento</a></section>
<?php else: ?>
<section class="document-list attention-document-list"><?php foreach($documentos as $documento): ?>
    <article class="document-row">
        <?php if(str_starts_with($documento['mime_type'],'image/')): ?><a class="document-row__icon document-row__preview" href="/documentos/<?= $view->escape($documento['id']) ?>/preview" data-appointment-modal="document-preview-<?= $view->escape($documento['id']) ?>" aria-label="Previsualizar <?= $view->escape($documento['nombre']) ?>"><img src="/documentos/<?= $view->escape($documento['id']) ?>/preview" alt="" loading="lazy"></a><?php else: ?><div class="document-row__icon">PDF</div><?php endif; ?>
        <div class="document-row__body"><div><span class="badge"><?= $view->escape($documento['tipo_nombre']) ?></span></div><h2><?= $view->escape($documento['nombre']) ?></h2><p><?= $view->escape($documento['fecha_documento'] ?: substr($documento['created_at'],0,10)) ?><?= $documento['descripcion'] ? ' · ' . $view->escape($documento['descripcion']) : '' ?></p></div>
        <div class="document-row__actions"><a class="button button--compact button--secondary" href="/documentos/<?= $view->escape($documento['id']) ?>/download">Descargar</a><form method="post" action="/documentos/<?= $view->escape($documento['id']) ?>" onsubmit="return confirm('¿Eliminar este documento de forma permanente?')"><?= $view->csrfField() ?><input type="hidden" name="_method" value="DELETE"><button class="button button--compact button--danger" type="submit">Eliminar</button></form></div>
    </article>
    <?php if(str_starts_with($documento['mime_type'],'image/')): ?><?= $view->component('document-preview-dialog', ['documento'=>$documento]) ?><?php endif; ?>
<?php endforeach; ?></section>
<?php endif; ?>

<?php if(is_array($medicamentoAgregado)): ?>
<dialog class="appointment-modal medication-added-modal" data-auto-open-modal aria-labelledby="medication-added-title">
    <div class="appointment-modal__header"><div><span class="appointment-modal__status"><i></i>Registro completado</span><h2 id="medication-added-title">Medicamento agregado</h2></div><form method="dialog"><button type="submit" aria-label="Cerrar confirmación">×</button></form></div>
    <div class="appointment-modal__body"><div class="medication-added-summary"><span class="medication-added-summary__icon">✓</span><div><span>Se agregó a esta atención</span><strong><?= $view->escape($medicamentoAgregado['nombre']??'Medicamento') ?></strong><p>Dosis: <?= $view->escape($medicamentoAgregado['dosis']??'No informada') ?></p></div></div></div>
    <footer class="appointment-modal__footer"><form method="dialog"><button class="button button--secondary" type="submit">Volver a la cita</button></form><a class="button button--primary" href="/medicamentos/<?= $view->escape($medicamentoAgregado['id']??'') ?>">Ver medicamento</a></footer>
</dialog>
<?php endif; ?>

<?php if(is_array($documentoAgregado)): ?>
<dialog class="appointment-modal document-added-modal" data-auto-open-modal aria-labelledby="document-added-title">
    <div class="appointment-modal__header"><div><span class="appointment-modal__status"><i></i>Registro completado</span><h2 id="document-added-title">Documento agregado</h2></div><form method="dialog"><button type="submit" aria-label="Cerrar confirmación">×</button></form></div>
    <div class="appointment-modal__body"><div class="medication-added-summary"><span class="medication-added-summary__icon">D</span><div><span>Se agregó a esta atención</span><strong><?= $view->escape($documentoAgregado['nombre']??'Documento') ?></strong><p>Tipo: <?= $view->escape($documentoAgregado['tipo_nombre']??'Documento') ?></p><p>Formato: <?= $view->escape($documentoAgregado['formato']??'Archivo') ?></p></div></div></div>
    <footer class="appointment-modal__footer"><form method="dialog"><button class="button button--secondary" type="submit">Volver a la atención</button></form><?php if(str_starts_with((string)($documentoAgregado['mime_type']??''),'image/')): ?><a class="button button--primary" href="/documentos/<?= $view->escape($documentoAgregado['id']??'') ?>/preview" data-appointment-modal="document-preview-<?= $view->escape($documentoAgregado['id']??'') ?>">Previsualizar</a><?php else: ?><a class="button button--primary" href="/documentos/<?= $view->escape($documentoAgregado['id']??'') ?>/download">Descargar</a><?php endif; ?></footer>
</dialog>
<?php endif; ?>
