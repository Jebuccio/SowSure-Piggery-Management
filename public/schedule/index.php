<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$today = manila_today();
$loadError = null;
try {
    $rows = $supabase->select('breeding_records', [
        'select' => 'id,sow_id,breeding_date,expected_farrowing_date,sows(name,tag_number,status,archived_at)',
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'breeding_date.desc',
    ], access_token());
} catch (Throwable $exception) {
    error_log('Schedule failed: ' . $exception->getMessage());
    $rows = [];
    $loadError = 'We could not load the gestation schedule.';
}
$latest = [];
foreach ($rows as $row) {
    if (($row['sows']['archived_at'] ?? null) === null && !isset($latest[$row['sow_id']])) {
        $latest[$row['sow_id']] = $row;
    }
}
$schedule = array_values($latest);
usort($schedule, static fn (array $a, array $b): int => strcmp($a['expected_farrowing_date'], $b['expected_farrowing_date']));

function gestation_state(string $date, string $today): array
{
    $days = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($date))->format('%r%a');
    if ($days < 0) {
        return ['Past expected date', 'status-overdue', $days];
    }
    if ($days <= 7) {
        return ['Due soon', 'status-due', $days];
    }
    return ['On track', 'status-gestating', $days];
}

$pageTitle = 'Gestation schedule';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><span class="eyebrow">114-day calendar</span><h1>Gestation schedule</h1><p>The latest breeding record for each active sow determines the current expected date.</p></div>
    <a class="button" href="<?= e(url('/breeding/create.php')) ?>">Add breeding</a>
</div>
<div class="rule-banner card"><span class="rule-number">114</span><div><strong>Expected farrowing rule</strong><p>Breeding date + 114 calendar days. Dates are stored as calendar dates to avoid timezone shifts.</p></div></div>
<?php if ($loadError): ?><div class="form-alert"><?= e($loadError) ?></div><?php endif; ?>
<section class="card table-card schedule-table">
<?php if (!$schedule && !$loadError): ?>
    <div class="empty-state embedded"><span class="empty-icon"><?= nav_icon('calendar') ?></span><h2>Your gestation schedule is empty</h2><p>Add a breeding record to calculate and display an expected farrowing date.</p><a class="button" href="<?= e(url('/breeding/create.php')) ?>">Add breeding record</a></div>
<?php else: ?>
    <div class="responsive-table"><table>
        <thead><tr><th>Sow</th><th>Breeding date</th><th>Expected farrowing</th><th>Time remaining</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($schedule as $record): $sow = $record['sows'] ?? []; [$label, $class, $days] = gestation_state($record['expected_farrowing_date'], $today); ?>
            <tr>
                <td><a class="table-primary" href="<?= e(url('/sows/view.php?id=' . rawurlencode($record['sow_id']))) ?>"><?= e($sow['name'] ?? 'Sow') ?></a><small>Tag <?= e($sow['tag_number'] ?? '—') ?></small></td>
                <td><?= e(format_date($record['breeding_date'])) ?></td>
                <td><strong><?= e(format_date($record['expected_farrowing_date'])) ?></strong></td>
                <td><?= $days < 0 ? e(abs($days) . ' days past') : e($days . ' days') ?></td>
                <td><span class="status <?= e($class) ?>"><?= e($label) ?></span></td>
                <td><a href="<?= e(url('/breeding/edit.php?id=' . rawurlencode($record['id']))) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
</section>
<p class="schedule-note">Status rule: 0–7 days is “Due soon,” more than 7 days is “On track,” and dates before today are “Past expected date.” Confirm actual farrowing events in farm notes.</p>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
