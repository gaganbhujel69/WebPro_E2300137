<?php
// ============================================================
//  EMS — Learner: Browse & Enrol in courses
//  learner/courses.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/enrolment_functions.php';
require_once __DIR__ . '/../includes/course_functions.php';
require_once __DIR__ . '/../includes/review_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('learner');

$learnerId = current_user_id();
$notice    = '';

// ── Handle Enrolment POST ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enrol') {
    verify_csrf();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $result   = enrol_learner($conn, $learnerId, $courseId);

    if ($result['success']) {
        // Redirect to payment
        header("Location: " . BASE_URL . "/learner/payment.php?enrolment_id={$result['enrolment_id']}");
        exit;
    } else {
        $notice = 'error:' . $result['message'];
    }
}

// ── Handle AJAX: Get reviews ──────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_reviews') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $reviews  = get_course_reviews_all($conn, $courseId);

    // Format dates for display
    foreach ($reviews as &$rv) {
        $rv['created_at'] = date('d M Y', strtotime($rv['created_at']));
    }

    header('Content-Type: application/json');
    echo json_encode($reviews);
    exit;
}

// ── Filters & Options ─────────────────────────────────────
$searchQ   = trim($_GET['q']          ?? '');
$filterCat = (int)($_GET['category']  ?? 0);
$filterMode= $_GET['mode']            ?? '';

// My enrolled course IDs
$myEnrolIds = array_keys(get_learner_enrolled_ids($conn, $learnerId));

// Reviewable courses
$reviewableenrolments = get_reviewable_enrolments($conn, $learnerId);
$reviewableCourseIds  = array_column($reviewableenrolments, 'enrolment_id', 'course_id');

// Categories
$categories = get_categories($conn);

// Build query for published courses
$sql  = "SELECT c.*, cc.name AS category_name, pr.organisation_name,
                (c.seats_total - c.seats_taken) AS seats_left,
                ROUND((SELECT AVG(r.rating) FROM reviews r WHERE r.course_id = c.course_id), 1) AS avg_rating
         FROM courses c
         JOIN providers pr  ON pr.provider_id = c.provider_id AND pr.status = 'approved'
         LEFT JOIN course_categories cc ON cc.category_id = c.category_id
         WHERE c.status = 'published'";
$args = [];

if ($searchQ) {
    $sql  .= ' AND (c.title LIKE ? OR c.description LIKE ? OR pr.organisation_name LIKE ?)';
    $like  = '%' . $searchQ . '%';
    $args  = array_merge($args, [$like, $like, $like]);
}
if ($filterCat) {
    $sql  .= ' AND c.category_id = ?';
    $args[] = $filterCat;
}
if (in_array($filterMode, ['in_person','online','hybrid'], true)) {
    $sql  .= ' AND c.mode = ?';
    $args[] = $filterMode;
}
$sql .= ' ORDER BY c.start_date ASC';

$stmt = $conn->prepare($sql);
$stmt->execute($args);
$courses = $stmt->fetchAll();

// KPIs for header
$kpis = get_learner_kpis($conn, $learnerId);
$totalSpent = $kpis['total_spent'];

$pageTitle = 'Browse courses';
include __DIR__ . '/../includes/header.php';

// Unpack notice type
$noticeType = 'success'; $noticeMsg = '';
if ($notice) {
    if (str_starts_with($notice, 'error:')) { $noticeType = 'danger'; $noticeMsg = substr($notice, 6); }
    else { $noticeMsg = $notice; }
}
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <h1><i class="bi bi-search me-2 text-ems-primary"></i>Browse courses</h1>
    <div>
        <div class="px-3 py-2 bg-white rounded-ems border shadow-sm small d-inline-block">
            <span class="text-muted me-2">Total Spent:</span>
            <span class="fw-bold text-success">RM <?= number_format($totalSpent, 2) ?></span>
        </div>
    </div>
</div>

