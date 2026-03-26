<?php
// ============================================================
//  EMS — reviews Business Logic
//  includes/review_functions.php
//
//  Requires: config/connection.php → $conn (PDO)
// ============================================================


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 1 — READ
// └─────────────────────────────────────────────────────────┘

/**
 * Fetch completed enrolments that the learner has NOT yet reviewed.
 *
 * @param  PDO $conn
 * @param  int $learnerId
 * @return array  Rows: enrolment_id, course_id, title, organisation_name, end_date
 */
function get_reviewable_enrolments(PDO $conn, int $learnerId): array
{
    $stmt = $conn->prepare(
        "SELECT e.enrolment_id, e.course_id,
                c.title, c.end_date,
                pr.organisation_name
         FROM enrolments e
         JOIN courses   c  ON c.course_id   = e.course_id
         JOIN providers pr ON pr.provider_id = c.provider_id
         WHERE e.learner_id = ?
           AND e.completion_status IN ('enrolled', 'completed')
           AND NOT EXISTS (
               SELECT 1 FROM reviews r WHERE r.enrolment_id = e.enrolment_id
           )
         ORDER BY c.end_date DESC"
    );
    $stmt->execute([$learnerId]);
    return $stmt->fetchAll();
}


/**
 * All reviews submitted by a learner, with course + provider.
 *
 * @param  PDO $conn
 * @param  int $learnerId
 * @return array
 */
function get_learner_reviews(PDO $conn, int $learnerId): array
{
    $stmt = $conn->prepare(
        'SELECT r.*, c.title, pr.organisation_name
         FROM reviews r
         JOIN courses   c  ON c.course_id   = r.course_id
         JOIN providers pr ON pr.provider_id = c.provider_id
         WHERE r.learner_id = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$learnerId]);
    return $stmt->fetchAll();
}


/**
 * Fetch average rating + review count for a single course.
 *
 * @param  PDO $conn
 * @param  int $courseId
 * @return array{avg_rating: float|null, review_count: int}
 */
function get_course_rating(PDO $conn, int $courseId): array
{
    $stmt = $conn->prepare(
        'SELECT ROUND(AVG(rating), 1) AS avg_rating,
                COUNT(review_id)      AS review_count
         FROM reviews WHERE course_id = ?'
    );
    $stmt->execute([$courseId]);
    $row = $stmt->fetch();
    return [
        'avg_rating'   => $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null,
        'review_count' => (int) $row['review_count'],
    ];
}


/**
 * Fetch all reviews for a course with learner names.
 *
 * @param  PDO $conn
 * @param  int $courseId
 * @return array
 */
function get_course_reviews_all(PDO $conn, int $courseId): array
{
    $stmt = $conn->prepare(
        "SELECT r.*, u.full_name AS learner_name
         FROM reviews r
         JOIN users u ON u.user_id = r.learner_id
         WHERE r.course_id = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 2 — VALIDATION
// └─────────────────────────────────────────────────────────┘

/**
 * Validate a review submission.
 *
 * Checks:
 *  - Rating is 1–5.
 *  - Enrolment belongs to the learner and is completed.
 *  - No duplicate review for this enrolment.
 *
 * @param  PDO    $conn
 * @param  int    $learnerId
 * @param  int    $rating
 * @param  int    $enrolmentId
 * @return array{errors: string[], course_id: int|null}
 */
function validate_review(PDO $conn, int $learnerId, int $rating, int $enrolmentId): array
{
    $errors   = [];
    $courseId = null;

    // Rating range
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a star rating between 1 and 5.';
    }

    // Ownership + completion check
    $chk = $conn->prepare(
        "SELECT e.enrolment_id, e.course_id
         FROM enrolments e
         WHERE e.enrolment_id = ? AND e.learner_id = ? AND e.completion_status IN ('enrolled', 'completed')
         LIMIT 1"
    );
    $chk->execute([$enrolmentId, $learnerId]);
    $enrolRow = $chk->fetch();

    if (!$enrolRow) {
        $errors[] = 'You can only review courses you have completed.';
    } else {
        $courseId = (int) $enrolRow['course_id'];

        // Duplicate review guard
        $dup = $conn->prepare(
            'SELECT review_id FROM reviews WHERE enrolment_id = ? LIMIT 1'
        );
        $dup->execute([$enrolmentId]);
        if ($dup->fetch()) {
            $errors[] = 'You have already submitted a review for this enrolment.';
        }
    }

    return ['errors' => $errors, 'course_id' => $courseId];
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 3 — WRITE
// └─────────────────────────────────────────────────────────┘

/**
 * Insert a new review row.
 * Call validate_review() before this to ensure data is clean.
 *
 * @param  PDO    $conn
 * @param  int    $learnerId
 * @param  int    $enrolmentId
 * @param  int    $courseId
 * @param  int    $rating      1–5
 * @param  string $feedback    Optional written feedback.
 * @return array{success: bool, message: string}
 */
function submit_review(
    PDO    $conn,
    int    $learnerId,
    int    $enrolmentId,
    int    $courseId,
    int    $rating,
    string $feedback = ''
): array {
    try {
        $conn->prepare(
            'INSERT INTO reviews (enrolment_id, course_id, learner_id, rating, feedback)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $enrolmentId,
            $courseId,
            $learnerId,
            $rating,
            $feedback !== '' ? $feedback : null,
        ]);
        return ['success' => true, 'message' => 'Thank you! Your review has been submitted.'];
    } catch (Exception $e) {
        error_log('[EMS submit_review] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to submit review. Please try again.'];
    }
}
