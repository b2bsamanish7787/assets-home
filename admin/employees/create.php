<?php
/**
 * File: admin/employees/create.php
 * Description: Form to create a new employee account with admin-entered ID and welcome email
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$errors   = [];
$formData = ['employee_id' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'role' => 'employee',
             'manager_name' => '', 'manager_email' => '', 'designation' => '', 'department' => ''];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: create.php');
        exit;
    }

    // Collect & sanitise raw input
    $formData = [
        'employee_id'   => trim($_POST['employee_id']   ?? ''),
        'first_name'    => trim($_POST['first_name']    ?? ''),
        'last_name'     => trim($_POST['last_name']     ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'role'          => trim($_POST['role']          ?? 'employee'),
        'manager_name'  => trim($_POST['manager_name']  ?? ''),
        'manager_email' => trim($_POST['manager_email'] ?? ''),
        'designation'   => trim($_POST['designation']   ?? ''),
        'department'    => trim($_POST['department']    ?? ''),
    ];

    // Validation
    if ($formData['employee_id'] === '') {
        $errors['employee_id'] = 'Employee ID is required.';
    } else {
        $dupEmp = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
        $dupEmp->execute([$formData['employee_id']]);
        if ($dupEmp->fetch()) {
            $errors['employee_id'] = 'This Employee ID is already in use.';
        }
    }

    // Validation
    if ($formData['first_name'] === '') {
        $errors['first_name'] = 'First name is required.';
    }
    if ($formData['last_name'] === '') {
        $errors['last_name'] = 'Last name is required.';
    }
    if ($formData['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $dup->execute([$formData['email']]);
        if ($dup->fetch()) {
            $errors['email'] = 'This email address is already registered.';
        }
    }
    if (!in_array($formData['role'], ['admin', 'hr', 'employee'], true)) {
        $errors['role'] = 'Invalid role selected.';
    }
    if ($formData['manager_name'] === '') {
        $errors['manager_name'] = 'Manager name is required.';
    }
    if ($formData['manager_email'] === '') {
        $errors['manager_email'] = 'Manager email is required.';
    } elseif (!filter_var($formData['manager_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['manager_email'] = 'Enter a valid manager email address.';
    }

    if (empty($errors)) {
        $employeeId = $formData['employee_id'];
        $tempPass   = generateTempPassword();
        $hashed     = password_hash($tempPass, PASSWORD_DEFAULT);
        $fullName   = $formData['first_name'] . ' ' . $formData['last_name'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users
                    (employee_id, first_name, last_name, email, password, role,
                     manager_name, manager_email, designation, department, temp_password, is_first_login, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())
            ");
            $stmt->execute([
                $employeeId,
                $formData['first_name'],
                $formData['last_name'],
                $formData['email'],
                $hashed,
                $formData['role'],
                $formData['manager_name'],
                $formData['manager_email'],
                $formData['designation'],
                $formData['department'],
                $tempPass,
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Send welcome email
            sendEmail(
                $formData['email'],
                'Welcome to ' . SITE_NAME,
                emailWelcome($fullName, $formData['email'], $tempPass)
            );

            logActivity(
                (int)$_SESSION['user_id'],
                'create_employee',
                "Created employee {$fullName} ({$employeeId}) with role {$formData['role']}."
            );

            sendNotification(
                null,
                'employee_created',
                "New employee {$fullName} ({$employeeId}) was added.",
                SITE_URL . '/admin/employees/edit.php?id=' . $newId
            );

            flashMessage('success', "Employee {$fullName} created successfully. Login credentials sent to {$formData['email']}.");
            header('Location: index.php');
            exit;

        } catch (Exception $e) {
            error_log('Create employee error: ' . $e->getMessage());
            $errors['db'] = 'A database error occurred. Please try again.';
        }
    }
}

$csrf      = generateCSRF();
$pageTitle = 'Add Employee — ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <!-- Page Header -->
  <div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-person-plus me-2"></i>Add Employee</h4>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to List
    </a>
  </div>

  <?php if (!empty($errors['db'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= sanitize($errors['db']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><i class="bi bi-person-lines-fill me-2"></i>Employee Details</div>
    <div class="card-body">
      <form method="POST" action="create.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <!-- Employee ID (admin-entered) -->
        <div class="mb-3">
          <label for="employee_id" class="form-label fw-semibold">Employee ID <span class="text-danger">*</span></label>
          <input type="text" id="employee_id" name="employee_id"
                 class="form-control <?= isset($errors['employee_id']) ? 'is-invalid' : '' ?>"
                 value="<?= sanitize($formData['employee_id']) ?>"
                 placeholder="e.g. EMP042" required>
          <?php if (isset($errors['employee_id'])): ?>
            <div class="invalid-feedback"><?= sanitize($errors['employee_id']) ?></div>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <!-- First Name -->
          <div class="col-md-6">
            <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
            <input type="text" id="first_name" name="first_name"
                   class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                   value="<?= sanitize($formData['first_name']) ?>" required>
            <?php if (isset($errors['first_name'])): ?>
              <div class="invalid-feedback"><?= sanitize($errors['first_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Last Name -->
          <div class="col-md-6">
            <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
            <input type="text" id="last_name" name="last_name"
                   class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                   value="<?= sanitize($formData['last_name']) ?>" required>
            <?php if (isset($errors['last_name'])): ?>
              <div class="invalid-feedback"><?= sanitize($errors['last_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Email -->
          <div class="col-md-6">
            <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
            <input type="email" id="email" name="email"
                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   value="<?= sanitize($formData['email']) ?>" required>
            <?php if (isset($errors['email'])): ?>
              <div class="invalid-feedback"><?= sanitize($errors['email']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Role -->
          <div class="col-md-6">
            <label for="role" class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
            <select id="role" name="role" class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" required>
              <option value="employee" <?= $formData['role'] === 'employee' ? 'selected' : '' ?>>Employee</option>
              <option value="hr"       <?= $formData['role'] === 'hr'       ? 'selected' : '' ?>>HR</option>
              <option value="admin"    <?= $formData['role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
            </select>
            <?php if (isset($errors['role'])): ?>
              <div class="invalid-feedback"><?= sanitize($errors['role']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Manager Name -->
          <div class="col-md-6">
            <label for="manager_name" class="form-label fw-semibold">Manager Name <span class="text-danger">*</span></label>
            <input type="text" id="manager_name" name="manager_name"
                   class="form-control <?= isset($errors['manager_name']) ? 'is-invalid' : '' ?>"
                   value="<?= sanitize($formData['manager_name']) ?>" required>
            <?php if (isset($errors['manager_name'])): ?>
              <div class="invalid-feedback"><?= sanitize($errors['manager_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Manager Email -->
          <div class="col-md-6">
            <label for="manager_email" class="form-label fw-semibold">Manager Email <span class="text-danger">*</span></label>
            <input type="email" id="manager_email" name="manager_email"
                   class="form-control <?= isset($errors['manager_email']) ? 'is-invalid' : '' ?>"
                   value="<?= sanitize($formData['manager_email']) ?>" required>
            <?php if (isset($errors['manager_email'])): ?>
              <div class="invalid-feedback"><?= sanitize($errors['manager_email']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Designation -->
          <div class="col-md-6">
            <label for="designation" class="form-label fw-semibold">Designation</label>
            <input type="text" id="designation" name="designation"
                   class="form-control"
                   value="<?= sanitize($formData['designation']) ?>">
          </div>

          <!-- Department -->
          <div class="col-md-6">
            <label for="department" class="form-label fw-semibold">Department</label>
            <input type="text" id="department" name="department"
                   class="form-control"
                   value="<?= sanitize($formData['department']) ?>">
          </div>
        </div>

        <hr class="my-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-check me-1"></i>Create Employee
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div><!-- /.main-content -->

<script>const siteUrl = '<?= SITE_URL ?>';</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
