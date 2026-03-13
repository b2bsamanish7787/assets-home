<?php
/**
 * Buzznation Assets Management System
 * File: admin/categories/index.php
 * Description: List all asset categories in a DataTable with edit/delete actions
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

// ── Fetch categories ─────────────────────────────────────────────────────────
$categories = [];
try {
    $categories = $pdo->query(
        "SELECT id, name, description, status, created_at FROM categories ORDER BY id ASC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('Fetch categories error: ' . $e->getMessage());
}

$csrf      = generateCSRF();
$flash     = getFlash();
$pageTitle = 'Categories — ' . SITE_NAME;

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
    <h4><i class="bi bi-tags me-2"></i>Asset Categories</h4>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Category
      </a>
    <?php endif; ?>
  </div>

  <!-- Categories Table -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table id="categoriesTable" class="table table-hover align-middle w-100">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Description</th>
              <th>Status</th>
              <?php if ($_SESSION['role'] === 'admin'): ?>
                <th class="text-center">Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $i => $cat): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= sanitize($cat['name']) ?></td>
                <td><?= sanitize($cat['description'] ?? '—') ?></td>
                <td>
                  <?php if ($cat['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <td class="text-center">
                    <a href="edit.php?id=<?= $cat['id'] ?>"
                       class="btn btn-sm btn-outline-primary me-1"
                       title="Edit">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <a href="#"
                       class="btn btn-sm btn-outline-danger btn-delete"
                       data-id="<?= $cat['id'] ?>"
                       data-name="<?= sanitize($cat['name']) ?>"
                       data-csrf="<?= $csrf ?>"
                       title="Delete">
                      <i class="bi bi-trash"></i>
                    </a>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.main-content -->

<?php
$extraScripts = <<<'JS'
<script>
$(function () {
  $('#categoriesTable').DataTable({
    pageLength: 25,
    order: [[0, 'asc']],
    columnDefs: [{ orderable: false, targets: -1 }]
  });

  // Delete confirmation
  $(document).on('click', '.btn-delete', function (e) {
    e.preventDefault();
    const id   = $(this).data('id');
    const name = $(this).data('name');
    const csrf = $(this).data('csrf');

    Swal.fire({
      title: 'Delete Category?',
      text: `"${name}" will be permanently deleted.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it!'
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = 'delete.php?id=' + id + '&csrf_token=' + encodeURIComponent(csrf);
      }
    });
  });
});
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
