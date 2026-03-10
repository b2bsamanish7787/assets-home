<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/assign.php
 * Description: Assign an available asset to an active employee
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$preselectedAsset = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST: process assignment ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: assign.php');
        exit;
    }

    $assetId    = (int)($_POST['asset_id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $notes      = sanitize($_POST['notes'] ?? '');

    if (!$assetId)    $errors[] = 'Please select an asset.';
    if (!$employeeId) $errors[] = 'Please select an employee.';

    if (empty($errors)) {
        try {
            // Verify asset is available
            $assetStmt = $pdo->prepare("SELECT id, asset_name, status FROM assets WHERE id = ?");
            $assetStmt->execute([$assetId]);
            $asset = $assetStmt->fetch();

            if (!$asset || !in_array($asset['status'], ['available', 'returned'], true)) {
                $errors[] = 'Selected asset is not available for assignment.';
            }

            // Verify employee is active
            $empStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? AND status = 'active'");
            $empStmt->execute([$employeeId]);
            $employee = $empStmt->fetch();

            if (!$employee) {
                $errors[] = 'Selected employee is not valid or inactive.';
            }
        } catch (Exception $e) {
            error_log('Assign asset validation error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update asset
            $pdo->prepare(
                "UPDATE assets SET assigned_to = ?, status = 'assigned' WHERE id = ?"
            )->execute([$employeeId, $assetId]);

            // Insert asset history
            $pdo->prepare(
                "INSERT INTO asset_history (asset_id, action, to_user, notes, created_by)
                 VALUES (?, 'assigned', ?, ?, ?)"
            )->execute([$assetId, $employeeId, $notes, $_SESSION['user_id']]);

            // Create consent record so the employee must acknowledge receipt
            $pdo->prepare(
                "INSERT INTO transfer_consent (asset_id, from_user, to_user, type, status)
                 VALUES (?, ?, ?, 'assignment', 'pending')"
            )->execute([$assetId, (int)$_SESSION['user_id'], $employeeId]);

            $pdo->commit();

            $empName  = trim($employee['first_name'] . ' ' . $employee['last_name']);
            $assetName = $asset['asset_name'];

            // Notify the employee — link to consent page so they can confirm receipt
            sendNotification(
                $employeeId,
                'asset_assigned',
                "Asset \"{$assetName}\" has been assigned to you. Please confirm receipt.",
                SITE_URL . '/employee/consent.php'
            );

            logActivity(
                (int)$_SESSION['user_id'],
                'assign_asset',
                "Assigned asset \"{$assetName}\" (ID: {$assetId}) to {$empName} (ID: {$employeeId})"
            );

            flashMessage('success', "Asset \"{$assetName}\" assigned to {$empName} successfully.");
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Assign asset error: ' . $e->getMessage());
            $errors[] = 'Database error. Assignment failed.';
        }
    }
}

// ── GET: load dropdowns ───────────────────────────────────────────────────────
$availableAssets = [];
$activeEmployees = [];
try {
    $availableAssets = $pdo->query(
        "SELECT a.id, a.asset_name, a.model_number, a.serial_number, c.name AS category_name
         FROM assets a
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.status IN ('available', 'returned')
         ORDER BY a.asset_name ASC"
    )->fetchAll();

    $activeEmployees = $pdo->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, employee_id, department
         FROM users
         WHERE status = 'active' AND role = 'employee'
         ORDER BY first_name ASC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Assign asset load error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Assign Asset — ' . SITE_NAME;

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
    <h4><i class="bi bi-person-check me-2"></i>Assign Asset</h4>
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
      <?php if (empty($availableAssets)): ?>
        <div class="alert alert-info mb-0">
          <i class="bi bi-info-circle me-2"></i>No available assets to assign at this time.
        </div>
      <?php else: ?>
        <form method="POST" action="assign.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

          <div class="mb-3">
            <label for="asset_id" class="form-label fw-semibold">
              Asset <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="asset_id" name="asset_id" required>
              <option value="">— Select Available Asset —</option>
              <?php foreach ($availableAssets as $a): ?>
                <option value="<?= $a['id'] ?>"
                  <?= (int)($a['id']) === $preselectedAsset ? 'selected' : '' ?>>
                  <?= sanitize($a['asset_name']) ?>
                  <?= $a['model_number'] ? '(' . sanitize($a['model_number']) . ')' : '' ?>
                  <?= $a['category_name'] ? ' — ' . sanitize($a['category_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="employee_id" class="form-label fw-semibold">
              Employee <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="employee_id" name="employee_id" required>
              <option value="">— Select Employee —</option>
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
                      placeholder="Optional notes about this assignment" maxlength="500"></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-circle me-1"></i>Assign Asset
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
