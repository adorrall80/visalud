<header class="page-heading">
    <div><p class="eyebrow">Archivos privados</p><h1>Documentos</h1><p class="muted">Recetas, órdenes, resultados e informes médicos de la familia.</p></div>
    <a class="button button--compact button--primary" href="/documentos/create">Cargar documento</a>
</header>

<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>

<form class="filter-bar filter-bar--compact" method="get" action="/documentos">
    <label><span>Tipo</span><select name="tipo_id"><option value="">Todos</option><?php foreach($tipos as $tipo): ?><option value="<?= $view->escape($tipo['id']) ?>" <?= (string)($filters['tipo_id'] ?? '') === (string)$tipo['id'] ? 'selected' : '' ?>><?= $view->escape($tipo['nombre']) ?></option><?php endforeach; ?></select></label>
    <button class="button button--compact button--secondary" type="submit">Filtrar</button>
</form>

<?php if($documentos === []): ?>
<section class="empty-state"><div class="empty-state__mark document-mark">D</div><h2>No hay documentos cargados</h2><p>Guarda órdenes, recetas, resultados o informes para encontrarlos cuando los necesites.</p><a class="button button--primary" href="/documentos/create">Cargar primer documento</a></section>
<?php else: ?>
<section class="document-list"><?php foreach($documentos as $documento): ?>
    <article class="document-row">
        <?php if(str_starts_with($documento['mime_type'],'image/')): ?><a class="document-row__icon document-row__preview" href="/documentos/<?= $view->escape($documento['id']) ?>/preview" data-appointment-modal="document-preview-<?= $view->escape($documento['id']) ?>" aria-label="Previsualizar <?= $view->escape($documento['nombre']) ?>"><img src="/documentos/<?= $view->escape($documento['id']) ?>/preview" alt="" loading="lazy"></a><?php else: ?><div class="document-row__icon">PDF</div><?php endif; ?>
        <div class="document-row__body"><div><span class="badge"><?= $view->escape($documento['tipo_nombre']) ?></span><a class="medication-card__person" href="/personas/<?= $view->escape($documento['persona_id']) ?>" aria-label="Ver ficha de <?= $view->escape($documento['persona_nombre']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2.4"></circle><path d="M5.8 16c.8-1.8 2-2.7 3.2-2.7s2.4.9 3.2 2.7M15 9h3M15 13h3"></path></svg><span><?= $view->escape($documento['persona_nombre']) ?></span></a><?php if(!empty($documento['atencion_id'])): ?><a class="origin-appointment-icon" href="/atenciones/<?= $view->escape($documento['atencion_id']) ?>" title="Ver atención de origen" aria-label="Ver atención de origen del documento"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M4 10h16M9 14h6"></path></svg></a><?php endif; ?></div><h2><?= $view->escape($documento['nombre']) ?></h2><p><?= $view->escape($documento['fecha_documento'] ?: substr($documento['created_at'],0,10)) ?><?= $documento['descripcion'] ? ' · ' . $view->escape($documento['descripcion']) : '' ?></p></div>
        <div class="document-row__actions"><a class="button button--compact button--secondary" href="/documentos/<?= $view->escape($documento['id']) ?>/download">Descargar</a><form method="post" action="/documentos/<?= $view->escape($documento['id']) ?>" onsubmit="return confirm('¿Eliminar este documento de forma permanente?')"><?= $view->csrfField() ?><input type="hidden" name="_method" value="DELETE"><button class="button button--compact button--danger" type="submit">Eliminar</button></form></div>
    </article>
    <?php if(str_starts_with($documento['mime_type'],'image/')): ?><?= $view->component('document-preview-dialog', ['documento'=>$documento]) ?><?php endif; ?>
<?php endforeach; ?></section>
<?php endif; ?>
