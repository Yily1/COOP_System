<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../includes/activity-logger.php';
requireLogin();

$currentRole = $_SESSION['role'];
$currentUserId = $_SESSION['user_id'];
$userId = $_GET['user_id'] ?? 0;
$message = '';

// Detect AJAX requests coming from the delete modal on
// admin/user-management.php or manager/user-management.php.
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST') &&
          (($_POST['ajax'] ?? '') === '1' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

// Where to send the browser back to after a non-AJAX delete,
// based on the role of the person doing the deleting - this
// handler is now shared across admin/ and manager/ instead of
// living under app/users/.
function deleterReturnUrl($role) {
    return $role === 'admin' ? '/app/admin/user-management.php' : '/app/manager/user-management.php';
}

$stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
}

// SECURITY: enforce the same rule the UI uses to hide the "Delete" link/button.
$allowedToDelete = $user && canDeleteUser($currentRole, $currentUserId, $user['role'], $user['id']);

if ($user && !$allowedToDelete) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "You don't have permission to delete this user."]);
        exit;
    }
    http_response_code(403);
    die("Access denied. You don't have permission to delete this user.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowedToDelete) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        if ($stmt->rowCount() > 0) {
            logActivity($pdo, $currentUserId, $_SESSION['email'], 'user_deleted', 'success');
            logActivity($pdo, $userId, $user['email'], 'account_deleted', 'success');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
                exit;
            }
            redirect(deleterReturnUrl($currentRole));
        } else {
            $message = "User not found.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
        }
    } catch (PDOException $e) {
        $message = "Error deleting user: " . $e->getMessage();
        logActivity($pdo, $currentUserId, $_SESSION['email'], 'user_deleted', 'failed');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
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
        <a href="<?php echo BASE_URL . deleterReturnUrl($currentRole); ?>">
            <button type="button" style="background: #6c757d;">Cancel</button>
        </a>
    </form>
<?php else: ?>
    <p>User not found.</p>
<?php endif; ?>

<?php renderFooter(); ?>