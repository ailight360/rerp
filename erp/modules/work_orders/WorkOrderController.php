<?php
/**
 * Work Orders Controller
 * Handles work order CRUD, tasks, kanban board, and timeline
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/AuditLog.php';
require_once __DIR__ . '/../../core/Response.php';

Auth::requireLogin();

class WorkOrderController {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }
    
    /**
     * List all work orders with filters
     */
    public function index() {
        $status = $_GET['status'] ?? '';
        $priority = $_GET['priority'] ?? '';
        $customer_id = $_GET['customer_id'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = ['1=1'];
        $params = [];
        
        if ($status) {
            $where[] = 'wo.status = ?';
            $params[] = $status;
        }
        if ($priority) {
            $where[] = 'wo.priority = ?';
            $params[] = $priority;
        }
        if ($customer_id) {
            $where[] = 'wo.customer_id = ?';
            $params[] = $customer_id;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get work orders
        $sql = "SELECT wo.*, c.name as customer_name, u.name as assigned_to_name
                FROM work_orders wo
                LEFT JOIN customers c ON wo.customer_id = c.id
                LEFT JOIN users u ON wo.assigned_to = u.id
                WHERE $whereClause
                ORDER BY wo.created_at DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM work_orders wo WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get customers for filter
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll();
        
        include __DIR__ . '/views/list.php';
    }
    
    /**
     * Show create/edit form
     */
    public function form($id = null) {
        $workOrder = null;
        $tasks = [];
        
        if ($id) {
            $stmt = $this->db->prepare("SELECT wo.*, c.name as customer_name 
                                        FROM work_orders wo
                                        LEFT JOIN customers c ON wo.customer_id = c.id
                                        WHERE wo.id = ?");
            $stmt->execute([$id]);
            $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$workOrder) {
                Response::redirect('/work-orders', 'Work order not found');
            }
            
            // Get tasks
            $stmt = $this->db->prepare("SELECT t.*, u.name as assigned_to_name 
                                        FROM work_order_tasks t
                                        LEFT JOIN users u ON t.assigned_to = u.id
                                        WHERE t.work_order_id = ?
                                        ORDER BY t.created_at");
            $stmt->execute([$id]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get customers
        $customers = $this->db->query("SELECT id, name FROM customers WHERE status = 1 ORDER BY name")->fetchAll();
        
        // Get users for assignment
        $users = $this->db->query("SELECT id, name FROM users WHERE status = 1 ORDER BY name")->fetchAll();
        
        include __DIR__ . '/views/form.php';
    }
    
    /**
     * Save work order (create or update)
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        $id = $_POST['id'] ?? null;
        $wo_no = $_POST['wo_no'] ?? '';
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'pending';
        $assigned_to = (int)($_POST['assigned_to'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$title || !$customer_id) {
            Response::error('Title and customer are required');
        }
        
        try {
            $this->db->beginTransaction();
            
            if ($id) {
                // Update existing
                $sql = "UPDATE work_orders SET 
                        wo_no = ?, customer_id = ?, title = ?, description = ?,
                        start_date = ?, due_date = ?, priority = ?, status = ?,
                        assigned_to = ?, notes = ?
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $wo_no, $customer_id, $title, $description,
                    $start_date, $due_date, $priority, $status,
                    $assigned_to, $notes, $id
                ]);
                
                AuditLog::log('update', 'work_orders', $id, null, [
                    'title' => $title, 'status' => $status, 'priority' => $priority
                ]);
                
                // Log timeline if status changed
                $oldStatus = $_POST['old_status'] ?? '';
                if ($oldStatus && $oldStatus !== $status) {
                    $this->logTimeline($id, "Status changed from $oldStatus to $status");
                }
                
            } else {
                // Create new
                if (!$wo_no) {
                    $wo_no = 'WO-' . date('Y') . '-' . str_pad($this->getNextNumber(), 4, '0', STR_PAD_LEFT);
                }
                
                $sql = "INSERT INTO work_orders 
                        (wo_no, customer_id, title, description, start_date, due_date, 
                         priority, status, assigned_to, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $wo_no, $customer_id, $title, $description,
                    $start_date, $due_date, $priority, $status,
                    $assigned_to, $notes, Auth::getId()
                ]);
                
                $id = $this->db->lastInsertId();
                
                AuditLog::log('create', 'work_orders', $id, null, [
                    'wo_no' => $wo_no, 'title' => $title
                ]);
                
                $this->logTimeline($id, "Work order created");
            }
            
            $this->db->commit();
            Response::redirect('/work-orders', 'Work order saved successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to save work order: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete work order
     */
    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        // Check if exists
        $stmt = $this->db->prepare("SELECT * FROM work_orders WHERE id = ?");
        $stmt->execute([$id]);
        $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$workOrder) {
            Response::error('Work order not found');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Delete tasks first (cascade should handle this, but being explicit)
            $stmt = $this->db->prepare("DELETE FROM work_order_tasks WHERE work_order_id = ?");
            $stmt->execute([$id]);
            
            // Delete timeline
            $stmt = $this->db->prepare("DELETE FROM work_order_timeline WHERE work_order_id = ?");
            $stmt->execute([$id]);
            
            // Delete work order
            $stmt = $this->db->prepare("DELETE FROM work_orders WHERE id = ?");
            $stmt->execute([$id]);
            
            AuditLog::log('delete', 'work_orders', $id, [
                'wo_no' => $workOrder['wo_no'], 'title' => $workOrder['title']
            ], null);
            
            $this->db->commit();
            Response::json(['success' => true, 'message' => 'Work order deleted']);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to delete work order');
        }
    }
    
    /**
     * View single work order with tasks and timeline
     */
    public function view($id) {
        $stmt = $this->db->prepare("SELECT wo.*, c.name as customer_name, c.phone as customer_phone,
                                    c.address as customer_address, u.name as assigned_to_name
                                    FROM work_orders wo
                                    LEFT JOIN customers c ON wo.customer_id = c.id
                                    LEFT JOIN users u ON wo.assigned_to = u.id
                                    WHERE wo.id = ?");
        $stmt->execute([$id]);
        $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$workOrder) {
            Response::redirect('/work-orders', 'Work order not found');
        }
        
        // Get tasks
        $stmt = $this->db->prepare("SELECT t.*, u.name as assigned_to_name 
                                    FROM work_order_tasks t
                                    LEFT JOIN users u ON t.assigned_to = u.id
                                    WHERE t.work_order_id = ?
                                    ORDER BY t.due_date, t.created_at");
        $stmt->execute([$id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get timeline
        $stmt = $this->db->prepare("SELECT tl.*, u.name as user_name 
                                    FROM work_order_timeline tl
                                    LEFT JOIN users u ON tl.created_by = u.id
                                    WHERE tl.work_order_id = ?
                                    ORDER BY tl.created_at DESC");
        $stmt->execute([$id]);
        $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments
        $stmt = $this->db->prepare("SELECT * FROM attachments 
                                    WHERE reference_type = 'work_order' AND reference_id = ?
                                    ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        include __DIR__ . '/views/view.php';
    }
    
    /**
     * Show Kanban board
     */
    public function kanban() {
        // Get all work orders grouped by status
        $statuses = ['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        $columns = [];
        
        foreach ($statuses as $status) {
            $stmt = $this->db->prepare("SELECT wo.*, c.name as customer_name 
                                        FROM work_orders wo
                                        LEFT JOIN customers c ON wo.customer_id = c.id
                                        WHERE wo.status = ?
                                        ORDER BY 
                                            CASE wo.priority 
                                                WHEN 'urgent' THEN 1 
                                                WHEN 'high' THEN 2 
                                                WHEN 'medium' THEN 3 
                                                WHEN 'low' THEN 4 
                                            END,
                                            wo.due_date ASC");
            $stmt->execute([$status]);
            $columns[$status] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        include __DIR__ . '/views/kanban.php';
    }
    
    /**
     * Update work order status (for Kanban drag-drop)
     */
    public function updateStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        
        $validStatuses = ['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            Response::error('Invalid status');
        }
        
        $stmt = $this->db->prepare("UPDATE work_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        $this->logTimeline($id, "Status changed to $status");
        
        AuditLog::log('update', 'work_orders', $id, null, ['status' => $status]);
        
        Response::json(['success' => true]);
    }
    
    /**
     * Add task to work order
     */
    public function addTask() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        $work_order_id = (int)($_POST['work_order_id'] ?? 0);
        $task_name = trim($_POST['task_name'] ?? '');
        $assigned_to = (int)($_POST['assigned_to'] ?? 0);
        $due_date = $_POST['due_date'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$work_order_id || !$task_name) {
            Response::error('Work order and task name are required');
        }
        
        $stmt = $this->db->prepare("INSERT INTO work_order_tasks 
                                    (work_order_id, task_name, assigned_to, due_date, notes)
                                    VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$work_order_id, $task_name, $assigned_to, $due_date, $notes]);
        
        $taskId = $this->db->lastInsertId();
        
        $this->logTimeline($work_order_id, "Task added: $task_name");
        
        Response::json(['success' => true, 'task_id' => $taskId]);
    }
    
    /**
     * Update task status
     */
    public function updateTask() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Invalid request method');
        }
        
        Helpers::verifyCSRF();
        
        $id = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        
        $validStatuses = ['pending', 'in_progress', 'done'];
        if (!in_array($status, $validStatuses)) {
            Response::error('Invalid status');
        }
        
        $stmt = $this->db->prepare("UPDATE work_order_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Get work order ID for timeline
        $stmt = $this->db->prepare("SELECT work_order_id FROM work_order_tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            $this->logTimeline($task['work_order_id'], "Task status changed to $status");
        }
        
        Response::json(['success' => true]);
    }
    
    /**
     * Log timeline entry
     */
    private function logTimeline($workOrderId, $action, $notes = '') {
        $stmt = $this->db->prepare("INSERT INTO work_order_timeline 
                                    (work_order_id, action, notes, created_by)
                                    VALUES (?, ?, ?, ?)");
        $stmt->execute([$workOrderId, $action, $notes, Auth::getId()]);
    }
    
    /**
     * Get next work order number
     */
    private function getNextNumber() {
        $year = date('Y');
        $prefix = "WO-$year-";
        
        $stmt = $this->db->prepare("SELECT MAX(CAST(SUBSTRING(wo_no, LENGTH(?) + 1) AS UNSIGNED)) as max_num 
                                    FROM work_orders WHERE wo_no LIKE ?");
        $stmt->execute([$prefix, $prefix . '%']);
        $max = $stmt->fetchColumn() ?: 0;
        
        return $max + 1;
    }
}

// Router
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

$controller = new WorkOrderController();

switch ($action) {
    case 'index':
        $controller->index();
        break;
    case 'form':
        $controller->form($id);
        break;
    case 'save':
        $controller->save();
        break;
    case 'delete':
        $controller->delete($id);
        break;
    case 'view':
        $controller->view($id);
        break;
    case 'kanban':
        $controller->kanban();
        break;
    case 'update-status':
        $controller->updateStatus();
        break;
    case 'add-task':
        $controller->addTask();
        break;
    case 'update-task':
        $controller->updateTask();
        break;
    default:
        $controller->index();
}
