<?php
// ============================================================
//  EMS — Ministry Officer (Admin) Business Logic
//  includes/admin_functions.php
//
//  Requires: config/connection.php → $conn (PDO)
// ============================================================


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 1 — DASHBOARD KPIs
// └─────────────────────────────────────────────────────────┘

/**
 * Get system-wide KPI counts for the admin dashboard.
 *
 * @param  PDO $conn
 * @return array{
 *   total_learners:   int,
 *   total_providers:  int,
 *   pending_providers:int,
 *   total_courses:    int,
 *   total_enrolments: int,
 *   total_revenue:    float
 * }
 */
function get_system_kpis(PDO $conn): array
{
    $row = $conn->query(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'learner')                AS total_learners,
            (SELECT COUNT(*) FROM providers)                                    AS total_providers,
            (SELECT COUNT(*) FROM providers WHERE status = 'pending')           AS pending_providers,
            (SELECT COUNT(*) FROM providers WHERE status = 'approved')          AS approved_providers,
            (SELECT COUNT(*) FROM courses WHERE status = 'published')           AS total_courses,
            (SELECT COUNT(*) FROM enrolments)                                   AS total_enrolments,
            (SELECT COALESCE(SUM(amount),0) FROM payments
             WHERE payment_status = 'success')                                  AS total_revenue"
    )->fetch();

    return [
        'total_learners'     => (int)   $row['total_learners'],
        'total_providers'    => (int)   $row['total_providers'],
        'pending_providers'  => (int)   $row['pending_providers'],
        'approved_providers' => (int)   $row['approved_providers'],
        'total_courses'      => (int)   $row['total_courses'],
        'total_enrolments'   => (int)   $row['total_enrolments'],
        'total_revenue'      => (float) $row['total_revenue'],
    ];
}


/**
 * Fetch all pending provider applications (newest first).
 *
 * @param  PDO $conn
 * @return array  Rows with: user_id, full_name, email, organisation_name,
 *                           registration_no, phone, website, created_at
 */
