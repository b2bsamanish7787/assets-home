<?php
/**
 * Buzznation Assets Management System
 * File: admin/service/action.php
 * Description: Handle approve, reject, complete, and bill upload for service requests
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

// ── Determine if this is a bill upload (POST) or a status action (GET) ────────
$isPost    = $_SERVER['REQUEST_METHOD'] === 'POST';
$csrfToken = $isPost ? ($_POST['csrf_token'] ?? '') : ($_GET['csrf_token'] ?? '');

if (!validateCSRF($csrfToken)) {
    flashMessage('danger', 'Invalid CSRF token. Action aborted.');
    header('Location: index.php');
    exit;
}

$action  = $isPost ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');
$id      = (int)($isPost ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
$remarks = trim($isPost ? ($_POST['remarks'] ?? '') : ($_GET['remarks'] ?? ''));

$allowedActions = ['approve', 'reject', 'complete', 'upload_bill'];
if (!in_array($action, $allowedActions, true) || !$id) {
    flashMessage('danger', 'Invalid action or service request ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch service request ─────────────────────────────────────────────────────
try {
    $srStmt = $pdo->prepare(
        "SELECT sr.id, sr.status, sr.asset_id, sr.employee_id,
                sr.problem_description,
                a.asset_name,
                a.assigned_to AS asset_assigned_to,
                CONCAT(u.first_name, ' ', u.last_name) AS employee_name,
                u.email AS employee_email
         FROM service_requests sr
         JOIN assets a ON a.id = sr.asset_id
         JOIN users u  ON u.id = sr.employee_id
         WHERE sr.id = ?"
    );
    $srStmt->execute([$id]);
    $sr = $srStmt->fetch();
} catch (Exception $e) {
    error_log('Fetch service request error: ' . $e->getMessage());
    flashMessage('danger', 'Database error. Please try again.');
    header('Location: index.php');
    exit;
}

if (!$sr) {
    flashMessage('danger', 'Service request not found.');
    header('Location: index.php');
    exit;
}

$empId    = (int)$sr['employee_id'];
$assetId  = (int)$sr['asset_id'];
$empEmail = $sr['employee_email'];
$empName  = $sr['employee_name'];
$assetName = $sr['asset_name'];

// ── Route action ──────────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    switch ($action) {

        // ── APPROVE ───────────────────────────────────────────────────────────
        case 'approve':
            if ($sr['status'] !== 'pending') {
                $pdo->rollBack();
                flashMessage('warning', 'This service request has already been processed.');
                header('Location: index.php');
                exit;
            }

            $pdo->prepare(
                "UPDATE service_requests SET status = 'approved', admin_remarks = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$remarks ?: null, $id]);

            $pdo->prepare(
                "UPDATE assets SET status = 'in_service' WHERE id = ?"
            )->execute([$assetId]);

            $pdo->prepare(
                "INSERT INTO asset_history (asset_id, action, notes, created_by) VALUES (?, 'service_approved', ?, ?)"
            )->execute([$assetId, "Service request #{$id} approved." . ($remarks ? " Remarks: {$remarks}" : ''), $_SESSION['user_id']]);

            sendNotification(
                $empId,
                'service_approved',
                "Your service request for \"{$assetName}\" has been approved.",
                SITE_URL . '/employee/service/my_service.php'
            );

            logActivity(
                (int)$_SESSION['user_id'],
                'approve_service',
                "Approved service request ID {$id} for asset \"{$assetName}\" ({$empName})"
            );
            flashMessage('success', 'Service request approved. Asset "' . $assetName . '" marked as In Service.');
            break;

        // ── REJECT ────────────────────────────────────────────────────────────
        case 'reject':
            if ($sr['status'] !== 'pending') {
                $pdo->rollBack();
                flashMessage('warning', 'This service request has already been processed.');
                header('Location: index.php');
                exit;
            }

            if ($remarks === '') {
                $pdo->rollBack();
                flashMessage('danger', 'Rejection remarks are required.');
                header('Location: index.php');
                exit;
            }

            $pdo->prepare(
                "UPDATE service_requests SET status = 'rejected', admin_remarks = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$remarks, $id]);

            sendNotification(
                $empId,
                'service_rejected',
                "Your service request for \"{$assetName}\" has been rejected. Reason: {$remarks}",
                SITE_URL . '/employee/service/my_service.php'
            );

            logActivity(
                (int)$_SESSION['user_id'],
                'reject_service',
                "Rejected service request ID {$id} for asset \"{$assetName}\" ({$empName}). Remarks: {$remarks}"
            );
            flashMessage('success', "Service request rejected.");
            break;

        // ── COMPLETE ──────────────────────────────────────────────────────────
        case 'complete':
            if ($sr['status'] !== 'approved') {
                $pdo->rollBack();
                flashMessage('warning', 'Only approved service requests can be marked complete.');
                header('Location: index.php');
                exit;
            }

            $pdo->prepare(
                "UPDATE service_requests SET status = 'completed', updated_at = NOW() WHERE id = ?"
            )->execute([$id]);

            // Restore asset status based on whether it had an assigned user
            $restoreStatus = $sr['asset_assigned_to'] ? 'assigned' : 'available';
            $pdo->prepare(
                "UPDATE assets SET status = ? WHERE id = ?"
            )->execute([$restoreStatus, $assetId]);

            $pdo->prepare(
                "INSERT INTO asset_history (asset_id, action, notes, created_by) VALUES (?, 'service_completed', ?, ?)"
            )->execute([$assetId, "Service request #{$id} completed. Asset status restored to {$restoreStatus}.", $_SESSION['user_id']]);

            sendNotification(
                $empId,
                'service_completed',
                "Service for \"{$assetName}\" has been completed and the asset is now {$restoreStatus}.",
                SITE_URL . '/employee/service/my_service.php'
            );

            logActivity(
                (int)$_SESSION['user_id'],
                'complete_service',
                "Marked service request ID {$id} as completed for asset \"{$assetName}\" ({$empName})"
            );
            flashMessage('success', "Service request marked as completed. Asset status updated to {$restoreStatus}.");
            break;

        // ── UPLOAD BILL ───────────────────────────────────────────────────────
        case 'upload_bill':
            if ($sr['status'] !== 'approved') {
                $pdo->rollBack();
                flashMessage('warning', 'Bill can only be uploaded for approved service requests.');
                header('Location: index.php');
                exit;
            }

            if (empty($_FILES['service_bill']) || $_FILES['service_bill']['error'] !== UPLOAD_ERR_OK) {
                $pdo->rollBack();
                flashMessage('danger', 'No file uploaded or upload error occurred.');
                header('Location: index.php');
                exit;
            }

            $file         = $_FILES['service_bill'];
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $mimeType     = mime_content_type($file['tmp_name']);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $pdo->rollBack();
                flashMessage('danger', 'Invalid file type. Only PDF, JPG, and PNG files are allowed.');
                header('Location: index.php');
                exit;
            }

            if ($file['size'] > 5 * 1024 * 1024) { // 5 MB
                $pdo->rollBack();
                flashMessage('danger', 'File size exceeds the 5 MB limit.');
                header('Location: index.php');
                exit;
            }

            $uploadDir = __DIR__ . '/../../uploads/bills/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'bill_' . $id . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $pdo->rollBack();
                flashMessage('danger', 'Failed to save the uploaded file.');
                header('Location: index.php');
                exit;
            }

            $pdo->prepare(
                "UPDATE service_requests SET service_bill = ?, updated_at = NOW() WHERE id = ?"
            )->execute(['bills/' . $filename, $id]);

            logActivity(
                (int)$_SESSION['user_id'],
                'upload_service_bill',
                "Uploaded service bill for request ID {$id} — {$filename}"
            );
            flashMessage('success', 'Service bill uploaded successfully.');
            break;
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Service action error: ' . $e->getMessage());
    flashMessage('danger', 'Database error. Action failed. Please try again.');
}

header('Location: index.php');
exit;
