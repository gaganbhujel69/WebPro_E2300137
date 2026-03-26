<?php
// ============================================================
//  EMS — Course Management Business Logic
//  includes/course_functions.php
//
//  Requires: config/connection.php → $conn (PDO)
// ============================================================


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 1 — LOOKUPS
// └─────────────────────────────────────────────────────────┘

/**
 * Fetch all course categories (for dropdowns).
 *
 * @param  PDO $conn
 * @return array  Rows: category_id, name
 */
function get_categories(PDO $conn): array
{
    return $conn->query(
        'SELECT category_id, name FROM course_categories ORDER BY name'
    )->fetchAll();
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 2 — READ
// └─────────────────────────────────────────────────────────┘

/**
 * Fetch all courses belonging to a specific provider, newest first.
 *
 * @param  PDO $conn
 * @param  int $providerId
 * @return array
 */
function get_provider_courses(PDO $conn, int $providerId): array
{
    $stmt = $conn->prepare(
        "SELECT c.*,
                cc.name AS category_name,
                (SELECT COUNT(*) FROM enrolments e WHERE e.course_id = c.course_id) AS enrolment_count,
                (SELECT COALESCE(SUM(p.amount),0)
                 FROM payments p
                 JOIN enrolments e ON e.enrolment_id = p.enrolment_id
                 WHERE e.course_id = c.course_id AND p.payment_status = 'success') AS total_revenue
         FROM courses c
         LEFT JOIN course_categories cc ON cc.category_id = c.category_id
         WHERE c.provider_id = ?
         ORDER BY c.created_at DESC"
    );
    $stmt->execute([$providerId]);
    return $stmt->fetchAll();
}


/**
 * Fetch a single course row, enforcing provider ownership.
 *
 * @param  PDO      $conn
 * @param  int      $courseId
 * @param  int|null $providerId  Pass null to skip ownership check (e.g. admin view).
 * @return array|false
 */
function get_course(PDO $conn, int $courseId, ?int $providerId = null): array|false
{
    if ($providerId !== null) {
        $stmt = $conn->prepare(
            'SELECT c.*, cc.name AS category_name
             FROM courses c
             LEFT JOIN course_categories cc ON cc.category_id = c.category_id
             WHERE c.course_id = ? AND c.provider_id = ? LIMIT 1'
        );
        $stmt->execute([$courseId, $providerId]);
    } else {
        $stmt = $conn->prepare(
            'SELECT c.*, cc.name AS category_name
             FROM courses c
             LEFT JOIN course_categories cc ON cc.category_id = c.category_id
             WHERE c.course_id = ? LIMIT 1'
        );
        $stmt->execute([$courseId]);
    }
    return $stmt->fetch();
}


/**
 * Provider-level KPIs (for dashboard).
 *
 * @param  PDO $conn
 * @param  int $providerId
 * @return array{total_courses, published_courses, total_enrolments, total_revenue}
 */
function get_provider_kpis(PDO $conn, int $providerId): array
{
    $row = $conn->prepare(
        "SELECT
            COUNT(DISTINCT c.course_id)                                 AS total_courses,
            SUM(c.status = 'published')                                 AS published_courses,
            COUNT(e.enrolment_id)                                       AS total_enrolments,
            COALESCE(SUM(CASE WHEN p.payment_status='success'
                              THEN p.amount ELSE 0 END), 0)             AS total_revenue
         FROM courses c
         LEFT JOIN enrolments e ON e.course_id = c.course_id
         LEFT JOIN payments   p ON p.enrolment_id = e.enrolment_id
         WHERE c.provider_id = ?"
    );
    $row->execute([$providerId]);
    $data = $row->fetch();
    return [
        'total_courses'     => (int)   ($data['total_courses']     ?? 0),
        'published_courses' => (int)   ($data['published_courses'] ?? 0),
        'total_enrolments'  => (int)   ($data['total_enrolments']  ?? 0),
        'total_revenue'     => (float) ($data['total_revenue']     ?? 0),
    ];
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 3 — VALIDATION
// └─────────────────────────────────────────────────────────┘

/**
 * Validate course add/edit form data.
 *
 * @param  array $data  Raw POST values.
 * @param  array $categories  Valid category_id list.
 * @return string[]  Errors (empty = valid).
 */
function validate_course_data(array $data, array $categories): array
{
    $errors   = [];
    $catIds   = array_column($categories, 'category_id');

    if (trim($data['title'] ?? '') === '')
        $errors[] = 'Course title is required.';
    if (mb_strlen(trim($data['title'] ?? '')) > 200)
        $errors[] = 'Course title must not exceed 200 characters.';

    if (trim($data['description'] ?? '') === '')
        $errors[] = 'Course description is required.';

    if (!in_array((int)($data['category_id'] ?? 0), $catIds))
        $errors[] = 'Please select a valid category.';

    $validModes = ['in_person', 'online', 'hybrid'];
    if (!in_array($data['mode'] ?? '', $validModes, true))
        $errors[] = 'Please select a valid delivery mode.';

    $fee = filter_var($data['fee'] ?? '', FILTER_VALIDATE_FLOAT);
    if ($fee === false || $fee < 0)
        $errors[] = 'Fee must be a valid non-negative number.';

    $seats = filter_var($data['seats_total'] ?? '', FILTER_VALIDATE_INT);
    if ($seats === false || $seats < 1)
        $errors[] = 'Seat capacity must be at least 1.';

    $duration = filter_var($data['duration_days'] ?? '', FILTER_VALIDATE_INT);
    if ($duration === false || $duration < 1)
        $errors[] = 'Duration must be at least 1 day.';

    if (empty($data['start_date']))
        $errors[] = 'Start date is required.';

    if (empty($data['end_date']))
        $errors[] = 'End date is required.';

    if (!empty($data['start_date']) && !empty($data['end_date'])
        && strtotime($data['end_date']) <= strtotime($data['start_date']))
        $errors[] = 'End date must be after the start date.';

    $validStatus = ['draft', 'published', 'closed'];
    if (!in_array($data['status'] ?? '', $validStatus, true))
        $errors[] = 'Invalid course status.';

    return $errors;
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 4 — CREATE / UPDATE / DELETE
// └─────────────────────────────────────────────────────────┘

/**
 * Insert a new course row.
 *
 * @param  PDO   $conn
 * @param  int   $providerId
 * @param  array $data  Validated form data.
 * @return array{success: bool, course_id: int|null, message: string}
 */
function create_course(PDO $conn, int $providerId, array $data): array
{
    try {
        $stmt = $conn->prepare(
            'INSERT INTO courses
             (provider_id, category_id, title, description, mode, fee,
              seats_total, seats_taken, duration_days, start_date, end_date,
              location, status)
             VALUES
             (:pid, :cat, :title, :desc, :mode, :fee,
              :seats, 0, :dur, :start, :end, :loc, :status)'
        );
        $stmt->execute([
            ':pid'    => $providerId,
            ':cat'    => (int) $data['category_id'],
            ':title'  => trim($data['title']),
            ':desc'   => trim($data['description']),
            ':mode'   => $data['mode'],
            ':fee'    => (float) $data['fee'],
            ':seats'  => (int)   $data['seats_total'],
            ':dur'    => (int)   $data['duration_days'],
            ':start'  => $data['start_date'],
            ':end'    => $data['end_date'],
            ':loc'    => trim($data['location'] ?? ''),
            ':status' => $data['status'],
        ]);
        return [
            'success'   => true,
            'course_id' => (int) $conn->lastInsertId(),
            'message'   => 'Course created successfully.',
        ];
    } catch (Exception $e) {
        error_log('[EMS create_course] ' . $e->getMessage());
        return ['success' => false, 'course_id' => null, 'message' => 'Failed to create course.'];
    }
}


/**
 * Update an existing course row (ownership enforced via WHERE clause).
 *
 * @param  PDO   $conn
 * @param  int   $courseId
 * @param  int   $providerId
 * @param  array $data  Validated form data.
 * @return array{success: bool, message: string}
 */
function update_course(PDO $conn, int $courseId, int $providerId, array $data): array
{
    try {
        $stmt = $conn->prepare(
            'UPDATE courses
             SET category_id   = :cat,
                 title         = :title,
                 description   = :desc,
                 mode          = :mode,
                 fee           = :fee,
                 seats_total   = :seats,
                 duration_days = :dur,
                 start_date    = :start,
                 end_date      = :end,
                 location      = :loc,
                 status        = :status
             WHERE course_id = :cid AND provider_id = :pid'
        );
        $stmt->execute([
            ':cat'    => (int)   $data['category_id'],
            ':title'  => trim($data['title']),
            ':desc'   => trim($data['description']),
            ':mode'   => $data['mode'],
            ':fee'    => (float) $data['fee'],
            ':seats'  => (int)   $data['seats_total'],
            ':dur'    => (int)   $data['duration_days'],
            ':start'  => $data['start_date'],
            ':end'    => $data['end_date'],
            ':loc'    => trim($data['location'] ?? ''),
            ':status' => $data['status'],
            ':cid'    => $courseId,
            ':pid'    => $providerId,
        ]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Course not found or you do not own it.'];
        }
        return ['success' => true, 'message' => 'Course updated successfully.'];
    } catch (Exception $e) {
        error_log('[EMS update_course] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update course.'];
    }
}


/**
 * Delete a course — blocked if any learner is already enrolled.
 *
 * @param  PDO $conn
 * @param  int $courseId
 * @param  int $providerId
 * @return array{success: bool, message: string}
 */
function delete_course(PDO $conn, int $courseId, int $providerId): array
{
    // Guard: cannot delete if enrolments exist
    $enrolCount = $conn->prepare(
        'SELECT COUNT(*) FROM enrolments WHERE course_id = ?'
    );
    $enrolCount->execute([$courseId]);
    if ((int) $enrolCount->fetchColumn() > 0) {
        return [
            'success' => false,
            'message' => 'This course cannot be deleted because learners are already enrolled.',
        ];
    }

    $stmt = $conn->prepare(
        'DELETE FROM courses WHERE course_id = ? AND provider_id = ?'
    );
    $stmt->execute([$courseId, $providerId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Course not found or you do not own it.'];
    }
    return ['success' => true, 'message' => 'Course deleted successfully.'];
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 5 — PROVIDER ANALYTICS
// └─────────────────────────────────────────────────────────┘

/**
 * Monthly enrolment + revenue for a provider in a given year.
 *
 * @param  PDO $conn
 * @param  int $providerId
 * @param  int $year
 * @return array  12 rows (month 1–12)
 */
function get_provider_monthly_analytics(PDO $conn, int $providerId, int $year = 0): array
{
    if (!$year) $year = (int) date('Y');

    $skeleton = [];
    for ($m = 1; $m <= 12; $m++) {
        $skeleton[$m] = ['month' => $m, 'enrolments' => 0, 'revenue' => 0.0];
    }

    $stmt = $conn->prepare(
        "SELECT MONTH(e.enrolment_date)     AS month,
                COUNT(e.enrolment_id)       AS enrolments,
                COALESCE(SUM(p.amount), 0)  AS revenue
         FROM enrolments e
         JOIN courses c ON c.course_id = e.course_id
         LEFT JOIN payments p
               ON p.enrolment_id = e.enrolment_id AND p.payment_status = 'success'
         WHERE c.provider_id = ? AND YEAR(e.enrolment_date) = ?
         GROUP BY MONTH(e.enrolment_date)"
    );
    $stmt->execute([$providerId, $year]);
    foreach ($stmt->fetchAll() as $row) {
        $skeleton[(int)$row['month']] = [
            'month'      => (int)   $row['month'],
            'enrolments' => (int)   $row['enrolments'],
            'revenue'    => (float) $row['revenue'],
        ];
    }
    return array_values($skeleton);
}


/**
 * Per-course breakdown: enrolments, revenue, average rating.
 *
 * @param  PDO $conn
 * @param  int $providerId
 * @return array
 */
function get_provider_course_breakdown(PDO $conn, int $providerId): array
{
    $stmt = $conn->prepare(
        "SELECT c.course_id, c.title, c.status,
                COUNT(DISTINCT e.enrolment_id)              AS enrolment_count,
                COALESCE(SUM(p.amount), 0)                  AS revenue,
                ROUND(AVG(r.rating), 1)                     AS avg_rating
         FROM courses c
         LEFT JOIN enrolments e ON e.course_id = c.course_id
         LEFT JOIN payments   p ON p.enrolment_id = e.enrolment_id
                                AND p.payment_status = 'success'
         LEFT JOIN reviews    r ON r.course_id = c.course_id
         WHERE c.provider_id = ?
         GROUP BY c.course_id
         ORDER BY enrolment_count DESC"
    );
    $stmt->execute([$providerId]);
    return $stmt->fetchAll();
}
