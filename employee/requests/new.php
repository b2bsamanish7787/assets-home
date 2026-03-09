<?php
/**
 * File: employee/requests/new.php
 * Description: Form for employees to submit a new asset request to HR/admin.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];
$errors        = [];
$formData      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: ' . SITE_URL . '/employee/requests/new.php');
        exit;
    }

    $formData['requirement']  = sanitize($_POST['requirement']  ?? '');
    $formData['description']  = sanitize($_POST['description']  ?? '');

    if (!$formData['requirement']) $errors[] = 'Asset requirement is required.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO asset_requests (employee_id, requirement, description, status, created_at)
                                   VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $currentUserId,
                $formData['requirement'],
                $formData['description'] ?: null,
            ]);
            $requestId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Notify & email
            $employeeName = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
            $notifMsg     = trim($employeeName) . ' submitted a new asset request: ' . $formData['requirement'];
            $notifLink    = SITE_URL . '/admin/requests/view.php?id=' . $requestId;
            sendNotification(null, 'asset_request', $notifMsg, $notifLink);

            $emailStmt  = $pdo->query("SELECT email, first_name FROM users WHERE role IN ('admin','hr','manager') AND status='active'");
            $recipients = $emailStmt->fetchAll(PDO::FETCH_ASSOC);
            $subject    = SITE_NAME . ': New Asset Request from ' . trim($employeeName);
            foreach ($recipients as $recipient) {
                if (function_exists('emailAssetRequest')) {
                    $body = emailAssetRequest($recipient['first_name'], trim($employeeName), $formData['requirement'], $notifLink);
                } else {
                    $body = '<p>Hi ' . htmlspecialchars($recipient['first_name']) . ',</p>'
                          . '<p><strong>' . htmlspecialchars(trim($employeeName)) . '</strong> submitted a new asset request.</p>'
                          . '<p><strong>Requirement:</strong> ' . htmlspecialchars($formData['requirement']) . '</p>'
                          . '<p><a href="' . $notifLink . '">View Request</a></p>';
                }
                sendEmail($recipient['email'], $subject, $body);
            }

            logActivity($currentUserId, 'asset_request', 'Submitted asset request: ' . $formData['requirement']);
            flashMessage('success', 'Your asset request has been submitted successfully!');
            header('Location: ' . SITE_URL . '/employee/requests/my_requests.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

$pageTitle = 'New Asset Request';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-clipboard-plus me-2 text-primary"></i>New Asset Request</h4>
      <a href="<?= SITE_URL ?>/employee/requests/my_requests.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>My Requests
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

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-pencil-fill me-1"></i>Request Details
          </div>
          <div class="card-body">
            <form method="POST" action="" id="requestForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

              <!-- Asset Requirement -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Asset Requirement <span class="text-danger">*</span></label>
                <input type="text" name="requirement" class="form-control" required
                       placeholder="e.g. Laptop, Mouse, Headset..."
                       value="<?= htmlspecialchars($formData['requirement'] ?? '') ?>">
                <div class="form-text">Briefly describe what asset you need.</div>
                <div class="invalid-feedback">Asset requirement is required.</div>
              </div>

              <!-- Description / Reason -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Description / Reason</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Explain why you need this asset and how it will be used..."><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
              </div>

              <hr>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-send-fill me-1"></i>Submit Request
                </button>
                <a href="<?= SITE_URL ?>/employee/requests/my_requests.php" class="btn btn-outline-secondary">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  $('#requestForm').on('submit', function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass('was-validated');
  });
});
</script>
