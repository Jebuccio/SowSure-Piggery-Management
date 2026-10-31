<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$id = (string) ($_GET['id'] ?? '');
try {
    $sow = find_owned_sow($id);
} catch (Throwable $exception) {
    error_log('Sow detail failed: ' . $exception->getMessage());
    $sow = null;
}
if (!$sow) {
    flash('error', 'That sow record was not found.');
    redirect('/sows/index.php');
}

if (method_is('POST') && ($_POST['action'] ?? '') === 'archive') {
    verify_csrf();
    try {
        $supabase->update('sows', ['id' => 'eq.' . $id, 'owner_id' => 'eq.' . auth_user_id()], [
            'archived_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ], access_token());
        flash('success', $sow['name'] . ' was archived. Breeding and care history was kept.');
        redirect('/sows/index.php');
    } catch (Throwable $exception) {
        error_log('Archive sow failed: ' . $exception->getMessage());
        flash('error', 'We could not archive this sow. Please try again.');
        redirect('/sows/view.php?id=' . rawurlencode($id));
    }
}

try {
    $breeding = $supabase->select('breeding_records', [
        'select' => 'id,breeding_date,expected_farrowing_date,notes',
        'sow_id' => 'eq.' . $id,
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'breeding_date.desc',
    ], access_token());
    $care = $supabase->select('care_activities', [
        'select' => 'id,activity_type,scheduled_date,completed_at',
        'sow_id' => 'eq.' . $id,
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'scheduled_date.desc',
        'limit' => '5',
    ], access_token());
} catch (Throwable $exception) {
    error_log('Sow related records failed: ' . $exception->getMessage());
    $breeding = $care = [];
}
$currentBreeding = $breeding[0] ?? null;

$pageTitle = $sow['name'];
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><a class="back-link" href="<?= e(url('/sows/index.php')) ?>">Back to sows</a><span class="eyebrow">Sow record · <?= e($sow['tag_number']) ?></span><h1><?= e($sow['name']) ?></h1><p>Added <?= e(format_date($sow['created_at'])) ?></p></div>
    <div class="button-row"><a class="button button-secondary" href="<?= e(url('/sows/edit.php?id=' . rawurlencode($id))) ?>">Edit record</a><a class="button" href="<?= e(url('/breeding/create.php?sow_id=' . rawurlencode($id))) ?>">Add breeding</a></div>
</div>
<section class="detail-grid">
    <article class="card profile-card">
        <div class="record-card-top"><span class="animal-avatar large"><?= e(strtoupper(mb_substr($sow['name'], 0, 1))) ?></span><span class="status status-<?= e($sow['status']) ?>"><?= e(ucfirst($sow['status'])) ?></span></div>
        <dl class="detail-list">
            <div><dt>Tag number</dt><dd><?= e($sow['tag_number']) ?></dd></div>
            <div><dt>Current status</dt><dd><?= e(ucfirst($sow['status'])) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(format_date($sow['updated_at'])) ?></dd></div>
        </dl>
        <div class="notes-block"><h2>Care notes</h2><p><?= nl2br(e($sow['notes'] ?: 'No care notes have been added.')) ?></p></div>
    </article>
    <article class="card highlight-card">
        <span class="eyebrow">Current gestation</span>
        <?php if ($currentBreeding): ?>
            <h2><?= e(format_date($currentBreeding['expected_farrowing_date'])) ?></h2>
            <p>Expected farrowing from breeding on <?= e(format_date($currentBreeding['breeding_date'])) ?>.</p>
            <span class="calculation-note">Breeding date + 114 calendar days</span>
        <?php else: ?>
            <h2>No breeding recorded</h2><p>Add a breeding record to calculate the expected farrowing date.</p>
        <?php endif; ?>
        <a class="text-link" href="<?= e(url('/breeding/create.php?sow_id=' . rawurlencode($id))) ?>">Add breeding record</a>
    </article>
</section>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">History</span><h2>Breeding records</h2></div></div>
    <div class="card table-card">
        <?php if (!$breeding): ?><div class="mini-empty">No breeding history yet.</div>
        <?php else: ?><div class="responsive-table"><table><thead><tr><th>Breeding date</th><th>Expected farrowing</th><th>Notes</th><th></th></tr></thead><tbody>
        <?php foreach ($breeding as $record): ?><tr><td><?= e(format_date($record['breeding_date'])) ?></td><td><strong><?= e(format_date($record['expected_farrowing_date'])) ?></strong></td><td><?= e($record['notes'] ?: '—') ?></td><td><a href="<?= e(url('/breeding/edit.php?id=' . rawurlencode($record['id']))) ?>">Edit</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </div>
</section>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">Care</span><h2>Recent activities</h2></div><a class="text-link" href="<?= e(url('/care/create.php?sow_id=' . rawurlencode($id))) ?>">Schedule care</a></div>
    <div class="card table-card">
        <?php if (!$care): ?><div class="mini-empty">No care activities yet.</div>
        <?php else: ?><div class="responsive-table"><table><thead><tr><th>Activity</th><th>Scheduled</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($care as $activity): ?><tr><td><?= e(ucwords(str_replace('_', ' ', $activity['activity_type']))) ?></td><td><?= e(format_date($activity['scheduled_date'])) ?></td><td><span class="status <?= $activity['completed_at'] ? 'status-complete' : 'status-pending' ?>"><?= $activity['completed_at'] ? 'Completed' : 'Pending' ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </div>
</section>
<section class="danger-zone card">
    <div><h2>Archive this sow</h2><p>Remove the sow from active lists without deleting breeding or care history.</p></div>
    <form method="post" data-confirm="Archive this sow? Her breeding and care history will be kept."><?= csrf_field() ?><input type="hidden" name="action" value="archive"><button class="button button-danger" type="submit">Archive sow</button></form>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
