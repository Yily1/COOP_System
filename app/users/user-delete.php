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

// Get user details BEFORE deletion
$stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// SECURITY: enforce the same rule the UI uses to hide the "Delete" link.
// Without this, anyone logged in could POST to this page with any
// user_id (including their own, or an admin's) and delete the account.
$allowedToDelete = $user && canDeleteUser($currentRole, $currentUserId, $user['role'], $user['id']);

if ($user && !$allowedToDelete) {
    http_response_code(403);
    die("Access denied. You don't have permission to delete this user.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowedToDelete) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            // Only log success AFTER the delete actually succeeded.
            logActivity($pdo, $currentUserId, $_SESSION['email'], 'user_deleted', 'success');
            logActivity($pdo, $userId, $user['email'], 'account_deleted', 'success');
            
            redirect('/app/users/dashboard.php');
        } else {
            $message = "User not found.";
        }
    } catch(PDOException $e) {
        $message = "Error deleting user: " . $e->getMessage();
        
        // Log failed deletion
        logActivity($pdo, $currentUserId, $_SESSION['email'], 'user_deleted', 'failed');
    }
}

renderHeader('Delete User');
?>

<h1>Delete User</h1>

<?php if ($message): ?>
    <div class="error"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($user): ?>
    <p>Are you sure you want to delete this user?</p>
    
    <div class="info-box">
        <strong>User Details:</strong><br>
        Email: <?php echo htmlspecialchars($user['email']); ?><br>
        Role: <?php echo htmlspecialchars($user['role']); ?>
    </div>
    
    <form method="POST">
        <button type="submit" style="background: #dc3545;">Delete User</button>
        <a href="<?php echo BASE_URL; ?>/app/users/dashboard.php">
            <button type="button" style="background: #6c757d;">Cancel</button>
        </a>
    </form>
<?php else: ?>
    <p>User not found.</p>
<?php endif; ?>

<?php renderFooter(); ?>