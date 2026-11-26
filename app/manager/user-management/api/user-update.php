<?php
ob_start(); // buffer output so PHP warnings/notices can't corrupt the JSON
// API: update a user account (JSON). Called from the Edit modal in user-management.php
//   POST ?user_id=ID  with: username, email, password (optional), role, account_status
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';
require_once __DIR__ . '/user-validate.php';

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

function uuRespond($success, $message, $extra = []) {
    if (ob_get_length()) { ob_clean(); } // drop any stray output before the JSON
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

$currentRole   = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? 0;

if (!in_array($currentRole, ['manager', 'admin'], true)) {
    http_response_code(403);
    uuRespond(false, 'Access denied.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    uuRespond(false, 'Invalid request method.');
}

if (!um_valid_id($_GET['user_id'] ?? '')) {
    uuRespond(false, 'Invalid user.');
}
$userId = (int)$_GET['user_id'];

// Load the target user
$stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    uuRespond(false, 'User not found.');
}

if (!canEditUser($currentRole, $currentUserId, $target['role'], $target['id'])) {
    http_response_code(403);
    uuRespond(false, 'You are not allowed to edit this user.');
}

// Format checks (required, username, email, password, status, role)
$v = um_validate_account($_POST, true);
$errors = $v['errors'];
$d = $v['data'];

$username       = $d['username'];
$email          = $d['email'];
$password       = $d['password'];
$account_status = $d['account_status'];

// Role: the select is disabled (not submitted) when the role can't be changed
$newRole = $target['role'];
if ($d['role'] !== null && canChangeRole($currentRole, $currentUserId, $target['id'])) {
    $newRole = $d['role'];
}

// Database checks (kapag tama na ang format)
if (empty($errors)) {
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $chk->execute([$username, $userId]);
    if ($chk->fetch()) $errors[] = 'That username is already taken.';

    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $chk->execute([$email, $userId]);
    if ($chk->fetch()) $errors[] = 'That email is already in use.';
}

if (!empty($errors)) {
    uuRespond(false, implode(' ', $errors), ['errors' => $errors]);
}

try {
    $sql = 'UPDATE users SET username = :username, email = :email, role = :role, account_status = :status';
    $params = [
        ':username' => $username,
        ':email'    => $email,
        ':role'     => $newRole,
        ':status'   => $account_status,
        ':id'       => $userId,
    ];

    // Only change the password when a new one was typed
    if ($password !== '') {
        $sql .= ', password = :password';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = :id';
    $pdo->prepare($sql)->execute($params);

    apiSafeLog($pdo, $currentUserId, $_SESSION['email'], 'user_updated', 'success');
    uuRespond(true, 'User updated successfully.');

} catch (PDOException $e) {
    error_log('user-update: ' . $e->getMessage());
    apiSafeLog($pdo, $currentUserId, $_SESSION['email'], 'user_updated', 'failed');

    if ($e->getCode() === '23000') {
        uuRespond(false, 'That username or email is already in use.');
    }
    uuRespond(false, 'Could not update the user. Please try again.');
}