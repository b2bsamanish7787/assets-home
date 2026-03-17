<?php
/**
 * File: employee/assets/edit.php
 * Description: Employee form to edit a pending (unapproved) asset submission.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];
$assetId       = (int)($_GET['id'] ?? $_POST['asset_id'] ?? 0);
$errors        = [];

if (!$assetId) {
    flashMessage('error', 'Invalid asset ID.');
    header('Location: my_assets.php');
    exit;
}

// Load only if this asset is pending AND submitted by the current employee
$assetStmt = $pdo->prepare(
    "SELECT a.*, c.name AS category_name
     FROM assets a
     LEFT JOIN categories c ON c.id = a.category_id
     WHERE a.id = ? AND a.submitted_by = ? AND a.approval_status = 'pending'"
);
$assetStmt->execute([$assetId, $currentUserId]);
$asset = $assetStmt->fetch(PDO::FETCH_ASSOC);

if (!$asset) {
    flashMessage('danger', 'Asset not found, or it has already been approved and can no longer be edited.');
    header('Location: my_assets.php');
    exit;
}

// Pre-fill form from existing asset
$formData = $asset;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: edit.php?id=' . $assetId);
        exit;
    }

    // Collect & sanitise
    $formData['category_id']   = (int)($_POST['category_id'] ?? 0);
    $formData['asset_name']    = sanitize($_POST['asset_name']    ?? '');
    $formData['model_number']  = sanitize($_POST['model_number']  ?? '');
    $formData['serial_number'] = sanitize($_POST['serial_number'] ?? '');
    $formData['receive_date']  = sanitize($_POST['receive_date']  ?? '');
    $formData['purchased_by']  = sanitize($_POST['purchased_by']  ?? '');

    // Validate
    if (!$formData['category_id'])  $errors[] = 'Please select a category.';
    if (!$formData['asset_name'])   $errors[] = 'Asset name is required.';
    if (!$formData['receive_date']) $errors[] = 'Receive date is required.';
    if (!in_array($formData['purchased_by'], ['me', 'company'])) $errors[] = 'Please select who purchased the asset.';

    // Resolve purchaser name
    if ($formData['purchased_by'] === 'me') {
        $firstName = trim($_SESSION['first_name'] ?? '');
        $lastName  = trim($_SESSION['last_name']  ?? '');
        $purchasedByName = trim($firstName . ' ' . $lastName) ?: 'Employee';
    } else {
        $purchasedByName = COMPANY_NAME;
    }

    // Handle optional bill upload (replace existing)
    $billPath = $asset['bill_file']; // keep existing by default
    if (!empty($_FILES['bill']['name'])) {
        $file         = $_FILES['bill'];
        $maxBytes     = 5 * 1024 * 1024; // 5 MB
        $allowedExts  = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error (code ' . $file['error'] . '). Please try again.';
        } elseif ($file['size'] > $maxBytes) {
            $errors[] = 'Bill file must not exceed 5 MB.';
        } elseif (!in_array($ext, $allowedExts, true)) {
            $errors[] = 'Only PDF, JPG, and PNG files are allowed for the bill.';
        } else {
            $detectedMime = false;
            if (function_exists('finfo_open')) {
                try {
                    $finfo        = new finfo(FILEINFO_MIME_TYPE);
                    $detectedMime = $finfo->file($file['tmp_name']);
                } catch (Exception $e) {
                    $detectedMime = false;
                }
            }
            if ($detectedMime && !in_array($detectedMime, $allowedMimes, true)) {
                $errors[] = 'Only PDF, JPG, and PNG files are allowed for the bill.';
            } else {
                $newName = uniqid('bill_', true) . '.' . $ext;
                $destDir = defined('UPLOAD_PATH')
                    ? rtrim(UPLOAD_PATH, '/') . '/bills/'
                    : __DIR__ . '/../../assets/uploads/bills/';

                if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
                    $errors[] = 'Upload directory could not be created. Please contact the administrator.';
                } elseif (!move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
                    $errors[] = 'Failed to save uploaded file. Please try again.';
                } else {
                    // Delete old bill file if present
                    if ($asset['bill_file']) {
                        $uploadBase = defined('UPLOAD_PATH')
                            ? rtrim(UPLOAD_PATH, '/')
                            : __DIR__ . '/../../assets/uploads';
                        $oldPath = $uploadBase . '/' . $asset['bill_file'];
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $billPath = 'bills/' . $newName;
                }
            }
        }
    } elseif ($formData['purchased_by'] === 'me' && !$billPath) {
        $errors[] = 'Please upload the bill/receipt for assets purchased by you.';
    }

    if (empty($errors)) {
        try {
            // Verify the asset is still pending before saving (guard against concurrent approval)
            $checkStmt = $pdo->prepare(
                "SELECT approval_status FROM assets WHERE id = ? AND submitted_by = ?"
            );
            $checkStmt->execute([$assetId, $currentUserId]);
            $currentStatus = $checkStmt->fetchColumn();

            if ($currentStatus !== 'pending') {
                flashMessage('danger', 'This asset has been approved and can no longer be edited.');
                header('Location: my_assets.php');
                exit;
            }

            $pdo->prepare(
                "UPDATE assets
                 SET category_id = ?, asset_name = ?, model_number = ?, serial_number = ?,
                     receive_date = ?, purchased_by = ?, purchased_by_name = ?, bill_file = ?
                 WHERE id = ? AND submitted_by = ? AND approval_status = 'pending'"
            )->execute([
                $formData['category_id'],
                $formData['asset_name'],
                $formData['model_number'] ?: null,
                $formData['serial_number'] ?: null,
                $formData['receive_date'],
                $formData['purchased_by'],
                $purchasedByName,
                $billPath,
                $assetId,
                $currentUserId,
            ]);

            logActivity($currentUserId, 'asset_edited', 'Edited pending asset submission: ' . $formData['asset_name']);
            flashMessage('success', 'Asset updated successfully!');
            header('Location: my_assets.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Edit Asset Submission';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Asset Submission</h4>
      <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to My Assets
      </a>
    </div>

    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i>
      You can edit this submission while it is pending admin approval. Once approved, editing is disabled.
    </div>

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
        <span class="badge bg-warning text-dark ms-2">
          <i class="bi bi-hourglass-split me-1"></i>Pending Approval
        </span>
      </div>
      <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" id="editAssetForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <input type="hidden" name="asset_id" value="<?= $assetId ?>">

          <div class="row g-4">

            <!-- Category -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= (int)($formData['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
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
              <label class="form-label fw-semibold">
                Upload Bill / Receipt
                <?php if ($asset['bill_file']): ?>
                  <span class="text-muted fw-normal">(leave empty to keep existing bill)</span>
                <?php else: ?>
                  <span class="text-danger">*</span>
                <?php endif; ?>
              </label>
              <?php if ($asset['bill_file']): ?>
                <div class="mb-2">
                  <a href="<?= uploadUrl($asset['bill_file']) ?>"
                     target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-earmark me-1"></i>View Current Bill
                  </a>
                </div>
              <?php endif; ?>
              <input type="file" name="bill" class="form-control" accept=".pdf,.jpg,.jpeg,.png" id="billInput">
              <div class="form-text">Accepted: PDF, JPG, PNG. Maximum size: 5 MB.</div>
            </div>

          </div><!-- /row -->

          <hr class="my-4">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i>Save Changes
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
  function toggleBill() {
    var val = $('input[name="purchased_by"]:checked').val();
    if (val === 'me') {
      $('#billUploadSection').slideDown(200);
    } else {
      $('#billUploadSection').slideUp(200);
      $('#billInput').val('');
    }
  }

  $('input[name="purchased_by"]').on('change', toggleBill);
  toggleBill();

  $('#billInput').on('change', function () {
    var maxBytes = 5 * 1024 * 1024;
    if (this.files[0] && this.files[0].size > maxBytes) {
      Swal.fire({ icon: 'warning', title: 'File too large', text: 'The bill file must not exceed 5 MB.' });
      $(this).val('');
    }
  });

  $('#editAssetForm').on('submit', function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass('was-validated');
  });
});
</script>
