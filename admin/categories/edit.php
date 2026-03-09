<?php
/**
 * Buzznation Assets Management System
 * File: admin/categories/edit.php
 * Description: Edit an existing asset category
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flashMessage('danger', 'Invalid category ID.');
    header('Location: index.php');
    exit;
}

// Fetch existing category
$category = null;
try {
    $stmt = $pdo->prepare("SELECT id, name, description, status FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
} catch (Exception $e) {
    error_log('Fetch category error: ' . $e->getMessage());
}

if (!$category) {
    flashMessage('danger', 'Category not found.');
    header('Location: index.php');
    exit;
}

$errors = [];
$old    = $category;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Invalid CSRF token. Please try again.');
        header('Location: edit.php?id=' . $id);
        exit;
    }

    $name        = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    $old = compact('name', 'description', 'status') + ['id' => $id];

    if ($name === '') {
        $errors[] = 'Category name is required.';
    }

    // Check uniqueness (excluding current record)
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $chk->execute([$name, $id]);
        if ($chk->fetch()) {
            $errors[] = 'Another category with this name already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?"
            );
            $stmt->execute([$name, $description, $status, $id]);
            logActivity(
                (int)$_SESSION['user_id'],
                'edit_category',
                "Updated category ID {$id}: {$name}"
            );
            flashMessage('success', "Category \"{$name}\" updated successfully.");
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            error_log('Edit category error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Edit Category — ' . SITE_NAME;

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
    <h4><i class="bi bi-pencil-square me-2"></i>Edit Category</h4>
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
      <form method="POST" action="edit.php?id=<?= $id ?>" novalidate>
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
                 maxlength="100">
        </div>

        <div class="mb-3">
          <label for="description" class="form-label fw-semibold">Description</label>
          <textarea class="form-control"
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="500"><?= sanitize($old['description'] ?? '') ?></textarea>
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
            <i class="bi bi-check-circle me-1"></i>Update Category
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div><!-- /.main-content -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>
