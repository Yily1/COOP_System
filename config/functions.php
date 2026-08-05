<?php

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/index.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        die("Access denied. Required role: $role");
    }
}

/**
 * Central permission rules for editing/deleting a target user.
 * Used by BOTH the UI (to show/hide links) and the actual
 * update/delete scripts (to actually enforce it). Previously
 * only the UI checked this, so anyone could bypass it by hitting
 * user-update.php / user-delete.php directly with a user_id.
 */
function canEditUser($currentRole, $currentUserId, $targetRole, $targetUserId) {
    // Everyone can edit their own account.
    if ($targetUserId == $currentUserId) {
        return true;
    }
    // Admin manages staff accounts (Admin/Manager).
    if ($currentRole === 'admin') {
        return in_array($targetRole, ['admin', 'manager']);
    }
    // Manager manages regular User (member) accounts.
    if ($currentRole === 'manager') {
        return $targetRole === 'user';
    }
    return false;
}

function canDeleteUser($currentRole, $currentUserId, $targetRole, $targetUserId) {
    // Nobody can delete their own account.
    if ($targetUserId == $currentUserId) {
        return false;
    }
    if ($currentRole === 'admin') {
        return in_array($targetRole, ['admin', 'manager']);
    }
    if ($currentRole === 'manager') {
        return $targetRole === 'user';
    }
    return false;
}

/**
 * Only admins can change a role, and never their own.
 */
function canChangeRole($currentRole, $currentUserId, $targetUserId) {
    if ($targetUserId == $currentUserId) {
        return false;
    }
    return $currentRole === 'admin';
}

/**
 * Generate the next Membership ID, e.g. SJFMC-0001, SJFMC-0002...
 * Looks at the highest existing numeric suffix (not just row count)
 * so deleted members don't cause duplicate IDs to be reused.
 */
function generateMembershipId($pdo, $prefix = 'SJFMC') {
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(membership_id, '-', -1) AS UNSIGNED)) as max_num
        FROM members
        WHERE membership_id LIKE ?
    ");
    $stmt->execute([$prefix . '-%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextNum = ($result && $result['max_num']) ? ((int)$result['max_num'] + 1) : 1;

    return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Members who don't have a user account linked to them yet -
 * these are the only ones an Admin should be able to pick from
 * when creating a new account, to avoid duplicate accounts.
 */
function getAvailableMembers($pdo) {
    $stmt = $pdo->query("
        SELECT m.*
        FROM members m
        LEFT JOIN users u ON u.member_id = m.id
        WHERE u.id IS NULL
        ORDER BY m.last_name, m.first_name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function navActive($path) {
    return (strpos($_SERVER['REQUEST_URI'], $path) !== false) ? 'active' : '';
}

function renderHeader($title) {
    $currentRole = $_SESSION['role'] ?? 'guest';
    $currentEmail = $_SESSION['email'] ?? '';
    $isLoggedIn = isset($_SESSION['user_id']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <!-- Material Icons -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="http://localhost/coop-system/assets/css/style.css">


    </head>
    <body>
        <?php if ($isLoggedIn): ?>
            <div class="sidebar">
                <div class="sidebar-header">
                    <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="SJFMC Coop Logo" class="sidebar-logo">
                </div>
                <div class="sidebar-menu">
                    <?php if ($currentRole === 'admin'): ?>
                        <a class="<?php echo navActive('/app/admin/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/admin/dashboard.php">Dashboard</a>
                        <a class="<?php echo navActive('/app/users/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/users/dashboard.php">User Management</a>
                        <a class="<?php echo navActive('/app/users/user-create.php'); ?>" href="<?php echo BASE_URL; ?>/app/users/user-create.php">Create Account</a>
                    <?php elseif ($currentRole === 'manager'): ?>
                        <a class="<?php echo navActive('/app/manager/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/dashboard.php">Dashboard</a>
                        <a class="<?php echo navActive('/app/members/member-list.php'); ?>" href="<?php echo BASE_URL; ?>/app/members/member-list.php">View Members</a>
                        <a class="<?php echo navActive('/app/members/member-create.php'); ?>" href="<?php echo BASE_URL; ?>/app/members/member-create.php">Member Registration</a>
                        <a class="<?php echo navActive('/app/members/account-create.php'); ?>" href="<?php echo BASE_URL; ?>/app/members/account-create.php">Create Account</a>
                        <a class="<?php echo navActive('/app/users/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/users/dashboard.php">User Management</a>
                    <?php elseif ($currentRole === 'user'): ?>
                        <a class="<?php echo navActive('/app/user/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/dashboard.php">Dashboard</a>
                        <a class="<?php echo navActive('/app/users/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/users/dashboard.php">My Account</a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/app/auth/signout.php">Logout</a>
                </div>
            </div>
            <div class="main-content">
                <div class="container">
        <?php else: ?>
            <div class="auth-wrapper">
                <div class="auth-box">
        <?php endif; ?>
    <?php
}

function renderFooter() {
    $isLoggedIn = isset($_SESSION['user_id']);
    ?>
        <?php if ($isLoggedIn): ?>
                </div>
            </div>
        <?php else: ?>
                </div>
            </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
}

?>