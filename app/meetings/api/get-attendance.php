<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

header('Content-Type: application/json');

$meetingId = $_GET['meeting_id'] ?? '';

if (empty($meetingId)) {
    echo json_encode(['success' => false, 'errors' => ['Walang meeting na napili.']]);
    exit;
}

$meetingStmt = $pdo->prepare("SELECT attendance_finalized FROM assembly_meetings WHERE id = ?");
$meetingStmt->execute([$meetingId]);
$meeting = $meetingStmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    echo json_encode(['success' => false, 'errors' => ['Hindi nahanap ang meeting.']]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.id, m.membership_id, m.last_name, m.first_name,
           (ma.id IS NOT NULL) as is_present
    FROM members m
    LEFT JOIN meeting_attendance ma ON ma.member_id = m.id AND ma.meeting_id = ?
    ORDER BY m.last_name, m.first_name
");
$stmt->execute([$meetingId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDO returns column values as strings (e.g. "0"/"1"), and a non-empty
// string like "0" is truthy in JavaScript. Cast explicitly to a real
// boolean here so the JSON output is `true`/`false`, not "0"/"1".
$members = array_map(function ($member) {
    $member['is_present'] = (bool) (int) $member['is_present'];
    return $member;
}, $members);

echo json_encode([
    'success' => true,
    'members' => $members,
    'finalized' => (bool) $meeting['attendance_finalized'],
]);