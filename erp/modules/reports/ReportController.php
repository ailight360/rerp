<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

Auth::requireLogin();

class ReportController {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Sales Report
     * Filters: Date range, Customer, Status
     * Metrics: Total sales, tax, discount, gross margin
     */
    public function sales() {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $customer_id = $_GET['customer_id'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $sql = "SELECT 
                    so.id, so.invoice_no, so.date, so.type, so.status,
                    c.name as customer_name,
                    SUM(soi.total) as subtotal,
                    SUM(so.tax) as tax_amount,
                    SUM(so.discount) as discount_amount,
                    SUM(so.total) as total_amount,
                    so.paid, so.due
                FROM stock_out so
                LEFT JOIN customers c ON so.customer_id = c.id
                LEFT JOIN stock_out_items soi ON so.id = soi.stock_out_id
                WHERE so.date BETWEEN :start AND :end
                AND so.type = 'sale'
                AND so.status != 'cancelled'";
        
        $params = [':start' => $start_date, ':end' => $end_date];
        
        if ($customer_id) {
            $sql .= " AND so.customer_id = :customer_id";
            $params[':customer_id'] = $customer_id;
        }
        
        if ($status) {
            $sql .= " AND so.status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " GROUP BY so.id ORDER BY so.date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $grand_total = array_sum(array_column($sales, 'total_amount'));
        $grand_paid = array_sum(array_column($sales, 'paid'));
        $grand_due = array_sum(array_column($sales, 'due'));
        
        // Get customers for filter
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll();
        
        include __DIR__ . '/views/sales-report.php';
    }

    /**
     * Purchase Report
     * Filters: Date range, Vendor, Status
     */
    public function purchases() {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $vendor_id = $_GET['vendor_id'] ?? '';
        
        $sql = "SELECT 
                    si.id, si.invoice_no, si.date, si.status,
                    v.name as vendor_name,
                    SUM(sii.total) as subtotal,
                    SUM(si.tax) as tax_amount,
                    SUM(si.total) as total_amount,
                    si.paid, si.due
                FROM stock_in si
                LEFT JOIN vendors v ON si.vendor_id = v.id
                LEFT JOIN stock_in_items sii ON si.id = sii.stock_in_id
                WHERE si.date BETWEEN :start AND :end
                AND si.status != 'cancelled'";
        
        $params = [':start' => $start_date, ':end' => $end_date];
        
        if ($vendor_id) {
            $sql .= " AND si.vendor_id = :vendor_id";
            $params[':vendor_id'] = $vendor_id;
        }
        
        $sql .= " GROUP BY si.id ORDER BY si.date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $grand_total = array_sum(array_column($purchases, 'total_amount'));
        $grand_paid = array_sum(array_column($purchases, 'paid'));
        $grand_due = array_sum(array_column($purchases, 'due'));
        
        $vendors = $this->db->query("SELECT id, name FROM vendors WHERE status = 1 ORDER BY name")->fetchAll();
        
        include __DIR__ . '/views/purchase-report.php';
    }

    /**
     * Stock Report
     * Shows current stock, values, pair availability, low stock alerts
     */
    public function stock() {
        $category_id = $_GET['category_id'] ?? '';
        $show_low_stock = $_GET['show_low_stock'] ?? 0;
        
        $sql = "SELECT 
                    p.id, p.code, p.name, p.product_type,
                    c.name as category_name,
                    u.name as unit_name,
                    p.current_stock,
                    p.purchase_price,
                    p.sale_price,
                    (p.current_stock * p.purchase_price) as stock_value,
                    p.min_stock,
                    CASE WHEN p.current_stock <= p.min_stock THEN 1 ELSE 0 END as is_low_stock,
                    pp.component_a_id, pp.component_b_id,
                    pa.name as component_a_name,
                    pb.name as component_b_name,
                    LEAST(pa.current_stock, pb.current_stock) as pair_available
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN units u ON p.unit_id = u.id
                LEFT JOIN product_pairs pp ON p.id = pp.pair_product_id
                LEFT JOIN products pa ON pp.component_a_id = pa.id
                LEFT JOIN products pb ON pp.component_b_id = pb.id
                WHERE p.status = 1";
        
        $params = [];
        
        if ($category_id) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        
        if ($show_low_stock) {
            $sql .= " AND p.current_stock <= p.min_stock";
        }
        
        $sql .= " ORDER BY p.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $categories = $this->db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        
        include __DIR__ . '/views/stock-report.php';
    }

    /**
     * Profit & Loss (Gross Margin)
     * Calculates: Billed Total - Cost of Goods Sold
     */
    public function profitLoss() {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        
        // Revenue: Total from billed stock_out (sales)
        $revenue_sql = "SELECT 
                            SUM(so.total) as total_revenue,
                            COUNT(DISTINCT so.id) as sale_count
                        FROM stock_out so
                        WHERE so.date BETWEEN :start AND :end
                        AND so.type = 'sale'
                        AND so.status IN ('confirmed', 'delivered')";
        
        $stmt = $this->db->prepare($revenue_sql);
        $stmt->execute([':start' => $start_date, ':end' => $end_date]);
        $revenue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // COGS: Purchase cost of sold items
        // Join stock_out_items with products to get purchase_price
        $cogs_sql = "SELECT 
                        SUM(soi.quantity * p.purchase_price) as total_cogs
                    FROM stock_out_items soi
                    JOIN stock_out so ON soi.stock_out_id = so.id
                    JOIN products p ON soi.product_id = p.id
                    WHERE so.date BETWEEN :start AND :end
                    AND so.type = 'sale'
                    AND so.status IN ('confirmed', 'delivered')";
        
        $stmt = $this->db->prepare($cogs_sql);
        $stmt->execute([':start' => $start_date, ':end' => $end_date]);
        $cogs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $gross_profit = ($revenue['total_revenue'] ?? 0) - ($cogs['total_cogs'] ?? 0);
        $margin_percent = ($revenue['total_revenue'] > 0) 
            ? ($gross_profit / $revenue['total_revenue']) * 100 
            : 0;
        
        // Expense summary (if expense module existed, would subtract here)
        // For now, just showing Gross Margin
        
        include __DIR__ . '/views/profit-loss-report.php';
    }

    /**
     * Ledger Report (Customer or Vendor)
     */
    public function ledger() {
        $party_type = $_GET['party_type'] ?? 'customer'; // customer or vendor
        $party_id = $_GET['party_id'] ?? '';
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        
        if (!$party_id) {
            // Show list of parties to select
            if ($party_type === 'customer') {
                $parties = $this->db->query("SELECT id, name, opening_balance, balance_type FROM customers WHERE status = 1 ORDER BY name")->fetchAll();
            } else {
                $parties = $this->db->query("SELECT id, name, opening_balance, balance_type FROM vendors WHERE status = 1 ORDER BY name")->fetchAll();
            }
            include __DIR__ . '/views/ledger-select.php';
            return;
        }
        
        // Get party details
        $table = $party_type === 'customer' ? 'customers' : 'vendors';
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->execute([':id' => $party_id]);
        $party = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get ledger entries
        $sql = "SELECT * FROM ledger_entries 
                WHERE party_type = :type 
                AND party_id = :id 
                AND date BETWEEN :start AND :end 
                ORDER BY date ASC, id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':type' => $party_type,
            ':id' => $party_id,
            ':start' => $start_date,
            ':end' => $end_date
        ]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate running balance
        $balance = $party['opening_balance'] ?? 0;
        if ($party['balance_type'] === 'credit') {
            $balance = -$balance;
        }
        
        foreach ($entries as &$entry) {
            $balance += ($entry['debit'] - $entry['credit']);
            $entry['running_balance'] = $balance;
        }
        
        include __DIR__ . '/views/ledger-detail.php';
    }

    /**
     * Export to CSV
     */
    public function exportCSV($type, $data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');
        
        if (empty($data)) {
            echo "No data";
            exit;
        }
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

// Router
$action = $_GET['action'] ?? 'sales';
$controller = new ReportController();

switch ($action) {
    case 'sales':
        $controller->sales();
        break;
    case 'purchases':
        $controller->purchases();
        break;
    case 'stock':
        $controller->stock();
        break;
    case 'profit_loss':
        $controller->profitLoss();
        break;
    case 'ledger':
        $controller->ledger();
        break;
    case 'export':
        // Handle export logic based on type
        break;
    default:
        $controller->sales();
}
