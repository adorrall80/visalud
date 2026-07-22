<header class="page-heading">
    <div><p class="eyebrow"><?= $view->escape($familia['nombre']) ?></p><h1>Integrantes</h1><p class="muted">Personas que pueden consultar la información familiar.</p></div>
</header>

<?php if ($message = $view->flash('success')): ?><div class="alert alert--success"><?= $view->escape($message) ?></div><?php endif; ?>
<?php if ($message = $view->flash('error')): ?><div class="alert alert--error"><?= $view->escape($message) ?></div><?php endif; ?>

<div class="two-column <?= $familia['rol_codigo']!=='ADMINISTRADOR'?'members-layout--single':'' ?>">
    <section class="panel">
        <h2>Miembros actuales</h2>
        <div class="list-stack list-stack--inside">
            <?php foreach ($integrantes as $integrante): ?>
                <article class="member-row">
                    <div class="avatar"><?= $view->escape(mb_strtoupper(mb_substr($integrante['nombre'], 0, 1))) ?></div>
                    <div><strong><?= $view->escape($integrante['nombre']) ?></strong><span><?= $view->escape($integrante['email']) ?></span></div>
                    <span class="badge"><?= $view->escape($integrante['rol_nombre']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if($familia['rol_codigo']==='ADMINISTRADOR'): ?>
    <form class="panel form-stack invitation-create" method="post" action="/familias/invitaciones">
        <?= $view->csrfField() ?>
        <div><p class="eyebrow">Acceso seguro</p><h2>Invitar integrante</h2><p class="muted">Genera un enlace para una persona. Podrá elegir con qué cuenta Google ingresar.</p></div>
        <ul class="invitation-rules"><li>Rol asignado: Familiar</li><li>Vigencia: 24 horas</li><li>Uso permitido: una persona</li></ul>
        <button class="button button--primary" type="submit">Generar enlace</button>
    </form>
    <?php endif; ?>
</div>

<?php if(is_array($invitacionGenerada)): ?>
<section class="generated-invitation" aria-labelledby="generated-invitation-title">
    <div><p class="eyebrow">Enlace listo</p><h2 id="generated-invitation-title">Cópialo y compártelo</h2><p>Por seguridad, el enlace completo se muestra solamente ahora.</p></div>
    <div class="copy-invitation"><input id="generated-invitation-url" readonly value="<?= $view->escape($invitacionGenerada['url']) ?>" aria-label="Enlace de invitación"><button class="button button--primary" type="button" data-copy-invitation="generated-invitation-url">Copiar enlace</button></div>
    <p class="copy-feedback" data-copy-feedback aria-live="polite"></p>
</section>
<?php endif; ?>

<?php if($familia['rol_codigo']==='ADMINISTRADOR'): ?>
<section class="section-heading treatments-heading"><div><p class="eyebrow">Control de acceso</p><h2>Invitaciones generadas</h2></div></section>
<?php if($invitaciones===[]): ?>
<section class="empty-panel"><div class="empty-panel__icon">I</div><div><h2>Sin invitaciones</h2><p>Los enlaces que generes aparecerán aquí con su estado.</p></div></section>
<?php else: ?>
<section class="invitation-list"><?php foreach($invitaciones as $invitacion): ?><article class="invitation-row" style="--invite-color:<?= $view->escape($invitacion['estado_color']) ?>"><span class="invitation-row__mark"></span><div><div class="invitation-row__top"><strong>Invitación #<?= $view->escape($invitacion['id']) ?></strong><span><?= $view->escape($invitacion['estado_nombre']) ?></span></div><p><?= $view->escape($invitacion['rol_nombre']) ?> · Vence <?= $view->escape(date('d-m-Y H:i',strtotime($invitacion['expira_at']))) ?></p><?php if($invitacion['aceptada_por_email']): ?><small>Utilizada por <?= $view->escape($invitacion['aceptada_por_nombre']) ?> · <?= $view->escape($invitacion['aceptada_por_email']) ?></small><?php elseif($invitacion['estado_codigo']==='PENDIENTE'): ?><small>El token no se vuelve a mostrar. Genera otro enlace si lo perdiste.</small><?php endif; ?></div><?php if($invitacion['estado_codigo']==='PENDIENTE'): ?><form method="post" action="/familias/invitaciones/<?= $view->escape($invitacion['id']) ?>/revocar" onsubmit="return confirm('¿Revocar esta invitación? El enlace dejará de funcionar.')"><?= $view->csrfField() ?><input type="hidden" name="_method" value="PUT"><button class="button button--compact button--danger" type="submit">Revocar</button></form><?php endif; ?></article><?php endforeach; ?></section>
<?php endif; ?>
<?php endif; ?>
