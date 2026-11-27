<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$errors = [];
$values = [
    'name' => '',
    'tag_number' => '',
    'status' => 'healthy',
    'notes' => ''
];

$statuses = [
    'healthy' => 'Healthy',
    'breeding' => 'Breeding',
    'gestating' => 'Gestating',
    'isolated' => 'Isolated'
];

if (method_is('POST')) {
    verify_csrf();
    
    $values['name'] = clean_text($_POST['name'] ?? '', 100);
    $values['tag_number'] = clean_text($_POST['tag_number'] ?? '', 50);
    $values['status'] = clean_text($_POST['status'] ?? 'healthy', 30);
    $values['notes'] = clean_text($_POST['notes'] ?? '', 2000);

    // Validation checks
    if (mb_strlen(trim($values['name'])) < 1) {
        $errors['name'] = 'Sow name is required.';
    }
    if (mb_strlen(trim($values['tag_number'])) < 1) {
        $errors['tag_number'] = 'Tag number is required.';
    }
    if (!isset($statuses[$values['status']])) {
        $errors['status'] = 'Choose a valid status.';
    }

    if (!$errors) {
        try {
            $supabase->insert('sows', [
                'owner_id' => auth_user_id(),
                'name' => $values['name'],
                'tag_number' => $values['tag_number'],
                'status' => $values['status'],
                'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ], access_token());

            flash('success', 'Sow ' . $values['name'] . ' (Tag: ' . $values['tag_number'] . ') was added successfully.');
            redirect('/dashboard.php');
        } catch (Throwable $exception) {
            error_log('Create sow failed: ' . $exception->getMessage());
            
            // TEMPORARY DEBUGGER: If it fails, show the exact reason on screen
            // (You can change this back to friendly_data_error once verified)
            $errors['form'] = 'Database Error: ' . $exception->getMessage();
        }
    }
}

$pageTitle = 'Add sow';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading compact">
    <div>
        <a class="back-link" href="<?= e(url('/dashboard.php')) ?>">Back to dashboard</a>
        <h1>Add new sow</h1>
        <p>Register an active sow into your herd records.</p>
    </div>
</div>

<section class="form-card card">
    <?php if (isset($errors['form'])): ?>
        <div class="form-alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>
    
    <form method="post" class="stack-form" novalidate>
        <?= csrf_field() ?>
        
        <label>Sow Name / Identifier 
            <input type="text" name="name" value="<?= e($values['name']) ?>" placeholder="e.g., Daisy or UNion" required maxlength="100">
            <?php if (isset($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?>
        </label>

        <div class="form-grid">
            <label>Tag Number 
                <input type="text" name="tag_number" value="<?= e($values['tag_number']) ?>" placeholder="e.g., 1 or TAG-12" required maxlength="50">
                <?php if (isset($errors['tag_number'])): ?><small class="field-error"><?= e($errors['tag_number']) ?></small><?php endif; ?>
            </label>

            <label>Status 
                <select name="status">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $values['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?><small class="field-error"><?= e($errors['status']) ?></small><?php endif; ?>
            </label>
        </div>

        <label>Notes 
            <textarea name="notes" rows="4" maxlength="2000" placeholder="Breed details, medical history, or observations"><?= e($values['notes']) ?></textarea>
        </label>

        <div class="form-actions">
            <button class="button" type="submit">Save sow</button>
            <a class="button button-ghost" href="<?= e(url('/dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</section>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>