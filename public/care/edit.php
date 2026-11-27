<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$id = (string) ($_GET['id'] ?? '');
try {
    $record = find_owned_record('care_activities', $id, 'id,sow_id,activity_type,scheduled_date,notes,completed_at,sows(name,tag_number)');
} catch (Throwable $exception) {
    error_log('Find care failed: ' . $exception->getMessage());
    $record = null;
}
if (!$record) {
    flash('error', 'That care activity was not found.');
    redirect('/care/index.php');
}
$types = ['vaccination' => 'Vaccination', 'vitamins' => 'Vitamins', 'deworming' => 'Deworming', 'health_check' => 'Health check', 'other' => 'Other care'];
$errors = [];
$values = ['activity_type' => (string) $record['activity_type'], 'scheduled_date' => (string) $record['scheduled_date'], 'notes' => (string) ($record['notes'] ?? '')];

if (method_is('POST')) {
    verify_csrf();
    $values['activity_type'] = clean_text($_POST['activity_type'] ?? '', 30);
    $values['scheduled_date'] = clean_text($_POST['scheduled_date'] ?? '', 10);
    $values['notes'] = clean_text($_POST['notes'] ?? '', 2000);
    if (!isset($types[$values['activity_type']])) {
        $errors['activity_type'] = 'Choose a valid care type.';
    }
    $dateChanged = $values['scheduled_date'] !== $record['scheduled_date'];
    if (!valid_iso_date($values['scheduled_date']) || ($dateChanged && $values['scheduled_date'] < manila_today())) {
        $errors['scheduled_date'] = 'A new scheduled date must be today or later in Asia/Manila time.';
    }
    if (!$errors) {
        try {
            $supabase->update('care_activities', ['id' => 'eq.' . $id, 'owner_id' => 'eq.' . auth_user_id()], [
                'activity_type' => $values['activity_type'],
                'scheduled_date' => $values['scheduled_date'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());
            flash('success', 'Care activity was updated.');
            redirect('/care/index.php');
        } catch (Throwable $exception) {
            error_log('Edit care failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not update this care activity.');
        }
    }
}
$sow = $record['sows'] ?? [];
$pageTitle = 'Edit care activity';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact"><div><a class="back-link" href="<?= e(url('/care/index.php')) ?>">Back to care</a><h1>Edit care activity</h1><p><?= e($sow['name'] ?? 'Sow') ?> · Tag <?= e($sow['tag_number'] ?? '—') ?></p></div></div>
<section class="form-card card">
    <?php if ($record['completed_at']): ?><div class="info-banner">This activity was completed on <?= e(format_date($record['completed_at'], 'M j, Y g:i A')) ?>.</div><?php endif; ?>
    <?php if (isset($errors['form'])): ?><div class="form-alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Activity type <select name="activity_type"><?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>" <?= $values['activity_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Scheduled date <input type="date" name="scheduled_date" value="<?= e($values['scheduled_date']) ?>" required><?php if (isset($errors['scheduled_date'])): ?><small class="field-error"><?= e($errors['scheduled_date']) ?></small><?php endif; ?></label>
        </div>
        <label>Notes <textarea name="notes" rows="4" maxlength="2000"><?= e($values['notes']) ?></textarea></label>
        <div class="form-actions"><button class="button" type="submit">Save changes</button><a class="button button-ghost" href="<?= e(url('/care/index.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
