<?php
/**
 * File: employee/service/new.php
 * Description: Form for employees to submit a service/repair request for one of their assigned assets.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];
$errors        = [];
$formData      = [];

// Pre-select asset from query string
$preselectedAssetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;

// Fetch employee's assigned assets
$assetsStmt = $pdo->prepare("SELECT a.id, a.asset_name, a.model_number, c.name AS category_name
                              FROM assets a
                              LEFT JOIN categories c ON c.id = a.category_id
                              WHERE a.assigned_to = ? AND a.status IN ('assigned', 'available')
                              ORDER BY a.asset_name");
$assetsStmt->execute([$currentUserId]);
$myAssets = $assetsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: ' . SITE_URL . '/employee/service/new.php');
        exit;
    }

    $formData['asset_id']      = (int)($_POST['asset_id']      ?? 0);
    $formData['problem']       = sanitize($_POST['problem']       ?? '');
    $formData['problem_since'] = sanitize($_POST['problem_since'] ?? '');
    $formData['impact']        = sanitize($_POST['impact']        ?? '');
    $formData['approx_amount'] = sanitize($_POST['approx_amount'] ?? '');

    if (!$formData['asset_id'])      $errors[] = 'Please select an asset.';
    if (!$formData['problem'])       $errors[] = 'Problem description is required.';
    if (!$formData['problem_since']) $errors[] = 'Problem since date is required.';

    // Verify the selected asset actually belongs to this employee
    if ($formData['asset_id']) {
        $verifyStmt = $pdo->prepare("SELECT id FROM assets WHERE id = ? AND assigned_to = ? LIMIT 1");
        $verifyStmt->execute([$formData['asset_id'], $currentUserId]);
        if (!$verifyStmt->fetch()) {
            $errors[] = 'Invalid asset selection.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO service_requests
                                   (employee_id, asset_id, problem_description, problem_since,
                                    impact_on_work, approx_amount, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $currentUserId,
                $formData['asset_id'],
                $formData['problem'],
                $formData['problem_since'],
                $formData['impact'] ?: null,
                $formData['approx_amount'] !== '' ? $formData['approx_amount'] : null,
            ]);
            $serviceId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Fetch asset name for notification
            $assetNameRow = $pdo->prepare("SELECT asset_name FROM assets WHERE id = ?");
            $assetNameRow->execute([$formData['asset_id']]);
            $assetName    = $assetNameRow->fetchColumn() ?: 'Asset';

            $employeeName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            $notifMsg     = $employeeName . ' raised a service request for: ' . $assetName;
            $notifLink    = SITE_URL . '/admin/service/view.php?id=' . $serviceId;
            sendNotification(null, 'service_request', $notifMsg, $notifLink);

            $emailStmt  = $pdo->query("SELECT email, first_name FROM users WHERE role IN ('admin','hr') AND status='active'");
            $recipients = $emailStmt->fetchAll(PDO::FETCH_ASSOC);
            $subject    = SITE_NAME . ': Service Request – ' . $assetName;
            foreach ($recipients as $recipient) {
                if (function_exists('emailServiceRequest')) {
                    $body = emailServiceRequest($employeeName, $assetName);
                } else {
                    $body = '<p>Hi ' . htmlspecialchars($recipient['first_name']) . ',</p>'
                          . '<p><strong>' . htmlspecialchars($employeeName) . '</strong> raised a service request.</p>'
                          . '<p><strong>Asset:</strong> ' . htmlspecialchars($assetName) . '</p>'
                          . '<p><strong>Problem:</strong> ' . htmlspecialchars($formData['problem']) . '</p>'
                          . '<p><a href="' . $notifLink . '">View Service Request</a></p>';
                }
                sendEmail($recipient['email'], $subject, $body);
            }

            // CC employee's manager
            $mgrStmt = $pdo->prepare("SELECT manager_name, manager_email FROM users WHERE id = ? LIMIT 1");
            $mgrStmt->execute([$currentUserId]);
            $mgr = $mgrStmt->fetch(PDO::FETCH_ASSOC);
            if ($mgr && !empty($mgr['manager_email'])) {
                $mgrBody = emailManagerServiceRequest(
                    $mgr['manager_name'] ?: 'Manager',
                    trim($employeeName),
                    $assetName,
                    $notifLink
                );
                sendEmail($mgr['manager_email'], $subject, $mgrBody);
            }

            logActivity($currentUserId, 'service_request', 'Submitted service request for: ' . $assetName);
            flashMessage('success', 'Service request submitted successfully!');
            header('Location: ' . SITE_URL . '/employee/service/my_service.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

$pageTitle = 'New Service Request';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-tools me-2 text-primary"></i>New Service Request</h4>
      <a href="<?= SITE_URL ?>/employee/service/my_service.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>My Service Requests
      </a>
    </div>

    <?= getFlash() ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($myAssets)): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
      <i class="bi bi-info-circle-fill fs-5"></i>
      <div>You have no active assets assigned to you.
        <a href="<?= SITE_URL ?>/employee/assets/submit.php" class="alert-link">Submit an asset</a> first.
      </div>
    </div>
    <?php else: ?>

    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-wrench-adjustable me-1"></i>Service Request Details
          </div>
          <div class="card-body">
            <form method="POST" action="" id="serviceForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

              <div class="row g-4">

                <!-- Asset -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Asset <span class="text-danger">*</span></label>
                  <select name="asset_id" class="form-select" required>
                    <option value="">— Select Asset —</option>
                    <?php foreach ($myAssets as $asset):
                      $selected = (($formData['asset_id'] ?? $preselectedAssetId) == $asset['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= $asset['id'] ?>" <?= $selected ?>>
                      <?= htmlspecialchars($asset['asset_name']) ?>
                      <?= $asset['model_number'] ? ' (' . htmlspecialchars($asset['model_number']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback">Please select an asset.</div>
                </div>

                <!-- Problem Since -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Problem Since <span class="text-danger">*</span></label>
                  <input type="date" name="problem_since" class="form-control" required
                         max="<?= date('Y-m-d') ?>"
                         value="<?= htmlspecialchars($formData['problem_since'] ?? '') ?>">
                  <div class="invalid-feedback">Please select the date when the problem started.</div>
                </div>

                <!-- Problem Description -->
                <div class="col-12">
                  <label class="form-label fw-semibold">Problem Description <span class="text-danger">*</span></label>
                  <textarea name="problem" class="form-control" rows="4" required
                            placeholder="Describe the issue in detail..."><?= htmlspecialchars($formData['problem'] ?? '') ?></textarea>
                  <div class="invalid-feedback">Problem description is required.</div>
                </div>

                <!-- Impact on Work -->
                <div class="col-12">
                  <label class="form-label fw-semibold">Impact on Work</label>
                  <textarea name="impact" class="form-control" rows="3"
                            placeholder="How is this issue affecting your work?"><?= htmlspecialchars($formData['impact'] ?? '') ?></textarea>
                </div>

                <!-- Approx Service Amount -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Approx. Service Amount (₹)</label>
                  <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" name="approx_amount" class="form-control" min="0" step="0.01"
                           placeholder="0.00"
                           value="<?= htmlspecialchars($formData['approx_amount'] ?? '') ?>">
                  </div>
                  <div class="form-text">Leave blank if unknown.</div>
                </div>

              </div><!-- /row -->

              <hr class="mt-4">
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-send-fill me-1"></i>Submit Service Request
                </button>
                <a href="<?= SITE_URL ?>/employee/service/my_service.php" class="btn btn-outline-secondary">
                  <i class="bi bi-x-circle me-1"></i>Cancel
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

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  $('#serviceForm').on('submit', function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass('was-validated');
  });
});
</script>
