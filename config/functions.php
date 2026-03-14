<?php
/**
 * Buzznation Assets Management System
 * File: config/functions.php
 * Description: Helper functions — session, CSRF, flash messages, notifications, email
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/db.php';

// ------------------------------------------------------------------
// Session & Auth Helpers
// ------------------------------------------------------------------

/**
 * Ensure the user is logged in; redirect to login if not.
 */
function checkLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    // Session timeout check
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Ensure the current user has one of the allowed roles.
 *
 * @param array $roles Allowed role strings, e.g. ['admin', 'hr']
 */
function checkRole(array $roles): void
{
    checkLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: ' . SITE_URL . '/login.php?forbidden=1');
        exit;
    }
}

// ------------------------------------------------------------------
// CSRF Helpers
// ------------------------------------------------------------------

/**
 * Generate (or reuse) a CSRF token stored in the session.
 *
 * @return string The CSRF token
 */
function generateCSRF(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 *
 * @param string $token The submitted token
 * @return bool
 */
function validateCSRF(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return hash_equals($sessionToken, $token);
}

// ------------------------------------------------------------------
// Input Sanitization
// ------------------------------------------------------------------

/**
 * Trim and escape a string for safe HTML output.
 *
 * @param string $input
 * @return string
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------------
// Flash Messages
// ------------------------------------------------------------------

/**
 * Set a flash message in the session.
 *
 * @param string $type    Bootstrap alert type: success|danger|warning|info
 * @param string $message Human-readable message
 */
function flashMessage(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message from the session.
 *
 * @return array|null ['type' => ..., 'message' => ...] or null
 */
function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Retrieve the flash message and return it as a rendered Bootstrap alert HTML string.
 * Returns an empty string when there is no flash message.
 * The message is HTML-escaped before output — callers should pass plain text.
 * Any user-supplied data embedded in the message must be unescaped (raw) when
 * passed to flashMessage(); this function handles the escaping.
 *
 * @return string HTML alert markup or ''
 */
function renderFlash(): string
{
    $flash = getFlash();
    if (!$flash) {
        return '';
    }
    $allowedTypes = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'];
    $type    = in_array($flash['type'], $allowedTypes, true) ? $flash['type'] : 'info';
    $message = htmlspecialchars(trim($flash['message']), ENT_QUOTES, 'UTF-8');
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show auto-dismiss" role="alert">'
        . $message
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
        . '</div>';
}

// ------------------------------------------------------------------
// Activity Logging
// ------------------------------------------------------------------

/**
 * Insert a record into activity_logs.
 *
 * @param int    $userId
 * @param string $action
 * @param string $description
 */
function logActivity(int $userId, string $action, string $description): void
{
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $description, $ip]);
    } catch (PDOException $e) {
        error_log('logActivity Error: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------------
// Notifications
// ------------------------------------------------------------------

/**
 * Insert a portal notification.
 *
 * @param int|null $userId NULL means all admins
 * @param string   $type
 * @param string   $message
 * @param string   $link
 */
function sendNotification(?int $userId, string $type, string $message, string $link): void
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, link)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $type, $message, $link]);
    } catch (PDOException $e) {
        error_log('sendNotification Error: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------------
// Employee ID Generation
// ------------------------------------------------------------------

/**
 * Generate the next employee ID in the format EMP001, EMP002, …
 *
 * @return string
 */
function generateEmployeeId(): string
{
    global $pdo;
    $stmt = $pdo->query("SELECT employee_id FROM users ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last && preg_match('/EMP(\d+)/', $last, $m)) {
        $next = intval($m[1]) + 1;
    } else {
        $next = 1;
    }
    return 'EMP' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

// ------------------------------------------------------------------
// Temporary Password
// ------------------------------------------------------------------

/**
 * Generate a random 8-character alphanumeric password.
 *
 * @return string
 */
function generateTempPassword(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < 8; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// ------------------------------------------------------------------
// Email (PHPMailer wrapper)
// ------------------------------------------------------------------

/**
 * Send an HTML email using PHPMailer.
 *
 * @param string $to      Recipient email address
 * @param string $subject Email subject
 * @param string $body    HTML email body
 * @return bool
 */
function sendEmail(string $to, string $subject, string $body): bool
{
    // PHPMailer is loaded via Composer autoloader or manual include.
    // If not available, log and return false gracefully.
    $autoloader = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloader)) {
        error_log("PHPMailer not installed. Email not sent to {$to}.");
        return false;
    }
    require_once $autoloader;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error ({$to}): " . $e->getMessage());
        return false;
    }
}

// ------------------------------------------------------------------
// Email Templates
// ------------------------------------------------------------------

/**
 * Build a Welcome Email HTML body.
 */
function emailWelcome(string $name, string $email, string $tempPassword): string
{
    $siteUrl  = SITE_URL;
    $siteName = SITE_NAME;
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">Welcome, {$name}!</h2>
    <p>Your account has been created. Use the credentials below to log in:</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0;">
      <tr><td style="padding:8px;background:#f4f6fb;font-weight:bold;">Email</td><td style="padding:8px;">{$email}</td></tr>
      <tr><td style="padding:8px;background:#f4f6fb;font-weight:bold;">Temporary Password</td><td style="padding:8px;">{$tempPassword}</td></tr>
    </table>
    <p>Please log in and change your password immediately.</p>
    <a href="{$siteUrl}/login.php" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">Login Now</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build an Asset Submission notification email.
 */
function emailAssetSubmission(string $employeeName, string $assetName): string
{
    $siteName = SITE_NAME;
    $link     = SITE_URL . '/admin/assets/index.php';
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">New Asset Submitted</h2>
    <p><strong>{$employeeName}</strong> has submitted a new asset: <strong>{$assetName}</strong>.</p>
    <a href="{$link}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">View Assets</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build an Asset Request notification email.
 */
function emailAssetRequest(string $employeeName, string $requirement): string
{
    $siteName = SITE_NAME;
    $link     = SITE_URL . '/admin/requests/index.php';
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">New Asset Request</h2>
    <p><strong>{$employeeName}</strong> has submitted an asset request: <strong>{$requirement}</strong>.</p>
    <a href="{$link}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">View Requests</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build a Request Action (approved/rejected) email.
 */
function emailRequestAction(string $name, string $status, string $remarks): string
{
    $siteName = SITE_NAME;
    $color    = $status === 'approved' ? '#27ae60' : '#e74c3c';
    $link     = SITE_URL . '/employee/requests/my_requests.php';
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:{$color};">Your Request Has Been {$status}</h2>
    <p>Hi {$name}, your asset request has been <strong style="color:{$color};">{$status}</strong>.</p>
    <p><strong>Admin Remarks:</strong> {$remarks}</p>
    <a href="{$link}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">View My Requests</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build an Asset Transfer notification email.
 */
function emailAssetTransfer(string $name, string $assetName): string
{
    $siteName = SITE_NAME;
    $link     = SITE_URL . '/employee/consent.php';
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">Asset Transfer Notification</h2>
    <p>Hi {$name}, the asset <strong>{$assetName}</strong> has been transferred to you. Please complete the consent form.</p>
    <a href="{$link}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">Complete Consent Form</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build a Service Request notification email.
 */
function emailServiceRequest(string $employeeName, string $assetName): string
{
    $siteName = SITE_NAME;
    $link     = SITE_URL . '/admin/service/index.php';
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">New Service Request</h2>
    <p><strong>{$employeeName}</strong> has submitted a service request for asset: <strong>{$assetName}</strong>.</p>
    <a href="{$link}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">View Service Requests</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build a Service Request notification email addressed to the employee's manager.
 */
function emailManagerServiceRequest(string $managerName, string $employeeName, string $assetName, string $requestLink): string
{
    $siteName = SITE_NAME;
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">Service Request — Your Direct Report</h2>
    <p>Hi {$managerName},</p>
    <p>Your direct report <strong>{$employeeName}</strong> has submitted a service request for asset: <strong>{$assetName}</strong>.</p>
    <p>This is an informational copy to keep you in the loop. The request is being handled by the admin/HR team.</p>
    <a href="{$requestLink}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">View Service Request</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}

/**
 * Build an Asset Request notification email addressed to the employee's manager.
 */
function emailManagerAssetRequest(string $managerName, string $employeeName, string $requirement, string $requestLink): string
{
    $siteName = SITE_NAME;
    return <<<HTML
<html><body style="font-family:Arial,sans-serif;background:#f4f6fb;padding:30px;">
<div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <div style="background:#2c3e50;padding:20px 30px;"><h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1></div>
  <div style="padding:30px;">
    <h2 style="color:#2c3e50;">Asset Request — Your Direct Report</h2>
    <p>Hi {$managerName},</p>
    <p>Your direct report <strong>{$employeeName}</strong> has submitted a new asset request: <strong>{$requirement}</strong>.</p>
    <p>This is an informational copy to keep you in the loop. The request is being handled by the admin/HR team.</p>
    <a href="{$requestLink}" style="display:inline-block;padding:12px 24px;background:#2c3e50;color:#fff;border-radius:5px;text-decoration:none;">View Asset Request</a>
  </div>
  <div style="padding:15px 30px;background:#f4f6fb;font-size:12px;color:#999;">© {$siteName}</div>
</div>
</body></html>
HTML;
}
