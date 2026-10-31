<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$today = manila_today();
$dataError = null;
try {
    $sows = $supabase->select('sows', [
        'select' => 'id,status',
        'owner_id' => 'eq.' . auth_user_id(),
        'archived_at' => 'is.null',
    ], access_token());
    $breedingRows = $supabase->select('breeding_records', [
        'select' => 'id,sow_id,breeding_date,expected_farrowing_date,sows(name,tag_number,status,archived_at)',
        'owner_id' => 'eq.' . auth_user_id(),
        'expected_farrowing_date' => 'gte.' . $today,
        'order' => 'breeding_date.desc',
    ], access_token());
    $careRows = $supabase->select('care_activities', [
        'select' => 'id,sow_id,activity_type,scheduled_date,completed_at,sows(name,tag_number,archived_at)',
        'owner_id' => 'eq.' . auth_user_id(),
        'completed_at' => 'is.null',
        'order' => 'scheduled_date.asc',
    ], access_token());
} catch (Throwable $exception) {
    error_log('Dashboard data failed: ' . $exception->getMessage());
    $sows = $breedingRows = $careRows = [];
    $dataError = 'We could not refresh your farm overview. Please try again.';
}

$latestBySow = [];
foreach ($breedingRows as $record) {
    if (($record['sows']['archived_at'] ?? null) === null && !isset($latestBySow[$record['sow_id']])) {
        $latestBySow[$record['sow_id']] = $record;
    }
}
$upcomingFarrowing = array_values($latestBySow);
usort($upcomingFarrowing, static fn (array $a, array $b): int => strcmp($a['expected_farrowing_date'], $b['expected_farrowing_date']));
$upcomingFarrowing = array_slice($upcomingFarrowing, 0, 5);

$upcomingCare = array_values(array_filter($careRows, static fn (array $a): bool =>
    $a['scheduled_date'] >= $today && ($a['sows']['archived_at'] ?? null) === null
));
$upcomingCare = array_slice($upcomingCare, 0, 5);
$overdueCare = array_values(array_filter($careRows, static fn (array $a): bool =>
    $a['scheduled_date'] < $today && ($a['sows']['archived_at'] ?? null) === null
));

$profile = current_profile();
$hour = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim((string) ($profile['full_name'] ?? 'there')))[0];

$pageTitle = 'Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div><span class="eyebrow"><?= e(format_date($today, 'l, F j')) ?></span><h1><?= e($greeting) ?>, <?= e($firstName) ?>.</h1><p><?= e($profile['farm_name'] ?? 'Your farm') ?> · Here is what needs your attention.</p></div>
    <div class="button-row"><a class="button button-secondary" href="<?= e(url('/sows/create.php')) ?>">Add sow</a><a class="button" href="<?= e(url('/care/create.php')) ?>">Schedule care</a></div>
</div>
<?php if ($dataError): ?><div class="form-alert" role="alert"><?= e($dataError) ?></div><?php endif; ?>
<section class="stats-grid" aria-label="Farm summary">
    <article class="stat-card card"><span class="stat-icon"><?= nav_icon('sow') ?></span><div><small>Active sows</small><strong><?= count($sows) ?></strong><span>Current herd records</span></div></article>
    <article class="stat-card card"><span class="stat-icon light"><?= nav_icon('calendar') ?></span><div><small>Upcoming farrowing</small><strong><?= count($upcomingFarrowing) ?></strong><span>Latest relevant records</span></div></article>
    <article class="stat-card card"><span class="stat-icon earth"><?= nav_icon('care') ?></span><div><small>Upcoming care</small><strong><?= count($upcomingCare) ?></strong><span>Incomplete activities</span></div></article>
    <article class="stat-card card <?= $overdueCare ? 'needs-attention' : '' ?>"><span class="stat-icon amber"><?= nav_icon('care') ?></span><div><small>Overdue care</small><strong><?= count($overdueCare) ?></strong><span><?= $overdueCare ? 'Needs attention' : 'Nothing overdue' ?></span></div></article>
