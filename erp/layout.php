<?php
/**
 * Main Layout Template
 * Used by all modules for consistent UI
 */

// Prevent direct access
if (!isset($GLOBALS['db'])) {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/config/session.php';
    require_once __DIR__ . '/core/Auth.php';
    require_once __DIR__ . '/core/Helpers.php';
}

$auth = new Auth();
$user = $auth->user();
$currentPage = $_GET['page'] ?? 'dashboard';

// Get unread notification count
$unreadCount = 0;
if ($auth->isLoggedIn()) {
    $stmt = DB::getInstance()->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user->id]);
    $unreadCount = $stmt->fetch(PDO::FETCH_OBJ)->count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#3b82f6">
    <meta name="description" content="ERP System - Business Management">
    <title><?= ucfirst(str_replace('_', ' ', $currentPage)) ?> | ERP</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/erp/manifest.json">
    <link rel="apple-touch-icon" href="/erp/assets/icons/icon-192.png">
    
    <!-- Chart.js (on demand) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/erp/assets/css/app.css">
</head>
<body>
    <?php if ($auth->isLoggedIn()): ?>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>🏢 ERP</h2>
        </div>
        
        <nav class="sidebar-nav">
            <a href="/erp/dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                📊 Dashboard
            </a>
            
            <div class="nav-section">Parties</div>
            <a href="/erp/customers" class="nav-item <?= $currentPage === 'customers' ? 'active' : '' ?>">
                👥 Customers
            </a>
            <a href="/erp/vendors" class="nav-item <?= $currentPage === 'vendors' ? 'active' : '' ?>">
                🏭 Vendors
            </a>
            
            <div class="nav-section">Inventory</div>
            <a href="/erp/products" class="nav-item <?= $currentPage === 'products' ? 'active' : '' ?>">
                📦 Products
            </a>
            <a href="/erp/stock_in" class="nav-item <?= $currentPage === 'stock_in' ? 'active' : '' ?>">
                📥 Stock In
            </a>
            <a href="/erp/stock_out" class="nav-item <?= $currentPage === 'stock_out' ? 'active' : '' ?>">
                📤 Stock Out
            </a>
            <a href="/erp/inventory" class="nav-item <?= $currentPage === 'inventory' ? 'active' : '' ?>">
                📋 Inventory
            </a>
            
            <div class="nav-section">Sales</div>
            <a href="/erp/quotations" class="nav-item <?= $currentPage === 'quotations' ? 'active' : '' ?>">
                📝 Quotations
            </a>
            <a href="/erp/billing" class="nav-item <?= $currentPage === 'billing' ? 'active' : '' ?>">
                💳 Billing
            </a>
            <a href="/erp/due_collection" class="nav-item <?= $currentPage === 'due_collection' ? 'active' : '' ?>">
                💰 Due Collection
            </a>
            
            <div class="nav-section">Operations</div>
            <a href="/erp/work_orders" class="nav-item <?= $currentPage === 'work_orders' ? 'active' : '' ?>">
                🔧 Work Orders
            </a>
            
            <div class="nav-section">Analysis</div>
            <a href="/erp/reports" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                📈 Reports
            </a>
            
            <?php if ($user->role === 'admin'): ?>
            <div class="nav-section">Admin</div>
            <a href="/erp/settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                ⚙️ Settings
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user->name, 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($user->name) ?></div>
                    <div class="user-role"><?= ucfirst($user->role) ?></div>
                </div>
            </div>
            <a href="/erp/logout" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
                <h1 class="page-title"><?= ucfirst(str_replace('_', ' ', $currentPage)) ?></h1>
            </div>
            
            <div class="header-right">
                <!-- Notifications -->
                <div class="notification-dropdown">
                    <button class="btn btn-icon notification-btn" onclick="toggleNotifications()">
                        🔔
                        <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-panel" id="notification-panel">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <button onclick="markAllRead()" class="btn btn-sm btn-link">Mark all read</button>
                        </div>
                        <div id="notification-list" class="notification-list">
                            <div class="text-center text-muted py-3">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="user-menu">
                    <span class="user-name"><?= htmlspecialchars($user->name) ?></span>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <main class="content">
            <?php endif; ?>
            
            <!-- Toast Container -->
            <div id="toast-container"></div>
            
            <!-- Modal Container -->
            <div id="modal-overlay" class="modal-overlay" style="display: none;">
                <div class="modal" id="modal-content">
                    <div class="modal-header">
                        <h3 id="modal-title">Modal Title</h3>
                        <button class="modal-close" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="modal-body" id="modal-body">
                        <!-- Dynamic content -->
                    </div>
                    <div class="modal-footer" id="modal-footer">
                        <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button class="btn btn-primary" id="modal-action">Save</button>
                    </div>
                </div>
            </div>
            
            <?php if ($auth->isLoggedIn()): ?>
        </main>
        
        <!-- Mobile Bottom Nav -->
        <nav class="mobile-bottom-nav">
            <a href="/erp/dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                📊
                <span>Home</span>
            </a>
            <a href="/erp/products" class="nav-item <?= $currentPage === 'products' ? 'active' : '' ?>">
                📦
                <span>Products</span>
            </a>
            <a href="/erp/stock_out" class="nav-item <?= $currentPage === 'stock_out' ? 'active' : '' ?>">
                💳
                <span>Sales</span>
            </a>
            <a href="/erp/reports" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                📈
                <span>Reports</span>
            </a>
        </nav>
    </div>
    
    <script src="/erp/assets/js/app.js"></script>
    <script>
        // Notification polling (60s)
        let notificationInterval;
        
        function startNotificationPolling() {
            loadNotifications();
            notificationInterval = setInterval(loadNotifications, 60000);
        }
        
        function loadNotifications() {
            fetch('/erp/api/notifications.php')
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('notification-list');
                    const badge = document.querySelector('.notification-badge');
                    
                    if (data.notifications && data.notifications.length > 0) {
                        list.innerHTML = data.notifications.map(n => `
                            <div class="notification-item ${n.is_read ? '' : 'unread'}" 
                                 onclick="markRead(${n.id})">
                                <div class="notification-title">${escapeHtml(n.title)}</div>
                                <div class="notification-message">${escapeHtml(n.message)}</div>
                                <div class="notification-time">${timeAgo(n.created_at)}</div>
                            </div>
                        `).join('');
                        
                        if (badge && data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'inline-block';
                        } else if (badge) {
                            badge.style.display = 'none';
                        }
                    } else {
                        list.innerHTML = '<div class="text-center text-muted py-3">No notifications</div>';
                        if (badge) badge.style.display = 'none';
                    }
                });
        }
        
        function toggleNotifications() {
            const panel = document.getElementById('notification-panel');
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        }
        
        function markRead(id) {
            fetch('/erp/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ action: 'mark_read', id: id })
            }).then(() => loadNotifications());
        }
        
        function markAllRead() {
            fetch('/erp/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ action: 'mark_all_read' })
            }).then(() => {
                loadNotifications();
                showToast('All notifications marked as read', 'success');
            });
        }
        
        // Start polling on page load
        document.addEventListener('DOMContentLoaded', startNotificationPolling);
    </script>
    <?php endif; ?>
</body>
</html>
