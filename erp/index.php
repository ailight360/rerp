<?php
/**
 * ERP System - Main Router
 */

session_start();

// Simple routing based on query parameters
$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Allowed modules
$allowed_modules = [
    'dashboard', 'customers', 'vendors', 'products', 
    'stock_in', 'stock_out', 'inventory', 'quotations',
    'work_orders', 'billing', 'payments', 'due_collection',
    'reports', 'settings', 'notifications'
];

if (!in_array($module, $allowed_modules)) {
    http_response_code(404);
    die('Module not found');
}

// Check if user is logged in (except for login page)
if (!isset($_SESSION['user_id']) && $module !== 'login') {
    header('Location: /login.php');
    exit;
}

// Load controller
$controller_file = __DIR__ . "/modules/$module/" . ucfirst(str_replace('_', '', $module)) . "Controller.php";

if (!file_exists($controller_file)) {
    die("Controller not found for module: $module");
}

require_once $controller_file;

$controller_class = ucfirst(str_replace('_', '', $module)) . 'Controller';
$controller = new $controller_class();

if (!method_exists($controller, $action)) {
    die("Action '$action' not found in $controller_class");
}

// Execute action
if ($id !== null) {
    $controller->$action($id);
} else {
    $controller->$action();
}
