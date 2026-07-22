<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title><?= htmlspecialchars($title ?? 'Resumen', ENT_QUOTES, 'UTF-8') ?></title></head>
<body><?= $slot ?? '' ?></body>
</html>
