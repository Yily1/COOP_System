<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$memberId   = (int) ($input['member_id'] ?? 0);
$cropTypeId = (int) ($input['crop_type_id'] ?? 0);
$location   = trim($input['location'] ?? '');
$area       = (float) ($input['area_hectares'] ?? 0);
$planted    = trim($input['planting_date'] ?? '');

if ($memberId <= 0 || $cropTypeId <= 0 || $location === '' || $area <= 0 || $planted === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

$cropType = getCropTypeById($pdo, $cropTypeId);
if (!$cropType) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please choose a crop from the list.']);
    exit;
}

$expected = calculateCropExpectedHarvest($planted, $cropType['max_value'], $cropType['unit']);
if ($expected === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid planting date.']);
    exit;
}

$check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
$check->execute([$memberId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit;
}

try {
    $ok = addCropPlanting($pdo, $memberId, $cropType['name'], $location, $area, $planted, $expected, (int) $cropType['id']);
    echo json_encode(['success' => (bool) $ok]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the planting.']);
}