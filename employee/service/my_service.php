<?php
/**
 * File: employee/service/my_service.php
 * Description: Lists all service requests for the current employee, with option to upload a service bill.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];

// Handle service bill upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bill'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: ' . SITE_URL . '/employee/service/my_service.php');
        exit;
    }

    $serviceId = (int)($_POST['service_id'] ?? 0);

    // Verify the service request belongs to this employee
    $verifyStmt = $pdo->prepare("SELECT id FROM service_requests WHERE id = ? AND employee_id = ? LIMIT 1");
    $verifyStmt->execute([$serviceId, $currentUserId]);
    if (!$verifyStmt->fetch()) {
        flashMessage('error', 'Invalid service request.');
        header('Location: ' . SITE_URL . '/employee/service/my_service.php');
        exit;
    }

    $uploadError = null;
    if (empty($_FILES['service_bill']['name'])) {
        $uploadError = 'Please select a file to upload.';
    } else {
        $file        = $_FILES['service_bill'];
        $maxBytes    = 5 * 1024 * 1024;
        $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
        $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['size'] > $maxBytes) {
            $uploadError = 'File must not exceed 5 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'File upload error. Please try again.';
        } else {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMime)) {
                $uploadError = 'Only PDF, JPG, and PNG files are allowed.';
            } else {
                $newName = uniqid('sbill_', true) . '.' . $ext;
                $destDir = __DIR__ . '/../../uploads/service_bills/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                if (!move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
                    $uploadError = 'Failed to save uploaded file.';
                } else {
                    $actualAmount = null;
                    if (isset($_POST['actual_amount']) && $_POST['actual_amount'] !== '') {
                        $actualAmount = (float)$_POST['actual_amount'];
                        if ($actualAmount < 0) {
                            $actualAmount = null;
                        }
                    }
                    $pdo->prepare("UPDATE service_requests SET service_bill = ?, actual_amount = ? WHERE id = ?")
                        ->execute(['service_bills/' . $newName, $actualAmount, $serviceId]);
                    logActivity($currentUserId, 'service_bill_uploaded', 'Uploaded service bill for request #' . $serviceId);
                    flashMessage('success', 'Service bill uploaded successfully!');
                    header('Location: ' . SITE_URL . '/employee/service/my_service.php');
                    exit;
                }
            }
        }
    }

    if ($uploadError) {
        flashMessage('error', $uploadError);
        header('Location: ' . SITE_URL . '/employee/service/my_service.php');
        exit;
    }
}

// Fetch service requests
$stmt = $pdo->prepare("SELECT sr.id, a.asset_name, sr.problem_description, sr.problem_since,
                               sr.approx_amount, sr.actual_amount, sr.status, sr.admin_remarks,
                               sr.service_bill, sr.created_at
                        FROM service_requests sr
                        LEFT JOIN assets a ON a.id = sr.asset_id
                        WHERE sr.employee_id = ?
                        ORDER BY sr.created_at DESC");
$stmt->execute([$currentUserId]);
$serviceRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Service Requests';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-tools me-2 text-primary"></i>My Service Requests</h4>
      <a href="<?= SITE_URL ?>/employee/service/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Service Request
      </a>
    </div>

    <?= renderFlash() ?>

    <div class="card shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i>All Service Requests
          <span class="badge bg-primary ms-1"><?= count($serviceRequests) ?></span>
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle" id="serviceTable">
            <thead class="table-dark">
              <tr>
                <th width="50">#</th>
                <th>Asset</th>
                <th>Problem</th>
                <th width="120">Problem Since</th>
                <th width="120" class="text-end">Amount</th>
                <th width="110" class="text-center">Status</th>
                <th>Admin Remarks</th>
                <th width="130" class="text-center">Bill</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($serviceRequests as $i => $sr): ?>
              <?php
                $sc = match($sr['status']) {
                  'pending'  => 'bg-warning text-dark',
                  'approved' => 'bg-success',
                  'rejected' => 'bg-danger',
                  'completed'=> 'bg-info text-dark',
                  default    => 'bg-secondary',
                };
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td class="fw-medium"><?= htmlspecialchars($sr['asset_name'] ?? '—') ?></td>
                <td>
                  <span title="<?= htmlspecialchars($sr['problem_description']) ?>">
                    <?= htmlspecialchars(mb_strimwidth($sr['problem_description'], 0, 60, '…')) ?>
                  </span>
                </td>
                <td class="text-nowrap text-muted small">
                  <?= $sr['problem_since'] ? date('d M Y', strtotime($sr['problem_since'])) : '—' ?>
                </td>
                <td class="text-end">
                  <?php if ($sr['actual_amount'] !== null): ?>
                    <span class="fw-semibold">₹<?= number_format((float)$sr['actual_amount'], 2) ?></span>
                    <?php if ($sr['approx_amount'] !== null): ?>
                      <br><small class="text-muted">Est: ₹<?= number_format((float)$sr['approx_amount'], 2) ?></small>
                    <?php endif; ?>
                  <?php elseif ($sr['approx_amount'] !== null): ?>
                    <span class="text-muted">₹<?= number_format((float)$sr['approx_amount'], 2) ?></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="badge <?= $sc ?>"><?= ucfirst($sr['status']) ?></span>
                </td>
                <td>
                  <?= $sr['admin_remarks']
                    ? htmlspecialchars($sr['admin_remarks'])
                    : '<span class="text-muted fst-italic">—</span>'
                  ?>
                </td>
                <td class="text-center">
                  <?php if ($sr['service_bill']): ?>
                    <a href="<?= htmlspecialchars(SITE_URL . '/uploads/' . implode('/', array_map('rawurlencode', explode('/', $sr['service_bill'])))) ?>"
                       target="_blank" class="btn btn-outline-success btn-sm px-2 py-0" title="View Bill">
                      <i class="bi bi-file-earmark-check me-1"></i>View
                    </a>
                  <?php elseif ($sr['status'] === 'approved'): ?>
                    <!-- Inline upload form -->
                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-0"
                            data-bs-toggle="modal" data-bs-target="#uploadBillModal"
                            data-service-id="<?= $sr['id'] ?>">
                      <i class="bi bi-upload me-1"></i>Upload
                    </button>
                  <?php else: ?>
                    <span class="text-muted small fst-italic">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($serviceRequests)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-3 d-block mb-2"></i>No service requests found.
                  <br>
                  <a href="<?= SITE_URL ?>/employee/service/new.php" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-plus-circle me-1"></i>Submit First Request
                  </a>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Upload Bill Modal -->
<div class="modal fade" id="uploadBillModal" tabindex="-1" aria-labelledby="uploadBillModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content shadow">
      <div class="modal-header bg-light">
        <h6 class="modal-title fw-semibold" id="uploadBillModalLabel">
          <i class="bi bi-upload me-1"></i>Upload Service Bill
        </h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data" id="billUploadForm">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <input type="hidden" name="upload_bill" value="1">
          <input type="hidden" name="service_id" id="modalServiceId" value="">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Actual Service Amount <span class="text-muted fw-normal">(optional)</span></label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">₹</span>
              <input type="number" name="actual_amount" class="form-control form-control-sm"
                     min="0" step="0.01" placeholder="Enter actual amount">
            </div>
            <div class="form-text">Leave blank if amount is not yet known.</div>
          </div>
          <label class="form-label small fw-semibold">Bill File <span class="text-danger">*</span></label>
          <input type="file" name="service_bill" class="form-control form-control-sm"
                 accept=".pdf,.jpg,.jpeg,.png" required>
          <div class="form-text mt-1">PDF, JPG, PNG — max 5 MB.</div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-upload me-1"></i>Upload
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  $('#serviceTable').DataTable({
    pageLength: 25,
    order: [[3, 'desc']],
    responsive: true,
    language: { search: 'Search service requests:' }
  });

  // Populate service ID into modal
  $('#uploadBillModal').on('show.bs.modal', function (e) {
    const btn = $(e.relatedTarget);
    $('#modalServiceId').val(btn.data('service-id'));
  });

  // Client-side file size guard
  $('#billUploadForm input[type=file]').on('change', function () {
    const maxBytes = 5 * 1024 * 1024;
    if (this.files[0] && this.files[0].size > maxBytes) {
      Swal.fire({ icon: 'warning', title: 'File too large', text: 'Max allowed size is 5 MB.' });
      $(this).val('');
    }
  });
});
</script>
