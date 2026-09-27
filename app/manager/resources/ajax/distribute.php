<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');
requireLogin();

if (($_SESSION['role'] ?? '') !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a manager can distribute resources.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$memberId     = (int) ($input['member_id'] ?? 0);
$resourceName = trim($input['resource_name'] ?? '');
$quantity     = trim($input['quantity'] ?? '');
$notes        = isset($input['notes']) ? trim((string) $input['notes']) : null;

if ($memberId <= 0 || $resourceName === '' || $quantity === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Member, resource, and quantity are all required.']);
    exit;
}

// Server-side re-check: never trust that the button was only clickable
// because the browser thought the member was eligible.
if (!memberIsEligibleForResources($pdo, $memberId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This member has no growing, ready-to-harvest, or harvested crop, so they are not eligible for DA resources.']);
    exit;
}

$distributedBy = (int) ($_SESSION['user_id'] ?? 0);

try {
    $ok = distributeResource($pdo, $memberId, $resourceName, $quantity, $distributedBy, $notes !== '' ? $notes : null);
    echo json_encode(['success' => $ok]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not record the distribution. Please try again.']);
}