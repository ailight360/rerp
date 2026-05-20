<?php
/**
 * Logout Handler
 */
session_start();

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Log audit
    require_once __DIR__ . '/core/AuditLog.php';
    $audit = new AuditLog();
    $audit->log($userId, 'logout', 'users', $userId, null, null, Helpers::getClientIP());
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
