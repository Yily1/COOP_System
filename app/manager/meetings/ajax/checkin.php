<?php
/**
 * app/manager/meetings/ajax/checkin.php
 * Uses app/config/config.php which defines $pdo (PDO).
 */
require_once __DIR__ . '/../../../../config/config.php';
header('Content-Type: application/json');

$meetingId = (int)($_POST['meeting_id'] ?? 0);
$memberId  = (int)($_POST['member_id'] ?? 0);

if (!$meetingId || !$memberId) {
    echo json_encode(['success' => false, 'message' => 'Missing meeting or member.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO attendance (meeting_id, member_id) VALUES (:meeting_id, :member_id)'
    );
    $stmt->execute([':meeting_id' => $meetingId, ':member_id' => $memberId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'This member has already checked in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-in failed. Please try again.']);
    }
}