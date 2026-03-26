<?php
// ============================================================
//  EMS — Provider Analytics
//  provider/analytics.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_approved_provider();

$userId = current_user_id();
$prov   = $conn->prepare('SELECT provider_id FROM providers WHERE user_id = ? LIMIT 1');
$prov->execute([$userId]);
$provId = (int)$prov->fetch()['provider_id'];

$year = (int)($_GET['year'] ?? date('Y'));

// All data from course_functions — no inline SQL
$monthlyRows   = get_provider_monthly_analytics($conn, $provId, $year);  // 12 rows
$yearlyData    = get_yearly_analytics($conn);   // system-wide (reuse admin fn) / can use this for overview
$perCourseData = get_provider_course_breakdown($conn, $provId);

// Flatten monthly rows for display table
$monthNames   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$enrolByMonth = array_fill(1, 12, 0);
$revByMonth   = array_fill(1, 12, 0);
foreach ($monthlyRows as $row) {
    $enrolByMonth[$row['month']] = $row['enrolments'];
    $revByMonth[$row['month']]   = $row['revenue'];
}

$pageTitle = 'Analytics Report';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1><i class="bi bi-graph-up me-2 text-ems-primary"></i>Analytics Report</h1>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 small fw-600">Year:</label>
        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
            <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Monthly Table -->
<div class="ems-card p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-calendar3 me-2"></i>Monthly Breakdown — <?= $year ?></h5>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0 text-center">
            <thead>
                <tr>
                    <th class="text-start">Metric</th>
                    <?php foreach ($monthNames as $m): ?><th><?= $m ?></th><?php endforeach; ?>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-start fw-600">enrolments</td>
                    <?php $t=0; foreach(range(1,12) as $i): $v=$enrolByMonth[$i]; $t+=$v; ?>
                        <td><?= $v ?></td>
                    <?php endforeach; ?>
                    <td class="fw-600"><?= $t ?></td>
                </tr>
                <tr>
                    <td class="text-start fw-600">Revenue (RM)</td>
                    <?php $t=0; foreach(range(1,12) as $i): $v=$revByMonth[$i]; $t+=$v; ?>
                        <td><?= number_format($v,2) ?></td>
                    <?php endforeach; ?>
                    <td class="fw-600"><?= number_format($t,2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Yearly Summary -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="ems-card p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-bar-chart me-2"></i>Yearly Summary</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Year</th><th class="text-center">enrolments</th><th class="text-end">Revenue (RM)</th></tr></thead>
                    <tbody>
                        <?php if (empty($yearlyData)): ?>
                            <tr><td colspan="3" class="text-muted text-center">No data yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($yearlyData as $yr): ?>
                            <tr>
                                <td><?= $yr['yr'] ?></td>
                                <td class="text-center"><?= $yr['enrolments'] ?></td>
                                <td class="text-end"><?= number_format($yr['revenue'],2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Per-Course Breakdown -->
    <div class="col-lg-7">
        <div class="ems-card p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-journal-text me-2"></i>Per-Course Breakdown</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-sort-table>
                    <thead><tr>
                        <th data-col="0">Course</th>
                        <th data-col="1" class="text-center">enrolments</th>
                        <th data-col="2" class="text-end">Revenue</th>
                        <th data-col="3" class="text-center">Avg Rating</th>
                    </tr></thead>
                    <tbody>
                        <?php if (empty($perCourseData)): ?>
                            <tr><td colspan="4" class="text-muted text-center">No courses yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($perCourseData as $pc): ?>
                            <tr>
                                <td><?= htmlspecialchars($pc['title']) ?></td>
                                <td class="text-center"><?= $pc['enrolments'] ?></td>
                                <td class="text-end">RM <?= number_format($pc['revenue'],2) ?></td>
                                <td class="text-center">
                                    <?php if ($pc['avg_rating']): ?>
                                        <span class="text-warning"><i class="bi bi-star-fill"></i></span>
                                        <?= number_format($pc['avg_rating'],1) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
