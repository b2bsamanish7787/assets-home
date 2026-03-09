<?php
/**
 * Buzznation Assets Management System
 * File: logout.php
 * Description: Destroys session and redirects to login
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

session_unset();
session_destroy();

header('Location: ' . SITE_URL . '/login.php');
exit;
