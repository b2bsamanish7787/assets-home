<?php
/**
 * Buzznation Assets Management System
 * File: admin/requests/action.php
 * Description: Approve or reject an employee asset request via GET params with CSRF
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

// ── Validate CSRF ─────────────────────────────────────────────────────────────
if (!validateCSRF($_GET['csrf_token'] ?? '')) {
    flashMessage('danger', 'Invalid CSRF token. Action aborted.');
    header('Location: index.php');
    exit;
}

$action  = $_GET['action']  ?? '';
$id      = (int)($_GET['id'] ?? 0);
$remarks = trim($_GET['remarks'] ?? '');

if (!in_array($action, ['approve', 'reject'], true) || !$id) {
    flashMessage('danger', 'Invalid action or request ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch asset request ───────────────────────────────────────────────────────
try {
    $reqStmt = $pdo->prepare(
        "SELECT ar.id, ar.asset_requirement, ar.status, ar.employee_id,
                CONCAT(u.first_name, ' ', u.last_name) AS employee_name,
                u.email AS employee_email
         FROM asset_requests ar
         JOIN users u ON u.id = ar.employee_id
         WHERE ar.id = ?"
    );
    $reqStmt->execute([$id]);
    $request = $reqStmt->fetch();
} catch (Exception $e) {
    error_log('Fetch asset request error: ' . $e->getMessage());
    flashMessage('danger', 'Database error. Please try again.');
    header('Location: index.php');
    exit;
}

if (!$request) {
    flashMessage('danger', 'Asset request not found.');
    header('Location: index.php');
    exit;
}

if ($request['status'] !== 'pending') {
    flashMessage('warning', 'This request has already been processed.');
    header('Location: index.php');
    exit;
}

if ($action === 'reject' && $remarks === '') {
    flashMessage('danger', 'Rejection remarks are required.');
    header('Location: index.php');
    exit;
}

// ── Process action ────────────────────────────────────────────────────────────
$newStatus = ($action === 'approve') ? 'approved' : 'rejected';

try {
    $pdo->prepare(
        "UPDATE asset_requests SET status = ?, admin_remarks = ?, updated_at = NOW() WHERE id = ?"
    )->execute([$newStatus, $remarks ?: null, $id]);

    $empName  = $request['employee_name'];
    $empEmail = $request['employee_email'];
    $empId    = (int)$request['employee_id'];
    $req      = $request['asset_requirement'];

    // Email the employee
    sendEmail(
        $empEmail,
        "Asset Request {$newStatus} — " . SITE_NAME,
        emailRequestAction($empName, $newStatus, $remarks)
    );

    // In-app notification
    sendNotification(
        $empId,
        'request_' . $newStatus,
        "Your asset request for \"{$req}\" has been {$newStatus}." . ($remarks ? " Remarks: {$remarks}" : ''),
        SITE_URL . '/employee/requests/my_requests.php'
    );

    logActivity(
        (int)$_SESSION['user_id'],
        $action . '_asset_request',
        ucfirst($action) . "d asset request ID {$id} for {$empName} ({$req})" . ($remarks ? ". Remarks: {$remarks}" : '')
    );

    flashMessage('success', "Asset request has been {$newStatus} successfully.");
} catch (Exception $e) {
    error_log('Asset request action error: ' . $e->getMessage());
    flashMessage('danger', 'Failed to process request. Please try again.');
}

header('Location: index.php');
exit;
