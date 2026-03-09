<?php
/**
 * Buzznation Assets Management System
 * File: admin/service/index.php
 * Description: List all service requests with asset/employee info and admin actions
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

// ── Fetch service requests ────────────────────────────────────────────────────
$serviceRequests = [];
try {
    $serviceRequests = $pdo->query(
        "SELECT sr.id,
                sr.problem_description,
                sr.problem_since,
                sr.approx_amount,
                sr.status,
                sr.admin_remarks,
                sr.service_bill,
                sr.created_at,
                a.asset_name,
                CONCAT(u.first_name, ' ', u.last_name) AS employee_name,
                u.email AS employee_email
         FROM service_requests sr
         JOIN assets a ON a.id = sr.asset_id
         JOIN users u  ON u.id = sr.employee_id
         ORDER BY sr.created_at DESC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Fetch service requests error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Service Requests — ' . SITE_NAME;

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
    <h4><i class="bi bi-tools me-2"></i>Service Requests</h4>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table id="serviceTable" class="table table-hover align-middle w-100">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Asset</th>
              <th>Problem Description</th>
              <th>Problem Since</th>
              <th>Approx. Amount</th>
              <th>Status</th>
              <?php if ($_SESSION['role'] === 'admin'): ?>
                <th class="text-center">Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($serviceRequests as $i => $sr): ?>
              <?php
                $badgeClass = match($sr['status']) {
                    'approved'  => 'success',
                    'rejected'  => 'danger',
                    'completed' => 'primary',
                    default     => 'warning',
                };
                $statusLabel = ucfirst($sr['status']);
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= sanitize($sr['employee_name']) ?></td>
                <td><?= sanitize($sr['asset_name']) ?></td>
                <td class="text-muted small" style="max-width:200px;">
                  <?= sanitize(mb_strimwidth($sr['problem_description'], 0, 80, '…')) ?>
                </td>
                <td><?= $sr['problem_since'] ? htmlspecialchars(date('d M Y', strtotime($sr['problem_since']))) : '—' ?></td>
                <td>
                  <?= $sr['approx_amount'] !== null
                      ? '₹ ' . number_format((float)$sr['approx_amount'], 2)
                      : '—' ?>
                </td>
                <td>
                  <span class="badge bg-<?= $badgeClass ?>"><?= $statusLabel ?></span>
                  <?php if ($sr['admin_remarks'] && $sr['status'] !== 'pending'): ?>
                    <i class="bi bi-chat-left-text ms-1 text-muted"
                       title="<?= sanitize($sr['admin_remarks']) ?>"
                       data-bs-toggle="tooltip"></i>
                  <?php endif; ?>
                </td>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <td class="text-center text-nowrap">

                    <?php if ($sr['status'] === 'pending'): ?>
                      <button type="button"
                              class="btn btn-sm btn-success me-1 btn-approve-svc"
                              data-id="<?= $sr['id'] ?>"
                              data-csrf="<?= $csrf ?>"
                              title="Approve">
                        <i class="bi bi-check-lg"></i>
                      </button>
                      <button type="button"
                              class="btn btn-sm btn-danger btn-reject-svc"
                              data-id="<?= $sr['id'] ?>"
                              data-csrf="<?= $csrf ?>"
                              title="Reject">
                        <i class="bi bi-x-lg"></i>
                      </button>

                    <?php elseif ($sr['status'] === 'approved'): ?>
                      <!-- Upload Bill -->
                      <button type="button"
                              class="btn btn-sm btn-outline-secondary me-1 btn-upload-bill"
                              data-id="<?= $sr['id'] ?>"
                              data-csrf="<?= $csrf ?>"
                              title="Upload Bill">
                        <i class="bi bi-upload"></i> Bill
                      </button>
                      <!-- Mark Complete -->
                      <button type="button"
                              class="btn btn-sm btn-primary btn-complete-svc"
                              data-id="<?= $sr['id'] ?>"
                              data-csrf="<?= $csrf ?>"
                              title="Mark Complete">
                        <i class="bi bi-check2-all"></i>
                      </button>

                    <?php elseif ($sr['status'] === 'completed' && $sr['service_bill']): ?>
                      <a href="<?= SITE_URL . '/assets/uploads/' . urlencode($sr['service_bill']) ?>"
                         target="_blank"
                         class="btn btn-sm btn-outline-secondary"
                         title="View Bill">
                        <i class="bi bi-file-earmark-text"></i> Bill
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>

                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Hidden bill upload form (submitted via JS) -->
  <?php if ($_SESSION['role'] === 'admin'): ?>
    <form id="billUploadForm" method="POST" action="action.php" enctype="multipart/form-data" class="d-none">
      <input type="hidden" name="csrf_token" id="billCsrf">
      <input type="hidden" name="action"     id="billAction" value="upload_bill">
      <input type="hidden" name="id"         id="billId">
      <input type="file"   name="service_bill" id="billFile" accept=".pdf,.jpg,.jpeg,.png">
    </form>
  <?php endif; ?>

</div><!-- /.main-content -->

<?php
$extraScripts = <<<JS
<script>
$(function () {
  $('#serviceTable').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
    columnDefs: [{ orderable: false, targets: -1 }]
  });

  $('[data-bs-toggle="tooltip"]').tooltip();

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
      text: 'Asset status will revert to assigned.',
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
