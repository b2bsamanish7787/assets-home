<?php
/**
 * File: admin/employees/index.php
 * Description: List all employees in a DataTable with actions for edit, delete, reset password, and toggle status
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

// ── Action Handlers ───────────────────────────────────────────────────────────

// Toggle active/inactive status via AJAX (CSRF validated via POST body or GET param)
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    if (!validateCSRF($_GET['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
        logActivity((int)$_SESSION['user_id'], 'toggle_status', "Toggled user ID {$id} status to {$newStatus}.");
        echo json_encode(['success' => true, 'status' => $newStatus]);
    } catch (Exception $e) {
        error_log('Toggle status error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// Reset password (CSRF validated)
if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['id'])) {
    if (!validateCSRF($_GET['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Action aborted.');
        header('Location: index.php');
        exit;
    }
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user) {
            $tempPass = generateTempPassword();
            $hashed   = password_hash($tempPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ?, temp_password = ?, is_first_login = 1, updated_at = NOW() WHERE id = ?")->execute([$hashed, $tempPass, $id]);
            $name = trim($user['first_name'] . ' ' . $user['last_name']);
            sendEmail($user['email'], 'Your Password Has Been Reset — ' . SITE_NAME, emailWelcome($name, $user['email'], $tempPass));
            logActivity((int)$_SESSION['user_id'], 'reset_password', "Reset password for user ID {$id} ({$user['email']}).");
            flashMessage('success', "Password reset and emailed to {$user['email']}.");
        } else {
            flashMessage('danger', 'Employee not found.');
        }
    } catch (Exception $e) {
        error_log('Reset password error: ' . $e->getMessage());
        flashMessage('danger', 'Failed to reset password.');
    }
    header('Location: index.php');
    exit;
}

// ── Fetch employees ───────────────────────────────────────────────────────────
$employees = [];
try {
    $employees = $pdo->query("SELECT id, employee_id, first_name, last_name, email, department, designation, role, status FROM users ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {
    error_log('Fetch employees error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Employees — ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <!-- Flash Message -->
  <?php if ($flash): ?>
    <div class="flash-container">
      <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show auto-dismiss" role="alert">
        <?= sanitize($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-people me-2"></i>Employees</h4>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Add Employee
      </a>
    <?php endif; ?>
  </div>

  <!-- Employees Table -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table id="employeesTable" class="table table-hover align-middle w-100">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Employee ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Department</th>
              <th>Designation</th>
              <th>Role</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $i => $emp): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= sanitize($emp['employee_id'] ?? '') ?></td>
                <td><?= sanitize(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?></td>
                <td><?= sanitize($emp['email']) ?></td>
                <td><?= sanitize($emp['department'] ?? '—') ?></td>
                <td><?= sanitize($emp['designation'] ?? '—') ?></td>
                <td>
                  <?php
                    $roleBadge = match($emp['role']) {
                        'admin'    => 'bg-dark',
                        'hr'       => 'bg-primary',
                        default    => 'bg-secondary',
                    };
                  ?>
                  <span class="badge <?= $roleBadge ?>"><?= ucfirst(sanitize($emp['role'])) ?></span>
                </td>
                <td>
                  <span class="badge <?= $emp['status'] === 'active' ? 'bg-success' : 'bg-danger' ?> status-badge"
                        data-id="<?= $emp['id'] ?>">
                    <?= ucfirst(sanitize($emp['status'])) ?>
                  </span>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                      <!-- Edit -->
                      <a href="edit.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <!-- Delete -->
                      <button type="button"
                              class="btn btn-sm btn-outline-danger btn-delete"
                              data-href="delete.php?id=<?= $emp['id'] ?>&csrf_token=<?= urlencode($csrf) ?>"
                              title="Delete">
                        <i class="bi bi-trash"></i>
                      </button>
                      <!-- Reset Password -->
                      <a href="?action=reset&id=<?= $emp['id'] ?>&csrf_token=<?= urlencode($csrf) ?>"
                         class="btn btn-sm btn-outline-warning btn-reset-pass"
                         title="Reset Password">
                        <i class="bi bi-key"></i>
                      </a>
                    <?php endif; ?>
                    <!-- Toggle Status -->
                    <button type="button"
                            class="btn btn-sm <?= $emp['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?> btn-toggle-status"
                            data-id="<?= $emp['id'] ?>"
                            data-csrf="<?= $csrf ?>"
                            title="Toggle Status">
                      <i class="bi <?= $emp['status'] === 'active' ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div><!-- /.main-content -->

<script>
const siteUrl = '<?= SITE_URL ?>';

// DataTable init
$(document).ready(function () {
  $('#employeesTable').DataTable({
    pageLength: 25,
    order: [[0, 'asc']],
    columnDefs: [{ orderable: false, targets: [8] }]
  });

  // ── Delete confirmation ─────────────────────────────────────────────────────
  $(document).on('click', '.btn-delete', function () {
    const href = $(this).data('href');
    Swal.fire({
      title: 'Delete Employee?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete'
    }).then(result => {
      if (result.isConfirmed) window.location.href = href;
    });
  });

  // ── Reset password confirmation ─────────────────────────────────────────────
  $(document).on('click', '.btn-reset-pass', function (e) {
    e.preventDefault();
    const href = $(this).attr('href');
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

  // ── Toggle status (AJAX) ────────────────────────────────────────────────────
  $(document).on('click', '.btn-toggle-status', function () {
    const btn    = $(this);
    const id     = btn.data('id');
    const csrf   = btn.data('csrf');
    const badge  = $('.status-badge[data-id="' + id + '"]');

    $.get('index.php', { action: 'toggle', id: id, csrf_token: csrf }, function (res) {
      if (res.success) {
        const active = res.status === 'active';
        badge.text(active ? 'Active' : 'Inactive')
             .removeClass('bg-success bg-danger')
             .addClass(active ? 'bg-success' : 'bg-danger');

        btn.removeClass('btn-outline-secondary btn-outline-success')
           .addClass(active ? 'btn-outline-secondary' : 'btn-outline-success');
        btn.find('i').removeClass('bi-toggle-on bi-toggle-off')
                     .addClass(active ? 'bi-toggle-on' : 'bi-toggle-off');

        Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: 'Status updated to ' + res.status, showConfirmButton: false, timer: 2000 });
      } else {
        Swal.fire('Error', res.message || 'Failed to update status.', 'error');
      }
    }, 'json').fail(function () {
      Swal.fire('Error', 'Request failed. Please try again.', 'error');
    });
  });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
