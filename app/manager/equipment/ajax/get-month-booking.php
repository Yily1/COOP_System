<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !canAccessEquipment($_SESSION['role'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$year  = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}

try {
    $bookings = getBookingsForMonth($pdo, $year, $month);

    $data = array_map(function ($b) {
        return [
            'equipment_name' => $b['equipment_name'],
            'renter_name'    => $b['renter_name'],
            'start_date'     => $b['start_date'],
            'end_date'       => $b['end_date'],
        ];
    }, $bookings);

    echo json_encode(['success' => true, 'bookings' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}