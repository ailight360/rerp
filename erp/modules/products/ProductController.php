<?php
/**
 * Products Controller - CRUD with Pair Support
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
                $code = trim($_POST['code'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $categoryId = intval($_POST['category_id'] ?? 0);
                $unitId = intval($_POST['unit_id'] ?? 0);
                $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
                $salePrice = floatval($_POST['sale_price'] ?? 0);
                $taxRateId = !empty($_POST['tax_rate_id']) ? intval($_POST['tax_rate_id']) : null;
                $productType = $_POST['product_type'] ?? 'single';
                $minStock = intval($_POST['min_stock'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                
                if (empty($code) || empty($name)) {
                    $errorMessage = 'Product code and name are required.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Insert product
                        $stmt = $pdo->prepare("INSERT INTO products (code, name, category_id, unit_id, purchase_price, sale_price, tax_rate_id, product_type, min_stock, description, created_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$code, $name, $categoryId, $unitId, $purchasePrice, $salePrice, $taxRateId, $productType, $minStock, $description]);
                        $productId = $pdo->lastInsertId();
                        
                        // If pair product, insert pair components
                        if ($productType === 'pair') {
                            $componentA = intval($_POST['component_a_id'] ?? 0);
                            $componentB = intval($_POST['component_b_id'] ?? 0);
                            
                            if ($componentA <= 0 || $componentB <= 0) {
                                throw new Exception('Both components are required for pair products.');
                            }
                            
                            $stmt = $pdo->prepare("INSERT INTO product_pairs (pair_product_id, component_a_id, component_b_id, created_at) 
                                                  VALUES (?, ?, ?, NOW())");
                            $stmt->execute([$productId, $componentA, $componentB]);
                        }
                        
                        $pdo->commit();
                        
                        // Audit log
                        $audit = new AuditLog();
                        $audit->log($userId, 'create', 'products', $productId, null, ['code' => $code, 'name' => $name], Helpers::getClientIP());
                        
                        $successMessage = 'Product created successfully!';
                        $action = 'list';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $errorMessage = 'Error creating product: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update':
                $id = intval($_POST['id'] ?? 0);
                $code = trim($_POST['code'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $categoryId = intval($_POST['category_id'] ?? 0);
                $unitId = intval($_POST['unit_id'] ?? 0);
                $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
                $salePrice = floatval($_POST['sale_price'] ?? 0);
                $taxRateId = !empty($_POST['tax_rate_id']) ? intval($_POST['tax_rate_id']) : null;
                $minStock = intval($_POST['min_stock'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                
                if ($id <= 0 || empty($code) || empty($name)) {
                    $errorMessage = 'Invalid product data.';
                } else {
                    try {
                        // Get old values
                        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                        $stmt->execute([$id]);
                        $oldValues = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->prepare("UPDATE products SET 
                                              code = ?, name = ?, category_id = ?, unit_id = ?, 
                                              purchase_price = ?, sale_price = ?, tax_rate_id = ?, 
                                              min_stock = ?, description = ? 
                                              WHERE id = ?");
                        $stmt->execute([$code, $name, $categoryId, $unitId, $purchasePrice, $salePrice, $taxRateId, $minStock, $description, $id]);
                        
                        // Audit log
                        $audit = new AuditLog();
                        $audit->log($userId, 'update', 'products', $id, $oldValues, ['code' => $code, 'name' => $name], Helpers::getClientIP());
                        
                        $successMessage = 'Product updated successfully!';
                        $action = 'list';
                    } catch (Exception $e) {
                        $errorMessage = 'Error updating product: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $errorMessage = 'Invalid product ID.';
                } else {
                    try {
                        // Check if has transactions
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_in_items WHERE product_id = ?");
                        $stmt->execute([$id]);
                        if ($stmt->fetchColumn() > 0) {
                            $errorMessage = 'Cannot delete product with existing transactions. Set status to inactive instead.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE products SET status = 0 WHERE id = ?");
                            $stmt->execute([$id]);
                            
                            $audit = new AuditLog();
                            $audit->log($userId, 'delete', 'products', $id, null, null, Helpers::getClientIP());
                            
                            $successMessage = 'Product deleted successfully!';
                        }
                        $action = 'list';
                    } catch (Exception $e) {
                        $errorMessage = 'Error deleting product: ' . $e->getMessage();
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
    
    $where = ['p.status = 1'];
    $params = [];
    
    if ($search) {
        $where[] = '(p.name LIKE ? OR p.code LIKE ?)';
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam];
    }
    
    $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS p.*, c.name as category_name, u.short_name as unit_name,
                                  CASE WHEN p.product_type = 'pair' THEN 
                                       (SELECT MIN(cp.current_stock) 
                                        FROM product_pairs pp 
                                        JOIN products cp ON (cp.id = pp.component_a_id OR cp.id = pp.component_b_id) 
                                        WHERE pp.pair_product_id = p.id)
                                  ELSE p.current_stock END as available_stock
                           FROM products p
                           LEFT JOIN categories c ON p.category_id = c.id
                           LEFT JOIN units u ON p.unit_id = u.id
                           WHERE " . implode(' AND ', $where) . "
                           ORDER BY p.created_at DESC
                           LIMIT $offset, $perPage");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT FOUND_ROWS()");
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
    
    $pageTitle = 'Products';
    $currentPage = 'products';
    include __DIR__ . '/views/list.php';
    exit;
}

// Form view (create/edit)
if ($action === 'create' || $action === 'edit') {
    $product = null;
    $categories = [];
    $units = [];
    $taxRates = [];
    $singleProducts = [];
    
    // Get dropdown options
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM units ORDER BY name");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM tax_rates WHERE status = 1 ORDER BY name");
    $taxRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get single products for pair components
    $stmt = $pdo->query("SELECT id, code, name FROM products WHERE product_type = 'single' AND status = 1 ORDER BY name");
    $singleProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($action === 'edit') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            header('Location: /products');
            exit;
        }
        
        // Get pair components if pair product
        if ($product['product_type'] === 'pair') {
            $stmt = $pdo->prepare("SELECT * FROM product_pairs WHERE pair_product_id = ?");
            $stmt->execute([$id]);
            $product['pair'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    $pageTitle = $action === 'create' ? 'New Product' : 'Edit Product';
    $currentPage = 'products';
    include __DIR__ . '/views/form.php';
    exit;
}
