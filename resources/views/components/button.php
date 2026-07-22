<?php if (!empty($href)): ?>
<a class="button <?= $view->escape($class ?? '') ?>" href="<?= $view->escape($href) ?>"><?= $view->escape($label ?? 'Continuar') ?></a>
<?php else: ?>
<button class="button <?= $view->escape($class ?? '') ?>" type="<?= $view->escape($type ?? 'submit') ?>"><?= $view->escape($label ?? 'Guardar') ?></button>
<?php endif; ?>
