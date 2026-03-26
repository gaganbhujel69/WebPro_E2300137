<?php
// ============================================================
//  EMS — Authentication Business Logic
//  includes/auth_functions.php
//
//  Requires:
//    - config/connection.php  → $conn  (PDO)
//    - includes/auth.php      → session helpers
// ============================================================


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 1 — INPUT VALIDATION
// └─────────────────────────────────────────────────────────┘

/**
 * Validate registration form data.
 *
 * @param  array  $data  Associative array of raw POST values.
 * @return array         List of human-readable error strings (empty = valid).
 */
function validate_registration(array $data): array
{
    $errors = [];

    // ── Role ──────────────────────────────────────────────
    $allowedRoles = ['learner', 'training_provider'];
    if (empty($data['role']) || !in_array($data['role'], $allowedRoles, true)) {
        $errors[] = 'Please select a valid role (Learner or Training Provider).';
    }

    // ── Full name ─────────────────────────────────────────
    $name = trim($data['full_name'] ?? '');
    if ($name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Full name must not exceed 150 characters.';
    }

    // ── Email ─────────────────────────────────────────────
    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 255) {
        $errors[] = 'Email address is too long.';
    }

    // ── Password ──────────────────────────────────────────
    $password = $data['password'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== ($data['password_confirm'] ?? '')) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Provider-specific fields ──────────────────────────
    if (($data['role'] ?? '') === 'training_provider') {
        if (trim($data['organisation_name'] ?? '') === '') {
            $errors[] = 'Organisation name is required.';
        }
        if (trim($data['registration_no'] ?? '') === '') {
            $errors[] = 'Company/organisation registration number is required.';
        }
        if (trim($data['address'] ?? '') === '') {
            $errors[] = 'Organisation address is required.';
        }
        if (trim($data['phone'] ?? '') === '') {
            $errors[] = 'Phone number is required.';
        }

        // Optional website — validate format only if provided
        $website = trim($data['website'] ?? '');
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Website must be a valid URL (e.g. https://example.com).';
        }
    }

    return $errors;
}


/**
 * Validate login form data (lightweight — full auth done in authenticate_user).
 *
 * @param  string $email
 * @param  string $password
 * @return array  Error strings (empty = OK to proceed).
 */
function validate_login(string $email, string $password): array
{
    $errors = [];
    if (empty(trim($email))) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }
    return $errors;
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 2 — REGISTRATION
// └─────────────────────────────────────────────────────────┘

/**
 * Register a new user (learner or training_provider).
 *
 * Uses a DB transaction: inserts into `users`, and optionally `providers`.
 * Password is hashed with bcrypt (cost 12) before storage.
 *
 * @param  PDO   $conn   Active PDO connection.
 * @param  array $data   Validated POST data.
 * @return array{
 *   success: bool,
 *   user_id: int|null,
 *   message: string
 * }
 */
function register_user(PDO $conn, array $data): array
{
    $role     = $data['role'];
    $fullName = trim($data['full_name']);
    $email    = strtolower(trim($data['email']));
    $password = $data['password'];

    // ── Step 1: Check duplicate email ─────────────────────
    $dup = $conn->prepare(
        'SELECT user_id FROM users WHERE email = ? LIMIT 1'
    );
    $dup->execute([$email]);
    if ($dup->fetch()) {
        return [
            'success' => false,
            'user_id' => null,
            'message' => 'An account with this email address already exists.',
        ];
    }

    // ── Step 2: Hash password (bcrypt, cost 12) ───────────
    //    password_hash() automatically generates a unique salt.
    //    Never store plain-text or MD5/SHA1 passwords.
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // ── Step 3: Insert inside a transaction ───────────────
    $conn->beginTransaction();
    try {
        // Insert into `users`
        $insUser = $conn->prepare(
            'INSERT INTO users (full_name, email, password_hash, role, is_first_login, is_active)
             VALUES (:name, :email, :hash, :role, 1, 1)'
        );
        $insUser->execute([
            ':name'  => $fullName,
            ':email' => $email,
            ':hash'  => $passwordHash,
            ':role'  => $role,
        ]);
        $newUserId = (int) $conn->lastInsertId();

        // If provider, insert extended profile into `providers`
        if ($role === 'training_provider') {
            $website = trim($data['website'] ?? '');
            
            // Handle File Upload
            $docPath = null;
            if (isset($_FILES['supporting_doc']) && $_FILES['supporting_doc']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/registrations/';
                $fileExt   = strtolower(pathinfo($_FILES['supporting_doc']['name'], PATHINFO_EXTENSION));
                $fileName  = 'provider_' . $newUserId . '_' . time() . '.' . $fileExt;
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $targetFile)) {
                    $docPath = 'uploads/registrations/' . $fileName;
                }
            }

            $insProv = $conn->prepare(
                'INSERT INTO providers
                 (user_id, organisation_name, registration_no, address, phone, website, document_path, status)
                 VALUES (:uid, :org, :reg, :addr, :phone, :web, :doc, "pending")'
            );
            $insProv->execute([
                ':uid'   => $newUserId,
                ':org'   => trim($data['organisation_name']),
                ':reg'   => trim($data['registration_no']),
                ':addr'  => trim($data['address']),
                ':phone' => trim($data['phone']),
                ':web'   => $website !== '' ? $website : null,
                ':doc'   => $docPath,
            ]);
        }

        $conn->commit();

        $message = ($role === 'training_provider')
            ? 'Your provider account has been submitted and is awaiting Ministry approval.'
            : 'Account created successfully! You can now log in.';

        return [
            'success' => true,
            'user_id' => $newUserId,
            'message' => $message,
        ];

    } catch (Exception $e) {
        $conn->rollBack();
        error_log('[EMS register_user] ' . $e->getMessage());
        return [
            'success' => false,
            'user_id' => null,
            'message' => 'Registration failed due to a server error. Please try again.',
        ];
    }
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 3 — LOGIN / AUTHENTICATION
// └─────────────────────────────────────────────────────────┘

