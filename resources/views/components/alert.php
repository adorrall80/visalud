<?php if (!empty($message)): ?>
<div class="alert alert--<?= $view->escape($type ?? 'info') ?>" role="alert"><?= $view->escape($message) ?></div>
<?php endif; ?>
