<?php
// ============================================================
//  EMS — Learner: Rate & Review enrolled/Completed courses
//  learner/reviews.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_role('learner');

$learnerId = current_user_id();

$errors  = [];
$success = '';

// ── Handle Review Submission ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $formData = [
        'enrolment_id' => $_POST['enrolment_id'] ?? 0,
        'rating'       => $_POST['rating']       ?? 0,
        'feedback'     => $_POST['feedback']     ?? ''
    ];

    $rating   = (int)($formData['rating'] ?? 0);
    $enrolId  = (int)($formData['enrolment_id'] ?? 0);

    $v = validate_review($conn, $learnerId, $rating, $enrolId);
    $errors = $v['errors'];

    if (empty($errors)) {
        $courseId = $v['course_id'];
        $result = submit_review($conn, $learnerId, $enrolId, $courseId, $rating, $formData['feedback']);
        if ($result['success']) {
            $success = $result['message'];
            // Clear feedback form on success
            unset($_POST['feedback']);
        } else {
            $errors[] = $result['message'];
        }
    }
}

// ── Fetch display data via review_functions ────────────────
$eligible  = get_reviewable_enrolments($conn, $learnerId);
$myreviews = get_learner_reviews($conn, $learnerId);

// Pre-select from URL (coming from dashboard review button)
$preselectedCourseId = (int)($_GET['course_id'] ?? 0);

$pageTitle = 'My reviews';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-star-fill me-2 text-ems-primary"></i>My reviews</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss="5000">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Review Form -->
    <div class="col-lg-5">
        <div class="ems-card p-4">
            <h5 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Submit a Review</h5>

            <?php
            // Filter eligible for not-yet-reviewed
            $pendingReview = array_filter($eligible, fn($e) => !$e['existing_review_id']);
            ?>

            <?php if (empty($pendingReview)): ?>
                <p class="text-muted">You have no enrolled or completed courses awaiting a review.</p>
            <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/learner/reviews.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="reviewEnrolment" class="form-label">Select Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="reviewEnrolment" name="enrolment_id" required>
                            <option value="">-- Choose an enrolled course --</option>
                            <?php foreach ($pendingReview as $e): ?>
                                <option value="<?= $e['enrolment_id'] ?>"
                                    <?= ($preselectedCourseId === (int)$e['course_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Rating <span class="text-danger">*</span></label>
                        <!-- Star Rating (CSS-only, RTL trick) -->
                        <div class="star-rating" id="starRating" style="justify-content:flex-start;flex-direction:row;">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i>1?'s':'' ?>">
                                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>">
                                    <i class="bi bi-star-fill" style="font-size:1.75rem;cursor:pointer;"
                                       data-star="<?= $i ?>"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <small class="text-muted" id="starLabel">Click to rate</small>
                    </div>

                    <div class="mb-4">
                        <label for="reviewFeedback" class="form-label">Written Feedback <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="reviewFeedback" name="feedback"
                                  rows="4" placeholder="Share your experience with this course..."><?= htmlspecialchars($_POST['feedback'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="btnSubmitReview">
                        <i class="bi bi-send me-2"></i>Submit Review
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Past reviews -->
    <div class="col-lg-7">
        <div class="ems-card p-4">
            <h5 class="mb-4"><i class="bi bi-list-stars me-2"></i>My Submitted reviews</h5>

            <?php if (empty($myreviews)): ?>
                <p class="text-muted mb-0">You haven't submitted any reviews yet.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($myreviews as $rv): ?>
                        <div class="ems-card p-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <strong><?= htmlspecialchars($rv['title']) ?></strong>
                                    <div class="text-muted small"><?= htmlspecialchars($rv['organisation_name']) ?></div>
                                </div>
                                <div class="text-warning">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="bi bi-star<?= ($s <= $rv['rating']) ? '-fill' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($rv['feedback']): ?>
                                <p class="text-muted small mb-0 mt-1"><?= htmlspecialchars($rv['feedback']) ?></p>
                            <?php endif; ?>
                            <div class="text-muted small mt-2">
                                <i class="bi bi-clock me-1"></i>
                                Reviewed on <?= date('d M Y', strtotime($rv['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Star rating interactivity (plain JS, no framework) -->
<script>
(function () {
    const stars  = document.querySelectorAll('#starRating label i');
    const radios = document.querySelectorAll('#starRating input[type="radio"]');
    const lbl    = document.getElementById('starLabel');
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    function paintStars(upTo) {
        stars.forEach(function (s) {
            const v = parseInt(s.dataset.star, 10);
            s.className = v <= upTo
                ? 'bi bi-star-fill text-warning'
                : 'bi bi-star text-muted';
        });
        if (lbl) lbl.textContent = upTo ? labels[upTo] + ' (' + upTo + '/5)' : 'Click to rate';
    }

    stars.forEach(function (s) {
        s.addEventListener('mouseenter', function () { paintStars(parseInt(this.dataset.star, 10)); });
    });

    document.getElementById('starRating').addEventListener('mouseleave', function () {
        const checked = document.querySelector('#starRating input:checked');
        paintStars(checked ? parseInt(checked.value, 10) : 0);
    });

    radios.forEach(function (r) {
        r.addEventListener('change', function () { paintStars(parseInt(this.value, 10)); });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
