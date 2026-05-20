<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

Auth::requireLogin();
Auth::requireRole('admin'); // Only admins can access settings

class SettingsController {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Company Settings
     */
    public function company() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->saveCompanySettings();
            return;
        }
        
        $settings = $this->getSettings();
        include __DIR__ . '/views/company-settings.php';
    }

    /**
     * Tax Rates CRUD
     */
    public function taxRates() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                $this->createTaxRate();
            } elseif ($action === 'update') {
                $this->updateTaxRate();
            } elseif ($action === 'delete') {
                $this->deleteTaxRate();
            }
            return;
        }
        
        $tax_rates = $this->db->query("SELECT * FROM tax_rates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/views/tax-rates.php';
    }

    /**
     * Units CRUD
     */
    public function units() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                $this->createUnit();
            } elseif ($action === 'update') {
                $this->updateUnit();
            } elseif ($action === 'delete') {
                $this->deleteUnit();
            }
            return;
        }
        
        $units = $this->db->query("SELECT * FROM units ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/views/units.php';
    }

    /**
     * Users CRUD (Admin only)
     */
    public function users() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'create';
            if ($action === 'create') {
                $this->createUser();
            } elseif ($action === 'update') {
                $this->updateUser();
            } elseif ($action === 'delete') {
                $this->deleteUser();
            } elseif ($action === 'toggle_status') {
                $this->toggleUserStatus();
            }
            return;
        }
        
        $users = $this->db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/views/users.php';
    }

    /**
     * Audit Log Viewer (Admin only)
     */
    public function auditLog() {
        $user_id = $_GET['user_id'] ?? '';
        $action = $_GET['action_filter'] ?? '';
        $table_name = $_GET['table_name'] ?? '';
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_log al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($user_id) {
            $sql .= " AND al.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        if ($action) {
            $sql .= " AND al.action = :action";
            $params[':action'] = $action;
        }
        
        if ($table_name) {
            $sql .= " AND al.table_name = :table_name";
            $params[':table_name'] = $table_name;
        }
        
        $sql .= " AND al.created_at BETWEEN :start AND :end";
        $params[':start'] = $start_date . ' 00:00:00';
        $params[':end'] = $end_date . ' 23:59:59';
        
        $sql .= " ORDER BY al.created_at DESC LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $users = $this->db->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
        
        include __DIR__ . '/views/audit-log.php';
    }

    /**
     * Invoice Prefixes & Numbering
     */
    public function invoicing() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->saveInvoicingSettings();
            return;
        }
        
        $settings = $this->getSettings();
        include __DIR__ . '/views/invoicing-settings.php';
    }

    // ========== Helper Methods ==========

    private function getSettings() {
        $stmt = $this->db->query("SELECT key_name, key_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key_name']] = $row['key_value'];
        }
        return $settings;
    }

    private function saveCompanySettings() {
        $fields = ['company_name', 'company_address', 'company_phone', 'company_email', 
                   'company_logo', 'currency', 'timezone', 'tax_number'];
        
        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            
            // Handle logo upload
            if ($field === 'company_logo' && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $value = $this->uploadLogo($_FILES['logo']);
            }
            
            $stmt = $this->db->prepare("INSERT INTO settings (key_name, key_value) VALUES (:key, :val) 
                                        ON DUPLICATE KEY UPDATE key_value = :val2");
            $stmt->execute([':key' => $field, ':val' => $value, ':val2' => $value]);
        }
        
        Helpers::redirect('?module=settings&action=company&success=1');
    }

    private function uploadLogo($file) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            die('Invalid file type');
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        $path = __DIR__ . '/../../uploads/company/' . $filename;
        
        move_uploaded_file($file['tmp_name'], $path);
        return '/uploads/company/' . $filename;
    }

    private function createTaxRate() {
        $stmt = $this->db->prepare("INSERT INTO tax_rates (name, rate, is_default, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['rate'],
            $_POST['is_default'] ?? 0,
            1
        ]);
        Helpers::redirect('?module=settings&action=tax_rates');
    }

    private function updateTaxRate() {
        $stmt = $this->db->prepare("UPDATE tax_rates SET name=?, rate=?, is_default=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['rate'], $_POST['is_default'] ?? 0, $_POST['id']]);
        Helpers::redirect('?module=settings&action=tax_rates');
    }

    private function deleteTaxRate() {
        $stmt = $this->db->prepare("DELETE FROM tax_rates WHERE id=?");
        $stmt->execute([$_POST['id']]);
        Helpers::redirect('?module=settings&action=tax_rates');
    }

    private function createUnit() {
        $stmt = $this->db->prepare("INSERT INTO units (name, short_name) VALUES (?, ?)");
        $stmt->execute([$_POST['name'], $_POST['short_name']]);
        Helpers::redirect('?module=settings&action=units');
    }

    private function updateUnit() {
        $stmt = $this->db->prepare("UPDATE units SET name=?, short_name=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['short_name'], $_POST['id']]);
        Helpers::redirect('?module=settings&action=units');
    }

    private function deleteUnit() {
        $stmt = $this->db->prepare("DELETE FROM units WHERE id=?");
        $stmt->execute([$_POST['id']]);
        Helpers::redirect('?module=settings&action=units');
    }

    private function createUser() {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $password_hash,
            $_POST['role'],
            1
        ]);
        Helpers::redirect('?module=settings&action=users');
    }

    private function updateUser() {
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
            $stmt->execute([$_POST['name'], $_POST['email'], $_POST['role'], $password_hash, $_POST['id']]);
        } else {
            $stmt = $this->db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
            $stmt->execute([$_POST['name'], $_POST['email'], $_POST['role'], $_POST['id']]);
        }
        Helpers::redirect('?module=settings&action=users');
    }

    private function deleteUser() {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$_POST['id']]);
        Helpers::redirect('?module=settings&action=users');
    }

    private function toggleUserStatus() {
        $stmt = $this->db->prepare("UPDATE users SET status = 1 - status WHERE id=?");
        $stmt->execute([$_POST['id']]);
        Helpers::redirect('?module=settings&action=users');
    }

    private function saveInvoicingSettings() {
        $fields = ['invoice_prefix_stock_in', 'invoice_prefix_stock_out', 
                   'invoice_prefix_bill', 'invoice_prefix_quote', 'invoice_prefix_wo'];
        
        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            $stmt = $this->db->prepare("INSERT INTO settings (key_name, key_value) VALUES (:key, :val) 
                                        ON DUPLICATE KEY UPDATE key_value = :val2");
            $stmt->execute([':key' => $field, ':val' => $value, ':val2' => $value]);
        }
        
        Helpers::redirect('?module=settings&action=invoicing&success=1');
    }
}

// Router
$action = $_GET['action'] ?? 'company';
$controller = new SettingsController();

switch ($action) {
    case 'company':
        $controller->company();
        break;
    case 'tax_rates':
        $controller->taxRates();
        break;
    case 'units':
        $controller->units();
        break;
    case 'users':
        $controller->users();
        break;
    case 'audit_log':
        $controller->auditLog();
        break;
    case 'invoicing':
        $controller->invoicing();
        break;
    default:
        $controller->company();
}
