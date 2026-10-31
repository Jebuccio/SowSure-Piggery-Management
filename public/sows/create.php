<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$errors = [];
$values = ['name' => '', 'tag_number' => '', 'status' => 'healthy', 'notes' => ''];
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
            $rows = $supabase->insert('sows', [
                'owner_id' => auth_user_id(),
                'name' => $values['name'],
                'tag_number' => $values['tag_number'],
                'status' => $values['status'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());
            flash('success', $values['name'] . ' was added to your herd.');
            redirect('/sows/view.php?id=' . rawurlencode((string) $rows[0]['id']));
        } catch (Throwable $exception) {
            error_log('Create sow failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not save this sow. Please try again.');
        }
    }
}

$pageTitle = 'Add a sow';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact"><div><a class="back-link" href="<?= e(url('/sows/index.php')) ?>">Back to sows</a><h1>Add a sow</h1><p>Add a clear, searchable record to your herd.</p></div></div>
<section class="form-card card">
    <?php if (isset($errors['form'])): ?><div class="form-alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Sow name <span aria-hidden="true">*</span>
                <input type="text" name="name" value="<?= e($values['name']) ?>" maxlength="100" required autofocus>
                <?php if (isset($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?>
            </label>
            <label>Tag number <span aria-hidden="true">*</span>
                <input type="text" name="tag_number" value="<?= e($values['tag_number']) ?>" maxlength="50" required>
                <small>Tag numbers are unique within your farm account.</small>
                <?php if (isset($errors['tag_number'])): ?><small class="field-error"><?= e($errors['tag_number']) ?></small><?php endif; ?>
            </label>
        </div>
        <label>Status
            <select name="status" required>
                <?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $values['status'] === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?>
            </select>
            <?php if (isset($errors['status'])): ?><small class="field-error"><?= e($errors['status']) ?></small><?php endif; ?>
        </label>
        <label>Care notes
            <textarea name="notes" rows="5" maxlength="2000" placeholder="Health, feeding, or handling notes"><?= e($values['notes']) ?></textarea>
        </label>
        <div class="form-actions"><button class="button" type="submit">Add sow</button><a class="button button-ghost" href="<?= e(url('/sows/index.php')) ?>">Cancel</a></div>
    </form>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
