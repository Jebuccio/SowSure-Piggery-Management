<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect('/dashboard.php');
}

$errors = [];
$identifier = '';

// Read any flash message set by register.php or logout.php
$successNotice = $_SESSION['flash']['success'] ?? ($_SESSION['_flash']['success'] ?? null);
unset($_SESSION['flash']['success'], $_SESSION['_flash']['success']);

if (method_is('POST')) {
    verify_csrf();
    $identifier = clean_text($_POST['identifier'] ?? '', 254);
    $password = (string) ($_POST['password'] ?? '');

    validate_required($errors, 'identifier', $identifier, 'Email, username, or phone number');
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (!$errors) {
        try {
            $email = strtolower($identifier);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cleanPhone = preg_replace('/[^0-9+]/', '', $identifier) ?? '';
                if (preg_match('/^\+?[0-9]{7,15}$/', $cleanPhone)) {
                    $resolvedEmail = $supabase->phoneEmail($cleanPhone);
                    if ($resolvedEmail === null) {
                        throw new SupabaseApiException('No account is linked to that phone number.', 401);
                    }
                    $email = strtolower($resolvedEmail);
                } else {
                    $resolvedEmail = $supabase->usernameEmail(strtolower($identifier));
                    if ($resolvedEmail === null) {
                        throw new SupabaseApiException('No account is linked to that username.', 401);
                    }
                    $email = strtolower($resolvedEmail);
                }
            }

            $session = $supabase->authSignIn($email, $password);
            store_auth_session($session);
            flash('success', 'Welcome back to SowSure.');
            redirect('/dashboard.php');
        } catch (Throwable $exception) {
            error_log('Sign-in failed: ' . $exception->getMessage());
            $msg = $exception->getMessage();
            if (stripos($msg, 'Email not confirmed') !== false) {
                $errors['form'] = 'Please verify your email first: open the confirmation email we sent to your inbox and click the verification link before signing in.';
            } else {
                $errors['form'] = 'Sign-in error: ' . $msg;
            }
        }
    }
}

$pageTitle = 'Sign in';
$bodyClass = 'auth-page';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>
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
    padding-right: 44px !important;
}

.auth-card .password-toggle-trigger {
    position: absolute !important;
    right: 10px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    background: none !important;
    border: none !important;
    cursor: pointer !important;
    color: #6b7280 !important;
    padding: 4px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 5 !important;
}

.auth-card .password-toggle-trigger svg {
    pointer-events: none !important;
}
</style>

