<?php
/**
 * Buzznation Assets Management System
 * File: login.php
 * Description: Login page with CSRF protection, rate limiting, and role-based redirect
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

// Start session early
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in — redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'employee';
    header('Location: ' . SITE_URL . '/' . $role . '/dashboard.php');
    exit;
}

$error   = '';
$success = '';

// Handle timeout and forbidden messages
if (!empty($_GET['timeout'])) {
    $error = 'Your session has expired. Please log in again.';
}
if (!empty($_GET['forbidden'])) {
    $error = 'You do not have permission to access that page.';
}

// ------------------------------------------------------------------
// POST handler
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Rate limiting — max 5 failed attempts
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
        $_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

        if (time() < $_SESSION['login_locked_until']) {
            $remaining = ceil(($_SESSION['login_locked_until'] - time()) / 60);
            $error = "Too many failed attempts. Please wait {$remaining} minute(s).";
        } else {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Email and password are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Fetch user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $error = 'Your account is inactive. Contact the administrator.';
                    } else {
                        // Successful login
                        $_SESSION['login_attempts']    = 0;
                        $_SESSION['login_locked_until'] = 0;

                        session_regenerate_id(true);

                        $_SESSION['user_id']      = $user['id'];
                        $_SESSION['role']         = $user['role'];
                        $_SESSION['name']         = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['first_name']   = $user['first_name'];
                        $_SESSION['last_name']    = $user['last_name'];
                        $_SESSION['email']        = $user['email'];
                        $_SESSION['employee_id']  = $user['employee_id'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['csrf_token']   = bin2hex(random_bytes(32));

                        // Log activity
                        logActivity($user['id'], 'login', 'User logged in');

                        // First login → redirect to change password
                        if ($user['is_first_login']) {
                            header('Location: ' . SITE_URL . '/employee/change_password.php');
                            exit;
                        }

                        // Role-based redirect
                        $redirectMap = [
                            'admin'    => SITE_URL . '/admin/dashboard.php',
                            'hr'       => SITE_URL . '/hr/dashboard.php',
                            'employee' => SITE_URL . '/employee/dashboard.php',
                        ];
                        header('Location: ' . ($redirectMap[$user['role']] ?? SITE_URL . '/employee/dashboard.php'));
                        exit;
                    }
                } else {
                    $_SESSION['login_attempts']++;
                    if ($_SESSION['login_attempts'] >= 5) {
                        $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_TIME; // 30 min lock
                        $error = 'Too many failed attempts. Account locked for 30 minutes.';
                    } else {
                        $remaining = 5 - $_SESSION['login_attempts'];
                        $error = "Invalid email or password. {$remaining} attempt(s) remaining.";
                    }
                }
            }
        }
    }
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= SITE_NAME ?></title>
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    body { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .login-card { max-width: 420px; width: 100%; border: none; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    .login-header { background: #2c3e50; color: #fff; border-radius: 12px 12px 0 0; padding: 30px; text-align: center; }
    .login-header h4 { margin: 0; font-weight: 700; letter-spacing: .5px; }
    .login-body { padding: 30px; background: #fff; border-radius: 0 0 12px 12px; }
    .btn-login { background: #2c3e50; border: none; }
    .btn-login:hover { background: #34495e; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-header">
      <i class="bi bi-boxes fs-1 mb-2 d-block"></i>
      <h4><?= SITE_NAME ?></h4>
      <small class="opacity-75">Sign in to your account</small>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i><?= sanitize($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="mb-3">
          <label for="email" class="form-label fw-semibold">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= sanitize($_POST['email'] ?? '') ?>"
                   placeholder="you@example.com" required autofocus>
          </div>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label fw-semibold">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="••••••••" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePwd">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-login btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    // Toggle password visibility
    document.getElementById('togglePwd').addEventListener('click', function () {
      const pwd = document.getElementById('password');
      const icon = this.querySelector('i');
      if (pwd.type === 'password') { pwd.type = 'text'; icon.className = 'bi bi-eye-slash'; }
      else { pwd.type = 'password'; icon.className = 'bi bi-eye'; }
    });
  </script>
</body>
</html>
