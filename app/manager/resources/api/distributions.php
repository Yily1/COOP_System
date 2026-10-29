<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');
requireRole('manager');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {

    case 'add':
        $rawIds           = $input['member_ids'] ?? [];
        $resourceName     = trim($input['resource_name'] ?? '');
        $quantity         = trim($input['quantity'] ?? '');
        $distributionDate = trim($input['distribution_date'] ?? '');
        $notes            = trim($input['notes'] ?? '');

        // Normalize: ints only, positive, no duplicates
        $memberIds = is_array($rawIds)
            ? array_values(array_unique(array_filter(array_map('intval', $rawIds), fn($id) => $id > 0)))
            : [];

        if (empty($memberIds) || $resourceName === '' || $quantity === '' || $distributionDate === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'At least one member, plus resource, quantity, and distribution date, are required.']);
            exit;
        }

        // Server-side eligibility check: EVERY selected member must be eligible.
        $eligibleIds = array_map('intval', array_column(getEligibleMembersForDistribution($pdo), 'id'));
        $invalid = array_diff($memberIds, $eligibleIds);
        if (!empty($invalid)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'One or more selected members do not have a crop planting.']);
            exit;
        }

        $distributedBy = (int) ($_SESSION['user_id'] ?? 0);
        $notesValue    = $notes !== '' ? $notes : null;

        try {
            // All-or-nothing: if one insert fails, none are saved.
            $pdo->beginTransaction();
            foreach ($memberIds as $memberId) {
                $ok = addDistribution($pdo, $memberId, $resourceName, $quantity, $distributionDate, $notesValue, $distributedBy);
                if (!$ok) {
                    throw new Exception('Insert failed for member ' . $memberId);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'count' => count($memberIds)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not save the distributions. Please try again.']);
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