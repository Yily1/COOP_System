<?php
// Location: app/manager/equipment/api/mark-returned.php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

apiRequireRole('manager');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE equipment_bookings
    SET status = 'returned'
    WHERE id = ? AND status IN ('ongoing', 'overdue')
");
$stmt->execute([$bookingId]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or already returned.']);
    exit;
}

echo json_encode(['success' => true]);