<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

if (method_is('POST') && ($_POST['action'] ?? '') === 'complete') {
    verify_csrf();
    $id = clean_text($_POST['id'] ?? '', 36);
    try {
        $record = find_owned_record('care_activities', $id);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }
        $supabase->update('care_activities', ['id' => 'eq.' . $id, 'owner_id' => 'eq.' . auth_user_id()], [
            'completed_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM),
        ], access_token());
        flash('success', 'Care activity marked complete.');
    } catch (Throwable $exception) {
        error_log('Complete care failed: ' . $exception->getMessage());
        flash('error', 'We could not complete that activity.');
    }
    redirect('/care/index.php');
}

$view = clean_text($_GET['view'] ?? 'pending', 20);
if (!in_array($view, ['pending', 'overdue', 'completed', 'all'], true)) {
    $view = 'pending';
}
$today = manila_today();
try {
    $activities = $supabase->select('care_activities', [
        'select' => 'id,sow_id,activity_type,scheduled_date,notes,completed_at,sows(name,tag_number)',
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'scheduled_date.asc',
    ], access_token());
} catch (Throwable $exception) {
    error_log('Care list failed: ' . $exception->getMessage());
    $activities = [];
    $loadError = 'We could not load care activities.';
}

$filtered = array_values(array_filter($activities, static function (array $activity) use ($view, $today): bool {
    return match ($view) {
        'pending' => !$activity['completed_at'] && $activity['scheduled_date'] >= $today,
        'overdue' => !$activity['completed_at'] && $activity['scheduled_date'] < $today,
        'completed' => (bool) $activity['completed_at'],
        default => true,
    };
}));
$overdueCount = count(array_filter($activities, static fn (array $a): bool => !$a['completed_at'] && $a['scheduled_date'] < $today));

$pageTitle = 'Care activities';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><span class="eyebrow">Care & reminders</span><h1>Care activities</h1><p>Keep vaccinations, vitamins, deworming, and health checks in view.</p></div>
    <a class="button" href="<?= e(url('/care/create.php')) ?>">Schedule care</a>
</div>
<?php if (isset($loadError)): ?><div class="form-alert"><?= e($loadError) ?></div><?php endif; ?>
<nav class="tabs" aria-label="Care activity views">
    <a class="<?= $view === 'pending' ? 'active' : '' ?>" href="?view=pending">Upcoming</a>
    <a class="<?= $view === 'overdue' ? 'active' : '' ?>" href="?view=overdue">Overdue<?= $overdueCount ? ' (' . $overdueCount . ')' : '' ?></a>
    <a class="<?= $view === 'completed' ? 'active' : '' ?>" href="?view=completed">Completed</a>
    <a class="<?= $view === 'all' ? 'active' : '' ?>" href="?view=all">All</a>
</nav>
<section class="agenda">
<?php if (!$filtered): ?>
    <div class="empty-state card"><span class="empty-icon"><?= nav_icon('care') ?></span><h2>Nothing in this view</h2><p><?= $view === 'pending' ? 'Schedule care to start a practical agenda for your herd.' : 'There are no matching care activities.' ?></p><a class="button" href="<?= e(url('/care/create.php')) ?>">Schedule care</a></div>
<?php else: ?>
    <?php foreach ($filtered as $activity): $sow = $activity['sows'] ?? []; $overdue = !$activity['completed_at'] && $activity['scheduled_date'] < $today; ?>
        <article class="agenda-item card <?= $overdue ? 'overdue' : '' ?>">
            <div class="agenda-date"><strong><?= e((new DateTimeImmutable($activity['scheduled_date']))->format('d')) ?></strong><span><?= e((new DateTimeImmutable($activity['scheduled_date']))->format('M')) ?></span></div>
            <div class="agenda-content">
                <div class="agenda-title"><h2><?= e(ucwords(str_replace('_', ' ', $activity['activity_type']))) ?></h2><span class="status <?= $activity['completed_at'] ? 'status-complete' : ($overdue ? 'status-overdue' : 'status-pending') ?>"><?= $activity['completed_at'] ? 'Completed' : ($overdue ? 'Overdue' : 'Scheduled') ?></span></div>
                <p><a href="<?= e(url('/sows/view.php?id=' . rawurlencode($activity['sow_id']))) ?>"><?= e($sow['name'] ?? 'Sow') ?></a> · Tag <?= e($sow['tag_number'] ?? '—') ?></p>
                <?php if ($activity['notes']): ?><p class="record-note"><?= e($activity['notes']) ?></p><?php endif; ?>
            </div>
            <div class="agenda-actions">
                <a class="button button-ghost button-small" href="<?= e(url('/care/edit.php?id=' . rawurlencode($activity['id']))) ?>">Edit</a>
                <?php if (!$activity['completed_at']): ?><form method="post" data-confirm="Mark this care activity as completed?"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?= e($activity['id']) ?>"><button class="button button-small" type="submit">Mark complete</button></form><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
