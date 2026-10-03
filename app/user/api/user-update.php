<?php
ob_start(); // buffer output so PHP warnings/notices can't corrupt the JSON
// API: a regular user updates THEIR OWN account (JSON).
// Called from the Update Account modal in app/user/profile.php
//   POST with: username, email, password (optional), confirm_password, account_status
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireLogin();
header('Content-Type: application/json');

function upRespond($success, $message, $extra = []) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Logging must never break the response
function upSafeLog($pdo, $userId, $email, $action, $status) {
    try {
        logActivity($pdo, $userId, $email, $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

if (($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    upRespond(false, 'Access denied.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    upRespond(false, 'Invalid request method.');
}

// A user can only ever edit their OWN account: the id comes from the
// session, never from the request, so nobody can edit someone else.
$userId = (int)$_SESSION['user_id'];

$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$account_status   = ($_POST['account_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

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

if ($password !== '' && $password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    upRespond(false, implode(' ', $errors));
}

try {
    // NOTE: role is intentionally NOT updated here. Users can never change their own role.
    $sql = 'UPDATE users SET username = :username, email = :email, account_status = :status';
    $params = [
        ':username' => $username,
        ':email'    => $email,
        ':status'   => $account_status,
        ':id'       => $userId,
    ];

    if ($password !== '') {
        $sql .= ', password = :password';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = :id';
    $pdo->prepare($sql)->execute($params);

    // Keep the session in sync with the new email
    $_SESSION['email'] = $email;

    upSafeLog($pdo, $userId, $email, 'user_updated', 'success');

    upRespond(true, 'Account updated successfully.', [
        'user' => [
            'username'       => $username,
            'email'          => $email,
            'account_status' => $account_status,
        ],
    ]);

} catch (PDOException $e) {
    error_log('user-self-update: ' . $e->getMessage());
    upSafeLog($pdo, $userId, $_SESSION['email'] ?? '', 'user_updated', 'failed');

    if ($e->getCode() === '23000') {
        upRespond(false, 'That username or email is already in use.');
    }
    upRespond(false, 'Could not update your account. Please try again.');
}