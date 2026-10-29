<?php
// Location: app/manager/equipment/api/update-booking-status.php
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

// action => [required current status, new status]
$transitions = [
    'approve' => ['pending',  'approved'],
    'reject'  => ['pending',  'rejected'],
    'use'     => ['approved', 'ongoing'],
];

$messages = [
    'approve' => 'Booking approved.',
    'reject'  => 'Booking rejected.',
    'use'     => 'Booking is now ongoing.',
];

if (!$bookingId || !isset($transitions[$action])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

[$fromStatus, $newStatus] = $transitions[$action];

try {
    // The status guard prevents double-handling if two manager tabs are open.
    $stmt = $pdo->prepare("
        UPDATE equipment_bookings
        SET status = ?
        WHERE id = ? AND status = ?
    ");
    $stmt->execute([$newStatus, $bookingId, $fromStatus]);

    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Booking not found or already handled']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => $messages[$action]]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}