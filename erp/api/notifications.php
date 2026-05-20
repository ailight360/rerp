<?php
/**
 * Notifications API - Get unread count and list
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];
header('Content-Type: application/json');

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications 
                       WHERE user_id IS NULL OR user_id = ?");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();

// Get recent notifications (last 10)
$stmt = $pdo->prepare("SELECT * FROM notifications 
                       WHERE user_id IS NULL OR user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 10");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'unread_count' => intval($unreadCount),
    'notifications' => $notifications
]);
