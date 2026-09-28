<?php
/**
 * app/manager/meetings/api/checkins.php
 * GET  ?meeting_id=5                  -> list ng naka-check in
 * POST {meeting_id, member_id} (JSON) -> mag check-in
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/../../../../config/payment-functions.php';
header('Content-Type: application/json');

apiRequireRole('manager');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $meetingId = (int)($_GET['meeting_id'] ?? 0);
    if (!$meetingId) {
        respond(422, ['error' => 'meeting_id is required.']);
    }

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
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $meetingId  = (int)($input['meeting_id'] ?? 0);
    $memberId   = (int)($input['member_id'] ?? 0);
    $recordedBy = $_SESSION['user_id'] ?? null;

    if (!$meetingId || !$memberId) {
        respond(422, ['error' => 'Missing meeting or member.']);
    }

    try {
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