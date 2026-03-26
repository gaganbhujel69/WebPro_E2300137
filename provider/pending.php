<?php
// ============================================================
//  EMS — Provider Pending Approval Notice
//  provider/pending.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('training_provider');
$pageTitle = 'Account Pending';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column align-items-center justify-content-center py-5">
    <i class="bi bi-hourglass-split text-warning" style="font-size:4rem;"></i>
    <h2 class="mt-3">Account Pending Approval</h2>
    <p class="text-muted text-center" style="max-width:500px;">
        Your training provider registration is currently under review by the Ministry Officer.
        You will be able to manage courses once your account is approved.
        Please check back later.
    </p>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline-secondary mt-3">
        <i class="bi bi-box-arrow-right me-2"></i>Logout
    </a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
