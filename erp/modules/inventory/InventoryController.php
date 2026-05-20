<?php
/**
 * Inventory Controller
 * Stock summary, movements, adjustments, pair availability
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Notify.php';

class InventoryController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->auth = new Auth();
    }

    /**
     * Show stock summary with pair availability
     */
    public function index() {
        $this->auth->requireLogin();
        
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $lowStockOnly = isset($_GET['low_stock']);
        
        $sql = "SELECT 
            p.id, p.code, p.name, p.product_type,
            c.name as category_name, u.name as unit_name,
            p.current_stock, p.min_stock,
            p.purchase_price, p.sale_price,
            CASE WHEN p.current_stock <= p.min_stock THEN 1 ELSE 0 END as is_low_stock";
        
        if ($p['product_type'] == 'pair') {
            $sql .= ", (SELECT MIN(cp.current_stock) FROM products cp 
                       JOIN product_pairs pp ON (cp.id = pp.component_a_id OR cp.id = pp.component_b_id) 
                       WHERE pp.pair_product_id = p.id) as pair_available";
        } else {
            $sql .= ", NULL as pair_available";
        }
        
        $sql .= " FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.status = 1";
        
        $params = [];
        
        if ($search) {
            $sql .= " AND (p.code LIKE ? OR p.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($category) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category;
        }
        
        if ($lowStockOnly) {
            $sql .= " AND p.current_stock <= p.min_stock";
        }
        
        $sql .= " ORDER BY p.name ASC";
        
        $products = $this->db->query($sql, $params);
        
        // Calculate pair availability for pair products
        foreach ($products as &$product) {
            if ($product['product_type'] == 'pair') {
                $pairInfo = $this->getPairComponents($product['id']);
                $product['components'] = $pairInfo;
                if ($pairInfo) {
                    $product['pair_available'] = min(
                        $pairInfo['component_a_stock'],
                        $pairInfo['component_b_stock']
                    );
                }
            }
        }
        
        $categories = $this->db->query("SELECT id, name FROM categories WHERE type = 'product' ORDER BY name");
        
        include __DIR__ . '/views/summary.php';
    }

    /**
     * Get pair component details
     */
    private function getPairComponents($pairProductId) {
        $sql = "SELECT 
            pp.component_a_id, pp.component_b_id,
            pa.name as component_a_name, pb.name as component_b_name,
            pa.current_stock as component_a_stock, pb.current_stock as component_b_stock,
            pa.code as component_a_code, pb.code as component_b_code
            FROM product_pairs pp
            JOIN products pa ON pp.component_a_id = pa.id
            JOIN products pb ON pp.component_b_id = pb.id
            WHERE pp.pair_product_id = ?";
        
        return $this->db->queryOne($sql, [$pairProductId]);
    }

    /**
     * Show stock movements log
     */
    public function movements() {
        $this->auth->requireLogin();
        
        $productId = $_GET['product_id'] ?? '';
        $type = $_GET['type'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $sql = "SELECT sm.*, p.name as product_name, p.code as product_code,
                u.name as user_name
                FROM stock_movements sm
                JOIN products p ON sm.product_id = p.id
                LEFT JOIN users u ON sm.created_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if ($productId) {
            $sql .= " AND sm.product_id = ?";
            $params[] = $productId;
        }
        
        if ($type) {
            $sql .= " AND sm.type = ?";
            $params[] = $type;
        }
        
        if ($startDate) {
            $sql .= " AND DATE(sm.created_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(sm.created_at) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY sm.created_at DESC LIMIT 500";
        
        $movements = $this->db->query($sql, $params);
        $products = $this->db->query("SELECT id, code, name FROM products WHERE status = 1 ORDER BY name");
        
        include __DIR__ . '/views/movements.php';
    }

    /**
     * Show stock adjustment form
     */
    public function adjustForm() {
        $this->auth->requireLogin();
        
        $id = $_GET['id'] ?? null;
        $product = null;
        
        if ($id) {
            $sql = "SELECT p.*, c.name as category_name, u.name as unit_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN units u ON p.unit_id = u.id
                    WHERE p.id = ?";
            $product = $this->db->queryOne($sql, [$id]);
        }
        
        $products = $this->db->query("SELECT id, code, name, current_stock FROM products WHERE status = 1 ORDER BY name");
        
        include __DIR__ . '/views/adjustment-form.php';
    }

    /**
     * Save stock adjustment
     */
    public function adjustSave() {
        $this->auth->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        $productId = $_POST['product_id'] ?? null;
        $newQuantity = Helpers::toDecimal($_POST['new_quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$productId || $newQuantity < 0) {
            Response::error('Product and valid quantity required');
        }
        
        $product = $this->db->queryOne("SELECT * FROM products WHERE id = ?", [$productId]);
        if (!$product) {
            Response::error('Product not found');
        }
        
        $oldQuantity = $product['current_stock'];
        $difference = $newQuantity - $oldQuantity;
        
        try {
            $this->db->beginTransaction();
            
            // Update product stock
            $this->db->execute(
                "UPDATE products SET current_stock = ? WHERE id = ?",
                [$newQuantity, $productId]
            );
            
            // Log movement
            $this->db->execute(
                "INSERT INTO stock_movements (product_id, type, quantity, notes, created_by)
                 VALUES (?, 'adjustment', ?, ?, ?)",
                [$productId, $difference, $notes ?: $reason, $this->auth->userId()]
            );
            
            // Audit log
            AuditLog::record('update', 'products', $productId, 
                ['current_stock' => $oldQuantity], 
                ['current_stock' => $newQuantity]);
            
            $this->db->commit();
            
            // Low stock alert
            if ($newQuantity <= $product['min_stock']) {
                Notify::send(null, 'low_stock', 'Low stock: ' . $product['name'],
                    "Adjusted to {$newQuantity} (min: {$product['min_stock']})", 
                    'product', $productId);
            }
            
            Response::success('Stock adjusted successfully', ['url' => '?module=inventory&action=movements&product_id=' . $productId]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to adjust stock: ' . $e->getMessage());
        }
    }

    /**
     * Export stock report to CSV
     */
    public function exportCSV() {
        $this->auth->requireLogin();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="stock_summary_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'Code', 'Product Name', 'Type', 'Category', 'Unit',
            'Current Stock', 'Min Stock', 'Purchase Price', 'Sale Price', 'Status'
        ]);
        
        $products = $this->db->query("
            SELECT p.code, p.name, p.product_type, c.name as category, u.name as unit,
                   p.current_stock, p.min_stock, p.purchase_price, p.sale_price,
                   CASE WHEN p.status = 1 THEN 'Active' ELSE 'Inactive' END as status
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.status IN (0, 1)
            ORDER BY p.name
        ");
        
        foreach ($products as $p) {
            fputcsv($output, array_values($p));
        }
        
        fclose($output);
        exit;
    }
}
