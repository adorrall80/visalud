<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>

<header class="dashboard-heading">
    <div><p class="eyebrow"><?= $view->escape($familia['nombre']) ?></p><h1>Calendario de salud</h1><p>Revisa y organiza las citas de <?= $personaSeleccionada ? $view->escape($personaActiva['nombre'] ?? '') : 'tu familia' ?>.</p></div>
    <?php if($personaSeleccionada): ?><a class="button button--primary" href="/atenciones/create">+ Nueva atención</a><?php endif; ?>
</header>

<section class="dashboard-toolbar dashboard-toolbar--actions"><div><?php if($personaSeleccionada): ?><p class="eyebrow">Persona activa</p><strong><?= $view->escape($personaActiva['nombre'] ?? '') ?></strong><?php else: ?><p class="eyebrow">Antes de comenzar</p><strong>Selecciona una persona en la parte superior.</strong><?php endif; ?></div><div class="quick-actions"><a href="/atenciones/create">+ Atención</a><a href="/medicamentos/create">+ Medicamento</a><a href="/documentos/create">+ Documento</a></div></section>

<?php if(!$personaSeleccionada): ?>
<section class="empty-panel"><div class="empty-panel__icon">A</div><div><h2>Selecciona una persona</h2><p>El calendario mostrará exclusivamente sus consultas, terapias y exámenes.</p></div><a class="button button--compact button--secondary" href="/personas">Ver personas</a></section>
<?php else: ?>
<section class="health-calendar" aria-labelledby="calendar-title" data-calendar-viewer>
    <header class="calendar-toolbar">
        <div><p class="eyebrow">Agenda mensual</p><h2 id="calendar-title"><?= $view->escape($tituloMes) ?></h2></div>
        <div class="calendar-toolbar__actions">
            <nav class="calendar-navigation" aria-label="Navegar por meses"><a href="/?mes=<?= $view->escape($mesAnterior) ?>" aria-label="Mes anterior">←</a><a href="/">Hoy</a><a href="/?mes=<?= $view->escape($mesSiguiente) ?>" aria-label="Mes siguiente">→</a></nav>
            <div class="calendar-view-toggle" role="group" aria-label="Forma de visualizar las citas">
                <button class="is-active" type="button" data-calendar-view="calendar" aria-pressed="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"></path></svg><span>Calendario</span></button>
                <button type="button" data-calendar-view="list" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12"></path><circle cx="4" cy="6" r="1"></circle><circle cx="4" cy="12" r="1"></circle><circle cx="4" cy="18" r="1"></circle></svg><span>Lista</span></button>
            </div>
        </div>
    </header>
    <div class="calendar-legend" aria-label="Colores de estados"><?php foreach($estadosAtencion as $estado): ?><span><i style="--event-color:<?= $view->escape($estado['color']) ?>"></i><?= $view->escape($estado['nombre']) ?></span><?php endforeach; ?></div>
    <div class="calendar-grid-scroll" data-calendar-panel="calendar"><div class="calendar-grid" role="grid" aria-label="<?= $view->escape($tituloMes) ?>">
        <?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $weekday): ?><div class="calendar-weekday" role="columnheader"><?= $weekday ?></div><?php endforeach; ?>
        <?php foreach($calendario as $dia): ?><article class="calendar-day <?= !$dia['del_mes']?'is-outside':'' ?> <?= $dia['hoy']?'is-today':'' ?>" role="gridcell">
            <a class="calendar-day__number" href="/atenciones/create?fecha=<?= $view->escape($dia['fecha']) ?>" aria-label="Agregar atención el <?= $view->escape($dia['fecha']) ?>"><?= $view->escape($dia['numero']) ?><span>+</span></a>
            <div class="calendar-events"><?php foreach($dia['atenciones'] as $atencion): ?><a class="calendar-event" style="--event-color:<?= $view->escape($atencion['estado_color']) ?>" href="/atenciones/<?= $view->escape($atencion['id']) ?>" data-appointment-modal="appointment-modal-<?= $view->escape($atencion['id']) ?>" title="<?= $view->escape($atencion['estado_nombre'].' · '.$atencion['tipo_nombre']) ?>"><time><?= $view->escape(substr($atencion['fecha_hora_local'],11,5)) ?></time><strong><?= $view->escape($atencion['tipo_nombre']) ?></strong><small><?= $view->escape($atencion['estado_nombre']) ?></small></a><?php endforeach; ?></div>
        </article><?php endforeach; ?>
    </div></div>
    <div class="calendar-agenda" data-calendar-panel="list" hidden>
        <?php if($atencionesMes===[]): ?><div class="calendar-empty">No hay citas registradas durante este mes.</div><?php else: ?>
        <?php $ultimaFechaAgenda=''; foreach($atencionesMes as $atencion): $fechaAgenda=substr($atencion['fecha_hora_local'],0,10); if($fechaAgenda!==$ultimaFechaAgenda): $ultimaFechaAgenda=$fechaAgenda; ?><h3 class="agenda-date"><?= $view->escape((new DateTimeImmutable($fechaAgenda))->format('d-m-Y')) ?></h3><?php endif; ?><a class="agenda-event" style="--event-color:<?= $view->escape($atencion['estado_color']) ?>" href="/atenciones/<?= $view->escape($atencion['id']) ?>" data-appointment-modal="appointment-modal-<?= $view->escape($atencion['id']) ?>"><time><?= $view->escape(substr($atencion['fecha_hora_local'],11,5)) ?></time><span><strong><?= $view->escape($atencion['tipo_nombre']) ?></strong><small><?= $view->escape($atencion['estado_nombre']) ?><?= $atencion['profesional']?' · '.$view->escape($atencion['profesional']):'' ?></small></span><b>→</b></a><?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php foreach($atencionesMes as $atencion): ?>
