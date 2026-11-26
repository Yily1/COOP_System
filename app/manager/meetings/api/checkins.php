<?php
/**
 * app/manager/meetings/api/checkins.php
 * GET  ?meeting_id=5                  -> list ng naka-check in
 * POST {meeting_id, member_id} (JSON) -> mag check-in
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/../../../../config/payment-functions.php';
require_once __DIR__ . '/meeting-validate.php';
header('Content-Type: application/json');

apiRequireRole('manager');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!mt_valid_id($_GET['meeting_id'] ?? '')) {
        respond(422, ['error' => 'meeting_id is required.']);
    }
    $meetingId = (int)$_GET['meeting_id'];

    $stmt = $pdo->prepare(
        "SELECT m.id, m.membership_id,
                CONCAT(m.first_name, ' ', m.last_name) AS name
         FROM meeting_attendance a
         JOIN members m ON m.id = a.member_id
         WHERE a.meeting_id = :meeting_id
         ORDER BY m.last_name ASC, m.first_name ASC"
    );
    $stmt->execute([':meeting_id' => $meetingId]);
    respond(200, ['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        respond(422, ['error' => 'Invalid request.']);
    }

    // Format checks (meeting_id at member_id)
    $v = mt_validate_checkin($input);
    if (!empty($v['errors'])) {
        respond(422, ['error' => implode(' ', $v['errors']), 'errors' => $v['errors']]);
    }

    $meetingId  = $v['data']['meeting_id'];
    $memberId   = $v['data']['member_id'];
    $recordedBy = $_SESSION['user_id'] ?? null;

    try {
        // Check-in is only allowed until the end of the meeting's date.
        $stmt = $pdo->prepare('SELECT meeting_date FROM meetings WHERE id = :id');
        $stmt->execute([':id' => $meetingId]);
        $meeting = $stmt->fetch();

        if (!$meeting) {
            respond(404, ['error' => 'Meeting not found.']);
        }
        if (!isMeetingCheckInOpen($meeting['meeting_date'])) {
            respond(403, ['error' => 'This meeting has already ended. Check-in is closed.']);
        }

        // Dapat umiiral ang member
        $stmt = $pdo->prepare('SELECT id FROM members WHERE id = :id');
        $stmt->execute([':id' => $memberId]);
        if (!$stmt->fetch()) {
            respond(404, ['error' => 'Member not found.']);
        }

        $inserted = markAttendance($pdo, $meetingId, $memberId, $recordedBy);

        if ($inserted && $pdo->lastInsertId() !== '0') {
            respond(201, ['message' => 'Checked in.']);
        }
        respond(409, ['error' => 'This member has already checked in.']);
    } catch (PDOException $e) {
        respond(500, ['error' => 'Check-in failed. Please try again.']);
    }
}

header('Allow: GET, POST');
respond(405, ['error' => 'Method not allowed.']);