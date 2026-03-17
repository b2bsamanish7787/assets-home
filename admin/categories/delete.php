<?php
/**
 * Buzznation Assets Management System
 * File: admin/categories/delete.php
 * Description: Delete an asset category (GET with CSRF token)
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

if (!validateCSRF($_GET['csrf_token'] ?? '')) {
    flashMessage('danger', 'Invalid CSRF token. Action aborted.');
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flashMessage('danger', 'Invalid category ID.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        flashMessage('danger', 'Category not found.');
        header('Location: index.php');
        exit;
    }

    // Check if any assets use this category
    $assetCheck = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category_id = ?");
    $assetCheck->execute([$id]);
    if ((int)$assetCheck->fetchColumn() > 0) {
        flashMessage('warning', 'Cannot delete "' . $category['name'] . '" — it is currently assigned to one or more assets.');
        header('Location: index.php');
        exit;
    }

    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    logActivity(
        (int)$_SESSION['user_id'],
        'delete_category',
        "Deleted category ID {$id}: {$category['name']}"
    );
    flashMessage('success', 'Category "' . $category['name'] . '" deleted successfully.');
} catch (Exception $e) {
    error_log('Delete category error: ' . $e->getMessage());
    flashMessage('danger', 'Database error. Could not delete category.');
}

header('Location: index.php');
exit;
