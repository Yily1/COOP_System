<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');
requireLogin();

$role = $_SESSION['role'] ?? '';
if ($role !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only members can submit a planting.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$cropName     = trim($input['crop_name'] ?? '');
$location     = trim($input['location'] ?? '');
$areaHectares = $input['area_hectares'] ?? '';
$plantingDate = trim($input['planting_date'] ?? '');
$harvestDate  = trim($input['expected_harvest_date'] ?? '');

if ($cropName === '' || $location === '' || $areaHectares === '' || $plantingDate === '' || $harvestDate === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Crop, location, area, planting date, and expected harvest date are all required.']);
    exit;
}

if (!is_numeric($areaHectares) || (float) $areaHectares <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Area (hectares) must be a positive number.']);
    exit;
}

if (strtotime($harvestDate) < strtotime($plantingDate)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Expected harvest date cannot be before the planting date.']);
    exit;
}

// Same account-linking pattern as getAvailableMembers(): users.member_id
// points at members.id. Adjust this line if your login flow stores it
// under a different session key.
$memberId = (int) ($_SESSION['member_id'] ?? 0);

if ($memberId <= 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No linked member record found for this account.']);
    exit;
}

try {
    $ok = addCropPlanting($pdo, $memberId, $cropName, $location, (float) $areaHectares, $plantingDate, $harvestDate);
    echo json_encode(['success' => $ok]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the planting. Please try again.']);
}