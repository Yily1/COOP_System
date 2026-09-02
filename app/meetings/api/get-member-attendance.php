<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

header('Content-Type: application/json');

$memberId = $_GET['member_id'] ?? '';

if (empty($memberId)) {
    echo json_encode(['success' => false, 'errors' => ['Walang member na napili.']]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, membership_id, last_name, first_name FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'errors' => ['Hindi nahanap ang member.']]);
    exit;
}

// Kunin ang lahat ng meetings na na-attend ng member na ito
$stmt = $pdo->prepare("
    SELECT am.id, am.title, am.meeting_date, am.meeting_time
    FROM meeting_attendance ma
    JOIN assembly_meetings am ON am.id = ma.meeting_id
    WHERE ma.member_id = ?
    ORDER BY am.meeting_date DESC
");
$stmt->execute([$memberId]);
$attendedMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kunin din ang total bilang ng LAHAT ng meetings na nangyari na (para sa "X of Y")
$totalStmt = $pdo->query("SELECT COUNT(*) as total FROM assembly_meetings");
$totalMeetings = (int) $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

echo json_encode([
    'success' => true,
    'member' => $member,
    'attended_count' => count($attendedMeetings),
    'total_meetings' => $totalMeetings,
    'meetings' => $attendedMeetings,
]);