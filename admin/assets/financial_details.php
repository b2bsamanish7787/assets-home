<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/financial_details.php
 * Description: Add or edit financial details for an asset (bill, purchase date, amounts, entity)
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$assetId = (int)($_GET['id'] ?? 0);
if (!$assetId) {
    flashMessage('danger', 'Invalid asset ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch asset ───────────────────────────────────────────────────────────────
$asset = null;
try {
    $stmt = $pdo->prepare("SELECT id, asset_name FROM assets WHERE id = ?");
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
} catch (Exception $e) {
    error_log('financial_details.php fetch asset: ' . $e->getMessage());
}
if (!$asset) {
    flashMessage('danger', 'Asset not found.');
    header('Location: index.php');
    exit;
}

// ── Fetch existing financial details (if any) ─────────────────────────────────
$details = null;
try {
    $dStmt = $pdo->prepare("SELECT * FROM asset_financial_details WHERE asset_id = ?");
    $dStmt->execute([$assetId]);
    $details = $dStmt->fetch();
} catch (Exception $e) {
    error_log('financial_details.php fetch details: ' . $e->getMessage());
}

$errors   = [];

// Determine current currency/amount from stored values for pre-fill
if (!empty($details) && $details['amount_usd'] !== null && $details['amount_usd'] !== '') {
    $initCurrency = 'USD';
    $initAmount   = $details['amount_usd'];
} elseif (!empty($details) && $details['amount_inr'] !== null && $details['amount_inr'] !== '') {
    $initCurrency = 'INR';
    $initAmount   = $details['amount_inr'];
} else {
    $initCurrency = 'USD';
    $initAmount   = '';
}

$formData = [
    'purchase_date'   => $details['purchase_date'] ?? '',
    'amount'          => $initAmount,
    'amount_currency' => $initCurrency,
    'entity'          => $details['entity']        ?? 'Creativeshop Global Marketing',
];

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: financial_details.php?id=' . $assetId);
        exit;
    }

    $formData['purchase_date']   = sanitize($_POST['purchase_date'] ?? '');
    $formData['entity']          = sanitize($_POST['entity'] ?? '');
    $formData['amount_currency'] = sanitize($_POST['amount_currency'] ?? 'USD');
    $amountRaw                   = trim($_POST['amount'] ?? '');
    $formData['amount']          = $amountRaw;
    $amountValue                 = $amountRaw !== '' ? (float)$amountRaw : null;

    // Normalise currency
    if (!in_array($formData['amount_currency'], ['USD', 'INR'], true)) {
        $formData['amount_currency'] = 'USD';
    }

    // Derive which DB column to populate
    if ($formData['amount_currency'] === 'USD') {
        $formData['amount_usd'] = $amountValue;
        $formData['amount_inr'] = null;
    } else {
        $formData['amount_usd'] = null;
        $formData['amount_inr'] = $amountValue;
    }

    // Validate
    if (!in_array($formData['entity'], ['Creativeshop Global Marketing', 'Buzznation'], true)) {
        $errors[] = 'Please select a valid entity.';
    }
    if ($formData['purchase_date'] && !strtotime($formData['purchase_date'])) {
        $errors[] = 'Invalid purchase date.';
    }
    if ($amountValue !== null && $amountValue < 0) {
        $errors[] = 'Purchase amount must not be negative.';
    }

    // File upload (optional)
    $billPath = $details['bill_file'] ?? null; // keep existing if no new upload
    if (!empty($_FILES['bill_file']['name'])) {
        $file        = $_FILES['bill_file'];
        $maxBytes    = 5 * 1024 * 1024;
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

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
                $destDir = rtrim(UPLOAD_PATH, '/') . '/financial/';
                if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
                    $errors[] = 'Upload directory could not be created. Please contact the administrator.';
                } else {
                    $newName = uniqid('fin_', true) . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
                        $errors[] = 'Failed to save uploaded file. Please try again.';
                    } else {
                        // Delete old bill file if replacing
                        if (!empty($details['bill_file'])) {
                            $oldPath = rtrim(UPLOAD_PATH, '/') . '/' . $details['bill_file'];
                            if (is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $billPath = 'financial/' . $newName;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $userId = (int)$_SESSION['user_id'];

            if ($details) {
                // Update existing record
                $pdo->prepare(
                    "UPDATE asset_financial_details
                     SET bill_file = ?, purchase_date = ?, amount_usd = ?, amount_inr = ?,
                         entity = ?, updated_by = ?, updated_at = NOW()
                     WHERE asset_id = ?"
                )->execute([
                    $billPath,
                    $formData['purchase_date'] ?: null,
                    $formData['amount_usd'],
                    $formData['amount_inr'],
                    $formData['entity'],
                    $userId,
                    $assetId,
                ]);
            } else {
                // Insert new record
                $pdo->prepare(
                    "INSERT INTO asset_financial_details
                         (asset_id, bill_file, purchase_date, amount_usd, amount_inr, entity, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $assetId,
                    $billPath,
                    $formData['purchase_date'] ?: null,
                    $formData['amount_usd'],
                    $formData['amount_inr'],
                    $formData['entity'],
                    $userId,
                    $userId,
                ]);
            }

            logActivity(
                $userId,
                'financial_details_saved',
                'Saved financial details for asset: ' . $asset['asset_name'] . ' (ID: ' . $assetId . ')'
            );

            flashMessage('success', 'Financial details saved successfully.');
            header('Location: view.php?id=' . $assetId);
            exit;
        } catch (Exception $e) {
            error_log('financial_details.php save error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }
}

$pageTitle = 'Financial Details - ' . sanitize($asset['asset_name']) . ' - ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <?= renderFlash() ?>

  <div class="page-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0">
      <i class="bi bi-cash-coin me-2 text-success"></i>Financial Details
      <small class="text-muted fs-6 fw-normal ms-2">&mdash; <?= sanitize($asset['asset_name']) ?></small>
    </h4>
    <a href="view.php?id=<?= $assetId ?>" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Asset
    </a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm" style="max-width:640px;">
    <div class="card-header">
      <i class="bi bi-pencil-square me-2"></i><?= $details ? 'Edit' : 'Add' ?> Financial Details
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data"
            action="financial_details.php?id=<?= $assetId ?>">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

        <!-- Asset Bill -->
        <div class="mb-3">
          <label class="form-label fw-semibold">
            Asset Bill <span class="text-muted fw-normal">(PDF, JPG, PNG &mdash; max 5 MB)</span>
          </label>
          <?php if (!empty($details['bill_file'])): ?>
            <div class="mb-2">
              <a href="<?= uploadUrl($details['bill_file']) ?>"
                 target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark me-1"></i>View Current Bill
              </a>
              <span class="text-muted small ms-2">Upload a new file to replace it.</span>
            </div>
          <?php endif; ?>
          <input type="file" name="bill_file" id="bill_file"
                 class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <!-- Purchase Date -->
        <div class="mb-3">
          <label for="purchase_date" class="form-label fw-semibold">Purchase Date</label>
          <input type="date" name="purchase_date" id="purchase_date"
                 class="form-control"
                 value="<?= htmlspecialchars($formData['purchase_date']) ?>"
                 max="<?= date('Y-m-d') ?>">
        </div>

        <!-- Purchase Amount (single field + currency dropdown) -->
        <div class="mb-3">
          <label for="amount" class="form-label fw-semibold">Purchase Amount</label>
          <div class="input-group">
            <select name="amount_currency" id="amount_currency" class="form-select flex-grow-0" style="max-width:120px;">
              <option value="USD" <?= $formData['amount_currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
              <option value="INR" <?= $formData['amount_currency'] === 'INR' ? 'selected' : '' ?>>INR (&#8377;)</option>
            </select>
            <input type="number" name="amount" id="amount"
                   class="form-control" min="0" step="0.01"
                   placeholder="0.00"
                   value="<?= $formData['amount'] !== '' ? htmlspecialchars((string)$formData['amount']) : '' ?>">
          </div>
        </div>

        <!-- Entity -->
        <div class="mb-4">
          <label for="entity" class="form-label fw-semibold">Entity</label>
          <select name="entity" id="entity" class="form-select" required>
            <option value="Creativeshop Global Marketing" <?= $formData['entity'] === 'Creativeshop Global Marketing' ? 'selected' : '' ?>>Creativeshop Global Marketing</option>
            <option value="Buzznation" <?= $formData['entity'] === 'Buzznation' ? 'selected' : '' ?>>Buzznation</option>
          </select>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-save me-1"></i>Save Details
          </button>
          <a href="view.php?id=<?= $assetId ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
