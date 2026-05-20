<?php
/**
 * Notification Handler
 * In-app notifications for low stock, overdue bills, payments, system messages
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

class Notify {
    
    /**
     * Send a notification
     * 
     * @param int|null $userId User ID (null = all users)
     * @param string $type Notification type: low_stock, overdue_bill, payment_received, system
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $referenceType Reference type (e.g., 'product', 'bill')
     * @param int|null $referenceId Reference ID
     */
    public static function send($userId, $type, $title, $message, $referenceType = null, $referenceId = null) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $referenceType,
                $referenceId
            ]);
            
            return $db->lastInsertId();
            
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Notification failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get unread notification count for current user
     */
    public static function getUnreadCount() {
        try {
            $db = getDB();
            $userId = Session::getUserId();
            
            // Count notifications for specific user + global notifications (user_id IS NULL)
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Get notifications for current user
     */
    public static function getNotifications($limit = 20, $unreadOnly = false) {
        try {
            $db = getDB();
            $userId = Session::getUserId();
            
            $where = "(user_id = ? OR user_id IS NULL)";
            $params = [$userId];
            
            if ($unreadOnly) {
                $where .= " AND is_read = 0";
            }
            
            $stmt = $db->prepare("
                SELECT * FROM notifications 
                WHERE $where
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $params[] = $limit;
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                UPDATE notifications SET is_read = 1 WHERE id = ?
            ");
            
            return $stmt->execute([$notificationId]);
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for current user
     */
    public static function markAllAsRead() {
        try {
            $db = getDB();
            $userId = Session::getUserId();
            
            $stmt = $db->prepare("
                UPDATE notifications SET is_read = 1 
                WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0
            ");
            
            return $stmt->execute([$userId]);
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Trigger low stock notification
     */
    public static function checkLowStock($productId, $productName, $currentStock, $minStock) {
        if ($currentStock <= $minStock) {
            self::send(
                null,
                'low_stock',
                'Low Stock Alert',
                "Product '$productName' has only $currentStock units left (minimum: $minStock)",
                'product',
                $productId
            );
        }
    }
    
    /**
     * Check and notify about overdue bills
     */
    public static function checkOverdueBills() {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                SELECT b.*, c.name as customer_name 
                FROM bills b
                JOIN customers c ON b.customer_id = c.id
                WHERE b.status IN ('unpaid', 'partial') 
                AND b.due_date < CURDATE()
                AND b.is_locked = 0
            ");
            
            $stmt->execute();
            $overdueBills = $stmt->fetchAll();
            
            foreach ($overdueBills as $bill) {
                $daysOverdue = floor((strtotime('now') - strtotime($bill['due_date'])) / 86400);
                
                // Only notify if not notified in last 24 hours
                $checkStmt = $db->prepare("
                    SELECT COUNT(*) FROM notifications 
                    WHERE reference_type = 'bill' 
                    AND reference_id = ? 
                    AND type = 'overdue_bill'
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
                ");
                
                $checkStmt->execute([$bill['id']]);
                $recentCount = $checkStmt->fetchColumn();
                
                if ($recentCount == 0) {
                    self::send(
                        null,
                        'overdue_bill',
                        'Overdue Bill Alert',
                        "Bill #{$bill['bill_no']} for {$bill['customer_name']} is overdue by $daysOverdue days. Amount due: {$bill['due']}",
                        'bill',
                        $bill['id']
                    );
                }
            }
            
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Overdue bill check failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Notify about payment received
     */
    public static function notifyPaymentReceived($paymentId, $amount, $customerName) {
        self::send(
            null,
            'payment_received',
            'Payment Received',
            "Received $amount from $customerName",
            'payment',
            $paymentId
        );
    }
}
