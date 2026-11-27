<?php
declare(strict_types=1);

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    ['/dashboard.php', t('dashboard'), 'home'],
    ['/sows/index.php', t('sows'), 'sow'],
    ['/breeding/index.php', t('breeding'), 'breeding'],
    ['/care/index.php', t('care'), 'care'],
    ['/schedule/index.php', t('schedule'), 'calendar'],
];

?>
<aside class="sidebar">
    <a class="brand brand-light" href="<?= e(url('/dashboard.php')) ?>">
        <span class="brand-mark">S</span>
        <span><strong>SowSure</strong><small>Smart piggery management</small></span>
    </a>
    <nav class="primary-nav" aria-label="Main navigation">
        <?php foreach ($navItems as [$href, $label, $icon]): ?>
            <?php $active = $href === '/dashboard.php' ? $currentPath === $href : str_starts_with($currentPath, dirname($href)); ?>
            <a href="<?= e(url($href)) ?>" class="<?= $active ? 'active' : '' ?>">
                <?= nav_icon($icon) ?><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-account">
        <a href="<?= e(url('/profile.php')) ?>">
            <span class="avatar"><?= e(strtoupper(mb_substr($profile['full_name'] ?? 'U', 0, 1))) ?></span>
            <span><strong><?= e($profile['full_name'] ?? 'Your profile') ?></strong><small><?= e($profile['farm_name'] ?? '') ?></small></span>
        </a>
        <form method="post" action="<?= e(url('/logout.php')) ?>">
            <?= csrf_field() ?>
            <button class="text-button" type="submit">Sign out</button>
        </form>
    </div>
</aside>
<header class="mobile-header">
    <a class="brand" href="<?= e(url('/dashboard.php')) ?>">
        <span class="brand-mark">S</span><strong>SowSure</strong>
    </a>
    <a class="avatar" href="<?= e(url('/profile.php')) ?>" aria-label="Open profile">
        <?= e(strtoupper(mb_substr($profile['full_name'] ?? 'U', 0, 1))) ?>
    </a>
</header>
<nav class="bottom-nav" aria-label="Mobile navigation">
    <?php foreach ($navItems as [$href, $label, $icon]): ?>
        <?php $active = $href === '/dashboard.php' ? $currentPath === $href : str_starts_with($currentPath, dirname($href)); ?>
        <a href="<?= e(url($href)) ?>" class="<?= $active ? 'active' : '' ?>">
            <?= nav_icon($icon) ?><span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
