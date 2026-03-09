<?php
/**
 * File: admin/notifications/index.php
 * Description: Notifications centre for admin, HR, and employee roles with mark-all-read via AJAX.
 * Author: Buzznation IT Team
 */

require_once '../../config/functions.php';
checkLogin();
checkRole(['admin', 'hr', 'employee']);

$currentUserId = (int)$_SESSION['user_id'];

// Handle mark-all-read via POST (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: ' . SITE_URL . '/admin/notifications/index.php');
        exit;
    }
    $pdo->prepare("UPDATE notifications SET is_read = 1
                   WHERE (user_id = :uid OR user_id IS NULL) AND is_read = 0")
        ->execute([':uid' => $currentUserId]);
    flashMessage('success', 'All notifications marked as read.');
    header('Location: ' . SITE_URL . '/admin/notifications/index.php');
    exit;
}

// Fetch notifications for current user (personal + broadcast)
$stmt = $pdo->prepare("SELECT * FROM notifications
                        WHERE user_id = :uid OR user_id IS NULL
                        ORDER BY created_at DESC");
$stmt->execute([':uid' => $currentUserId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = array_reduce($notifications, fn($c, $n) => $c + ($n['is_read'] ? 0 : 1), 0);

$pageTitle = 'Notifications';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">
        <i class="bi bi-bell-fill me-2 text-primary"></i>Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="badge bg-danger ms-1"><?= $unreadCount ?> unread</span>
        <?php endif; ?>
      </h4>

      <?php if ($unreadCount > 0): ?>
      <div class="d-flex gap-2">
        <!-- AJAX Mark All Read -->
        <button id="btnMarkAllRead" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-check-all me-1"></i>Mark All as Read
        </button>

        <!-- Fallback POST form -->
        <form method="POST" action="" class="d-none" id="formMarkAllRead">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <input type="hidden" name="mark_all_read" value="1">
        </form>
      </div>
      <?php endif; ?>
    </div>

    <?= getFlash() ?>

    <!-- Notifications Table -->
    <div class="card shadow-sm">
      <div class="card-header bg-light fw-semibold">
        <i class="bi bi-list-ul me-1"></i>All Notifications
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle" id="notifTable">
            <thead class="table-dark">
              <tr>
                <th width="50">#</th>
                <th width="140">Type</th>
                <th>Message</th>
                <th width="120">Date &amp; Time</th>
                <th width="80" class="text-center">Status</th>
                <th width="80" class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($notifications as $i => $notif): ?>
              <?php
                $typeMap = [
                  'asset_submitted'  => ['bg-primary',  'bi-box-arrow-in-down'],
                  'asset_request'    => ['bg-warning text-dark', 'bi-clipboard-plus'],
                  'service_request'  => ['bg-info text-dark',    'bi-tools'],
                  'consent_given'    => ['bg-success',  'bi-check-circle'],
                  'transfer'         => ['bg-secondary','bi-arrow-left-right'],
                  'general'          => ['bg-dark',     'bi-bell'],
                ];
                $tc = $typeMap[$notif['type']] ?? $typeMap['general'];
                $rowClass = $notif['is_read'] ? '' : 'table-warning fw-semibold';
              ?>
              <tr class="<?= $rowClass ?>" data-notif-id="<?= $notif['id'] ?>">
                <td><?= $i + 1 ?></td>
                <td>
                  <span class="badge <?= $tc[0] ?> d-inline-flex align-items-center gap-1">
                    <i class="bi <?= $tc[1] ?>"></i>
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $notif['type']))) ?>
                  </span>
                </td>
                <td>
                  <?php if ($notif['link']): ?>
                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="text-decoration-none text-dark notif-link" data-id="<?= $notif['id'] ?>">
                      <?= htmlspecialchars($notif['message']) ?>
                    </a>
                  <?php else: ?>
                    <?= htmlspecialchars($notif['message']) ?>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap text-muted small">
                  <?= date('d M Y', strtotime($notif['created_at'])) ?>
                  <br><?= date('H:i', strtotime($notif['created_at'])) ?>
                </td>
                <td class="text-center">
                  <?php if ($notif['is_read']): ?>
                    <span class="badge bg-light text-secondary border">Read</span>
                  <?php else: ?>
                    <span class="badge bg-danger">New</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if (!$notif['is_read']): ?>
                  <button class="btn btn-outline-success btn-xs btn-mark-read px-1 py-0"
                          data-id="<?= $notif['id'] ?>" title="Mark as read">
                    <i class="bi bi-check2"></i>
                  </button>
                  <?php else: ?>
                  <span class="text-muted"><i class="bi bi-check-all"></i></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($notifications)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-5">
                  <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>No notifications yet.
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
  $('#notifTable').DataTable({
    pageLength: 25,
    order: [[3, 'desc']],
    responsive: true,
    language: { search: 'Search notifications:' }
  });

  // Mark single notification as read
  $(document).on('click', '.btn-mark-read', function () {
    const id  = $(this).data('id');
    const row = $(this).closest('tr');
    $.post('<?= SITE_URL ?>/ajax/mark_notification_read.php', { id: id, csrf: '<?= generateCSRF() ?>' })
      .done(function (res) {
        const data = typeof res === 'string' ? JSON.parse(res) : res;
        if (data.success) {
          row.removeClass('table-warning fw-semibold');
          row.find('.badge.bg-danger').replaceWith('<span class="badge bg-light text-secondary border">Read</span>');
          row.find('.btn-mark-read').replaceWith('<span class="text-muted"><i class="bi bi-check-all"></i></span>');
        }
      });
  });

  // Mark all as read via AJAX
  $('#btnMarkAllRead').on('click', function () {
    $.post('<?= SITE_URL ?>/ajax/mark_notification_read.php', { all: 1, csrf: '<?= generateCSRF() ?>' })
      .done(function (res) {
        const data = typeof res === 'string' ? JSON.parse(res) : res;
        if (data.success) {
          Swal.fire({
            icon: 'success', title: 'Done!', text: 'All notifications marked as read.',
            timer: 1500, showConfirmButton: false
          }).then(() => location.reload());
        }
      });
  });

  // Clicking a notification link marks it read automatically
  $(document).on('click', '.notif-link', function () {
    const id = $(this).data('id');
    if (id) {
      $.post('<?= SITE_URL ?>/ajax/mark_notification_read.php', { id: id, csrf: '<?= generateCSRF() ?>' });
    }
  });
});
</script>
