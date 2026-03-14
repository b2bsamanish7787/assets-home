<?php
/**
 * File: admin/employees/delete.php
 * Description: Handler to delete an employee after CSRF validation — no HTML output
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

// Validate CSRF token from query string
$csrfToken = $_GET['csrf_token'] ?? '';
if (!validateCSRF($csrfToken)) {
    flashMessage('danger', 'Invalid CSRF token. Action aborted.');
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    flashMessage('danger', 'Invalid employee ID.');
    header('Location: index.php');
    exit;
}

try {
    // Fetch employee details before deleting for logging
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, employee_id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        flashMessage('danger', 'Employee not found.');
        header('Location: index.php');
        exit;
    }

    // Prevent self-deletion
    if ($id === (int)$_SESSION['user_id']) {
        flashMessage('danger', 'You cannot delete your own account.');
        header('Location: index.php');
        exit;
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $fullName = trim($employee['first_name'] . ' ' . $employee['last_name']);
    logActivity(
        (int)$_SESSION['user_id'],
        'delete_employee',
        "Deleted employee {$fullName} ({$employee['employee_id']}) — {$employee['email']}."
    );

    flashMessage('success', 'Employee ' . $fullName . ' has been deleted.');

} catch (Exception $e) {
    error_log('Delete employee error: ' . $e->getMessage());
    flashMessage('danger', 'A database error occurred. The employee could not be deleted.');
}

header('Location: index.php');
exit;
