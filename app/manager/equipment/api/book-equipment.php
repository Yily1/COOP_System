<?php
// Location: app/manager/equipment/api/book-equipment.php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/equipment-validate.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !(hasRole('manager') || hasRole('user'))) {
    eqFail('You are not allowed to book equipment', 403);
}

// Managers book directly (already approved; press "Use" when the equipment is taken).
// Regular users need manager approval first (pending).
$bookingStatus = hasRole('manager') ? 'approved' : 'pending';

$equipmentId    = trim($_POST['equipment_id'] ?? '');
$startDate      = trim($_POST['start_date'] ?? '');
$endDate        = trim($_POST['end_date'] ?? '');
$renterName     = trim($_POST['renter_name'] ?? '');
$quantity       = trim($_POST['quantity'] ?? '');
$membershipType = $_POST['membership_type'] ?? '';
$location       = trim($_POST['location'] ?? '');

// ---- Validation ----
$error = eqCheckSelectId($equipmentId, 'equipment')
      ?? eqCheckChoice($membershipType, ['member', 'nonmember'], 'renter type (member or non-member)')
      ?? eqCheckQuantity($quantity)
      ?? eqCheckLocation($location)
      ?? eqCheckDate($startDate, 'Start date')
      ?? eqCheckDate($endDate, 'End date')
      ?? eqCheckName($renterName, 'Renter name');

if ($error) {
    eqFail($error);
}

if (strtotime($endDate) < strtotime($startDate)) {
    eqFail('End date cannot be before start date');
}

try {
    $rateStmt = $pdo->prepare("SELECT rate_member, rate_nonmember FROM equipment WHERE id = ?");
    $rateStmt->execute([$equipmentId]);
    $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rateRow) {
        eqFail('Equipment not found');
    }

    // Overlap check: block if the same equipment already has an approved,
    // ongoing or overdue booking that touches the requested dates.
    // (Pending requests do not block, since they are not confirmed yet.)
    $overlap = $pdo->prepare("
        SELECT COUNT(*) FROM equipment_bookings
        WHERE equipment_id = ?
          AND status IN ('approved', 'ongoing', 'overdue')
          AND start_date <= ?
          AND end_date >= ?
    ");
    $overlap->execute([$equipmentId, $endDate, $startDate]);
    if ((int) $overlap->fetchColumn() > 0) {
        eqFail('This equipment is already booked on those dates. Please choose different dates.', 409);
    }

    $rate = $membershipType === 'member' ? $rateRow['rate_member'] : $rateRow['rate_nonmember'];
    $totalCost = (float) $quantity * $rate;

    $insert = $pdo->prepare("
        INSERT INTO equipment_bookings (equipment_id, start_date, end_date, renter_name, membership_type, quantity, location, total_cost, status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([$equipmentId, $startDate, $endDate, $renterName, $membershipType, $quantity, $location, $totalCost, $bookingStatus, $_SESSION['user_id']]);

    $message = $bookingStatus === 'approved'
        ? 'Booking submitted.'
        : 'Booking request submitted. Waiting for manager approval.';

    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'booking_id' => $pdo->lastInsertId(),
        'status'     => $bookingStatus,
    ]);
} catch (PDOException $e) {
    eqFail('Database error', 500);
}