<?php
/**
 * Application Configuration
 * Constants, timezone, currency, tax settings
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'ERP System');
}

// Base URL - adjust for your environment
define('BASE_URL', 'http://localhost/erp');

// File upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Currency
define('CURRENCY_SYMBOL', '৳');
define('CURRENCY_CODE', 'BDT');

// Default tax rate (percentage)
define('DEFAULT_TAX_RATE', 15.0);

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Invoice prefixes
define('STOCK_IN_PREFIX', 'SI-');
define('STOCK_OUT_PREFIX', 'SO-');
define('BILL_PREFIX', 'BILL-');
define('QUOTE_PREFIX', 'QT-');
define('WORK_ORDER_PREFIX', 'WO-');
define('PAYMENT_PREFIX', 'PAY-');

// Items per page for pagination
define('ITEMS_PER_PAGE', 25);

// Max file upload size (in bytes) - 5MB
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed file extensions for uploads
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xlsx', 'xls']);

// Date format for display
define('DATE_FORMAT', 'Y-m-d');
define('DISPLAY_DATE_FORMAT', 'd M Y');

// Debug mode (set to false in production)
define('DEBUG_MODE', true);

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
