<header class="page-heading page-heading--form">
    <div><a class="back-link" href="/personas">← Volver a personas</a><p class="eyebrow">Ficha familiar</p><h1><?= $method === 'POST' ? 'Agregar persona' : 'Editar persona' ?></h1></div>
</header>

<form class="record-form" method="post" action="<?= $view->escape($action) ?>">
    <?= $view->csrfField() ?>
    <?php if ($method !== 'POST'): ?><input type="hidden" name="_method" value="<?= $view->escape($method) ?>"><?php endif; ?>

    <section class="form-section">
        <div class="form-section__heading"><span>1</span><div><h2>Información personal</h2><p>Datos básicos para identificar la ficha.</p></div></div>
        <div class="form-grid">
            <label class="field field--wide"><span>Nombre completo *</span><input name="nombre" maxlength="150" required value="<?= $view->escape($persona['nombre'] ?? '') ?>" placeholder="Ej. Juan Pérez González"><?php if (!empty($errors['nombre'][0])): ?><small class="field-error"><?= $view->escape($errors['nombre'][0]) ?></small><?php endif; ?></label>
            <label class="field"><span>Identificación</span><input name="identificacion" maxlength="30" value="<?= $view->escape($persona['identificacion'] ?? '') ?>" placeholder="RUT u otra identificación"><?php if (!empty($errors['identificacion'][0])): ?><small class="field-error"><?= $view->escape($errors['identificacion'][0]) ?></small><?php endif; ?></label>
            <label class="field"><span>Fecha de nacimiento</span><input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" value="<?= $view->escape($persona['fecha_nacimiento'] ?? '') ?>"><?php if (!empty($errors['fecha_nacimiento'][0])): ?><small class="field-error"><?= $view->escape($errors['fecha_nacimiento'][0]) ?></small><?php endif; ?></label>
            <label class="field"><span>Grupo sanguíneo</span><select name="grupo_sanguineo"><option value="">No informado</option><?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $grupo): ?><option value="<?= $grupo ?>" <?= ($persona['grupo_sanguineo'] ?? '') === $grupo ? 'selected' : '' ?>><?= $grupo ?></option><?php endforeach; ?></select></label>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section__heading"><span>2</span><div><h2>Información de salud</h2><p>Antecedentes importantes para el cuidado diario.</p></div></div>
        <div class="form-grid">
            <label class="field"><span>Alergias conocidas</span><textarea name="alergias" rows="4" placeholder="Medicamentos, alimentos u otras alergias"><?= $view->escape($persona['alergias'] ?? '') ?></textarea></label>
            <label class="field"><span>Enfermedades crónicas</span><textarea name="enfermedades_cronicas" rows="4" placeholder="Hipertensión, diabetes u otras condiciones"><?= $view->escape($persona['enfermedades_cronicas'] ?? '') ?></textarea></label>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section__heading"><span>3</span><div><h2>Emergencia y observaciones</h2><p>Información útil frente a una situación urgente.</p></div></div>
        <div class="form-grid">
            <label class="field"><span>Contacto de emergencia</span><input name="contacto_emergencia" maxlength="150" value="<?= $view->escape($persona['contacto_emergencia'] ?? '') ?>"></label>
            <label class="field"><span>Teléfono de emergencia</span><input name="telefono_emergencia" maxlength="30" value="<?= $view->escape($persona['telefono_emergencia'] ?? '') ?>"></label>
            <label class="field field--wide"><span>Observaciones generales</span><textarea name="observaciones" rows="4"><?= $view->escape($persona['observaciones'] ?? '') ?></textarea></label>
        </div>
    </section>

    <div class="form-actions"><a class="button button--secondary" href="/personas">Cancelar</a><button class="button button--primary" type="submit"><?= $method === 'POST' ? 'Crear ficha' : 'Guardar cambios' ?></button></div>
</form>
