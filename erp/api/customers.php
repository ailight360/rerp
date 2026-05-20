<?php
/**
 * Customers API - Search
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pdo = Database::getInstance();
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$where = ['status = 1'];
$params = [];

if ($query) {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $searchParam = "%$query%";
    $params = [$searchParam, $searchParam, $searchParam];
}

$sql = "SELECT id, name, phone, email, address, opening_balance, balance_type 
        FROM customers 
        WHERE " . implode(' AND ', $where) . "
        ORDER BY name
        LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($customers);
