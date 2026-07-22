<header class="page-heading page-heading--form"><div><a class="back-link" href="/medicamentos">← Volver a medicamentos</a><p class="eyebrow">Tratamientos</p><h1><?= $view->escape($title) ?></h1><p class="muted">Registra la dosis y los horarios indicados por el profesional de salud.</p></div></header>

<?php if (!empty($errors['general'][0])): ?><div class="alert alert--error"><?= $view->escape($errors['general'][0]) ?></div><?php endif; ?>

<form class="record-form" method="post" action="<?= $view->escape($action) ?>">
    <?= $view->csrfField() ?>
    <?php if ($method !== 'POST'): ?><input type="hidden" name="_method" value="<?= $view->escape($method) ?>"><?php endif; ?>

    <section class="form-section"><div class="form-section__heading"><span>1</span><div><h2>Tratamiento</h2><p>Persona, medicamento y estado actual.</p></div></div><div class="form-grid">
        <div class="field context-person"><span>Persona</span><strong><?= $view->escape($personaActiva['nombre']) ?></strong><small>Definida por el selector superior.</small><input type="hidden" name="persona_id" value="<?= $view->escape($personaActiva['id']) ?>"></div>
        <label class="field"><span>Estado *</span><select name="estado_id" required><?php foreach($estados as $estado): ?><option value="<?= $view->escape($estado['id']) ?>" <?= (string)($medicamento['estado_id'] ?? '') === (string)$estado['id'] ? 'selected' : '' ?>><?= $view->escape($estado['nombre']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Nombre del medicamento *</span><input name="nombre" maxlength="150" required value="<?= $view->escape($medicamento['nombre'] ?? '') ?>" placeholder="Ej.: Losartán"><?php if(!empty($errors['nombre'][0])):?><small class="field-error"><?= $view->escape($errors['nombre'][0]) ?></small><?php endif;?></label>
        <label class="field"><span>Dosis *</span><input name="dosis" maxlength="100" required value="<?= $view->escape($medicamento['dosis'] ?? '') ?>" placeholder="Ej.: 50 mg, 1 comprimido"><?php if(!empty($errors['dosis'][0])):?><small class="field-error"><?= $view->escape($errors['dosis'][0]) ?></small><?php endif;?></label>
        <label class="field"><span>Frecuencia</span><input name="frecuencia" maxlength="150" value="<?= $view->escape($medicamento['frecuencia'] ?? '') ?>" placeholder="Ej.: Cada 12 horas"></label>
        <label class="field"><span>Atención de origen</span><select name="atencion_id"><option value="">Sin atención asociada</option><?php foreach($atenciones as $atencion): ?><option value="<?= $view->escape($atencion['id']) ?>" <?= (string)($medicamento['atencion_id'] ?? '') === (string)$atencion['id'] ? 'selected' : '' ?>><?= $view->escape($atencion['persona_nombre'] . ' · ' . $atencion['tipo_nombre'] . ' · ' . $atencion['fecha_hora_formato']) ?></option><?php endforeach; ?></select><small>Si seleccionas una atención, debe corresponder a la misma persona.</small></label>
    </div></section>

    <section class="form-section"><div class="form-section__heading"><span>2</span><div><h2>Período e indicaciones</h2><p>Inicio, término e instrucciones de uso.</p></div></div><div class="form-grid">
        <label class="field"><span>Fecha de inicio *</span><input type="date" name="fecha_inicio" required value="<?= $view->escape($medicamento['fecha_inicio'] ?? date('Y-m-d')) ?>"><?php if(!empty($errors['fecha_inicio'][0])):?><small class="field-error"><?= $view->escape($errors['fecha_inicio'][0]) ?></small><?php endif;?></label>
        <label class="field"><span>Fecha de término</span><input type="date" name="fecha_termino" value="<?= $view->escape($medicamento['fecha_termino'] ?? '') ?>"></label>
        <label class="field field--wide"><span>Indicaciones</span><textarea name="indicaciones" rows="4" placeholder="Cómo tomarlo, cuidados u observaciones"><?= $view->escape($medicamento['indicaciones'] ?? '') ?></textarea></label>
    </div></section>

    <?php $hours = is_array($medicamento['horarios'] ?? null) ? $medicamento['horarios'] : []; $hourCount = max(3, count($hours)); ?>
    <section class="form-section"><div class="form-section__heading"><span>3</span><div><h2>Horarios diarios</h2><p>Puedes dejar espacios vacíos cuando no exista una hora fija.</p></div></div><div class="hours-grid">
        <?php for($index = 0; $index < $hourCount; $index++): ?><label class="field"><span>Horario <?= $index + 1 ?></span><input type="time" name="horarios[]" value="<?= $view->escape($hours[$index] ?? '') ?>"></label><?php endfor; ?>
    </div></section>

    <div class="form-actions"><a class="button button--secondary" href="/medicamentos">Cancelar</a><button class="button button--primary" type="submit"><?= $method === 'POST' ? 'Registrar medicamento' : 'Guardar cambios' ?></button></div>
</form>
