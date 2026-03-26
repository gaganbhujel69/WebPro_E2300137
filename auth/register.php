<?php
// ============================================================
//  EMS — Registration Page (Learner or Training Provider)
//  auth/register.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Redirect if already logged in
if (is_logged_in()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$errors  = [];
$success = '';

// ── Handle POST submission ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check — aborts if token invalid
    verify_csrf();

    // 2. Load DB connection
    require_once __DIR__ . '/../config/connection.php';

    // 3. Collect all POST data into one array for the functions
    $formData = [
        'role'              => post_str('role'),
        'full_name'         => post_str('full_name'),
        'email'             => post_str('email'),
        'password'          => $_POST['password']         ?? '',
        'password_confirm'  => $_POST['password_confirm'] ?? '',
        // Provider-only fields (ignored by validate if role ≠ provider)
        'organisation_name' => post_str('organisation_name'),
        'registration_no'   => post_str('registration_no'),
        'address'           => post_str('address'),
        'phone'             => post_str('phone'),
        'website'           => post_str('website'),
    ];

    // 4. Validate form data (structure, length, format, required fields)
    $errors = validate_registration($formData);

    // 5. If validation passes, attempt DB insert via register_user()
    //    register_user() handles: duplicate-email check, password hashing,
    //    DB transaction, users + providers insert.
    if (empty($errors)) {
        $result = register_user($conn, $formData);

        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for EduSkill Marketplace System as a Learner or Training Provider.">
    <title>Create Account | EduSkill Marketplace System</title>

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

    <style>
        /* Override form panel width for registration (needs more room) */
        @media (min-width: 992px) {
            .auth-form-panel { max-width: 600px; padding: 2.5rem 3rem; }
        }
    </style>
</head>
<body>

<div class="auth-page">

    <!-- ══ LEFT BRANDING PANEL ══════════════════════════════ -->
    <div class="auth-brand-panel">

        <div class="auth-bubbles">
            <div class="auth-bubble"></div>
            <div class="auth-bubble"></div>
            <div class="auth-bubble"></div>
        </div>

        <div class="auth-brand-logo" style="position:relative;z-index:1;">
            <i class="bi bi-mortarboard-fill me-2" style="color:#f59e0b;"></i>
            EduSkill<span style="color:#f59e0b;">EMS</span>
        </div>

        <div style="position:relative;z-index:1;">
            <p class="auth-brand-headline">
                Join <span style="color:#f59e0b;">EMS</span><br>
                as a Learner or<br>Training Provider
            </p>
            <p class="auth-brand-sub">
                Create your free account today and take the first step toward
                upskilling or reaching new learners nationwide.
            </p>
        </div>

        <!-- Visual role comparison -->
        <div style="position:relative;z-index:1;">
            <div style="background:rgba(255,255,255,.07);border-radius:.75rem;padding:1.25rem;">
                <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.5);margin-bottom:.75rem;">
                    What you can do
                </p>
                <div class="row g-2">
                    <div class="col-6">
                        <p style="font-size:.8rem;font-weight:700;color:#f59e0b;margin-bottom:.5rem;">
                            <i class="bi bi-person-fill me-1"></i>As Learner
                        </p>
                        <ul style="list-style:none;padding:0;margin:0;font-size:.78rem;color:rgba(255,255,255,.7);">
                            <li class="mb-1"><i class="bi bi-check2 me-1 text-success"></i>Browse courses</li>
                            <li class="mb-1"><i class="bi bi-check2 me-1 text-success"></i>Enrol &amp; pay online</li>
                            <li class="mb-1"><i class="bi bi-check2 me-1 text-success"></i>Get receipts</li>
                            <li><i class="bi bi-check2 me-1 text-success"></i>Leave reviews</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <p style="font-size:.8rem;font-weight:700;color:#60a5fa;margin-bottom:.5rem;">
                            <i class="bi bi-building me-1"></i>As Provider
                        </p>
                        <ul style="list-style:none;padding:0;margin:0;font-size:.78rem;color:rgba(255,255,255,.7);">
                            <li class="mb-1"><i class="bi bi-check2 me-1 text-success"></i>List short courses</li>
                            <li class="mb-1"><i class="bi bi-check2 me-1 text-success"></i>Manage enrolments</li>
                            <li class="mb-1"><i class="bi bi-check2 me-1 text-success"></i>Track revenue</li>
                            <li><i class="bi bi-check2 me-1 text-success"></i>Analytics reports</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <p style="position:relative;z-index:1;color:rgba(255,255,255,.4);font-size:.78rem;margin:0;">
            &copy; <?= date('Y') ?> EduSkill Marketplace System
        </p>

    </div><!-- /auth-brand-panel -->

    <!-- ══ RIGHT FORM PANEL ══════════════════════════════════ -->
    <div class="auth-form-panel">
        <div>

            <!-- Mobile logo -->
            <div class="d-flex d-lg-none justify-content-center mb-4">
                <span style="font-size:1.4rem;font-weight:800;color:#2563eb;">
                    <i class="bi bi-mortarboard-fill me-1" style="color:#f59e0b;"></i>
                    EduSkill<span style="color:#f59e0b;">EMS</span>
                </span>
            </div>

            <?php if ($success): ?>
                <!-- SUCCESS STATE -->
                <div class="text-center py-3">
                    <div style="width:72px;height:72px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
                        <i class="bi bi-check-lg" style="font-size:2rem;color:#16a34a;"></i>
                    </div>
                    <h2 class="auth-form-title text-center mb-2">Account Created!</h2>
                    <div class="auth-alert auth-alert-success" style="justify-content:center;margin-bottom:1.5rem;">
                        <i class="bi bi-info-circle-fill"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>/auth/login.php"
                       class="btn-auth"
                       style="display:block;text-decoration:none;text-align:center;">
                        <span class="btn-label">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                        </span>
                    </a>
                </div>

            <?php else: ?>
                <!-- REGISTRATION FORM -->
                <h1 class="auth-form-title">Create Account</h1>
                <p class="auth-form-sub">Register as a Learner or Training Provider</p>

                <!-- Error Alert -->
                <?php if ($errors): ?>
                    <div class="auth-alert auth-alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Please fix the following:</strong>
                            <ul class="mb-0 ps-3 mt-1">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/auth/register.php" id="authForm" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <!-- ── Role Toggle Cards ───────────────── -->
                    <div class="auth-input-group">
                        <label>I am registering as</label>
                        <div class="role-toggle">
                            <div class="role-card">
                                <input type="radio"
                                       id="roleLearner"
                                       name="role"
                                       value="learner"
                                       <?= (($_POST['role'] ?? 'learner') === 'learner') ? 'checked' : '' ?>>
                                <label for="roleLearner">
                                    <i class="bi bi-person-fill"></i>
                                    Learner
                                    <small style="font-weight:400;color:inherit;opacity:.7;">Browse &amp; Enrol</small>
                                </label>
                            </div>
                            <div class="role-card">
                                <input type="radio"
                                       id="roleProvider"
                                       name="role"
                                       value="training_provider"
                                       <?= (($_POST['role'] ?? '') === 'training_provider') ? 'checked' : '' ?>>
                                <label for="roleProvider">
                                    <i class="bi bi-building"></i>
                                    Provider
                                    <small style="font-weight:400;color:inherit;opacity:.7;">List courses</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Common Fields ───────────────────── -->
                    <div class="auth-input-group">
                        <label for="regFullName">Full Name</label>
                        <i class="bi bi-person auth-input-icon"></i>
                        <input type="text"
                               class="form-control"
                               id="regFullName"
                               name="full_name"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               placeholder="Jane Smith"
                               required>
                    </div>

                    <div class="auth-input-group">
                        <label for="regEmail">Email Address</label>
                        <i class="bi bi-envelope auth-input-icon"></i>
                        <input type="email"
                               class="form-control"
                               id="regEmail"
                               name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="jane@example.com"
                               required>
                    </div>

                    <div class="auth-input-group">
                        <label for="regPassword">Password</label>
                        <i class="bi bi-lock auth-input-icon"></i>
                        <input type="password"
                               class="form-control"
                               id="regPassword"
                               name="password"
                               placeholder="Min. 8 characters"
                               required>
                        <button type="button"
                                class="btn-pw-toggle"
                                data-target="regPassword"
                                aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                        <!-- Strength bar -->
                        <div class="pw-strength-bar mt-1">
                            <div class="pw-strength-fill" id="pwStrengthFill"></div>
                        </div>
                        <div class="pw-strength-label" id="pwStrengthLabel"></div>
                    </div>

                    <div class="auth-input-group">
                        <label for="passwordConfirm">Confirm Password</label>
                        <i class="bi bi-lock-fill auth-input-icon"></i>
                        <input type="password"
                               class="form-control"
                               id="passwordConfirm"
                               name="password_confirm"
                               placeholder="Re-enter password"
                               required>
                        <button type="button"
                                class="btn-pw-toggle"
                                data-target="passwordConfirm"
                                aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                        <small id="pwMatchMsg" class="d-block mt-1 fw-600"></small>
                    </div>

                    <!-- ── Provider Extra Fields (animated) ── -->
                    <div class="provider-fields" id="providerFields">
                        <p class="provider-section-label">
                            <i class="bi bi-building me-1"></i>Organisation Details
                        </p>

                        <div class="auth-input-group">
                            <label for="orgName">Organisation Name</label>
                            <i class="bi bi-building auth-input-icon"></i>
                            <input type="text"
                                   class="form-control"
                                   id="orgName"
                                   name="organisation_name"
                                   value="<?= htmlspecialchars($_POST['organisation_name'] ?? '') ?>"
                                   placeholder="Acme Training Sdn Bhd"
                                   data-required="1">
                        </div>

                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="regNo">Registration No.</label>
                                    <i class="bi bi-hash auth-input-icon"></i>
                                    <input type="text"
                                           class="form-control"
                                           id="regNo"
                                           name="registration_no"
                                           value="<?= htmlspecialchars($_POST['registration_no'] ?? '') ?>"
                                           placeholder="ROC-123456"
                                           data-required="1">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="regPhone">Phone Number</label>
                                    <i class="bi bi-telephone auth-input-icon"></i>
                                    <input type="tel"
                                           class="form-control"
                                           id="regPhone"
                                           name="phone"
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                           placeholder="+60-12-345-6789"
                                           data-required="1">
                                </div>
                            </div>
                        </div>

                        <div class="auth-input-group">
                            <label for="regAddress">Address</label>
                            <i class="bi bi-geo-alt auth-input-icon" style="top:14px;transform:none;"></i>
                            <textarea class="form-control no-icon"
                                      id="regAddress"
                                      name="address"
                                      rows="2"
                                      placeholder="Street, City, State, Postcode"
                                      style="padding-left:1rem;"
                                      data-required="1"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>

                        <div class="auth-input-group">
                            <label for="regWebsite">
                                Website
                                <small style="font-weight:400;text-transform:none;color:#94a3b8;">(optional)</small>
                            </label>
                            <i class="bi bi-globe auth-input-icon"></i>
                            <input type="url"
                                   class="form-control"
                                   id="regWebsite"
                                   name="website"
                                   value="<?= htmlspecialchars($_POST['website'] ?? '') ?>"
                                   placeholder="https://yourorg.com">
                        </div>

                        <div class="auth-input-group">
                            <label for="regDoc">
                                Supporting Documents
                                <small style="font-weight:400;text-transform:none;color:#94a3b8;">(PDF/Image, Max 5MB)</small>
                            </label>
                            <i class="bi bi-file-earmark-pdf auth-input-icon"></i>
                            <input type="file"
                                   class="form-control"
                                   id="regDoc"
                                   name="supporting_doc"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted" style="font-size:0.75rem;">Upload company registration or accreditation.</small>
                        </div>

                        <!-- Provider note -->
                        <div class="d-flex gap-2 align-items-start mb-3"
                             style="background:#eff6ff;border-radius:.6rem;padding:.75rem .9rem;">
                            <i class="bi bi-info-circle-fill mt-1" style="color:#2563eb;flex-shrink:0;"></i>
                            <p class="mb-0" style="font-size:.8rem;color:#1d4ed8;line-height:1.5;">
                                Provider accounts are reviewed by a Ministry Officer before activation.
                                You'll be notified once approved.
                            </p>
                        </div>
                    </div><!-- /provider-fields -->

                    <!-- Submit -->
                    <button type="submit" class="btn-auth mt-1" id="btnSubmitAuth">
                        <span class="spinner"></span>
                        <span class="btn-label">
                            <i class="bi bi-person-check-fill me-2"></i>Create Account
                        </span>
                    </button>

                </form>

                <div class="auth-divider">or</div>

                <p class="text-center mb-0" style="font-size:.885rem;color:#475569;">
                    Already have an account?
                    <a href="<?= BASE_URL ?>/auth/login.php"
                       class="fw-700"
                       style="color:#2563eb;text-decoration:none;">
                        Sign in &rarr;
                    </a>
                </p>

            <?php endif; ?>
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
