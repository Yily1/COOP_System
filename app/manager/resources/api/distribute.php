<?php
/**
 * app/manager/resources/api/distributions.php
 *
 * GET                                        -> list ng lahat ng distributions
 * POST  {member_id, resource_name, quantity,
 *        distribution_date, notes?}          -> mag-add ng distribution   (201)
 * PATCH {id, status: "released"}             -> confirm                   (200)
 * PATCH {id, status: "not_released"}         -> decline                   (200)
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
header('Content-Type: application/json');

apiRequireRole('manager');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ------------------------------------------------------------
// GET — list
// ------------------------------------------------------------
if ($method === 'GET') {
    respond(200, ['data' => getAllDistributions($pdo)]);
}

// ------------------------------------------------------------
// POST — add a distribution
// ------------------------------------------------------------
if ($method === 'POST') {
    $memberId         = (int) ($input['member_id'] ?? 0);
    $resourceName     = trim($input['resource_name'] ?? '');
    $quantity         = trim($input['quantity'] ?? '');
    $distributionDate = trim($input['distribution_date'] ?? '');
    $notes            = trim($input['notes'] ?? '');

    if ($memberId <= 0 || $resourceName === '' || $quantity === '' || $distributionDate === '') {
        respond(422, ['error' => 'Member, resource, quantity, and distribution date are all required.']);
    }

    // Server-side eligibility check — don't trust the dropdown alone.
    $eligibleIds = array_map('intval', array_column(getEligibleMembersForDistribution($pdo), 'id'));
    if (!in_array($memberId, $eligibleIds, true)) {
        respond(422, ['error' => 'This member does not currently have an active crop planting.']);
    }

    try {
        $distributedBy = (int) ($_SESSION['user_id'] ?? 0);
        $ok = addDistribution(
            $pdo, $memberId, $resourceName, $quantity, $distributionDate,
            $notes !== '' ? $notes : null, $distributedBy
        );

        if (!$ok) {
            respond(500, ['error' => 'Could not save the distribution. Please try again.']);
        }
        respond(201, ['message' => 'Distribution added.', 'id' => (int) $pdo->lastInsertId()]);
    } catch (Exception $e) {
        respond(500, ['error' => 'Could not save the distribution. Please try again.']);
    }
}

// ------------------------------------------------------------
// PATCH — confirm / decline
// ------------------------------------------------------------
if ($method === 'PATCH') {
    $id     = (int) ($input['id'] ?? ($_GET['id'] ?? 0));
    $status = $input['status'] ?? '';

    if ($id <= 0) {
        respond(422, ['error' => 'Missing distribution id.']);
    }
    if (!in_array($status, ['released', 'not_released'], true)) {
        respond(422, ['error' => 'Status must be "released" or "not_released".']);
    }

    if (!getDistributionById($pdo, $id)) {
        respond(404, ['error' => 'Distribution record not found.']);
    }

    $ok = $status === 'released'
        ? confirmDistribution($pdo, $id)
        : declineDistribution($pdo, $id);

    if (!$ok) {
        respond(500, ['error' => 'Could not update the distribution. Please try again.']);
    }
    respond(200, ['message' => $status === 'released' ? 'Distribution confirmed.' : 'Distribution declined.']);
}

header('Allow: GET, POST, PATCH');
respond(405, ['error' => 'Method not allowed.']);