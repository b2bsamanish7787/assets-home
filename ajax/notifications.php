<?php
/**
 * File: ajax/notifications.php
 * Description: AJAX endpoint for notification count, listing, and mark-read actions.
 * Author: Buzznation IT Team
 */

require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

checkLogin();

$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$userId = (int)$_SESSION['user_id'];

try {
    switch ($action) {

        // ── Count unread notifications ────────────────────────────────────────
        case 'count':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM notifications
                WHERE is_read = 0
                  AND (user_id = :uid OR user_id IS NULL)
            ");
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['count' => (int)$row['cnt']]);
            break;

        // ── List last 10 notifications ────────────────────────────────────────
        case 'list':
            $stmt = $pdo->prepare("
                SELECT id, type, message, link, is_read, created_at
                FROM notifications
                WHERE (user_id = :uid OR user_id IS NULL)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([':uid' => $userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Cast types for clean JSON output
            foreach ($notifications as &$n) {
                $n['id']      = (int)$n['id'];
                $n['is_read'] = (int)$n['is_read'];
            }
            unset($n);
            echo json_encode($notifications);
            break;

        // ── Mark a single notification as read ───────────────────────────────
        case 'mark_read':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid notification ID.']);
                break;
            }
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = :id
                  AND (user_id = :uid OR user_id IS NULL)
            ");
            $stmt->execute([':id' => $id, ':uid' => $userId]);
            echo json_encode(['success' => true]);
            break;

        // ── Mark all notifications as read ───────────────────────────────────
        case 'mark_all_read':
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE is_read = 0
                  AND (user_id = :uid OR user_id IS NULL)
            ");
            $stmt->execute([':uid' => $userId]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing action.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
