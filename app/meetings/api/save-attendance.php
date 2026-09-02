<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('manager');

header('Content-Type: application/json');

$meetingId = $_POST['meeting_id'] ?? '';
$attendedMemberIds = $_POST['attended'] ?? [];
$recordedBy = $_SESSION['user_id'];

if (empty($meetingId)) {
    echo json_encode(['success' => false, 'errors' => ['Walang meeting na napili.']]);
    exit;
}

// I-check muna kung finalized na ang meeting na ito - kung oo, huwag nang payagan
$stmt = $pdo->prepare("SELECT attendance_finalized FROM assembly_meetings WHERE id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    echo json_encode(['success' => false, 'errors' => ['Hindi nahanap ang meeting.']]);
    exit;
}

if ((int) $meeting['attendance_finalized'] === 1) {
    echo json_encode(['success' => false, 'errors' => ['Naka-save na at naka-lock na ang attendance na ito.']]);
    exit;
}

$allMembersStmt = $pdo->query("SELECT id FROM members");
$allMemberIds = array_column($allMembersStmt->fetchAll(PDO::FETCH_ASSOC), 'id');

foreach ($allMemberIds as $memberId) {
    if (in_array($memberId, $attendedMemberIds)) {
        markAttendance($pdo, $meetingId, $memberId, $recordedBy);
    } else {
        unmarkAttendance($pdo, $meetingId, $memberId);
    }
}

// Permanenteng i-lock ang attendance ng meeting na ito
$lockStmt = $pdo->prepare("UPDATE assembly_meetings SET attendance_finalized = 1 WHERE id = ?");
$lockStmt->execute([$meetingId]);

echo json_encode(['success' => true]);