<?php
// ============================================================
//  EMS — Provider Add Course
//  provider/course_add.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_functions.php';
require_once __DIR__ . '/../config/connection.php';
require_approved_provider();

$userId     = current_user_id();
$prov       = $conn->prepare('SELECT provider_id FROM providers WHERE user_id = ? LIMIT 1');
$prov->execute([$userId]);
$provId     = (int)$prov->fetch()['provider_id'];

$categories = get_categories($conn);   // from course_functions
$errors     = [];
$success    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Collect data as one array — matches validate_course_data() signature
    $formData = [
        'title'        => $_POST['title']        ?? '',
        'description'  => $_POST['description']  ?? '',
        'category_id'  => $_POST['category_id']  ?? 0,
        'mode'         => $_POST['mode']         ?? 'in_person',
        'fee'          => $_POST['fee']          ?? 0,
        'seats_total'  => $_POST['seats_total']  ?? 30,
        'duration_days'=> $_POST['duration_days']?? 1,
        'start_date'   => $_POST['start_date']   ?? '',
        'end_date'     => $_POST['end_date']     ?? '',
        'location'     => $_POST['location']     ?? '',
        'status'       => $_POST['status']       ?? 'draft',
    ];

    // Validate
    $errors = validate_course_data($formData, $categories);

    // Insert
    if (empty($errors)) {
        $result  = create_course($conn, $provId, $formData);
        $success = $result['success'];
        if (!$success) $errors[] = $result['message'];
    }
}

$pageTitle = 'Add New Course';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-plus-circle me-2 text-ems-primary"></i>Add New Course</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/provider/courses.php">My courses</a></li>
            <li class="breadcrumb-item active">Add</li>
        </ol>
    </nav>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Course created successfully!
        <a href="<?= BASE_URL ?>/provider/courses.php" class="fw-600 ms-2">Back to My courses</a>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$success): ?>
<div class="ems-card p-4" style="max-width:780px;">
    <form method="POST" action="<?= BASE_URL ?>/provider/course_add.php" novalidate>
        <?= csrf_field() ?>

        <div class="row g-3">
            <div class="col-12">
                <label for="courseTitle" class="form-label">Course Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="courseTitle" name="title"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            </div>

            <div class="col-md-6">
                <label for="courseCategory" class="form-label">Category</label>
                <select class="form-select" id="courseCategory" name="category_id">
                    <option value="">-- Select --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"
                            <?= (($_POST['category_id'] ?? '') == $cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="courseMode" class="form-label">Delivery Mode <span class="text-danger">*</span></label>
                <select class="form-select" id="courseMode" name="mode">
                    <option value="in_person"  <?= (($_POST['mode'] ?? 'in_person') === 'in_person')  ? 'selected' : '' ?>>In Person</option>
                    <option value="online"     <?= (($_POST['mode'] ?? '') === 'online')     ? 'selected' : '' ?>>Online</option>
                    <option value="hybrid"     <?= (($_POST['mode'] ?? '') === 'hybrid')     ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

            <div class="col-12">
                <label for="courseDesc" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="courseDesc" name="description"
                          rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
                <label for="courseFee" class="form-label">Fee (RM) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="courseFee" name="fee"
                       min="0" step="0.01"
                       value="<?= htmlspecialchars($_POST['fee'] ?? '0.00') ?>">
            </div>

            <div class="col-md-4">
                <label for="courseSeats" class="form-label">Total Seats <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="courseSeats" name="seats_total"
                       min="1" value="<?= htmlspecialchars($_POST['seats_total'] ?? '30') ?>">
            </div>

            <div class="col-md-4">
                <label for="courseDuration" class="form-label">Duration (days) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="courseDuration" name="duration_days"
                       min="1" value="<?= htmlspecialchars($_POST['duration_days'] ?? '1') ?>">
            </div>

            <div class="col-md-6">
                <label for="courseStart" class="form-label">Start Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="courseStart" name="start_date"
                       value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label for="courseEnd" class="form-label">End Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="courseEnd" name="end_date"
                       value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
            </div>

            <div class="col-12">
                <label for="courseLocation" class="form-label">Location / URL <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="courseLocation" name="location"
                       placeholder="e.g. Room 3B, Tech Hub or https://zoom.us/..."
                       value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label for="courseStatus" class="form-label">Publish Status</label>
                <select class="form-select" id="courseStatus" name="status">
                    <option value="draft"     <?= (($_POST['status'] ?? 'draft') === 'draft')     ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= (($_POST['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4" id="btnSaveCourse">
                <i class="bi bi-floppy me-2"></i>Save Course
            </button>
            <a href="<?= BASE_URL ?>/provider/courses.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
