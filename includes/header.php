<?php
// ============================================================
//  EMS — Global HTML Header / Navigation
//  includes/header.php
//  Usage: include at the top of every page AFTER require_login()
//         Pass $pageTitle before including, e.g.:
//         $pageTitle = 'Dashboard'; include '...header.php';
// ============================================================
if (!isset($pageTitle)) { $pageTitle = 'EduSkill Marketplace'; }
$role = current_role() ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EduSkill Marketplace System — Find and enrol in short courses from trusted local providers.">
    <title><?= htmlspecialchars($pageTitle) ?> | EMS</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- EMS Global Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    
    <!-- Mobile Navbar Critical Fix -->
    <style>
        @media (max-width: 991.98px) {
            .ems-navbar { height: auto !important; min-height: 64px !important; background: #0f172a !important; display: block !important; position: relative !important; z-index: 10000 !important; }
            .ems-navbar .container-fluid { background: #0f172a !important; display: flex !important; flex-wrap: wrap !important; align-items: center !important; justify-content: space-between !important; padding: 0.75rem 1rem !important; }
            .navbar-collapse { background: #0f172a !important; display: none; position: relative !important; width: 100% !important; padding: 0 1rem 1.5rem 1rem !important; }
            .navbar-collapse.show { display: block !important; }
            .navbar-nav .nav-link { color: white !important; display: block !important; padding: 1rem 0 !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; font-weight: 600 !important; }
            .navbar-nav .nav-link:last-child { border-bottom: none !important; }
        }
    </style>
</head>
<body>

<!-- ── Navigation Bar ─────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark ems-navbar" id="mainNavbar">
    <div class="container-fluid">

        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/index.php">
            <i class="bi bi-mortarboard-fill me-2"></i>EduSkill<span class="text-warning">EMS</span>
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMain"
                aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Links -->
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php if ($role === 'ministry_officer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/providers.php">
                            <i class="bi bi-people-fill me-1"></i>Providers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/analytics.php">
                            <i class="bi bi-bar-chart-fill me-1"></i>Analytics
                        </a>
                    </li>

                <?php elseif ($role === 'training_provider'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/provider/dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/provider/courses.php">
                            <i class="bi bi-journal-bookmark-fill me-1"></i>My courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/provider/analytics.php">
                            <i class="bi bi-graph-up me-1"></i>Analytics
                        </a>
                    </li>

                <?php elseif ($role === 'learner'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/learner/dashboard.php">
                            <i class="bi bi-house-fill me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/learner/courses.php">
                            <i class="bi bi-search me-1"></i>Browse courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/learner/reviews.php">
                            <i class="bi bi-star-fill me-1"></i>My reviews
                        </a>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- Right-side user info / logout -->
            <ul class="navbar-nav ms-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <span class="dropdown-item-text text-muted small">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-warning btn-sm ms-2" href="<?= BASE_URL ?>/auth/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Page Wrapper ───────────────────────────────────────── -->
<main class="ems-main">
    <div class="container-fluid px-4 py-3">
