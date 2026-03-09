<?php
/**
 * Buzznation Assets Management System
 * File: includes/sidebar.php
 * Description: Role-based navigation sidebar
 * Author: Buzznation IT Team
 */
$currentRole = $_SESSION['role'] ?? '';
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="bi bi-boxes me-2"></i>BNation Assets
  </div>

  <ul class="sidebar-nav">

    <?php if ($currentRole === 'admin'): ?>

      <!-- ADMIN NAV -->
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-link <?= $currentFile === 'dashboard.php' && $currentDir === 'admin' ? 'active' : '' ?>">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/employees/index.php" class="nav-link <?= $currentDir === 'employees' ? 'active' : '' ?>">
          <i class="bi bi-people"></i> Employees
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/categories/index.php" class="nav-link <?= $currentDir === 'categories' ? 'active' : '' ?>">
          <i class="bi bi-tags"></i> Categories
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/assets/index.php" class="nav-link <?= $currentDir === 'assets' && $currentRole === 'admin' ? 'active' : '' ?>">
          <i class="bi bi-box-seam"></i> Assets
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/requests/index.php" class="nav-link <?= $currentDir === 'requests' ? 'active' : '' ?>">
          <i class="bi bi-clipboard-check"></i> Requests
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/service/index.php" class="nav-link <?= $currentDir === 'service' ? 'active' : '' ?>">
          <i class="bi bi-tools"></i> Service
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/reports/index.php" class="nav-link <?= $currentDir === 'reports' ? 'active' : '' ?>">
          <i class="bi bi-bar-chart"></i> Reports
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/logs/index.php" class="nav-link <?= $currentDir === 'logs' ? 'active' : '' ?>">
          <i class="bi bi-journal-text"></i> Activity Logs
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/notifications/index.php" class="nav-link <?= $currentDir === 'notifications' ? 'active' : '' ?>">
          <i class="bi bi-bell"></i> Notifications
        </a>
      </li>

    <?php elseif ($currentRole === 'hr'): ?>

      <!-- HR NAV -->
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/hr/dashboard.php" class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/employees/index.php" class="nav-link <?= $currentDir === 'employees' ? 'active' : '' ?>">
          <i class="bi bi-people"></i> Employees
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/assets/index.php" class="nav-link <?= $currentDir === 'assets' ? 'active' : '' ?>">
          <i class="bi bi-box-seam"></i> Assets
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/reports/index.php" class="nav-link <?= $currentDir === 'reports' ? 'active' : '' ?>">
          <i class="bi bi-bar-chart"></i> Reports
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/admin/notifications/index.php" class="nav-link <?= $currentDir === 'notifications' ? 'active' : '' ?>">
          <i class="bi bi-bell"></i> Notifications
        </a>
      </li>

    <?php else: ?>

      <!-- EMPLOYEE NAV -->
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/dashboard.php" class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/assets/my_assets.php" class="nav-link <?= $currentFile === 'my_assets.php' ? 'active' : '' ?>">
          <i class="bi bi-box-seam"></i> My Assets
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/assets/submit.php" class="nav-link <?= $currentFile === 'submit.php' ? 'active' : '' ?>">
          <i class="bi bi-plus-circle"></i> Submit Asset
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/requests/new.php" class="nav-link <?= $currentFile === 'new.php' && $currentDir === 'requests' ? 'active' : '' ?>">
          <i class="bi bi-clipboard-plus"></i> Asset Request
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/requests/my_requests.php" class="nav-link <?= $currentFile === 'my_requests.php' ? 'active' : '' ?>">
          <i class="bi bi-clipboard-check"></i> My Requests
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/service/new.php" class="nav-link <?= $currentFile === 'new.php' && $currentDir === 'service' ? 'active' : '' ?>">
          <i class="bi bi-tools"></i> Service Request
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= SITE_URL ?>/employee/service/my_service.php" class="nav-link <?= $currentFile === 'my_service.php' ? 'active' : '' ?>">
          <i class="bi bi-wrench-adjustable"></i> My Services
        </a>
      </li>

    <?php endif; ?>

    <!-- Logout -->
    <li class="nav-item mt-auto">
      <a href="<?= SITE_URL ?>/logout.php" class="nav-link text-danger">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </li>

  </ul>
</nav>
<!-- End Sidebar -->
