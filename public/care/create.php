<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

try {
    $sows = owned_active_sows();
} catch (Throwable $exception) {
    error_log('Load sows for care failed: ' . $exception->getMessage());
    $sows = [];
}
$types = ['vaccination' => 'Vaccination', 'vitamins' => 'Vitamins', 'deworming' => 'Deworming', 'health_check' => 'Health check', 'other' => 'Other care'];
$errors = [];
$values = ['sow_id' => (string) ($_GET['sow_id'] ?? ''), 'activity_type' => 'health_check', 'scheduled_date' => manila_today(), 'notes' => ''];

if (method_is('POST')) {
    verify_csrf();
    $values['sow_id'] = clean_text($_POST['sow_id'] ?? '', 36);
    $values['activity_type'] = clean_text($_POST['activity_type'] ?? '', 30);
    $values['scheduled_date'] = clean_text($_POST['scheduled_date'] ?? '', 10);
    $values['notes'] = clean_text($_POST['notes'] ?? '', 2000);
    if (!in_array($values['sow_id'], array_column($sows, 'id'), true)) {
        $errors['sow_id'] = 'Choose one of your active sows.';
    }
    if (!isset($types[$values['activity_type']])) {
        $errors['activity_type'] = 'Choose a valid care type.';
    }
    if (!valid_iso_date($values['scheduled_date']) || $values['scheduled_date'] < manila_today()) {
        $errors['scheduled_date'] = 'Scheduled date must be today or later in Asia/Manila time.';
    }
    if (!$errors) {
        try {
            $supabase->insert('care_activities', [
                'owner_id' => auth_user_id(),
                'sow_id' => $values['sow_id'],
                'activity_type' => $values['activity_type'],
                'scheduled_date' => $values['scheduled_date'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());
            flash('success', $types[$values['activity_type']] . ' was scheduled for ' . format_date($values['scheduled_date']) . '.');
            redirect('/care/index.php');
        } catch (Throwable $exception) {
            error_log('Create care failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not schedule this care activity.');
        }
    }
}

$pageTitle = 'Schedule care';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact"><div><a class="back-link" href="<?= e(url('/care/index.php')) ?>">Back to care</a><h1>Schedule care</h1><p>Plan one clear activity for a sow.</p></div></div>
<?php if (!$sows): ?>
    <section class="empty-state card"><h2>Add an active sow first</h2><p>Care must be linked to one of your active sows.</p><a class="button" href="<?= e(url('/sows/create.php')) ?>">Add sow</a></section>
<?php else: ?>
<section class="form-card card">
    <?php if (isset($errors['form'])): ?><div class="form-alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        <label>Sow <select name="sow_id" required><option value="">Choose a sow</option><?php foreach ($sows as $sow): ?><option value="<?= e($sow['id']) ?>" <?= $values['sow_id'] === $sow['id'] ? 'selected' : '' ?>><?= e($sow['name']) ?> · <?= e($sow['tag_number']) ?></option><?php endforeach; ?></select><?php if (isset($errors['sow_id'])): ?><small class="field-error"><?= e($errors['sow_id']) ?></small><?php endif; ?></label>
        <div class="form-grid">
            <label>Activity type <select name="activity_type"><?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>" <?= $values['activity_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['activity_type'])): ?><small class="field-error"><?= e($errors['activity_type']) ?></small><?php endif; ?></label>
            <label>Scheduled date <input type="date" name="scheduled_date" value="<?= e($values['scheduled_date']) ?>" min="<?= e(manila_today()) ?>" required><?php if (isset($errors['scheduled_date'])): ?><small class="field-error"><?= e($errors['scheduled_date']) ?></small><?php endif; ?></label>
        </div>
        <label>Notes <textarea name="notes" rows="4" maxlength="2000" placeholder="Dose, preparation, or observations"><?= e($values['notes']) ?></textarea></label>
        <div class="form-actions"><button class="button" type="submit">Schedule activity</button><a class="button button-ghost" href="<?= e(url('/care/index.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
