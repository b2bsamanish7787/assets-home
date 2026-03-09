<?php
/**
 * File: employee/change_password.php
 * Description: Form for any logged-in user to change their password with current-password verification.
 * Author: Buzznation IT Team
 */

require_once '../config/functions.php';
checkLogin();

$currentUserId = (int)$_SESSION['user_id'];
$errors        = [];
$success       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: ' . SITE_URL . '/employee/change_password.php');
        exit;
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']      ?? '';
    $confirmPassword = $_POST['confirm_password']  ?? '';

    // Fetch current user
    $userStmt = $pdo->prepare("SELECT id, password, role FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$currentUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        flashMessage('error', 'User session invalid. Please log in again.');
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }

    // Validate current password
    if (!password_verify($currentPassword, $user['password'])) {
        $errors[] = 'Your current password is incorrect.';
    }

    // Validate new password
    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $newPassword)) {
        $errors[] = 'New password must contain at least one letter.';
    }
    if (!preg_match('/[0-9]/', $newPassword)) {
        $errors[] = 'New password must contain at least one number.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }
    if ($currentPassword === $newPassword) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, is_first_login = 0, temp_password = NULL WHERE id = ?")
            ->execute([$hashedPassword, $currentUserId]);

        logActivity($currentUserId, 'password_changed', 'User changed their account password.');

        // Redirect based on role
        $dashboardMap = [
            'admin'    => SITE_URL . '/admin/dashboard.php',
            'hr'       => SITE_URL . '/hr/dashboard.php',
            'manager'  => SITE_URL . '/manager/dashboard.php',
            'employee' => SITE_URL . '/employee/dashboard.php',
        ];
        $role      = $user['role'] ?? 'employee';
        $dashboard = $dashboardMap[$role] ?? SITE_URL . '/employee/dashboard.php';

        flashMessage('success', 'Password changed successfully!');
        header('Location: ' . $dashboard);
        exit;
    }
}

$pageTitle = 'Change Password';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-key-fill me-2 text-primary"></i>Change Password</h4>
      <?php
        $backLinks = [
          'admin'    => SITE_URL . '/admin/dashboard.php',
          'hr'       => SITE_URL . '/hr/dashboard.php',
          'manager'  => SITE_URL . '/manager/dashboard.php',
          'employee' => SITE_URL . '/employee/dashboard.php',
        ];
        $backLink = $backLinks[$_SESSION['role'] ?? 'employee'] ?? SITE_URL . '/employee/dashboard.php';
      ?>
      <a href="<?= $backLink ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
      </a>
    </div>

    <?= getFlash() ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
      <div class="col-lg-5 col-md-7">
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-shield-lock-fill me-1 text-primary"></i>Update Your Password
          </div>
          <div class="card-body">

            <!-- Password requirements info -->
            <div class="alert alert-info alert-sm d-flex align-items-start gap-2 mb-4 py-2">
              <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
              <div class="small">
                <strong>Password Requirements:</strong>
                <ul class="mb-0 mt-1 ps-3">
                  <li>Minimum 8 characters</li>
                  <li>At least one letter (A–Z or a–z)</li>
                  <li>At least one number (0–9)</li>
                </ul>
              </div>
            </div>

            <form method="POST" action="" id="changePasswordForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">

              <!-- Current Password -->
              <div class="mb-3">
                <label class="form-label fw-semibold" for="current_password">
                  Current Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <input type="password" id="current_password" name="current_password"
                         class="form-control" required autocomplete="current-password"
                         placeholder="Enter your current password">
                  <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <div class="invalid-feedback">Current password is required.</div>
              </div>

              <!-- New Password -->
              <div class="mb-3">
                <label class="form-label fw-semibold" for="new_password">
                  New Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <input type="password" id="new_password" name="new_password"
                         class="form-control" required minlength="8" autocomplete="new-password"
                         placeholder="Enter your new password">
                  <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <!-- Strength indicator -->
                <div class="progress mt-2" style="height: 4px;">
                  <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                </div>
                <small id="passwordStrengthText" class="text-muted"></small>
                <div class="invalid-feedback">New password must be at least 8 characters.</div>
              </div>

              <!-- Confirm Password -->
              <div class="mb-4">
                <label class="form-label fw-semibold" for="confirm_password">
                  Confirm New Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <input type="password" id="confirm_password" name="confirm_password"
                         class="form-control" required autocomplete="new-password"
                         placeholder="Re-enter your new password">
                  <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <div id="confirmMismatch" class="invalid-feedback d-block text-danger small" style="display:none!important;"></div>
                <div class="invalid-feedback">Please confirm your new password.</div>
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-circle-fill me-1"></i>Update Password
                </button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(function () {
  // Toggle password visibility
  $('.toggle-password').on('click', function () {
    const targetId = $(this).data('target');
    const input    = $('#' + targetId);
    const icon     = $(this).find('i');
    if (input.attr('type') === 'password') {
      input.attr('type', 'text');
      icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
      input.attr('type', 'password');
      icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
  });

  // Password strength meter
  $('#new_password').on('input', function () {
    const val    = $(this).val();
    const bar    = $('#passwordStrengthBar');
    const text   = $('#passwordStrengthText');
    let score    = 0;
    if (val.length >= 8)                score++;
    if (/[A-Z]/.test(val))              score++;
    if (/[a-z]/.test(val))              score++;
    if (/[0-9]/.test(val))              score++;
    if (/[^A-Za-z0-9]/.test(val))       score++;

    const levels = [
      { pct: 0,   cls: '',          lbl: '' },
      { pct: 20,  cls: 'bg-danger', lbl: 'Very Weak' },
      { pct: 40,  cls: 'bg-warning',lbl: 'Weak' },
      { pct: 60,  cls: 'bg-info',   lbl: 'Fair' },
      { pct: 80,  cls: 'bg-primary',lbl: 'Strong' },
      { pct: 100, cls: 'bg-success',lbl: 'Very Strong' },
    ];
    const level = levels[score] || levels[0];
    bar.css('width', level.pct + '%').attr('class', 'progress-bar ' + level.cls);
    text.text(level.lbl);
  });

  // Confirm password match feedback
  $('#confirm_password').on('input', function () {
    const np = $('#new_password').val();
    const cp = $(this).val();
    const msg = $('#confirmMismatch');
    if (cp && np !== cp) {
      msg.text('Passwords do not match.').show();
    } else {
      msg.hide().text('');
    }
  });

  // Form submit validation
  $('#changePasswordForm').on('submit', function (e) {
    const np = $('#new_password').val();
    const cp = $('#confirm_password').val();
    if (np !== cp) {
      e.preventDefault();
      $('#confirmMismatch').text('Passwords do not match.').show();
      return;
    }
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass('was-validated');
  });
});
</script>
