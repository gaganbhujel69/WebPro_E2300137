<?php
// ============================================================
//  EMS — Authentication & Session Helpers
//  includes/auth.php
// ============================================================

// Start a session if one hasn't been started yet.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Environment Configuration ────────────────────────────────
define('BASE_URL', '/booking_site');

// ── Helpers ─────────────────────────────────────────────────

/**
 * Check whether a user is currently logged in.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Return the role of the currently logged-in user, or null.
 */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Return the user_id of the currently logged-in user, or null.
 */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// ── Role-Guard Functions ─────────────────────────────────────

/**
 * Redirect to login if the visitor is not authenticated.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Require the logged-in user to have a specific role.
 * Redirects to login (or a 403 page) on failure.
 */
function require_role(string $role): void {
    require_login();
    if (current_role() !== $role) {
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
        exit;
    }
    enforce_password_change();
}

/**
 * Redirect to password change page if it's the user's first login.
 */
function enforce_password_change(): void {
    if (($_SESSION['is_first_login'] ?? false) && basename($_SERVER['PHP_SELF']) !== 'change_password.php' && basename($_SERVER['PHP_SELF']) !== 'logout.php') {
        header('Location: ' . BASE_URL . '/auth/change_password.php');
        exit;
    }
}

/**
 * Require the logged-in user to be an approved training provider.
 */
function require_approved_provider(): void {
    require_role('training_provider');
    if (($_SESSION['provider_status'] ?? '') !== 'approved') {
        header('Location: ' . BASE_URL . '/provider/pending.php');
        exit;
    }
}

// ── CSRF Helpers ─────────────────────────────────────────────

/**
 * Generate (or retrieve) the CSRF token for this session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify the submitted CSRF token. Die on failure.
 */
function verify_csrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}

// ── Session Population ───────────────────────────────────────

/**
 * Populate $_SESSION after a successful login query.
 *
 * @param array $user  Associative row from the `users` table.
 * @param string|null $providerStatus  'approved' | 'pending' | 'rejected' | null.
 */
function populate_session(array $user, ?string $providerStatus = null): void {
    session_regenerate_id(true);  // prevent session fixation
    $_SESSION['user_id']         = $user['user_id'];
    $_SESSION['full_name']       = $user['full_name'];
    $_SESSION['email']           = $user['email'];
    $_SESSION['role']            = $user['role'];
    $_SESSION['is_first_login']  = (bool)$user['is_first_login'];
    if ($providerStatus !== null) {
        $_SESSION['provider_status'] = $providerStatus;
    }
}

/**
 * Destroy the session fully (used on logout).
 */
function destroy_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
