<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Portal Familiar de Salud', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <header class="topbar">
        <div class="topbar__inner">
            <a class="brand" href="/" aria-label="Ir al inicio"><span class="brand__mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"></path><path d="M5.5 10.5V20h13v-9.5M9.5 20v-6h5v6"></path></svg></span><span class="brand__name"><b>Visalud</b><span>SaludFamiliar</span></span></a>
            <?php if (($personasContexto ?? []) !== []): ?>
            <form class="person-switcher" method="post" action="/persona-activa">
                <?= $view->csrfField() ?><input type="hidden" name="return_to" value="<?= $view->escape($rutaActual ?? '/') ?>">
                <label><span>Persona activa</span><select name="persona_id" data-auto-submit aria-label="Persona activa"><option value="" disabled <?= empty($personaActiva)?'selected':'' ?>>Seleccionar persona</option><?php foreach($personasContexto as $personaContexto): ?><option value="<?= $view->escape($personaContexto['id']) ?>" <?= (int)($personaActiva['id']??0)===(int)$personaContexto['id']?'selected':'' ?>><?= $view->escape($personaContexto['nombre']) ?></option><?php endforeach; ?></select></label>
            </form>
            <?php endif; ?>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Abrir navegación"><span></span><span></span><span></span></button>
            <nav class="nav" id="main-navigation" aria-label="Principal">
                <a href="/">Inicio</a>
                <a href="/personas">Personas</a>
                <a href="/atenciones">Atenciones</a>
                <a href="/medicamentos">Medicamentos</a>
                <a href="/documentos">Documentos</a>
                <a href="/mantenedores">Mantenedores</a>
                <a href="/familias/integrantes">Integrantes</a>
                <a href="/familias">Familias</a>
            </nav>
            <form method="post" action="/logout"><?= $view->csrfField() ?><button class="logout" type="submit">Salir</button></form>
        </div>
    </header>
    <main class="container"><?= $slot ?? '' ?></main>
</body>
</html>
