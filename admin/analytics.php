<?php
// ============================================================
//  EMS — System-wide Analytics (Ministry Officer)
//  admin/analytics.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('ministry_officer');

// Year filter
$year        = (int)($_GET['year'] ?? date('Y'));
$yearOptions = get_analytics_years($conn);

// Fetch all data from admin_functions — no inline SQL
$monthlyRows = get_monthly_analytics($conn, $year);   // 12 rows
$topcourses  = get_top_courses($conn, 5);
$byCategory  = get_category_breakdown($conn);

// Flatten monthly rows into simple arrays for the display table
$monthNames   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$enrolByMonth = array_fill(1, 12, 0);
$revByMonth   = array_fill(1, 12, 0);
foreach ($monthlyRows as $row) {
    $enrolByMonth[$row['month']] = $row['enrolments'];
    $revByMonth[$row['month']]   = $row['revenue'];
}

$pageTitle = 'System Analytics';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1><i class="bi bi-bar-chart-fill me-2 text-ems-primary"></i>System-wide Analytics</h1>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label for="yearSelect" class="form-label mb-0 small fw-600">Year:</label>
        <select name="year" id="yearSelect" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
            <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Monthly enrolments Table -->
<div class="ems-card p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-calendar3 me-2"></i>Monthly enrolments & Revenue — <?= $year ?></h5>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <?php foreach ($monthNames as $m): ?>
                        <th class="text-center"><?= $m ?></th>
                    <?php endforeach; ?>
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-600">enrolments</td>
                    <?php
                    $totalEnrol = 0;
                    foreach (range(1,12) as $i):
                        $v = $enrolByMonth[$i]; $totalEnrol += $v;
                    ?>
                        <td class="text-center"><?= $v ?></td>
                    <?php endforeach; ?>
                    <td class="text-center fw-600"><?= $totalEnrol ?></td>
                </tr>
                <tr>
                    <td class="fw-600">Revenue (RM)</td>
                    <?php
                    $totalRev = 0;
                    foreach (range(1,12) as $i):
                        $v = $revByMonth[$i]; $totalRev += $v;
                    ?>
                        <td class="text-center"><?= number_format($v, 2) ?></td>
                    <?php endforeach; ?>
                    <td class="text-center fw-600"><?= number_format($totalRev, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Top courses & Category Breakdown side by side -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="ems-card p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Top 5 courses</h5>
            <?php if (empty($topcourses)): ?>
                <p class="text-muted mb-0">No enrolment data yet.</p>
            <?php else: ?>
                <ol class="mb-0 ps-3">
                    <?php foreach ($topcourses as $tc): ?>
                        <li class="mb-2">
                            <strong><?= htmlspecialchars($tc['title']) ?></strong>
                            <small class="text-muted d-block"><?= htmlspecialchars($tc['organisation_name']) ?></small>
                            <span class="badge bg-primary rounded-pill"><?= $tc['total'] ?> enrolments</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ems-card p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-pie-chart-fill text-ems-primary me-2"></i>enrolments by Category</h5>
            <?php if (empty($byCategory)): ?>
                <p class="text-muted mb-0">No data yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Category</th><th class="text-end">enrolments</th></tr></thead>
                        <tbody>
                            <?php foreach ($byCategory as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['name'] ?? 'Uncategorised') ?></td>
                                <td class="text-end fw-600"><?= $cat['total'] ?></td>
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
