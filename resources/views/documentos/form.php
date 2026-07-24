<header class="page-heading page-heading--form"><div><a class="back-link" href="/documentos">← Volver a documentos</a><p class="eyebrow">Archivo privado</p><h1><?= $editing ? 'Editar documento' : 'Cargar documento' ?></h1><p class="muted">El archivo quedará protegido y disponible únicamente para tu familia.</p></div></header>

<?php if(!empty($errors['general'][0])):?><div class="alert alert--error"><?= $view->escape($errors['general'][0]) ?></div><?php endif; ?>

<form class="record-form" method="post" action="<?= $editing ? '/documentos/' . $view->escape($documento['id']) : '/documentos' ?>" enctype="multipart/form-data">
    <?= $view->csrfField() ?>
    <?php if($editing): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
    <section class="form-section"><div class="form-section__heading"><span>1</span><div><h2>Archivo</h2><p><?= $editing ? 'Puedes conservar el archivo actual o reemplazarlo.' : 'PDF o imagen de hasta ' . $view->escape($maxUploadMb) . ' MB.' ?></p></div></div><label class="upload-zone"><input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required' ?>><strong><?= $editing ? 'Reemplazar archivo' : 'Seleccionar documento' ?></strong><span><?= $editing ? 'Deja este campo vacío para conservar el archivo actual.' : 'PDF, JPG, PNG o WEBP' ?></span></label></section>

    <section class="form-section"><div class="form-section__heading"><span>2</span><div><h2>Clasificación</h2><p>Indica a quién pertenece y qué tipo de documento es.</p></div></div><div class="form-grid">
        <label class="field"><span>Tipo de documento *</span><select name="tipo_id" required><option value="">Seleccionar</option><?php foreach($tipos as $tipo): ?><option value="<?= $view->escape($tipo['id']) ?>" <?= (string)($documento['tipo_id'] ?? '') === (string)$tipo['id'] ? 'selected' : '' ?>><?= $view->escape($tipo['nombre']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Nombre visible</span><input name="nombre" maxlength="255" value="<?= $view->escape($documento['nombre'] ?? '') ?>" placeholder="Si lo dejas vacío se utilizará el nombre del archivo"></label>
        <label class="field"><span>Fecha del documento</span><input type="date" name="fecha_documento" value="<?= $view->escape($documento['fecha_documento'] ?? '') ?>"></label>
        <label class="field field--wide"><span>Descripción</span><textarea name="descripcion" rows="3" placeholder="Resumen opcional del contenido"><?= $view->escape($documento['descripcion'] ?? '') ?></textarea></label>
    </div></section>

    <?php if(!empty($documento['atencion_id'])): ?><input type="hidden" name="atencion_id" value="<?= $view->escape($documento['atencion_id']) ?>"><?php endif; ?>
    <div class="form-actions"><a class="button button--secondary" href="/documentos">Cancelar</a><button class="button button--primary" type="submit"><?= $editing ? 'Actualizar documento' : 'Guardar documento' ?></button></div>
</form>
