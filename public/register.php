<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect('/dashboard.php');
}

$errors = [];$values = ['full_name' => '', 'farm_name' => '', 'email' => '', 'username' => '', 'phone' => ''];

if (method_is('POST')) {
    verify_csrf();
    $values['full_name'] = clean_text($_POST['full_name'] ?? '', 120);
    $values['farm_name'] = clean_text($_POST['farm_name'] ?? '', 120);
    $values['email'] = strtolower(clean_text($_POST['email'] ?? '', 254));
    $values['username'] = strtolower(clean_text($_POST['username'] ?? '', 30));
    $values['phone'] = clean_text($_POST['phone'] ?? '', 20);
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    validate_required($errors, 'full_name',$values['full_name'], 'Full name');
    validate_required($errors, 'farm_name',$values['farm_name'], 'Farm name');
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {$errors['email'] = 'Enter a valid email address.';
    }
    if (!valid_username($values['username'])) {$errors['username'] = 'Use 3–30 lowercase letters, numbers, dots, hyphens, or underscores.';
    }
    if (!empty($values['phone']) && !valid_phone($values['phone'])) {$errors['phone'] = 'Enter a valid phone number.';
    }
    if (!valid_password($password)) {$errors['password'] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirmation) {$errors['password_confirmation'] = 'The passwords do not match.';
    }
    if (!isset($_POST['terms'])) {$errors['terms'] = 'You must agree before creating an account.';
    }

    if (!$errors) {
        try {
            if ($supabase->usernameEmail($values['username']) !== null) {$errors['username'] = 'That username is already taken. Please choose a unique one.';
            } else {
                $response =$supabase->authSignUp($values['email'],$password, [
                    'full_name' => $values['full_name'],
                    'farm_name' => $values['farm_name'],
                    'username' => $values['username'],
                    'phone' => $values['phone'],
                ]);

                if (!empty($response['access_token'])) {
                    store_auth_session($response);
                    flash('success', 'Your SowSure account is ready.');
                    redirect('/dashboard.php');
                }

                flash('success', 'Account created. Check your email and confirm your address before signing in.');
                redirect('/login.php');
            }
        } catch (Throwable $exception) {
            error_log('Registration failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'We could not create the account. The email, phone, or username may already be in use.');
        }
    }
}

$pageTitle = 'Create account';$bodyClass = 'auth-page';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>
/* Dedicated styles for social buttons and form fields bypassing global .button conflicts */
.auth-card .social-auth-buttons {
    display: flex !important;
    gap: 12px !important;
    margin-bottom: 20px !important;
    width: 100% !important;
}

.auth-card .btn-social-custom {
    flex: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    padding: 10px 16px !important;
    border-radius: 6px !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    text-decoration: none !important;
    line-height: 1.4 !important;
    box-sizing: border-box !important;
}

.auth-card .btn-google-custom {
    background-color: #ffffff !important;
    color: #374151 !important;
    border: 1px solid #d1d5db !important;
}

.auth-card .btn-google-custom:hover {
    background-color: #f9fafb !important;
    border-color: #9ca3af !important;
}

.auth-card .btn-facebook-custom {
    background-color: #1877f2 !important;
    color: #ffffff !important;
    border: 1px solid #1877f2 !important;
}

.auth-card .btn-facebook-custom:hover {
    background-color: #166fe5 !important;
}

.auth-card .password-field-container {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
}

.auth-card .password-field-container input {
    width: 100% !important;
    padding-right: 42px !important;
}

.auth-card .password-toggle-trigger {
    position: absolute !important;
    right: 10px !important;
    background: none !important;
    border: none !important;
    cursor: pointer !important;
    color: #6b7280 !important;
    padding: 4px !important;
    display: flex !important;
    align-items: center !important;
}
</style>

<section class="auth-layout">
    <div class="auth-intro">
        <span class="eyebrow">Start with the essentials</span>
        <h1>A fresh start for your farm.</h1>
        <p>Create one workspace for your sow, breeding, and care records.</p>
        <ul class="check-list">
            <li>Private records protected per account</li>
            <li>Automatic 114-day farrowing dates</li>
            <li>Works on phone, tablet, and desktop</li>
        </ul>
    </div>
    <div class="auth-card auth-card-wide">
        <div class="auth-card-heading">
            <span class="eyebrow">Create account</span>
            <h2>Name your farm workspace</h2>
            <p>Register via Gmail, Facebook, Phone, or Email.</p>
        </div>

        <?php if (isset($errors['form'])): ?><div class="form-alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>

        <!-- Quick Social Options -->
        <div class="social-auth-buttons">
            <button type="button" id="btn-google-action" class="btn-social-custom btn-google-custom">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.13 0-5.78-2.11-6.73-4.96H1.19v3.15C3.17 21.32 7.22 24 12 24z"/><path fill="#FBBC05" d="M5.27 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.19C.43 8.14 0 9.88 0 12s.43 3.86 1.19 5.39l4.08-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.22 0 3.17 2.68 1.19 6.61l4.08 3.15c.95-2.85 3.6-4.96 6.73-4.96z"/></svg>
                Google (Gmail)
            </button>
            <button type="button" id="btn-facebook-action" class="btn-social-custom btn-facebook-custom">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Facebook
            </button>
        </div>

        <div style="display: flex; align-items: center; text-align: center; margin: 15px 0; color: #666; font-size: 0.85rem;">
            <div style="flex: 1; border-bottom: 1px solid #ddd;"></div>
            <span style="padding: 0 10px;">or register with phone / email</span>
            <div style="flex: 1; border-bottom: 1px solid #ddd;"></div>
        </div>

        <form method="post" class="stack-form" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>Full name
                    <input type="text" id="full_name_input" name="full_name" value="<?= e($values['full_name']) ?>" maxlength="120" autocomplete="name" required>
                    <?php if (isset($errors['full_name'])): ?><small class="field-error"><?= e($errors['full_name']) ?></small><?php endif; ?>
                </label>
                <label>Farm name
                    <input type="text" name="farm_name" value="<?= e($values['farm_name']) ?>" maxlength="120" autocomplete="organization" required>
                    <?php if (isset($errors['farm_name'])): ?><small class="field-error"><?= e($errors['farm_name']) ?></small><?php endif; ?>
                </label>
            </div>
            <div class="form-grid">
                <label>Email address
                    <input type="email" name="email" value="<?= e($values['email']) ?>" maxlength="254" autocomplete="email" required>
                    <?php if (isset($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?>
                </label>
                <label>Phone number (SMS OTP)
                    <input type="tel" name="phone" value="<?= e($values['phone'] ?? '') ?>" maxlength="20" placeholder="09123456789" autocomplete="tel">
                    <?php if (isset($errors['phone'])): ?><small class="field-error"><?= e($errors['phone']) ?></small><?php endif; ?>
                </label>
            </div>
            <label>Username
                <input type="text" id="username_input" name="username" value="<?= e($values['username']) ?>" maxlength="30" autocomplete="username" required>
                <small>Auto-generated from your name. Modify if already taken.</small>
                <?php if (isset($errors['username'])): ?><small class="field-error" style="color: #dc2626; font-weight: 600;"><?= e($errors['username']) ?></small><?php endif; ?>
            </label>
            <div class="form-grid">
                <label>Password
                    <div class="password-field-container">
                        <input type="password" name="password" id="password_input" autocomplete="new-password" required>
                        <button type="button" id="toggle_password_btn_1" class="password-toggle-trigger" title="Show/Hide Password">
                            <svg id="eye_icon_1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>
                </label>
                <label>Confirm password
                    <div class="password-field-container">
                        <input type="password" name="password_confirmation" id="password_confirm_input" autocomplete="new-password" required>
                        <button type="button" id="toggle_password_btn_2" class="password-toggle-trigger" title="Show/Hide Password">
                            <svg id="eye_icon_2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <?php if (isset($errors['password_confirmation'])): ?><small class="field-error"><?= e($errors['password_confirmation']) ?></small><?php endif; ?>
                </label>
            </div>
            <p class="password-hint">Use at least 6 characters.</p>
            <label class="checkbox-label"><input type="checkbox" name="terms" value="1" required> <span>I agree to use SowSure for responsible farm record-keeping.</span></label>
            <?php if (isset($errors['terms'])): ?><small class="field-error"><?= e($errors['terms']) ?></small><?php endif; ?>
            <button class="button button-wide" type="submit">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="<?= e(url('/login.php')) ?>">Sign in</a></p