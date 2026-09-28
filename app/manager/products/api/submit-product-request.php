<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('user');
header('Content-Type: application/json');

$stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$memberId = $stmt->fetchColumn();

if (!$memberId) {
    echo json_encode(['success' => false, 'message' => 'No member profile is linked to your account.']);
    exit;
}

$productId   = $_POST['product_id'] ?? '';
$productName = trim($_POST['requested_product_name'] ?? '');
$quantity    = $_POST['quantity'] ?? '';
$pickupDate  = trim($_POST['pickup_date'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if ($quantity === '' || !is_numeric($quantity) || (float)$quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid quantity.']);
    exit;
}
if ($pickupDate === '' || !DateTime::createFromFormat('Y-m-d', $pickupDate)) {
    echo json_encode(['success' => false, 'message' => 'A valid pickup date is required.']);
    exit;
}

if ($productId !== '' && ctype_digit((string)$productId)) {
    $stmt = $pdo->prepare("SELECT status, stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'This product no longer exists.']);
        exit;
    }
    if ($product['status'] !== 'available' || (float)$quantity > (float)$product['stock_quantity']) {
        echo json_encode(['success' => false, 'message' => 'Requested quantity exceeds what is currently available.']);
        exit;
    }
    $productIdToInsert = $productId;
    $requestedNameToInsert = null;
} else {
    if ($productName === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter the product you want to request.']);
        exit;
    }
    $productIdToInsert = null;
    $requestedNameToInsert = $productName;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_requests (member_id, product_id, requested_product_name, quantity, pickup_date, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $memberId,
        $productIdToInsert,
        $requestedNameToInsert,
        $quantity,
        $pickupDate,
        $notes !== '' ? $notes : null,
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}