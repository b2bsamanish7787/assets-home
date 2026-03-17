<?php
/**
 * Buzznation Assets Management System
 * File: admin/assets/index.php
 * Description: List all assets with category, assigned user, status, and admin actions
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

// ── Fetch all assets ──────────────────────────────────────────────────────────
$assets = [];
try {
    $assets = $pdo->query(
        "SELECT a.id,
                a.asset_name,
                a.model_number,
                a.serial_number,
                a.status,
                a.approval_status,
                c.name         AS category_name,
                CONCAT(u.first_name, ' ', u.last_name) AS assigned_to_name
         FROM assets a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u      ON u.id = a.assigned_to
         ORDER BY a.id ASC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Fetch assets error: ' . $e->getMessage());
}

$pageTitle = 'Assets — ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <?= renderFlash() ?>

  <div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-box-seam me-2"></i>Assets</h4>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table id="assetsTable" class="table table-hover align-middle w-100">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Asset Name</th>
              <th>Category</th>
              <th>Model</th>
              <th>Serial No.</th>
              <th>Assigned To</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assets as $i => $asset): ?>
              <?php
                $statusBadge = match($asset['status']) {
                    'available'  => 'success',
                    'assigned'   => 'primary',
                    'in_service' => 'warning',
                    'returned'   => 'secondary',
                    default      => 'light',
                };
                $statusLabel = ucfirst(str_replace('_', ' ', $asset['status']));
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= sanitize($asset['asset_name']) ?></td>
                <td><?= sanitize($asset['category_name'] ?? '—') ?></td>
                <td><?= sanitize($asset['model_number'] ?? '—') ?></td>
                <td><?= sanitize($asset['serial_number'] ?? '—') ?></td>
                <td><?= $asset['assigned_to_name'] ? sanitize($asset['assigned_to_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                <td>
                  <span class="badge bg-<?= $statusBadge ?>">
                    <?= $statusLabel ?>
                  </span>
                  <?php if (($asset['approval_status'] ?? 'approved') === 'pending'): ?>
                    <span class="badge bg-warning text-dark ms-1">
                      <i class="bi bi-hourglass-split"></i> Pending Approval
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-center text-nowrap">
                  <a href="view.php?id=<?= $asset['id'] ?>"
                     class="btn btn-sm btn-outline-primary me-1"
                     title="View Details">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="assign.php?id=<?= $asset['id'] ?>"
                       class="btn btn-sm btn-outline-success me-1"
                       title="Assign">
                      <i class="bi bi-person-check"></i>
                    </a>
                    <a href="transfer.php?id=<?= $asset['id'] ?>"
                       class="btn btn-sm btn-outline-info me-1"
                       title="Transfer">
                      <i class="bi bi-arrow-left-right"></i>
                    </a>
                    <a href="return.php?id=<?= $asset['id'] ?>"
                       class="btn btn-sm btn-outline-warning me-1"
                       title="Return">
                      <i class="bi bi-box-arrow-in-left"></i>
                    </a>
                    <a href="financial_details.php?id=<?= $asset['id'] ?>"
                       class="btn btn-sm btn-outline-success me-1"
                       title="Financial Details">
                      <i class="bi bi-cash-coin"></i>
                    </a>
                  <?php endif; ?>
                  <a href="history.php?id=<?= $asset['id'] ?>"
                     class="btn btn-sm btn-outline-secondary"
                     title="History">
                    <i class="bi bi-clock-history"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.main-content -->

<?php
$extraScripts = <<<JS
<script>
$(function () {
  $('#assetsTable').DataTable({
    pageLength: 25,
    order: [[0, 'asc']],
    columnDefs: [{ orderable: false, targets: -1 }]
  });
});
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