/**
 * Authenticate a user by email + password.
 *
 * Uses password_verify() against the stored bcrypt hash.
 * On success, calls populate_session() and returns the redirect URL.
 *
 * @param  PDO    $conn
 * @param  string $email      Raw email from form.
 * @param  string $password   Raw password from form.
 * @return array{
 *   success: bool,
 *   redirect: string|null,
 *   message: string
 * }
 */
function authenticate_user(PDO $conn, string $email, string $password): array
{
    $email = strtolower(trim($email));

    // ── Fetch user record ──────────────────────────────────
    // We fetch by email only; password verification done in PHP (not SQL).
    // This prevents timing attacks from short-circuiting on wrong email.
    $stmt = $conn->prepare(
        'SELECT user_id, full_name, email, password_hash, role, is_active, is_first_login
         FROM users
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // ── Verify password ────────────────────────────────────
    // password_verify() is timing-attack resistant by design.
    // Always run it even if no user found (to prevent user enumeration).
    $dummyHash = '$2y$12$invalidhashtopreventtimingattack00000000000000000000000';
    $hash      = $user ? $user['password_hash'] : $dummyHash;

    if (!password_verify($password, $hash) || !$user) {
        return [
            'success'  => false,
            'redirect' => null,
            'message'  => 'Invalid email or password. Please try again.',
        ];
    }

    // ── Check active status ────────────────────────────────
    if (!(bool) $user['is_active']) {
        return [
            'success'  => false,
            'redirect' => null,
            'message'  => 'Your account has been deactivated. Please contact support.',
        ];
    }

    // ── Fetch provider status (if applicable) ─────────────
    $providerStatus = null;
    if ($user['role'] === 'training_provider') {
        $ps = $conn->prepare(
            'SELECT status FROM providers WHERE user_id = ? LIMIT 1'
        );
        $ps->execute([$user['user_id']]);
        $row = $ps->fetch();
        $providerStatus = $row['status'] ?? 'pending';
    }

    // ── Upgrade hash if needed (future-proofing) ──────────
    // If bcrypt cost or algorithm has changed since last login, rehash silently.
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
             ->execute([$newHash, $user['user_id']]);
    }

    // ── Populate session ───────────────────────────────────
    // session_regenerate_id(true) is called inside populate_session()
    // to prevent session fixation attacks.
    populate_session($user, $providerStatus);

    // ── Role-based redirect URL ────────────────────────────
    $redirectMap = [
        'ministry_officer'  => BASE_URL . '/admin/dashboard.php',
        'training_provider' => BASE_URL . '/provider/dashboard.php',
        'learner'           => BASE_URL . '/learner/dashboard.php',
    ];
    $redirect = $redirectMap[$user['role']] ?? '/index.php';

    return [
        'success'  => true,
        'redirect' => $redirect,
        'message'  => 'Login successful.',
    ];
}


// ┌─────────────────────────────────────────────────────────┐
//  SECTION 4 — UTILITY HELPERS
// └─────────────────────────────────────────────────────────┘

/**
 * Sanitise and return a single POST value as a string.
 *
 * @param  string $key      Key in $_POST.
 * @param  string $default  Fallback if key missing.
 */
function post_str(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

/**
 * Safely echo HTML-encoded output (prevents XSS).
 *
 * @param  string $value
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Return old POST value for re-population after validation failure.
 * Strips sensitive keys (passwords) automatically.
 *
 * @param  string $key
 * @param  string $default
 */
function old(string $key, string $default = ''): string
{
    $sensitive = ['password', 'password_confirm'];
    if (in_array($key, $sensitive, true)) return $default;
    return e(post_str($key, $default));
}
