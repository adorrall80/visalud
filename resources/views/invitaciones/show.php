<section class="auth-card invitation-entry">
    <div class="invitation-entry__mark">F</div>
    <p class="eyebrow">Invitación familiar</p>
    <h1>Únete a <?= $view->escape($invitacion['familia_nombre']) ?></h1>
    <p>Este enlace permite que te unas como <strong>Familiar</strong> utilizando la cuenta Google que elijas.</p>
    <?php if($error): ?><div class="alert alert--error"><?= $view->escape($error) ?></div><?php endif; ?>
    <?php if($usuario===null): ?>
        <a class="button button--google" href="/auth/google">Continuar con Google</a>
        <p class="invitation-entry__note">Después de ingresar podrás confirmar antes de unirte.</p>
    <?php else: ?>
        <div class="invitation-account"><span>Cuenta seleccionada</span><strong><?= $view->escape($usuario['nombre']) ?></strong><small><?= $view->escape($usuario['email']) ?></small></div>
        <form method="post" action="/invitaciones/aceptar"><?= $view->csrfField() ?><button class="button button--primary invitation-accept" type="submit">Aceptar y unirme a la familia</button></form>
        <p class="invitation-entry__note">La invitación se utilizará una sola vez.</p>
    <?php endif; ?>
</section>
