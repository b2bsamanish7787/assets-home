<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/transfer.php
 * Description: Transfer an assigned asset from one employee to another
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$preselectedAsset = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST: process transfer ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: transfer.php');
        exit;
    }

    $assetId       = (int)($_POST['asset_id'] ?? 0);
    $newEmployeeId = (int)($_POST['new_employee_id'] ?? 0);
    $notes         = sanitize($_POST['notes'] ?? '');

    if (!$assetId)       $errors[] = 'Please select an asset.';
    if (!$newEmployeeId) $errors[] = 'Please select the new employee.';

    $asset       = null;
    $newEmployee = null;

    if (empty($errors)) {
        try {
            $assetStmt = $pdo->prepare(
                "SELECT a.id, a.asset_name, a.assigned_to, a.status,
                        CONCAT(u.first_name, ' ', u.last_name) AS current_owner_name
                 FROM assets a
                 LEFT JOIN users u ON u.id = a.assigned_to
                 WHERE a.id = ?"
            );
            $assetStmt->execute([$assetId]);
            $asset = $assetStmt->fetch();

            if (!$asset || $asset['status'] !== 'assigned') {
                $errors[] = 'Selected asset is not currently assigned.';
            } elseif ((int)$asset['assigned_to'] === $newEmployeeId) {
                $errors[] = 'The new employee is already the current holder of this asset.';
            }

            $empStmt = $pdo->prepare(
                "SELECT id, first_name, last_name, email FROM users WHERE id = ? AND status = 'active'"
            );
            $empStmt->execute([$newEmployeeId]);
            $newEmployee = $empStmt->fetch();

            if (!$newEmployee) {
                $errors[] = 'Selected employee is not valid or inactive.';
            }
        } catch (Exception $e) {
            error_log('Transfer validation error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $fromUserId = (int)$asset['assigned_to'];

            // Record transfer consent (pending)
            $pdo->prepare(
                "INSERT INTO transfer_consent (asset_id, from_user, to_user, status)
                 VALUES (?, ?, ?, 'pending')"
            )->execute([$assetId, $fromUserId, $newEmployeeId]);

            // Update asset: set to new owner, keep as assigned
            $pdo->prepare(
                "UPDATE assets SET assigned_to = ?, status = 'assigned' WHERE id = ?"
            )->execute([$newEmployeeId, $assetId]);

            // Asset history
            $pdo->prepare(
                "INSERT INTO asset_history (asset_id, action, from_user, to_user, notes, created_by)
                 VALUES (?, 'transferred', ?, ?, ?, ?)"
            )->execute([$assetId, $fromUserId, $newEmployeeId, $notes, $_SESSION['user_id']]);

            $pdo->commit();

            $newEmpName  = trim($newEmployee['first_name'] . ' ' . $newEmployee['last_name']);
            $assetName   = $asset['asset_name'];

            // Notify new employee
            sendNotification(
                $newEmployeeId,
                'asset_transferred',
                "Asset \"{$assetName}\" has been transferred to you. Please confirm receipt.",
                SITE_URL . '/employee/consent.php'
            );

            // Email new employee
            sendEmail(
                $newEmployee['email'],
                'Asset Transfer Notice — ' . SITE_NAME,
                emailAssetTransfer($newEmpName, $assetName)
            );

            logActivity(
                (int)$_SESSION['user_id'],
                'transfer_asset',
                "Transferred asset \"{$assetName}\" (ID: {$assetId}) from user ID {$fromUserId} to {$newEmpName} (ID: {$newEmployeeId})"
            );

            flashMessage('success', "Asset \"{$assetName}\" transferred to {$newEmpName} successfully.");
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Transfer asset error: ' . $e->getMessage());
            $errors[] = 'Database error. Transfer failed.';
        }
    }
}

// ── GET: load dropdowns ───────────────────────────────────────────────────────
$assignedAssets  = [];
$activeEmployees = [];
try {
    $assignedAssets = $pdo->query(
        "SELECT a.id, a.asset_name, a.model_number,
                CONCAT(u.first_name, ' ', u.last_name) AS current_owner
         FROM assets a
         JOIN users u ON u.id = a.assigned_to
         WHERE a.status = 'assigned'
         ORDER BY a.asset_name ASC"
    )->fetchAll();

    $activeEmployees = $pdo->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, employee_id, department
         FROM users
         WHERE status = 'active' AND role = 'employee'
         ORDER BY first_name ASC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Transfer load error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Transfer Asset — ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <?php if ($flash): ?>
    <div class="flash-container">
      <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show auto-dismiss" role="alert">
        <?= sanitize($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-arrow-left-right me-2"></i>Transfer Asset</h4>
    <a href="index.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= sanitize($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <?php if (empty($assignedAssets)): ?>
        <div class="alert alert-info mb-0">
          <i class="bi bi-info-circle me-2"></i>No assigned assets available for transfer.
        </div>
      <?php else: ?>
        <form method="POST" action="transfer.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

          <div class="mb-3">
            <label for="asset_id" class="form-label fw-semibold">
              Asset <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="asset_id" name="asset_id" required>
              <option value="">— Select Assigned Asset —</option>
              <?php foreach ($assignedAssets as $a): ?>
                <option value="<?= $a['id'] ?>"
                  <?= (int)$a['id'] === $preselectedAsset ? 'selected' : '' ?>>
                  <?= sanitize($a['asset_name']) ?>
                  <?= $a['model_number'] ? ' (' . sanitize($a['model_number']) . ')' : '' ?>
                  — Currently held by: <?= sanitize($a['current_owner']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="new_employee_id" class="form-label fw-semibold">
              New Employee <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="new_employee_id" name="new_employee_id" required>
              <option value="">— Select New Employee —</option>
              <?php foreach ($activeEmployees as $emp): ?>
                <option value="<?= $emp['id'] ?>">
                  <?= sanitize($emp['full_name']) ?>
                  <?= $emp['employee_id'] ? ' [' . sanitize($emp['employee_id']) . ']' : '' ?>
                  <?= $emp['department'] ? ' — ' . sanitize($emp['department']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label for="notes" class="form-label fw-semibold">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2"
                      placeholder="Optional notes about this transfer" maxlength="500"></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-arrow-left-right me-1"></i>Transfer Asset
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