<section class="auth-layout">
    <div class="auth-intro">
        <span class="eyebrow">Welcome back</span>
        <h1>A little clarity for every farm day.</h1>
        <p>Access your herd records, active gestation schedules, and care tasks.</p>
    </div>
    <div class="auth-card">
        <div class="auth-card-heading">
            <span class="eyebrow">Sign in securely</span>
            <h2>Open your workspace</h2>
            <p>Sign in using Google, Facebook, phone, username, or email.</p>
        </div>

        <?php if (!empty($successNotice)): ?>
            <?php $isVerificationMsg = stripos($successNotice, 'verif') !== false; ?>
            <div role="status" style="background: #ecfdf5; border: 1px solid #10b981; color: #065f46; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.92rem; line-height: 1.45;">
                <?php if ($isVerificationMsg): ?>
                    <strong>Next step — Verify your email:</strong><br>
                <?php endif; ?>
                <?= e($successNotice) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['form'])): ?>
            <div class="form-alert" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <div class="social-auth-buttons">
            <button type="button" id="btn-google-action" class="btn-social-custom btn-google-custom">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.13 0-5.78-2.11-6.73-4.96H1.19v3.15C3.17 21.32 7.22 24 12 24z"/><path fill="#FBBC05" d="M5.27 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.19C.43 8.14 0 9.88 0 12s.43 3.86 1.19 5.39l4.08-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.22 0 3.17 2.68 1.19 6.61l4.08 3.15c.95-2.85 3.6-4.96 6.73-4.96z"/></svg>
                Google
            </button>
            <button type="button" id="btn-facebook-action" class="btn-social-custom btn-facebook-custom">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Facebook
            </button>
        </div>

        <div style="display: flex; align-items: center; text-align: center; margin: 15px 0; color: #666; font-size: 0.85rem;">
            <div style="flex: 1; border-bottom: 1px solid #ddd;"></div>
            <span style="padding: 0 10px;">or sign in with password</span>
            <div style="flex: 1; border-bottom: 1px solid #ddd;"></div>
        </div>

        <form method="post" class="stack-form" autocomplete="off" novalidate>
            <?= csrf_field() ?>
            <label>Email, username, or phone number
                <input type="text" name="identifier" id="login_identifier_input" value="<?= e($identifier) ?>" maxlength="254" autocomplete="off" required>
                <?php if (isset($errors['identifier'])): ?><small class="field-error"><?= e($errors['identifier']) ?></small><?php endif; ?>
            </label>
            <label>Password
                <div class="password-field-container">
                    <input type="password" name="password" id="login_password_input" value="" autocomplete="new-password" required>
                    <button type="button" id="toggle_login_password" class="password-toggle-trigger" title="Show/Hide Password">
                        <svg id="login_eye_icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>
            </label>
            <button class="button button-wide" type="submit">Sign in</button>
        </form>
        <p class="auth-switch">New to SowSure? <a href="<?= e(url('/register.php')) ?>">Create an account</a></p>
    </div>
</section>
<script>
(function () {
    function clearPrefilledFields() {
        var isPostAttempt = <?= method_is('POST') ? 'true' : 'false' ?>;
        if (!isPostAttempt) {
            var idInput = document.getElementById('login_identifier_input');
            var pwdInput = document.getElementById('login_password_input');
            if (idInput) idInput.value = '';
            if (pwdInput) pwdInput.value = '';
        }
    }

    function initLoginHandlers() {
        clearPrefilledFields();
        setTimeout(clearPrefilledFields, 80);

        var googleBtn = document.getElementById('btn-google-action');
        var facebookBtn = document.getElementById('btn-facebook-action');

        function startOAuth(provider) {
            var supabaseUrl = 'https://eefuplooeyhwomvwydew.supabase.co';
            var isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            var origin = isLocal
                ? window.location.origin
                : window.location.origin.replace(/^http:\/\//i, 'https://');
            var redirectTo = origin + '/login.php';
            window.location.href = supabaseUrl + '/auth/v1/authorize?provider=' + provider + '&redirect_to=' + encodeURIComponent(redirectTo) + '&flow_type=token';
        }

        if (googleBtn) {
            googleBtn.addEventListener('click', function () { startOAuth('google'); });
        }
        if (facebookBtn) {
            facebookBtn.addEventListener('click', function () { startOAuth('facebook'); });
        }

        var toggleBtn = document.getElementById('toggle_login_password');
        var pwdInput = document.getElementById('login_password_input');
        var eyeIcon = document.getElementById('login_eye_icon');

        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    if (eyeIcon) eyeIcon.style.stroke = '#2563eb';
                } else {
                    pwdInput.type = 'password';
                    if (eyeIcon) eyeIcon.style.stroke = 'currentColor';
                }
            });
        }

        if (window.location.hash && window.location.hash.indexOf('access_token=') !== -1) {
            var hashParams = new URLSearchParams(window.location.hash.substring(1));
            var accessToken = hashParams.get('access_token');
            var refreshToken = hashParams.get('refresh_token');

            if (accessToken) {
                fetch('/auth_callback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        access_token: accessToken,
                        refresh_token: refreshToken
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        window.location.href = '/dashboard.php';
                    } else {
                        alert('Auth error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(function (err) {
                    console.error('Callback error:', err);
                });
            }
        }
    }

    window.addEventListener('pageshow', clearPrefilledFields);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoginHandlers);
    } else {
        initLoginHandlers();
    }
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>