function get_pending_providers(PDO $conn): array
{
    return $conn->query(
        "SELECT u.user_id, u.full_name, u.email,
                p.provider_id, p.organisation_name, p.registration_no,
                p.phone, p.website, p.created_at
         FROM providers p
         JOIN users u ON u.user_id = p.user_id
         WHERE p.status = 'pending'
         ORDER BY p.created_at DESC"
    )->fetchAll();
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 2 — PROVIDER MANAGEMENT (Approve / Reject)
// └─────────────────────────────────────────────────────────┘

/**
 * Fetch all providers with optional status filter.
 *
 * @param  PDO    $conn
 * @param  string $statusFilter  'pending'|'approved'|'rejected'|'' (all)
 * @return array
 */
function get_all_providers(PDO $conn, string $statusFilter = ''): array
{
    $sql  = "SELECT u.user_id, u.full_name, u.email,
                    p.provider_id, p.organisation_name, p.registration_no,
                    p.phone, p.status, p.rejection_reason, p.created_at, p.document_path,
                    COUNT(c.course_id) AS course_count
             FROM providers p
             JOIN users u ON u.user_id = p.user_id
             LEFT JOIN courses c ON c.provider_id = p.provider_id
             WHERE 1=1";
    $args = [];
    if (in_array($statusFilter, ['pending','approved','rejected'], true)) {
        $sql  .= ' AND p.status = ?';
        $args[] = $statusFilter;
    }
    $sql .= ' GROUP BY p.provider_id ORDER BY p.created_at DESC';

    $stmt = $conn->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}


/**
 * Approve a training provider.
 *
 * @param  PDO $conn
 * @param  int $providerId
 * @return bool  true on success
 */
function approve_provider(PDO $conn, int $providerId): bool
{
    // Fetch provider/user info for email
    $stmtUser = $conn->prepare("SELECT u.email, u.full_name FROM providers p JOIN users u ON u.user_id = p.user_id WHERE p.provider_id = ?");
    $stmtUser->execute([$providerId]);
    $user = $stmtUser->fetch();

    $stmt = $conn->prepare(
        "UPDATE providers
         SET status = 'approved', rejection_reason = NULL, reviewed_at = NOW()
         WHERE provider_id = ?"
    );
    $stmt->execute([$providerId]);

    if ($stmt->rowCount() > 0 && $user) {
        require_once __DIR__ . '/mail_functions.php';
        send_approval_notification($user['email'], $user['full_name']);
        return true;
    }
    return false;
}


/**
 * Reject a training provider with an optional reason.
 *
 * @param  PDO    $conn
 * @param  int    $providerId
 * @param  string $reason
 * @return bool
 */
function reject_provider(PDO $conn, int $providerId, string $reason = ''): bool
{
    // Fetch provider/user info for email
    $stmtUser = $conn->prepare("SELECT u.email, u.full_name FROM providers p JOIN users u ON u.user_id = p.user_id WHERE p.provider_id = ?");
    $stmtUser->execute([$providerId]);
    $user = $stmtUser->fetch();

    $stmt = $conn->prepare(
        "UPDATE providers
         SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW()
         WHERE provider_id = ?"
    );
    $stmt->execute([($reason ?: null), $providerId]);

    if ($stmt->rowCount() > 0 && $user) {
        require_once __DIR__ . '/mail_functions.php';
        send_rejection_notification($user['email'], $user['full_name'], $reason);
        return true;
    }
    return false;
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 3 — SYSTEM-WIDE ANALYTICS
// └─────────────────────────────────────────────────────────┘

/**
 * Monthly enrolment count and revenue for a given year.
 *
 * @param  PDO $conn
 * @param  int $year  Defaults to current year.
 * @return array  12 rows, one per month, even if no data.
 */
function get_monthly_analytics(PDO $conn, int $year = 0): array
{
    if (!$year) $year = (int) date('Y');

    // Build a 12-month skeleton so months with no data still appear
    $skeleton = [];
    for ($m = 1; $m <= 12; $m++) {
        $skeleton[$m] = ['month' => $m, 'enrolments' => 0, 'revenue' => 0.0];
    }

    $stmt = $conn->prepare(
        "SELECT MONTH(e.enrolment_date)         AS month,
                COUNT(e.enrolment_id)            AS enrolments,
                COALESCE(SUM(p.amount), 0)       AS revenue
         FROM enrolments e
         LEFT JOIN payments p
               ON p.enrolment_id = e.enrolment_id AND p.payment_status = 'success'
         WHERE YEAR(e.enrolment_date) = ?
         GROUP BY MONTH(e.enrolment_date)
         ORDER BY month"
    );
    $stmt->execute([$year]);
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
 * Yearly summary of enrolments and revenue (all years in DB).
 *
 * @param  PDO $conn
 * @return array
 */
function get_yearly_analytics(PDO $conn): array
{
    return $conn->query(
        "SELECT YEAR(e.enrolment_date)           AS year,
                COUNT(e.enrolment_id)            AS enrolments,
                COALESCE(SUM(p.amount), 0)       AS revenue
         FROM enrolments e
         LEFT JOIN payments p
               ON p.enrolment_id = e.enrolment_id AND p.payment_status = 'success'
         GROUP BY YEAR(e.enrolment_date)
         ORDER BY year DESC"
    )->fetchAll();
}


/**
 * Top N courses by total enrolment count (system-wide).
 *
 * @param  PDO $conn
 * @param  int $limit
 * @return array
 */
function get_top_courses(PDO $conn, int $limit = 10): array
{
    $stmt = $conn->prepare(
        "SELECT c.course_id, c.title,
                pr.organisation_name,
                COUNT(e.enrolment_id)       AS enrolment_count,
                COALESCE(SUM(p.amount), 0)  AS revenue
         FROM courses c
         JOIN providers pr ON pr.provider_id = c.provider_id
         LEFT JOIN enrolments e ON e.course_id = c.course_id
         LEFT JOIN payments p
               ON p.enrolment_id = e.enrolment_id AND p.payment_status = 'success'
         GROUP BY c.course_id
         ORDER BY enrolment_count DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}


/**
 * Enrolment breakdown by course category.
 *
 * @param  PDO $conn
 * @return array  Each row: category_name, enrolment_count, revenue
 */
function get_category_breakdown(PDO $conn): array
{
    return $conn->query(
        "SELECT cc.name                          AS category_name,
                COUNT(e.enrolment_id)            AS enrolment_count,
                COALESCE(SUM(p.amount), 0)       AS revenue
         FROM course_categories cc
         LEFT JOIN courses c    ON c.category_id  = cc.category_id
         LEFT JOIN enrolments e ON e.course_id    = c.course_id
         LEFT JOIN payments p
               ON p.enrolment_id = e.enrolment_id AND p.payment_status = 'success'
         GROUP BY cc.category_id
         ORDER BY enrolment_count DESC"
    )->fetchAll();
}


/**
 * Available years with enrolment data (for analytics year filter dropdown).
 *
 * @param  PDO $conn
 * @return int[]
 */
function get_analytics_years(PDO $conn): array
{
    $rows = $conn->query(
        'SELECT DISTINCT YEAR(enrolment_date) AS y FROM enrolments ORDER BY y DESC'
    )->fetchAll(PDO::FETCH_COLUMN);

    // Always include current year even if no data yet
    $currentYear = (int) date('Y');
    if (!in_array($currentYear, $rows)) array_unshift($rows, $currentYear);
    return $rows;
}


/**
 * Permanently delete a training provider and all associated data.
 *
 * @param  PDO $conn
 * @param  int $providerId
 * @return bool
 */
function delete_provider(PDO $conn, int $providerId): bool
{
    try {
        // Find the user_id associated with this provider
        $stmt = $conn->prepare('SELECT user_id FROM providers WHERE provider_id = ?');
        $stmt->execute([$providerId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $userId = (int)$row['user_id'];

        // Delete the user record (cascades to providers, courses, enrolments, payments)
        $del = $conn->prepare('DELETE FROM users WHERE user_id = ?');
        $del->execute([$userId]);

        return $del->rowCount() > 0;
    } catch (Exception $e) {
        error_log('[EMS delete_provider] ' . $e->getMessage());
        return false;
    }
}
