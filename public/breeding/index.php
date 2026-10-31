<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$loadError = null;
try {
    $records = $supabase->select('breeding_records', [
        'select' => 'id,sow_id,breeding_date,expected_farrowing_date,notes,sows(name,tag_number,status)',
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'breeding_date.desc',
    ], access_token());
} catch (Throwable $exception) {
    error_log('Breeding list failed: ' . $exception->getMessage());
    $records = [];
    $loadError = 'We could not load breeding records. Please try again.';
}

$pageTitle = 'Breeding records';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><span class="eyebrow">Breeding history</span><h1>Breeding records</h1><p>Every breeding stays in history; expected farrowing is calculated automatically.</p></div>
    <a class="button" href="<?= e(url('/breeding/create.php')) ?>">Add breeding</a>
</div>
<?php if ($loadError): ?><div class="form-alert"><?= e($loadError) ?></div><?php endif; ?>
<section class="card table-card">
    <?php if (!$records && !$loadError): ?>
        <div class="empty-state embedded"><span class="empty-icon"><?= nav_icon('breeding') ?></span><h2>No breeding records yet</h2><p>Add a record and SowSure will calculate the 114-day expected farrowing date.</p><a class="button" href="<?= e(url('/breeding/create.php')) ?>">Add first breeding</a></div>
    <?php else: ?>
        <div class="responsive-table"><table>
            <thead><tr><th>Sow</th><th>Breeding date</th><th>Expected farrowing</th><th>Notes</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($records as $record): $sow = $record['sows'] ?? []; ?>
                <tr>
                    <td><a class="table-primary" href="<?= e(url('/sows/view.php?id=' . rawurlencode($record['sow_id']))) ?>"><?= e($sow['name'] ?? 'Sow') ?></a><small>Tag <?= e($sow['tag_number'] ?? '—') ?></small></td>
                    <td><?= e(format_date($record['breeding_date'])) ?></td>
                    <td><strong><?= e(format_date($record['expected_farrowing_date'])) ?></strong><small>114 days</small></td>
                    <td><?= e($record['notes'] ?: '—') ?></td>
                    <td><a href="<?= e(url('/breeding/edit.php?id=' . rawurlencode($record['id']))) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
