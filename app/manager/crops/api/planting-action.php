<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');
requireLogin();

$role     = $_SESSION['role'] ?? '';
$memberId = (int) ($_SESSION['member_id'] ?? 0);

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

    case 'approve':
        if ($role !== 'manager') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only a manager can approve a planting.']);
            exit;
        }
        $ok = approveCropPlanting($pdo, $id);
        echo json_encode(['success' => $ok]);
        break;

    case 'reject':
        if ($role !== 'manager') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only a manager can reject a planting.']);
            exit;
        }
        $reason = isset($input['reason']) ? trim((string) $input['reason']) : null;
        $ok = rejectCropPlanting($pdo, $id, $reason !== '' ? $reason : null);
        echo json_encode(['success' => $ok]);
        break;

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