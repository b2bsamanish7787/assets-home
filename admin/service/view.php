<?php
/**
 * Buzznation Assets Management System
 * File: admin/service/view.php
 * Description: Full detail view for a single service request with approve/reject/complete/bill actions.
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

$serviceId = (int)($_GET['id'] ?? 0);
if (!$serviceId) {
    flashMessage('danger', 'Invalid service request ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch service request details ─────────────────────────────────────────────
$sr = null;
try {
    $stmt = $pdo->prepare(
        "SELECT sr.id,
                sr.problem_description,
                sr.problem_since,
                sr.impact_on_work,
                sr.approx_amount,
                sr.actual_amount,
                sr.status,
                sr.admin_remarks,
                sr.service_bill,
                sr.created_at,
                sr.updated_at,
                a.id          AS asset_id,
                a.asset_name,
                a.model_number,
                a.serial_number,
                a.status      AS asset_status,
                a.assigned_to AS asset_assigned_to,
                c.name        AS category_name,
                u.id          AS employee_id,
                u.first_name,
                u.last_name,
                u.email       AS employee_email,
                u.employee_id AS emp_code,
                u.department  AS employee_department,
                u.designation AS employee_designation
         FROM service_requests sr
         JOIN assets a  ON a.id  = sr.asset_id
         JOIN users  u  ON u.id  = sr.employee_id
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE sr.id = ?"
    );
    $stmt->execute([$serviceId]);
    $sr = $stmt->fetch();
} catch (Exception $e) {
    error_log('service/view.php fetch error: ' . $e->getMessage());
}

if (!$sr) {
    flashMessage('danger', 'Service request not found.');
    header('Location: index.php');
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$badgeClass = match($sr['status']) {
    'approved'  => 'success',
    'rejected'  => 'danger',
    'completed' => 'primary',
    default     => 'warning text-dark',
};
$statusLabel  = ucfirst($sr['status']);
$employeeName = trim($sr['first_name'] . ' ' . $sr['last_name']);

$assetStatusBadge = [
    'available'  => 'success',
    'assigned'   => 'primary',
    'in_service' => 'warning text-dark',
    'returned'   => 'secondary',
];

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Service Request #' . $serviceId . ' — ' . SITE_NAME;

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

  <!-- Page header -->
  <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">
      <i class="bi bi-tools me-2"></i>Service Request #<?= $serviceId ?>
      <span class="badge bg-<?= $badgeClass ?> ms-2 fs-6 align-middle"><?= $statusLabel ?></span>
    </h4>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($_SESSION['role'] === 'admin'): ?>

        <?php if ($sr['status'] === 'pending'): ?>
          <button type="button"
                  class="btn btn-success btn-sm btn-approve-svc"
                  data-id="<?= $serviceId ?>"
                  data-csrf="<?= $csrf ?>">
            <i class="bi bi-check-lg me-1"></i>Approve
          </button>
          <button type="button"
                  class="btn btn-danger btn-sm btn-reject-svc"
                  data-id="<?= $serviceId ?>"
                  data-csrf="<?= $csrf ?>">
            <i class="bi bi-x-lg me-1"></i>Reject
          </button>

        <?php elseif ($sr['status'] === 'approved'): ?>
          <button type="button"
                  class="btn btn-outline-secondary btn-sm btn-upload-bill"
                  data-id="<?= $serviceId ?>"
                  data-csrf="<?= $csrf ?>">
            <i class="bi bi-upload me-1"></i>Upload Bill
          </button>
          <button type="button"
                  class="btn btn-primary btn-sm btn-complete-svc"
                  data-id="<?= $serviceId ?>"
                  data-csrf="<?= $csrf ?>">
            <i class="bi bi-check2-all me-1"></i>Mark Complete
          </button>
        <?php endif; ?>

      <?php endif; ?>

      <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>

  <div class="row g-4">

    <!-- Left: Service Request Details -->
    <div class="col-lg-7">
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-info-circle me-2"></i>Request Information
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Request ID</dt>
            <dd class="col-sm-8 fw-semibold">#<?= $serviceId ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Status</dt>
            <dd class="col-sm-8">
              <span class="badge bg-<?= $badgeClass ?>"><?= $statusLabel ?></span>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Problem Description</dt>
            <dd class="col-sm-8">
              <?= nl2br(sanitize($sr['problem_description'])) ?>
            </dd>

            <?php if ($sr['problem_since']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Problem Since</dt>
              <dd class="col-sm-8">
                <?= htmlspecialchars(date('d M Y', strtotime($sr['problem_since']))) ?>
              </dd>
            <?php endif; ?>

            <?php if ($sr['impact_on_work']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Impact on Work</dt>
              <dd class="col-sm-8">
                <?= nl2br(sanitize($sr['impact_on_work'])) ?>
              </dd>
            <?php endif; ?>

            <dt class="col-sm-4 text-muted fw-normal">Est. Amount</dt>
            <dd class="col-sm-8">
              <?= $sr['approx_amount'] !== null
                    ? '₹ ' . number_format((float)$sr['approx_amount'], 2)
                    : '<span class="text-muted">—</span>' ?>
            </dd>

            <?php if ($sr['actual_amount'] !== null): ?>
              <dt class="col-sm-4 text-muted fw-normal">Actual Amount</dt>
              <dd class="col-sm-8 fw-semibold">
                ₹ <?= number_format((float)$sr['actual_amount'], 2) ?>
              </dd>
            <?php endif; ?>

            <dt class="col-sm-4 text-muted fw-normal">Submitted On</dt>
            <dd class="col-sm-8">
              <?= htmlspecialchars(date('d M Y, H:i', strtotime($sr['created_at']))) ?>
            </dd>

            <?php if ($sr['updated_at'] && $sr['updated_at'] !== $sr['created_at']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Last Updated</dt>
              <dd class="col-sm-8">
                <?= htmlspecialchars(date('d M Y, H:i', strtotime($sr['updated_at']))) ?>
              </dd>
            <?php endif; ?>

            <?php if ($sr['admin_remarks']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Admin Remarks</dt>
              <dd class="col-sm-8 mb-0">
                <div class="alert alert-light border mb-0 py-2 px-3 small">
                  <i class="bi bi-chat-left-text me-1 text-muted"></i>
                  <?= nl2br(sanitize($sr['admin_remarks'])) ?>
                </div>
              </dd>
            <?php endif; ?>

            <?php if ($sr['service_bill']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Service Bill</dt>
              <dd class="col-sm-8 mb-0">
                <a href="<?= htmlspecialchars(SITE_URL . '/uploads/' . implode('/', array_map('rawurlencode', explode('/', $sr['service_bill'])))) ?>"
                   target="_blank"
                   class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-file-earmark-text me-1"></i>View Bill
                </a>
              </dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    </div>

    <!-- Right: Asset + Employee Details -->
    <div class="col-lg-5">

      <!-- Asset Details -->
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-box-seam me-2"></i>Asset Details
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted fw-normal">Asset Name</dt>
            <dd class="col-sm-7 fw-semibold"><?= sanitize($sr['asset_name']) ?></dd>

            <?php if ($sr['category_name']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Category</dt>
              <dd class="col-sm-7"><?= sanitize($sr['category_name']) ?></dd>
            <?php endif; ?>

            <?php if ($sr['model_number']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Model</dt>
              <dd class="col-sm-7"><?= sanitize($sr['model_number']) ?></dd>
            <?php endif; ?>

            <?php if ($sr['serial_number']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Serial #</dt>
              <dd class="col-sm-7"><code><?= sanitize($sr['serial_number']) ?></code></dd>
            <?php endif; ?>

            <dt class="col-sm-5 text-muted fw-normal">Asset Status</dt>
            <dd class="col-sm-7 mb-0">
              <span class="badge bg-<?= $assetStatusBadge[$sr['asset_status']] ?? 'secondary' ?>">
                <?= ucfirst(str_replace('_', ' ', $sr['asset_status'])) ?>
              </span>
            </dd>
          </dl>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <div class="card-footer bg-transparent">
            <a href="<?= SITE_URL ?>/admin/assets/view.php?id=<?= (int)$sr['asset_id'] ?>"
               class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-eye me-1"></i>View Asset
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Employee Details -->
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-person-badge me-2"></i>Employee Details
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted fw-normal">Name</dt>
            <dd class="col-sm-7 fw-semibold"><?= sanitize($employeeName) ?></dd>

            <?php if ($sr['emp_code']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Employee ID</dt>
              <dd class="col-sm-7"><?= sanitize($sr['emp_code']) ?></dd>
            <?php endif; ?>

            <dt class="col-sm-5 text-muted fw-normal">Email</dt>
            <dd class="col-sm-7">
              <a href="mailto:<?= sanitize($sr['employee_email']) ?>">
                <?= sanitize($sr['employee_email']) ?>
              </a>
            </dd>

            <?php if ($sr['employee_department']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Department</dt>
              <dd class="col-sm-7"><?= sanitize($sr['employee_department']) ?></dd>
            <?php endif; ?>

            <?php if ($sr['employee_designation']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Designation</dt>
              <dd class="col-sm-7 mb-0"><?= sanitize($sr['employee_designation']) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <div class="card-footer bg-transparent">
            <a href="<?= SITE_URL ?>/admin/employees/view.php?id=<?= (int)$sr['employee_id'] ?>"
               class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-person-lines-fill me-1"></i>View Employee Profile
            </a>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /col-lg-5 -->

  </div><!-- /.row -->

</div><!-- /.main-content -->

<!-- Hidden bill upload form -->
<?php if ($_SESSION['role'] === 'admin'): ?>
  <form id="billUploadForm" method="POST" action="action.php" enctype="multipart/form-data" class="d-none">
    <input type="hidden" name="csrf_token" id="billCsrf">
    <input type="hidden" name="action"     value="upload_bill">
    <input type="hidden" name="id"         id="billId">
    <input type="file"   name="service_bill" id="billFile" accept=".pdf,.jpg,.jpeg,.png">
  </form>
<?php endif; ?>

<?php
$extraScripts = <<<JS
<script>
$(function () {
  // ── Approve service request ──────────────────────────────────────────────
  $(document).on('click', '.btn-approve-svc', function () {
    const id   = $(this).data('id');
    const csrf = $(this).data('csrf');
    Swal.fire({
      title: 'Approve Service Request?',
      text: 'The asset will be marked as "In Service".',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#198754',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, Approve'
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = 'action.php?action=approve&id=' + id + '&csrf_token=' + encodeURIComponent(csrf);
      }
    });
  });

  // ── Reject service request ───────────────────────────────────────────────
  $(document).on('click', '.btn-reject-svc', function () {
    const id   = $(this).data('id');
    const csrf = $(this).data('csrf');
    Swal.fire({
      title: 'Reject Service Request',
      input: 'textarea',
      inputPlaceholder: 'Enter rejection reason...',
      inputAttributes: { maxlength: 500 },
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Reject',
      preConfirm: (remarks) => {
        if (!remarks || remarks.trim() === '') {
          Swal.showValidationMessage('Please enter a rejection reason.');
        }
        return remarks;
      }
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = 'action.php?action=reject&id=' + id
          + '&csrf_token=' + encodeURIComponent(csrf)
          + '&remarks=' + encodeURIComponent(result.value.trim());
      }
    });
  });

  // ── Mark service complete ────────────────────────────────────────────────
  $(document).on('click', '.btn-complete-svc', function () {
    const id   = $(this).data('id');
    const csrf = $(this).data('csrf');
    Swal.fire({
      title: 'Mark as Completed?',
      text: 'Asset status will return to assigned.',
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#0d6efd',
      confirmButtonText: 'Yes, Complete'
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = 'action.php?action=complete&id=' + id + '&csrf_token=' + encodeURIComponent(csrf);
      }
    });
  });

  // ── Upload Bill ──────────────────────────────────────────────────────────
  $(document).on('click', '.btn-upload-bill', function () {
    const id   = $(this).data('id');
    const csrf = $(this).data('csrf');
    $('#billId').val(id);
    $('#billCsrf').val(csrf);
    $('#billFile').off('change').on('change', function () {
      if (this.files.length > 0) {
        $('#billUploadForm').submit();
      }
    });
    $('#billFile').trigger('click');
  });
});
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
