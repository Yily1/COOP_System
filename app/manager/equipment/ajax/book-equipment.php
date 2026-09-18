<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !(hasRole('manager') || hasRole('user'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to book equipment']);
    exit;
}

// Managers book directly (ongoing). Regular users need manager approval first (pending).
$bookingStatus = hasRole('manager') ? 'ongoing' : 'pending';

$equipmentId    = $_POST['equipment_id'] ?? null;
$startDate      = $_POST['start_date'] ?? '';
$endDate        = $_POST['end_date'] ?? '';
$renterName     = trim($_POST['renter_name'] ?? '');
$quantity       = $_POST['quantity'] ?? null;
$membershipType = $_POST['membership_type'] ?? '';

if (!$equipmentId || $startDate === '' || $endDate === '' || $renterName === '' || $quantity === null || $quantity === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!in_array($membershipType, ['member', 'nonmember'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select renter type (member or non-member)']);
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantity must be a valid positive number']);
    exit;
}

if (strtotime($endDate) < strtotime($startDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'End date cannot be before start date']);
    exit;
}

try {
    $rateStmt = $pdo->prepare("SELECT rate_member, rate_nonmember FROM equipment WHERE id = ?");
    $rateStmt->execute([$equipmentId]);
    $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rateRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Equipment not found']);
        exit;
    }

    $rate = $membershipType === 'member' ? $rateRow['rate_member'] : $rateRow['rate_nonmember'];
    $totalCost = $quantity * $rate;

    $insert = $pdo->prepare("
        INSERT INTO equipment_bookings (equipment_id, start_date, end_date, renter_name, membership_type, quantity, total_cost, status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([$equipmentId, $startDate, $endDate, $renterName, $membershipType, $quantity, $totalCost, $bookingStatus, $_SESSION['user_id']]);

    $message = $bookingStatus === 'ongoing'
        ? 'Booking submitted.'
        : 'Booking request submitted. Waiting for manager approval.';

    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'booking_id' => $pdo->lastInsertId(),
        'status'     => $bookingStatus,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}