<?php
/**
 * File: ajax/export_report.php
 * Description: CSV export of asset reports (assigned, available, employees) for admin and HR.
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../config/functions.php';

checkLogin();
checkRole(['admin', 'hr']);

// ── Collect and sanitize parameters ──────────────────────────────────────────
$type       = isset($_GET['type'])        ? sanitize($_GET['type'])        : '';
$dateFrom   = isset($_GET['date_from'])   ? sanitize($_GET['date_from'])   : '';
$dateTo     = isset($_GET['date_to'])     ? sanitize($_GET['date_to'])     : '';
$department = isset($_GET['department'])  ? sanitize($_GET['department'])  : '';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id']     : 0;
$status     = isset($_GET['status'])      ? sanitize($_GET['status'])      : '';

$rows    = [];
$columns = [];
$sql     = '';
$params  = [];

// ── Build query based on report type ─────────────────────────────────────────
switch ($type) {

    // ── Assets assigned to employees ─────────────────────────────────────────
    case 'assets_assigned':
        $columns = [
            'Asset ID', 'Asset Name', 'Model Number', 'Serial Number',
            'Category', 'Status', 'Assigned To', 'Department',
            'Designation', 'Assigned Date',
        ];
        $sql = "
            SELECT a.id,
                   a.asset_name,
                   a.model_number,
                   a.serial_number,
                   c.name          AS category_name,
                   a.status,
                   CONCAT(u.first_name, ' ', u.last_name) AS assigned_to,
                   u.department,
                   u.designation,
                   a.created_at    AS assigned_date
            FROM assets a
            LEFT JOIN categories c ON c.id = a.category_id
            INNER JOIN users u ON u.id = a.assigned_to
            WHERE a.assigned_to IS NOT NULL
        ";
        if ($categoryId > 0) {
            $sql .= " AND a.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        if ($department !== '') {
            $sql .= " AND u.department = :department";
            $params[':department'] = $department;
        }
        if ($dateFrom !== '') {
            $sql .= " AND DATE(a.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND DATE(a.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        $sql .= " ORDER BY a.id DESC";
        break;

    // ── Available (unassigned) assets ─────────────────────────────────────────
    case 'assets_available':
        $columns = [
            'Asset ID', 'Asset Name', 'Model Number', 'Serial Number',
            'Category', 'Status', 'Purchased By', 'Receive Date', 'Added On',
        ];
        $sql = "
            SELECT a.id,
                   a.asset_name,
                   a.model_number,
                   a.serial_number,
                   c.name          AS category_name,
                   a.status,
                   a.purchased_by,
                   a.receive_date,
                   a.created_at
            FROM assets a
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'available'
        ";
        if ($categoryId > 0) {
            $sql .= " AND a.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        if ($dateFrom !== '') {
            $sql .= " AND DATE(a.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND DATE(a.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        $sql .= " ORDER BY a.id DESC";
        break;

    // ── Employee list ─────────────────────────────────────────────────────────
    case 'employees':
        $columns = [
            'Employee ID', 'First Name', 'Last Name', 'Email',
            'Designation', 'Department', 'Manager', 'Status', 'Joined On',
        ];
        $sql = "
            SELECT employee_id,
                   first_name,
                   last_name,
                   email,
                   designation,
                   department,
                   manager_name,
                   status,
                   created_at
            FROM users
            WHERE role = 'employee'
        ";
        if ($department !== '') {
            $sql .= " AND department = :department";
            $params[':department'] = $department;
        }
        if ($status !== '') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        if ($dateFrom !== '') {
            $sql .= " AND DATE(created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND DATE(created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        $sql .= " ORDER BY id DESC";
        break;

    default:
        http_response_code(400);
        exit('Invalid report type specified.');
}

// ── Execute query ─────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ── Set CSV download headers ──────────────────────────────────────────────────
$filename = $type . '_report_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ── Output CSV via fputcsv ────────────────────────────────────────────────────
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, $columns);

foreach ($rows as $row) {
    fputcsv($output, array_values($row));
}

fclose($output);
exit;
