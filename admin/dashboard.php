<?php
/**
 * Buzznation Assets Management System
 * File: admin/dashboard.php
 * Description: Admin dashboard with stat cards and notifications
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

checkLogin();
checkRole(['admin']);

$pageTitle = 'Dashboard — ' . SITE_NAME;

// Fetch dashboard stats
$stats = [];
$queries = [
    'total_employees'   => "SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'",
    'total_assets'      => "SELECT COUNT(*) FROM assets",
    'assigned_assets'   => "SELECT COUNT(*) FROM assets WHERE status = 'assigned'",
    'available_assets'  => "SELECT COUNT(*) FROM assets WHERE status = 'available'",
    'pending_requests'  => "SELECT COUNT(*) FROM asset_requests WHERE status = 'pending'",
    'pending_service'   => "SELECT COUNT(*) FROM service_requests WHERE status = 'pending'",
];

foreach ($queries as $key => $sql) {
    try {
        $stats[$key] = (int)$pdo->query($sql)->fetchColumn();
    } catch (Exception $e) {
        $stats[$key] = 0;
    }
}

// Recent notifications (latest 10)
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.*, u.first_name, u.last_name
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        WHERE n.user_id = ? OR n.user_id IS NULL
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) { /* silence */ }

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
  <?= renderFlash() ?>

  <!-- Page Header -->
  <div class="page-header">
    <h4><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
    <span class="text-muted small">Welcome back, <?= sanitize($_SESSION['name']) ?>!</span>
  </div>

  <!-- Stat Cards -->
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-2">
      <div class="card stat-card border-primary h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Employees</div>
            <h3 class="fw-bold mb-0"><?= $stats['total_employees'] ?></h3>
          </div>
          <i class="bi bi-people stat-icon text-primary"></i>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
          <a href="<?= SITE_URL ?>/admin/employees/index.php" class="small text-primary">View all →</a>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card stat-card border-success h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Total Assets</div>
            <h3 class="fw-bold mb-0"><?= $stats['total_assets'] ?></h3>
          </div>
          <i class="bi bi-box-seam stat-icon text-success"></i>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
          <a href="<?= SITE_URL ?>/admin/assets/index.php" class="small text-success">View all →</a>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card stat-card border-info h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Assigned</div>
            <h3 class="fw-bold mb-0"><?= $stats['assigned_assets'] ?></h3>
          </div>
          <i class="bi bi-person-check stat-icon text-info"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card stat-card border-secondary h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Available</div>
            <h3 class="fw-bold mb-0"><?= $stats['available_assets'] ?></h3>
          </div>
          <i class="bi bi-box stat-icon text-secondary"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card stat-card border-warning h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Pending Requests</div>
            <h3 class="fw-bold mb-0"><?= $stats['pending_requests'] ?></h3>
          </div>
          <i class="bi bi-clipboard stat-icon text-warning"></i>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
          <a href="<?= SITE_URL ?>/admin/requests/index.php" class="small text-warning">View →</a>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card stat-card border-danger h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Pending Service</div>
            <h3 class="fw-bold mb-0"><?= $stats['pending_service'] ?></h3>
          </div>
          <i class="bi bi-tools stat-icon text-danger"></i>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
          <a href="<?= SITE_URL ?>/admin/service/index.php" class="small text-danger">View →</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Notifications -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-bell me-2"></i>Recent Notifications</span>
      <a href="<?= SITE_URL ?>/admin/notifications/index.php" class="btn btn-sm btn-outline-light">View All</a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($notifications)): ?>
        <p class="text-center text-muted py-4">No notifications yet.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($notifications as $n): ?>
            <li class="list-group-item <?= !$n['is_read'] ? 'list-group-item-warning' : '' ?> d-flex justify-content-between align-items-start">
              <div>
                <span class="badge bg-secondary me-2"><?= sanitize($n['type'] ?? '') ?></span>
                <?= sanitize($n['message'] ?? '') ?>
                <?php if ($n['link']): ?>
                  <a href="<?= sanitize($n['link']) ?>" class="ms-2 small">[View]</a>
                <?php endif; ?>
              </div>
              <small class="text-muted"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div><!-- /.main-content -->

<script>const siteUrl = '<?= SITE_URL ?>';</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
