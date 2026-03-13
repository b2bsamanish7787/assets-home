<?php
/**
 * Buzznation Assets Management System
 * File: admin/employees/view.php
 * Description: Full profile view for a single employee — details, assigned assets, and asset request history.
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

$employeeId = (int)($_GET['id'] ?? 0);
if (!$employeeId) {
    flashMessage('danger', 'Invalid employee ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch employee details ────────────────────────────────────────────────────
$employee = null;
try {
    $stmt = $pdo->prepare(
        "SELECT id,
                employee_id,
                first_name,
                last_name,
                email,
                role,
                designation,
                department,
                manager_name,
                manager_email,
                status,
                is_first_login,
                temp_password,
                created_at,
                updated_at
         FROM users
         WHERE id = ?"
    );
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
} catch (Exception $e) {
    error_log('employees/view.php fetch error: ' . $e->getMessage());
}

if (!$employee) {
    flashMessage('danger', 'Employee not found.');
    header('Location: index.php');
    exit;
}

// ── Fetch assigned assets ─────────────────────────────────────────────────────
$assignedAssets = [];
try {
    $assetStmt = $pdo->prepare(
        "SELECT a.id,
                a.asset_name,
                a.model_number,
                a.serial_number,
                a.status,
                c.name AS category_name,
                a.created_at
         FROM assets a
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.assigned_to = ?
         ORDER BY a.created_at DESC"
    );
    $assetStmt->execute([$employeeId]);
    $assignedAssets = $assetStmt->fetchAll();
} catch (Exception $e) {
    error_log('employees/view.php fetch assets error: ' . $e->getMessage());
}

// ── Fetch asset requests ──────────────────────────────────────────────────────
$assetRequests = [];
try {
    $reqStmt = $pdo->prepare(
        "SELECT id,
                asset_requirement,
                description,
                status,
                admin_remarks,
                created_at
         FROM asset_requests
         WHERE employee_id = ?
         ORDER BY created_at DESC"
    );
    $reqStmt->execute([$employeeId]);
    $assetRequests = $reqStmt->fetchAll();
} catch (Exception $e) {
    error_log('employees/view.php fetch requests error: ' . $e->getMessage());
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$fullName = trim($employee['first_name'] . ' ' . $employee['last_name']);

$roleBadge = match($employee['role']) {
    'admin' => 'bg-dark',
    'hr'    => 'bg-primary',
    default => 'bg-secondary',
};

$statusBadge = $employee['status'] === 'active' ? 'bg-success' : 'bg-danger';

$assetStatusBadge = [
    'available'  => 'success',
    'assigned'   => 'primary',
    'in_service' => 'warning',
    'returned'   => 'secondary',
];

$reqStatusBadge = [
    'approved' => 'success',
    'rejected' => 'danger',
    'pending'  => 'warning text-dark',
];

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = sanitize($fullName) . ' — ' . SITE_NAME;

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
      <i class="bi bi-person-badge me-2"></i><?= sanitize($fullName) ?>
      <span class="badge <?= $statusBadge ?> ms-2 fs-6 align-middle"><?= ucfirst($employee['status']) ?></span>
      <span class="badge <?= $roleBadge ?> ms-1 fs-6 align-middle"><?= ucfirst($employee['role']) ?></span>
    </h4>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="edit.php?id=<?= $employeeId ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>

  <div class="row g-4">

    <!-- Left: Employee Profile -->
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-person-circle me-2"></i>Profile Information
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted fw-normal">Employee ID</dt>
            <dd class="col-sm-7 fw-semibold"><?= sanitize($employee['employee_id'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted fw-normal">Full Name</dt>
            <dd class="col-sm-7 fw-semibold"><?= sanitize($fullName) ?></dd>

            <dt class="col-sm-5 text-muted fw-normal">Email</dt>
            <dd class="col-sm-7">
              <a href="mailto:<?= sanitize($employee['email']) ?>">
                <?= sanitize($employee['email']) ?>
              </a>
            </dd>

            <dt class="col-sm-5 text-muted fw-normal">Role</dt>
            <dd class="col-sm-7">
              <span class="badge <?= $roleBadge ?>"><?= ucfirst($employee['role']) ?></span>
            </dd>

            <dt class="col-sm-5 text-muted fw-normal">Status</dt>
            <dd class="col-sm-7">
              <span class="badge <?= $statusBadge ?>"><?= ucfirst($employee['status']) ?></span>
            </dd>

            <?php if ($employee['designation']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Designation</dt>
              <dd class="col-sm-7"><?= sanitize($employee['designation']) ?></dd>
            <?php endif; ?>

            <?php if ($employee['department']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Department</dt>
              <dd class="col-sm-7"><?= sanitize($employee['department']) ?></dd>
            <?php endif; ?>

            <?php if ($employee['manager_name']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Manager</dt>
              <dd class="col-sm-7"><?= sanitize($employee['manager_name']) ?></dd>
            <?php endif; ?>

            <?php if ($employee['manager_email']): ?>
              <dt class="col-sm-5 text-muted fw-normal">Manager Email</dt>
              <dd class="col-sm-7">
                <a href="mailto:<?= sanitize($employee['manager_email']) ?>">
                  <?= sanitize($employee['manager_email']) ?>
                </a>
              </dd>
            <?php endif; ?>

            <dt class="col-sm-5 text-muted fw-normal">Joined On</dt>
            <dd class="col-sm-7">
              <?= htmlspecialchars(date('d M Y', strtotime($employee['created_at']))) ?>
            </dd>

            <dt class="col-sm-5 text-muted fw-normal">Login Status</dt>
            <dd class="col-sm-7 mb-0">
              <?php if ($employee['is_first_login']): ?>
                <span class="badge bg-warning text-dark">
                  <i class="bi bi-exclamation-triangle me-1"></i>Awaiting First Login
                </span>
                <?php if (!empty($employee['temp_password']) && $_SESSION['role'] === 'admin'): ?>
                  <div class="mt-2 p-2 bg-light border rounded small">
                    <i class="bi bi-key me-1 text-muted"></i>
                    <strong>Temp Password:</strong>
                    <code><?= sanitize($employee['temp_password']) ?></code>
                    <div class="text-muted mt-1" style="font-size:0.8em;">Share this with the employee if the welcome email was not received.</div>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge bg-light text-dark border">Active User</span>
              <?php endif; ?>
            </dd>
          </dl>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <div class="card-footer bg-transparent d-flex gap-2">
            <a href="index.php?action=reset&id=<?= $employeeId ?>&csrf_token=<?= urlencode($csrf) ?>"
               class="btn btn-sm btn-outline-warning btn-reset-pass flex-fill">
              <i class="bi bi-key me-1"></i>Reset Password
            </a>
            <a href="edit.php?id=<?= $employeeId ?>" class="btn btn-sm btn-outline-primary flex-fill">
              <i class="bi bi-pencil me-1"></i>Edit Profile
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Assets + Requests -->
    <div class="col-lg-7">

      <!-- Assigned Assets -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-box-seam me-2"></i>Assigned Assets</span>
          <span class="badge bg-light text-dark"><?= count($assignedAssets) ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($assignedAssets)): ?>
            <p class="text-muted text-center py-3 mb-0">
              <i class="bi bi-inbox me-1"></i>No assets currently assigned.
            </p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Asset</th>
                    <th>Category</th>
                    <th>Serial #</th>
                    <th>Status</th>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                      <th></th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($assignedAssets as $asset): ?>
                    <tr>
                      <td class="fw-semibold small"><?= sanitize($asset['asset_name']) ?></td>
                      <td class="small text-muted"><?= sanitize($asset['category_name'] ?? '—') ?></td>
                      <td class="small">
                        <?= $asset['serial_number']
                              ? '<code>' . sanitize($asset['serial_number']) . '</code>'
                              : '—' ?>
                      </td>
                      <td>
                        <span class="badge bg-<?= $assetStatusBadge[$asset['status']] ?? 'secondary' ?>">
                          <?= ucfirst(str_replace('_', ' ', $asset['status'])) ?>
                        </span>
                      </td>
                      <?php if ($_SESSION['role'] === 'admin'): ?>
                        <td>
                          <a href="<?= SITE_URL ?>/admin/assets/view.php?id=<?= (int)$asset['id'] ?>"
                             class="btn btn-sm btn-outline-secondary py-0" title="View Asset">
                            <i class="bi bi-eye"></i>
                          </a>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Asset Request History -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clipboard-check me-2"></i>Asset Requests</span>
          <span class="badge bg-light text-dark"><?= count($assetRequests) ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($assetRequests)): ?>
            <p class="text-muted text-center py-3 mb-0">
              <i class="bi bi-clipboard me-1"></i>No asset requests found.
            </p>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($assetRequests as $req): ?>
                <li class="list-group-item py-3">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <span class="fw-semibold small"><?= sanitize($req['asset_requirement']) ?></span>
                    <span class="badge bg-<?= $reqStatusBadge[$req['status']] ?? 'secondary' ?>">
                      <?= ucfirst($req['status']) ?>
                    </span>
                  </div>
                  <?php if ($req['description']): ?>
                    <p class="text-muted small mb-1">
                      <?= sanitize(mb_strimwidth($req['description'], 0, 120, '…')) ?>
                    </p>
                  <?php endif; ?>
                  <div class="d-flex justify-content-between align-items-center" style="font-size:.75rem;">
                    <span class="text-muted">
                      <?= htmlspecialchars(date('d M Y', strtotime($req['created_at']))) ?>
                    </span>
                    <?php if ($req['admin_remarks']): ?>
                      <span class="text-secondary">
                        <i class="bi bi-chat-left-text me-1"></i><?= sanitize(mb_strimwidth($req['admin_remarks'], 0, 60, '…')) ?>
                      </span>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                      <a href="<?= SITE_URL ?>/admin/requests/view.php?id=<?= (int)$req['id'] ?>"
                         class="btn btn-sm btn-outline-secondary py-0" style="font-size:.75rem;">
                        <i class="bi bi-eye me-1"></i>View
                      </a>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <?php if ($_SESSION['role'] === 'admin' && !empty($assetRequests)): ?>
          <div class="card-footer bg-transparent">
            <a href="<?= SITE_URL ?>/admin/requests/index.php" class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-list me-1"></i>All Asset Requests
            </a>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /col-lg-7 -->

  </div><!-- /.row -->

</div><!-- /.main-content -->

<?php
$extraScripts = <<<JS
<script>
$(function () {
  // Reset password confirmation
  $(document).on('click', '.btn-reset-pass', function (e) {
    e.preventDefault();
    const href = \$(this).attr('href');
    Swal.fire({
      title: 'Reset Password?',
      text: 'A new temporary password will be generated and emailed to the employee.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#fd7e14',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, reset'
    }).then(result => {
      if (result.isConfirmed) window.location.href = href;
    });
  });
});
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
