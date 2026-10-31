<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$id = (string) ($_GET['id'] ?? '');
try {
    $record = find_owned_record('breeding_records', $id, 'id,sow_id,breeding_date,expected_farrowing_date,notes,sows(name,tag_number)');
} catch (Throwable $exception) {
    error_log('Find breeding failed: ' . $exception->getMessage());
    $record = null;
}
if (!$record) {
    flash('error', 'That breeding record was not found.');
    redirect('/breeding/index.php');
}

$errors = [];
$values = ['breeding_date' => (string) $record['breeding_date'], 'notes' => (string) ($record['notes'] ?? '')];
if (method_is('POST')) {
    verify_csrf();
    $values['breeding_date'] = clean_text($_POST['breeding_date'] ?? '', 10);
    $values['notes'] = clean_text($_POST['notes'] ?? '', 2000);
    if (!valid_iso_date($values['breeding_date'])) {
        $errors['breeding_date'] = 'Enter a valid breeding date.';
    }
    if (!$errors) {
        try {
            $supabase->update('breeding_records', ['id' => 'eq.' . $id, 'owner_id' => 'eq.' . auth_user_id()], [
                'breeding_date' => $values['breeding_date'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());
            flash('success', 'Breeding updated. The expected farrowing date was recalculated.');
            redirect('/sows/view.php?id=' . rawurlencode((string) $record['sow_id']));
        } catch (Throwable $exception) {
            error_log('Edit breeding failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not update this breeding record.');
        }
    }
}
$sow = $record['sows'] ?? [];
$pageTitle = 'Edit breeding record';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact"><div><a class="back-link" href="<?= e(url('/sows/view.php?id=' . rawurlencode((string) $record['sow_id']))) ?>">Back to sow</a><h1>Edit breeding record</h1><p><?= e($sow['name'] ?? 'Sow') ?> · Tag <?= e($sow['tag_number'] ?? '—') ?></p></div></div>
<section class="form-card card">
    <?php if (isset($errors['form'])): ?><div class="form-alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        <label>Breeding date <input type="date" name="breeding_date" value="<?= e($values['breeding_date']) ?>" required data-breeding-date><?php if (isset($errors['breeding_date'])): ?><small class="field-error"><?= e($errors['breeding_date']) ?></small><?php endif; ?></label>
        <div class="calculation-preview"><span>Recalculated expected farrowing</span><strong data-farrowing-preview><?= valid_iso_date($values['breeding_date']) ? e(format_date(expected_farrowing_date($values['breeding_date']))) : 'Choose a date' ?></strong><small>Changing the breeding date automatically changes this date.</small></div>
        <label>Notes <textarea name="notes" rows="4" maxlength="2000"><?= e($values['notes']) ?></textarea></label>
        <div class="form-actions"><button class="button" type="submit">Save changes</button><a class="button button-ghost" href="<?= e(url('/sows/view.php?id=' . rawurlencode((string) $record['sow_id']))) ?>">Cancel</a></div>
    </form>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
