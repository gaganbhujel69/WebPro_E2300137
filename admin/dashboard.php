<?php
// ============================================================
//  EMS — Ministry Officer Dashboard
//  admin/dashboard.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('ministry_officer');

// All data fetched via admin_functions — no inline SQL
$kpis    = get_system_kpis($conn);
$pending = get_pending_providers($conn);
// Limit display to 5 on dashboard
$pending = array_slice($pending, 0, 5);

$pageTitle = 'Ministry Officer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-ems-primary"></i>Officer Dashboard</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
    <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= date('d M Y, H:i') ?></span>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= $kpis['pending_providers'] ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-building-check"></i></div>
            <div>
                <div class="stat-value"><?= $kpis['approved_providers'] ?></div>
                <div class="stat-label">Approved Providers</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div>
                <div class="stat-value"><?= $kpis['total_courses'] ?></div>
                <div class="stat-label">Published courses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= $kpis['total_enrolments'] ?></div>
                <div class="stat-label">Total enrolments</div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Providers Table -->
<div class="ems-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-hourglass me-2"></i>Pending Provider Applications</h5>
        <a href="<?= BASE_URL ?>/admin/providers.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <?php if (empty($pending)): ?>
        <p class="text-muted mb-0"><i class="bi bi-check-circle me-1 text-success"></i>No pending applications.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sort-table>
                <thead>
                    <tr>
                        <th data-col="0">Contact Person</th>
                        <th data-col="1">Organisation</th>
                        <th data-col="2">Applied On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['organisation_name']) ?></td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/providers.php?id=<?= $row['provider_id'] ?>"
                               class="btn btn-sm btn-primary">Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
