<label>
    <span><?= $view->escape($label ?? $name ?? '') ?></span>
    <select name="<?= $view->escape($name ?? '') ?>" <?= !empty($required) ? 'required' : '' ?>>
        <?php foreach (($options ?? []) as $optionValue => $optionLabel): ?>
            <option value="<?= $view->escape($optionValue) ?>" <?= (string) ($value ?? '') === (string) $optionValue ? 'selected' : '' ?>>
                <?= $view->escape($optionLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>
