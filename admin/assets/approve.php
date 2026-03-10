<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/approve.php
 * Description: Approve a pending employee-submitted asset
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flashMessage('danger', 'Invalid CSRF token. Please try again.');
    header('Location: index.php');
    exit;
}

$assetId = (int)($_POST['asset_id'] ?? 0);
if (!$assetId) {
    flashMessage('danger', 'Invalid asset ID.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, asset_name, approval_status, submitted_by FROM assets WHERE id = ?");
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();

    if (!$asset) {
        flashMessage('danger', 'Asset not found.');
        header('Location: index.php');
        exit;
    }

    if ($asset['approval_status'] !== 'pending') {
        flashMessage('warning', 'This asset has already been approved.');
        header('Location: view.php?id=' . $assetId);
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare("UPDATE assets SET approval_status = 'approved' WHERE id = ?")
        ->execute([$assetId]);

    $pdo->prepare(
        "INSERT INTO asset_history (asset_id, action, notes, created_by, created_at)
         VALUES (?, 'approved', 'Asset approved by admin.', ?, NOW())"
    )->execute([$assetId, (int)$_SESSION['user_id']]);

    $pdo->commit();

    // Notify the submitting employee
    if (!empty($asset['submitted_by'])) {
        sendNotification(
            (int)$asset['submitted_by'],
            'asset_approved',
            'Your asset "' . $asset['asset_name'] . '" has been approved.',
            SITE_URL . '/employee/assets/my_assets.php'
        );
    }

    logActivity(
        (int)$_SESSION['user_id'],
        'approve_asset',
        'Approved asset: ' . $asset['asset_name'] . ' (ID: ' . $assetId . ')'
    );

    flashMessage('success', 'Asset "' . $asset['asset_name'] . '" approved successfully.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Approve asset error: ' . $e->getMessage());
    flashMessage('danger', 'Database error. Approval failed. Please try again.');
}

header('Location: view.php?id=' . $assetId);
exit;
