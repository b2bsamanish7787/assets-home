<?php
/**
 * File: employee/requests/my_requests.php
 * Description: Lists all asset requests submitted by the currently logged-in employee.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, asset_requirement AS requirement, description, status, admin_remarks, created_at
                        FROM asset_requests
                        WHERE employee_id = ?
                        ORDER BY created_at DESC");
$stmt->execute([$currentUserId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Asset Requests';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-clipboard-check-fill me-2 text-primary"></i>My Asset Requests</h4>
      <a href="<?= SITE_URL ?>/employee/requests/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Request
      </a>
    </div>

    <?= getFlash() ?>

    <div class="card shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i>All My Requests
          <span class="badge bg-primary ms-1"><?= count($requests) ?></span>
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle" id="requestsTable">
            <thead class="table-dark">
              <tr>
                <th width="50">#</th>
                <th>Asset Requirement</th>
                <th>Description</th>
                <th width="130">Date Submitted</th>
                <th width="110" class="text-center">Status</th>
                <th>Admin Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $i => $req): ?>
              <?php
                $sc = match($req['status']) {
                  'pending'  => 'bg-warning text-dark',
                  'approved' => 'bg-success',
                  'rejected' => 'bg-danger',
                  default    => 'bg-secondary',
                };
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td class="fw-medium"><?= htmlspecialchars($req['requirement']) ?></td>
                <td class="text-muted">
                  <?= $req['description'] ? htmlspecialchars($req['description']) : '<span class="text-muted fst-italic">—</span>' ?>
                </td>
                <td class="text-nowrap text-muted small">
                  <?= date('d M Y', strtotime($req['created_at'])) ?>
                </td>
                <td class="text-center">
                  <span class="badge <?= $sc ?>"><?= ucfirst($req['status']) ?></span>
                </td>
                <td>
                  <?php if ($req['admin_remarks']): ?>
                    <span class="text-muted"><?= htmlspecialchars($req['admin_remarks']) ?></span>
                  <?php else: ?>
                    <span class="text-muted fst-italic">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>

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
  $('#requestsTable').DataTable({
    pageLength: 25,
    order: [[3, 'desc']],
    responsive: true,
    language: {
      search: 'Search requests:',
      emptyTable: '<div class="text-center text-muted py-4">' +
                    '<i class="bi bi-inbox fs-3 d-block mb-2"></i>' +
                    "You haven't submitted any asset requests yet." +
                    '<br>' +
                    '<a href="<?= SITE_URL ?>/employee/requests/new.php" class="btn btn-primary btn-sm mt-2">' +
                      '<i class="bi bi-plus-circle me-1"></i>Submit a Request' +
                    '</a>' +
                  '</div>'
    }
  });
});
</script>
