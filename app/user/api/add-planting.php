<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

header('Content-Type: application/json');
requireRole('user');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$cropTypeId   = (int) ($input['crop_type_id'] ?? 0);
$location     = trim($input['location'] ?? '');
$areaHectares = $input['area_hectares'] ?? '';
$plantingDate = trim($input['planting_date'] ?? '');

if ($cropTypeId <= 0 || $location === '' || $areaHectares === '' || $plantingDate === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Crop, location, area, and planting date are all required.']);
    exit;
}

if (!is_numeric($areaHectares) || (float) $areaHectares <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Area (hectares) must be a positive number.']);
    exit;
}

$cropType = getCropTypeById($pdo, $cropTypeId);
if (!$cropType) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please choose a crop from the list.']);
    exit;
}

$harvestDate = calculateCropExpectedHarvest($plantingDate, $cropType['max_value'], $cropType['unit']);
if ($harvestDate === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid planting date.']);
    exit;
}

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

if ($memberId <= 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No linked member record found for this account.']);
    exit;
}

try {
    $ok = addCropPlanting($pdo, $memberId, $cropType['name'], $location, (float) $areaHectares, $plantingDate, $harvestDate, (int) $cropType['id']);
    echo json_encode(['success' => (bool) $ok]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the planting. Please try again.']);
}