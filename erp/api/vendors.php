<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    Response::json(['error' => 'Unauthorized'], 401);
}

$db = DB::getInstance();
$action = $_GET['action'] ?? 'search';

if ($action === 'search') {
    $term = $_GET['q'] ?? '';
    
    $stmt = $db->prepare("
        SELECT id, name, phone, email, address 
        FROM vendors 
        WHERE status = 1 AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)
        ORDER BY name ASC
        LIMIT 20
    ");
    $searchTerm = "%{$term}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $vendors = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    Response::json($vendors);
} elseif ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ? AND status = 1");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch(PDO::FETCH_OBJ);
    
    if ($vendor) {
        Response::json($vendor);
    } else {
        Response::json(['error' => 'Vendor not found'], 404);
    }
}
