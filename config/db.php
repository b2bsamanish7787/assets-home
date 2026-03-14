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

// ---------------------------------------------------------------------------
// Automatic schema migrations — safe to run on every boot (IF NOT EXISTS / no-op)
// ---------------------------------------------------------------------------
try {
    // Migration: add purchased_by_name (added after initial schema release)
    $pdo->exec("ALTER TABLE assets ADD COLUMN IF NOT EXISTS purchased_by_name VARCHAR(150) NULL AFTER purchased_by");

    // Migration: add approval_status (added after initial schema release)
    $pdo->exec("ALTER TABLE assets ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved') NOT NULL DEFAULT 'approved' AFTER status");

    // Migration: add accepted_at to transfer_consent
    $pdo->exec("ALTER TABLE transfer_consent ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL DEFAULT NULL AFTER status");

    // Migration: add type to transfer_consent
    $pdo->exec("ALTER TABLE transfer_consent ADD COLUMN IF NOT EXISTS type ENUM('transfer','assignment') NOT NULL DEFAULT 'transfer' AFTER to_user");

    // Migration: extend asset_history action enum to include 'approved'
    $pdo->exec("ALTER TABLE asset_history MODIFY COLUMN action ENUM('submitted','assigned','transferred','returned','service_requested','service_approved','service_completed','approved') NOT NULL");
} catch (PDOException $e) {
    error_log('Schema migration error: ' . $e->getMessage());
}
