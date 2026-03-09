<?php
/**
 * File: hr/dashboard.php
 * Description: HR role dashboard showing key stats, recent notifications, and quick navigation links.
 * Author: Buzznation IT Team
 */

require_once '../config/functions.php';
checkLogin();
checkRole(['hr']);

$currentUserId = (int)$_SESSION['user_id'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalEmployees  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employee' AND status='active'")->fetchColumn();
$totalAssets     = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$availableAssets = (int)$pdo->query("SELECT COUNT(*) FROM assets WHERE status='available'")->fetchColumn();
$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM asset_requests WHERE status='pending'")->fetchColumn();
$pendingService  = (int)$pdo->query("SELECT COUNT(*) FROM service_requests WHERE status='pending'")->fetchColumn();

// ── Recent Notifications ──────────────────────────────────────────────────────
$notifStmt = $pdo->prepare("SELECT * FROM notifications
                             WHERE user_id = :uid OR user_id IS NULL
                             ORDER BY created_at DESC LIMIT 8");
$notifStmt->execute([':uid' => $currentUserId]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
$unreadCount   = array_reduce($notifications, fn($c, $n) => $c + ($n['is_read'] ? 0 : 1), 0);

// ── Recent Employees (last 5 added) ──────────────────────────────────────────
$recentEmp = $pdo->query("SELECT id, first_name, last_name, department, email, status, created_at
                           FROM users WHERE role='employee' ORDER BY created_at DESC LIMIT 5")
                 ->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'HR Dashboard';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>HR Dashboard</h4>
        <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['first_name'] ?? ($_SESSION['name'] ?? 'HR User')) ?>!</small>
      </div>
      <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('l, d F Y') ?></span>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
              <i class="bi bi-people-fill fs-4 text-primary"></i>
            </div>
            <div>
              <div class="fs-3 fw-bold text-primary"><?= $totalEmployees ?></div>
              <div class="text-muted small">Total Employees</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <a href="<?= SITE_URL ?>/admin/employees/index.php" class="text-primary small text-decoration-none">
              View all <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-dark bg-opacity-10 p-3">
              <i class="bi bi-box-seam-fill fs-4 text-dark"></i>
            </div>
            <div>
              <div class="fs-3 fw-bold text-dark"><?= $totalAssets ?></div>
              <div class="text-muted small">Total Assets</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <a href="<?= SITE_URL ?>/admin/assets/index.php" class="text-dark small text-decoration-none">
              View all <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success bg-opacity-10 p-3">
              <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
            <div>
              <div class="fs-3 fw-bold text-success"><?= $availableAssets ?></div>
              <div class="text-muted small">Available Assets</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <a href="<?= SITE_URL ?>/admin/assets/index.php?status=available" class="text-success small text-decoration-none">
              View <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
              <i class="bi bi-clipboard-check-fill fs-4 text-warning"></i>
            </div>
            <div>
              <div class="fs-3 fw-bold text-warning"><?= $pendingRequests ?></div>
              <div class="text-muted small">Pending Asset Requests</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <a href="<?= SITE_URL ?>/admin/requests/index.php" class="text-warning small text-decoration-none">
              View <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-info bg-opacity-10 p-3">
              <i class="bi bi-tools fs-4 text-info"></i>
            </div>
            <div>
              <div class="fs-3 fw-bold text-info"><?= $pendingService ?></div>
              <div class="text-muted small">Pending Service Requests</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <a href="<?= SITE_URL ?>/admin/service/index.php" class="text-info small text-decoration-none">
              View <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">

      <!-- Recent Notifications -->
      <div class="col-lg-5">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
              <i class="bi bi-bell-fill me-1 text-warning"></i>Recent Notifications
              <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
              <?php endif; ?>
            </span>
            <a href="<?= SITE_URL ?>/admin/notifications/index.php" class="btn btn-outline-primary btn-sm">
              View All
            </a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
              <div class="text-center text-muted py-5">
                <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>No notifications.
              </div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($notifications as $notif): ?>
              <?php
                $typeColor = match($notif['type']) {
                  'asset_submitted' => 'primary',
                  'asset_request'   => 'warning',
                  'service_request' => 'info',
                  'consent_given'   => 'success',
                  default           => 'secondary',
                };
              ?>
              <li class="list-group-item list-group-item-action d-flex align-items-start gap-2 py-2
                         <?= $notif['is_read'] ? '' : 'list-group-item-warning' ?>">
                <span class="badge bg-<?= $typeColor ?> mt-1" style="min-width:10px;min-height:10px;padding:5px;border-radius:50%;">&nbsp;</span>
                <div class="flex-grow-1 small">
                  <?php if ($notif['link']): ?>
                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="text-decoration-none text-dark">
                      <?= htmlspecialchars($notif['message']) ?>
                    </a>
                  <?php else: ?>
                    <?= htmlspecialchars($notif['message']) ?>
                  <?php endif; ?>
                  <div class="text-muted" style="font-size:0.75rem;">
                    <?= date('d M Y, H:i', strtotime($notif['created_at'])) ?>
                  </div>
                </div>
                <?php if (!$notif['is_read']): ?>
                  <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">New</span>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Quick Links + Recent Employees -->
      <div class="col-lg-7">
        <!-- Quick Links -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>Quick Links
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-sm-4">
                <a href="<?= SITE_URL ?>/admin/employees/index.php"
                   class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3">
                  <i class="bi bi-people-fill fs-3 mb-1"></i>
                  <span class="small">Manage Employees</span>
                </a>
              </div>
              <div class="col-sm-4">
                <a href="<?= SITE_URL ?>/admin/assets/index.php"
                   class="btn btn-outline-dark w-100 d-flex flex-column align-items-center py-3">
                  <i class="bi bi-box-seam-fill fs-3 mb-1"></i>
                  <span class="small">Manage Assets</span>
                </a>
              </div>
              <div class="col-sm-4">
                <a href="<?= SITE_URL ?>/admin/reports/index.php"
                   class="btn btn-outline-success w-100 d-flex flex-column align-items-center py-3">
                  <i class="bi bi-bar-chart-fill fs-3 mb-1"></i>
                  <span class="small">View Reports</span>
                </a>
              </div>
              <div class="col-sm-4">
                <a href="<?= SITE_URL ?>/admin/requests/index.php"
                   class="btn btn-outline-warning w-100 d-flex flex-column align-items-center py-3">
                  <i class="bi bi-clipboard-check-fill fs-3 mb-1"></i>
                  <span class="small">Asset Requests</span>
                </a>
              </div>
              <div class="col-sm-4">
                <a href="<?= SITE_URL ?>/admin/service/index.php"
                   class="btn btn-outline-info w-100 d-flex flex-column align-items-center py-3">
                  <i class="bi bi-tools fs-3 mb-1"></i>
                  <span class="small">Service Requests</span>
                </a>
              </div>
              <div class="col-sm-4">
                <a href="<?= SITE_URL ?>/admin/notifications/index.php"
                   class="btn btn-outline-secondary w-100 d-flex flex-column align-items-center py-3">
                  <i class="bi bi-bell-fill fs-3 mb-1"></i>
                  <span class="small">Notifications</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Recently Added Employees -->
        <div class="card shadow-sm">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-person-plus-fill me-1 text-primary"></i>Recently Added Employees</span>
            <a href="<?= SITE_URL ?>/admin/employees/index.php" class="btn btn-outline-primary btn-sm">View All</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                  <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Added</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentEmp as $emp): ?>
                  <tr>
                    <td>
                      <a href="<?= SITE_URL ?>/admin/employees/view.php?id=<?= $emp['id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                      </a>
                    </td>
                    <td><?= htmlspecialchars($emp['department'] ?? '—') ?></td>
                    <td>
                      <span class="badge <?= $emp['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                        <?= ucfirst($emp['status']) ?>
                      </span>
                    </td>
                    <td class="text-muted small"><?= date('d M Y', strtotime($emp['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentEmp)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No employees found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
