<?php
ob_start(); // keep stray PHP warnings from corrupting the JSON
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/_product-photo.php';

header('Content-Type: application/json');

function upRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    upRespond(false, 'Only managers can edit products.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    upRespond(false, 'Invalid request.');
}

$id       = (int)($_POST['id'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$price    = $_POST['price'] ?? '';
$unit     = trim($_POST['unit'] ?? '');
$stock    = $_POST['stock_quantity'] ?? '';
$status   = $_POST['status'] ?? 'available';

$allowedCategories = ['Grains', 'Vegetables', 'Meat & Poultry', 'Farm Inputs', 'Others'];
$allowedStatuses   = ['available', 'out_of_stock', 'discontinued'];

if ($id <= 0) {
    upRespond(false, 'Invalid product.');
}
if ($name === '' || mb_strlen($name) > 150) {
    upRespond(false, 'Please enter a product name (up to 150 characters).');
}
if (!in_array($category, $allowedCategories, true)) {
    upRespond(false, 'Please choose a valid category.');
}
if (!is_numeric($price) || (float)$price < 0) {
    upRespond(false, 'Please enter a valid price.');
}
if ($unit === '' || mb_strlen($unit) > 30) {
    upRespond(false, 'Please enter a unit (for example Kg, Sack, Pc).');
}
if (!is_numeric($stock) || (float)$stock < 0) {
    upRespond(false, 'Please enter a valid stock quantity.');
}
if (!in_array($status, $allowedStatuses, true)) {
    upRespond(false, 'Invalid status.');
}

// Make sure the product exists, and remember its current photo
$stmt = $pdo->prepare('SELECT id, photo_path FROM products WHERE id = ?');
$stmt->execute([$id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
    upRespond(false, 'Product not found.');
}

// Optional new photo (if none is chosen, the current photo is kept)
$photoError = '';
$newPhoto = null;
if (isset($_FILES['photo'])) {
    $newPhoto = saveProductPhoto($_FILES['photo'], $photoError);
    if ($photoError !== '') {
        upRespond(false, $photoError);
    }
}

try {
    $sql = 'UPDATE products SET name = ?, category = ?, price = ?, unit = ?, stock_quantity = ?, status = ?';
    $params = [$name, $category, (float)$price, $unit, (float)$stock, $status];

    if ($newPhoto !== null) {
        $sql .= ', photo_path = ?';
        $params[] = $newPhoto;
    }

    $sql .= ' WHERE id = ?';
    $params[] = $id;

    $pdo->prepare($sql)->execute($params);

    // The new photo replaced the old one, so remove the old file
    if ($newPhoto !== null) {
        deleteProductPhotoFile($existing['photo_path']);
    }

    upRespond(true, 'Product updated.');
} catch (PDOException $e) {
    error_log('update-product: ' . $e->getMessage());
    deleteProductPhotoFile($newPhoto); // don't leave an orphan file behind
    upRespond(false, 'Could not update the product. Please try again.');
}