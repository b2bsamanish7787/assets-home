<?php
/**
 * File: admin/logs/index.php
 * Description: Activity logs viewer with filters, DataTable, and CSV export for admins.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['admin']);

// Collect filter params
$dateFrom   = isset($_GET['date_from'])  ? sanitize($_GET['date_from'])  : '';
$dateTo     = isset($_GET['date_to'])    ? sanitize($_GET['date_to'])    : '';
$userId     = isset($_GET['user_id'])    ? (int)$_GET['user_id']         : 0;
$actionType = isset($_GET['action_type'])? sanitize($_GET['action_type']): '';

// Fetch users for dropdown
$users = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM users ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$sql = "SELECT al.id, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email,
               al.action, al.description, al.ip_address, al.created_at
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE 1=1";
$params = [];

if ($dateFrom) { $sql .= " AND DATE(al.created_at) >= :df"; $params[':df'] = $dateFrom; }
if ($dateTo)   { $sql .= " AND DATE(al.created_at) <= :dt"; $params[':dt'] = $dateTo; }
if ($userId)   { $sql .= " AND al.user_id = :uid";          $params[':uid'] = $userId; }
if ($actionType){ $sql .= " AND al.action LIKE :act";       $params[':act'] = '%' . $actionType . '%'; }

$sql .= " ORDER BY al.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build export query string
$exportParams = http_build_query([
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'user_id'     => $userId,
    'action_type' => $actionType,
]);

$pageTitle = 'Activity Logs';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Activity Logs</h4>
      <a href="<?= SITE_URL ?>/ajax/export_logs.php?<?= $exportParams ?>" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
      </a>
    </div>

    <?= getFlash() ?>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-light fw-semibold">
        <i class="bi bi-funnel me-1"></i>Filter Logs
      </div>
      <div class="card-body">
        <form method="GET" action="">
          <div class="row g-3">
            <div class="col-md-2">
              <label class="form-label">Date From</label>
              <input type="date" name="date_from" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Date To</label>
              <input type="date" name="date_to" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">User</label>
              <select name="user_id" class="form-select form-select-sm">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Action Type</label>
              <input type="text" name="action_type" class="form-control form-control-sm"
                     placeholder="e.g. login, asset_submitted..."
                     value="<?= htmlspecialchars($actionType) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-search me-1"></i>Filter
              </button>
              <a href="<?= SITE_URL ?>/admin/logs/index.php" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-x-circle me-1"></i>Reset
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="card shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i>Log Entries
          <span class="badge bg-secondary ms-1"><?= count($logs) ?></span>
        </span>
        <span class="text-muted small">Showing all filtered results</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0" id="logsTable">
            <thead class="table-dark">
              <tr>
                <th width="50">#</th>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP Address</th>
                <th>Date &amp; Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $i => $log): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td>
                  <span class="fw-medium"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                  <?php if ($log['email']): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-primary text-uppercase small">
                    <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($log['description'] ?? '—') ?></td>
                <td><code><?= htmlspecialchars($log['ip_address'] ?? '—') ?></code></td>
                <td><?= $log['created_at'] ? date('d M Y, H:i:s', strtotime($log['created_at'])) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($logs)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-3 d-block mb-2"></i>No log entries found.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  $('#logsTable').DataTable({
    pageLength: 50,
    order: [[5, 'desc']],
    responsive: true,
    columnDefs: [
      { orderable: false, targets: [] }
    ],
    language: {
      search: 'Search logs:',
      emptyTable: 'No log entries found.'
    }
  });
});
</script>
