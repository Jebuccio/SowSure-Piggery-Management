<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'SowSure';
$bodyClass = $bodyClass ?? '';
$profile = is_authenticated() ? current_profile() : [];
$flashMessages = pull_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SowSure sow breeding and care tracking system">
    <meta name="theme-color" content="#214e34">
    <title><?= e($pageTitle) ?> · SowSure</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php if (is_authenticated()): ?>
    <?php require __DIR__ . '/navigation.php'; ?>
<?php else: ?>
    <header class="public-header">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="SowSure home">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span><strong>SowSure</strong><small>Smart piggery management</small></span>
        </a>
        <nav aria-label="Account navigation">
            <a href="<?= e(url('/login.php')) ?>">Sign in</a>
            <a class="button button-small" href="<?= e(url('/register.php')) ?>">Create account</a>
        </nav>
    </header>
<?php endif; ?>
<div class="<?= is_authenticated() ? 'app-shell' : 'public-shell' ?>">
    <?php if (is_authenticated()): ?><div class="content-shell"><?php endif; ?>
    <?php foreach ($flashMessages as $type => $message): ?>
        <div class="flash flash-<?= e($type) ?>" role="status">
            <span><?= e($message) ?></span>
            <button type="button" data-dismiss aria-label="Dismiss message">&times;</button>
        </div>
    <?php endforeach; ?>
    <main id="main-content">
