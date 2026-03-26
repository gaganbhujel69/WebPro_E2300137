<?php
// ============================================================
//  EMS — Training Provider Dashboard
//  provider/dashboard.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_approved_provider();

$pdo        = get_db();
$userId     = current_user_id();

// Get provider record
$prov = $pdo->prepare('SELECT * FROM providers WHERE user_id = ? LIMIT 1');
$prov->execute([$userId]);
$provider = $prov->fetch();
$provId   = (int)$provider['provider_id'];

// KPIs
$kpis = [
    'total_courses'    => $pdo->prepare('SELECT COUNT(*) FROM courses WHERE provider_id = ?'),
    'published'        => $pdo->prepare('SELECT COUNT(*) FROM courses WHERE provider_id = ? AND status = "published"'),
    'total_enrolments' => $pdo->prepare(
        'SELECT COUNT(*) FROM enrolments e JOIN courses c ON c.course_id = e.course_id WHERE c.provider_id = ?'
    ),
    'revenue' => $pdo->prepare(
        'SELECT COALESCE(SUM(p.amount),0) FROM payments p
         JOIN enrolments e ON e.enrolment_id = p.enrolment_id
         JOIN courses c ON c.course_id = e.course_id
         WHERE c.provider_id = ? AND p.payment_status = "success"'
    ),
];
foreach ($kpis as $key => $stmt) {
    $stmt->execute([$provId]);
    $kpis[$key] = $stmt->fetchColumn();
}

// Recent enrolments
$recent = $pdo->prepare(
    'SELECT u.full_name, c.title, e.enrolment_date, e.payment_status
     FROM enrolments e
     JOIN users u ON u.user_id = e.learner_id
     JOIN courses c ON c.course_id = e.course_id
     WHERE c.provider_id = ?
     ORDER BY e.enrolment_date DESC LIMIT 6'
);
$recent->execute([$provId]);
$recentenrolments = $recent->fetchAll();

$pageTitle = 'Provider Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-speedometer2 me-2 text-ems-primary"></i>
        <?= htmlspecialchars($provider['organisation_name']) ?>
    </h1>
    <p class="text-muted mb-0">Training Provider Portal</p>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-bookmark"></i></div>
            <div><div class="stat-value"><?= $kpis['total_courses'] ?></div><div class="stat-label">Total courses</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-broadcast"></i></div>
            <div><div class="stat-value"><?= $kpis['published'] ?></div><div class="stat-label">Published</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-people"></i></div>
            <div><div class="stat-value"><?= $kpis['total_enrolments'] ?></div><div class="stat-label">enrolments</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-dollar"></i></div>
            <div>
                <div class="stat-value">RM <?= number_format((float)$kpis['revenue'], 0) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions + Recent enrolments -->
<div class="row g-4">
    <div class="col-lg-4">
        <div class="ems-card p-4">
            <h5 class="mb-3">Quick Actions</h5>
            <div class="d-grid gap-2">
                <a href="<?= BASE_URL ?>/provider/course_add.php" class="btn btn-primary" id="btnAddCourse">
                    <i class="bi bi-plus-circle me-2"></i>Add New Course
                </a>
                <a href="<?= BASE_URL ?>/provider/courses.php" class="btn btn-outline-primary" id="btnManagecourses">
                    <i class="bi bi-journal-text me-2"></i>Manage courses
                </a>
                <a href="<?= BASE_URL ?>/provider/analytics.php" class="btn btn-outline-secondary" id="btnAnalytics">
                    <i class="bi bi-graph-up me-2"></i>View Analytics
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ems-card p-4">
            <h5 class="mb-3"><i class="bi bi-clock-history me-2"></i>Recent enrolments</h5>
            <?php if (empty($recentenrolments)): ?>
                <p class="text-muted mb-0">No enrolments yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Learner</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentenrolments as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= date('d M Y', strtotime($row['enrolment_date'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $row['payment_status'] ?> px-2 py-1">
                                        <?= ucfirst($row['payment_status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
