<?php
ob_start(); // buffer output so PHP warnings/notices can't corrupt the JSON
// API: delete a user account (JSON). Called from the Delete modal in user-management.php
//   POST ?user_id=ID
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';

// Logging must never break the response: if logActivity() fails (e.g. the
// action name isn't allowed by the log table), just record it in the PHP error log.
function apiSafeLog($pdo, $userId, $email, $action, $status) {
    try {
        logActivity($pdo, $userId, $email, $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

requireLogin();
header('Content-Type: application/json');

function udRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); } // drop any stray output before the JSON
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

$currentRole   = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? 0;

if (!in_array($currentRole, ['manager', 'admin'], true)) {
    http_response_code(403);
    udRespond(false, 'Access denied.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    udRespond(false, 'Invalid request method.');
}

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    udRespond(false, 'Invalid user.');
}

$stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    udRespond(false, 'User not found.');
}

// Same permission rule used to show the Delete button (blocks deleting yourself, etc.)
if (!canDeleteUser($currentRole, $currentUserId, $target['role'], $target['id'])) {
    http_response_code(403);
    udRespond(false, 'You are not allowed to delete this user.');
}

try {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

    apiSafeLog($pdo, $currentUserId, $_SESSION['email'], 'user_deleted', 'success');
    udRespond(true, 'User deleted successfully.');

} catch (PDOException $e) {
    error_log('user-delete: ' . $e->getMessage());
    apiSafeLog($pdo, $currentUserId, $_SESSION['email'], 'user_deleted', 'failed');

    // 23000 = foreign key constraint (user still referenced by other records)
    if ($e->getCode() === '23000') {
        udRespond(false, 'This user cannot be deleted because other records depend on it. Set the account to Inactive instead.');
    }
    udRespond(false, 'Could not delete the user. Please try again.');
}