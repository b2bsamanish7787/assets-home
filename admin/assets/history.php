<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/history.php
 * Description: View the full action history timeline for a specific asset
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
        "SELECT a.id, a.asset_name, a.model_number, a.serial_number,
                a.status, a.receive_date, a.purchased_by,
                c.name AS category_name,
                CONCAT(u.first_name, ' ', u.last_name) AS assigned_to_name,
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
    error_log('Fetch asset detail error: ' . $e->getMessage());
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
         ORDER BY ah.created_at ASC"
    );
    $histStmt->execute([$assetId]);
    $history = $histStmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch asset history error: ' . $e->getMessage());
}

// ── Status badge map ──────────────────────────────────────────────────────────
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
];

$flash     = getFlash();
$pageTitle = 'Asset History — ' . SITE_NAME;

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

  <div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-clock-history me-2"></i>Asset History</h4>
    <a href="index.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Assets
    </a>
  </div>

  <!-- Asset Info Card -->
  <div class="card mb-4">
    <div class="card-header fw-semibold bg-dark text-white">
      <i class="bi bi-box-seam me-2"></i>Asset Details
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Asset Name</div>
          <div class="fw-semibold"><?= sanitize($asset['asset_name']) ?></div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Category</div>
          <div><?= sanitize($asset['category_name'] ?? '—') ?></div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Model Number</div>
          <div><?= sanitize($asset['model_number'] ?? '—') ?></div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Serial Number</div>
          <div><?= sanitize($asset['serial_number'] ?? '—') ?></div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Currently Assigned To</div>
          <div><?= $asset['assigned_to_name'] ? sanitize($asset['assigned_to_name']) : '<span class="text-muted">Unassigned</span>' ?></div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Status</div>
          <div><span class="badge bg-<?= $statusBadge ?>"><?= $statusLabel ?></span></div>
        </div>
        <?php if ($asset['receive_date']): ?>
          <div class="col-sm-6 col-md-4">
            <div class="text-muted small">Received Date</div>
            <div><?= htmlspecialchars(date('d M Y', strtotime($asset['receive_date']))) ?></div>
          </div>
        <?php endif; ?>
        <div class="col-sm-6 col-md-4">
          <div class="text-muted small">Submitted By</div>
          <div><?= sanitize($asset['submitted_by_name'] ?? '—') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Timeline -->
  <div class="card">
    <div class="card-header fw-semibold bg-dark text-white">
      <i class="bi bi-list-ul me-2"></i>Action Timeline
    </div>
    <div class="card-body">
      <?php if (empty($history)): ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-clock-history fs-2"></i>
          <p class="mt-2 mb-0">No history records found for this asset.</p>
        </div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($history as $record):
            $meta  = $actionMeta[$record['action']] ?? ['icon' => 'bi-circle', 'color' => 'text-muted', 'label' => ucfirst($record['action'])];
          ?>
            <li class="list-group-item px-0">
              <div class="d-flex align-items-start gap-3">
                <!-- Icon -->
                <div class="flex-shrink-0 mt-1">
                  <span class="fs-4 <?= $meta['color'] ?>">
                    <i class="bi <?= $meta['icon'] ?>"></i>
                  </span>
                </div>

                <!-- Content -->
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                    <span class="fw-semibold"><?= $meta['label'] ?></span>
                    <small class="text-muted">
                      <?= htmlspecialchars(date('d M Y, H:i', strtotime($record['created_at']))) ?>
                    </small>
                  </div>

                  <div class="small text-muted mt-1">
                    <?php if ($record['from_user_name'] && trim($record['from_user_name']) !== ' '): ?>
                      <span><i class="bi bi-person-dash me-1"></i>From: <?= sanitize($record['from_user_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($record['to_user_name'] && trim($record['to_user_name']) !== ' '): ?>
                      <span class="ms-3"><i class="bi bi-person-plus me-1"></i>To: <?= sanitize($record['to_user_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($record['created_by_name'] && trim($record['created_by_name']) !== ' '): ?>
                      <span class="ms-3"><i class="bi bi-person-gear me-1"></i>By: <?= sanitize($record['created_by_name']) ?></span>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($record['notes'])): ?>
                    <div class="mt-1 text-secondary small">
                      <i class="bi bi-chat-left-text me-1"></i><?= sanitize($record['notes']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
