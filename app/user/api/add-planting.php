<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';
require_once __DIR__ . '/../../manager/crops/api/crop-validate.php';

header('Content-Type: application/json');
requireRole('user');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

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

$result = cm_validate_planting($pdo, $input);
if (!empty($result['errors'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $result['errors']), 'errors' => $result['errors']]);
    exit;
}

try {
    $ok = cm_insert_planting($pdo, $memberId, $result['data']);
    echo json_encode(['success' => (bool) $ok]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the planting. Please try again.']);
}