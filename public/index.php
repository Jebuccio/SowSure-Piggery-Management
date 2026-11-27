<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect('/dashboard.php');
}

$pageTitle = 'Clear records for better sow care';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">Sow breeding & care tracking</span>
        <h1>Good care starts with a clear picture.</h1>
        <p>Keep sow records, breeding dates, expected farrowing dates, and daily care in one calm, practical workspace.</p>
        <div class="button-row">
            <a class="button" href="<?= e(url('/register.php')) ?>">Create your farm workspace</a>
            <a class="button button-secondary" href="<?= e(url('/login.php')) ?>">Sign in</a>
        </div>
        <p class="hero-note">Designed for small and family-operated pig farms in the Philippines.</p>
    </div>
    <div class="hero-panel" aria-label="SowSure feature preview">
        <div class="panel-top">
            <span><small>Today</small><strong>Farm overview</strong></span>
            <span class="status-dot"></span>
        </div>
        <div class="preview-stat">
            <span class="preview-icon"><?= nav_icon('sow') ?></span>
            <span><small>Active herd</small><strong>Your sow records</strong></span>
        </div>
        <div class="preview-list">
            <div><span class="date-chip">114</span><span><strong>Automatic farrowing dates</strong><small>Calculated from every breeding record</small></span></div>
            <div><span class="date-chip earth">Due</span><span><strong>Care reminders</strong><small>Upcoming and overdue work stays visible</small></span></div>
            <div><span class="date-chip gold">Safe</span><span><strong>Private farm data</strong><small>Protected per account with database policies</small></span></div>
        </div>
    </div>
</section>
<section class="feature-strip" aria-label="Key features">
    <article><span>01</span><h2>Know every sow</h2><p>Find names, tags, status, and notes quickly.</p></article>
    <article><span>02</span><h2>Plan the next step</h2><p>Track breeding history and the 114-day gestation.</p></article>
    <article><span>03</span><h2>Care on time</h2><p>Schedule vaccinations, vitamins, and health checks.</p></article>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
