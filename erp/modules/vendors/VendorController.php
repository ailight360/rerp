<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/AuditLog.php';
require_once __DIR__ . '/../../core/Helpers.php';

class VendorController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->auth = new Auth();
    }

    public function list() {
        $this->auth->requireLogin();
        
        $stmt = $this->db->query("
            SELECT v.*, 
                   COALESCE(SUM(CASE WHEN le.debit > 0 THEN le.debit ELSE 0 END), 0) as total_debit,
                   COALESCE(SUM(CASE WHEN le.credit > 0 THEN le.credit ELSE 0 END), 0) as total_credit
            FROM vendors v
            LEFT JOIN ledger_entries le ON le.party_type = 'vendor' AND le.party_id = v.id
            WHERE v.status = 1
            GROUP BY v.id
            ORDER BY v.name ASC
        ");
        $vendors = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        // Calculate current balance for each vendor
        foreach ($vendors as $v) {
            $v->current_balance = $v->opening_balance + ($v->total_debit - $v->total_credit);
            if ($v->balance_type === 'credit') {
                $v->current_balance = -$v->current_balance;
            }
        }
        
        include __DIR__ . '/views/list.php';
    }

    public function create() {
        $this->auth->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->db->beginTransaction();
                
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'opening_balance' => floatval($_POST['opening_balance'] ?? 0),
                    'balance_type' => $_POST['balance_type'] ?? 'debit',
                    'status' => 1
                ];
                
                if (empty($data['name'])) {
                    throw new Exception('Vendor name is required');
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO vendors (name, phone, email, address, opening_balance, balance_type, status, created_at)
                    VALUES (:name, :phone, :email, :address, :opening_balance, :balance_type, :status, NOW())
                ");
                $stmt->execute($data);
                $vendorId = $this->db->lastInsertId();
                
                // Create opening ledger entry if balance exists
                if ($data['opening_balance'] != 0) {
                    $ledgerDebit = $data['balance_type'] === 'debit' ? $data['opening_balance'] : 0;
                    $ledgerCredit = $data['balance_type'] === 'credit' ? $data['opening_balance'] : 0;
                    
                    $this->db->prepare("
                        INSERT INTO ledger_entries (party_type, party_id, date, description, debit, credit, reference_type, reference_id, created_at)
                        VALUES ('vendor', :vendor_id, CURDATE(), 'Opening Balance', :debit, :credit, 'opening', :record_id, NOW())
                    ")->execute([
                        'vendor_id' => $vendorId,
                        'debit' => $ledgerDebit,
                        'credit' => $ledgerCredit,
                        'record_id' => $vendorId
                    ]);
                }
                
                $this->db->commit();
                
                AuditLog::log('create', 'vendors', $vendorId, null, $data);
                
                Response::json(['success' => true, 'message' => 'Vendor created successfully']);
            } catch (Exception $e) {
                $this->db->rollBack();
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
            }
        }
        
        include __DIR__ . '/views/form.php';
    }

    public function edit($id) {
        $this->auth->requireLogin();
        
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        $vendor = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$vendor) {
            Response::redirect('/vendors', ['error' => 'Vendor not found']);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $oldData = (array)$vendor;
                
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'status' => intval($_POST['status'] ?? 1)
                ];
                
                if (empty($data['name'])) {
                    throw new Exception('Vendor name is required');
                }
                
                $stmt = $this->db->prepare("
                    UPDATE vendors 
                    SET name = :name, phone = :phone, email = :email, address = :address, status = :status
                    WHERE id = :id
                ");
                $stmt->execute(array_merge($data, ['id' => $id]));
                
                AuditLog::log('update', 'vendors', $id, $oldData, $data);
                
                Response::json(['success' => true, 'message' => 'Vendor updated successfully']);
            } catch (Exception $e) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
            }
        }
        
        include __DIR__ . '/views/form.php';
    }

    public function delete($id) {
        $this->auth->requireLogin();
        
        try {
            // Check if vendor has transactions
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM stock_in WHERE vendor_id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            
            if ($result->count > 0) {
                throw new Exception('Cannot delete vendor with existing transactions. Set status to inactive instead.');
            }
            
            $stmt = $this->db->prepare("UPDATE vendors SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            AuditLog::log('delete', 'vendors', $id, ['status' => 1], ['status' => 0]);
            
            Response::json(['success' => true, 'message' => 'Vendor deactivated successfully']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function ledger($id) {
        $this->auth->requireLogin();
        
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        $vendor = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$vendor) {
            Response::redirect('/vendors', ['error' => 'Vendor not found']);
        }
        
        $filter = $_GET['filter'] ?? 'all';
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $sql = "SELECT * FROM ledger_entries WHERE party_type = 'vendor' AND party_id = ?";
        $params = [$id];
        
        if ($filter === 'dated' && !empty($startDate) && !empty($endDate)) {
            $sql .= " AND date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY date DESC, id DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        // Calculate running balance
        $runningBalance = $vendor->opening_balance;
        if ($vendor->balance_type === 'credit') {
            $runningBalance = -$runningBalance;
        }
        
        foreach ($entries as $entry) {
            $runningBalance += ($entry->debit - $entry->credit);
            $entry->running_balance = $runningBalance;
        }
        
        include __DIR__ . '/views/ledger.php';
    }

    public function statement($id) {
        $this->auth->requireLogin();
        
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        $vendor = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$vendor) {
            die('Vendor not found');
        }
        
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT * FROM ledger_entries 
            WHERE party_type = 'vendor' AND party_id = ? AND date BETWEEN ? AND ?
            ORDER BY date ASC, id ASC
        ");
        $stmt->execute([$id, $startDate, $endDate]);
        $entries = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/statement.php';
    }
}
