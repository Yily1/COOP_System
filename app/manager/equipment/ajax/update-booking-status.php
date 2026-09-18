<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only managers can update bookings']);
    exit;
}

$bookingId = $_POST['booking_id'] ?? null;
$action    = $_POST['action'] ?? '';

if (!$bookingId || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$newStatus = $action === 'approve' ? 'ongoing' : 'rejected';

try {
    // The "WHERE status = 'pending'" guard prevents a booking from being
    // approved/rejected twice if two manager tabs are open at once.
    $stmt = $pdo->prepare("
        UPDATE equipment_bookings
        SET status = ?
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$newStatus, $bookingId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Booking not found or already handled']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 'Booking approved.' : 'Booking rejected.',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}