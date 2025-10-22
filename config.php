<?php
/**
 * Alumni Portal Configuration
 * Database and application settings
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'alumni_portal');
define('DB_USER', 'root');
define('DB_PASS', '2550');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Sinhgad Alumni Portal');
define('APP_URL', 'https://alumni.free.nf');
define('APP_TIMEZONE', 'UTC');
define('APP_ENV', 'production'); // development, production

// Security Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp']);

// OAuth Configuration
define('LINKEDIN_CLIENT_ID', '78jcfr9mle6fu5');
define('LINKEDIN_CLIENT_SECRET', 'WPL_AP1.UziFhaEQBOn12jzu.q0TmQg==');
define('LINKEDIN_REDIRECT_URI', 'https://linkedin.free.nf/callback.php');

define('GOOGLE_CLIENT_ID', 'your-google-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('GOOGLE_REDIRECT_URI', 'https://linkedin.free.nf/public/google_callback.php');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('FROM_EMAIL', 'noreply@alumni.edu');
define('FROM_NAME', 'Alumni Portal');

// reCAPTCHA Configuration
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');

// Chat Configuration
define('CHAT_POLL_INTERVAL', 5000); // 5 seconds
define('CHAT_MESSAGE_LIMIT', 1000);
define('CHAT_RATE_LIMIT', 10); // messages per minute

// Pagination
define('ITEMS_PER_PAGE', 10);

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set(APP_TIMEZONE);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', APP_ENV === 'production' ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create upload directories if they don't exist
$upload_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'profiles/',
    UPLOAD_PATH . 'notices/',
    UPLOAD_PATH . 'news/',
    UPLOAD_PATH . 'jobs/',
    UPLOAD_PATH . 'events/',
    UPLOAD_PATH . 'gallery/',
    UPLOAD_PATH . 'chat/',
    UPLOAD_PATH . 'feed/',
    UPLOAD_PATH . 'temp/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
