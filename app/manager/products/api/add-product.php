<?php
ob_start(); // keep stray PHP warnings from corrupting the JSON
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/_product-photo.php';

header('Content-Type: application/json');

function apRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    apRespond(false, 'Only managers can add products.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    apRespond(false, 'Invalid request.');
}

$name     = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$price    = $_POST['price'] ?? '';
$unit     = trim($_POST['unit'] ?? '');
$stock    = $_POST['stock_quantity'] ?? '';
$status   = $_POST['status'] ?? 'available';

$allowedCategories = ['Grains', 'Vegetables', 'Meat & Poultry', 'Farm Inputs', 'Others'];
$allowedStatuses   = ['available', 'out_of_stock', 'discontinued'];

if ($name === '' || mb_strlen($name) > 150) {
    apRespond(false, 'Please enter a product name (up to 150 characters).');
}
if (!in_array($category, $allowedCategories, true)) {
    apRespond(false, 'Please choose a valid category.');
}
if (!is_numeric($price) || (float)$price < 0) {
    apRespond(false, 'Please enter a valid price.');
}
if ($unit === '' || mb_strlen($unit) > 30) {
    apRespond(false, 'Please enter a unit (for example Kg, Sack, Pc).');
}
if (!is_numeric($stock) || (float)$stock < 0) {
    apRespond(false, 'Please enter a valid stock quantity.');
}
if (!in_array($status, $allowedStatuses, true)) {
    apRespond(false, 'Invalid status.');
}

// Optional photo
$photoError = '';
$photoPath = null;
if (isset($_FILES['photo'])) {
    $photoPath = saveProductPhoto($_FILES['photo'], $photoError);
    if ($photoError !== '') {
        apRespond(false, $photoError);
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO products (name, category, price, unit, stock_quantity, status, photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $category, (float)$price, $unit, (float)$stock, $status, $photoPath]);

    apRespond(true, 'Product added.');
} catch (PDOException $e) {
    error_log('add-product: ' . $e->getMessage());
    deleteProductPhotoFile($photoPath); // don't leave an orphan file behind
    apRespond(false, 'Could not save the product. Please try again.');
}