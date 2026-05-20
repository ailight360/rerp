<?php
/**
 * Session Handler
 * Authentication session management
 */

class Session {
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['email']);
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user email
     */
    public static function getEmail() {
        return $_SESSION['email'] ?? null;
    }
    
    /**
     * Get current user role
     */
    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Get current user name
     */
    public static function getName() {
        return $_SESSION['name'] ?? null;
    }
    
    /**
     * Set user session data
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session data
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session has key
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session data
     */
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Regenerate session ID (security)
     */
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    /**
     * Destroy session (logout)
     */
    public static function destroy() {
        session_unset();
        session_destroy();
        $_SESSION = [];
    }
    
    /**
     * Check session timeout
     */
    public static function checkTimeout() {
        if (self::isLoggedIn()) {
            $lastActivity = self::get('last_activity', 0);
            if (time() - $lastActivity > SESSION_TIMEOUT) {
                self::destroy();
                return false;
            }
            self::set('last_activity', time());
        }
        return true;
    }
    
    /**
     * Set flash message
     */
    public static function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Get and clear flash message
     */
    public static function getFlash() {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}