<dialog class="appointment-modal" id="appointment-modal-<?= $view->escape($atencion['id']) ?>" style="--event-color:<?= $view->escape($atencion['estado_color']) ?>" aria-labelledby="appointment-title-<?= $view->escape($atencion['id']) ?>">
    <div class="appointment-modal__header"><div><span class="appointment-modal__status"><i></i><?= $view->escape($atencion['estado_nombre']) ?></span><h2 id="appointment-title-<?= $view->escape($atencion['id']) ?>"><?= $view->escape($atencion['tipo_nombre']) ?></h2></div><form method="dialog"><button type="submit" aria-label="Cerrar detalle">×</button></form></div>
    <div class="appointment-modal__body">
        <dl class="appointment-modal__facts"><div><dt>Fecha y hora</dt><dd><?= $view->escape($atencion['fecha_hora_formato']) ?></dd></div><div><dt>Profesional</dt><dd><?= $view->escape($atencion['profesional'] ?: 'No informado') ?></dd></div><div><dt>Especialidad</dt><dd><?= $view->escape($atencion['especialidad'] ?: 'No informada') ?></dd></div><div><dt>Lugar</dt><dd><?= $view->escape($atencion['centro_medico'] ?: 'No informado') ?></dd></div></dl>
        <?php foreach(['motivo'=>'Motivo','diagnostico_resultado'=>'Diagnóstico o resultado','indicaciones'=>'Indicaciones','temas_abordados'=>'Temas abordados','acuerdos'=>'Acuerdos'] as $campo=>$etiqueta): if(!empty($atencion[$campo])): ?><section class="appointment-modal__section"><h3><?= $etiqueta ?></h3><p><?= nl2br($view->escape($atencion[$campo])) ?></p></section><?php endif; endforeach; ?>
    </div>
    <footer class="appointment-modal__footer"><form method="dialog"><button class="button button--secondary" type="submit">Cerrar</button></form><a class="button button--primary" href="/atenciones/<?= $view->escape($atencion['id']) ?>">Ver detalle completo</a></footer>
</dialog>
<?php endforeach; ?>
<?php endif; ?>

<section class="section-heading treatments-heading"><div><p class="eyebrow">Resumen</p><h2>Otros registros</h2></div></section>
<section class="dashboard-grid">
    <a class="module-card module-card--green" href="/personas"><div class="module-icon">P</div><div><h3>Personas</h3><p>Fichas e historia familiar</p></div><span class="arrow">→</span></a>
    <a class="module-card module-card--blue" href="/atenciones"><div class="module-icon">A</div><div><h3>Atenciones</h3><p>Consultas, terapias y exámenes</p></div><span class="arrow">→</span></a>
    <a class="module-card module-card--amber" href="/medicamentos"><div class="module-icon">M</div><div><h3>Medicamentos</h3><p>Tratamientos y horarios activos</p></div><span class="arrow">→</span></a>
</section>

<section class="section-heading treatments-heading"><div><p class="eyebrow">Para hoy</p><h2>Tratamientos activos</h2></div><a href="/medicamentos">Ver todos</a></section>
<?php if($medicamentosActivos === []): ?>
<section class="empty-panel"><div class="empty-panel__icon">Rx</div><div><h2>Sin tratamientos activos</h2><p>Cuando registres un medicamento activo, sus horarios aparecerán aquí.</p></div><a class="button button--compact button--primary" href="/medicamentos/create">Agregar medicamento</a></section>
<?php else: ?>
<section class="active-treatments"><?php foreach(array_slice($medicamentosActivos,0,4) as $medicamento): ?><a href="/medicamentos/<?= $view->escape($medicamento['id']) ?>"><span class="active-treatments__time"><?= $view->escape($medicamento['horarios_texto'] ?: 'Sin hora fija') ?></span><strong><?= $view->escape($medicamento['nombre']) ?></strong><small><?= $view->escape($medicamento['persona_nombre'] . ' · ' . $medicamento['dosis']) ?></small></a><?php endforeach; ?></section>
<?php endif; ?>
<section class="section-heading treatments-heading"><div><p class="eyebrow">Archivos privados</p><h2>Documentos recientes</h2></div><a href="/documentos">Ver todos</a></section>
<?php if($documentosRecientes === []): ?>
<section class="empty-panel"><div class="empty-panel__icon">D</div><div><h2>Sin documentos guardados</h2><p>Carga recetas, resultados e informes para mantenerlos disponibles.</p></div><a class="button button--compact button--secondary" href="/documentos/create">Cargar documento</a></section>
<?php else: ?>
<section class="recent-documents"><?php foreach($documentosRecientes as $documento): ?><a href="/documentos/<?= $view->escape($documento['id']) ?>/download"><span><?= $view->escape($documento['tipo_nombre']) ?></span><strong><?= $view->escape($documento['nombre']) ?></strong><small><?= $view->escape($documento['persona_nombre']) ?></small></a><?php endforeach; ?></section>
<?php endif; ?>
