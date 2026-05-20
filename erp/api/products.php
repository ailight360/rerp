<?php
/**
 * Products API - Search & Stock Lookup
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pdo = Database::getInstance();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'search';

// Search products by code or name
if ($action === 'search') {
    $query = trim($_GET['q'] ?? '');
    $code = trim($_GET['code'] ?? '');
    
    $where = ['p.status = 1'];
    $params = [];
    
    if ($code) {
        $where[] = 'p.code = ?';
        $params[] = $code;
    } elseif ($query) {
        $where[] = '(p.name LIKE ? OR p.code LIKE ?)';
        $params[] = "%$query%";
        $params[] = "%$query%";
    }
    
    $sql = "SELECT p.*, c.name as category_name, u.short_name as unit_name,
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
            ORDER BY p.name
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For pair products, get component info
    foreach ($products as &$product) {
        if ($product['product_type'] === 'pair') {
            $stmt = $pdo->prepare("SELECT pp.*, ca.name as component_a_name, cb.name as component_b_name
                                   FROM product_pairs pp
                                   JOIN products ca ON pp.component_a_id = ca.id
                                   JOIN products cb ON pp.component_b_id = cb.id
                                   WHERE pp.pair_product_id = ?");
            $stmt->execute([$product['id']]);
            $pairInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pairInfo) {
                $product['pair_info'] = $pairInfo;
            }
        }
        $product['available_stock'] = floatval($product['available_stock'] ?? 0);
    }
    
    echo json_encode($products);
    
} elseif ($action === 'stock') {
    // Get current stock for a specific product
    $productId = intval($_GET['id'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT p.*, 
                                  CASE WHEN p.product_type = 'pair' THEN 
                                       (SELECT MIN(cp.current_stock) 
                                        FROM product_pairs pp 
                                        JOIN products cp ON (cp.id = pp.component_a_id OR cp.id = pp.component_b_id) 
                                        WHERE pp.pair_product_id = p.id)
                                  ELSE p.current_stock END as available_stock
                           FROM products p
                           WHERE p.id = ? AND p.status = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    echo json_encode($product);
    
} else {
    echo json_encode(['error' => 'Invalid action']);
}
