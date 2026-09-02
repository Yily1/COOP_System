<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

header('Content-Type: application/json');

$errors = [];

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$title = trim($_POST['title'] ?? '');
$meetingDate = trim($_POST['meeting_date'] ?? '');
$meetingTime = trim($_POST['meeting_time'] ?? '');
$location = trim($_POST['location'] ?? '');
$agenda = trim($_POST['agenda'] ?? '');

if (!$id) {
    $errors[] = 'Invalid meeting.';
}
if ($title === '') {
    $errors[] = 'Meeting title is required.';
}
if ($meetingDate === '' || !DateTime::createFromFormat('Y-m-d', $meetingDate)) {
    $errors[] = 'A valid date is required.';
}
if ($meetingTime === '') {
    $errors[] = 'Time is required.';
}
if ($location === '') {
    $errors[] = 'Location is required.';
}
if ($agenda === '') {
    $errors[] = 'Agenda is required.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $checkStmt = $pdo->prepare("SELECT id FROM assembly_meetings WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Meeting not found.']]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE assembly_meetings
        SET title = ?, meeting_date = ?, meeting_time = ?, location = ?, agenda = ?
        WHERE id = ?
    ");
    $stmt->execute([$title, $meetingDate, $meetingTime, $location, $agenda, $id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('update-meeting.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Server error. Please try again.']]);
}