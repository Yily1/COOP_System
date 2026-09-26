<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

header('Content-Type: application/json');
requireRole('user');

$role = $_SESSION['role'] ?? 'user';

// Same account-linking pattern used by payments.php: look up members.id
// via the users -> members join, not a session value.
$userId = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT m.id AS member_db_id
    FROM users u
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$myProfile = $stmt->fetch(PDO::FETCH_ASSOC);
$memberId  = (int) ($myProfile['member_db_id'] ?? 0);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$id     = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing planting id.']);
    exit;
}

$crop = getCropById($pdo, $id);
if (!$crop) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Planting not found.']);
    exit;
}

switch ($action) {

    case 'advance':
        // Only the owning member can move their own crop forward.
        if ($role !== 'user' || (int) $crop['member_id'] !== $memberId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You can only update your own plantings.']);
            exit;
        }

        $nextStatus = $input['status'] ?? '';

        // Enforce the allowed forward transitions server-side.
        $validTransitions = [
            'growing'          => 'ready_to_harvest',
            'ready_to_harvest' => 'harvested',
        ];

        if (($validTransitions[$crop['status']] ?? null) !== $nextStatus) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'That status change is not allowed from the current stage.']);
            exit;
        }

        $ok = advanceCropStatus($pdo, $id, $nextStatus);
        echo json_encode(['success' => $ok]);
        break;

    default:
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}