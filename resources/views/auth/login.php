<section class="auth-card">
    <p class="eyebrow">Visalud · Portal Familiar de Salud</p>
    <h1>Organiza la salud de tu familia</h1>
    <p>Accede con tu cuenta Google para consultar atenciones, medicamentos y documentos.</p>

    <?php if ($message = $view->flash('error')): ?>
        <div class="alert alert--error"><?= $view->escape($message) ?></div>
    <?php endif; ?>

    <a class="button button--google" href="/auth/google">Continuar con Google</a>
</section>
