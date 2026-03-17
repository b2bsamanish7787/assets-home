<?php
/**
 * Buzznation Assets Management System
 * File: config/config.php
 * Description: Site-wide configuration constants
 * Author: Buzznation IT Team
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'buzznation_assets');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_URL', 'http://localhost/assets-home');
define('SITE_NAME', 'Buzznation Assets Management System');
define('COMPANY_NAME', 'Buzznation');

// Mail configuration (PHPMailer)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'noreply@buzznationmarketing.com');
define('MAIL_PASS', 'your_email_password');
define('MAIL_FROM', 'noreply@buzznationmarketing.com');
define('MAIL_FROM_NAME', 'Buzznation Assets');
define('MAIL_PORT', 587);

// Upload path — stored inside assets/uploads/
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);
// Login lockout duration in seconds (30 minutes)
define('LOGIN_LOCKOUT_TIME', 1800);

// Error reporting — set to 0 in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');
