<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/view.php
 * Description: Full detail view for a single asset — info, service requests, and history
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

$assetId = (int)($_GET['id'] ?? 0);
if (!$assetId) {
    flashMessage('danger', 'Invalid asset ID.');
    header('Location: index.php');
    exit;
}

// ── Fetch asset details ───────────────────────────────────────────────────────
$asset = null;
try {
    $stmt = $pdo->prepare(
        "SELECT a.id,
                a.asset_name,
                a.model_number,
                a.serial_number,
                a.receive_date,
                a.purchased_by,
                a.purchased_by_name,
                a.bill_file,
                a.status,
                a.approval_status,
                a.created_at,
                c.name AS category_name,
                CONCAT(u.first_name, ' ', u.last_name) AS assigned_to_name,
                u.email AS assigned_to_email,
                u.department AS assigned_to_dept,
                CONCAT(s.first_name, ' ', s.last_name) AS submitted_by_name
         FROM assets a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u      ON u.id = a.assigned_to
         LEFT JOIN users s      ON s.id = a.submitted_by
         WHERE a.id = ?"
    );
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
} catch (Exception $e) {
    error_log('view.php fetch asset error: ' . $e->getMessage());
}

if (!$asset) {
    flashMessage('danger', 'Asset not found.');
    header('Location: index.php');
    exit;
}

// ── Fetch asset history ───────────────────────────────────────────────────────
$history = [];
try {
    $histStmt = $pdo->prepare(
        "SELECT ah.id, ah.action, ah.notes, ah.created_at,
                CONCAT(fu.first_name, ' ', fu.last_name) AS from_user_name,
                CONCAT(tu.first_name, ' ', tu.last_name) AS to_user_name,
                CONCAT(cb.first_name, ' ', cb.last_name) AS created_by_name
         FROM asset_history ah
         LEFT JOIN users fu ON fu.id = ah.from_user
         LEFT JOIN users tu ON tu.id = ah.to_user
         LEFT JOIN users cb ON cb.id = ah.created_by
         WHERE ah.asset_id = ?
         ORDER BY ah.created_at DESC"
    );
    $histStmt->execute([$assetId]);
    $history = $histStmt->fetchAll();
} catch (Exception $e) {
    error_log('view.php fetch history error: ' . $e->getMessage());
}

// ── Fetch related service requests ───────────────────────────────────────────
$serviceRequests = [];
try {
    $srStmt = $pdo->prepare(
        "SELECT sr.id, sr.problem_description, sr.problem_since,
                sr.approx_amount, sr.status, sr.admin_remarks, sr.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS employee_name
         FROM service_requests sr
         LEFT JOIN users u ON u.id = sr.employee_id
         WHERE sr.asset_id = ?
         ORDER BY sr.created_at DESC"
    );
    $srStmt->execute([$assetId]);
    $serviceRequests = $srStmt->fetchAll();
} catch (Exception $e) {
    error_log('view.php fetch service requests error: ' . $e->getMessage());
}

// ── Fetch financial details ───────────────────────────────────────────────────
$financialDetails = null;
try {
    $fdStmt = $pdo->prepare("SELECT * FROM asset_financial_details WHERE asset_id = ?");
    $fdStmt->execute([$assetId]);
    $financialDetails = $fdStmt->fetch();
} catch (Exception $e) {
    error_log('view.php fetch financial details error: ' . $e->getMessage());
}

// ── Status helpers ────────────────────────────────────────────────────────────
$statusBadge = match($asset['status']) {
    'available'  => 'success',
    'assigned'   => 'primary',
    'in_service' => 'warning',
    'returned'   => 'secondary',
    default      => 'light',
};
$statusLabel = ucfirst(str_replace('_', ' ', $asset['status']));

