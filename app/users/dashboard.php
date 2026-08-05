<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
requireLogin();

// Role-based filtering
$currentRole = $_SESSION['role'];
$currentUserId = $_SESSION['user_id'];

// Build query based on role hierarchy, joining member profile info
$baseSelect = "SELECT u.id, u.email, u.role, u.account_status, u.is_verified, u.created_at,
                      m.membership_id, m.last_name, m.first_name, m.middle_name
               FROM users u
               LEFT JOIN members m ON u.member_id = m.id";

if ($currentRole === 'admin') {
    // Admin manages staff accounts (Admin/Manager)
    $stmt = $pdo->prepare("$baseSelect WHERE u.role IN ('admin', 'manager') ORDER BY
        CASE u.role
            WHEN 'admin' THEN 1
            WHEN 'manager' THEN 2
        END, u.created_at DESC");
    $stmt->execute();
} elseif ($currentRole === 'manager') {
    // Manager manages regular User (member) accounts
    $stmt = $pdo->prepare("$baseSelect WHERE u.role = 'user' ORDER BY u.created_at DESC");
    $stmt->execute();
} else {
    // Regular users can only see themselves
    $stmt = $pdo->prepare("$baseSelect WHERE u.id = ? ORDER BY u.created_at DESC");
    $stmt->execute([$currentUserId]);
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = ($currentRole === 'admin' || $currentRole === 'manager') ? 'User Management' : 'My Account';
renderHeader('User Management');
?>

<h1><?php echo htmlspecialchars($pageTitle); ?></h1>

<div class="info-box">
    <strong>Your Role: <span class="badge badge-<?php echo $currentRole; ?>"><?php echo ucfirst($currentRole); ?></span></strong><br>
    <?php if ($currentRole === 'admin'): ?>
        You manage Admin and Manager staff accounts.
    <?php elseif ($currentRole === 'manager'): ?>
        You manage regular User (member) accounts.
    <?php else: ?>
        You can only view your own profile.
    <?php endif; ?>
</div>

<p>
    <?php if ($currentRole === 'admin'): ?>
        Showing all staff accounts (<?php echo count($users); ?> total)
    <?php elseif ($currentRole === 'manager'): ?>
        Showing all member accounts (<?php echo count($users); ?> total)
    <?php else: ?>
        Showing your profile
    <?php endif; ?>
</p>

<table>
    <thead>
        <tr>
            <th>Membership ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Verified</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo $user['membership_id'] ? htmlspecialchars($user['membership_id']) : '<span style="color:#999;">Staff</span>'; ?></td>
            <td><?php echo $user['last_name'] ? htmlspecialchars($user['last_name'] . ', ' . $user['first_name']) : '-'; ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td>
                <span class="badge badge-<?php echo $user['role']; ?>">
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </td>
            <td>
                <span class="badge" style="background: <?php echo $user['account_status'] === 'Active' ? '#28a745' : '#6c757d'; ?>;">
                    <?php echo htmlspecialchars($user['account_status']); ?>
                </span>
            </td>
            <td>
                <span class="badge badge-<?php echo $user['is_verified'] ? 'verified' : 'unverified'; ?>">
                    <?php echo $user['is_verified'] ? 'Yes' : 'No'; ?>
                </span>
            </td>
            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
            <td>
                <a href="user-view.php?user_id=<?php echo $user['id']; ?>">View</a>
                
                <?php if (canEditUser($currentRole, $currentUserId, $user['role'], $user['id'])): ?>
                    | <a href="user-update.php?user_id=<?php echo $user['id']; ?>">Edit</a>
                <?php endif; ?>
                
                <?php if (canDeleteUser($currentRole, $currentUserId, $user['role'], $user['id'])): ?>
                    | <a href="user-delete.php?user_id=<?php echo $user['id']; ?>" 
                         onclick="return confirm('Are you sure you want to delete this user?')" 
                         style="color: #dc3545;">Delete</a>
                <?php elseif ($user['id'] == $currentUserId): ?>
                    | <span style="color: #6c757d;" title="You cannot delete your own account">Delete</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($users)): ?>
    <p style="text-align: center; padding: 40px; color: #6c757d;">
        No accounts to display based on your role permissions.
    </p>
<?php endif; ?>

<?php renderFooter(); ?>
