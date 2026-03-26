<?php
// ============================================================
//  EMS — Provider Management (Approve / Reject)
//  admin/providers.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('ministry_officer');

$notice = '';

// ── Handle Approve / Reject POST ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action     = $_POST['action']      ?? '';
    $providerId = (int)($_POST['provider_id'] ?? 0);

    error_log("[EMS Admin Action] Initiating $action for Provider ID: $providerId");

    if ($providerId > 0) {
        if ($action === 'approve') {
            if (approve_provider($conn, $providerId)) {
                $notice = 'Provider approved successfully.';
            } else {
                $notice = 'error:Failed to approve provider. It might already be approved.';
            }
        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (reject_provider($conn, $providerId, $reason)) {
                $notice = 'Provider rejected.';
            } else {
                $notice = 'error:Failed to reject provider.';
            }
        } elseif ($action === 'delete') {
            if (delete_provider($conn, $providerId)) {
                $notice = 'Provider and all associated data deleted successfully.';
            } else {
                $notice = 'error:Failed to delete provider.';
            }
        }
    } else {
        error_log("[EMS Admin Action] Invalid Provider ID: $providerId");
    }
}

$highlightId = (int)($_GET['id'] ?? 0);

// ── Filter ────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, ['pending','approved','rejected','all'], true)) {
    $filterStatus = 'pending';
}
$providers = get_all_providers($conn, $filterStatus === 'all' ? '' : $filterStatus);

$pageTitle = 'Manage Providers';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-people-fill me-2 text-ems-primary"></i>Training Provider Applications</h1>
</div>

<?php if ($notice): ?>
    <?php
    $nType = 'success'; $nMsg = $notice;
    if (str_starts_with($notice, 'error:')) { $nType = 'danger'; $nMsg = substr($notice, 6); }
    ?>
    <div class="alert alert-<?= $nType ?> alert-dismissible fade show" data-auto-dismiss="4000">
        <i class="bi <?= $nType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
        <?= htmlspecialchars($nMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-4">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $s => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filterStatus === $s ? 'active' : '' ?>"
               href="?status=<?= $s ?>"><?= $label ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="ems-card p-4">
    <?php if (empty($providers)): ?>
        <p class="text-muted mb-0">No providers found for this filter.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" data-sort-table>
                <thead>
                    <tr>
                        <th data-col="0">#</th>
                        <th data-col="1">Contact</th>
                        <th data-col="2">Organisation</th>
                        <th data-col="3">Reg No.</th>
                        <th data-col="4">Phone</th>
                        <th data-col="5">Applied</th>
                        <th data-col="6">Document</th>
                        <th data-col="7">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $p): ?>
                    <tr class="<?= ($highlightId === (int)$p['provider_id']) ? 'table-warning' : '' ?>" 
                        id="provider-row-<?= $p['provider_id'] ?>">
                        <td><?= $p['provider_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['full_name']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($p['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($p['organisation_name']) ?></td>
                        <td><?= htmlspecialchars($p['registration_no']) ?></td>
                        <td><?= htmlspecialchars($p['phone']) ?></td>
                        <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <?php if ($p['document_path']): ?>
                                <a href="<?= BASE_URL . '/' . $p['document_path'] ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-info"
                                   title="View Supporting Document">
                                    <i class="bi bi-file-earmark-text"></i> View
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">No doc</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $p['status'] ?> px-2 py-1">
                                <?= ucfirst($p['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <!-- Approve -->
                                    <form method="POST" class="d-inline" data-confirm="Approve this provider?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="provider_id" value="<?= $p['provider_id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success px-3" id="btnApprove<?= $p['provider_id'] ?>">
                                            <i class="bi bi-check-lg me-1"></i> Approve
                                        </button>
                                    </form>
                                    <!-- Reject -->
                                    <button class="btn btn-sm btn-outline-danger px-3"
                                            data-bs-toggle="modal"
                                            data-bs-target="#rejectModal"
                                            data-pid="<?= $p['provider_id'] ?>"
                                            id="btnReject<?= $p['provider_id'] ?>">
                                        <i class="bi bi-x-lg me-1"></i> Reject
                                    </button>
                                <?php endif; ?>

                                <!-- Delete (Always available) -->
                                <form method="POST" class="d-inline" data-confirm="Permanently DELETE this provider and all their courses? This cannot be undone!">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="provider_id" value="<?= $p['provider_id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-sm btn-danger px-3" id="btnDelete<?= $p['provider_id'] ?>">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-ems">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel"><i class="bi bi-x-circle text-danger me-2"></i>Reject Provider</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/providers.php?status=<?= htmlspecialchars($filterStatus) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="provider_id" id="modalProviderId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason"
                                  rows="4" placeholder="Provide a clear reason..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmReject">
                        <i class="bi bi-x-lg me-1"></i>Confirm Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Populate provider_id in reject modal
document.getElementById('rejectModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('modalProviderId').value = btn.dataset.pid;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
