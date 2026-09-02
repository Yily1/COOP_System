<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../includes/activity-logger.php';

requireRole('manager');

header('Content-Type: application/json');

$memberId = $_POST['member_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT m.*, u.id as user_id
    FROM members m
    LEFT JOIN users u ON u.member_id = m.id
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit;
}

// SECURITY / DATA INTEGRITY: only members without a linked account
// can be deleted, so we never orphan an existing login account.
if (!empty($member['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'This member has a linked login account and cannot be deleted. Remove the account first, or edit the member instead.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
    $stmt->execute([$memberId]);

    if ($stmt->rowCount() > 0) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_deleted', 'success');
        echo json_encode(['success' => true, 'message' => 'Member deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found.']);
    }
} catch (PDOException $e) {
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_deleted', 'failed');
    echo json_encode(['success' => false, 'message' => 'Error deleting member: ' . $e->getMessage()]);
}