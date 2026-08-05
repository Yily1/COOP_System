<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireLogin();

$currentRole = $_SESSION['role'];

if ($currentRole !== 'manager') {
    die("Access denied. Only managers can delete member profiles.");
}

$memberId = $_GET['member_id'] ?? 0;
$message = '';

// Get member details, plus whether they have a linked account
$stmt = $pdo->prepare("
    SELECT m.*, u.id as user_id
    FROM members m
    LEFT JOIN users u ON u.member_id = m.id
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Member not found.");
}

// SECURITY / DATA INTEGRITY: only members without a linked account
// can be deleted, so we never orphan an existing login account.
$canDelete = empty($member['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canDelete) {
        http_response_code(403);
        die("This member has a linked account and cannot be deleted.");
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
        $stmt->execute([$memberId]);

        if ($stmt->rowCount() > 0) {
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_deleted', 'success');
            redirect('/app/members/member-list.php');
        } else {
            $message = "Member not found.";
        }
    } catch (PDOException $e) {
        $message = "Error deleting member: " . $e->getMessage();
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_deleted', 'failed');
    }
}

renderHeader('Delete Member');
?>

<h1>Delete Member</h1>

<?php if ($message): ?>
    <div class="error"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!$canDelete): ?>
    <div class="error">
        This member (<?php echo htmlspecialchars($member['membership_id']); ?>) has a linked login account and cannot be deleted.
        The account must be removed first by an Admin, or this member should just be edited instead.
    </div>
    <p style="margin-top: 15px;">
        <a href="<?php echo BASE_URL; ?>/app/members/member-list.php">
            <button style="background: #6c757d;">Back to Members</button>
        </a>
    </p>
<?php else: ?>
    <p>Are you sure you want to delete this member profile? This cannot be undone.</p>

    <div class="info-box">
        <strong>Member Details:</strong><br>
        Membership ID: <?php echo htmlspecialchars($member['membership_id']); ?><br>
        Name: <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name']); ?>
    </div>

    <form method="POST">
        <button type="submit" style="background: #dc3545;">Delete Member</button>
        <a href="<?php echo BASE_URL; ?>/app/members/member-list.php">
            <button type="button" style="background: #6c757d;">Cancel</button>
        </a>
    </form>
<?php endif; ?>

<?php renderFooter(); ?>
