<?php
ob_start(); // buffer output so PHP warnings/notices can't corrupt the JSON
// API: update a user account (JSON). Called from the Edit modal in user-management.php
//   POST ?user_id=ID  with: username, email, password (optional), role, account_status
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

function uuRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); } // drop any stray output before the JSON
    echo json_encode(['success' => $success, 'message' => $message]);
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

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    uuRespond(false, 'Invalid user.');
}

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

$username       = trim($_POST['username'] ?? '');
$email          = trim($_POST['email'] ?? '');
$password       = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$account_status = ($_POST['account_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

// Role: the select is disabled (not submitted) when the role can't be changed
$newRole = $target['role'];
if (isset($_POST['role']) && canChangeRole($currentRole, $currentUserId, $target['id'])) {
    if (in_array($_POST['role'], ['user', 'manager', 'admin'], true)) {
        $newRole = $_POST['role'];
    }
}

$errors = [];

if ($username === '') {
    $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore, or period only).';
} else {
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $chk->execute([$username, $userId]);
    if ($chk->fetch()) $errors[] = 'That username is already taken.';
}

if ($email === '') {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is not valid.';
} else {
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $chk->execute([$email, $userId]);
    if ($chk->fetch()) $errors[] = 'That email is already in use.';
}

// A new password must be confirmed
if ($password !== '' && $password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    uuRespond(false, implode(' ', $errors));
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