<?php if ($noticeMsg): ?>
    <div class="alert alert-<?= $noticeType ?> alert-dismissible fade show" data-auto-dismiss="5000">
        <?= htmlspecialchars($noticeMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/learner/courses.php" class="ems-card p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" name="q" id="courseSearchInput"
                   value="<?= htmlspecialchars($searchQ) ?>" placeholder="Course name, provider...">
        </div>
        <div class="col-md-3">
            <label class="form-label">Category</label>
            <select class="form-select" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>"
                        <?= $filterCat == $cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Mode</label>
            <select class="form-select" name="mode">
                <option value="">All</option>
                <?php foreach (['in_person'=>'In Person','online'=>'Online','hybrid'=>'Hybrid'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $filterMode===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100" id="btnFiltercourses">
                <i class="bi bi-filter me-1"></i>Filter
            </button>
        </div>
    </div>
</form>

<!-- Course Cards -->
<?php if (empty($courses)): ?>
    <div class="text-center py-5">
        <i class="bi bi-journal-x" style="font-size:3rem;color:var(--ems-border);"></i>
        <p class="text-muted mt-3">No courses match your search criteria.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        <?php foreach ($courses as $c):
            $courseId = $c['course_id'];
            $enrolled = in_array($courseId, $myEnrolIds, true);
            $full     = $c['seats_left'] <= 0;
            $canReview= isset($reviewableCourseIds[$courseId]);
        ?>
        <div class="col course-card-wrapper">
            <div class="course-card">
                <div class="course-card-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="badge bg-light text-dark mb-2"><?= htmlspecialchars($c['category_name'] ?? 'General') ?></span>
                        <span class="badge" style="background:rgba(255,255,255,0.2);">
                            <?= ucfirst(str_replace('_',' ',$c['mode'])) ?>
                        </span>
                    </div>
                    <h5 class="mb-1 fw-600"><?= htmlspecialchars($c['title']) ?></h5>
                    <small><?= htmlspecialchars($c['organisation_name']) ?></small>
                </div>
                <div class="course-card-body">
                    <p class="text-muted small mb-3" style="line-height:1.5;">
                        <?= htmlspecialchars(mb_strimwidth($c['description'], 0, 120, '…')) ?>
                    </p>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($c['start_date'])) ?></span>
                        <span><i class="bi bi-clock me-1"></i><?= $c['duration_days'] ?> day(s)</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span>
                            <i class="bi bi-person me-1"></i>
                            <?= $c['seats_left'] ?> seat<?= $c['seats_left'] != 1 ? 's' : '' ?> left
                        </span>
                        <?php if ($c['avg_rating']): ?>
                        <span class="text-warning">
                            <i class="bi bi-star-fill"></i> <?= $c['avg_rating'] ?>
                            <a href="javascript:void(0)" class="ms-1 small text-decoration-none view-reviews-link"
                               data-course-id="<?= $courseId ?>" data-course-title="<?= htmlspecialchars($c['title']) ?>">
                                (View reviews)
                            </a>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-700 text-ems-primary fs-5">
                            <?= $c['fee'] > 0 ? 'RM ' . number_format($c['fee'], 2) : 'Free' ?>
                        </span>
                        <?php if ($canReview): ?>
                            <a href="<?= BASE_URL ?>/learner/reviews.php?course_id=<?= $courseId ?>" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-star"></i> Review Course
                            </a>
                        <?php elseif ($enrolled): ?>
                            <span class="badge badge-enrolled px-3 py-2">Enrolled</span>
                        <?php elseif ($full): ?>
                            <span class="badge bg-secondary px-3 py-2">Full</span>
                        <?php else: ?>
                            <form method="POST" action="<?= BASE_URL ?>/learner/courses.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"    value="enrol">
                                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                <button type="submit"
                                        class="btn btn-primary btn-sm"
                                        id="btnEnrol<?= $c['course_id'] ?>">
                                    Enrol Now
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- reviews Modal -->
<div class="modal fade" id="reviewsModal" tabindex="-1" aria-labelledby="reviewsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-600" id="reviewsModalLabel">Course reviews</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="reviewsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Fetching reviews...</p>
                </div>
                <div id="reviewsContent" style="display:none;">
                    <div id="reviewItems" class="d-flex flex-column gap-3"></div>
                </div>
                <div id="noReviews" class="text-center py-4" style="display:none;">
                    <i class="bi bi-chat-left-dots text-muted" style="font-size:2rem;"></i>
                    <p class="text-muted mt-2">No reviews yet for this course.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewsModal = new bootstrap.Modal(document.getElementById('reviewsModal'));
    const reviewsContent = document.getElementById('reviewsContent');
    const reviewsLoading = document.getElementById('reviewsLoading');
    const noReviews = document.getElementById('noReviews');
    const reviewItems = document.getElementById('reviewItems');
    const modalTitle = document.getElementById('reviewsModalLabel');

    document.querySelectorAll('.view-reviews-link').forEach(link => {
        link.addEventListener('click', function() {
            const courseId = this.dataset.courseId;
            const courseTitle = this.dataset.courseTitle;

            modalTitle.textContent = 'reviews for: ' + courseTitle;
            reviewItems.innerHTML = '';
            reviewsContent.style.display = 'none';
            noReviews.style.display = 'none';
            reviewsLoading.style.display = 'block';

            reviewsModal.show();

            fetch('<?= BASE_URL ?>/learner/courses.php?action=get_reviews&course_id=' + courseId)
                .then(response => response.json())
                .then(data => {
                    reviewsLoading.style.display = 'none';
                    if (data.length > 0) {
                        data.forEach(rv => {
                            const stars = Array(5).fill(0).map((_, i) =>
                                `<i class="bi bi-star${i < rv.rating ? '-fill' : ''} text-warning"></i>`
                            ).join('');

                            const card = `
                                <div class="ems-card p-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold">${escapeHtml(rv.learner_name)}</div>
                                        <div>${stars}</div>
                                    </div>
                                    ${rv.feedback ? `<p class="mb-2 text-muted small">${escapeHtml(rv.feedback)}</p>` : ''}
                                    <div class="text-muted x-small">
                                        <i class="bi bi-clock me-1"></i>${rv.created_at}
                                    </div>
                                </div>
                            `;
                            reviewItems.innerHTML += card;
                        });
                        reviewsContent.style.display = 'block';
                    } else {
                        noReviews.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error(err);
                    reviewsLoading.innerHTML = '<div class="text-danger">Failed to load reviews.</div>';
                });
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<style>
.x-small { font-size: 0.75rem; }
</style>
