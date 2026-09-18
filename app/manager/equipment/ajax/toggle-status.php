<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    echo json_encode(['error' => 'Only managers can update equipment status']);
    exit;
}

$equipmentId = $_POST['equipment_id'] ?? null;

if (!$equipmentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing equipment_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT status FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        exit;
    }

    $newStatus = $equipment['status'] === 'available' ? 'not_available' : 'available';

    $update = $pdo->prepare("UPDATE equipment SET status = ? WHERE id = ?");
    $update->execute([$newStatus, $equipmentId]);

    echo json_encode(['success' => true, 'status' => $newStatus]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}