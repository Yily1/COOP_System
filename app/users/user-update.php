<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireLogin();

$currentRole = $_SESSION['role'];
$currentUserId = $_SESSION['user_id'];
$userId = $_GET['user_id'] ?? 0;
$message = '';
$success = false;

// Detect AJAX requests coming from the edit modal on user-view.php.
// We check both the header (sent by fetch()) and the hidden
// "ajax" field (belt-and-suspenders, in case the header is ever
// stripped by a proxy).
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_POST['ajax']));

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// SECURITY: enforce the same rule the UI uses to hide the "Edit" link/button.
// Without this, anyone logged in could POST to this page with any
// user_id and edit accounts they have no business touching - this
// matters even more now that requests can come in silently via AJAX.
$allowedToEdit = $user && canEditUser($currentRole, $currentUserId, $user['role'], $user['id']);

if ($user && !$allowedToEdit) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => "Access denied."]));
    }
    die("Access denied. You don't have permission to edit this user.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowedToEdit) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $account_status = $_POST['account_status'] ?? '';

    $updates = [];
    $params = [];
    $changes = []; // Track what changed, for the activity log

    if ($email && $email !== $user['email']) {
        $updates[] = "email = ?";
        $params[] = $email;
        $changes[] = 'email';
    }

    if ($password) {
        $updates[] = "password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
        $changes[] = 'password';
    }

    if (in_array($account_status, ['Active', 'Inactive']) && $account_status !== $user['account_status']) {
        $updates[] = "account_status = ?";
        $params[] = $account_status;
        $changes[] = 'account_status';
    }

    // Only admins may change a role, and never their own (matches canChangeRole()).
    // Managers/users submitting a role field are silently ignored here rather
    // than trusted, since the dropdown is only ever meant to be admin-editable.
    if ($role && in_array($role, ['admin', 'manager', 'user']) && $role !== $user['role']) {
        if (canChangeRole($currentRole, $currentUserId, $user['id'])) {
            $updates[] = "role = ?";
            $params[] = $role;
            $changes[] = 'role';
        }
    }

    if (!empty($updates)) {
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $message = "User updated successfully!";
            $success = true;

            $changesStr = implode(', ', $changes);

            // Log the update action by the admin/manager doing the editing
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_updated (' . $changesStr . ')', 'success');

            // Log the change for the affected user (may be the same person)
            logActivity($pdo, $userId, $user['email'], 'profile_updated (' . $changesStr . ')', 'success');

            // Refresh user data so the response/page reflects the new values
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $message = "Error updating user: " . $e->getMessage();

            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'user_updated', 'failed');
        }
    } else {
        $message = "No changes were made.";
        $success = true;
    }
}

// ============================================================
// AJAX RESPONSE (used by the edit modal on user-view.php)
// Return JSON instead of a rendered page so the modal can
// update the Account Information card in place.
// ============================================================
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message ?: 'No changes were made.',
        'user' => $user ? [
            'email' => $user['email'],
            'account_status' => $user['account_status'],
            'role' => $user['role'],
        ] : null,
    ]);
    exit;
}

// ============================================================
// NON-AJAX FALLBACK (admin/manager editing another account via
// the full-page form, or direct navigation) - unchanged.
// ============================================================
renderHeader('Update User');
?>

<h1>Update User</h1>

<?php if ($message): ?>
    <div class="<?php echo $success ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($user): ?>
    <form method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password (leave empty to keep current):</label>
            <input type="password" id="password" name="password">
        </div>

        <?php $canChangeThisRole = canChangeRole($currentRole, $currentUserId, $user['id']); ?>
        <div class="form-group">
            <label for="role">Role:</label>
            <select id="role" name="role" <?php echo $canChangeThisRole ? '' : 'disabled'; ?>>
                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
            <?php if (!$canChangeThisRole): ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                <small style="color: #666;">Only admins can change roles, and not their own.</small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="account_status">Account Status:</label>
            <select id="account_status" name="account_status">
                <option value="Active" <?php echo $user['account_status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo $user['account_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <button type="submit">Update User</button>
    </form>

    <?php
    // Show linked member profile info, read-only (Personal/Membership
    // Info is edited via Member Profiling, not here)
    $memStmt = $pdo->prepare("SELECT * FROM members WHERE id = (SELECT member_id FROM users WHERE id = ?)");
    $memStmt->execute([$userId]);
    $linkedMember = $memStmt->fetch(PDO::FETCH_ASSOC);
    ?>
    <?php if ($linkedMember): ?>
        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-top: 0;">Linked Member Profile (read-only)</h3>
            <p><strong>Membership ID:</strong> <?php echo htmlspecialchars($linkedMember['membership_id']); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($linkedMember['last_name'] . ', ' . $linkedMember['first_name']); ?></p>
            <p style="font-size: 13px; color: #666;">To edit personal/membership details, use Member Profiling.</p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <p>User not found.</p>
<?php endif; ?>

<?php renderFooter(); ?>