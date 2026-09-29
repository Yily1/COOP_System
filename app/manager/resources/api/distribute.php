<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');
requireRole('manager');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {

    case 'add':
        $memberId         = (int) ($input['member_id'] ?? 0);
        $resourceName     = trim($input['resource_name'] ?? '');
        $quantity         = trim($input['quantity'] ?? '');
        $distributionDate = trim($input['distribution_date'] ?? '');
        $notes            = trim($input['notes'] ?? '');

        if ($memberId <= 0 || $resourceName === '' || $quantity === '' || $distributionDate === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Member, resource, quantity, and distribution date are all required.']);
            exit;
        }

        // Server-side eligibility check — don't trust the dropdown alone.
        $eligibleIds = array_column(getEligibleMembersForDistribution($pdo), 'id');
        if (!in_array($memberId, $eligibleIds, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'This member does not currently have an active crop planting.']);
            exit;
        }

        try {
            $distributedBy = (int) ($_SESSION['user_id'] ?? 0);
            $ok = addDistribution($pdo, $memberId, $resourceName, $quantity, $distributionDate, $notes !== '' ? $notes : null, $distributedBy);
            echo json_encode(['success' => $ok]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not save the distribution. Please try again.']);
        }
        break;

    case 'confirm':
    case 'decline':
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Missing distribution id.']);
            exit;
        }

        $distribution = getDistributionById($pdo, $id);
        if (!$distribution) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Distribution record not found.']);
            exit;
        }

        $ok = $action === 'confirm'
            ? confirmDistribution($pdo, $id)
            : declineDistribution($pdo, $id);

        echo json_encode(['success' => $ok]);
        break;

    default:
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}