<?php
/**
 * Buzznation Assets Management System
 * File: admin/requests/index.php
 * Description: List all employee asset requests with approve/reject actions for admin
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

// ── Fetch all asset requests ──────────────────────────────────────────────────
$requests = [];
try {
    $requests = $pdo->query(
        "SELECT ar.id,
                ar.asset_requirement,
                ar.description,
                ar.status,
                ar.admin_remarks,
                ar.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS employee_name,
                u.email AS employee_email
         FROM asset_requests ar
         JOIN users u ON u.id = ar.employee_id
         ORDER BY ar.created_at DESC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Fetch asset requests error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Asset Requests — ' . SITE_NAME;

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
    <h4><i class="bi bi-clipboard-check me-2"></i>Asset Requests</h4>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table id="requestsTable" class="table table-hover align-middle w-100">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Requirement</th>
              <th>Description</th>
              <th>Date</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $i => $req): ?>
              <?php
                $badgeClass = match($req['status']) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default    => 'warning',
                };
                $statusLabel = ucfirst($req['status']);
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= sanitize($req['employee_name']) ?></td>
                <td><?= sanitize($req['asset_requirement']) ?></td>
                <td class="text-muted small" style="max-width:200px;">
                  <?= $req['description'] ? sanitize(mb_strimwidth($req['description'], 0, 80, '…')) : '—' ?>
                </td>
                <td><?= htmlspecialchars(date('d M Y', strtotime($req['created_at']))) ?></td>
                <td>
                  <span class="badge bg-<?= $badgeClass ?>"><?= $statusLabel ?></span>
                  <?php if ($req['admin_remarks'] && $req['status'] !== 'pending'): ?>
                    <i class="bi bi-chat-left-text ms-1 text-muted"
                       title="<?= sanitize($req['admin_remarks']) ?>"
                       data-bs-toggle="tooltip"></i>
                  <?php endif; ?>
                </td>
                <td class="text-center text-nowrap">
                  <a href="view.php?id=<?= $req['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($_SESSION['role'] === 'admin' && $req['status'] === 'pending'): ?>
                    <a href="action.php?action=approve&id=<?= $req['id'] ?>&csrf_token=<?= urlencode($csrf) ?>"
                       class="btn btn-sm btn-success me-1 btn-approve"
                       data-id="<?= $req['id'] ?>"
                       data-name="<?= sanitize($req['employee_name']) ?>"
                       title="Approve">
                      <i class="bi bi-check-lg"></i> Approve
                    </a>
                    <button type="button"
                            class="btn btn-sm btn-danger btn-reject"
                            data-id="<?= $req['id'] ?>"
                            data-name="<?= sanitize($req['employee_name']) ?>"
                            data-csrf="<?= $csrf ?>"
                            title="Reject">
                      <i class="bi bi-x-lg"></i> Reject
                    </button>
                  <?php endif; ?>
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
  // Initialise DataTable
  $('#requestsTable').DataTable({
    pageLength: 25,
    order: [[4, 'desc']],
    columnDefs: [{ orderable: false, targets: -1 }]
  });

  // Enable Bootstrap tooltips
  $('[data-bs-toggle="tooltip"]').tooltip();

  // Approve button — confirm via SweetAlert
  $(document).on('click', '.btn-approve', function (e) {
    e.preventDefault();
    const url  = $(this).attr('href');
    const name = $(this).data('name');
    Swal.fire({
      title: 'Approve Request?',
      text: `Approve the asset request from "${name}"?`,
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
    const id   = $(this).data('id');
    const name = $(this).data('name');
    const csrf = $(this).data('csrf');

    Swal.fire({
      title: 'Reject Request',
      text: `Provide remarks for rejecting "${name}"'s request:`,
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
