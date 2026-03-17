<?php
/**
 * Buzznation Assets Management System
 * File: index.php
 * Description: Entry point — redirects to login
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/config/config.php';

header('Location: ' . SITE_URL . '/login.php');
exit;
