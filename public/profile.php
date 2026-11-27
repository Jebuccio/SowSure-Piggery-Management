<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$profile = current_profile();
$errors = [];
$values = [
    'full_name' => (string) ($profile['full_name'] ?? ''),
    'farm_name' => (string) ($profile['farm_name'] ?? ''),
    'username' => (string) ($profile['username'] ?? ''),
    'preferred_language' => (string) ($profile['preferred_language'] ?? 'en'),
];

if (method_is('POST')) {
    verify_csrf();
    $values['full_name'] = clean_text($_POST['full_name'] ?? '', 120);
    $values['farm_name'] = clean_text($_POST['farm_name'] ?? '', 120);
    $values['username'] = strtolower(clean_text($_POST['username'] ?? '', 30));
    $values['preferred_language'] = clean_text($_POST['preferred_language'] ?? 'en', 5);
    validate_required($errors, 'full_name', $values['full_name'], 'Full name');
    validate_required($errors, 'farm_name', $values['farm_name'], 'Farm name');
    if (!valid_username($values['username'])) {
        $errors['username'] = 'Use 3–30 lowercase letters, numbers, dots, hyphens, or underscores.';
    }
    if ($values['preferred_language'] !== 'en') {
        $errors['preferred_language'] = 'English is the supported language in Version 1.';
    }
    if (!$errors) {
        try {
            $rows = $supabase->update('profiles', ['id' => 'eq.' . auth_user_id()], [
                'full_name' => $values['full_name'],
                'farm_name' => $values['farm_name'],
                'username' => $values['username'],
                'preferred_language' => 'en',
            ], access_token());
            $_SESSION['profile'] = $rows[0] ?? array_merge($profile, $values);
            flash('success', 'Your profile and farm details were updated.');
            redirect('/profile.php');
        } catch (Throwable $exception) {
            error_log('Profile update failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not update your profile.');
        }
    }
}

$pageTitle = 'Profile & settings';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Account & farm</span><h1>Profile settings</h1><p>Keep the details behind your farm workspace current.</p></div></div>
<section class="profile-layout">
    <aside class="card identity-card">
        <span class="avatar avatar-large"><?= e(strtoupper(mb_substr($values['full_name'] ?: 'U', 0, 1))) ?></span>
        <h2><?= e($values['full_name']) ?></h2>
        <p><?= e($values['farm_name']) ?></p>
        <dl class="detail-list">
            <div><dt>Account email</dt><dd><?= e($_SESSION['auth']['email'] ?? '') ?></dd></div>
            <div><dt>Member since</dt><dd><?= e(format_date($profile['created_at'] ?? null)) ?></dd></div>
            <div><dt>Timezone</dt><dd>Asia/Manila</dd></div>
        </dl>
    </aside>
    <section class="card form-card">
        <div class="section-heading"><div><span class="eyebrow">Editable details</span><h2>Farm profile</h2></div></div>
        <?php if (isset($errors['form'])): ?><div class="form-alert"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" class="stack-form" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>Full name <input type="text" name="full_name" value="<?= e($values['full_name']) ?>" maxlength="120" required><?php if (isset($errors['full_name'])): ?><small class="field-error"><?= e($errors['full_name']) ?></small><?php endif; ?></label>
                <label>Farm name <input type="text" name="farm_name" value="<?= e($values['farm_name']) ?>" maxlength="120" required><?php if (isset($errors['farm_name'])): ?><small class="field-error"><?= e($errors['farm_name']) ?></small><?php endif; ?></label>
            </div>
            <label>Username <input type="text" name="username" value="<?= e($values['username']) ?>" maxlength="30" required><small>You can use this username to sign in.</small><?php if (isset($errors['username'])): ?><small class="field-error"><?= e($errors['username']) ?></small><?php endif; ?></label>
            <label>Language <select name="preferred_language"><option value="en" selected>English</option></select><small>User-facing text is organized for a future Cebuano translation.</small></label>
            <div class="info-banner">Email changes and password resets are managed through Supabase Auth. They are intentionally not mixed with profile updates.</div>
            <button class="button" type="submit">Save profile</button>
        </form>
    </section>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
