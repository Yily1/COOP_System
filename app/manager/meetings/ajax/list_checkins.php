<?php
/**
 * app/manager/meetings/ajax/list_checkins.php
 * Returns the members already checked in to a given meeting.
 * Uses app/config/config.php which defines $pdo (PDO).
 */
require_once __DIR__ . '/../../../../config/config.php';
header('Content-Type: application/json');

$meetingId = (int)($_GET['meeting_id'] ?? 0);

if (!$meetingId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT m.id, m.membership_id,
            CONCAT(m.first_name, ' ', m.last_name) AS name
     FROM attendance a
     JOIN members m ON m.id = a.member_id
     WHERE a.meeting_id = :meeting_id
     ORDER BY m.last_name ASC, m.first_name ASC"
);
$stmt->execute([':meeting_id' => $meetingId]);

echo json_encode($stmt->fetchAll());