<?php
/**
 * Payments API Endpoint
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();

if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'outstanding') {
    $party_type = $_GET['party_type'] ?? '';
    $party_id = $_GET['party_id'] ?? 0;
    
    if (!$party_type || !$party_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $db = getDB();
    
    if ($party_type === 'customer') {
        $stmt = $db->prepare("SELECT 'stock_out' as type, id, invoice_no as ref_no, date, total, paid, due, 'Sale Invoice' as description FROM stock_out WHERE customer_id=? AND due>0 AND status!='cancelled' UNION ALL SELECT 'bill' as type, id, bill_no as ref_no, date, total, paid, due, 'Tax Invoice' as description FROM bills WHERE customer_id=? AND due>0 AND status IN ('unpaid','partial') ORDER BY date ASC");
        $stmt->execute([$party_id, $party_id]);
    } elseif ($party_type === 'vendor') {
        $stmt = $db->prepare("SELECT 'stock_in' as type, id, invoice_no as ref_no, date, total, paid, due, 'Purchase Invoice' as description FROM stock_in WHERE vendor_id=? AND due>0 AND status!='cancelled' ORDER BY date ASC");
        $stmt->execute([$party_id]);
    }
    
    $invoices = $stmt->fetchAll();
    echo json_encode(['success' => true, 'invoices' => $invoices]);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
