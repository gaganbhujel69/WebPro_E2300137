<?php
// ============================================================
//  EMS — Enrolment & Payment Business Logic
//  includes/enrolment_functions.php
//
//  Requires: config/connection.php → $conn (PDO)
// ============================================================


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 1 — READ
// └─────────────────────────────────────────────────────────┘

/**
 * Fetch all enrolments for a learner, with course + provider details.
 *
 * @param  PDO $conn
 * @param  int $learnerId
 * @param  int $limit  0 = no limit
 * @return array
 */
function get_learner_enrolments(PDO $conn, int $learnerId, int $limit = 0): array
{
    $sql = "SELECT e.*,
                   c.title, c.start_date, c.end_date, c.mode, c.fee,
                   pr.organisation_name,
                   p.payment_status AS pay_status,
                   p.amount         AS amount_paid,
                   EXISTS(SELECT 1 FROM reviews r WHERE r.enrolment_id = e.enrolment_id) AS has_review
            FROM enrolments e
            JOIN courses   c  ON c.course_id   = e.course_id
            JOIN providers pr ON pr.provider_id = c.provider_id
            LEFT JOIN payments p ON p.enrolment_id = e.enrolment_id
            WHERE e.learner_id = ?
            ORDER BY e.enrolment_date DESC";
    if ($limit > 0) $sql .= " LIMIT {$limit}";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$learnerId]);
    return $stmt->fetchAll();
}


/**
 * Return just the course IDs the learner is enrolled in.
 * Used for fast "Enrolled" badge logic on the browse page.
 *
 * @param  PDO $conn
 * @param  int $learnerId
 * @return int[]
 */
