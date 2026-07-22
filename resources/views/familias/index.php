<header class="page-heading">
    <div><p class="eyebrow">Configuración</p><h1>Mis familias</h1><p class="muted">Selecciona un espacio o archívalo sin eliminar su información.</p></div>
    <a class="button button--compact button--secondary" href="/familias/create">Nueva familia</a>
</header>

<?php if($message=$view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>
<?php if($message=$view->flash('error')): ?><div class="alert alert--error"><?= $view->escape($message) ?></div><?php endif; ?>

<?php if($familias===[]): ?><section class="empty-panel"><div class="empty-panel__icon">F</div><div><h2>No tienes familias activas</h2><p>Puedes crear una nueva familia o restaurar una de las archivadas.</p></div></section><?php endif; ?>
<section class="list-stack">
    <?php foreach ($familias as $familia): ?>
        <article class="list-card family-card <?= (int)$familiaActivaId===(int)$familia['id']?'is-current':'' ?>">
            <div class="avatar avatar--family"><?= $view->escape(mb_strtoupper(mb_substr($familia['nombre'], 0, 1))) ?></div>
            <div class="list-card__content"><div class="family-card__title"><h2><?= $view->escape($familia['nombre']) ?></h2><?php if((int)$familiaActivaId===(int)$familia['id']): ?><span class="badge">En uso</span><?php endif; ?></div><p><?= $view->escape($familia['rol_nombre']) ?> · <?= $view->escape($familia['total_personas']) ?> personas · <?= $view->escape($familia['total_atenciones']) ?> atenciones · <?= $view->escape($familia['total_documentos']) ?> documentos</p></div>
            <div class="family-card__actions">
                <?php if((int)$familiaActivaId!==(int)$familia['id']): ?><form method="post" action="/familias/<?= $view->escape($familia['id']) ?>/seleccionar"><?= $view->csrfField() ?><button class="button button--compact button--secondary" type="submit">Seleccionar</button></form><?php endif; ?>
                <?php if($familia['rol_codigo']==='ADMINISTRADOR'): ?><form method="post" action="/familias/<?= $view->escape($familia['id']) ?>/archivar" onsubmit="return confirm('¿Archivar <?= $view->escape($familia['nombre']) ?>? Las personas y toda la información médica se conservarán.')"><?= $view->csrfField() ?><input type="hidden" name="_method" value="PUT"><button class="button button--compact button--archive" type="submit">Archivar</button></form><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if($familiasArchivadas!==[]): ?>
<section class="section-heading treatments-heading"><div><p class="eyebrow">Conservadas</p><h2>Familias archivadas</h2><p class="muted">No aparecen en el trabajo diario, pero mantienen todos sus datos.</p></div></section>
<section class="list-stack">
    <?php foreach($familiasArchivadas as $familia): ?><article class="list-card family-card family-card--archived"><div class="avatar avatar--family">A</div><div class="list-card__content"><h2><?= $view->escape($familia['nombre']) ?></h2><p>Archivada · <?= $view->escape($familia['total_personas']) ?> personas · <?= $view->escape($familia['total_atenciones']) ?> atenciones · <?= $view->escape($familia['total_documentos']) ?> documentos</p></div><?php if($familia['rol_codigo']==='ADMINISTRADOR'): ?><form method="post" action="/familias/<?= $view->escape($familia['id']) ?>/restaurar"><?= $view->csrfField() ?><input type="hidden" name="_method" value="PUT"><button class="button button--compact button--secondary" type="submit">Restaurar</button></form><?php endif; ?></article><?php endforeach; ?>
</section>
<?php endif; ?>
