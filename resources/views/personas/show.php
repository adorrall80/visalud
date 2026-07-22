<?php $age = $persona['fecha_nacimiento'] ? (new DateTimeImmutable($persona['fecha_nacimiento']))->diff(new DateTimeImmutable('today'))->y : null; ?>
<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>
<header class="profile-header">
    <div class="avatar avatar--profile"><?= $view->escape(mb_strtoupper(mb_substr($persona['nombre'], 0, 1))) ?></div>
    <div class="profile-header__main"><p class="eyebrow">Ficha familiar</p><h1><?= $view->escape($persona['nombre']) ?></h1><p><?= $age !== null ? $view->escape($age . ' años') : 'Edad no informada' ?><?= $persona['grupo_sanguineo'] ? ' · Grupo ' . $view->escape($persona['grupo_sanguineo']) : '' ?></p></div>
    <div class="header-actions"><a class="button button--compact button--primary" href="/atenciones/create?persona_id=<?= $view->escape($persona['id']) ?>">Registrar atención</a><a class="button button--compact button--secondary" href="/medicamentos/create?persona_id=<?= $view->escape($persona['id']) ?>">Agregar medicamento</a><a class="button button--compact button--secondary" href="/documentos/create?persona_id=<?= $view->escape($persona['id']) ?>">Cargar documento</a><a class="button button--compact button--secondary" href="/personas/<?= $view->escape($persona['id']) ?>/edit">Editar ficha</a></div>
</header>

<section class="profile-grid">
    <article class="panel"><p class="eyebrow">Información importante</p><div class="detail-list"><div><span>Alergias</span><strong><?= $view->escape($persona['alergias'] ?: 'No informadas') ?></strong></div><div><span>Enfermedades crónicas</span><strong><?= $view->escape($persona['enfermedades_cronicas'] ?: 'No informadas') ?></strong></div><div><span>Identificación</span><strong><?= $view->escape($persona['identificacion'] ?: 'No informada') ?></strong></div></div></article>
    <article class="panel"><p class="eyebrow">Contacto de emergencia</p><div class="detail-list"><div><span>Persona</span><strong><?= $view->escape($persona['contacto_emergencia'] ?: 'No informada') ?></strong></div><div><span>Teléfono</span><strong><?= $view->escape($persona['telefono_emergencia'] ?: 'No informado') ?></strong></div></div></article>
</section>

<section class="stats-row"><div><strong><?= $view->escape($persona['total_atenciones']) ?></strong><span>Atenciones</span></div><div><strong><?= $view->escape($persona['total_medicamentos']) ?></strong><span>Medicamentos</span></div><div><strong><?= $view->escape($persona['total_documentos']) ?></strong><span>Documentos</span></div></section>

<section class="empty-panel"><div class="empty-panel__icon">⌁</div><div><h2>Historia de atenciones</h2><p>Consulta las visitas, terapias y exámenes registrados para esta persona.</p></div><a class="button button--compact button--secondary" href="/atenciones?persona_id=<?= $view->escape($persona['id']) ?>">Ver atenciones</a></section>
<?php if($medicamentosActivos === []): ?>
<section class="empty-panel"><div class="empty-panel__icon">Rx</div><div><h2>Sin tratamientos activos</h2><p>Agrega un medicamento para organizar su dosis y horarios.</p></div><a class="button button--compact button--secondary" href="/medicamentos/create?persona_id=<?= $view->escape($persona['id']) ?>">Agregar medicamento</a></section>
<?php else: ?>
<section class="section-heading treatments-heading"><div><p class="eyebrow">Tratamientos</p><h2>Medicamentos activos</h2></div><a href="/medicamentos?persona_id=<?= $view->escape($persona['id']) ?>">Ver todos</a></section>
<section class="active-treatments"><?php foreach($medicamentosActivos as $medicamento): ?><a href="/medicamentos/<?= $view->escape($medicamento['id']) ?>"><span class="active-treatments__time"><?= $view->escape($medicamento['horarios_texto'] ?: 'Sin hora fija') ?></span><strong><?= $view->escape($medicamento['nombre']) ?></strong><small><?= $view->escape($medicamento['dosis']) ?></small></a><?php endforeach; ?></section>
<?php endif; ?>
<section class="empty-panel"><div class="empty-panel__icon">D</div><div><h2>Documentos médicos</h2><p>Consulta recetas, resultados e informes asociados a esta persona.</p></div><a class="button button--compact button--secondary" href="/documentos?persona_id=<?= $view->escape($persona['id']) ?>">Ver documentos</a></section>
