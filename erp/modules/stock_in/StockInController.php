<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/AuditLog.php';
require_once __DIR__ . '/../../core/Notify.php';
require_once __DIR__ . '/../../core/Response.php';

class StockInController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->auth = new Auth();
    }

    public function index() {
        $stmt = $this->db->query("
            SELECT si.*, v.name as vendor_name, u.name as created_by_name,
                   COUNT(sii.id) as item_count
            FROM stock_in si
            LEFT JOIN vendors v ON si.vendor_id = v.id
            LEFT JOIN users u ON si.created_by = u.id
            LEFT JOIN stock_in_items sii ON si.id = sii.stock_in_id
            WHERE si.status != 'cancelled'
            GROUP BY si.id
            ORDER BY si.date DESC, si.created_at DESC
        ");
        $stockIns = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/list.php';
    }

    public function create() {
        // Get vendors for dropdown
        $vendors = $this->db->query("SELECT id, name FROM vendors WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        
        // Get tax rates
        $taxRates = $this->db->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY rate")->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/form.php';
    }

    public function store() {
        try {
            $this->db->beginTransaction();
            
            $data = $_POST;
            $invoiceNo = $data['invoice_no'] ?? $this->generateInvoiceNo();
            $vendorId = $data['vendor_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $notes = $data['notes'] ?? '';
            $status = $data['status'] ?? 'confirmed';
            
            // Calculate totals if prices provided
            $subtotal = 0;
            $tax = 0;
            $total = 0;
            $items = $data['items'] ?? [];
            
            foreach ($items as $item) {
                if (!empty($item['unit_price']) && !empty($item['quantity'])) {
                    $lineTotal = $item['unit_price'] * $item['quantity'];
                    $subtotal += $lineTotal;
                    
                    if (!empty($item['tax_rate_id'])) {
                        $taxStmt = $this->db->prepare("SELECT rate FROM tax_rates WHERE id = ?");
                        $taxStmt->execute([$item['tax_rate_id']]);
                        $taxRate = $taxStmt->fetch(PDO::FETCH_OBJ);
                        if ($taxRate) {
                            $tax += $lineTotal * ($taxRate->rate / 100);
                        }
                    }
                }
            }
            
            $discount = floatval($data['discount'] ?? 0);
            $discountType = $data['discount_type'] ?? 'percent';
            
            if ($discount > 0) {
                if ($discountType === 'percent') {
                    $subtotal -= $subtotal * ($discount / 100);
                } else {
                    $subtotal -= $discount;
                }
            }
            
            $total = $subtotal + $tax;
            
            // Insert stock_in
            $stmt = $this->db->prepare("
                INSERT INTO stock_in (invoice_no, vendor_id, date, subtotal, discount, discount_type, tax, total, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceNo, $vendorId, $date, 
                $subtotal > 0 ? $subtotal : null,
                $discount, $discountType,
                $tax > 0 ? $tax : null,
                $total > 0 ? $total : null,
                $notes, $status, $this->auth->id()
            ]);
            
            $stockInId = $this->db->lastInsertId();
            
            // Insert items and update stock
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $itemType = $item['item_type'] ?? 'single';
                $quantity = floatval($item['quantity'] ?? 0);
                $unitPrice = !empty($item['unit_price']) ? floatval($item['unit_price']) : null;
                $taxRateId = $item['tax_rate_id'] ?? null;
                
                if (!$productId || $quantity <= 0) continue;
                
                // Insert item
                $itemStmt = $this->db->prepare("
                    INSERT INTO stock_in_items (stock_in_id, product_id, item_type, quantity, unit_price, tax_rate_id, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $itemTotal = ($unitPrice !== null) ? ($unitPrice * $quantity) : null;
                $itemStmt->execute([$stockInId, $productId, $itemType, $quantity, $unitPrice, $taxRateId, $itemTotal]);
                
                // Update product stock
                if ($itemType === 'single') {
                    $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                        ->execute([$quantity, $productId]);
                    
                    // Log movement
                    AuditLog::logMovement($productId, $itemType, 'in', $quantity, 'stock_in', $stockInId, $notes, $this->auth->id());
                } else {
                    // Pair product - decrement both components
                    $pairStmt = $this->db->prepare("SELECT component_a_id, component_b_id FROM product_pairs WHERE pair_product_id = ?");
                    $pairStmt->execute([$productId]);
                    $pair = $pairStmt->fetch(PDO::FETCH_OBJ);
                    
                    if ($pair) {
                        // Decrement component A
                        $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_a_id]);
                        AuditLog::logMovement($pair->component_a_id, 'single', 'in', $quantity, 'stock_in', $stockInId, "Pair component A - {$notes}", $this->auth->id());
                        
                        // Decrement component B
                        $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_b_id]);
                        AuditLog::logMovement($pair->component_b_id, 'single', 'in', $quantity, 'stock_in', $stockInId, "Pair component B - {$notes}", $this->auth->id());
                        
                        // Log pair creation movement
                        AuditLog::logMovement($productId, 'pair', 'pair_created', $quantity, 'stock_in', $stockInId, $notes, $this->auth->id());
                    }
                }
            }
            
            // Check for low stock notifications (after stock increase, clear any existing alerts)
            Notify::clearByReference('product', null); // Could be more specific
            
            $this->db->commit();
            
            AuditLog::log('create', 'stock_in', $stockInId, null, ['invoice_no' => $invoiceNo], $this->auth->id());
            
            Response::json(['success' => true, 'message' => 'Stock In created successfully', 'id' => $stockInId]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show($id) {
        $stmt = $this->db->prepare("
            SELECT si.*, v.name as vendor_name, v.phone as vendor_phone, v.address as vendor_address,
                   u.name as created_by_name
            FROM stock_in si
            LEFT JOIN vendors v ON si.vendor_id = v.id
            LEFT JOIN users u ON si.created_by = u.id
            WHERE si.id = ?
        ");
        $stmt->execute([$id]);
        $stockIn = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$stockIn) {
            Response::redirect('/stock_in', 'Stock In not found');
            return;
        }
        
        $items = $this->db->prepare("
            SELECT sii.*, p.name as product_name, p.code as product_code, 
                   tr.rate as tax_rate, tr.name as tax_name
            FROM stock_in_items sii
            LEFT JOIN products p ON sii.product_id = p.id
            LEFT JOIN tax_rates tr ON sii.tax_rate_id = tr.id
            WHERE sii.stock_in_id = ?
        ");
        $items->execute([$id]);
        $stockIn->items = $items->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/view.php';
    }

    public function edit($id) {
        $stockIn = $this->db->prepare("SELECT * FROM stock_in WHERE id = ?");
        $stockIn->execute([$id]);
        $stockIn = $stockIn->fetch(PDO::FETCH_OBJ);
        
        if (!$stockIn || $stockIn->is_locked) {
            Response::redirect('/stock_in', 'Cannot edit locked or non-existent record');
            return;
        }
        
        $vendors = $this->db->query("SELECT id, name FROM vendors WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        $taxRates = $this->db->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY rate")->fetchAll(PDO::FETCH_OBJ);
        
        $items = $this->db->prepare("SELECT * FROM stock_in_items WHERE stock_in_id = ?");
        $items->execute([$id]);
        $stockIn->items = $items->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/form.php';
    }

    public function update($id) {
        // Similar to store but with UPDATE logic
        // For simplicity, preventing updates to locked records
        Response::json(['success' => false, 'message' => 'Update not implemented - create new record instead']);
    }

    public function destroy($id) {
        $stockIn = $this->db->prepare("SELECT * FROM stock_in WHERE id = ?");
        $stockIn->execute([$id]);
        $record = $stockIn->fetch(PDO::FETCH_OBJ);
        
        if (!$record) {
            Response::json(['success' => false, 'message' => 'Record not found'], 404);
            return;
        }
        
        if ($record->is_locked) {
            Response::json(['success' => false, 'message' => 'Cannot delete locked record'], 403);
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Reverse stock movements
            $items = $this->db->prepare("SELECT * FROM stock_in_items WHERE stock_in_id = ?");
            $items->execute([$id]);
            foreach ($items->fetchAll(PDO::FETCH_OBJ) as $item) {
                if ($item->item_type === 'single') {
                    $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                        ->execute([$item->quantity, $item->product_id]);
                    AuditLog::logMovement($item->product_id, 'single', 'out', $item->quantity, 'stock_in_delete', $id, 'Reversed on delete', $this->auth->id());
                }
            }
            
            // Delete record (cascade will handle items)
            $this->db->prepare("DELETE FROM stock_in WHERE id = ?")->execute([$id]);
            
            $this->db->commit();
            
            AuditLog::log('delete', 'stock_in', $id, ['invoice_no' => $record->invoice_no], null, $this->auth->id());
            
            Response::json(['success' => true, 'message' => 'Stock In deleted successfully']);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function returns() {
        $stmt = $this->db->query("
            SELECT sir.*, v.name as vendor_name, si.invoice_no as original_invoice
            FROM stock_in_returns sir
            LEFT JOIN vendors v ON sir.vendor_id = v.id
            LEFT JOIN stock_in si ON sir.stock_in_id = si.id
            ORDER BY sir.date DESC
        ");
        $returns = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/returns-list.php';
    }

    public function createReturn() {
        $stockIns = $this->db->query("
            SELECT id, invoice_no, vendor_id, date 
            FROM stock_in 
            WHERE status = 'confirmed' 
            ORDER BY date DESC
        ")->fetchAll(PDO::FETCH_OBJ);
        
        $vendors = $this->db->query("SELECT id, name FROM vendors WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/return-form.php';
    }

    public function storeReturn() {
        try {
            $this->db->beginTransaction();
            
            $data = $_POST;
            $returnNo = $data['return_no'] ?? $this->generateReturnNo();
            $stockInId = $data['stock_in_id'];
            $vendorId = $data['vendor_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $notes = $data['notes'] ?? '';
            
            // Insert return
            $stmt = $this->db->prepare("
                INSERT INTO stock_in_returns (return_no, stock_in_id, vendor_id, date, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$returnNo, $stockInId, $vendorId, $date, $notes, $this->auth->id()]);
            
            $returnId = $this->db->lastInsertId();
            
            // Process return items
            $items = $data['items'] ?? [];
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $itemType = $item['item_type'] ?? 'single';
                $quantity = floatval($item['quantity'] ?? 0);
                
                if (!$productId || $quantity <= 0) continue;
                
                // Insert return item
                $itemStmt = $this->db->prepare("
                    INSERT INTO stock_in_return_items (return_id, product_id, item_type, quantity)
                    VALUES (?, ?, ?, ?)
                ");
                $itemStmt->execute([$returnId, $productId, $itemType, $quantity]);
                
                // Reduce stock (reverse of stock_in)
                if ($itemType === 'single') {
                    $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                        ->execute([$quantity, $productId]);
                    AuditLog::logMovement($productId, 'single', 'return_in', $quantity, 'stock_in_return', $returnId, $notes, $this->auth->id());
                } else {
                    // Pair product
                    $pairStmt = $this->db->prepare("SELECT component_a_id, component_b_id FROM product_pairs WHERE pair_product_id = ?");
                    $pairStmt->execute([$productId]);
                    $pair = $pairStmt->fetch(PDO::FETCH_OBJ);
                    
                    if ($pair) {
                        $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_a_id]);
                        AuditLog::logMovement($pair->component_a_id, 'single', 'return_in', $quantity, 'stock_in_return', $returnId, "Pair component A", $this->auth->id());
                        
                        $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_b_id]);
                        AuditLog::logMovement($pair->component_b_id, 'single', 'return_in', $quantity, 'stock_in_return', $returnId, "Pair component B", $this->auth->id());
                    }
                }
            }
            
            $this->db->commit();
            
            AuditLog::log('create', 'stock_in_return', $returnId, null, ['return_no' => $returnNo], $this->auth->id());
            
            Response::json(['success' => true, 'message' => 'Return created successfully', 'id' => $returnId]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function generateInvoiceNo() {
        $prefix = Helpers::getSetting('stock_in_prefix', 'SI');
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT invoice_no FROM stock_in 
            WHERE invoice_no LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(["{$prefix}-{$year}-%"]);
        $last = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($last) {
            $parts = explode('-', $last->invoice_no);
            $num = intval(end($parts)) + 1;
        } else {
            $num = 1;
        }
        
        return sprintf("%s-%s-%04d", $prefix, $year, $num);
    }

    private function generateReturnNo() {
        $prefix = 'SIR';
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT return_no FROM stock_in_returns 
            WHERE return_no LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(["{$prefix}-{$year}-%"]);
        $last = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($last) {
            $parts = explode('-', $last->return_no);
            $num = intval(end($parts)) + 1;
        } else {
            $num = 1;
        }
        
        return sprintf("%s-%s-%04d", $prefix, $year, $num);
    }
}
