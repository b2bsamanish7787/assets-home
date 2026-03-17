<?php
/**
 * File: employee/dashboard.php
 * Description: Employee dashboard showing personal asset stats, pending consent alert, and recent activity.
 * Author: Buzznation IT Team
 */

require_once '../config/functions.php';
checkLogin();
checkRole(['employee']);

$currentUserId = (int)$_SESSION['user_id'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$stmtA = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE assigned_to = ?");
$stmtA->execute([$currentUserId]);
$myAssetsCount = (int)$stmtA->fetchColumn();

$stmtR = $pdo->prepare("SELECT COUNT(*) FROM asset_requests WHERE employee_id = ? AND status = 'pending'");
$stmtR->execute([$currentUserId]);
$pendingRequestsCount = (int)$stmtR->fetchColumn();

$stmtS = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE employee_id = ? AND status = 'pending'");
$stmtS->execute([$currentUserId]);
$pendingServiceCount = (int)$stmtS->fetchColumn();

// ── Pending Consent ───────────────────────────────────────────────────────────
$stmtC = $pdo->prepare("SELECT tc.*, a.asset_name, c.name AS category_name
                         FROM transfer_consent tc
                         JOIN assets a ON a.id = tc.asset_id
                         LEFT JOIN categories c ON c.id = a.category_id
                         WHERE tc.to_user = ? AND tc.status = 'pending'
                         LIMIT 1");
$stmtC->execute([$currentUserId]);
$pendingConsent = $stmtC->fetch(PDO::FETCH_ASSOC);

// ── Recent Activity Logs ──────────────────────────────────────────────────────
$stmtL = $pdo->prepare("SELECT action, description, ip_address, created_at
                         FROM activity_logs WHERE user_id = ?
                         ORDER BY created_at DESC LIMIT 5");
$stmtL->execute([$currentUserId]);
$recentActivity = $stmtL->fetchAll(PDO::FETCH_ASSOC);

// ── My Recent Assets ─────────────────────────────────────────────────────────
$stmtMA = $pdo->prepare("SELECT a.id, a.asset_name, c.name AS category_name, a.status, a.receive_date
                          FROM assets a
                          LEFT JOIN categories c ON c.id = a.category_id
                          WHERE a.assigned_to = ?
                          ORDER BY a.receive_date DESC LIMIT 5");
$stmtMA->execute([$currentUserId]);
$myRecentAssets = $stmtMA->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Dashboard';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>My Dashboard</h4>
        <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['first_name'] ?? ($_SESSION['name'] ?? 'Employee')) ?>!</small>
      </div>
      <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('l, d F Y') ?></span>
    </div>

    <?= renderFlash() ?>

    <!-- Pending Consent Alert -->
    <?php if ($pendingConsent): ?>
    <div class="alert alert-warning alert-dismissible border-warning shadow-sm d-flex align-items-center gap-3 mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0"></i>
      <div class="flex-grow-1">
        <strong>Action Required:</strong> You have a pending asset transfer consent for
        <strong><?= htmlspecialchars($pendingConsent['asset_name']) ?></strong>
        (<?= htmlspecialchars($pendingConsent['category_name'] ?? 'N/A') ?>).
        Please confirm receipt.
      </div>
      <a href="<?= SITE_URL ?>/employee/consent.php" class="btn btn-warning btn-sm ms-auto text-nowrap">
        <i class="bi bi-pen-fill me-1"></i>Give Consent
      </a>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
              <i class="bi bi-box-seam-fill fs-3 text-primary"></i>
            </div>
            <div>
              <div class="fs-2 fw-bold text-primary"><?= $myAssetsCount ?></div>
              <div class="text-muted">My Assets</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-2">
            <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="text-primary small text-decoration-none">
              View my assets <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
              <i class="bi bi-clipboard-check-fill fs-3 text-warning"></i>
            </div>
            <div>
              <div class="fs-2 fw-bold text-warning"><?= $pendingRequestsCount ?></div>
              <div class="text-muted">Pending Requests</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-2">
            <a href="<?= SITE_URL ?>/employee/requests/my_requests.php" class="text-warning small text-decoration-none">
              View my requests <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="rounded-circle bg-info bg-opacity-10 p-3">
              <i class="bi bi-tools fs-3 text-info"></i>
            </div>
            <div>
              <div class="fs-2 fw-bold text-info"><?= $pendingServiceCount ?></div>
              <div class="text-muted">Pending Service</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-2">
            <a href="<?= SITE_URL ?>/employee/service/my_service.php" class="text-info small text-decoration-none">
              View service requests <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">

      <!-- Quick Actions -->
      <div class="col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>Quick Actions
          </div>
          <div class="card-body d-flex flex-column gap-2">
            <a href="<?= SITE_URL ?>/employee/assets/submit.php" class="btn btn-primary d-flex align-items-center gap-2">
              <i class="bi bi-box-arrow-in-down fs-5"></i>
              <div class="text-start">
                <div class="fw-semibold">Submit Asset</div>
                <small class="opacity-75">Add an asset to your inventory</small>
              </div>
            </a>
            <a href="<?= SITE_URL ?>/employee/requests/new.php" class="btn btn-warning d-flex align-items-center gap-2">
              <i class="bi bi-clipboard-plus fs-5"></i>
              <div class="text-start">
                <div class="fw-semibold">Request Asset</div>
                <small class="opacity-75">Request a new asset from HR</small>
              </div>
            </a>
            <a href="<?= SITE_URL ?>/employee/service/new.php" class="btn btn-info d-flex align-items-center gap-2">
              <i class="bi bi-tools fs-5"></i>
              <div class="text-start">
                <div class="fw-semibold">Log Service Request</div>
                <small class="opacity-75">Report an asset issue</small>
              </div>
            </a>
            <?php if ($pendingConsent): ?>
            <a href="<?= SITE_URL ?>/employee/consent.php" class="btn btn-outline-warning d-flex align-items-center gap-2">
              <i class="bi bi-pen-fill fs-5"></i>
              <div class="text-start">
                <div class="fw-semibold">Give Transfer Consent</div>
                <small class="opacity-75">Confirm received asset</small>
              </div>
            </a>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/employee/change_password.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
              <i class="bi bi-key-fill fs-5"></i>
              <div class="text-start">
                <div class="fw-semibold">Change Password</div>
                <small class="opacity-75">Update your credentials</small>
              </div>
            </a>
          </div>
        </div>
      </div>

      <!-- My Recent Assets -->
      <div class="col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-box-seam me-1 text-primary"></i>My Recent Assets</span>
            <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="btn btn-outline-primary btn-sm">View All</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($myRecentAssets)): ?>
              <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>No assets assigned yet.
              </div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($myRecentAssets as $asset): ?>
              <?php
                $sc = match($asset['status']) {
                  'assigned'   => 'bg-primary',
                  'available'  => 'bg-success',
                  'in_service' => 'bg-warning text-dark',
                  'returned'   => 'bg-secondary',
                  default      => 'bg-secondary',
                };
              ?>
              <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                <div>
                  <div class="fw-medium small"><?= htmlspecialchars($asset['asset_name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($asset['category_name'] ?? 'N/A') ?>
                    <?= $asset['receive_date'] ? ' · ' . date('d M Y', strtotime($asset['receive_date'])) : '' ?>
                  </small>
                </div>
                <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_', ' ', $asset['status'])) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-clock-history me-1 text-secondary"></i>Recent Activity
          </div>
          <div class="card-body p-0">
            <?php if (empty($recentActivity)): ?>
              <div class="text-center text-muted py-5">
                <i class="bi bi-journal-x fs-3 d-block mb-2"></i>No recent activity.
              </div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($recentActivity as $log): ?>
              <li class="list-group-item py-2">
                <div class="d-flex justify-content-between align-items-start">
                  <span class="badge bg-primary text-uppercase small me-2">
                    <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                  </span>
                  <small class="text-muted text-nowrap"><?= date('d M, H:i', strtotime($log['created_at'])) ?></small>
                </div>
                <?php if ($log['description']): ?>
                  <div class="text-muted small mt-1"><?= htmlspecialchars($log['description']) ?></div>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
