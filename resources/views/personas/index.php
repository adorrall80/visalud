<header class="page-heading">
    <div><p class="eyebrow">Cuidado familiar</p><h1>Personas</h1><p class="muted">Fichas de las personas cuya salud organiza la familia.</p></div>
    <a class="button button--compact button--primary" href="/personas/create">Agregar persona</a>
</header>

<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>
<?php if ($message = $view->flash('error')): ?><div class="alert alert--error"><?= $view->escape($message) ?></div><?php endif; ?>

<?php if ($personas === []): ?>
    <section class="empty-state">
        <div class="empty-state__mark">+</div>
        <h2>Aún no hay personas registradas</h2>
        <p>Agrega la primera ficha para comenzar a organizar sus atenciones y medicamentos.</p>
        <a class="button button--primary" href="/personas/create">Crear primera ficha</a>
    </section>
<?php else: ?>
    <section class="people-grid">
        <?php foreach ($personas as $persona): ?>
            <a class="person-card <?= (int)($personaActiva['id']??0)===(int)$persona['id']?'is-active':'' ?>" href="/personas/<?= $view->escape($persona['id']) ?>">
                <div class="avatar avatar--large"><?= $view->escape(mb_strtoupper(mb_substr($persona['nombre'], 0, 1))) ?></div>
                <div class="person-card__main">
                    <h2><?= $view->escape($persona['nombre']) ?></h2>
                    <?php if((int)($personaActiva['id']??0)===(int)$persona['id']): ?><span class="active-person-badge">Persona activa</span><?php endif; ?>
                    <p><?= $persona['fecha_nacimiento'] ? $view->escape((new DateTimeImmutable($persona['fecha_nacimiento']))->diff(new DateTimeImmutable('today'))->y . ' años') : 'Edad no informada' ?></p>
                </div>
                <div class="person-stats"><span><strong><?= $view->escape($persona['total_atenciones']) ?></strong> atenciones</span><span><strong><?= $view->escape($persona['total_medicamentos']) ?></strong> medicamentos</span></div>
                <span class="arrow">→</span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
