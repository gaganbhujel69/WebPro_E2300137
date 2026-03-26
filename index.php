<?php
// ============================================================
//  EMS — Landing Page
//  index.php
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Redirect logged-in users to their portal
if (is_logged_in()) {
    $redirectMap = [
        'ministry_officer'  => BASE_URL . '/admin/dashboard.php',
        'training_provider' => BASE_URL . '/provider/dashboard.php',
        'learner'           => BASE_URL . '/learner/dashboard.php',
    ];
    $dest = $redirectMap[current_role()] ?? (BASE_URL . '/auth/login.php');
    header('Location: ' . $dest);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EduSkill Marketplace System — Find and enrol in short courses from trusted local training providers.">
    <title>EduSkill Marketplace System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="m-0 p-0">

<!-- Hero Section -->
<section class="d-flex flex-column justify-content-center align-items-center text-white text-center"
         style="min-height:100vh;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#2563eb 100%);">
    <div class="container px-4">
        <div class="mb-4">
            <i class="bi bi-mortarboard-fill" style="font-size:4rem;color:#f59e0b;"></i>
        </div>
        <h1 class="display-5 fw-800 mb-3">
            EduSkill <span style="color:#f59e0b;">Marketplace</span> System
        </h1>
        <p class="lead mb-5 text-white-50" style="max-width:600px;margin:0 auto;">
            Connecting learners with trusted local training providers.
            Discover, enrol, and grow your skills with certified short courses.
        </p>
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-warning btn-lg px-5 fw-600">
                <i class="bi bi-person-plus-fill me-2"></i>Get Started
            </a>
            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-light btn-lg px-5 fw-600">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </a>
        </div>

        <!-- Feature pills -->
        <div class="d-flex flex-wrap gap-3 justify-content-center mt-5">
            <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.1);font-size:.85rem;">
                <i class="bi bi-shield-check me-1"></i>Ministry Verified Providers
            </span>
            <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.1);font-size:.85rem;">
                <i class="bi bi-receipt me-1"></i>Official Enrolment Receipts
            </span>
            <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.1);font-size:.85rem;">
                <i class="bi bi-star-fill me-1"></i>Verified Course reviews
            </span>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
</body>
</html>
