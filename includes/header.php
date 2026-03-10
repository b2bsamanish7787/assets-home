<?php
/**
 * Buzznation Assets Management System
 * File: includes/header.php
 * Description: HTML head + top navbar
 * Author: Buzznation IT Team
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$currentUser = $_SESSION['name'] ?? 'User';
$currentRole = $_SESSION['role'] ?? '';

// Unread notification count
// Employees only see their own notifications; admin/hr also see broadcast (user_id IS NULL) ones.
$unreadCount = 0;
if (!empty($_SESSION['user_id'])) {
    global $pdo;
    try {
        if ($currentRole === 'employee') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
        }
        $stmt->execute([$_SESSION['user_id']]);
        $unreadCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) { /* silence */ }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= generateCSRF() ?>">
  <title><?= $pageTitle ?? SITE_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top top-navbar">
  <div class="container-fluid">
    <!-- Sidebar toggle (mobile) -->
    <button class="btn btn-outline-secondary btn-sm me-2 d-lg-none" id="sidebarToggle">
      <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>/<?= $currentRole ?>/dashboard.php">
      <i class="bi bi-boxes me-1"></i><?= SITE_NAME ?>
    </a>

    <div class="ms-auto d-flex align-items-center gap-3">
      <!-- Notification Bell -->
      <a href="<?= SITE_URL ?>/<?= $currentRole === 'employee' ? 'employee' : 'admin' ?>/notifications/index.php" class="text-white position-relative notification-bell">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($unreadCount > 0): ?>
          <span class="badge bg-danger notification-badge" id="notif-count"><?= $unreadCount ?></span>
        <?php else: ?>
          <span class="badge bg-danger notification-badge d-none" id="notif-count">0</span>
        <?php endif; ?>
      </a>

      <!-- User Dropdown -->
      <div class="dropdown">
        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i><?= sanitize($currentUser) ?>
          <span class="badge bg-primary ms-1 text-uppercase" style="font-size:.65em;"><?= sanitize($currentRole) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php if ($currentRole === 'employee'): ?>
          <li><a class="dropdown-item" href="<?= SITE_URL ?>/employee/change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
          <li><hr class="dropdown-divider"></li>
          <?php endif; ?>
          <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- Page wrapper starts here -->
<div class="wrapper d-flex">
