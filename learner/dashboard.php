<?php
// ============================================================
//  EMS — Learner Dashboard
//  learner/dashboard.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/enrolment_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('learner');

$learnerId = current_user_id();

// KPIs from enrolment_functions
$kpis = get_learner_kpis($conn, $learnerId);
$totalEnrolled  = $kpis['total_enrolled'];
$totalCompleted = $kpis['total_completed'];
$totalPaid      = $kpis['total_spent'];

// My enrolments (limit 5 for dashboard)
$myenrolments = get_learner_enrolments($conn, $learnerId);
$myenrolments = array_slice($myenrolments, 0, 5);

$pageTitle = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-house-fill me-2 text-ems-primary"></i>
        Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Learner') ?>
    </h1>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-check"></i></div>
            <div><div class="stat-value"><?= $totalEnrolled ?></div><div class="stat-label">courses Enrolled</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-patch-check"></i></div>
            <div><div class="stat-value"><?= $totalCompleted ?></div><div class="stat-label">Completed</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-currency-dollar"></i></div>
            <div>
                <div class="stat-value">RM <?= number_format((float)$totalPaid, 2) ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent enrolments + Actions -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="ems-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>My enrolments</h5>
                <a href="<?= BASE_URL ?>/learner/courses.php" class="btn btn-sm btn-outline-primary">Browse More</a>
            </div>
            <?php if (empty($myenrolments)): ?>
                <p class="text-muted mb-0">You haven't enrolled in any courses yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Course</th><th>Start</th><th>Status</th><th>Payment</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($myenrolments as $e): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($e['title']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($e['organisation_name']) ?></small>
                                </td>
                                <td><?= date('d M Y', strtotime($e['start_date'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $e['completion_status'] ?> px-2 py-1">
                                        <?= ucfirst($e['completion_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($e['pay_status']): ?>
                                        <div class="mb-1">
                                            <span class="badge badge-<?= $e['pay_status'] ?> px-2 py-1">
                                                <?= ucfirst($e['pay_status']) ?>
                                            </span>
                                        </div>
                                        <?php if ($e['pay_status'] === 'success' && $e['amount_paid'] > 0): ?>
                                            <small class="text-success fw-bold">RM <?= number_format($e['amount_paid'], 2) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/learner/payment.php?enrolment_id=<?= $e['enrolment_id'] ?>"
                                           class="btn btn-sm btn-warning" id="btnPay<?= $e['enrolment_id'] ?>">
                                            Pay Now
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$e['has_review']): ?>
                                        <a href="<?= BASE_URL ?>/learner/reviews.php?course_id=<?= $e['course_id'] ?>"
                                           class="btn btn-sm btn-outline-warning"
                                           id="btnReview<?= $e['enrolment_id'] ?>">
                                            <i class="bi bi-star"></i> Review
                                        </a>
                                    <?php else: ?>
                                        <span class="badge text-warning border border-warning px-2 py-1 bg-light">
                                            <i class="bi bi-star-fill"></i> Reviewed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ems-card p-4">
            <h5 class="mb-3">Quick Links</h5>
            <div class="d-grid gap-2">
                <a href="<?= BASE_URL ?>/learner/courses.php" class="btn btn-primary" id="btnBrowse">
                    <i class="bi bi-search me-2"></i>Browse courses
                </a>
                <a href="<?= BASE_URL ?>/learner/reviews.php" class="btn btn-outline-secondary" id="btnMyreviews">
                    <i class="bi bi-star me-2"></i>My reviews
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
