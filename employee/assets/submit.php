<?php
/**
 * File: employee/assets/submit.php
 * Description: Employee form to submit a personally held or company asset with file upload and email notifications.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];
$errors        = [];
$formData      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: ' . SITE_URL . '/employee/assets/submit.php');
        exit;
    }

    // Collect & sanitise
    $formData['category_id']   = (int)($_POST['category_id'] ?? 0);
    $formData['asset_name']    = sanitize($_POST['asset_name']    ?? '');
    $formData['model_number']  = sanitize($_POST['model_number']  ?? '');
    $formData['serial_number'] = sanitize($_POST['serial_number'] ?? '');
    $formData['receive_date']  = sanitize($_POST['receive_date']  ?? '');
    $formData['purchased_by']  = sanitize($_POST['purchased_by']  ?? '');
    $consentChecked            = isset($_POST['consent']) ? true : false;

    // Validate
    if (!$formData['category_id'])  $errors[] = 'Please select a category.';
    if (!$formData['asset_name'])   $errors[] = 'Asset name is required.';
    if (!$formData['receive_date']) $errors[] = 'Receive date is required.';
    if (!in_array($formData['purchased_by'], ['me', 'company'])) $errors[] = 'Please select who purchased the asset.';
    if (!$consentChecked) $errors[] = 'You must confirm the consent statement.';

    // File upload — required only when purchased by employee
    $billPath = null;
    if ($formData['purchased_by'] === 'me') {
        if (empty($_FILES['bill']['name'])) {
            $errors[] = 'Please upload the bill/receipt for assets purchased by you.';
        } else {
            $file      = $_FILES['bill'];
            $maxBytes  = 5 * 1024 * 1024; // 5 MB
            $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
            $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extMap      = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

            if ($file['size'] > $maxBytes) {
                $errors[] = 'Bill file must not exceed 5 MB.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error. Please try again.';
            } else {
                // MIME check via finfo
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);
                if (!in_array($mimeType, $allowedMime)) {
                    $errors[] = 'Only PDF, JPG, and PNG files are allowed for the bill.';
                } else {
                    $newName  = uniqid('bill_', true) . '.' . $ext;
                    $destDir  = defined('UPLOAD_PATH') ? rtrim(UPLOAD_PATH, '/') . '/bills/' : __DIR__ . '/../../uploads/bills/';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    if (!move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
                        $errors[] = 'Failed to save uploaded file. Please try again.';
                    } else {
                        $billPath = 'bills/' . $newName;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert asset
            $stmt = $pdo->prepare("INSERT INTO assets
                                   (category_id, asset_name, model_number, serial_number, receive_date,
                                    purchased_by, bill_file, status, assigned_to, submitted_by, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned', ?, ?, NOW())");
            $stmt->execute([
                $formData['category_id'],
                $formData['asset_name'],
                $formData['model_number']  ?: null,
                $formData['serial_number'] ?: null,
                $formData['receive_date'],
                $formData['purchased_by'],
                $billPath,
                $currentUserId,
                $currentUserId,
            ]);
            $assetId = (int)$pdo->lastInsertId();

            // Insert asset history
            $pdo->prepare("INSERT INTO asset_history (asset_id, action, to_user, created_by, created_at)
                           VALUES (?, 'submitted', ?, ?, NOW())")
                ->execute([$assetId, $currentUserId, $currentUserId]);

            $pdo->commit();

            // Notify admin/HR
            $notifMsg  = ($_SESSION['first_name'] ?? 'An employee') . ' submitted a new asset: ' . $formData['asset_name'];
            $notifLink = SITE_URL . '/admin/assets/view.php?id=' . $assetId;
            sendNotification(null, 'asset_submitted', $notifMsg, $notifLink);

            // Email admins and HR
            $emailStmt = $pdo->query("SELECT email, first_name FROM users WHERE role IN ('admin','hr') AND status='active'");
            $recipients = $emailStmt->fetchAll(PDO::FETCH_ASSOC);
            $subject    = SITE_NAME . ': New Asset Submitted';
            foreach ($recipients as $recipient) {
                if (function_exists('emailAssetSubmission')) {
                    $body = emailAssetSubmission(trim($_SESSION['first_name'] ?? 'An employee'), $formData['asset_name']);
                } else {
                    $body = '<p>Hi ' . htmlspecialchars($recipient['first_name']) . ',</p>'
                          . '<p><strong>' . htmlspecialchars($_SESSION['first_name'] ?? 'An employee') . '</strong> submitted a new asset: '
                          . htmlspecialchars($formData['asset_name']) . '.</p>'
                          . '<p><a href="' . $notifLink . '">View Asset</a></p>';
                }
                sendEmail($recipient['email'], $subject, $body);
            }

            logActivity($currentUserId, 'asset_submitted', 'Submitted asset: ' . $formData['asset_name']);
            flashMessage('success', 'Asset submitted successfully!');
            header('Location: ' . SITE_URL . '/employee/assets/my_assets.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Submit Asset';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-box-arrow-in-down me-2 text-primary"></i>Submit Asset</h4>
      <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to My Assets
      </a>
    </div>

    <?= getFlash() ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-header bg-light fw-semibold">
        <i class="bi bi-clipboard-fill me-1"></i>Asset Details
      </div>
      <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" id="submitAssetForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

          <div class="row g-4">

            <!-- Category -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select <?= !empty($errors) && !($formData['category_id'] ?? 0) ? 'is-invalid' : '' ?>" required>
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= ($formData['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select a category.</div>
            </div>

            <!-- Asset Name -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Asset Name <span class="text-danger">*</span></label>
              <input type="text" name="asset_name" class="form-control" required
                     placeholder="e.g. Dell Laptop XPS 15"
                     value="<?= htmlspecialchars($formData['asset_name'] ?? '') ?>">
              <div class="invalid-feedback">Asset name is required.</div>
            </div>

            <!-- Model Number -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Model Number</label>
              <input type="text" name="model_number" class="form-control"
                     placeholder="e.g. XPS-9500"
                     value="<?= htmlspecialchars($formData['model_number'] ?? '') ?>">
            </div>

            <!-- Serial Number -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Serial Number</label>
              <input type="text" name="serial_number" class="form-control"
                     placeholder="e.g. SN-1234567890"
                     value="<?= htmlspecialchars($formData['serial_number'] ?? '') ?>">
            </div>

            <!-- Receive Date -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Receive Date <span class="text-danger">*</span></label>
              <input type="date" name="receive_date" class="form-control" required
                     max="<?= date('Y-m-d') ?>"
                     value="<?= htmlspecialchars($formData['receive_date'] ?? '') ?>">
              <div class="invalid-feedback">Receive date is required.</div>
            </div>

            <!-- Purchased By -->
            <div class="col-md-6">
              <label class="form-label fw-semibold d-block">Purchased By <span class="text-danger">*</span></label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="purchased_by" id="purchasedMe"
                       value="me" <?= ($formData['purchased_by'] ?? '') === 'me' ? 'checked' : '' ?>>
                <label class="form-check-label" for="purchasedMe">
                  <i class="bi bi-person me-1"></i>Me (Personal Purchase)
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="purchased_by" id="purchasedCompany"
                       value="company" <?= ($formData['purchased_by'] ?? '') === 'company' ? 'checked' : '' ?>>
                <label class="form-check-label" for="purchasedCompany">
                  <i class="bi bi-building me-1"></i>Company
                </label>
              </div>
            </div>

            <!-- Bill Upload (shown only if purchased_by = me) -->
            <div class="col-md-12" id="billUploadSection" style="display:none;">
              <label class="form-label fw-semibold">Upload Bill / Receipt <span class="text-danger">*</span></label>
              <input type="file" name="bill" class="form-control" accept=".pdf,.jpg,.jpeg,.png" id="billInput">
              <div class="form-text">Accepted: PDF, JPG, PNG. Maximum size: 5 MB.</div>
            </div>

            <!-- Consent -->
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input <?= !empty($errors) && !($consentChecked ?? false) ? 'is-invalid' : '' ?>"
                       type="checkbox" name="consent" id="consentCheck"
                       <?= !empty($formData) && isset($_POST['consent']) ? 'checked' : '' ?> required>
                <label class="form-check-label" for="consentCheck">
                  I confirm that this asset is in my possession and I am responsible for its safety and condition.
                </label>
                <div class="invalid-feedback">You must accept the consent statement.</div>
              </div>
            </div>

          </div><!-- /row -->

          <hr class="my-4">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-send-fill me-1"></i>Submit Asset
            </button>
            <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="btn btn-outline-secondary">
              <i class="bi bi-x-circle me-1"></i>Cancel
            </a>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  // Toggle bill upload section
  function toggleBill() {
    const val = $('input[name="purchased_by"]:checked').val();
    if (val === 'me') {
      $('#billUploadSection').slideDown(200);
      $('#billInput').prop('required', true);
    } else {
      $('#billUploadSection').slideUp(200);
      $('#billInput').prop('required', false).val('');
    }
  }

  $('input[name="purchased_by"]').on('change', toggleBill);
  toggleBill(); // Run on load in case of POST-back

  // Client-side file size check
  $('#billInput').on('change', function () {
    const maxBytes = 5 * 1024 * 1024;
    if (this.files[0] && this.files[0].size > maxBytes) {
      Swal.fire({ icon: 'warning', title: 'File too large', text: 'The bill file must not exceed 5 MB.' });
      $(this).val('');
    }
  });

  // Bootstrap validation
  $('#submitAssetForm').on('submit', function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass('was-validated');
  });
});
</script>
