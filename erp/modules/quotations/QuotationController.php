<?php
/**
 * Quotation Controller
 * Create, manage, and convert quotations to sales
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Notify.php';

class QuotationController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->auth = new Auth();
    }

    /**
     * List quotations
     */
    public function index() {
        $this->auth->requireLogin();
        
        $search = $_GET['search'] ?? '';
        $customer = $_GET['customer'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $sql = "SELECT q.*, c.name as customer_name
                FROM quotations q
                LEFT JOIN customers c ON q.customer_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        if ($search) {
            $sql .= " AND (q.quote_no LIKE ? OR q.title LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($customer) {
            $sql .= " AND q.customer_id = ?";
            $params[] = $customer;
        }
        
        if ($status) {
            $sql .= " AND q.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY q.created_at DESC";
        
        $quotations = $this->db->query($sql, $params);
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name");
        
        include __DIR__ . '/views/list.php';
    }

    /**
     * Show quotation form (create/edit)
     */
    public function form() {
        $this->auth->requireLogin();
        
        $id = $_GET['id'] ?? null;
        $quotation = null;
        $items = [];
        
        if ($id) {
            $quotation = $this->db->queryOne(
                "SELECT q.*, c.name as customer_name FROM quotations q
                 LEFT JOIN customers c ON q.customer_id = c.id WHERE q.id = ?",
                [$id]
            );
            $items = $this->db->query(
                "SELECT qi.*, p.name as product_name, p.code as product_code,
                        t.rate as tax_rate, t.name as tax_name
                 FROM quotation_items qi
                 LEFT JOIN products p ON qi.product_id = p.id
                 LEFT JOIN tax_rates t ON qi.tax_rate_id = t.id
                 WHERE qi.quotation_id = ?",
                [$id]
            );
        }
        
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name");
        $taxRates = $this->db->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY rate");
        
        include __DIR__ . '/views/form.php';
    }

    /**
     * Save quotation
     */
    public function save() {
        $this->auth->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        $id = $_POST['id'] ?? null;
        $customerId = $_POST['customer_id'] ?? null;
        $date = $_POST['date'] ?? date('Y-m-d');
        $validUntil = $_POST['valid_until'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $terms = trim($_POST['terms'] ?? '');
        $items = $_POST['items'] ?? [];
        
        if (!$customerId || empty($items)) {
            Response::error('Customer and at least one item required');
        }
        
        // Calculate totals
        $subtotal = 0;
        $taxTotal = 0;
        $discount = Helpers::toDecimal($_POST['discount'] ?? 0);
        $discountType = $_POST['discount_type'] ?? 'percent';
        
        foreach ($items as &$item) {
            $qty = Helpers::toDecimal($item['quantity'] ?? 0);
            $price = Helpers::toDecimal($item['unit_price'] ?? 0);
            $taxRateId = $item['tax_rate_id'] ?? null;
            
            if ($qty <= 0 || $price <= 0) {
                Response::error('Invalid quantity or price for item');
            }
            
            $lineTotal = $qty * $price;
            $item['total'] = $lineTotal;
            
            if ($taxRateId) {
                $taxRate = $this->db->queryOne("SELECT rate FROM tax_rates WHERE id = ?", [$taxRateId]);
                $taxAmount = $lineTotal * ($taxRate['rate'] / 100);
                $item['tax_amount'] = $taxAmount;
                $taxTotal += $taxAmount;
            } else {
                $item['tax_amount'] = 0;
            }
            
            $subtotal += $lineTotal;
        }
        
        // Apply discount
        if ($discount > 0) {
            if ($discountType == 'percent') {
                $discountAmount = $subtotal * ($discount / 100);
            } else {
                $discountAmount = $discount;
            }
        } else {
            $discountAmount = 0;
        }
        
        $total = $subtotal - $discountAmount + $taxTotal;
        
        try {
            $this->db->beginTransaction();
            
            if ($id) {
                // Update existing
                $quoteNo = $_POST['quote_no'];
                $this->db->execute(
                    "UPDATE quotations SET 
                     customer_id = ?, date = ?, valid_until = ?, 
                     subtotal = ?, discount = ?, discount_type = ?, 
                     tax = ?, total = ?, notes = ?, terms = ?, 
                     status = COALESCE(status, 'draft')
                     WHERE id = ?",
                    [$customerId, $date, $validUntil ?: null, $subtotal, $discountAmount, 
                     $discountType, $taxTotal, $total, $notes, $terms, $id]
                );
                
                // Delete old items
                $this->db->execute("DELETE FROM quotation_items WHERE quotation_id = ?", [$id]);
                
                AuditLog::record('update', 'quotations', $id);
            } else {
                // Create new
                $quoteNo = 'QT-' . date('Y') . '-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
                $id = $this->db->insert(
                    "INSERT INTO quotations 
                     (quote_no, customer_id, date, valid_until, subtotal, discount, discount_type, 
                      tax, total, notes, terms, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)",
                    [$quoteNo, $customerId, $date, $validUntil ?: null, $subtotal, $discountAmount, 
                     $discountType, $taxTotal, $total, $notes, $terms, $this->auth->userId()]
                );
                
                AuditLog::record('create', 'quotations', $id);
            }
            
            // Insert items
            $stmt = $this->db->pdo()->prepare(
                "INSERT INTO quotation_items 
                 (quotation_id, product_id, item_type, quantity, unit_price, tax_rate_id, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            
            foreach ($items as $item) {
                $stmt->execute([
                    $id,
                    $item['product_id'],
                    $item['item_type'] ?? 'single',
                    $item['quantity'],
                    $item['unit_price'],
                    $item['tax_rate_id'] ?: null,
                    $item['total']
                ]);
            }
            
            $this->db->commit();
            
            Response::success('Quotation saved successfully', [
                'url' => "?module=quotations&action=view&id=$id"
            ]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to save quotation: ' . $e->getMessage());
        }
    }

    /**
     * View quotation details
     */
    public function view() {
        $this->auth->requireLogin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            Response::error('Invalid quotation ID');
        }
        
        $quotation = $this->db->queryOne(
            "SELECT q.*, c.name as customer_name, c.phone as customer_phone, 
                    c.email as customer_email, c.address as customer_address,
                    u.name as created_by_name
             FROM quotations q
             LEFT JOIN customers c ON q.customer_id = c.id
             LEFT JOIN users u ON q.created_by = u.id
             WHERE q.id = ?",
            [$id]
        );
        
        if (!$quotation) {
            Response::error('Quotation not found');
        }
        
        $items = $this->db->query(
            "SELECT qi.*, p.name as product_name, p.code as product_code,
                    t.rate as tax_rate, t.name as tax_name
             FROM quotation_items qi
             LEFT JOIN products p ON qi.product_id = p.id
             LEFT JOIN tax_rates t ON qi.tax_rate_id = t.id
             WHERE qi.quotation_id = ?",
            [$id]
        );
        
        include __DIR__ . '/views/view.php';
    }

    /**
     * Print quotation PDF
     */
    public function print() {
        $this->auth->requireLogin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            Response::error('Invalid quotation ID');
        }
        
        // Same data fetch as view()
        $quotation = $this->db->queryOne(
            "SELECT q.*, c.name as customer_name, c.phone as customer_phone, 
                    c.email as customer_email, c.address as customer_address
             FROM quotations q
             LEFT JOIN customers c ON q.customer_id = c.id
             WHERE q.id = ?",
            [$id]
        );
        
        $items = $this->db->query(
            "SELECT qi.*, p.name as product_name, p.code as product_code,
                    t.rate as tax_rate
             FROM quotation_items qi
             LEFT JOIN products p ON qi.product_id = p.id
             LEFT JOIN tax_rates t ON qi.tax_rate_id = t.id
             WHERE qi.quotation_id = ?",
            [$id]
        );
        
        include __DIR__ . '/views/print.php';
    }

    /**
     * Convert quotation to stock_out (sale)
     */
    public function convertToSale() {
        $this->auth->requireLogin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            Response::error('Invalid quotation ID');
        }
        
        $quotation = $this->db->queryOne("SELECT * FROM quotations WHERE id = ?", [$id]);
        if (!$quotation) {
            Response::error('Quotation not found');
        }
        
        if ($quotation['converted_to_sale']) {
            Response::error('Already converted to sale');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Create stock_out
            $invoiceNo = 'SO-' . date('Y') . '-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
            $stockOutId = $this->db->insert(
                "INSERT INTO stock_out 
                 (invoice_no, customer_id, date, type, status, notes, created_by)
                 VALUES (?, ?, ?, 'sale', 'confirmed', ?, ?)",
                [$invoiceNo, $quotation['customer_id'], date('Y-m-d'), 
                 'Converted from quotation ' . $quotation['quote_no'], $this->auth->userId()]
            );
            
            // Copy items
            $items = $this->db->query(
                "SELECT * FROM quotation_items WHERE quotation_id = ?",
                [$id]
            );
            
            foreach ($items as $item) {
                $this->db->execute(
                    "INSERT INTO stock_out_items 
                     (stock_out_id, product_id, item_type, quantity, unit_price, tax_rate_id, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$stockOutId, $item['product_id'], $item['item_type'], 
                     $item['quantity'], $item['unit_price'], $item['tax_rate_id'], $item['total']]
                );
                
                // Update product stock
                if ($item['item_type'] == 'pair') {
                    // Handle pair deduction
                    $pairComponents = $this->db->queryOne(
                        "SELECT component_a_id, component_b_id FROM product_pairs 
                         WHERE pair_product_id = ?",
                        [$item['product_id']]
                    );
                    if ($pairComponents) {
                        $this->db->execute(
                            "UPDATE products SET current_stock = current_stock - ? WHERE id = ?",
                            [$item['quantity'], $pairComponents['component_a_id']]
                        );
                        $this->db->execute(
                            "UPDATE products SET current_stock = current_stock - ? WHERE id = ?",
                            [$item['quantity'], $pairComponents['component_b_id']]
                        );
                    }
                } else {
                    $this->db->execute(
                        "UPDATE products SET current_stock = current_stock - ? WHERE id = ?",
                        [$item['quantity'], $item['product_id']]
                    );
                }
            }
            
            // Update quotation status
            $this->db->execute(
                "UPDATE quotations SET status = 'accepted', converted_to_sale = ? WHERE id = ?",
                [$stockOutId, $id]
            );
            
            AuditLog::record('update', 'quotations', $id, 
                ['status' => $quotation['status']], 
                ['status' => 'accepted', 'converted_to_sale' => $stockOutId]);
            
            $this->db->commit();
            
            Response::success('Quotation converted to sale successfully', [
                'url' => "?module=stock_out&action=view&id=$stockOutId"
            ]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to convert: ' . $e->getMessage());
        }
    }

    /**
     * Delete quotation
     */
    public function delete() {
        $this->auth->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        $id = $_POST['id'] ?? null;
        $quotation = $this->db->queryOne("SELECT * FROM quotations WHERE id = ?", [$id]);
        
        if (!$quotation) {
            Response::error('Quotation not found');
        }
        
        if ($quotation['converted_to_sale']) {
            Response::error('Cannot delete converted quotation');
        }
        
        $this->db->execute("DELETE FROM quotations WHERE id = ?", [$id]);
        AuditLog::record('delete', 'quotations', $id);
        
        Response::success('Quotation deleted successfully');
    }
}
