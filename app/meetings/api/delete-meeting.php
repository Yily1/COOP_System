<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo json_encode(['success' => false, 'errors' => ['Invalid meeting.']]);
    exit;
}

try {
    $checkStmt = $pdo->prepare("SELECT id FROM assembly_meetings WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Meeting not found.']]);
        exit;
    }

    $pdo->beginTransaction();

    // Remove attendance records first (foreign key dependency)
    $deleteAttendance = $pdo->prepare("DELETE FROM meeting_attendance WHERE meeting_id = ?");
    $deleteAttendance->execute([$id]);

    $deleteMeeting = $pdo->prepare("DELETE FROM assembly_meetings WHERE id = ?");
    $deleteMeeting->execute([$id]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('delete-meeting.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Server error. Please try again.']]);
}