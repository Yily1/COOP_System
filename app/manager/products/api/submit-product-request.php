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
$notes       = trim($_POST['notes'] ?? '');
$requestedUnit = trim($_POST['requested_unit'] ?? '');

// The pickup date is no longer asked from the member. The manager sets it
// later, so it is saved as NULL when the request is created.

if ($quantity === '' || !is_numeric($quantity) || (float)$quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid quantity.']);
    exit;
}

if ($productId !== '' && ctype_digit((string)$productId)) {
    $stmt = $pdo->prepare("SELECT status, stock_quantity, price FROM products WHERE id = ?");
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
    // The product's price is saved with the request, so the amount stays the
    // same even if the product's price changes later. The manager can still change it.
    $unitPriceToInsert = (float) $product['price'];
    // A listed product already has its own unit
    $requestedUnit = '';
} else {
    if ($productName === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter the product you want to request.']);
        exit;
    }
    if ($requestedUnit === '') {
        echo json_encode(['success' => false, 'message' => 'Choose or type the unit.']);
        exit;
    }
    if (mb_strlen($requestedUnit) > 50) {
        echo json_encode(['success' => false, 'message' => 'The unit is too long (50 letters at most).']);
        exit;
    }
    $productIdToInsert = null;
    $requestedNameToInsert = $productName;
    // A product we don't carry yet has no price until the manager sets it
    $unitPriceToInsert = null;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_requests (member_id, product_id, requested_product_name, requested_unit, quantity, unit_price, pickup_date, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 'pending')
    ");
    $stmt->execute([
        $memberId,
        $productIdToInsert,
        $requestedNameToInsert,
        $requestedUnit !== '' ? $requestedUnit : null,
        $quantity,
        $unitPriceToInsert,
        $notes !== '' ? $notes : null,
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error. Did you add the unit_price column and make pickup_date nullable?']);
}