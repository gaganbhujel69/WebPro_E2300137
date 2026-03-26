<?php
// ============================================================
//  EMS — Provider Course Management (List + Delete)
//  provider/courses.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_approved_provider();

$userId = current_user_id();
$prov   = $conn->prepare('SELECT provider_id FROM providers WHERE user_id = ? LIMIT 1');
$prov->execute([$userId]);
$provId = (int)$prov->fetch()['provider_id'];

$notice = '';

// ── Handle Delete (with enrolment guard) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $result   = delete_course($conn, $courseId, $provId);
    $notice   = $result['success'] ? $result['message'] : '';
    if (!$result['success']) {
        $notice = 'error:' . $result['message'];
    }
}

// ── Fetch courses via course_functions ────────────────────
$courses = get_provider_courses($conn, $provId);

$pageTitle = 'My courses';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1><i class="bi bi-journal-bookmark-fill me-2 text-ems-primary"></i>My courses</h1>
    <a href="<?= BASE_URL ?>/provider/course_add.php" class="btn btn-primary" id="btnAddCourse">
        <i class="bi bi-plus-circle me-2"></i>Add New Course
    </a>
</div>

<?php if ($notice): ?>
    <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss="4000">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($notice) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="ems-card p-4">
    <?php if (empty($courses)): ?>
        <div class="text-center py-5">
            <i class="bi bi-journal-x" style="font-size:3rem;color:var(--ems-border);"></i>
            <p class="text-muted mt-3">You haven't listed any courses yet.</p>
            <a href="<?= BASE_URL ?>/provider/course_add.php" class="btn btn-primary">Add Your First Course</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" data-sort-table>
                <thead>
                    <tr>
                        <th data-col="0">Title</th>
                        <th data-col="1">Category</th>
                        <th data-col="2">Fee (RM)</th>
                        <th data-col="3">Start Date</th>
                        <th data-col="4">Seats</th>
                        <th data-col="5">enrolments</th>
                        <th data-col="6">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
                        <td><?= htmlspecialchars($c['category_name'] ?? '—') ?></td>
                        <td><?= number_format($c['fee'], 2) ?></td>
                        <td><?= date('d M Y', strtotime($c['start_date'])) ?></td>
                        <td>
                            <?= $c['seats_taken'] ?>/<?= $c['seats_total'] ?>
                            <div class="progress mt-1" style="height:4px;">
                                <div class="progress-bar seat-progress"
                                     role="progressbar"
                                     data-taken="<?= $c['seats_taken'] ?>"
                                     data-total="<?= $c['seats_total'] ?>"
                                     style="width:0%"></div>
                            </div>
                        </td>
                        <td class="text-center"><?= $c['enrol_count'] ?></td>
                        <td>
                            <span class="badge badge-<?= $c['status'] ?> px-2 py-1">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/provider/course_edit.php?id=<?= $c['course_id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1"
                               id="btnEdit<?= $c['course_id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline"
                                  data-confirm="Delete this course? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"    value="delete">
                                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                        id="btnDelete<?= $c['course_id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
