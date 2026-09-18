<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only managers can add equipment']);
    exit;
}

$name          = trim($_POST['name'] ?? '');
$rateMember    = $_POST['rate_member'] ?? null;
$rateNonmember = $_POST['rate_nonmember'] ?? null;
$unitType      = $_POST['unit_type'] ?? '';

$allowedUnits = ['day', 'hectare', 'sack'];

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

// ---- Photo upload (optional) ----
$photoPath = null;

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

    // Verify the actual file content, not just the filename extension.
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

    // Stored relative to the app root, matching how equipment.php builds the <img> src
    $photoPath = 'assets/uploads/equipment/' . $filename;
}

try {
    $insert = $pdo->prepare("
        INSERT INTO equipment (name, rate_member, rate_nonmember, unit_type, photo_path, status)
        VALUES (?, ?, ?, ?, ?, 'available')
    ");
    $insert->execute([$name, $rateMember, $rateNonmember, $unitType, $photoPath]);

    echo json_encode([
        'success'      => true,
        'message'      => 'Equipment added.',
        'equipment_id' => $pdo->lastInsertId(),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}