// ── Action icon/color map ─────────────────────────────────────────────────────
$actionMeta = [
    'submitted'         => ['icon' => 'bi-box-arrow-in-down', 'color' => 'text-primary',   'label' => 'Submitted'],
    'assigned'          => ['icon' => 'bi-person-check',      'color' => 'text-success',   'label' => 'Assigned'],
    'transferred'       => ['icon' => 'bi-arrow-left-right',  'color' => 'text-info',      'label' => 'Transferred'],
    'returned'          => ['icon' => 'bi-box-arrow-in-left', 'color' => 'text-secondary', 'label' => 'Returned'],
    'service_requested' => ['icon' => 'bi-tools',             'color' => 'text-warning',   'label' => 'Service Requested'],
    'service_approved'  => ['icon' => 'bi-shield-check',      'color' => 'text-success',   'label' => 'Service Approved'],
    'service_completed' => ['icon' => 'bi-check2-all',        'color' => 'text-primary',   'label' => 'Service Completed'],
    'approved'          => ['icon' => 'bi-check2-circle',     'color' => 'text-success',   'label' => 'Approved'],
];

$srStatusBadge = [
    'pending'   => 'warning',
    'approved'  => 'info',
    'rejected'  => 'danger',
    'completed' => 'success',
];

$pageTitle = sanitize($asset['asset_name']) . ' — ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <?= renderFlash() ?>

  <!-- Page header -->
  <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">
      <i class="bi bi-box-seam me-2"></i><?= sanitize($asset['asset_name']) ?>
      <span class="badge bg-<?= $statusBadge ?> ms-2 fs-6 align-middle"><?= $statusLabel ?></span>
    </h4>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <?php if (($asset['approval_status'] ?? 'approved') === 'pending'): ?>
          <form method="POST" action="approve.php" class="d-inline"
                onsubmit="return confirm('Approve this asset submission?');">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="asset_id" value="<?= $assetId ?>">
            <button type="submit" class="btn btn-success btn-sm">
              <i class="bi bi-check2-circle me-1"></i>Approve
            </button>
          </form>
        <?php endif; ?>
        <?php if ($asset['status'] === 'available'): ?>
          <a href="assign.php?id=<?= $assetId ?>" class="btn btn-success btn-sm">
            <i class="bi bi-person-check me-1"></i>Assign
          </a>
        <?php endif; ?>
        <?php if ($asset['status'] === 'assigned'): ?>
          <a href="transfer.php?id=<?= $assetId ?>" class="btn btn-info btn-sm text-white">
            <i class="bi bi-arrow-left-right me-1"></i>Transfer
          </a>
          <a href="return.php?id=<?= $assetId ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-box-arrow-in-left me-1"></i>Return
          </a>
        <?php endif; ?>
        <a href="history.php?id=<?= $assetId ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-clock-history me-1"></i>Full History
        </a>
        <a href="financial_details.php?id=<?= $assetId ?>" class="btn btn-outline-success btn-sm">
          <i class="bi bi-cash-coin me-1"></i>Financial Details
        </a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>

  <div class="row g-4">

    <!-- Left column: Asset details -->
    <div class="col-lg-7">

      <!-- Asset Info -->
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-info-circle me-2"></i>Asset Information
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Asset Name</dt>
            <dd class="col-sm-8 fw-semibold"><?= sanitize($asset['asset_name']) ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Category</dt>
            <dd class="col-sm-8"><?= sanitize($asset['category_name'] ?? '—') ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Model Number</dt>
            <dd class="col-sm-8"><?= sanitize($asset['model_number'] ?? '—') ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Serial Number</dt>
            <dd class="col-sm-8">
              <?php if ($asset['serial_number']): ?>
                <code><?= sanitize($asset['serial_number']) ?></code>
              <?php else: ?>
                —
              <?php endif; ?>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Receive Date</dt>
            <dd class="col-sm-8">
              <?= $asset['receive_date']
                    ? htmlspecialchars(date('d M Y', strtotime($asset['receive_date'])))
                    : '—' ?>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Purchased By</dt>
            <dd class="col-sm-8">
              <?php if ($asset['purchased_by'] === 'me'): ?>
                <span class="badge bg-info text-dark">
                  <i class="bi bi-person-fill me-1"></i><?= sanitize($asset['purchased_by_name'] ?? 'Employee') ?>
                </span>
              <?php else: ?>
                <span class="badge bg-secondary">
                  <i class="bi bi-building me-1"></i><?= sanitize($asset['purchased_by_name'] ?? 'Company') ?>
                </span>
              <?php endif; ?>
            </dd>

            <?php if ($asset['bill_file']): ?>
              <dt class="col-sm-4 text-muted fw-normal">Purchase Bill</dt>
              <dd class="col-sm-8">
                <a href="<?= uploadUrl($asset['bill_file']) ?>"
                   target="_blank" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-file-earmark me-1"></i>View Bill
                </a>
              </dd>
            <?php endif; ?>

            <dt class="col-sm-4 text-muted fw-normal">Status</dt>
            <dd class="col-sm-8">
              <span class="badge bg-<?= $statusBadge ?>"><?= $statusLabel ?></span>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Approval</dt>
            <dd class="col-sm-8">
              <?php if (($asset['approval_status'] ?? 'approved') === 'pending'): ?>
                <span class="badge bg-warning text-dark">
                  <i class="bi bi-hourglass-split me-1"></i>Pending Approval
                </span>
              <?php else: ?>
                <span class="badge bg-success">
                  <i class="bi bi-check2-circle me-1"></i>Approved
                </span>
              <?php endif; ?>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal">Submitted By</dt>
            <dd class="col-sm-8"><?= sanitize($asset['submitted_by_name'] ?? '—') ?></dd>

            <dt class="col-sm-4 text-muted fw-normal">Added On</dt>
            <dd class="col-sm-8 mb-0">
              <?= htmlspecialchars(date('d M Y, H:i', strtotime($asset['created_at']))) ?>
            </dd>
          </dl>
        </div>
      </div>

      <!-- Current Assignment -->
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-person-badge me-2"></i>Current Assignment
        </div>
        <div class="card-body">
          <?php if ($asset['assigned_to_name'] && trim($asset['assigned_to_name']) !== ''): ?>
            <dl class="row mb-0">
              <dt class="col-sm-4 text-muted fw-normal">Employee</dt>
              <dd class="col-sm-8 fw-semibold"><?= sanitize($asset['assigned_to_name']) ?></dd>

              <?php if ($asset['assigned_to_email']): ?>
                <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                <dd class="col-sm-8">
                  <a href="mailto:<?= sanitize($asset['assigned_to_email']) ?>">
                    <?= sanitize($asset['assigned_to_email']) ?>
                  </a>
                </dd>
              <?php endif; ?>

              <?php if ($asset['assigned_to_dept']): ?>
                <dt class="col-sm-4 text-muted fw-normal">Department</dt>
                <dd class="col-sm-8 mb-0"><?= sanitize($asset['assigned_to_dept']) ?></dd>
              <?php endif; ?>
            </dl>
          <?php else: ?>
            <p class="text-muted mb-0">
              <i class="bi bi-person-slash me-2"></i>This asset is currently unassigned.
            </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Financial Details -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-cash-coin me-2 text-success"></i>Financial Details</span>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="financial_details.php?id=<?= $assetId ?>" class="btn btn-sm btn-outline-success">
              <i class="bi bi-pencil-square me-1"></i><?= $financialDetails ? 'Edit' : 'Add' ?>
            </a>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($financialDetails): ?>
            <dl class="row mb-0">
              <?php if ($financialDetails['purchase_date']): ?>
                <dt class="col-sm-5 text-muted fw-normal">Purchase Date</dt>
                <dd class="col-sm-7"><?= htmlspecialchars(date('d M Y', strtotime($financialDetails['purchase_date']))) ?></dd>
              <?php endif; ?>
              <?php if ($financialDetails['amount_usd'] !== null && $financialDetails['amount_usd'] !== ''): ?>
                <dt class="col-sm-5 text-muted fw-normal">Amount (USD)</dt>
                <dd class="col-sm-7">$<?= number_format((float)$financialDetails['amount_usd'], 2) ?></dd>
              <?php endif; ?>
              <?php if ($financialDetails['amount_inr'] !== null && $financialDetails['amount_inr'] !== ''): ?>
                <dt class="col-sm-5 text-muted fw-normal">Amount (INR)</dt>
                <dd class="col-sm-7">₹<?= number_format((float)$financialDetails['amount_inr'], 2) ?></dd>
              <?php endif; ?>
              <dt class="col-sm-5 text-muted fw-normal">Entity</dt>
              <dd class="col-sm-7">
                <span class="badge bg-primary"><?= htmlspecialchars($financialDetails['entity']) ?></span>
              </dd>
              <?php if ($financialDetails['bill_file']): ?>
                <dt class="col-sm-5 text-muted fw-normal">Bill File</dt>
                <dd class="col-sm-7 mb-0">
                  <a href="<?= uploadUrl($financialDetails['bill_file']) ?>"
                     target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark me-1"></i>View Bill
                  </a>
                </dd>
              <?php endif; ?>
            </dl>
          <?php else: ?>
            <p class="text-muted mb-0">
              <i class="bi bi-info-circle me-2"></i>No financial details added yet.
              <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="financial_details.php?id=<?= $assetId ?>" class="ms-1">Add now</a>
              <?php endif; ?>
            </p>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /col-lg-7 -->

    <!-- Right column: Service requests + History -->
    <div class="col-lg-5">

      <!-- Service Requests -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-tools me-2"></i>Service Requests</span>
          <span class="badge bg-light text-dark"><?= count($serviceRequests) ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($serviceRequests)): ?>
            <p class="text-muted text-center py-3 mb-0">No service requests for this asset.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($serviceRequests as $sr): ?>
                <li class="list-group-item py-3">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <span class="fw-semibold small"><?= sanitize($sr['employee_name'] ?? '—') ?></span>
                    <span class="badge bg-<?= $srStatusBadge[$sr['status']] ?? 'secondary' ?>">
                      <?= ucfirst($sr['status']) ?>
                    </span>
                  </div>
                  <p class="text-muted small mb-1"><?= sanitize($sr['problem_description']) ?></p>
                  <div class="d-flex justify-content-between text-muted" style="font-size:.75rem;">
                    <span>
                      <?php if ($sr['approx_amount']): ?>
                        <i class="bi bi-currency-rupee"></i><?= number_format((float)$sr['approx_amount'], 2) ?>
                      <?php endif; ?>
                    </span>
                    <span><?= htmlspecialchars(date('d M Y', strtotime($sr['created_at']))) ?></span>
                  </div>
                  <?php if ($sr['admin_remarks']): ?>
                    <div class="mt-1 small text-secondary">
                      <i class="bi bi-chat-left-text me-1"></i><?= sanitize($sr['admin_remarks']) ?>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <div class="card-footer bg-transparent">
            <a href="<?= SITE_URL ?>/admin/service/index.php" class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-list me-1"></i>All Service Requests
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent History (latest 5) -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clock-history me-2"></i>Recent History</span>
          <a href="history.php?id=<?= $assetId ?>" class="btn btn-sm btn-outline-light">
            View All
          </a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($history)): ?>
            <p class="text-muted text-center py-3 mb-0">No history records found.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach (array_slice($history, 0, 5) as $record):
                $meta = $actionMeta[$record['action']] ?? [
                    'icon'  => 'bi-circle',
                    'color' => 'text-muted',
                    'label' => ucfirst($record['action']),
                ];
              ?>
                <li class="list-group-item py-2 px-3">
                  <div class="d-flex align-items-start gap-2">
                    <span class="<?= $meta['color'] ?> mt-1 flex-shrink-0">
                      <i class="bi <?= $meta['icon'] ?>"></i>
                    </span>
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between">
                        <span class="fw-semibold small"><?= $meta['label'] ?></span>
                        <small class="text-muted">
                          <?= htmlspecialchars(date('d M Y', strtotime($record['created_at']))) ?>
                        </small>
                      </div>
                      <div class="text-muted" style="font-size:.75rem;">
                        <?php if ($record['to_user_name'] && trim($record['to_user_name']) !== ''): ?>
                          <i class="bi bi-person-plus me-1"></i><?= sanitize($record['to_user_name']) ?>
                        <?php endif; ?>
                        <?php if ($record['created_by_name'] && trim($record['created_by_name']) !== ''): ?>
                          · by <?= sanitize($record['created_by_name']) ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /col-lg-5 -->

  </div><!-- /.row -->

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
