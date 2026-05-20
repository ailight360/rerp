<?php
/**
 * Billing Controller - Tax Invoices with Manual Price Entry
 * 
 * Features:
 * - Manual price entry per line item (NOT NULL enforced)
 * - Per-product tax rate override
 * - Generate from Stock Out (products/qty copied, prices empty)
 * - Recurring billing (weekly/monthly/quarterly auto-clone)
 * - WhatsApp share button
 * - Invoice locking after payment
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/AuditLog.php';
require_once __DIR__ . '/../../core/Notify.php';
require_once __DIR__ . '/../../core/Response.php';

class BillingController {
    private $db;
    private $auth;
    private $helpers;
    private $audit;
    private $notify;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->auth = new Auth();
        $this->helpers = new Helpers();
        $this->audit = new AuditLog();
        $this->notify = new Notify();
    }

    /**
     * Route handler
     */
    public function handle($action = 'list') {
        if (!$this->auth->isLoggedIn()) {
            Response::redirect('login.php');
        }

        switch ($action) {
            case 'list':
                return $this->listBills();
            case 'create':
                return $this->createForm();
            case 'store':
                return $this->store();
            case 'view':
                return $this->view($_GET['id'] ?? 0);
            case 'edit':
                return $this->editForm($_GET['id'] ?? 0);
            case 'update':
                return $this->update($_POST['id'] ?? 0);
            case 'delete':
                return $this->delete($_POST['id'] ?? 0);
            case 'print':
                return $this->print($_GET['id'] ?? 0);
            case 'createFromStockOut':
                return $this->createFromStockOut($_GET['stock_out_id'] ?? 0);
            case 'toggleRecurring':
                return $this->toggleRecurring($_POST['id'] ?? 0);
            default:
                return $this->listBills();
        }
    }

    /**
     * List all bills with filters
     */
    private function listBills() {
        $status = $_GET['status'] ?? '';
        $customer_id = $_GET['customer_id'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if ($status) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        if ($customer_id) {
            $where[] = 'b.customer_id = ?';
            $params[] = $customer_id;
        }
        if ($date_from) {
            $where[] = 'b.date >= ?';
            $params[] = $date_from;
        }
        if ($date_to) {
            $where[] = 'b.date <= ?';
            $params[] = $date_to;
        }

        $whereClause = implode(' AND ', $where);

        // Get bills
        $sql = "SELECT b.*, c.name as customer_name, c.phone, u.name as created_by_name
                FROM bills b
                LEFT JOIN customers c ON b.customer_id = c.id
                LEFT JOIN users u ON b.created_by = u.id
                WHERE $whereClause
                ORDER BY b.created_at DESC
                LIMIT $limit OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM bills b WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($total / $limit);

        // Get customers for filter
        $stmt = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/views/list.php';
    }

    /**
     * Show create form
     */
    private function createForm() {
        $customers = $this->getActiveCustomers();
        $taxRates = $this->getTaxRates();
        include __DIR__ . '/views/form.php';
    }

    /**
     * Create bill from Stock Out
     */
    private function createFromStockOut($stockOutId) {
        if (!$stockOutId) {
            Response::json(['success' => false, 'message' => 'Invalid stock out ID']);
            return;
        }

        // Get stock out details
        $stmt = $this->db->prepare("SELECT * FROM stock_out WHERE id = ?");
        $stmt->execute([$stockOutId]);
        $stockOut = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stockOut) {
            Response::json(['success' => false, 'message' => 'Stock out not found']);
            return;
        }

        // Get stock out items
        $stmt = $this->db->prepare("
            SELECT soi.*, p.name, p.code, p.product_type
            FROM stock_out_items soi
            JOIN products p ON soi.product_id = p.id
            WHERE soi.stock_out_id = ?
        ");
        $stmt->execute([$stockOutId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return as JSON for AJAX form population
        Response::json([
            'success' => true,
            'stock_out' => $stockOut,
            'items' => $items
        ]);
    }

    /**
     * Store new bill
     */
    private function store() {
        try {
            $this->db->beginTransaction();

            // Validate CSRF
            if (!$this->helpers->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $customer_id = (int)($_POST['customer_id'] ?? 0);
            $stock_out_id = !empty($_POST['stock_out_id']) ? (int)$_POST['stock_out_id'] : null;
            $date = $_POST['date'] ?? date('Y-m-d');
            $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
            $discount = (float)($_POST['discount'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'percent';
            $notes = $_POST['notes'] ?? '';
            $repeat_interval = $_POST['repeat_interval'] ?? 'none';
            $repeat_next_date = !empty($_POST['repeat_next_date']) ? $_POST['repeat_next_date'] : null;

            if (!$customer_id) {
                throw new Exception('Customer is required');
            }

            // Generate bill number
            $prefix = $this->helpers->getSetting('bill_prefix') ?: 'BILL';
            $billNo = $this->helpers->generateInvoiceNumber($prefix, 'bills', 'bill_no');

            // Calculate totals from items
            $items = $_POST['items'] ?? [];
            if (empty($items)) {
                throw new Exception('At least one item is required');
            }

            $subtotal = 0;
            $taxTotal = 0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                
                // Price is ALWAYS required for billing
                if ($price <= 0) {
                    throw new Exception('Unit price is required for all items');
                }

                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                // Calculate tax if tax_rate_id provided
                if (!empty($item['tax_rate_id'])) {
                    $stmt = $this->db->prepare("SELECT rate FROM tax_rates WHERE id = ?");
                    $stmt->execute([$item['tax_rate_id']]);
                    $taxRate = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($taxRate) {
                        $taxTotal += $lineTotal * ($taxRate['rate'] / 100);
                    }
                }
            }

            // Apply discount
            $discountAmount = ($discount_type === 'percent') 
                ? ($subtotal * $discount / 100) 
                : $discount;

            $total = $subtotal - $discountAmount + $taxTotal;

            // Insert bill
            $sql = "INSERT INTO bills (
                bill_no, customer_id, stock_out_id, date, due_date,
                subtotal, discount, discount_type, tax, total,
                paid, due, notes, repeat_interval, repeat_next_date,
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 'unpaid', ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $due = $total; // Initially all due
            $stmt->execute([
                $billNo, $customer_id, $stock_out_id, $date, $due_date,
                $subtotal, $discount, $discount_type, $taxTotal, $total,
                $due, $notes, $repeat_interval, $repeat_next_date,
                $this->auth->user()['id']
            ]);

            $billId = $this->db->lastInsertId();

            // Insert bill items
            foreach ($items as $item) {
                $sql = "INSERT INTO bill_items (
                    bill_id, product_id, item_type, description,
                    quantity, unit_price, tax_rate_id, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineTotal = $qty * $price;

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $billId,
                    $item['product_id'] ?? null,
                    $item['item_type'] ?? 'single',
                    $item['description'] ?? '',
                    $qty,
                    $price,
                    $item['tax_rate_id'] ?? null,
                    $lineTotal
                ]);
            }

            // Create ledger entry
            $this->createLedgerEntry($billId, $customer_id, $total, 'debit');

            // Check for low stock after bill creation
            $this->checkLowStock($items);

            // Audit log
            $this->audit->log(
                'create',
                'bills',
                $billId,
                null,
                ['bill_no' => $billNo, 'total' => $total],
                'Bill created'
            );

            $this->db->commit();

            // Notification
            $this->notify->send(
                null,
                'system',
                'New Bill Created',
                "Bill #$billNo - Total: " . $this->helpers->formatCurrency($total),
                'bill',
                $billId
            );

            Response::json([
                'success' => true,
                'message' => 'Bill created successfully',
                'bill_id' => $billId,
                'redirect' => '?module=billing&action=view&id=' . $billId
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * View single bill
     */
    private function view($id) {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as customer_name, c.phone, c.address, c.email,
                   u.name as created_by_name, so.invoice_no as stock_out_no
            FROM bills b
            LEFT JOIN customers c ON b.customer_id = c.id
            LEFT JOIN users u ON b.created_by = u.id
            LEFT JOIN stock_out so ON b.stock_out_id = so.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bill) {
            Response::redirect('?module=billing');
        }

        // Get items
        $stmt = $this->db->prepare("
            SELECT bi.*, p.name as product_name, p.code, tr.rate as tax_rate,
                   tr.name as tax_name
            FROM bill_items bi
            LEFT JOIN products p ON bi.product_id = p.id
            LEFT JOIN tax_rates tr ON bi.tax_rate_id = tr.id
            WHERE bi.bill_id = ?
        ");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get payments
        $stmt = $this->db->prepare("
            SELECT * FROM payments 
            WHERE reference_type = 'bill' AND reference_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // WhatsApp share URL
        $pdfUrl = urlencode(BASE_URL . '/modules/billing/print.php?id=' . $id);
        $message = urlencode("Invoice #{$bill['bill_no']} — Total: {$this->helpers->formatCurrency($bill['total'])}\n$pdfUrl");
        $whatsappUrl = "https://wa.me/?text={$message}";

        include __DIR__ . '/views/view.php';
    }

    /**
     * Edit form
     */
    private function editForm($id) {
        $stmt = $this->db->prepare("SELECT * FROM bills WHERE id = ?");
        $stmt->execute([$id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bill || $bill['is_locked']) {
            Response::redirect('?module=billing');
        }

        // Get items
        $stmt = $this->db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $customers = $this->getActiveCustomers();
        $taxRates = $this->getTaxRates();

        include __DIR__ . '/views/form.php';
    }

    /**
     * Update bill
     */
    private function update($id) {
        try {
            $this->db->beginTransaction();

            // Check if locked
            $stmt = $this->db->prepare("SELECT * FROM bills WHERE id = ?");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill) {
                throw new Exception('Bill not found');
            }

            if ($bill['is_locked']) {
                throw new Exception('Cannot edit a locked bill. Unlock first or contact admin.');
            }

            // Validate CSRF
            if (!$this->helpers->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $customer_id = (int)($_POST['customer_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
            $discount = (float)($_POST['discount'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'percent';
            $notes = $_POST['notes'] ?? '';

            // Recalculate totals
            $items = $_POST['items'] ?? [];
            $subtotal = 0;
            $taxTotal = 0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                
                if ($price <= 0) {
                    throw new Exception('Unit price is required for all items');
                }

                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                if (!empty($item['tax_rate_id'])) {
                    $stmt = $this->db->prepare("SELECT rate FROM tax_rates WHERE id = ?");
                    $stmt->execute([$item['tax_rate_id']]);
                    $taxRate = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($taxRate) {
                        $taxTotal += $lineTotal * ($taxRate['rate'] / 100);
                    }
                }
            }

            $discountAmount = ($discount_type === 'percent') 
                ? ($subtotal * $discount / 100) 
                : $discount;

            $total = $subtotal - $discountAmount + $taxTotal;
            $due = $total - $bill['paid'];

            // Update bill
            $sql = "UPDATE bills SET
                customer_id = ?, date = ?, due_date = ?,
                subtotal = ?, discount = ?, discount_type = ?,
                tax = ?, total = ?, due = ?, notes = ?
                WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $customer_id, $date, $due_date,
                $subtotal, $discount, $discount_type,
                $taxTotal, $total, $due, $notes,
                $id
            ]);

            // Delete old items and insert new
            $stmt = $this->db->prepare("DELETE FROM bill_items WHERE bill_id = ?");
            $stmt->execute([$id]);

            foreach ($items as $item) {
                $sql = "INSERT INTO bill_items (
                    bill_id, product_id, item_type, description,
                    quantity, unit_price, tax_rate_id, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineTotal = $qty * $price;

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $id,
                    $item['product_id'] ?? null,
                    $item['item_type'] ?? 'single',
                    $item['description'] ?? '',
                    $qty,
                    $price,
                    $item['tax_rate_id'] ?? null,
                    $lineTotal
                ]);
            }

            // Audit log
            $this->audit->log(
                'update',
                'bills',
                $id,
                ['old_total' => $bill['total']],
                ['new_total' => $total],
                'Bill updated'
            );

            $this->db->commit();

            Response::json([
                'success' => true,
                'message' => 'Bill updated successfully',
                'redirect' => '?module=billing&action=view&id=' . $id
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete bill
     */
    private function delete($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bills WHERE id = ?");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill) {
                throw new Exception('Bill not found');
            }

            if ($bill['is_locked']) {
                throw new Exception('Cannot delete a locked bill');
            }

            // Delete ledger entries
            $stmt = $this->db->prepare("DELETE FROM ledger_entries WHERE reference_type = 'bill' AND reference_id = ?");
            $stmt->execute([$id]);

            // Delete bill (items cascade)
            $stmt = $this->db->prepare("DELETE FROM bills WHERE id = ?");
            $stmt->execute([$id]);

            // Audit log
            $this->audit->log(
                'delete',
                'bills',
                $id,
                ['bill_no' => $bill['bill_no'], 'total' => $bill['total']],
                null,
                'Bill deleted'
            );

            Response::json([
                'success' => true,
                'message' => 'Bill deleted successfully'
            ]);

        } catch (Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Print bill
     */
    private function print($id) {
        include __DIR__ . '/views/print.php';
    }

    /**
     * Toggle recurring billing
     */
    private function toggleRecurring($id) {
        try {
            $interval = $_POST['interval'] ?? 'none';
            $nextDate = $_POST['next_date'] ?? null;

            $validIntervals = ['none', 'weekly', 'monthly', 'quarterly'];
            if (!in_array($interval, $validIntervals)) {
                throw new Exception('Invalid interval');
            }

            $sql = "UPDATE bills SET repeat_interval = ?, repeat_next_date = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$interval, $nextDate, $id]);

            // Audit log
            $this->audit->log(
                'update',
                'bills',
                $id,
                null,
                ['repeat_interval' => $interval],
                'Recurring billing updated'
            );

            Response::json([
                'success' => true,
                'message' => 'Recurring billing settings updated'
            ]);

        } catch (Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create ledger entry for bill
     */
    private function createLedgerEntry($billId, $customerId, $amount, $type) {
        $stmt = $this->db->prepare("SELECT bill_no FROM bills WHERE id = ?");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        $debit = ($type === 'debit') ? $amount : 0;
        $credit = ($type === 'credit') ? $amount : 0;

        $sql = "INSERT INTO ledger_entries (
            party_type, party_id, date, description, debit, credit,
            reference_type, reference_id, created_at
        ) VALUES ('customer', ?, CURDATE(), ?, ?, ?, 'bill', ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $customerId,
            "Bill #{$bill['bill_no']}",
            $debit,
            $credit,
            $billId
        ]);
    }

    /**
     * Check for low stock after billing
     */
    private function checkLowStock($items) {
        foreach ($items as $item) {
            if (empty($item['product_id'])) continue;

            $stmt = $this->db->prepare("
                SELECT p.*, pp.component_a_id, pp.component_b_id
                FROM products p
                LEFT JOIN product_pairs pp ON p.id = pp.pair_product_id
                WHERE p.id = ?
            ");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) continue;

            // For pair products, check components
            if ($product['product_type'] === 'pair' && $product['component_a_id']) {
                // Check component A
                $stmt = $this->db->prepare("SELECT current_stock, min_stock, name FROM products WHERE id = ?");
                $stmt->execute([$product['component_a_id']]);
                $compA = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($compA && $compA['current_stock'] <= $compA['min_stock']) {
                    $this->notify->send(
                        null,
                        'low_stock',
                        'Low Stock Alert',
                        "{$compA['name']} has only {$compA['current_stock']} left",
                        'product',
                        $product['component_a_id']
                    );
                }

                // Check component B
                $stmt = $this->db->prepare("SELECT current_stock, min_stock, name FROM products WHERE id = ?");
                $stmt->execute([$product['component_b_id']]);
                $compB = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($compB && $compB['current_stock'] <= $compB['min_stock']) {
                    $this->notify->send(
                        null,
                        'low_stock',
                        'Low Stock Alert',
                        "{$compB['name']} has only {$compB['current_stock']} left",
                        'product',
                        $product['component_b_id']
                    );
                }
            } else {
                // Single product
                if ($product['current_stock'] <= $product['min_stock']) {
                    $this->notify->send(
                        null,
                        'low_stock',
                        'Low Stock Alert',
                        "{$product['name']} has only {$product['current_stock']} left",
                        'product',
                        $product['id']
                    );
                }
            }
        }
    }

    /**
     * Get active customers
     */
    private function getActiveCustomers() {
        $stmt = $this->db->query("SELECT id, name, phone FROM customers WHERE status = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get tax rates
     */
    private function getTaxRates() {
        $stmt = $this->db->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY rate");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Router
$action = $_GET['action'] ?? 'list';
$controller = new BillingController();
$controller->handle($action);
