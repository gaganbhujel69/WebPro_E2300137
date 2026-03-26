<?php
// ============================================================
//  EMS — Provider Edit Course
//  provider/course_edit.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_approved_provider();

$pdo    = get_db();
$userId = current_user_id();

$provStmt = $pdo->prepare('SELECT provider_id FROM providers WHERE user_id = ? LIMIT 1');
$provStmt->execute([$userId]);
$provider = $provStmt->fetch();
$provId   = (int)$provider['provider_id'];

$courseId = (int)($_GET['id'] ?? 0);

// Fetch course and verify ownership
$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE course_id = ? AND provider_id = ? LIMIT 1');
$courseStmt->execute([$courseId, $provId]);
$course = $courseStmt->fetch();

if (!$course) {
    http_response_code(404);
    die('Course not found or access denied.');
}

$categories = $pdo->query('SELECT * FROM course_categories ORDER BY name')->fetchAll();
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $fee         = (float)($_POST['fee'] ?? 0);
    $seats       = (int)($_POST['seats_total'] ?? 30);
    $startDate   = $_POST['start_date'] ?? '';
    $endDate     = $_POST['end_date']   ?? '';
    $location    = trim($_POST['location'] ?? '');
    $mode        = $_POST['mode'] ?? 'in_person';
    $status      = $_POST['status'] ?? 'draft';
    $duration    = (int)($_POST['duration_days'] ?? 1);

    if (empty($title))     $errors[] = 'Course title is required.';
    if (empty($description)) $errors[] = 'Description is required.';
    if ($fee < 0)          $errors[] = 'Fee cannot be negative.';
    if ($seats < (int)$course['seats_taken']) $errors[] = 'Seats cannot be less than already enrolled.';
    if (empty($startDate)) $errors[] = 'Start date is required.';
    if (empty($endDate))   $errors[] = 'End date is required.';
    if (!empty($startDate) && !empty($endDate) && $endDate < $startDate)
                           $errors[] = 'End date must be on or after start date.';
    if (empty($location))  $errors[] = 'Location is required.';

    if (empty($errors)) {
        $upd = $pdo->prepare(
            'UPDATE courses SET
                category_id = ?, title = ?, description = ?, duration_days = ?,
                fee = ?, seats_total = ?, start_date = ?, end_date = ?,
                location = ?, mode = ?, status = ?
             WHERE course_id = ? AND provider_id = ?'
        );
        $upd->execute([
            $categoryId ?: null, $title, $description, $duration,
            $fee, $seats, $startDate, $endDate,
            $location, $mode, $status, $courseId, $provId
        ]);

        // Reload updated course
        $courseStmt->execute([$courseId, $provId]);
        $course  = $courseStmt->fetch();
        $success = true;
    }
}

// Use POST values on validation failure, else DB values
$v = (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $course;

$pageTitle = 'Edit Course';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-pencil-square me-2 text-ems-primary"></i>Edit Course</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/provider/courses.php">My courses</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss="4000">
        <i class="bi bi-check-circle-fill me-2"></i>Course updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<div class="ems-card p-4" style="max-width:780px;">
    <form method="POST" action="<?= BASE_URL ?>/provider/course_edit.php?id=<?= $courseId ?>" novalidate>
        <?= csrf_field() ?>

        <div class="row g-3">
            <div class="col-12">
                <label for="courseTitle" class="form-label">Course Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="courseTitle" name="title"
                       value="<?= htmlspecialchars($v['title'] ?? '') ?>" required>
            </div>

            <div class="col-md-6">
                <label for="courseCategory" class="form-label">Category</label>
                <select class="form-select" id="courseCategory" name="category_id">
                    <option value="">-- Select --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"
                            <?= ($v['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="courseMode" class="form-label">Delivery Mode</label>
                <select class="form-select" id="courseMode" name="mode">
                    <?php foreach (['in_person'=>'In Person','online'=>'Online','hybrid'=>'Hybrid'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($v['mode'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label for="courseDesc" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="courseDesc" name="description"
                          rows="4"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
                <label for="courseFee" class="form-label">Fee (RM)</label>
                <input type="number" class="form-control" id="courseFee" name="fee"
                       min="0" step="0.01" value="<?= htmlspecialchars($v['fee'] ?? '0.00') ?>">
            </div>

            <div class="col-md-4">
                <label for="courseSeats" class="form-label">Total Seats</label>
                <input type="number" class="form-control" id="courseSeats" name="seats_total"
                       min="<?= $course['seats_taken'] ?>"
                       value="<?= htmlspecialchars($v['seats_total'] ?? '30') ?>">
                <div class="form-text">Currently <?= $course['seats_taken'] ?> enrolled.</div>
            </div>

            <div class="col-md-4">
                <label for="courseDuration" class="form-label">Duration (days)</label>
                <input type="number" class="form-control" id="courseDuration" name="duration_days"
                       min="1" value="<?= htmlspecialchars($v['duration_days'] ?? '1') ?>">
            </div>

            <div class="col-md-6">
                <label for="courseStart" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="courseStart" name="start_date"
                       value="<?= htmlspecialchars($v['start_date'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label for="courseEnd" class="form-label">End Date</label>
                <input type="date" class="form-control" id="courseEnd" name="end_date"
                       value="<?= htmlspecialchars($v['end_date'] ?? '') ?>">
            </div>

            <div class="col-12">
                <label for="courseLocation" class="form-label">Location / URL</label>
                <input type="text" class="form-control" id="courseLocation" name="location"
                       value="<?= htmlspecialchars($v['location'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label for="courseStatus" class="form-label">Publish Status</label>
                <select class="form-select" id="courseStatus" name="status">
                    <?php foreach (['draft'=>'Draft','published'=>'Published','cancelled'=>'Cancelled','completed'=>'Completed'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($v['status'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4" id="btnUpdateCourse">
                <i class="bi bi-floppy me-2"></i>Update Course
            </button>
            <a href="<?= BASE_URL ?>/provider/courses.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
