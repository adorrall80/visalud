<header class="page-heading">
    <div><p class="eyebrow">Historia familiar</p><h1>Atenciones</h1><p class="muted">Consultas médicas, terapias y exámenes en orden cronológico.</p></div>
    <a class="button button--compact button--primary" href="/atenciones/create">Registrar atención</a>
</header>

<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>

<form class="filter-bar" method="get" action="/atenciones">
    <label><span>Tipo</span><select name="tipo_id"><option value="">Todos</option><?php foreach ($tipos as $tipo): ?><option value="<?= $view->escape($tipo['id']) ?>" <?= (string)($filters['tipo_id'] ?? '') === (string)$tipo['id'] ? 'selected' : '' ?>><?= $view->escape($tipo['nombre']) ?></option><?php endforeach; ?></select></label>
    <label><span>Estado</span><select name="estado_id"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= $view->escape($estado['id']) ?>" <?= (string)($filters['estado_id'] ?? '') === (string)$estado['id'] ? 'selected' : '' ?>><?= $view->escape($estado['nombre']) ?></option><?php endforeach; ?></select></label>
    <label><span>Desde</span><input type="date" name="desde" value="<?= $view->escape($filters['desde'] ?? '') ?>"></label>
    <label><span>Hasta</span><input type="date" name="hasta" value="<?= $view->escape($filters['hasta'] ?? '') ?>"></label>
    <button class="button button--compact button--secondary" type="submit">Filtrar</button>
</form>

<?php if ($atenciones === []): ?>
    <section class="empty-state"><div class="empty-state__mark">+</div><h2>No hay atenciones registradas</h2><p>Registra una consulta, terapia o examen para comenzar la historia de salud.</p><a class="button button--primary" href="/atenciones/create">Registrar primera atención</a></section>
<?php else: ?>
    <section class="timeline">
        <?php foreach ($atenciones as $atencion): ?>
            <a class="timeline-item" href="/atenciones/<?= $view->escape($atencion['id']) ?>">
                <div class="timeline-item__date"><strong><?= $view->escape(substr($atencion['fecha_hora_formato'],0,5)) ?></strong><span><?= $view->escape(substr($atencion['fecha_hora_formato'],6,5)) ?></span></div>
                <div class="timeline-item__dot timeline-item__dot--<?= strtolower($view->escape($atencion['tipo_codigo'])) ?>"></div>
                <div class="timeline-item__body"><div class="timeline-item__top"><span class="badge"><?= $view->escape($atencion['tipo_nombre']) ?></span><span class="status status--<?= strtolower($view->escape($atencion['estado_codigo'])) ?>"><?= $view->escape($atencion['estado_nombre']) ?></span></div><h2><?= $view->escape($atencion['persona_nombre']) ?></h2><p><?= $view->escape($atencion['motivo'] ?: $atencion['especialidad'] ?: 'Sin descripción') ?></p></div>
                <span class="arrow">→</span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
