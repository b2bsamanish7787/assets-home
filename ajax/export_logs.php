<?php
/**
 * File: ajax/export_logs.php
 * Description: CSV export of activity logs with optional filters (admin only).
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../config/functions.php';

checkLogin();
checkRole(['admin']);

// ── Collect and sanitize filter parameters ────────────────────────────────────
$dateFrom   = isset($_GET['date_from'])   ? sanitize($_GET['date_from'])   : '';
$dateTo     = isset($_GET['date_to'])     ? sanitize($_GET['date_to'])     : '';
$userId     = isset($_GET['user_id'])     ? (int)$_GET['user_id']          : 0;
$actionType = isset($_GET['action_type']) ? sanitize($_GET['action_type']) : '';

// ── Build query with conditional WHERE clauses ────────────────────────────────
$sql    = "
    SELECT al.id,
           CONCAT(u.first_name, ' ', u.last_name) AS user_name,
           al.action,
           al.description,
           al.ip_address,
           al.created_at
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE 1=1
";
$params = [];

if ($dateFrom !== '') {
    $sql .= " AND DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}
if ($userId > 0) {
    $sql .= " AND al.user_id = :user_id";
    $params[':user_id'] = $userId;
}
if ($actionType !== '') {
    $sql .= " AND al.action = :action_type";
    $params[':action_type'] = $actionType;
}

$sql .= " ORDER BY al.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ── Set CSV download headers ──────────────────────────────────────────────────
$filename = 'activity_logs_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ── Output CSV via fputcsv ────────────────────────────────────────────────────
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Column headers
fputcsv($output, ['ID', 'User Name', 'Action', 'Description', 'IP Address', 'Date Time']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['user_name'] ?? 'N/A',
        $row['action'],
        $row['description'],
        $row['ip_address'],
        $row['created_at'],
    ]);
}

fclose($output);
exit;
