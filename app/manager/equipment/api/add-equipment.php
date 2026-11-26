<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/equipment-validate.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('manager')) {
    eqFail('Only managers can add equipment', 403);
}

$name          = trim($_POST['name'] ?? '');
$rateMember    = trim($_POST['rate_member'] ?? '');
$rateNonmember = trim($_POST['rate_nonmember'] ?? '');
$unitType      = $_POST['unit_type'] ?? '';

$allowedUnits = ['day', 'hectare', 'sack'];

// ---- Validation ----
$error = eqCheckName($name, 'Equipment name', true)
      ?? eqCheckWholeNumber($rateMember, 'Member rate')
      ?? eqCheckWholeNumber($rateNonmember, 'Non-member rate')
      ?? eqCheckChoice($unitType, $allowedUnits, 'unit type');

if ($error) {
    eqFail($error);
}

$rateMember    = (int) $rateMember;
$rateNonmember = (int) $rateNonmember;

// ---- Photo upload (optional) ----
$photoPath = null;

if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        eqFail('Photo upload failed');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // Verify the actual file content, not just the filename extension.
    $mimeType = mime_content_type($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        eqFail('Photo must be a JPG, PNG, or WEBP image');
    }

    $maxBytes = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxBytes) {
        eqFail('Photo must be smaller than 5MB');
    }

    $uploadDir = __DIR__ . '/../../../../assets/uploads/equipment/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = $allowedTypes[$mimeType];
    $filename = 'eq_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        eqFail('Could not save photo', 500);
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
    eqFail('Database error', 500);
}