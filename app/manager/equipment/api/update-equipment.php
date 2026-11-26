<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/equipment-validate.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('manager')) {
    eqFail('Only managers can edit equipment', 403);
}

$equipmentId   = trim($_POST['equipment_id'] ?? '');
$name          = trim($_POST['name'] ?? '');
$rateMember    = trim($_POST['rate_member'] ?? '');
$rateNonmember = trim($_POST['rate_nonmember'] ?? '');
$unitType      = $_POST['unit_type'] ?? '';

$allowedUnits = ['day', 'hectare', 'sack'];

// ---- Validation ----
if ($equipmentId === '' || !ctype_digit($equipmentId)) {
    eqFail('Missing equipment id');
}

$error = eqCheckName($name, 'Equipment name', true)
      ?? eqCheckWholeNumber($rateMember, 'Member rate')
      ?? eqCheckWholeNumber($rateNonmember, 'Non-member rate')
      ?? eqCheckChoice($unitType, $allowedUnits, 'unit type');

if ($error) {
    eqFail($error);
}

$rateMember    = (int) $rateMember;
$rateNonmember = (int) $rateNonmember;

// Look up the existing row first, so we know the current photo_path
// in case no new photo is uploaded (keep it) or one is (delete the old file).
$existingStmt = $pdo->prepare("SELECT photo_path FROM equipment WHERE id = ?");
$existingStmt->execute([$equipmentId]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    eqFail('Equipment not found', 404);
}

$photoPath = $existing['photo_path']; // default: keep the current photo

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
    eqFail('Database error', 500);
}