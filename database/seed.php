<?php
/**
 * Buzznation Assets Management System
 * File: database/seed.php
 * Description: Seeds the default admin user into the database
 * Author: Buzznation IT Team
 */

// Run once to seed the admin user
require_once __DIR__ . '/../config/db.php';

$hash = password_hash('Admin@123', PASSWORD_BCRYPT);

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@buzznationmarketing.com']);
    if ($stmt->fetch()) {
        echo "Admin user already exists. Seed skipped.\n";
        exit;
    }

    // Insert default admin
    $stmt = $pdo->prepare("
        INSERT INTO users (employee_id, first_name, last_name, email, password, role, status, is_first_login)
        VALUES ('EMP001', 'System', 'Admin', 'admin@buzznationmarketing.com', ?, 'admin', 'active', 0)
    ");
    $stmt->execute([$hash]);
    echo "Admin user created successfully.\n";
    echo "Email: admin@buzznationmarketing.com\n";
    echo "Password: Admin@123\n";
} catch (PDOException $e) {
    echo "Error seeding admin: " . $e->getMessage() . "\n";
}
