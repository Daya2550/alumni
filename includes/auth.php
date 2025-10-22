<?php
/**
 * Authentication and Authorization Functions
 */

require_once __DIR__ . '/database.php';

class Auth {
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user data
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $user_id = $_SESSION['user_id'];
        return db()->fetchOne(
            "SELECT * FROM users WHERE id = ? AND is_active = 1",
            [$user_id]
        );
    }
    
    /**
     * Login user
     */
    public static function login($user_id, $remember = false) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Update last login
        db()->execute(
            "UPDATE users SET updated_at = NOW() WHERE id = ?",
            [$user_id]
        );
        
        // Create session record
        self::createSession($user_id);
        
        return true;
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            // Delete session from database
            self::deleteSession($_SESSION['user_id']);
        }
        
        // Destroy session
        session_destroy();
        session_start();
        
        return true;
    }
    
    /**
     * Check user role
     */
    public static function hasRole($role) {
        $user = self::getCurrentUser();
        if (!$user) return false;
        // Admin is superuser: has all roles
        if ($user['role'] === 'admin') return true;
        return $user['role'] === $role;
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public static function hasAnyRole($roles) {
        $user = self::getCurrentUser();
        if (!$user) return false;
        // Admin is superuser: has all roles
        if ($user['role'] === 'admin') return true;
        return in_array($user['role'], $roles);
    }
    
    /**
     * Require login - redirect if not logged in
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
    }
    
    /**
     * Require any of the specified roles
     */
    public static function requireAnyRole($roles) {
        self::requireLogin();
        if (!self::hasAnyRole($roles)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
    }
    
    /**
     * Create session record
     */
    private static function createSession($user_id) {
        $session_id = session_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        db()->execute(
            "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) 
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             ip_address = VALUES(ip_address), 
             user_agent = VALUES(user_agent), 
             expires_at = VALUES(expires_at)",
            [$session_id, $user_id, $ip_address, $user_agent, $expires_at]
        );
    }
    
    /**
     * Delete session record
     */
    private static function deleteSession($user_id) {
        $session_id = session_id();
        db()->execute(
            "DELETE FROM user_sessions WHERE id = ? AND user_id = ?",
            [$session_id, $user_id]
        );
    }
    
    /**
     * Clean expired sessions
     */
    public static function cleanExpiredSessions() {
        db()->execute("DELETE FROM user_sessions WHERE expires_at < NOW()");
    }
    
    /**
     * Check if user can access batch-specific content
     */
    public static function canAccessBatch($batch) {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        // Admin and Staff can access all batches
        if ($user['role'] === 'admin' || $user['role'] === 'staff') return true;
        
        // Users can access their own batch
        return $user['batch'] === $batch;
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
}

// Rate limiting functions
class RateLimiter {
    
    /**
     * Check rate limit for user action
     */
    public static function checkLimit($action, $user_id, $limit = 10, $window = 60) {
        $key = $action . '_' . $user_id;
        
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        $window_start = $now - $window;
        
        // Clean old entries
        if (isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = array_filter(
                $_SESSION['rate_limit'][$key],
                function($timestamp) use ($window_start) {
                    return $timestamp > $window_start;
                }
            );
        } else {
            $_SESSION['rate_limit'][$key] = [];
        }
        
        // Check if limit exceeded
        if (count($_SESSION['rate_limit'][$key]) >= $limit) {
            return false;
        }
        
        // Add current timestamp
        $_SESSION['rate_limit'][$key][] = $now;
        
        return true;
    }
}

// Clean expired sessions on each request (basic cleanup)
Auth::cleanExpiredSessions();
?>
