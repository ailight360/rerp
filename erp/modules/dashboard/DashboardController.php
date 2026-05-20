<?php
/**
 * Dashboard Controller
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/Helpers.php';

$pdo = Database::getInstance();
$userRole = $_SESSION['user_role'];

// Get dashboard stats
$stats = [];

// Total customers
$stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 1");
$stats['total_customers'] = $stmt->fetchColumn();

// Total vendors
$stmt = $pdo->query("SELECT COUNT(*) FROM vendors WHERE status = 1");
$stats['total_vendors'] = $stmt->fetchColumn();

// Total products
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 1");
$stats['total_products'] = $stmt->fetchColumn();

// Low stock products
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock AND status = 1");
$stats['low_stock'] = $stmt->fetchColumn();

// Total sales this month
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM stock_out 
                       WHERE MONTH(date) = MONTH(CURRENT_DATE()) 
                       AND YEAR(date) = YEAR(CURRENT_DATE()) 
                       AND status != 'cancelled'");
$stmt->execute();
$stats['monthly_sales'] = $stmt->fetchColumn();

// Total purchases this month
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM stock_in 
                       WHERE MONTH(date) = MONTH(CURRENT_DATE()) 
                       AND YEAR(date) = YEAR(CURRENT_DATE()) 
                       AND status != 'cancelled'");
$stmt->execute();
$stats['monthly_purchases'] = $stmt->fetchColumn();

// Overdue bills
$stmt = $pdo->query("SELECT COUNT(*) FROM bills WHERE due_date < CURRENT_DATE() AND status != 'paid' AND status != 'cancelled'");
$stats['overdue_bills'] = $stmt->fetchColumn();

// Total receivables
$stmt = $pdo->query("SELECT COALESCE(SUM(due), 0) FROM bills WHERE status != 'paid' AND status != 'cancelled'");
$stats['total_receivables'] = $stmt->fetchColumn();

// Recent stock out transactions
$stmt = $pdo->query("SELECT so.*, c.name as customer_name 
                     FROM stock_out so 
                     LEFT JOIN customers c ON so.customer_id = c.id 
                     ORDER BY so.created_at DESC LIMIT 5");
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products
$stmt = $pdo->query("SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.current_stock <= p.min_stock AND p.status = 1 
                     ORDER BY p.current_stock ASC LIMIT 5");
$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
include __DIR__ . '/views/dashboard.php';
