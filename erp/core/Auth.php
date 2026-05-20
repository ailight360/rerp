<?php
/**
 * Authentication Handler
 * Login, logout, role checks
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/AuditLog.php';

class Auth {
    
    /**
     * Attempt user login
     */
    public static function login($email, $password) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            if ($user['status'] != 1) {
                return ['success' => false, 'message' => 'Account is disabled. Contact administrator.'];
            }
            
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Set session data
            Session::set('user_id', $user['id']);
            Session::set('email', $user['email']);
            Session::set('name', $user['name']);
            Session::set('role', $user['role']);
            Session::set('last_activity', time());
            Session::regenerate();
            
            // Log the login action
            AuditLog::log('login', 'users', $user['id'], null, ['email' => $user['email']]);
            
            return ['success' => true, 'message' => 'Login successful'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (Session::isLoggedIn()) {
            AuditLog::log('logout', 'users', Session::getUserId(), null, null);
        }
        Session::destroy();
    }
    
    /**
     * Check if user is logged in, redirect to login if not
     */
    public static function requireLogin() {
        if (!Session::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/index.php?page=login');
            exit;
        }
        
        if (!Session::checkTimeout()) {
            header('Location: ' . BASE_URL . '/index.php?page=login&timeout=1');
            exit;
        }
    }
    
    /**
     * Check if user has required role
     */
    public static function requireRole($roles) {
        self::requireLogin();
        
        $userRole = Session::getRole();
        $allowedRoles = is_array($roles) ? $roles : [$roles];
        
        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            die('Access denied. Insufficient permissions.');
        }
    }
    
    /**
     * Check if user is admin
     */
    public static function requireAdmin() {
        self::requireRole('admin');
    }
    
    /**
     * Check if user is admin or manager
     */
    public static function requireManager() {
        self::requireRole(['admin', 'manager']);
    }
    
    /**
     * Get current user ID
     */
    public static function userId() {
        return Session::getUserId();
    }
    
    /**
     * Get current user role
     */
    public static function userRole() {
        return Session::getRole();
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        return Session::getRole() === $role;
    }
    
    /**
     * Is admin user
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Is manager or admin
     */
    public static function isManager() {
        return in_array(Session::getRole(), ['admin', 'manager']);
    }
}
