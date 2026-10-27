<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/../../../../config/funds.php';

requireRole('manager');
header('Content-Type: application/json');

// Set to true while fixing problems (shows the real database error).
// Set back to false when everything works.
const BUY_STOCK_DEBUG = true;

function fail($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$productId = trim((string) ($_POST['product_id'] ?? ''));
$itemName  = trim((string) ($_POST['item_name'] ?? ''));
$unit      = trim((string) ($_POST['unit'] ?? ''));
$quantity  = $_POST['quantity'] ?? '';
$totalCost = $_POST['total_cost'] ?? '';
$date      = $_POST['purchase_date'] ?? '';

if ($productId !== '' && !ctype_digit($productId)) {
    fail('Invalid product.');
}
if ($productId === '' && $itemName === '') {
    fail('Choose or type the item.');
}
if (mb_strlen($itemName) > 150) {
    fail('The item name is too long (150 letters at most).');
}
if ($productId === '' && $unit === '') {
    fail('Choose or type the unit.');
}
if (mb_strlen($unit) > 50) {
    fail('The unit is too long (50 letters at most).');
}
if (!is_numeric($quantity) || (float) $quantity <= 0) {
    fail('Enter a quantity greater than 0.');
}
if (!is_numeric($totalCost) || (float) $totalCost <= 0) {
    fail('Enter a total cost greater than 0.');
}

$quantity  = round((float) $quantity, 2);
$totalCost = round((float) $totalCost, 2);

if ($quantity > 99999999.99 || $totalCost > 99999999.99) {
    fail('That amount is too large.');
}

$d = DateTime::createFromFormat('Y-m-d', (string) $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    fail('Enter a valid date.');
}
if ($date > date('Y-m-d')) {
    fail('The date cannot be in the future.');
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    fail('Your session expired. Log in again.');
}

try {
    $pdo->beginTransaction();

    // Find the product (if there is one). Lock the row so two purchases
    // cannot overwrite each other.
    $product = null;
    if ($productId !== '') {
        $stmt = $pdo->prepare("SELECT id, name, unit, status FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            $pdo->rollBack();
            fail('Product not found.');
        }
    } else {
        $stmt = $pdo->prepare("SELECT id, name, unit, status FROM products WHERE LOWER(name) = LOWER(?) LIMIT 1 FOR UPDATE");
        $stmt->execute([$itemName]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($product) {
        if ($product['status'] === 'discontinued') {
            $pdo->rollBack();
            fail('This product is discontinued.');
        }
        // A product keeps its own unit, so the stock numbers stay correct
        if ($productId === '' && mb_strtolower($unit) !== mb_strtolower((string) $product['unit'])) {
            $pdo->rollBack();
            fail('"' . $product['name'] . '" is counted in ' . $product['unit'] . '. Choose that unit.');
        }
        $itemName = $product['name'];
        $unit = $product['unit'];
    }

    // Is there enough money?
    $funds = getAvailableFunds($pdo);
    if ($totalCost > $funds) {
        $pdo->rollBack();
        fail('Not enough funds. Available: ₱' . number_format(max(0, $funds), 2) . '.');
    }

    // Add the stock (only for a product); an out-of-stock product becomes available
    if ($product) {
        $stmt = $pdo->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity + ?,
                status = IF(status = 'out_of_stock', 'available', status)
            WHERE id = ?");
        $stmt->execute([$quantity, $product['id']]);
    }

    // Record the purchase
    $stmt = $pdo->prepare("
        INSERT INTO stock_purchases (product_id, item_name, unit, quantity, total_cost, purchase_date, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $product ? $product['id'] : null,
        $itemName, $unit, $quantity, $totalCost, $date, $userId
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'stock_added' => (bool) $product]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('buy-stock error: ' . $e->getMessage());
    if (BUY_STOCK_DEBUG) {
        fail('Error: ' . $e->getMessage());
    }
    fail('Database error. Did you run the stock_purchases SQL?');
}