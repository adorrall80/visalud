<?php $errors = $view->flash('errors', []); $old = $view->flash('old', []); ?>
<section class="setup-shell">
    <div class="setup-copy">
        <span class="pill">Primer paso</span>
        <h1>Crea tu espacio familiar</h1>
        <p>Aquí podrás reunir las atenciones, medicamentos y documentos de las personas que cuida tu familia.</p>
        <ul class="feature-list">
            <li>Información separada y privada por familia</li>
            <li>Acceso compartido con cuentas Google</li>
            <li>Historia organizada por cada persona</li>
        </ul>
    </div>
    <form class="form-card" method="post" action="/familias">
        <?= $view->csrfField() ?>
        <div>
            <p class="eyebrow">Configuración inicial</p>
            <h2>Nombre de la familia</h2>
            <p class="muted">Puedes cambiarlo más adelante.</p>
        </div>
        <label class="field">
            <span>Nombre</span>
            <input name="nombre" maxlength="150" required autofocus placeholder="Ej. Familia González" value="<?= $view->escape($old['nombre'] ?? '') ?>">
            <?php if (!empty($errors['nombre'][0])): ?><small class="field-error"><?= $view->escape($errors['nombre'][0]) ?></small><?php endif; ?>
        </label>
        <button class="button button--primary" type="submit">Crear espacio familiar</button>
    </form>
</section>
