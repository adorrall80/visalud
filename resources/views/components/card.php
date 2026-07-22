<article class="card">
    <?php if (!empty($title)): ?><h2><?= $view->escape($title) ?></h2><?php endif; ?>
    <?= $slot ?? '' ?>
</article>
