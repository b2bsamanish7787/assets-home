<?php
/**
 * Buzznation Assets Management System
 * File: admin/categories/create.php
 * Description: Create a new asset category
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$errors = [];
$old    = ['name' => '', 'description' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: create.php');
        exit;
    }

    $rawName     = trim($_POST['name'] ?? '');
    $name        = sanitize($rawName);
    $description = sanitize($_POST['description'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    $old = compact('name', 'description', 'status');

    // Validate
    if ($name === '') {
        $errors[] = 'Category name is required.';
    }

    // Check uniqueness
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $chk->execute([$name]);
        if ($chk->fetch()) {
            $errors[] = 'A category with this name already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO categories (name, description, status) VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $description, $status]);
            logActivity(
                (int)$_SESSION['user_id'],
                'create_category',
                "Created category: {$name}"
            );
            flashMessage('success', 'Category "' . $rawName . '" created successfully.');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            error_log('Create category error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }
}

$csrf      = generateCSRF();
$pageTitle = 'Add Category — ' . SITE_NAME;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">

  <?= renderFlash() ?>

  <div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-plus-circle me-2"></i>Add Category</h4>
    <a href="index.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= sanitize($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST" action="create.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="mb-3">
          <label for="name" class="form-label fw-semibold">
            Category Name <span class="text-danger">*</span>
          </label>
          <input type="text"
                 class="form-control"
                 id="name"
                 name="name"
                 value="<?= sanitize($old['name']) ?>"
                 required
                 maxlength="100"
                 placeholder="e.g. Laptops">
        </div>

        <div class="mb-3">
          <label for="description" class="form-label fw-semibold">Description</label>
          <textarea class="form-control"
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="500"
                    placeholder="Optional description"><?= sanitize($old['description']) ?></textarea>
        </div>

        <div class="mb-4">
          <label for="status" class="form-label fw-semibold">Status</label>
          <select class="form-select" id="status" name="status">
            <option value="active"   <?= $old['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i>Create Category
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
