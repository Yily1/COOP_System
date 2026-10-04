<?php
ob_start(); // buffer output so PHP warnings/notices can't corrupt the JSON
// API: create a STAFF account (Manager or Admin) - admin only.
//   POST -> create the account (JSON)
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';

// activity logger (only loaded if it isn't already available from config.php)
if (!function_exists('logActivity') && file_exists(__DIR__ . '/../../../../includes/activity-logger.php')) {
    require_once __DIR__ . '/../../../../includes/activity-logger.php';
}

requireLogin();
header('Content-Type: application/json');

function acRespond($success, $message, $extra = []) {
    if (ob_get_length()) { ob_clean(); } // drop any stray output before the JSON
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Logging must never break the response
function acSafeLog($pdo, $userId, $email, $action, $status) {
    try {
        logActivity($pdo, $userId, $email, $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

// Only admins can create staff accounts
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    acRespond(false, 'Access denied. Only admins can create staff accounts.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    acRespond(false, 'Invalid request method.');
}

$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role             = $_POST['role'] ?? 'manager';
$account_status   = ($_POST['account_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
$send_email       = isset($_POST['send_verification_email']);

$errors = [];

if ($username === '') {
    $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore, or period only).';
} else {
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $chk->execute([$username]);
    if ($chk->fetch()) $errors[] = 'That username is already taken.';
}

if ($email === '') {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is not valid.';
} else {
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) $errors[] = 'That email is already in use.';
}

if ($password === '') $errors[] = 'Password is required.';
if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';

// Admin can only create staff accounts (Admin or Manager) - never User
if (!in_array($role, ['admin', 'manager'], true)) {
    $errors[] = 'Invalid role. Only Admin or Manager accounts can be created here.';
}

if (!empty($errors)) {
    acRespond(false, implode(' ', $errors));
}

try {
    $email_verified = 1;
    $verification_token = null;
    $verification_expires = null;

    if ($send_email) {
        $email_verified = 0;
        $verification_token = bin2hex(random_bytes(32));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    }

    // Staff accounts (Admin/Manager) are never linked to a member profile
    $stmt = $pdo->prepare("
        INSERT INTO users
            (member_id, username, email, password, role, account_status, verification_token, is_verified, email_verification_expires, created_at)
        VALUES
            (NULL, :username, :email, :password, :role, :account_status, :token, :verified, :expires, NOW())
    ");
    $stmt->execute([
        ':username'       => $username,
        ':email'          => $email,
        ':password'       => password_hash($password, PASSWORD_DEFAULT),
        ':role'           => $role,
        ':account_status' => $account_status,
        ':token'          => $verification_token,
        ':verified'       => $email_verified,
        ':expires'        => $verification_expires,
    ]);

    $new_user_id = $pdo->lastInsertId();

    acSafeLog($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_created', 'success');
    acSafeLog($pdo, $new_user_id, $email, 'account_created', 'success');

    $message = 'Staff account created successfully! (Email verification skipped)';

    if ($send_email) {
        $verificationLink = BASE_URL . '/app/auth/verify-email.php?token=' . $verification_token;

        $email_subject = 'Verify Your Email Address';
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1976d2; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .button { display: inline-block; padding: 12px 30px; background: #4caf50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'><h2>Email Verification</h2></div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>An account was created for this email. Please verify your email address.</p>
                    <p style='text-align: center;'>
                        <a href='{$verificationLink}' class='button'>Verify Email</a>
                    </p>
                    <p>If the button doesn't work, copy and paste this link:</p>
                    <p style='word-break: break-all; color: #1976d2;'>{$verificationLink}</p>
                    <p>This link expires in 24 hours.</p>
                </div>
                <div class='footer'><p>&copy; " . date('Y') . " SJFMC Cooperative System</p></div>
            </div>
        </body>
        </html>
        ";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: noreply@ics-dev.io\r\n";

        if (@mail($email, $email_subject, $email_body, $headers)) {
            $message = 'Staff account created successfully! Verification email sent.';
            acSafeLog($pdo, $new_user_id, $email, 'verification_email_sent', 'success');
        } else {
            $message = 'Staff account created successfully! Verification email failed to send.';
            acSafeLog($pdo, $new_user_id, $email, 'verification_email_sent', 'failed');
        }
    }

    acRespond(true, $message, [
        'user' => [
            'id'             => $new_user_id,
            'username'       => $username,
            'email'          => $email,
            'role'           => $role,
            'account_status' => $account_status,
            'is_verified'    => $email_verified,
            'created_at'     => date('Y-m-d H:i:s'),
        ],
    ]);

} catch (PDOException $e) {
    error_log('admin account-create: ' . $e->getMessage());
    acSafeLog($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_created', 'failed');

    // 23000 = duplicate key (race condition on username / email)
    if ($e->getCode() === '23000') {
        acRespond(false, 'That username or email is already in use.');
    }
    acRespond(false, 'Could not create the account. Please try again.');
}