function get_learner_enrolled_ids(PDO $conn, int $learnerId): array
{
    $stmt = $conn->prepare(
        'SELECT course_id FROM enrolments WHERE learner_id = ?'
    );
    $stmt->execute([$learnerId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


/**
 * Fetch a specific enrolment row with full course + provider detail.
 * Returns false if not found or does not belong to $learnerId.
 *
 * @param  PDO $conn
 * @param  int $enrolmentId
 * @param  int $learnerId
 * @return array|false
 */
function get_enrolment_detail(PDO $conn, int $enrolmentId, int $learnerId): array|false
{
    $stmt = $conn->prepare(
        'SELECT e.*, c.title, c.fee, c.start_date, c.end_date,
                c.location, c.mode, c.duration_days,
                pr.organisation_name
         FROM enrolments e
         JOIN courses   c  ON c.course_id   = e.course_id
         JOIN providers pr ON pr.provider_id = c.provider_id
         WHERE e.enrolment_id = ? AND e.learner_id = ? LIMIT 1'
    );
    $stmt->execute([$enrolmentId, $learnerId]);
    return $stmt->fetch();
}


/**
 * Fetch an existing payment + receipt for an enrolment, if any.
 *
 * @param  PDO $conn
 * @param  int $enrolmentId
 * @return array|false
 */
function get_existing_payment(PDO $conn, int $enrolmentId): array|false
{
    $stmt = $conn->prepare(
        'SELECT p.*, r.receipt_no
         FROM payments p
         LEFT JOIN receipts r ON r.payment_id = p.payment_id
         WHERE p.enrolment_id = ? LIMIT 1'
    );
    $stmt->execute([$enrolmentId]);
    return $stmt->fetch();
}


/**
 * Learner dashboard KPIs.
 *
 * @param  PDO $conn
 * @param  int $learnerId
 * @return array{total_enrolled, total_completed, total_spent}
 */
function get_learner_kpis(PDO $conn, int $learnerId): array
{
    $row = $conn->prepare(
        "SELECT
            COUNT(e.enrolment_id)                                   AS total_enrolled,
            SUM(e.completion_status = 'completed')                  AS total_completed,
            COALESCE(SUM(CASE WHEN p.payment_status = 'success'
                              THEN p.amount ELSE 0 END), 0)         AS total_spent
         FROM enrolments e
         LEFT JOIN payments p ON p.enrolment_id = e.enrolment_id
         WHERE e.learner_id = ?"
    );
    $row->execute([$learnerId]);
    $data = $row->fetch();
    return [
        'total_enrolled'  => (int)   ($data['total_enrolled']  ?? 0),
        'total_completed' => (int)   ($data['total_completed'] ?? 0),
        'total_spent'     => (float) ($data['total_spent']     ?? 0),
    ];
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 2 — ENROLMENT
// └─────────────────────────────────────────────────────────┘

/**
 * Enrol a learner into a course.
 *
 * Steps performed inside a transaction:
 *   1. Check duplicate enrolment
 *   2. Check seats still available + course is published
 *   3. INSERT into enrolments
 *   4. Increment seats_taken on the course
 *   On success: returns enrolment_id for redirect to payment page.
 *
 * @param  PDO $conn
 * @param  int $learnerId
 * @param  int $courseId
 * @return array{success: bool, enrolment_id: int|null, message: string}
 */
function enrol_learner(PDO $conn, int $learnerId, int $courseId): array
{
    // 1. Duplicate check
    $dup = $conn->prepare(
        'SELECT enrolment_id FROM enrolments WHERE learner_id = ? AND course_id = ? LIMIT 1'
    );
    $dup->execute([$learnerId, $courseId]);
    if ($dup->fetch()) {
        return ['success' => false, 'enrolment_id' => null,
                'message' => 'You are already enrolled in this course.'];
    }

    // 2. Seat & status check
    $seat = $conn->prepare(
        'SELECT course_id FROM courses
         WHERE course_id = ? AND status = "published" AND seats_taken < seats_total LIMIT 1'
    );
    $seat->execute([$courseId]);
    if (!$seat->fetch()) {
        return ['success' => false, 'enrolment_id' => null,
                'message' => 'This course is no longer available or is fully booked.'];
    }

    // 3 + 4. Insert + seat increment inside transaction
    $conn->beginTransaction();
    try {
        $ins = $conn->prepare(
            'INSERT INTO enrolments (learner_id, course_id, payment_status, completion_status)
             VALUES (?, ?, "pending", "enrolled")'
        );
        $ins->execute([$learnerId, $courseId]);
        $newId = (int) $conn->lastInsertId();

        $conn->prepare('UPDATE courses SET seats_taken = seats_taken + 1 WHERE course_id = ?')
             ->execute([$courseId]);

        $conn->commit();
        return ['success' => true, 'enrolment_id' => $newId, 'message' => 'Enrolled successfully.'];

    } catch (Exception $e) {
        $conn->rollBack();
        error_log('[EMS enrol_learner] ' . $e->getMessage());
        return ['success' => false, 'enrolment_id' => null,
                'message' => 'Enrolment failed due to a server error. Please try again.'];
    }
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 3 — PAYMENT & RECEIPT
// └─────────────────────────────────────────────────────────┘

/**
 * Validate the payment form fields.
 *
 * @param  string $method
 * @param  string $cardholderName
 * @return string[]  Errors
 */
function validate_payment(string $method, string $cardholderName): array
{
    $errors = [];
    $validMethods = ['credit_card', 'debit_card', 'online_transfer', 'others'];
    if (!in_array($method, $validMethods, true))
        $errors[] = 'Please select a valid payment method.';
    if (trim($cardholderName) === '')
        $errors[] = 'Cardholder / account name is required.';
    return $errors;
}


/**
 * Process a payment (simulated) and issue a receipt.
 *
 * Steps inside a transaction:
 *   1. INSERT into payments
 *   2. UPDATE enrolments.payment_status = 'paid' + amount_paid
 *   3. INSERT into receipts with a formatted receipt number
 *
 * @param  PDO    $conn
 * @param  int    $enrolmentId
 * @param  float  $amount
 * @param  string $method        One of credit_card|debit_card|online_transfer|others
 * @param  string $cardholderName
 * @return array{success: bool, payment_id: int|null, receipt_no: string, message: string}
 */
function process_payment(
    PDO    $conn,
    int    $enrolmentId,
    float  $amount,
    string $method,
    string $cardholderName
): array {
    $transRef   = 'TXN-' . strtoupper(bin2hex(random_bytes(5)));
    $receiptNo  = 'RCP-' . date('Ymd') . '-' . str_pad($enrolmentId, 6, '0', STR_PAD_LEFT);

    $conn->beginTransaction();
    try {
        // 1. Insert payment record
        $insPay = $conn->prepare(
            'INSERT INTO payments
             (enrolment_id, transaction_ref, amount, payment_method, payment_status, paid_at)
             VALUES (:eid, :ref, :amt, :method, "success", NOW())'
        );
        $insPay->execute([
            ':eid'    => $enrolmentId,
            ':ref'    => $transRef,
            ':amt'    => $amount,
            ':method' => $method,
        ]);
        $paymentId = (int) $conn->lastInsertId();

        // 2. Mark enrolment as paid
        $conn->prepare(
            'UPDATE enrolments SET payment_status = "paid", amount_paid = ? WHERE enrolment_id = ?'
        )->execute([$amount, $enrolmentId]);

        // 3. Issue receipt
        $conn->prepare('INSERT INTO receipts (payment_id, receipt_no) VALUES (?, ?)')
             ->execute([$paymentId, $receiptNo]);

        $conn->commit();
        return [
            'success'    => true,
            'payment_id' => $paymentId,
            'receipt_no' => $receiptNo,
            'message'    => 'Payment successful.',
        ];
    } catch (Exception $e) {
        $conn->rollBack();
        error_log('[EMS process_payment] ' . $e->getMessage());
        return [
            'success'    => false,
            'payment_id' => null,
            'receipt_no' => '',
            'message'    => 'Payment processing failed. Please try again.',
        ];
    }
}
