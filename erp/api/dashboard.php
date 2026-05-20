<?php
/**
 * Dashboard API - Stats & Charts
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

// Get chart type from query
$chart = $_GET['chart'] ?? 'sales';

if ($chart === 'sales') {
    // Last 6 months sales vs purchases
    $labels = [];
    $salesData = [];
    $purchasesData = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $labels[] = date('M Y', strtotime("-$i months"));
        
        // Sales
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM stock_out 
                               WHERE DATE_FORMAT(date, '%Y-%m') = ? 
                               AND status != 'cancelled'");
        $stmt->execute([$month]);
        $salesData[] = floatval($stmt->fetchColumn());
        
        // Purchases
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM stock_in 
                               WHERE DATE_FORMAT(date, '%Y-%m') = ? 
                               AND status != 'cancelled'");
        $stmt->execute([$month]);
        $purchasesData[] = floatval($stmt->fetchColumn());
    }
    
    echo json_encode([
        'labels' => $labels,
        'sales' => $salesData,
        'purchases' => $purchasesData
    ]);
} else {
    echo json_encode(['error' => 'Invalid chart type']);
}
