<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

try {
    $sows = owned_active_sows();
} catch (Throwable $exception) {
    error_log('Load sows for breeding failed: ' . $exception->getMessage());
    $sows = [];
}
$selectedSow = (string) ($_GET['sow_id'] ?? '');
$errors = [];
$values = ['sow_id' => $selectedSow, 'breeding_date' => manila_today(), 'notes' => ''];

if (method_is('POST')) {
    verify_csrf();
    $values['sow_id'] = clean_text($_POST['sow_id'] ?? '', 36);
    $values['breeding_date'] = clean_text($_POST['breeding_date'] ?? '', 10);
    $values['notes'] = clean_text($_POST['notes'] ?? '', 2000);
    $ownedIds = array_column($sows, 'id');
    if (!in_array($values['sow_id'], $ownedIds, true)) {
        $errors['sow_id'] = 'Choose one of your active sows.';
    }
    if (!valid_iso_date($values['breeding_date'])) {
        $errors['breeding_date'] = 'Enter a valid breeding date.';
    }

    if (!$errors) {
        try {
            $supabase->insert('breeding_records', [
                'owner_id' => auth_user_id(),
                'sow_id' => $values['sow_id'],
                'breeding_date' => $values['breeding_date'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());
            flash('success', 'Breeding saved. Expected farrowing: ' . format_date(expected_farrowing_date($values['breeding_date'])) . '.');
            redirect('/sows/view.php?id=' . rawurlencode($values['sow_id']));
        } catch (Throwable $exception) {
            error_log('Create breeding failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not save this breeding record.');
        }
    }
}

$pageTitle = 'Add breeding record';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact"><div><a class="back-link" href="<?= e(url('/breeding/index.php')) ?>">Back to breeding</a><h1>Add breeding record</h1><p>The expected farrowing date is breeding date + 114 calendar days.</p></div></div>
<?php if (!$sows): ?>
    <section class="empty-state card"><h2>Add an active sow first</h2><p>A breeding record must be linked to one of your sows.</p><a class="button" href="<?= e(url('/sows/create.php')) ?>">Add sow</a></section>
<?php else: ?>
<section class="form-card card">
    <?php if (isset($errors['form'])): ?><div class="form-alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        <label>Sow <select name="sow_id" required><option value="">Choose a sow</option><?php foreach ($sows as $sow): ?><option value="<?= e($sow['id']) ?>" <?= $values['sow_id'] === $sow['id'] ? 'selected' : '' ?>><?= e($sow['name']) ?> · <?= e($sow['tag_number']) ?></option><?php endforeach; ?></select><?php if (isset($errors['sow_id'])): ?><small class="field-error"><?= e($errors['sow_id']) ?></small><?php endif; ?></label>
        <label>Breeding date <input type="date" name="breeding_date" value="<?= e($values['breeding_date']) ?>" required data-breeding-date><?php if (isset($errors['breeding_date'])): ?><small class="field-error"><?= e($errors['breeding_date']) ?></small><?php endif; ?></label>
        <div class="calculation-preview"><span>Expected farrowing</span><strong data-farrowing-preview><?= valid_iso_date($values['breeding_date']) ? e(format_date(expected_farrowing_date($values['breeding_date']))) : 'Choose a date' ?></strong><small>Final value is calculated and enforced by PostgreSQL.</small></div>
        <label>Notes <textarea name="notes" rows="4" maxlength="2000" placeholder="Breeding method, boar reference, or observations"><?= e($values['notes']) ?></textarea></label>
        <div class="form-actions"><button class="button" type="submit">Save breeding</button><a class="button button-ghost" href="<?= e(url('/breeding/index.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
