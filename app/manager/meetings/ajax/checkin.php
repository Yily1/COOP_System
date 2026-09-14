<?php
/**
 * app/manager/meetings/ajax/checkin.php
 * Uses app/config/config.php which defines $pdo (PDO), and
 * config/payment-functions.php for markAttendance().
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/payment-functions.php';
header('Content-Type: application/json');

$meetingId = (int)($_POST['meeting_id'] ?? 0);
$memberId  = (int)($_POST['member_id'] ?? 0);
$recordedBy = $_SESSION['user_id'] ?? null;

if (!$meetingId || !$memberId) {
    echo json_encode(['success' => false, 'message' => 'Missing meeting or member.']);
    exit;
}

try {
    $inserted = markAttendance($pdo, $meetingId, $memberId, $recordedBy);

    if ($inserted && $pdo->lastInsertId() !== '0') {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'This member has already checked in.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Check-in failed. Please try again.']);
}