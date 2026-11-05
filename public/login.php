<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect('/dashboard.php');
}

$errors = [];
$identifier = '';

if (method_is('POST')) {
    verify_csrf();
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    validate_required($errors, 'identifier', $identifier, 'Email, username, or phone');
    validate_required($errors, 'password', $password, 'Password');

    if (!$errors) {
        try {
            $loginEmail = $identifier;

            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL) && !valid_phone($identifier)) {
                $resolvedEmail = $supabase->usernameEmail($identifier);
                if ($resolvedEmail !== null) {
                    $loginEmail = $resolvedEmail;
                }
            } elseif (valid_phone($identifier)) {
                $resolvedEmail = $supabase->phoneEmail($identifier);
                if ($resolvedEmail !== null) {
                    $loginEmail = $resolvedEmail;
                }
            }

            $response = $supabase->authSignIn($loginEmail, $password);

            if (!empty($response['access_token'])) {
                store_auth_session($response);
                flash('success', 'Welcome back to SowSure.');
                redirect('/dashboard.php');
            }

            $errors['form'] = 'Invalid sign-in credentials.';
        } catch (Throwable $exception) {
            error_log('Login failed: ' . $exception->getMessage());
            $errors['form'] = friendly_data_error($exception, 'Invalid email, username, phone, or password.');
        }
    }
}

$pageTitle = 'Sign in';
$bodyClass = 'auth-page';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>
/* CSS overrides to ensure social buttons render properly and bypass global .button conflicts */
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
        <span class="eyebrow">Welcome back</span>
        <h1>A little clarity for every farm day.</h1>
        <p>Access your herd records, active gestation schedules, and care tasks.</p>
    </div>
    <div class="auth-card">
        <div class="auth-card-heading">
            <span class="eyebrow">Sign in securely</span>
            <h2>Open your workspace</h2>
            <p>Sign in using Google, Facebook, phone, or email.</p>
        </div>

        <?php if (isset($errors['form'])): ?><div class="form-alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>

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

        <form method="post" class="stack-form" novalidate>
            <?= csrf_field() ?>
            <label>Email, username, or phone number
                <input type="text" name="identifier" value="<?= e($identifier) ?>" maxlength="254" autocomplete="username" required>
                <?php if (isset($errors['identifier'])): ?><small class="field-error"><?= e($errors['identifier']) ?></small><?php endif; ?>
            </label>
            <label>Password
                <div class="password-field-container">
                    <input type="password" name="password" id="login_password_input" autocomplete="current-password" required>
                    <button type="button" id="toggle_password_btn" class="password-toggle-trigger" title="Show/Hide Password">
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
(function() {
    function initAuthHandlers() {
        const googleBtn = document.getElementById('btn-google-action');
        const facebookBtn = document.getElementById('btn-facebook-action');
        const toggleBtn = document.getElementById('toggle_password_btn');
        const passwordInput = document.getElementById('login_password_input');
        const eyeIcon = document.getElementById('login_eye_icon');

        function startOAuth(provider) {
            const supabaseUrl = 'https://eefuplooeyhwomvwydew.supabase.co';
            // Force HTTPS on Render to align with reverse-proxy environments
            const origin = window.location.origin.replace('http://', 'https://');
            const redirectTo = origin + '/login.php';
            window.location.href = `${supabaseUrl}/auth/v1/authorize?provider=${provider}&redirect_to=${encodeURIComponent(redirectTo)}&flow_type=token`;
        }

        if (googleBtn) {
            googleBtn.addEventListener('click', function() { startOAuth('google'); });
        }
        if (facebookBtn) {
            facebookBtn.addEventListener('click', function() { startOAuth('facebook'); });
        }

        if (toggleBtn && passwordInput && eyeIcon) {
            toggleBtn.addEventListener('click', function() {
                if (passwordInput.type === "password") {
                    passwordInput.type = "text";
                    eyeIcon.style.stroke = "#2563eb";
                } else {
                    passwordInput.type = "password";
                    eyeIcon.style.stroke = "currentColor";
                }
            });
        }

        // Process token fragment returned from Supabase
        const hash = window.location.hash;
        if (hash && hash.includes('access_token')) {
            const params = new URLSearchParams(hash.substring(1));
            const accessToken = params.get('access_token');
            const refreshToken = params.get('refresh_token');

            if (accessToken) {
                fetch('/auth_callback.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        access_token: accessToken, 
                        refresh_token: refreshToken 
                    })
                }).then(async function(response) {
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        alert('Auth response error: ' + text.substring(0, 100));
                        return;
                    }

                    if (response.ok && data.status === 'success') {
                        window.location.href = '/dashboard.php';
                    } else {
                        alert('Auth error: ' + (data.message || 'Unknown error'));
                    }
                }).catch(function(err) {
                    alert('Network error during Google callback.');
                });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAuthHandlers);
    } else {
        initAuthHandlers();
    }
})();
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>