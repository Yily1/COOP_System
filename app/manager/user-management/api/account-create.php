<?php
ob_start(); // buffer output so PHP warnings/notices can't corrupt the JSON
// API: create a login account for a cooperative member (manager only).
//   GET  -> list of members without an account (JSON)
//   POST -> create the account (JSON)
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

function acRespond($success, $message, $extra = []) {
    if (ob_get_length()) { ob_clean(); } // drop any stray output before the JSON
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Only managers can create User accounts for cooperative members
if (($_SESSION['role'] ?? '') !== 'manager') {
    http_response_code(403);
    acRespond(false, 'Access denied. Only managers can create member accounts.');
}

// GET: members that still have no account
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    acRespond(true, '', ['members' => getAvailableMembers($pdo)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    acRespond(false, 'Invalid request method.');
}

$member_id        = $_POST['member_id'] ?? '';
$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$account_status   = ($_POST['account_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
$send_email       = isset($_POST['send_verification_email']);

$errors = [];

if ($username === '') {
    $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore, or period only).';
}

if ($email === '') {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is not valid.';
}

if ($password === '') $errors[] = 'Password is required.';
if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';

// Username must be unique
if ($username !== '') {
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $chk->execute([$username]);
    if ($chk->fetch()) $errors[] = 'That username is already taken.';
}

// Email must be unique
if ($email !== '') {
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) $errors[] = 'That email is already in use.';
}

// Member must exist and still be unlinked (re-checked server-side)
$selectedMember = null;
if ($member_id === '') {
    $errors[] = 'Please select a member for this account.';
} else {
    $chk = $pdo->prepare('SELECT m.* FROM members m LEFT JOIN users u ON u.member_id = m.id WHERE m.id = ? AND u.id IS NULL');
    $chk->execute([$member_id]);
    $selectedMember = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$selectedMember) {
        $errors[] = 'That member is no longer available (may already have an account).';
    }
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

    $stmt = $pdo->prepare("
        INSERT INTO users
            (member_id, username, email, password, role, account_status, verification_token, is_verified, email_verification_expires, created_at)
        VALUES
            (:member_id, :username, :email, :password, 'user', :account_status, :token, :verified, :expires, NOW())
    ");
    $stmt->execute([
        ':member_id'      => $selectedMember['id'],
        ':username'       => $username,
        ':email'          => $email,
        ':password'       => password_hash($password, PASSWORD_DEFAULT),
        ':account_status' => $account_status,
        ':token'          => $verification_token,
        ':verified'       => $email_verified,
        ':expires'        => $verification_expires,
    ]);

    $new_user_id = $pdo->lastInsertId();

    apiSafeLog($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_created', 'success');
    apiSafeLog($pdo, $new_user_id, $email, 'account_created', 'success');

    $message = 'Account created successfully! (Email verification skipped)';

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
            $message = 'Account created successfully! Verification email sent.';
            apiSafeLog($pdo, $new_user_id, $email, 'verification_email_sent', 'success');
        } else {
            $message = 'Account created successfully! Verification email failed to send.';
            apiSafeLog($pdo, $new_user_id, $email, 'verification_email_sent', 'failed');
        }
    }

    acRespond(true, $message, [
        'user' => [
            'id'             => $new_user_id,
            'username'       => $username,
            'email'          => $email,
            'account_status' => $account_status,
            'membership_id'  => $selectedMember['membership_id'],
            'last_name'      => $selectedMember['last_name'],
            'first_name'     => $selectedMember['first_name'],
            'is_verified'    => $email_verified,
            'created_at'     => date('Y-m-d H:i:s'),
        ],
    ]);

} catch (PDOException $e) {
    error_log('account-create: ' . $e->getMessage());
    apiSafeLog($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_created', 'failed');

    // 23000 = duplicate key (race condition on username / email / member)
    if ($e->getCode() === '23000') {
        acRespond(false, 'That username, email, or member already has an account.');
    }
    acRespond(false, 'Could not create the account. Please try again.');
}