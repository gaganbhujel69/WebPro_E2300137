<?php
// ============================================================
//  EMS — Logout
//  auth/logout.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
destroy_session();
header('Location: ' . BASE_URL . '/auth/login.php?msg=logged_out');
exit;
