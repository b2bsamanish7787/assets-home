<?php
/**
 * Buzznation Assets Management System
 * File: config/db.php
 * Description: PDO database connection
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the real error, show a generic message to users
    error_log('DB Connection Error: ' . $e->getMessage());
    die('Database connection failed. Please try again later.');
}
