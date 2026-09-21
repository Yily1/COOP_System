<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$requestId    = $_POST['request_id'] ?? '';
$status       = trim($_POST['status'] ?? '');
$managerNote  = trim($_POST['manager_note'] ?? '');

$validStatuses = ['pending', 'under_review', 'approved', 'processing', 'ready_for_pickup', 'claimed', 'rejected'];

if (!$requestId || !ctype_digit((string)$requestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request reference.']);
    exit;
}
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE product_requests
        SET status = ?, manager_note = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $managerNote !== '' ? $managerNote : null, $requestId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}