</section>
<section class="dashboard-grid">
    <article class="card dashboard-panel">
        <div class="section-heading"><div><span class="eyebrow">Gestation</span><h2>Upcoming farrowing</h2></div><a href="<?= e(url('/schedule/index.php')) ?>">View schedule</a></div>
        <?php if (!$upcomingFarrowing): ?>
            <div class="mini-empty"><p>No upcoming farrowing dates.</p><a href="<?= e(url('/breeding/create.php')) ?>">Add a breeding record</a></div>
        <?php else: ?>
            <div class="dashboard-list">
            <?php foreach ($upcomingFarrowing as $record): $sow = $record['sows'] ?? []; $days = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($record['expected_farrowing_date']))->format('%a'); ?>
                <a href="<?= e(url('/sows/view.php?id=' . rawurlencode($record['sow_id']))) ?>">
                    <span class="date-tile"><strong><?= e((new DateTimeImmutable($record['expected_farrowing_date']))->format('d')) ?></strong><small><?= e((new DateTimeImmutable($record['expected_farrowing_date']))->format('M')) ?></small></span>
                    <span><strong><?= e($sow['name'] ?? 'Sow') ?></strong><small>Tag <?= e($sow['tag_number'] ?? '—') ?> · <?= $days ?> days away</small></span><span class="chevron">›</span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <article class="card dashboard-panel">
        <div class="section-heading"><div><span class="eyebrow">Care agenda</span><h2>Coming up</h2></div><a href="<?= e(url('/care/index.php')) ?>">View care</a></div>
        <?php if (!$upcomingCare): ?>
            <div class="mini-empty"><p>No upcoming care scheduled.</p><a href="<?= e(url('/care/create.php')) ?>">Schedule an activity</a></div>
        <?php else: ?>
            <div class="dashboard-list">
            <?php foreach ($upcomingCare as $activity): $sow = $activity['sows'] ?? []; ?>
                <a href="<?= e(url('/care/edit.php?id=' . rawurlencode($activity['id']))) ?>">
                    <span class="date-tile earth"><strong><?= e((new DateTimeImmutable($activity['scheduled_date']))->format('d')) ?></strong><small><?= e((new DateTimeImmutable($activity['scheduled_date']))->format('M')) ?></small></span>
                    <span><strong><?= e(ucwords(str_replace('_', ' ', $activity['activity_type']))) ?></strong><small><?= e($sow['name'] ?? 'Sow') ?> · Tag <?= e($sow['tag_number'] ?? '—') ?></small></span><span class="chevron">›</span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php if ($overdueCare): ?>
<section class="overdue-panel card">
    <div><span class="alert-mark">!</span><div><span class="eyebrow">Action needed</span><h2><?= count($overdueCare) ?> overdue <?= count($overdueCare) === 1 ? 'activity' : 'activities' ?></h2><p>Review incomplete care and mark finished work as complete.</p></div></div>
    <a class="button button-warm" href="<?= e(url('/care/index.php?view=overdue')) ?>">Review overdue care</a>
</section>
<?php endif; ?>
<section class="quick-actions">
    <div class="section-heading"><div><span class="eyebrow">Shortcuts</span><h2>Quick actions</h2></div></div>
    <div class="action-grid">
        <a href="<?= e(url('/sows/create.php')) ?>"><?= nav_icon('sow') ?><span><strong>Add a sow</strong><small>Create a herd record</small></span></a>
        <a href="<?= e(url('/breeding/create.php')) ?>"><?= nav_icon('breeding') ?><span><strong>Record breeding</strong><small>Calculate farrowing</small></span></a>
        <a href="<?= e(url('/care/create.php')) ?>"><?= nav_icon('care') ?><span><strong>Schedule care</strong><small>Plan the next activity</small></span></a>
        <a href="<?= e(url('/schedule/index.php')) ?>"><?= nav_icon('calendar') ?><span><strong>Open schedule</strong><small>Review gestation dates</small></span></a>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
