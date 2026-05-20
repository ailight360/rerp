<?php
/**
 * Customers Controller - List & CRUD
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/AuditLog.php';

$pdo = Database::getInstance();
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'list';
$successMessage = '';
$errorMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Helpers::verifyCsrfToken($csrf)) {
        $errorMessage = 'Invalid security token. Please try again.';
    } else {
        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $openingBalance = floatval($_POST['opening_balance'] ?? 0);
                $balanceType = $_POST['balance_type'] ?? 'debit';
                
                if (empty($name)) {
                    $errorMessage = 'Customer name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, opening_balance, balance_type, created_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$name, $phone, $email, $address, $openingBalance, $balanceType]);
                        $customerId = $pdo->lastInsertId();
                        
                        // Create opening ledger entry if balance > 0
                        if ($openingBalance > 0) {
                            $stmt = $pdo->prepare("INSERT INTO ledger_entries (party_type, party_id, date, description, debit, credit, reference_type, reference_id) 
                                                  VALUES ('customer', ?, CURDATE(), 'Opening Balance', ?, 0, 'opening', NULL)");
                            if ($balanceType === 'credit') {
                                $stmt = $pdo->prepare("INSERT INTO ledger_entries (party_type, party_id, date, description, debit, credit, reference_type, reference_id) 
                                                      VALUES ('customer', ?, CURDATE(), 'Opening Balance', 0, ?, 'opening', NULL)");
                            }
                            $stmt->execute([$customerId, $openingBalance]);
                        }
                        
                        // Audit log
                        $audit = new AuditLog();
                        $audit->log($userId, 'create', 'customers', $customerId, null, ['name' => $name], Helpers::getClientIP());
                        
                        $successMessage = 'Customer created successfully!';
                        $action = 'list';
                    } catch (Exception $e) {
                        $errorMessage = 'Error creating customer: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $address = trim($_POST['address'] ?? '');
                
                if ($id <= 0 || empty($name)) {
                    $errorMessage = 'Invalid customer data.';
                } else {
                    try {
                        // Get old values
                        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                        $stmt->execute([$id]);
                        $oldValues = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                        $stmt->execute([$name, $phone, $email, $address, $id]);
                        
                        // Audit log
                        $audit = new AuditLog();
                        $audit->log($userId, 'update', 'customers', $id, $oldValues, ['name' => $name], Helpers::getClientIP());
                        
                        $successMessage = 'Customer updated successfully!';
                        $action = 'list';
                    } catch (Exception $e) {
                        $errorMessage = 'Error updating customer: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $errorMessage = 'Invalid customer ID.';
                } else {
                    try {
                        // Check if has transactions
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_out WHERE customer_id = ?");
                        $stmt->execute([$id]);
                        if ($stmt->fetchColumn() > 0) {
                            $errorMessage = 'Cannot delete customer with existing transactions. Set status to inactive instead.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE customers SET status = 0 WHERE id = ?");
                            $stmt->execute([$id]);
                            
                            $audit = new AuditLog();
                            $audit->log($userId, 'delete', 'customers', $id, null, null, Helpers::getClientIP());
                            
                            $successMessage = 'Customer deleted successfully!';
                        }
                        $action = 'list';
                    } catch (Exception $e) {
                        $errorMessage = 'Error deleting customer: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// List view
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    $where = 'WHERE c.status = 1';
    $params = [];
    
    if ($search) {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam];
    }
    
    $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS c.*, 
                                  COALESCE(SUM(b.due), 0) as total_due
                           FROM customers c
                           LEFT JOIN bills b ON c.id = b.customer_id AND b.status != 'paid' AND b.status != 'cancelled'
                           $where
                           GROUP BY c.id
                           ORDER BY c.created_at DESC
                           LIMIT $offset, $perPage");
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT FOUND_ROWS()");
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
    
    $pageTitle = 'Customers';
    $currentPage = 'customers';
    include __DIR__ . '/views/list.php';
    exit;
}

// Form view (create/edit)
if ($action === 'create' || $action === 'edit') {
    $customer = null;
    if ($action === 'edit') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            header('Location: /customers');
            exit;
        }
    }
    
    $pageTitle = $action === 'create' ? 'New Customer' : 'Edit Customer';
    $currentPage = 'customers';
    include __DIR__ . '/views/form.php';
    exit;
}

// Ledger view
if ($action === 'ledger') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header('Location: /customers');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM ledger_entries 
                           WHERE party_type = 'customer' AND party_id = ? 
                           ORDER BY date DESC, id DESC");
    $stmt->execute([$id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate running balance
    $balance = 0;
    foreach ($entries as &$entry) {
        if ($entry['debit'] > 0) {
            $balance += floatval($entry['debit']);
        } else {
            $balance -= floatval($entry['credit']);
        }
        $entry['running_balance'] = $balance;
    }
    
    $pageTitle = 'Customer Ledger - ' . $customer['name'];
    $currentPage = 'customers';
    include __DIR__ . '/views/ledger.php';
    exit;
}
