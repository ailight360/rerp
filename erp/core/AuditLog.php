<?php
/**
 * Audit Log Handler
 * Records all create, update, delete, login, logout actions
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

class AuditLog {
    
    /**
     * Log an action
     * 
     * @param string $action Action type: create, update, delete, login, logout
     * @param string $tableName Table name affected
     * @param int|null $recordId Record ID affected
     * @param array|null $oldValues Old values (for update/delete)
     * @param array|null $newValues New values (for create/update)
     */
    public static function log($action, $tableName, $recordId = null, $oldValues = null, $newValues = null) {
        try {
            $db = getDB();
            
            $userId = Session::getUserId();
            $ipAddress = self::getClientIP();
            
            // Convert arrays to JSON
            $oldJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt = $db->prepare("
                INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldJson,
                $newJson,
                $ipAddress
            ]);
            
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Audit log failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        return $ip;
    }
    
    /**
     * Get audit logs with pagination
     */
    public static function getLogs($page = 1, $limit = 50, $filters = []) {
        try {
            $db = getDB();
            $offset = ($page - 1) * $limit;
            
            $where = [];
            $params = [];
            
            if (!empty($filters['user_id'])) {
                $where[] = "al.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $where[] = "al.action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['table_name'])) {
                $where[] = "al.table_name = ?";
                $params[] = $filters['table_name'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "DATE(al.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "DATE(al.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "
                SELECT al.*, u.name as user_name, u.email as user_email
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                $whereClause
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get total count of audit logs
     */
    public static function getTotalCount($filters = []) {
        try {
            $db = getDB();
            
            $where = [];
            $params = [];
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['table_name'])) {
                $where[] = "table_name = ?";
                $params[] = $filters['table_name'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT COUNT(*) FROM audit_log $whereClause";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            return 0;
        }
    }
}
