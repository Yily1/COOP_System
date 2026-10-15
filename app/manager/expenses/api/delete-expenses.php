<?php
ob_start(); // keep stray PHP warnings from corrupting the JSON
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
if (!function_exists('logActivity') && file_exists(__DIR__ . '/../../../../includes/activity-logger.php')) {
    require_once __DIR__ . '/../../../../includes/activity-logger.php';
}

header('Content-Type: application/json');

function deRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
// Logging must never break the response
function deLog($pdo, $action, $status) {
    try {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'] ?? '', $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    deRespond(false, 'Only managers can delete expenses.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    deRespond(false, 'Invalid request.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    deRespond(false, 'Invalid expense.');
}

try {
    $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        deRespond(false, 'Expense not found.');
    }

    deLog('expense_deleted', 'success');
    deRespond(true, 'Expense deleted.');
} catch (PDOException $e) {
    error_log('delete-expense: ' . $e->getMessage());
    deLog('expense_deleted', 'failed');
    deRespond(false, 'Could not delete the expense. Please try again.');
}