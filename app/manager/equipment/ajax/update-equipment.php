<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only managers can edit equipment']);
    exit;
}

$equipmentId   = $_POST['equipment_id'] ?? null;
$name          = trim($_POST['name'] ?? '');
$rateMember    = $_POST['rate_member'] ?? null;
$rateNonmember = $_POST['rate_nonmember'] ?? null;
$unitType      = $_POST['unit_type'] ?? '';

$allowedUnits = ['day', 'hectare', 'sack'];

if (!$equipmentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing equipment id']);
    exit;
}

if ($name === '' || $rateMember === null || $rateMember === '' || $rateNonmember === null || $rateNonmember === '' || $unitType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Equipment name, both rates, and unit are required']);
    exit;
}

if (!is_numeric($rateMember) || $rateMember < 0 || !is_numeric($rateNonmember) || $rateNonmember < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rates must be valid positive numbers']);
    exit;
}

if (!in_array($unitType, $allowedUnits, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid unit type']);
    exit;
}

// Look up the existing row first, so we know the current photo_path
// in case no new photo is uploaded (keep it) or one is (delete the old file).
$existingStmt = $pdo->prepare("SELECT photo_path FROM equipment WHERE id = ?");
$existingStmt->execute([$equipmentId]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Equipment not found']);
    exit;
}

$photoPath = $existing['photo_path']; // default: keep the current photo

if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Photo upload failed']);
        exit;
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mimeType = mime_content_type($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Photo must be a JPG, PNG, or WEBP image']);
        exit;
    }

    $maxBytes = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Photo must be smaller than 5MB']);
        exit;
    }

    $uploadDir = __DIR__ . '/../../../../assets/uploads/equipment/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = $allowedTypes[$mimeType];
    $filename = 'eq_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save photo']);
        exit;
    }

    // Delete the old photo file (if any) now that the new one is saved,
    // so replaced photos don't pile up unused on disk.
    if (!empty($existing['photo_path'])) {
        $oldFile = __DIR__ . '/../../../../' . ltrim($existing['photo_path'], '/');
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $photoPath = 'assets/uploads/equipment/' . $filename;
}

try {
    $update = $pdo->prepare("
        UPDATE equipment
        SET name = ?, rate_member = ?, rate_nonmember = ?, unit_type = ?, photo_path = ?
        WHERE id = ?
    ");
    $update->execute([$name, $rateMember, $rateNonmember, $unitType, $photoPath, $equipmentId]);

    echo json_encode([
        'success' => true,
        'message' => 'Equipment updated.',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}