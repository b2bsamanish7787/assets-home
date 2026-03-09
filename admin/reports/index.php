<?php
/**
 * File: admin/reports/index.php
 * Description: Reports page showing assigned assets, available inventory, and employee summary with filters and CSV export.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['admin', 'hr']);

// Collect filter params
$dateFrom   = isset($_GET['date_from'])  ? sanitize($_GET['date_from'])  : '';
$dateTo     = isset($_GET['date_to'])    ? sanitize($_GET['date_to'])    : '';
$department = isset($_GET['department']) ? sanitize($_GET['department']) : '';
$category   = isset($_GET['category'])   ? sanitize($_GET['category'])   : '';
$status     = isset($_GET['status'])     ? sanitize($_GET['status'])     : '';

// ── Fetch filter options ──────────────────────────────────────────────────────
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$categories  = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ── Report 1: Assets Assigned to Employees ────────────────────────────────────
$sql1  = "SELECT a.id, a.asset_name, c.name AS category_name, a.model_number, a.serial_number,
                 a.receive_date, a.purchased_by, a.status,
                 CONCAT(u.first_name,' ',u.last_name) AS employee_name,
                 u.department, u.employee_id AS emp_code
          FROM assets a
          LEFT JOIN categories c ON c.id = a.category_id
          LEFT JOIN users u ON u.id = a.assigned_to
          WHERE a.assigned_to IS NOT NULL";
$params1 = [];

if ($dateFrom) { $sql1 .= " AND a.receive_date >= :df1";  $params1[':df1'] = $dateFrom; }
if ($dateTo)   { $sql1 .= " AND a.receive_date <= :dt1";  $params1[':dt1'] = $dateTo;   }
if ($department){ $sql1 .= " AND u.department = :dep1";   $params1[':dep1'] = $department; }
if ($category)  { $sql1 .= " AND a.category_id = :cat1";  $params1[':cat1'] = $category; }
if ($status)    { $sql1 .= " AND a.status = :st1";        $params1[':st1'] = $status; }
$sql1 .= " ORDER BY a.asset_name";
$stmt1 = $pdo->prepare($sql1);
$stmt1->execute($params1);
$assignedAssets = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// ── Report 2: Available Assets Inventory ─────────────────────────────────────
$sql2  = "SELECT a.id, a.asset_name, c.name AS category_name, a.model_number, a.serial_number,
                 a.receive_date, a.purchased_by, a.status
          FROM assets a
          LEFT JOIN categories c ON c.id = a.category_id
          WHERE a.status = 'available'";
$params2 = [];
if ($dateFrom) { $sql2 .= " AND a.receive_date >= :df2"; $params2[':df2'] = $dateFrom; }
if ($dateTo)   { $sql2 .= " AND a.receive_date <= :dt2"; $params2[':dt2'] = $dateTo;   }
if ($category) { $sql2 .= " AND a.category_id = :cat2";  $params2[':cat2'] = $category; }
$sql2 .= " ORDER BY a.asset_name";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($params2);
$availableAssets = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// ── Report 3: Employee Summary by Department ──────────────────────────────────
$sql3 = "SELECT department,
                SUM(CASE WHEN status='active'   THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) AS inactive_count,
                COUNT(*) AS total_count
         FROM users
         WHERE role='employee'";
$params3 = [];
if ($department) { $sql3 .= " AND department = :dep3"; $params3[':dep3'] = $department; }
$sql3 .= " GROUP BY department ORDER BY department";
$stmt3 = $pdo->prepare($sql3);
$stmt3->execute($params3);
$empSummary = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// Build export query string
$exportParams = http_build_query([
    'type'       => 'assets',
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'department' => $department,
    'category'   => $category,
    'status'     => $status,
]);

$pageTitle = 'Reports';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Reports</h4>
      <a href="<?= SITE_URL ?>/ajax/export_report.php?<?= $exportParams ?>" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
      </a>
    </div>

    <?= getFlash() ?>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-light fw-semibold">
        <i class="bi bi-funnel me-1"></i>Filters
      </div>
      <div class="card-body">
        <form method="GET" action="">
          <div class="row g-3">
            <div class="col-md-2">
              <label class="form-label">Date From</label>
              <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Date To</label>
              <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Department</label>
              <select name="department" class="form-select form-select-sm">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Category</label>
              <select name="category" class="form-select form-select-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="assigned"  <?= $status === 'assigned'  ? 'selected' : '' ?>>Assigned</option>
                <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="in_service"<?= $status === 'in_service'? 'selected' : '' ?>>In Service</option>
                <option value="disposed"  <?= $status === 'disposed'  ? 'selected' : '' ?>>Disposed</option>
              </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-search me-1"></i>Apply
              </button>
              <a href="<?= SITE_URL ?>/admin/reports/index.php" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-x-circle me-1"></i>Reset
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-3" id="reportTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assignedPane"
                type="button" role="tab">
          <i class="bi bi-person-check me-1"></i>Assigned Assets
          <span class="badge bg-primary ms-1"><?= count($assignedAssets) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#availablePane"
                type="button" role="tab">
          <i class="bi bi-box-seam me-1"></i>Available Inventory
          <span class="badge bg-success ms-1"><?= count($availableAssets) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="emp-tab" data-bs-toggle="tab" data-bs-target="#empPane"
                type="button" role="tab">
          <i class="bi bi-people me-1"></i>Employee Summary
          <span class="badge bg-info ms-1"><?= count($empSummary) ?></span>
        </button>
      </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">

      <!-- Tab 1: Assigned Assets -->
      <div class="tab-pane fade show active" id="assignedPane" role="tabpanel">
        <div class="card shadow-sm">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-person-check me-1"></i>Assets Assigned to Employees</span>
            <a href="<?= SITE_URL ?>/ajax/export_report.php?<?= $exportParams ?>&tab=assigned" class="btn btn-outline-success btn-sm">
              <i class="bi bi-download me-1"></i>Export This Tab
            </a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0" id="tableAssigned">
                <thead class="table-dark">
                  <tr>
                    <th>#</th>
                    <th>Asset Name</th>
                    <th>Category</th>
                    <th>Model</th>
                    <th>Serial</th>
                    <th>Receive Date</th>
                    <th>Purchased By</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Emp Code</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($assignedAssets as $i => $row): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['asset_name']) ?></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['model_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['serial_number'] ?? '—') ?></td>
                    <td><?= $row['receive_date'] ? date('d M Y', strtotime($row['receive_date'])) : '—' ?></td>
                    <td><?= htmlspecialchars(ucfirst($row['purchased_by'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars($row['employee_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['department'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['emp_code'] ?? '—') ?></td>
                    <td>
                      <?php
                        $sc = match($row['status']) {
                          'assigned'   => 'bg-primary',
                          'available'  => 'bg-success',
                          'in_service' => 'bg-warning text-dark',
                          'disposed'   => 'bg-danger',
                          default      => 'bg-secondary',
                        };
                      ?>
                      <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_', ' ', $row['status'])) ?></span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Available Inventory -->
      <div class="tab-pane fade" id="availablePane" role="tabpanel">
        <div class="card shadow-sm">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-box-seam me-1"></i>Available Assets Inventory</span>
            <a href="<?= SITE_URL ?>/ajax/export_report.php?<?= $exportParams ?>&tab=available" class="btn btn-outline-success btn-sm">
              <i class="bi bi-download me-1"></i>Export This Tab
            </a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0" id="tableAvailable">
                <thead class="table-dark">
                  <tr>
                    <th>#</th>
                    <th>Asset Name</th>
                    <th>Category</th>
                    <th>Model</th>
                    <th>Serial</th>
                    <th>Receive Date</th>
                    <th>Purchased By</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($availableAssets as $i => $row): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['asset_name']) ?></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['model_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['serial_number'] ?? '—') ?></td>
                    <td><?= $row['receive_date'] ? date('d M Y', strtotime($row['receive_date'])) : '—' ?></td>
                    <td><?= htmlspecialchars(ucfirst($row['purchased_by'] ?? '—')) ?></td>
                    <td><span class="badge bg-success">Available</span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 3: Employee Summary -->
      <div class="tab-pane fade" id="empPane" role="tabpanel">
        <div class="card shadow-sm">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-people me-1"></i>Employee Summary by Department</span>
            <a href="<?= SITE_URL ?>/ajax/export_report.php?<?= $exportParams ?>&tab=employees" class="btn btn-outline-success btn-sm">
              <i class="bi bi-download me-1"></i>Export This Tab
            </a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0" id="tableEmp">
                <thead class="table-dark">
                  <tr>
                    <th>#</th>
                    <th>Department</th>
                    <th>Active Employees</th>
                    <th>Inactive Employees</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $totalActive = 0; $totalInactive = 0; $grandTotal = 0;
                    foreach ($empSummary as $i => $row):
                      $totalActive   += (int)$row['active_count'];
                      $totalInactive += (int)$row['inactive_count'];
                      $grandTotal    += (int)$row['total_count'];
                  ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['department'] ?? 'Unassigned') ?></td>
                    <td><span class="badge bg-success"><?= $row['active_count'] ?></span></td>
                    <td><span class="badge bg-danger"><?= $row['inactive_count'] ?></span></td>
                    <td><strong><?= $row['total_count'] ?></strong></td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                  <?php if (!empty($empSummary)): ?>
                  <tfoot>
                    <tr class="table-secondary fw-bold">
                      <td colspan="2" class="text-end">Grand Total</td>
                      <td><span class="badge bg-success"><?= $totalActive ?></span></td>
                      <td><span class="badge bg-danger"><?= $totalInactive ?></span></td>
                      <td><?= $grandTotal ?></td>
                    </tr>
                  </tfoot>
                  <?php endif; ?>
                </table>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /tab-content -->
  </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(function () {
  $('#tableAssigned').DataTable({
    pageLength: 25,
    order: [[1, 'asc']],
    responsive: true,
    language: { search: 'Search assigned assets:', emptyTable: 'No records found.' }
  });
  $('#tableAvailable').DataTable({
    pageLength: 25,
    order: [[1, 'asc']],
    responsive: true,
    language: { search: 'Search available assets:', emptyTable: 'No available assets found.' }
  });
  $('#tableEmp').DataTable({
    pageLength: 25,
    order: [[1, 'asc']],
    responsive: true,
    language: { search: 'Search departments:', emptyTable: 'No employee data found.' }
  });

  // Re-initialise DataTables when a tab is shown (fixes column width)
  $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
  });
});
</script>
