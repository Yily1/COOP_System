<?php
ob_start(); 


require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireLogin();
header('Content-Type: application/json');

function udRespond($success, $message, $extra = []) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Logging must never break the response
function udSafeLog($pdo, $userId, $email, $action, $status) {
    try {
        logActivity($pdo, $userId, $email, $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

if (($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    udRespond(false, 'Access denied.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    udRespond(false, 'Invalid request method.');
}
if (($_POST['confirm'] ?? '') !== '1') {
    udRespond(false, 'Please confirm that you want to deactivate your account.');
}

// The id comes from the session, never from the request.
$userId = (int)$_SESSION['user_id'];
$email  = $_SESSION['email'] ?? '';

try {
    $stmt = $pdo->prepare("UPDATE users SET account_status = 'Inactive' WHERE id = :id AND role = 'user'");
    $stmt->execute([':id' => $userId]);

    udSafeLog($pdo, $userId, $email, 'account_deactivated', 'success');

    // Log out agad (buburahin ang session)
    forceLogoutInactive();

    udRespond(true, 'Your account has been deactivated. Please contact the manager to reactivate it.', [
        'redirect' => BASE_URL . '/index.php?inactive=1',
    ]);

} catch (PDOException $e) {
    error_log('user-deactivate: ' . $e->getMessage());
    udSafeLog($pdo, $userId, $email, 'account_deactivated', 'failed');
    udRespond(false, 'Could not deactivate your account. Please try again.');
}