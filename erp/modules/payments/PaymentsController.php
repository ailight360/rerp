<?php
/**
 * Payments Controller
 * Record payments for stock_in, stock_out, bills
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Notify.php';

class PaymentsController {
    
    private $auth;
    private $db;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->db = getDB();
        
        if (!$this->auth->check()) {
            header('Location: /login.php');
            exit;
        }
    }
    
    public function index() {
        $user = $this->auth->user();
        $type = $_GET['type'] ?? '';
        $party_type = $_GET['party_type'] ?? '';
        $party_id = $_GET['party_id'] ?? '';
        $method = $_GET['method'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $where = ['1=1'];
        $params = [];
        
        if ($type) { $where[] = 'p.type = ?'; $params[] = $type; }
        if ($party_type) { $where[] = 'p.party_type = ?'; $params[] = $party_type; }
        if ($party_id) { $where[] = 'p.party_id = ?'; $params[] = $party_id; }
        if ($method) { $where[] = 'p.method = ?'; $params[] = $method; }
        if ($date_from) { $where[] = 'p.date >= ?'; $params[] = $date_from; }
        if ($date_to) { $where[] = 'p.date <= ?'; $params[] = $date_to; }
        
        $where_clause = implode(' AND ', $where);
        
        $count_stmt = $this->db->prepare("SELECT COUNT(*) FROM payments p WHERE $where_clause");
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();
        $total_pages = ceil($total / $per_page);
        
        $sql = "SELECT p.*, 
                CASE WHEN p.party_type = 'customer' THEN c.name 
                     WHEN p.party_type = 'vendor' THEN v.name 
                     ELSE 'Unknown' END as party_name,
                u.name as created_by_name
                FROM payments p
                LEFT JOIN customers c ON p.party_type = 'customer' AND p.party_id = c.id
                LEFT JOIN vendors v ON p.party_type = 'vendor' AND p.party_id = v.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE $where_clause
                ORDER BY p.created_at DESC LIMIT $per_page OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();
        
        require __DIR__ . '/views/list.php';
    }
    
    public function form($id = null) {
        $user = $this->auth->user();
        $payment = null;
        $edit_mode = false;
        
        if ($id) {
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$id]);
            $payment = $stmt->fetch();
            if (!$payment) die('Payment not found');
            $edit_mode = true;
        }
        
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll();
        $vendors = $this->db->query("SELECT id, name FROM vendors WHERE status = 1 ORDER BY name")->fetchAll();
        
        require __DIR__ . '/views/form.php';
    }
    
    public function save() {
        $user = $this->auth->user();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die('Invalid request method');
        if (!Helpers::verifyCsrfToken($_POST['csrf_token'] ?? '')) die('Invalid security token');
        
        $id = $_POST['id'] ?? null;
        $type = $_POST['type'] ?? 'received';
        $party_type = $_POST['party_type'] ?? '';
        $party_id = $_POST['party_id'] ?? null;
        $reference_type = $_POST['reference_type'] ?? 'manual';
        $reference_id = $_POST['reference_id'] ?? null;
        $amount = Helpers::toDecimal($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? 'cash';
        $date = $_POST['date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$party_type || !$party_id) {
            echo json_encode(['success' => false, 'message' => 'Please select a customer or vendor']);
            return;
        }
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero']);
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            if ($id) {
                $stmt = $this->db->prepare("UPDATE payments SET type=?, party_type=?, party_id=?, reference_type=?, reference_id=?, amount=?, method=?, date=?, notes=? WHERE id=?");
                $stmt->execute([$type, $party_type, $party_id, $reference_type, $reference_id, $amount, $method, $date, $notes, $id]);
                AuditLog::log('update', 'payments', $id, null, compact('type','party_type','party_id','reference_type','reference_id','amount','method','date','notes'));
                $payment_id = $id;
                $message = 'Payment updated successfully';
            } else {
                $payment_no = Helpers::generateNumber('PAY');
                $stmt = $this->db->prepare("INSERT INTO payments (payment_no,type,party_type,party_id,reference_type,reference_id,amount,method,date,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$payment_no,$type,$party_type,$party_id,$reference_type,$reference_id,$amount,$method,$date,$notes,$user['id']]);
                $payment_id = $this->db->lastInsertId();
                AuditLog::log('create', 'payments', $payment_id, null, compact('payment_no','type','party_type','party_id','reference_type','reference_id','amount','method','date','notes'));
                $message = 'Payment recorded successfully';
            }
            
            if ($reference_id && $reference_type !== 'manual') {
                $this->updateInvoicePayment($reference_type, $reference_id);
            }
            
            $this->createLedgerEntry($payment_id, $type, $party_type, $party_id, $amount, $date, $reference_type, $reference_id);
            
            $this->db->commit();
            echo json_encode(['success' => true, 'message' => $message, 'payment_id' => $payment_id]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    
    private function updateInvoicePayment($reference_type, $reference_id) {
        $table = '';
        switch ($reference_type) {
            case 'stock_in': $table = 'stock_in'; break;
            case 'stock_out': $table = 'stock_out'; break;
            case 'bill': $table = 'bills'; break;
            default: return;
        }
        
        $stmt = $this->db->prepare("SELECT SUM(CASE WHEN type='received' THEN amount ELSE -amount END) as total_paid FROM payments WHERE reference_type=? AND reference_id=?");
        $stmt->execute([$reference_type, $reference_id]);
        $result = $stmt->fetch();
        $total_paid = $result['total_paid'] ?? 0;
        
        $stmt = $this->db->prepare("SELECT total FROM $table WHERE id=?");
        $stmt->execute([$reference_id]);
        $invoice = $stmt->fetch();
        if (!$invoice) return;
        
        $invoice_total = $invoice['total'] ?? 0;
        $due = max(0, $invoice_total - $total_paid);
        $is_locked = ($due <= 0) ? 1 : 0;
        
        $stmt = $this->db->prepare("UPDATE $table SET paid=?, due=?, is_locked=? WHERE id=?");
        $stmt->execute([$total_paid, $due, $is_locked, $reference_id]);
        
        if ($table === 'bills') {
            $status = $due <= 0 ? 'paid' : ($total_paid > 0 ? 'partial' : 'unpaid');
            $stmt = $this->db->prepare("UPDATE bills SET status=? WHERE id=?");
            $stmt->execute([$status, $reference_id]);
        }
    }
    
    private function createLedgerEntry($payment_id, $type, $party_type, $party_id, $amount, $date, $reference_type, $reference_id) {
        $stmt = $this->db->prepare("SELECT payment_no FROM payments WHERE id=?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        $description = "Payment " . ($type === 'received' ? 'received' : 'paid') . " (" . $payment['payment_no'] . ")";
        
        if ($type === 'received') {
            $debit = 0; $credit = $amount;
        } else {
            $debit = $amount; $credit = 0;
        }
        
        $stmt = $this->db->prepare("INSERT INTO ledger_entries (party_type,party_id,date,description,debit,credit,reference_type,reference_id) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$party_type, $party_id, $date, $description, $debit, $credit, 'payment', $payment_id]);
    }
    
    public function delete($id) {
        $user = $this->auth->user();
        if (!$this->auth->isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can delete payments']);
            return;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE id=?");
            $stmt->execute([$id]);
            $payment = $stmt->fetch();
            if (!$payment) {
                echo json_encode(['success' => false, 'message' => 'Payment not found']);
                return;
            }
            
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM ledger_entries WHERE reference_type='payment' AND reference_id=?");
            $stmt->execute([$id]);
            $stmt = $this->db->prepare("DELETE FROM payments WHERE id=?");
            $stmt->execute([$id]);
            
            if ($payment['reference_id'] && $payment['reference_type'] !== 'manual') {
                $this->updateInvoicePayment($payment['reference_type'], $payment['reference_id']);
            }
            
            AuditLog::log('delete', 'payments', $id, ['payment_no'=>$payment['payment_no'],'amount'=>$payment['amount']], null);
            $this->db->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    
    public function getOutstandingInvoices() {
        header('Content-Type: application/json');
        $party_type = $_GET['party_type'] ?? '';
        $party_id = $_GET['party_id'] ?? 0;
        
        if (!$party_type || !$party_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            return;
        }
        
        if ($party_type === 'customer') {
            $stmt = $this->db->prepare("SELECT 'stock_out' as type, id, invoice_no as ref_no, date, total, paid, due FROM stock_out WHERE customer_id=? AND due>0 AND status!='cancelled' UNION ALL SELECT 'bill' as type, id, bill_no as ref_no, date, total, paid, due FROM bills WHERE customer_id=? AND due>0 AND status IN ('unpaid','partial') ORDER BY date ASC");
            $stmt->execute([$party_id, $party_id]);
        } elseif ($party_type === 'vendor') {
            $stmt = $this->db->prepare("SELECT 'stock_in' as type, id, invoice_no as ref_no, date, total, paid, due FROM stock_in WHERE vendor_id=? AND due>0 AND status!='cancelled' ORDER BY date ASC");
            $stmt->execute([$party_id]);
        }
        
        $invoices = $stmt->fetchAll();
        echo json_encode(['success' => true, 'invoices' => $invoices]);
    }
}
