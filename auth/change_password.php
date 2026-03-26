<?php
// ============================================================
//  EMS — Forced Password Change
//  auth/change_password.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../config/connection.php';

require_login();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $userId = current_user_id();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, is_first_login = 0 WHERE user_id = ?");
        if ($stmt->execute([$hash, $userId])) {
            $_SESSION['is_first_login'] = false;
            $success = 'Password updated successfully! Redirecting...';
            
            // Redirect based on role
            $role = current_role();
            $redirectMap = [
                'ministry_officer'  => BASE_URL . '/admin/dashboard.php',
                'training_provider' => BASE_URL . '/provider/dashboard.php',
                'learner'           => BASE_URL . '/learner/dashboard.php',
            ];
            $redirect = $redirectMap[$role] ?? BASE_URL . '/index.php';
            header("Refresh: 2; URL=$redirect");
        } else {
            $errors[] = 'Failed to update password. Please try again.';
        }
    }
}

$pageTitle = 'Change Password';
// We don't include the standard header here to prevent loops if we missed something, 
// using a minimal layout instead.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password | EMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="ems-card p-4">
                    <h2 class="text-center mb-4">Change Your Password</h2>
                    <p class="text-muted text-center mb-4">This is your first login. For security, please choose a new password before continuing.</p>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php else: ?>
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Update Password</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
