<?php
// ============================================================
//  EMS — Login Page
//  auth/login.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Redirect if already authenticated
if (is_logged_in()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$error    = '';
$flashMsg = '';

// ── Handle POST submission ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check — aborts if token invalid
    verify_csrf();

    // 2. Load DB connection
    require_once __DIR__ . '/../config/connection.php';

    $email    = post_str('email');
    $password = $_POST['password'] ?? '';

    // 3. Lightweight format validation
    $validationErrors = validate_login($email, $password);

    if (!empty($validationErrors)) {
        $error = $validationErrors[0];
    } else {
        // 4. Full authentication: DB lookup + password_verify() + session
        $result = authenticate_user($conn, $email, $password);

        if ($result['success']) {
            // 5. Redirect to the role-specific portal (set inside authenticate_user)
            header('Location: ' . $result['redirect']);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Flash message passed from logout redirect
if (($_GET['msg'] ?? '') === 'logged_out') {
    $flashMsg = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to EduSkill Marketplace System — Access your learner or provider portal.">
    <title>Login | EduSkill Marketplace System</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <!-- EMS Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>

<div class="auth-page">

    <!-- ══ LEFT BRANDING PANEL ══════════════════════════════ -->
    <div class="auth-brand-panel">

        <!-- Floating bubbles -->
        <div class="auth-bubbles">
            <div class="auth-bubble"></div>
            <div class="auth-bubble"></div>
            <div class="auth-bubble"></div>
        </div>

        <!-- Logo -->
        <div class="auth-brand-logo" style="position:relative;z-index:1;">
            <i class="bi bi-mortarboard-fill me-2" style="color:#f59e0b;"></i>
            EduSkill<span style="color:#f59e0b;">EMS</span>
        </div>

        <!-- Headline -->
        <div style="position:relative;z-index:1;">
            <p class="auth-brand-headline">
                Unlock Your<br>
                <span style="color:#f59e0b;">Potential</span> with<br>
                Short courses
            </p>
            <p class="auth-brand-sub">
                Connecting learners with certified local training providers
                under the supervision of the Ministry of Education.
            </p>
        </div>

        <!-- Feature List -->
        <ul class="auth-features" style="position:relative;z-index:1;">
            <li>
                <div style="width:36px;height:36px;background:rgba(255,255,255,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-shield-check"></i>
                </div>
                Ministry-verified Training Providers
            </li>
            <li>
                <div style="width:36px;height:36px;background:rgba(255,255,255,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                Official Enrolment Receipts Issued
            </li>
            <li>
                <div style="width:36px;height:36px;background:rgba(255,255,255,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-star-half"></i>
                </div>
                Verified Learner reviews &amp; Ratings
            </li>
            <li>
                <div style="width:36px;height:36px;background:rgba(255,255,255,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                Analytics Dashboard for Providers
            </li>
        </ul>

        <!-- Footer note -->
        <p style="position:relative;z-index:1;color:rgba(255,255,255,.4);font-size:.78rem;margin:0;">
            &copy; <?= date('Y') ?> EduSkill Marketplace System
        </p>

    </div><!-- /auth-brand-panel -->

    <!-- ══ RIGHT FORM PANEL ══════════════════════════════════ -->
    <div class="auth-form-panel">

        <div>
            <!-- Mobile logo (only shows when brand panel is hidden) -->
            <div class="d-flex d-lg-none justify-content-center mb-4">
                <span class="auth-brand-logo" style="font-size:1.5rem;color:#2563eb;">
                    <i class="bi bi-mortarboard-fill me-1" style="color:#f59e0b;"></i>
                    EduSkill<span style="color:#f59e0b;">EMS</span>
                </span>
            </div>

            <h1 class="auth-form-title">Welcome back</h1>
            <p class="auth-form-sub">Sign in to continue to your portal</p>

            <!-- Alerts -->
            <?php if ($flashMsg): ?>
                <div class="auth-alert auth-alert-success" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($flashMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="auth-alert auth-alert-danger" role="alert" id="loginError">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="<?= BASE_URL ?>/auth/login.php" id="authForm" novalidate>
                <?= csrf_field() ?>

                <!-- Email -->
                <div class="auth-input-group">
                    <label for="loginEmail">Email Address</label>
                    <i class="bi bi-envelope auth-input-icon"></i>
                    <input type="email"
                           class="form-control"
                           id="loginEmail"
                           name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="you@example.com"
                           required
                           autofocus>
                </div>

                <!-- Password -->
                <div class="auth-input-group">
                    <label for="loginPassword">Password</label>
                    <i class="bi bi-lock auth-input-icon"></i>
                    <input type="password"
                           class="form-control"
                           id="loginPassword"
                           name="password"
                           placeholder="••••••••"
                           required>
                    <button type="button"
                            class="btn-pw-toggle"
                            data-target="loginPassword"
                            aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-auth mt-2" id="btnSubmitAuth">
                    <span class="spinner"></span>
                    <span class="btn-label">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </span>
                </button>

            </form>

            <div class="auth-divider">or</div>

            <p class="text-center mb-0" style="font-size:.885rem;color:#475569;">
                Don't have an account?
                <a href="<?= BASE_URL ?>/auth/register.php"
                   class="fw-700"
                   style="color:#2563eb;text-decoration:none;">
                    Create one here &rarr;
                </a>
            </p>
        </div>

    </div><!-- /auth-form-panel -->

</div><!-- /auth-page -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
<!-- Auth JS -->
<script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
