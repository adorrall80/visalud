<label>
    <span><?= $view->escape($label ?? $name ?? '') ?></span>
    <input
        type="<?= $view->escape($type ?? 'text') ?>"
        name="<?= $view->escape($name ?? '') ?>"
        value="<?= $view->escape($value ?? '') ?>"
        <?= !empty($required) ? 'required' : '' ?>
    >
</label>
