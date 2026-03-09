<?php
/**
 * File: employee/assets/my_assets.php
 * Description: Lists all assets assigned to the currently logged-in employee with service request action.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT a.id, a.asset_name, c.name AS category_name, a.model_number,
                               a.serial_number, a.receive_date, a.purchased_by, a.status
                        FROM assets a
                        LEFT JOIN categories c ON c.id = a.category_id
                        WHERE a.assigned_to = ?
                        ORDER BY a.receive_date DESC");
$stmt->execute([$currentUserId]);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Assets';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-box-seam-fill me-2 text-primary"></i>My Assets</h4>
      <a href="<?= SITE_URL ?>/employee/assets/submit.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Submit New Asset
      </a>
    </div>

    <?= getFlash() ?>

    <div class="card shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i>Assigned Assets
          <span class="badge bg-primary ms-1"><?= count($assets) ?></span>
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle" id="myAssetsTable">
            <thead class="table-dark">
              <tr>
                <th width="50">#</th>
                <th>Asset Name</th>
                <th>Category</th>
                <th>Model No.</th>
                <th>Serial No.</th>
                <th>Receive Date</th>
                <th>Purchased By</th>
                <th>Status</th>
                <th class="text-center" width="130">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assets as $i => $asset): ?>
              <?php
                $sc = match($asset['status']) {
                  'assigned'   => 'bg-primary',
                  'available'  => 'bg-success',
                  'in_service' => 'bg-warning text-dark',
                  'disposed'   => 'bg-danger',
                  default      => 'bg-secondary',
                };
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td class="fw-medium"><?= htmlspecialchars($asset['asset_name']) ?></td>
                <td><?= htmlspecialchars($asset['category_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($asset['model_number'] ?? '—') ?></td>
                <td><code><?= htmlspecialchars($asset['serial_number'] ?? '—') ?></code></td>
                <td class="text-nowrap">
                  <?= $asset['receive_date'] ? date('d M Y', strtotime($asset['receive_date'])) : '—' ?>
                </td>
                <td>
                  <?php if ($asset['purchased_by'] === 'me'): ?>
                    <span class="badge bg-info text-dark"><i class="bi bi-person-fill me-1"></i>Me</span>
                  <?php else: ?>
                    <span class="badge bg-secondary"><i class="bi bi-building me-1"></i>Company</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_', ' ', $asset['status'])) ?></span></td>
                <td class="text-center">
                  <a href="<?= SITE_URL ?>/employee/service/new.php?asset_id=<?= $asset['id'] ?>"
                     class="btn btn-outline-warning btn-sm" title="Request Service">
                    <i class="bi bi-tools me-1"></i>Service
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($assets)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-3 d-block mb-2"></i>No assets assigned to you yet.
                  <br>
                  <a href="<?= SITE_URL ?>/employee/assets/submit.php" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-plus-circle me-1"></i>Submit Your First Asset
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

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  $('#myAssetsTable').DataTable({
    pageLength: 25,
    order: [[5, 'desc']],
    responsive: true,
    language: { search: 'Search my assets:' }
  });
});
</script>
