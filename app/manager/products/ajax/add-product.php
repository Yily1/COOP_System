<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$name     = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$price    = $_POST['price'] ?? '';
$unit     = trim($_POST['unit'] ?? '');
$stock    = $_POST['stock_quantity'] ?? '';
$status   = trim($_POST['status'] ?? 'available');

$validStatuses = ['available', 'out_of_stock', 'discontinued'];
$errors = [];

if ($name === '') $errors[] = 'Product name is required.';
if ($category === '') $errors[] = 'Category is required.';
if ($price === '' || !is_numeric($price) || (float)$price < 0) $errors[] = 'Price must be 0 or more.';
if ($unit === '') $errors[] = 'Unit is required.';
if ($stock === '' || !is_numeric($stock) || (float)$stock < 0) $errors[] = 'Stock quantity must be 0 or more.';
if (!in_array($status, $validStatuses, true)) $errors[] = 'Invalid status.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$photoPath = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Photo upload failed.']);
        exit;
    }
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType])) {
        echo json_encode(['success' => false, 'message' => 'Photo must be a JPG, PNG, or WEBP image.']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Photo must be smaller than 5MB.']);
        exit;
    }
    $uploadDir = __DIR__ . '/../../../../assets/uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        echo json_encode(['success' => false, 'message' => 'Could not save photo.']);
        exit;
    }
    $photoPath = 'assets/uploads/products/' . $filename;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO products (name, category, unit, price, stock_quantity, status, photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $category, $unit, $price, $stock, $status, $photoPath]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}