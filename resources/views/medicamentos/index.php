<header class="page-heading">
    <div><p class="eyebrow">Tratamientos</p><h1>Medicamentos</h1><p class="muted">Dosis, horarios y estado de los tratamientos familiares.</p></div>
    <a class="button button--compact button--primary" href="/medicamentos/create">Registrar medicamento</a>
</header>

<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>

<form class="filter-bar filter-bar--compact" method="get" action="/medicamentos">
    <label><span>Estado</span><select name="estado_id"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= $view->escape($estado['id']) ?>" <?= (string)($filters['estado_id'] ?? '') === (string)$estado['id'] ? 'selected' : '' ?>><?= $view->escape($estado['nombre']) ?></option><?php endforeach; ?></select></label>
    <button class="button button--compact button--secondary" type="submit">Filtrar</button>
</form>

<?php if ($medicamentos === []): ?>
    <section class="empty-state"><div class="empty-state__mark medication-mark">M</div><h2>No hay medicamentos registrados</h2><p>Registra un tratamiento para organizar su dosis, frecuencia y horarios.</p><a class="button button--primary" href="/medicamentos/create">Registrar primer medicamento</a></section>
<?php else: ?>
    <section class="medication-grid">
        <?php foreach ($medicamentos as $medicamento): ?>
            <article class="medication-card">
                <div class="medication-card__icon">Rx</div>
                <div class="medication-card__body"><div class="medication-card__top"><span class="status status--<?= strtolower($view->escape($medicamento['estado_codigo'])) ?>"><?= $view->escape($medicamento['estado_nombre']) ?></span><a class="medication-card__person" href="/personas/<?= $view->escape($medicamento['persona_id']) ?>" aria-label="Ver ficha de <?= $view->escape($medicamento['persona_nombre']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2.4"></circle><path d="M5.8 16c.8-1.8 2-2.7 3.2-2.7s2.4.9 3.2 2.7M15 9h3M15 13h3"></path></svg><span><?= $view->escape($medicamento['persona_nombre']) ?></span></a><?php if(!empty($medicamento['atencion_id'])): ?><a class="origin-appointment-icon" href="/atenciones/<?= $view->escape($medicamento['atencion_id']) ?>" title="Ver cita de origen" aria-label="Ver cita de origen del medicamento"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M4 10h16M9 14h6"></path></svg></a><?php endif; ?></div><h2><a class="medication-card__primary" href="/medicamentos/<?= $view->escape($medicamento['id']) ?>"><?= $view->escape($medicamento['nombre']) ?></a></h2><p><strong><?= $view->escape($medicamento['dosis']) ?></strong><?= $medicamento['frecuencia'] ? ' · ' . $view->escape($medicamento['frecuencia']) : '' ?></p><div class="schedule-line"><?= $medicamento['horarios_texto'] ? 'Horarios: ' . $view->escape($medicamento['horarios_texto']) : 'Sin horarios específicos' ?></div></div>
                <span class="arrow">→</span>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
