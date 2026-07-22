<header class="page-heading page-heading--form"><div><a class="back-link" href="/atenciones">← Volver a atenciones</a><p class="eyebrow">Historia de salud</p><h1><?= $view->escape($title) ?></h1><p class="muted">Los campos de terapia se utilizan solo cuando correspondan.</p></div></header>

<?php if (!empty($errors['general'][0])): ?><div class="alert alert--error"><?= $view->escape($errors['general'][0]) ?></div><?php endif; ?>

<form class="record-form" method="post" action="<?= $view->escape($action) ?>">
    <?= $view->csrfField() ?>
    <?php if ($method !== 'POST'): ?><input type="hidden" name="_method" value="<?= $view->escape($method) ?>"><?php endif; ?>

    <section class="form-section"><div class="form-section__heading"><span>1</span><div><h2>Datos de la atención</h2><p>Persona, tipo, estado y fecha.</p></div></div><div class="form-grid">
        <div class="field context-person"><span>Persona</span><strong><?= $view->escape($personaActiva['nombre']) ?></strong><small>Definida por el selector superior.</small><input type="hidden" name="persona_id" value="<?= $view->escape($personaActiva['id']) ?>"></div>
        <label class="field"><span>Tipo *</span><select name="tipo_id" required><option value="">Seleccionar</option><?php foreach($tipos as $tipo): ?><option value="<?= $view->escape($tipo['id']) ?>" <?= (string)($atencion['tipo_id'] ?? '') === (string)$tipo['id'] ? 'selected' : '' ?>><?= $view->escape($tipo['nombre']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Estado *</span><select name="estado_id" required><?php foreach($estados as $estado): ?><option value="<?= $view->escape($estado['id']) ?>" <?= (string)($atencion['estado_id'] ?? '') === (string)$estado['id'] ? 'selected' : '' ?>><?= $view->escape($estado['nombre']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Fecha y hora *</span><input type="datetime-local" name="fecha_hora" required value="<?= $view->escape($atencion['fecha_hora'] ?? date('Y-m-d\TH:i')) ?>"><?php if(!empty($errors['fecha_hora'][0])):?><small class="field-error"><?= $view->escape($errors['fecha_hora'][0]) ?></small><?php endif;?></label>
    </div></section>

    <section class="form-section"><div class="form-section__heading"><span>2</span><div><h2>Profesional y lugar</h2><p>Información de quien realizó la atención.</p></div></div><div class="form-grid">
        <label class="field"><span>Profesional</span><input name="profesional" maxlength="150" value="<?= $view->escape($atencion['profesional'] ?? '') ?>"></label>
        <label class="field"><span>Especialidad</span><input name="especialidad" maxlength="120" value="<?= $view->escape($atencion['especialidad'] ?? '') ?>"></label>
        <label class="field field--wide"><span>Centro médico o lugar</span><input name="centro_medico" maxlength="150" value="<?= $view->escape($atencion['centro_medico'] ?? '') ?>"></label>
    </div></section>

    <section class="form-section"><div class="form-section__heading"><span>3</span><div><h2>Información clínica</h2><p>Registra lo comunicado durante la atención.</p></div></div><div class="form-grid">
        <label class="field"><span>Motivo</span><textarea name="motivo" rows="4"><?= $view->escape($atencion['motivo'] ?? '') ?></textarea></label>
        <label class="field"><span>Diagnóstico o resultado</span><textarea name="diagnostico_resultado" rows="4"><?= $view->escape($atencion['diagnostico_resultado'] ?? '') ?></textarea></label>
        <label class="field"><span>Indicaciones</span><textarea name="indicaciones" rows="4"><?= $view->escape($atencion['indicaciones'] ?? '') ?></textarea></label>
        <label class="field"><span>Temas abordados en terapia</span><textarea name="temas_abordados" rows="4"><?= $view->escape($atencion['temas_abordados'] ?? '') ?></textarea></label>
        <label class="field"><span>Acuerdos o tareas</span><textarea name="acuerdos" rows="4"><?= $view->escape($atencion['acuerdos'] ?? '') ?></textarea></label>
        <label class="field"><span>Próxima fecha</span><input type="date" name="proxima_fecha" value="<?= $view->escape($atencion['proxima_fecha'] ?? '') ?>"></label>
    </div></section>

    <div class="form-actions"><a class="button button--secondary" href="/atenciones">Cancelar</a><button class="button button--primary" type="submit"><?= $method === 'POST' ? 'Registrar atención' : 'Guardar cambios' ?></button></div>
</form>
