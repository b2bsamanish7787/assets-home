<?php
/**
 * File: employee/consent.php
 * Description: Employee consent form for accepting a pending asset transfer.
 * Author: Buzznation IT Team
 */

require_once '../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];
$errors        = [];

// Fetch the single pending consent for this employee
$stmt = $pdo->prepare("SELECT tc.id AS consent_id, tc.asset_id, tc.from_user,
                               a.asset_name, a.model_number, a.serial_number,
                               c.name AS category_name,
                               CONCAT(u.first_name,' ',u.last_name) AS from_user_name
                        FROM transfer_consent tc
                        JOIN assets a ON a.id = tc.asset_id
                        LEFT JOIN categories c ON c.id = a.category_id
                        LEFT JOIN users u ON u.id = tc.from_user
                        WHERE tc.to_user = ? AND tc.status = 'pending'
                        LIMIT 1");
$stmt->execute([$currentUserId]);
$consent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $consent) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: ' . SITE_URL . '/employee/consent.php');
        exit;
    }

    $receiveDate   = sanitize($_POST['receive_date'] ?? '');
    $consentGiven  = isset($_POST['consent_check']) ? true : false;
    $consentId     = (int)($_POST['consent_id'] ?? 0);

    if (!$receiveDate)  $errors[] = 'Receive date is required.';
    if (!$consentGiven) $errors[] = 'You must confirm receipt of the asset.';
    if ($consentId !== (int)$consent['consent_id']) $errors[] = 'Invalid consent record.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Mark consent as accepted
            $pdo->prepare("UPDATE transfer_consent
                           SET consent_given = 1, status = 'accepted', receive_date = ?
                           WHERE id = ?")
                ->execute([$receiveDate, $consentId]);

            // Update asset ownership
            $pdo->prepare("UPDATE assets SET assigned_to = ?, status = 'assigned' WHERE id = ?")
                ->execute([$currentUserId, $consent['asset_id']]);

            // Record in asset history
            $pdo->prepare("INSERT INTO asset_history (asset_id, action, from_user, to_user, created_by, created_at)
                           VALUES (?, 'transferred', ?, ?, ?, NOW())")
                ->execute([$consent['asset_id'], $consent['from_user'], $currentUserId, $currentUserId]);

            $pdo->commit();

            // Notify admin
            $employeeName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            $notifMsg     = $employeeName . ' accepted transfer of asset: ' . $consent['asset_name'];
            $notifLink    = SITE_URL . '/admin/assets/view.php?id=' . $consent['asset_id'];

            // Notify the admin user(s) by fetching admin IDs
            $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1");
            $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
            $adminId   = $adminUser ? (int)$adminUser['id'] : null;
            sendNotification($adminId, 'consent_given', $notifMsg, $notifLink);

            logActivity($currentUserId, 'consent_given', 'Accepted transfer of asset: ' . $consent['asset_name']);
            flashMessage('success', 'You have successfully accepted the asset transfer for <strong>' . htmlspecialchars($consent['asset_name']) . '</strong>.');
            header('Location: ' . SITE_URL . '/employee/assets/my_assets.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Asset Transfer Consent';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-pen-fill me-2 text-primary"></i>Asset Transfer Consent</h4>
      <a href="<?= SITE_URL ?>/employee/dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
      </a>
    </div>

    <?= getFlash() ?>

    <?php if (!$consent): ?>
    <!-- No pending consent -->
    <div class="card shadow-sm">
      <div class="card-body text-center py-6">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>
        <h5 class="mt-3 text-muted">No Pending Transfers</h5>
        <p class="text-muted mb-3">You have no pending asset transfer consents at this time.</p>
        <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-box-seam me-1"></i>View My Assets
        </a>
      </div>
    </div>

    <?php else: ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- Asset Details (Read-only) -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-warning text-dark d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span class="fw-semibold">Pending Asset Transfer — Action Required</span>
          </div>
          <div class="card-body">
            <p class="text-muted mb-3">
              The following asset is being transferred to you. Please review the details carefully and confirm receipt.
            </p>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label text-muted small mb-0">Asset Name</label>
                <div class="fw-semibold fs-6"><?= htmlspecialchars($consent['asset_name']) ?></div>
              </div>
              <div class="col-sm-6">
                <label class="form-label text-muted small mb-0">Category</label>
                <div class="fw-semibold fs-6"><?= htmlspecialchars($consent['category_name'] ?? '—') ?></div>
              </div>
              <div class="col-sm-6">
                <label class="form-label text-muted small mb-0">Model Number</label>
                <div class="fw-semibold fs-6"><?= htmlspecialchars($consent['model_number'] ?? '—') ?></div>
              </div>
              <div class="col-sm-6">
                <label class="form-label text-muted small mb-0">Serial Number</label>
                <div class="fw-semibold fs-6"><?= htmlspecialchars($consent['serial_number'] ?? '—') ?></div>
              </div>
              <div class="col-sm-6">
                <label class="form-label text-muted small mb-0">Transferred From</label>
                <div class="fw-semibold fs-6"><?= htmlspecialchars($consent['from_user_name'] ?? 'Admin / HR') ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Consent Form -->
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-clipboard-check me-1"></i>Confirm Receipt
          </div>
          <div class="card-body">
            <form method="POST" action="" id="consentForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
              <input type="hidden" name="consent_id" value="<?= (int)$consent['consent_id'] ?>">

              <!-- Receive Date -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Receive Date <span class="text-danger">*</span></label>
                <input type="date" name="receive_date" class="form-control" required
                       max="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($_POST['receive_date'] ?? '') ?>">
                <div class="form-text">Enter the date you physically received this asset.</div>
                <div class="invalid-feedback">Receive date is required.</div>
              </div>

              <!-- Consent Checkbox -->
              <div class="mb-4">
                <div class="form-check p-3 border rounded bg-light">
                  <input class="form-check-input" type="checkbox" name="consent_check"
                         id="consentCheckBox" required
                         <?= isset($_POST['consent_check']) ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="consentCheckBox">
                    I confirm that I have received <strong><?= htmlspecialchars($consent['asset_name']) ?></strong>
                    in proper condition and accept full responsibility for its safety, security, and condition.
                  </label>
                  <div class="invalid-feedback">You must confirm receipt of the asset.</div>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                  <i class="bi bi-check-circle-fill me-1"></i>Accept Transfer
                </button>
                <a href="<?= SITE_URL ?>/employee/dashboard.php" class="btn btn-outline-secondary">
                  <i class="bi bi-x-circle me-1"></i>Decide Later
                </a>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>

    <?php endif; ?>

  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(function () {
  $('#consentForm').on('submit', function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    } else {
      // Confirm before submitting
      e.preventDefault();
      Swal.fire({
        icon: 'question',
        title: 'Confirm Receipt',
        text: 'Are you sure you want to accept this asset transfer? This action cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Accept',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#198754',
      }).then(result => {
        if (result.isConfirmed) {
          this.submit();
        }
      });
    }
    $(this).addClass('was-validated');
  });
});
</script>
