<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/AuditLog.php';
require_once __DIR__ . '/../../core/Notify.php';
require_once __DIR__ . '/../../core/Response.php';

class StockOutController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->auth = new Auth();
    }

    public function index() {
        $stmt = $this->db->query("
            SELECT so.*, c.name as customer_name, u.name as created_by_name,
                   COUNT(soi.id) as item_count
            FROM stock_out so
            LEFT JOIN customers c ON so.customer_id = c.id
            LEFT JOIN users u ON so.created_by = u.id
            LEFT JOIN stock_out_items soi ON so.id = soi.stock_out_id
            WHERE so.status != 'cancelled'
            GROUP BY so.id
            ORDER BY so.date DESC, so.created_at DESC
        ");
        $stockOuts = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/list.php';
    }

    public function create() {
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        $taxRates = $this->db->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY rate")->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/form.php';
    }

    public function store() {
        try {
            $this->db->beginTransaction();
            
            $data = $_POST;
            $invoiceNo = $data['invoice_no'] ?? $this->generateInvoiceNo();
            $customerId = $data['customer_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $type = $data['type'] ?? 'sale';
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
            
            // Insert stock_out
            $stmt = $this->db->prepare("
                INSERT INTO stock_out (invoice_no, customer_id, date, type, subtotal, discount, discount_type, tax, total, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceNo, $customerId, $date, $type,
                $subtotal > 0 ? $subtotal : null,
                $discount, $discountType,
                $tax > 0 ? $tax : null,
                $total > 0 ? $total : null,
                $notes, $status, $this->auth->id()
            ]);
            
            $stockOutId = $this->db->lastInsertId();
            
            // Insert items and update stock
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $itemType = $item['item_type'] ?? 'single';
                $quantity = floatval($item['quantity'] ?? 0);
                
                if (!$productId || $quantity <= 0) continue;
                
                // Check stock availability before deducting
                $availableStock = 0;
                if ($itemType === 'single') {
                    $stockCheck = $this->db->prepare("SELECT current_stock FROM products WHERE id = ?");
                    $stockCheck->execute([$productId]);
                    $product = $stockCheck->fetch(PDO::FETCH_OBJ);
                    $availableStock = $product ? $product->current_stock : 0;
                } else {
                    // Pair product - check both components
                    $pairStmt = $this->db->prepare("SELECT component_a_id, component_b_id FROM product_pairs WHERE pair_product_id = ?");
                    $pairStmt->execute([$productId]);
                    $pair = $pairStmt->fetch(PDO::FETCH_OBJ);
                    
                    if ($pair) {
                        $stockA = $this->db->prepare("SELECT current_stock FROM products WHERE id = ?");
                        $stockA->execute([$pair->component_a_id]);
                        $compA = $stockA->fetch(PDO::FETCH_OBJ);
                        
                        $stockB = $this->db->prepare("SELECT current_stock FROM products WHERE id = ?");
                        $stockB->execute([$pair->component_b_id]);
                        $compB = $stockB->fetch(PDO::FETCH_OBJ);
                        
                        $availableStock = min($compA->current_stock ?? 0, $compB->current_stock ?? 0);
                    }
                }
                
                if ($availableStock < $quantity) {
                    $this->db->rollBack();
                    Response::json(['success' => false, 'message' => 'Insufficient stock for product ID ' . $productId], 400);
                    return;
                }
                
                // Insert item
                $unitPrice = !empty($item['unit_price']) ? floatval($item['unit_price']) : null;
                $taxRateId = $item['tax_rate_id'] ?? null;
                $itemTotal = ($unitPrice !== null) ? ($unitPrice * $quantity) : null;
                
                $itemStmt = $this->db->prepare("
                    INSERT INTO stock_out_items (stock_out_id, product_id, item_type, quantity, unit_price, tax_rate_id, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $itemStmt->execute([$stockOutId, $productId, $itemType, $quantity, $unitPrice, $taxRateId, $itemTotal]);
                
                // Update product stock
                if ($itemType === 'single') {
                    $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                        ->execute([$quantity, $productId]);
                    
                    AuditLog::logMovement($productId, 'single', 'out', $quantity, 'stock_out', $stockOutId, $notes, $this->auth->id());
                } else {
                    // Pair product - decrement both components
                    $pairStmt = $this->db->prepare("SELECT component_a_id, component_b_id FROM product_pairs WHERE pair_product_id = ?");
                    $pairStmt->execute([$productId]);
                    $pair = $pairStmt->fetch(PDO::FETCH_OBJ);
                    
                    if ($pair) {
                        $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_a_id]);
                        AuditLog::logMovement($pair->component_a_id, 'single', 'out', $quantity, 'stock_out', $stockOutId, "Pair component A - {$notes}", $this->auth->id());
                        
                        $this->db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_b_id]);
                        AuditLog::logMovement($pair->component_b_id, 'single', 'out', $quantity, 'stock_out', $stockOutId, "Pair component B - {$notes}", $this->auth->id());
                    }
                }
                
                // Check for low stock alert
                if ($itemType === 'single') {
                    $this->checkLowStock($productId);
                } else if (isset($pair)) {
                    $this->checkLowStock($pair->component_a_id);
                    $this->checkLowStock($pair->component_b_id);
                }
            }
            
            $this->db->commit();
            
            AuditLog::log('create', 'stock_out', $stockOutId, null, ['invoice_no' => $invoiceNo], $this->auth->id());
            
            Response::json(['success' => true, 'message' => 'Stock Out created successfully', 'id' => $stockOutId]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show($id) {
        $stmt = $this->db->prepare("
            SELECT so.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
                   u.name as created_by_name
            FROM stock_out so
            LEFT JOIN customers c ON so.customer_id = c.id
            LEFT JOIN users u ON so.created_by = u.id
            WHERE so.id = ?
        ");
        $stmt->execute([$id]);
        $stockOut = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$stockOut) {
            Response::redirect('/stock_out', 'Stock Out not found');
            return;
        }
        
        $items = $this->db->prepare("
            SELECT soi.*, p.name as product_name, p.code as product_code, 
                   tr.rate as tax_rate, tr.name as tax_name
            FROM stock_out_items soi
            LEFT JOIN products p ON soi.product_id = p.id
            LEFT JOIN tax_rates tr ON soi.tax_rate_id = tr.id
            WHERE soi.stock_out_id = ?
        ");
        $items->execute([$id]);
        $stockOut->items = $items->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/view.php';
    }

    public function edit($id) {
        $stockOut = $this->db->prepare("SELECT * FROM stock_out WHERE id = ?");
        $stockOut->execute([$id]);
        $stockOut = $stockOut->fetch(PDO::FETCH_OBJ);
        
        if (!$stockOut || $stockOut->is_locked) {
            Response::redirect('/stock_out', 'Cannot edit locked or non-existent record');
            return;
        }
        
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        $taxRates = $this->db->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY rate")->fetchAll(PDO::FETCH_OBJ);
        
        $items = $this->db->prepare("SELECT * FROM stock_out_items WHERE stock_out_id = ?");
        $items->execute([$id]);
        $stockOut->items = $items->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/form.php';
    }

    public function destroy($id) {
        $stockOut = $this->db->prepare("SELECT * FROM stock_out WHERE id = ?");
        $stockOut->execute([$id]);
        $record = $stockOut->fetch(PDO::FETCH_OBJ);
        
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
            $items = $this->db->prepare("SELECT * FROM stock_out_items WHERE stock_out_id = ?");
            $items->execute([$id]);
            foreach ($items->fetchAll(PDO::FETCH_OBJ) as $item) {
                if ($item->item_type === 'single') {
                    $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                        ->execute([$item->quantity, $item->product_id]);
                    AuditLog::logMovement($item->product_id, 'single', 'in', $item->quantity, 'stock_out_delete', $id, 'Reversed on delete', $this->auth->id());
                }
            }
            
            $this->db->prepare("DELETE FROM stock_out WHERE id = ?")->execute([$id]);
            
            $this->db->commit();
            
            AuditLog::log('delete', 'stock_out', $id, ['invoice_no' => $record->invoice_no], null, $this->auth->id());
            
            Response::json(['success' => true, 'message' => 'Stock Out deleted successfully']);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function returns() {
        $stmt = $this->db->query("
            SELECT sor.*, c.name as customer_name, so.invoice_no as original_invoice
            FROM stock_out_returns sor
            LEFT JOIN customers c ON sor.customer_id = c.id
            LEFT JOIN stock_out so ON sor.stock_out_id = so.id
            ORDER BY sor.date DESC
        ");
        $returns = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/returns-list.php';
    }

    public function createReturn() {
        $stockOuts = $this->db->query("
            SELECT id, invoice_no, customer_id, date 
            FROM stock_out 
            WHERE status IN ('confirmed', 'delivered') 
            ORDER BY date DESC
        ")->fetchAll(PDO::FETCH_OBJ);
        
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
        
        include __DIR__ . '/views/return-form.php';
    }

    public function storeReturn() {
        try {
            $this->db->beginTransaction();
            
            $data = $_POST;
            $returnNo = $data['return_no'] ?? $this->generateReturnNo();
            $stockOutId = $data['stock_out_id'];
            $customerId = $data['customer_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $notes = $data['notes'] ?? '';
            
            $stmt = $this->db->prepare("
                INSERT INTO stock_out_returns (return_no, stock_out_id, customer_id, date, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$returnNo, $stockOutId, $customerId, $date, $notes, $this->auth->id()]);
            
            $returnId = $this->db->lastInsertId();
            
            $items = $data['items'] ?? [];
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $itemType = $item['item_type'] ?? 'single';
                $quantity = floatval($item['quantity'] ?? 0);
                
                if (!$productId || $quantity <= 0) continue;
                
                $itemStmt = $this->db->prepare("
                    INSERT INTO stock_out_return_items (return_id, product_id, item_type, quantity)
                    VALUES (?, ?, ?, ?)
                ");
                $itemStmt->execute([$returnId, $productId, $itemType, $quantity]);
                
                // Add stock back (customer return)
                if ($itemType === 'single') {
                    $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                        ->execute([$quantity, $productId]);
                    AuditLog::logMovement($productId, 'single', 'return_out', $quantity, 'stock_out_return', $returnId, $notes, $this->auth->id());
                } else {
                    $pairStmt = $this->db->prepare("SELECT component_a_id, component_b_id FROM product_pairs WHERE pair_product_id = ?");
                    $pairStmt->execute([$productId]);
                    $pair = $pairStmt->fetch(PDO::FETCH_OBJ);
                    
                    if ($pair) {
                        $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_a_id]);
                        AuditLog::logMovement($pair->component_a_id, 'single', 'return_out', $quantity, 'stock_out_return', $returnId, "Pair component A", $this->auth->id());
                        
                        $this->db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                            ->execute([$quantity, $pair->component_b_id]);
                        AuditLog::logMovement($pair->component_b_id, 'single', 'return_out', $quantity, 'stock_out_return', $returnId, "Pair component B", $this->auth->id());
                    }
                }
            }
            
            $this->db->commit();
            
            AuditLog::log('create', 'stock_out_return', $returnId, null, ['return_no' => $returnNo], $this->auth->id());
            
            Response::json(['success' => true, 'message' => 'Return created successfully', 'id' => $returnId]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function checkLowStock($productId) {
        $product = $this->db->prepare("SELECT name, current_stock, min_stock FROM products WHERE id = ?");
        $product->execute([$productId]);
        $p = $product->fetch(PDO::FETCH_OBJ);
        
        if ($p && $p->current_stock <= $p->min_stock) {
            Notify::send(
                null,
                'low_stock',
                'Low stock alert: ' . $p->name,
                'Only ' . $p->current_stock . ' left (min: ' . $p->min_stock . ')',
                'product',
                $productId
            );
        }
    }

    private function generateInvoiceNo() {
        $prefix = Helpers::getSetting('stock_out_prefix', 'SO');
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT invoice_no FROM stock_out 
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
        $prefix = 'SOR';
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT return_no FROM stock_out_returns 
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
