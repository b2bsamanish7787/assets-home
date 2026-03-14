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
// Automatic schema migrations — MySQL 5.7+ compatible (INFORMATION_SCHEMA check)
// ---------------------------------------------------------------------------

/**
 * Add a column to a table only if it does not already exist.
 * Uses INFORMATION_SCHEMA so it works on MySQL 5.7+ and MariaDB
 * (unlike `ADD COLUMN IF NOT EXISTS` which requires MySQL 8.0+).
 *
 * @param PDO    $pdo        Active database connection
 * @param string $table      Table name — must be a valid SQL identifier
 * @param string $column     Column name — must be a valid SQL identifier
 * @param string $definition Column definition (type, constraints, etc.) — must be a
 *                           hardcoded literal string; NEVER pass user-supplied input here
 */
function _migrateAddColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    // Validate identifiers: letters, digits, underscores only, max 64 chars (MySQL limit)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $column)) {
        error_log("_migrateAddColumn: invalid identifier table={$table} column={$column}");
        return;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if (!(int)$stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

try {
    // assets: purchased_by_name (added after initial schema release)
    _migrateAddColumn($pdo, 'assets', 'purchased_by_name', 'VARCHAR(150) NULL AFTER purchased_by');

    // assets: approval_status (added after initial schema release)
    _migrateAddColumn($pdo, 'assets', 'approval_status', "ENUM('pending','approved') NOT NULL DEFAULT 'approved' AFTER status");

    // transfer_consent: accepted_at (added after initial schema release)
    _migrateAddColumn($pdo, 'transfer_consent', 'accepted_at', 'TIMESTAMP NULL DEFAULT NULL AFTER status');

    // transfer_consent: type (added after initial schema release)
    _migrateAddColumn($pdo, 'transfer_consent', 'type', "ENUM('transfer','assignment') NOT NULL DEFAULT 'transfer' AFTER to_user");

    // asset_history: extend action enum to include 'approved' (MODIFY is idempotent)
    $pdo->exec("ALTER TABLE asset_history MODIFY COLUMN action ENUM('submitted','assigned','transferred','returned','service_requested','service_approved','service_completed','approved') NOT NULL");
} catch (PDOException $e) {
    error_log('Schema migration error: ' . $e->getMessage());
}
