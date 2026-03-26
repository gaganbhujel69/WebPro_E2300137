<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 Forbidden | EMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="d-flex flex-column align-items-center justify-content-center"
     style="min-height:100vh;background:var(--ems-light-bg);">
    <i class="bi bi-shield-exclamation text-danger" style="font-size:4rem;"></i>
    <h1 class="mt-3">403 — Access Denied</h1>
    <p class="text-muted">You do not have permission to view this page.</p>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary mt-2">Go Home</a>
</div>
</body>
</html>
