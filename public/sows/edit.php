<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$id = (string) ($_GET['id'] ?? '');
try {
    $sow = find_owned_sow($id);
} catch (Throwable $exception) {
    error_log('Find sow failed: ' . $exception->getMessage());
    $sow = null;
}
if (!$sow) {
    http_response_code(404);
    flash('error', 'That sow record was not found.');
    redirect('/sows/index.php');
}

$errors = [];
$values = [
    'name' => (string) $sow['name'],
    'tag_number' => (string) $sow['tag_number'],
    'status' => (string) $sow['status'],
    'notes' => (string) ($sow['notes'] ?? ''),
];
$allowedStatuses = ['healthy', 'breeding', 'gestating', 'isolated'];

if (method_is('POST')) {
    verify_csrf();
    $values['name'] = clean_text($_POST['name'] ?? '', 100);
    $values['tag_number'] = strtoupper(clean_text($_POST['tag_number'] ?? '', 50));
    $values['status'] = clean_text($_POST['status'] ?? '', 20);
    $values['notes'] = clean_text($_POST['notes'] ?? '', 2000);
    validate_required($errors, 'name', $values['name'], 'Sow name');
    validate_required($errors, 'tag_number', $values['tag_number'], 'Tag number');
    if (!in_array($values['status'], $allowedStatuses, true)) {
        $errors['status'] = 'Choose a valid status.';
    }
    if (!$errors) {
        try {
            $supabase->update('sows', ['id' => 'eq.' . $id, 'owner_id' => 'eq.' . auth_user_id()], [
                'name' => $values['name'],
                'tag_number' => $values['tag_number'],
                'status' => $values['status'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());
            flash('success', $values['name'] . '’s record was updated.');
            redirect('/sows/view.php?id=' . rawurlencode($id));
        } catch (Throwable $exception) {
            error_log('Edit sow failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not update this sow. Please try again.');
        }
    }
}

$pageTitle = 'Edit ' . $sow['name'];
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact"><div><a class="back-link" href="<?= e(url('/sows/view.php?id=' . rawurlencode($id))) ?>">Back to record</a><h1>Edit <?= e($sow['name']) ?></h1><p>Keep identity, status, and care notes current.</p></div></div>
<section class="form-card card">
    <?php if (isset($errors['form'])): ?><div class="form-alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Sow name <input type="text" name="name" value="<?= e($values['name']) ?>" maxlength="100" required><?php if (isset($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?></label>
            <label>Tag number <input type="text" name="tag_number" value="<?= e($values['tag_number']) ?>" maxlength="50" required><?php if (isset($errors['tag_number'])): ?><small class="field-error"><?= e($errors['tag_number']) ?></small><?php endif; ?></label>
        </div>
        <label>Status <select name="status"><?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $values['status'] === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></label>
        <label>Care notes <textarea name="notes" rows="5" maxlength="2000"><?= e($values['notes']) ?></textarea></label>
        <div class="form-actions"><button class="button" type="submit">Save changes</button><a class="button button-ghost" href="<?= e(url('/sows/view.php?id=' . rawurlencode($id))) ?>">Cancel</a></div>
    </form>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
