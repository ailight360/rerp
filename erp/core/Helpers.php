<?php
/**
 * Helper Functions
 * Utility functions for formatting, dates, currency, CSRF, etc.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

class Helpers {
    
    /**
     * Format currency amount
     */
    public static function formatCurrency($amount) {
        return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = null) {
        if (empty($date)) return '';
        return date($format ?? DISPLAY_DATE_FORMAT, strtotime($date));
    }
    
    /**
     * Format datetime for display
     */
    public static function formatDateTime($datetime) {
        if (empty($datetime)) return '';
        return date('d M Y h:i A', strtotime($datetime));
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenerate CSRF token
     */
    public static function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF input field HTML
     */
    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . self::generateCSRFToken() . '">';
    }
    
    /**
     * Escape HTML output
     */
    public static function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Redirect to URL
     */
    public static function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber($prefix, $tableName, $columnName = 'invoice_no') {
        try {
            $db = getDB();
            $year = date('Y');
            
            $stmt = $db->prepare("
                SELECT $columnName FROM $tableName 
                WHERE $columnName LIKE ? 
                ORDER BY id DESC LIMIT 1
            ");
            
            $pattern = $prefix . $year . '-%';
            $stmt->execute([$pattern]);
            $last = $stmt->fetchColumn();
            
            if ($last) {
                $lastNum = (int) substr($last, strrpos($last, '-') + 1);
                $nextNum = $lastNum + 1;
            } else {
                $nextNum = 1;
            }
            
            return $prefix . $year . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            
        } catch (PDOException $e) {
            return $prefix . date('YmdHis');
        }
    }
    
    /**
     * Calculate discount amount
     */
    public static function calculateDiscount($subtotal, $discount, $discountType) {
        if ($discountType === 'percent') {
            return ($subtotal * $discount) / 100;
        }
        return $discount;
    }
    
    /**
     * Calculate tax amount
     */
    public static function calculateTax($amount, $taxRate) {
        if (empty($taxRate)) return 0;
        return ($amount * $taxRate) / 100;
    }
    
    /**
     * Calculate total with discount and tax
     */
    public static function calculateTotal($subtotal, $discount, $discountType, $taxRate) {
        $discountAmount = self::calculateDiscount($subtotal, $discount, $discountType);
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = self::calculateTax($afterDiscount, $taxRate);
        return $afterDiscount + $taxAmount;
    }
    
    /**
     * Get user role badge class
     */
    public static function getRoleBadgeClass($role) {
        $classes = [
            'admin' => 'badge-danger',
            'manager' => 'badge-warning',
            'staff' => 'badge-info'
        ];
        return $classes[$role] ?? 'badge-secondary';
    }
    
    /**
     * Get status badge class
     */
    public static function getStatusBadgeClass($status) {
        $classes = [
            'draft' => 'badge-secondary',
            'confirmed' => 'badge-success',
            'delivered' => 'badge-info',
            'cancelled' => 'badge-danger',
            'unpaid' => 'badge-warning',
            'partial' => 'badge-info',
            'paid' => 'badge-success',
            'overdue' => 'badge-danger',
            'pending' => 'badge-warning',
            'in_progress' => 'badge-info',
            'completed' => 'badge-success',
            'accepted' => 'badge-success',
            'rejected' => 'badge-danger',
            'expired' => 'badge-secondary'
        ];
        return $classes[$status] ?? 'badge-secondary';
    }
    
    /**
     * Sanitize file name
     */
    public static function sanitizeFileName($filename) {
        $info = pathinfo($filename);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['filename']);
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        return $name . $ext;
    }
    
    /**
     * Generate UUID
     */
    public static function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Get time ago format
     */
    public static function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $diff . ' seconds ago';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' days ago';
        } else {
            return self::formatDate($datetime);
        }
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * JSON response helper
     */
    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Global helper functions for convenience
function e($str) {
    return Helpers::e($str);
}

function format_currency($amount) {
    return Helpers::formatCurrency($amount);
}

function format_date($date) {
    return Helpers::formatDate($date);
}

function format_datetime($datetime) {
    return Helpers::formatDateTime($datetime);
}

function time_ago($datetime) {
    return Helpers::timeAgo($datetime);
}
