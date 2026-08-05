<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireLogin();

$currentRole = $_SESSION['role'];

// Only managers can create User accounts for cooperative members
if ($currentRole !== 'manager') {
    die("Access denied. Only managers can create member accounts.");
}

$message = '';
$success = false;
$verificationLink = '';

$form = [
    'member_id' => '',
    'email' => '',
    'account_status' => 'Active',
];

// Only members without an existing account can be selected
$availableMembers = getAvailableMembers($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $account_status = ($_POST['account_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
    $send_email = isset($_POST['send_verification_email']);

    $form = compact('member_id', 'email', 'account_status');

    $errors = [];
    if ($email === '') $errors[] = "Email is required.";
    if ($password === '') $errors[] = "Password is required.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    $memberIdToLink = null;
    if ($member_id === '') {
        $errors[] = "Please select a member for this account.";
    } else {
        // Re-check server-side that this member is still unlinked
        $check = $pdo->prepare("SELECT m.* FROM members m LEFT JOIN users u ON u.member_id = m.id WHERE m.id = ? AND u.id IS NULL");
        $check->execute([$member_id]);
        $selectedMember = $check->fetch(PDO::FETCH_ASSOC);
        if (!$selectedMember) {
            $errors[] = "That member is no longer available (may already have an account).";
        } else {
            $memberIdToLink = $selectedMember['id'];
        }
    }

    if (!empty($errors)) {
        $message = implode(' ', $errors);
        $success = false;
    } else {
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
                    (member_id, email, password, role, account_status, verification_token, is_verified, email_verification_expires, created_at)
                VALUES
                    (:member_id, :email, :password, 'user', :account_status, :token, :verified, :expires, NOW())
            ");
            $stmt->execute([
                ':member_id'      => $memberIdToLink,
                ':email'          => $email,
                ':password'       => password_hash($password, PASSWORD_DEFAULT),
                ':account_status' => $account_status,
                ':token'          => $verification_token,
                ':verified'       => $email_verified,
                ':expires'        => $verification_expires,
            ]);

            $new_user_id = $pdo->lastInsertId();

            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_created', 'success');
            logActivity($pdo, $new_user_id, $email, 'account_created', 'success');

            if ($send_email) {
                $verificationLink = BASE_URL . "/app/auth/verify-email.php?token=" . $verification_token;

                $email_subject = "Verify Your Email Address";
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

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: noreply@ics-dev.io" . "\r\n";

                if (@mail($email, $email_subject, $email_body, $headers)) {
                    $message = "Account created successfully! Verification email sent.";
                    logActivity($pdo, $new_user_id, $email, 'verification_email_sent', 'success');
                } else {
                    $message = "Account created successfully! Verification email failed to send.";
                    logActivity($pdo, $new_user_id, $email, 'verification_email_sent', 'failed');
                }
            } else {
                $message = "Account created successfully! (Email verification skipped)";
            }

            $success = true;
            $form = ['member_id' => '', 'email' => '', 'account_status' => 'Active'];
            $availableMembers = getAvailableMembers($pdo); // refresh dropdown

        } catch (PDOException $e) {
            $message = "Error creating account: " . $e->getMessage();
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_created', 'failed');
        }
    }
}

$title = 'Create Account';
renderHeader($title);
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Create Account</h2>
        <span class="badge badge-manager">Manager</span>
    </div>

    <div class="info-box">
        Select a cooperative member to give them a login account. Only members without an existing account are listed.
        Don't see the member you're looking for? <a href="<?php echo BASE_URL; ?>/app/members/member-create.php">Create their profile first</a>.
    </div>

    <?php if ($message): ?>
        <div class="<?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($verificationLink): ?>
        <div class="info-box">
            <strong>📧 Email Verification Link (for testing):</strong><br>
            <a href="<?php echo $verificationLink; ?>" target="_blank"><?php echo $verificationLink; ?></a>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                In production, this link will be sent via email. The email has been queued.
            </p>
        </div>
    <?php endif; ?>

    <form method="POST">
        <h3 style="margin-top: 0;">Account Information</h3>

        <div class="form-group">
            <label for="member_id">Select Member:</label>
            <select id="member_id" name="member_id" required>
                <option value="">-- Select a member --</option>
                <?php foreach ($availableMembers as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo (string)$form['member_id'] === (string)$m['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['membership_id'] . ' - ' . $m['last_name'] . ', ' . $m['first_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required placeholder="user@example.com" value="<?php echo htmlspecialchars($form['email']); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Enter password">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
        </div>

        <div class="form-group">
            <label>Role:</label>
            <input type="text" value="User" disabled style="background: #f0f0f0; color: #888;">
            <small style="color: #666;">Managers can only create regular User accounts.</small>
        </div>

        <div class="form-group">
            <label for="account_status">Account Status:</label>
            <select id="account_status" name="account_status">
                <option value="Active" <?php echo $form['account_status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo $form['account_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="send_verification_email" value="1" checked
                       style="width: auto; margin-right: 10px;">
                <span>Send email verification (recommended)</span>
            </label>
            <small style="color: #666; margin-left: 30px;">
                If unchecked, user will be verified immediately without email confirmation.
            </small>
        </div>

        <button type="submit">
            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">person_add</span>
            Create Account
        </button>
    </form>
</div>

<?php renderFooter(); ?>
