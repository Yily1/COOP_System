<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/crop-validate.php';

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

$result = cm_validate_planting($pdo, $input);
$errors = $result['errors'];

$memberId = (int) ($input['member_id'] ?? 0);
if ($memberId <= 0) {
    array_unshift($errors, 'Select a member from the search results.');
} else {
    $check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
    $check->execute([$memberId]);
    if (!$check->fetch()) {
        array_unshift($errors, 'Member not found.');
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
    exit;
}

try {
    $ok = cm_insert_planting($pdo, $memberId, $result['data']);
    echo json_encode(['success' => (bool) $ok]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the planting.']);
}