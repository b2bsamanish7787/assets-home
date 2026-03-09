<?php
/**
 * Buzznation Assets Management System
 * File: admin/requests/view.php
 * Description: Full detail view for a single employee asset request with approve/reject actions.
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

$requestId = (int)($_GET['id'] ?? 0);
if (!$requestId) {
    flashMessage('danger', 'Invalid request ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch request details ─────────────────────────────────────────────────────
$request = null;
try {
    $stmt = $pdo->prepare(
        "SELECT ar.id,
                ar.asset_requirement,
                ar.description,
                ar.status,
                ar.admin_remarks,
                ar.created_at,
                ar.updated_at,
                u.id          AS employee_id,
                u.first_name,
                u.last_name,
                u.email       AS employee_email,
                u.department  AS employee_department,
                u.designation AS employee_designation,
                u.employee_id AS emp_code
         FROM asset_requests ar
         JOIN users u ON u.id = ar.employee_id
         WHERE ar.id = ?"
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
} catch (Exception $e) {
    error_log('view.php fetch request error: ' . $e->getMessage());
}

if (!$request) {
    flashMessage('danger', 'Asset request not found.');
    header('Location: index.php');
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$badgeClass  = match($request['status']) {
    'approved' => 'success',
    'rejected' => 'danger',
    default    => 'warning text-dark',
};
$statusLabel = ucfirst($request['status']);

$employeeName = trim($request['first_name'] . ' ' . $request['last_name']);

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Request #' . $requestId . ' — ' . SITE_NAME;

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
      <i class="bi bi-clipboard-check me-2"></i>Asset Request #<?= $requestId ?>
      <span class="badge bg-<?= $badgeClass ?> ms-2 fs-6 align-middle"><?= $statusLabel ?></span>
    </h4>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($_SESSION['role'] === 'admin' && $request['status'] === 'pending'): ?>
        <a href="action.php?action=approve&id=<?= $requestId ?>&csrf_token=<?= urlencode($csrf) ?>"
           class="btn btn-success btn-sm btn-approve"
           data-id="<?= $requestId ?>"
           data-name="<?= sanitize($employeeName) ?>">
          <i class="bi bi-check-lg me-1"></i>Approve
        </a>
        <button type="button"
                class="btn btn-danger btn-sm btn-reject"
                data-id="<?= $requestId ?>"
                data-name="<?= sanitize($employeeName) ?>"
                data-csrf="<?= $csrf ?>">
          <i class="bi bi-x-lg me-1"></i>Reject
        </button>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>

  <div class="row g-4">

    <!-- Left: Request Details -->
    <div class="col-lg-7">
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-info-circle me-2"></i>Request Information
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Request ID</dt>
            <dd class="col-sm-8 fw-semibold">#<?= $requestId ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Asset Required</dt>
            <dd class="col-sm-8 fw-semibold"><?= sanitize($request['asset_requirement']) ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Description</dt>
            <dd class="col-sm-8">
              <?= $request['description']
                    ? nl2br(sanitize($request['description']))
                    : '<span class="text-muted">—</span>' ?>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Status</dt>
            <dd class="col-sm-8">
              <span class="badge bg-<?= $badgeClass ?>"><?= $statusLabel ?></span>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Submitted On</dt>
            <dd class="col-sm-8">
              <?= htmlspecialchars(date('d M Y, H:i', strtotime($request['created_at']))) ?>
            </dd>

            <?php if ($request['updated_at'] && $request['updated_at'] !== $request['created_at']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Last Updated</dt>
              <dd class="col-sm-8">
                <?= htmlspecialchars(date('d M Y, H:i', strtotime($request['updated_at']))) ?>
              </dd>
            <?php endif; ?>

            <?php if ($request['admin_remarks']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Admin Remarks</dt>
              <dd class="col-sm-8">
                <div class="alert alert-light border mb-0 py-2 px-3 small">
                  <i class="bi bi-chat-left-text me-1 text-muted"></i>
                  <?= nl2br(sanitize($request['admin_remarks'])) ?>
                </div>
              </dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    </div>

    <!-- Right: Employee Details -->
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-person-badge me-2"></i>Employee Details
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted fw-normal">Name</dt>
            <dd class="col-sm-7 fw-semibold"><?= sanitize($employeeName) ?></dd>

            <?php if ($request['emp_code']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Employee ID</dt>
              <dd class="col-sm-7"><?= sanitize($request['emp_code']) ?></dd>
            <?php endif; ?>

            <dt class="col-sm-5 text-muted fw-normal">Email</dt>
            <dd class="col-sm-7">
              <a href="mailto:<?= sanitize($request['employee_email']) ?>">
                <?= sanitize($request['employee_email']) ?>
              </a>
            </dd>

            <?php if ($request['employee_department']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Department</dt>
              <dd class="col-sm-7"><?= sanitize($request['employee_department']) ?></dd>
            <?php endif; ?>

            <?php if ($request['employee_designation']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Designation</dt>
              <dd class="col-sm-7 mb-0"><?= sanitize($request['employee_designation']) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <div class="card-footer bg-transparent">
            <a href="<?= SITE_URL ?>/admin/employees/view.php?id=<?= (int)$request['employee_id'] ?>"
               class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-person-lines-fill me-1"></i>View Employee Profile
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.row -->

</div><!-- /.main-content -->

<?php
$extraScripts = <<<JS
<script>
$(function () {
  // Approve button — confirm via SweetAlert
  $(document).on('click', '.btn-approve', function (e) {
    e.preventDefault();
    const url  = \$(this).attr('href');
    const name = \$(this).data('name');
    Swal.fire({
      title: 'Approve Request?',
      text: \`Approve the asset request from "\${name}"?\`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#198754',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, Approve'
    }).then(result => {
      if (result.isConfirmed) window.location.href = url;
    });
  });

  // Reject button — prompt for remarks via SweetAlert
  $(document).on('click', '.btn-reject', function () {
    const id   = \$(this).data('id');
    const name = \$(this).data('name');
    const csrf = \$(this).data('csrf');

    Swal.fire({
      title: 'Reject Request',
      text: \`Provide remarks for rejecting "\${name}"'s request:\`,
      input: 'textarea',
      inputPlaceholder: 'Enter reason for rejection...',
      inputAttributes: { maxlength: 500 },
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Reject',
      preConfirm: (remarks) => {
        if (!remarks || remarks.trim() === '') {
          Swal.showValidationMessage('Please enter a reason for rejection.');
        }
        return remarks;
      }
    }).then(result => {
      if (result.isConfirmed) {
        const url = 'action.php?action=reject&id=' + id
          + '&csrf_token=' + encodeURIComponent(csrf)
          + '&remarks=' + encodeURIComponent(result.value.trim());
        window.location.href = url;
      }
    });
  });
});
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
