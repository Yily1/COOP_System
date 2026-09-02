<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$title = trim($_POST['title'] ?? '');
$meetingDate = $_POST['meeting_date'] ?? '';
$meetingTime = $_POST['meeting_time'] ?? '';
$location = trim($_POST['location'] ?? '');
$agenda = trim($_POST['agenda'] ?? '');

if (empty($title)) {
    $response['errors'][] = 'Ilagay ang title ng meeting.';
}
if (empty($meetingDate)) {
    $response['errors'][] = 'Piliin ang petsa ng meeting.';
}
if (empty($meetingTime)) {
    $response['errors'][] = 'Piliin ang oras ng meeting.';
}
if (empty($location)) {
    $response['errors'][] = 'Ilagay ang lokasyon ng meeting.';
}
if (empty($agenda)) {
    $response['errors'][] = 'Ilagay ang agenda ng meeting.';
}

if (empty($response['errors'])) {
    $stmt = $pdo->prepare("
        INSERT INTO assembly_meetings (meeting_date, meeting_time, title, location, agenda, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$meetingDate, $meetingTime, $title, $location, $agenda, $_SESSION['user_id']]);
    $newId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM assembly_meetings WHERE id = ?");
    $stmt->execute([$newId]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['meeting'] = $meeting;
}

echo json_encode($response);