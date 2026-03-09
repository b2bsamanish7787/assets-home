<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/return.php
 * Description: Process the return of an assigned asset
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$preselectedAsset = (int)($_GET['id'] ?? 0);
$errors = [];

// ── POST: process return ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: return.php');
        exit;
    }

    $assetId = (int)($_POST['asset_id'] ?? 0);
    $notes   = sanitize($_POST['notes'] ?? '');

    if (!$assetId) {
        $errors[] = 'Please select an asset to return.';
    }

    $asset = null;
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
            }
        } catch (Exception $e) {
            error_log('Return validation error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $fromUserId = (int)$asset['assigned_to'];
            $assetName  = $asset['asset_name'];

            // Update asset status to returned and clear assignment
            $pdo->prepare(
                "UPDATE assets SET assigned_to = NULL, status = 'returned' WHERE id = ?"
            )->execute([$assetId]);

            // Insert asset history
            $pdo->prepare(
                "INSERT INTO asset_history (asset_id, action, from_user, notes, created_by)
                 VALUES (?, 'returned', ?, ?, ?)"
            )->execute([$assetId, $fromUserId, $notes, $_SESSION['user_id']]);

            $pdo->commit();

            logActivity(
                (int)$_SESSION['user_id'],
                'return_asset',
                "Returned asset \"{$assetName}\" (ID: {$assetId}) from user ID {$fromUserId}"
            );

            flashMessage('success', "Asset \"{$assetName}\" has been returned successfully.");
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Return asset error: ' . $e->getMessage());
            $errors[] = 'Database error. Return failed.';
        }
    }
}

// ── GET: load assigned assets ─────────────────────────────────────────────────
$assignedAssets = [];
try {
    $assignedAssets = $pdo->query(
        "SELECT a.id, a.asset_name, a.model_number,
                CONCAT(u.first_name, ' ', u.last_name) AS current_owner
         FROM assets a
         JOIN users u ON u.id = a.assigned_to
         WHERE a.status = 'assigned'
         ORDER BY a.asset_name ASC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Return load error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Return Asset — ' . SITE_NAME;

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
    <h4><i class="bi bi-box-arrow-in-left me-2"></i>Return Asset</h4>
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
          <i class="bi bi-info-circle me-2"></i>No assigned assets available for return.
        </div>
      <?php else: ?>
        <form method="POST" action="return.php" novalidate>
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
                  — Held by: <?= sanitize($a['current_owner']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label for="notes" class="form-label fw-semibold">Return Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"
                      placeholder="Condition of asset, reason for return, any damage, etc."
                      maxlength="1000"></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning text-dark">
              <i class="bi bi-box-arrow-in-left me-1"></i>Process Return
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
