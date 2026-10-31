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

$memberId = (int) ($input['member_id'] ?? 0);
$cropName = trim($input['crop_name'] ?? '');
$location = trim($input['location'] ?? '');
$area     = (float) ($input['area_hectares'] ?? 0);
$planted  = trim($input['planting_date'] ?? '');
$expected = trim($input['expected_harvest_date'] ?? '');

if ($memberId <= 0 || $cropName === '' || $location === '' || $area <= 0 || $planted === '' || $expected === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

if (strtotime($expected) < strtotime($planted)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Expected harvest date cannot be before the planting date.']);
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
    $stmt = $pdo->prepare("
        INSERT INTO crops (member_id, crop_name, location, area_hectares, planting_date, expected_harvest_date, status)
        VALUES (?, ?, ?, ?, ?, ?, 'growing')
    ");
    $stmt->execute([$memberId, $cropName, $location, $area, $planted, $expected]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the planting.']);
}