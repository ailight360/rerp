<?php
/**
 * Due Collection Controller
 * Handles customer receivables and vendor payables with aging analysis
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Response.php';

Auth::requireLogin();

class DueController {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }
    
    /**
     * Customer receivables list
     */
    public function customers() {
        $sql = "SELECT c.id, c.name, c.phone, 
                COALESCE(SUM(CASE WHEN type = 'sale' OR reference_type = 'bill' THEN due ELSE 0 END), 0) as total_due,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), date) <= 30 THEN due ELSE 0 END), 0) as current_30,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), date) BETWEEN 31 AND 60 THEN due ELSE 0 END), 0) as days_31_60,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), date) BETWEEN 61 AND 90 THEN due ELSE 0 END), 0) as days_61_90,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), date) > 90 THEN due ELSE 0 END), 0) as days_90_plus
                FROM customers c
                LEFT JOIN stock_out so ON c.id = so.customer_id AND so.due > 0
                LEFT JOIN bills b ON c.id = b.customer_id AND b.due > 0 AND b.reference_type IS NULL
                WHERE c.status = 1
                GROUP BY c.id
                HAVING total_due > 0
                ORDER BY total_due DESC";
        
        $customers = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/views/customers-due.php';
    }
    
    /**
     * Vendor payables list
     */
    public function vendors() {
        $sql = "SELECT v.id, v.name, v.phone,
                COALESCE(SUM(si.due), 0) as total_due,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) <= 30 THEN si.due ELSE 0 END), 0) as current_30,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) BETWEEN 31 AND 60 THEN si.due ELSE 0 END), 0) as days_31_60,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) BETWEEN 61 AND 90 THEN si.due ELSE 0 END), 0) as days_61_90,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) > 90 THEN si.due ELSE 0 END), 0) as days_90_plus
                FROM vendors v
                LEFT JOIN stock_in si ON v.id = si.vendor_id AND si.due > 0
                WHERE v.status = 1
                GROUP BY v.id
                HAVING total_due > 0
                ORDER BY total_due DESC";
        
        $vendors = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/views/vendors-due.php';
    }
    
    /**
     * Aging analysis report
     */
    public function aging() {
        $type = $_GET['type'] ?? 'customer'; // customer or vendor
        
        if ($type === 'customer') {
            $sql = "SELECT 'customer' as party_type, c.id, c.name,
                    SUM(so.due) as total_due,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), so.date) <= 30 THEN so.due ELSE 0 END) as bucket_current,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), so.date) BETWEEN 31 AND 60 THEN so.due ELSE 0 END) as bucket_30,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), so.date) BETWEEN 61 AND 90 THEN so.due ELSE 0 END) as bucket_60,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), so.date) BETWEEN 91 AND 120 THEN so.due ELSE 0 END) as bucket_90,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), so.date) > 120 THEN so.due ELSE 0 END) as bucket_120_plus
                    FROM customers c
                    JOIN stock_out so ON c.id = so.customer_id
                    WHERE so.due > 0 AND c.status = 1
                    GROUP BY c.id
                    
                    UNION ALL
                    
                    SELECT 'customer', c.id, c.name,
                    SUM(b.due), 
                    SUM(CASE WHEN DATEDIFF(CURDATE(), b.date) <= 30 THEN b.due ELSE 0 END),
                    SUM(CASE WHEN DATEDIFF(CURDATE(), b.date) BETWEEN 31 AND 60 THEN b.due ELSE 0 END),
                    SUM(CASE WHEN DATEDIFF(CURDATE(), b.date) BETWEEN 61 AND 90 THEN b.due ELSE 0 END),
                    SUM(CASE WHEN DATEDIFF(CURDATE(), b.date) BETWEEN 91 AND 120 THEN b.due ELSE 0 END),
                    SUM(CASE WHEN DATEDIFF(CURDATE(), b.date) > 120 THEN b.due ELSE 0 END)
                    FROM customers c
                    JOIN bills b ON c.id = b.customer_id
                    WHERE b.due > 0 AND b.status != 'cancelled'
                    GROUP BY c.id";
        } else {
            $sql = "SELECT 'vendor' as party_type, v.id, v.name,
                    SUM(si.due) as total_due,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) <= 30 THEN si.due ELSE 0 END) as bucket_current,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) BETWEEN 31 AND 60 THEN si.due ELSE 0 END) as bucket_30,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) BETWEEN 61 AND 90 THEN si.due ELSE 0 END) as bucket_60,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) BETWEEN 91 AND 120 THEN si.due ELSE 0 END) as bucket_90,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), si.date) > 120 THEN si.due ELSE 0 END) as bucket_120_plus
                    FROM vendors v
                    JOIN stock_in si ON v.id = si.vendor_id
                    WHERE si.due > 0 AND v.status = 1
                    GROUP BY v.id";
        }
        
        $aging = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/views/aging.php';
    }
}

$controller = new DueController();
$action = $_GET['action'] ?? 'customers';

switch ($action) {
    case 'customers':
        $controller->customers();
        break;
    case 'vendors':
        $controller->vendors();
        break;
    case 'aging':
        $controller->aging();
        break;
    default:
        $controller->customers();
}
