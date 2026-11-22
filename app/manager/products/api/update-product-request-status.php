<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$requestId    = $_POST['request_id'] ?? '';
$status       = trim($_POST['status'] ?? '');
$managerNote  = trim($_POST['manager_note'] ?? '');
$unitPriceRaw = trim((string) ($_POST['unit_price'] ?? ''));
$pickupRaw    = trim((string) ($_POST['pickup_date'] ?? ''));

$validStatuses = ['pending', 'under_review', 'approved', 'processing', 'ready_for_pickup', 'claimed', 'rejected'];

$allowedMoves = [
    'pending'          => ['approved', 'rejected'],
    'under_review'     => ['approved', 'rejected'],
    'approved'         => ['ready_for_pickup', 'claimed'],
    'processing'       => ['ready_for_pickup', 'claimed'],
    'ready_for_pickup' => ['ready_for_pickup', 'claimed'],
];

$preApprovalStatuses = ['pending', 'under_review'];

if (!$requestId || !ctype_digit((string) $requestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request reference.']);
    exit;
}
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

$unitPrice = null;
if ($unitPriceRaw !== '') {
    if (!is_numeric($unitPriceRaw) || (float) $unitPriceRaw < 0) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid price per unit.']);
        exit;
    }
    $unitPrice = round((float) $unitPriceRaw, 2);
    if ($unitPrice > 99999999.99) {
        echo json_encode(['success' => false, 'message' => 'That price is too large.']);
        exit;
    }
}

$pickupDate = null;
if ($pickupRaw !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $pickupRaw);
    if (!$d || $d->format('Y-m-d') !== $pickupRaw) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid pickup date.']);
        exit;
    }
    $pickupDate = $pickupRaw;
}

function failAndExit(PDO $pdo, string $message): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function fmtQty($n): string {
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

try {
    $pdo->beginTransaction();

    $cur = $pdo->prepare("SELECT status, unit_price, product_id, quantity FROM product_requests WHERE id = ? FOR UPDATE");
    $cur->execute([$requestId]);
    $current = $cur->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        failAndExit($pdo, 'Request not found.');
    }
    if (!isset($allowedMoves[$current['status']])) {
        failAndExit($pdo, 'This request is already finished and cannot be changed.');
    }
    if (!in_array($status, $allowedMoves[$current['status']], true)) {
        failAndExit($pdo, 'That status change is not allowed.');
    }

    // Listed product: the price is locked. The price typed in the form is ignored.
    // The request keeps the price it already has; if it has none, the product's
    // current price is saved when the request is approved.
    // A product we don't carry yet (no product_id): the manager's price is used.
    if (!empty($current['product_id'])) {
        $unitPrice = null;
        if ($status === 'approved' && $current['unit_price'] === null) {
            $pp = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $pp->execute([(int) $current['product_id']]);
            $listedPrice = $pp->fetchColumn();
            if ($listedPrice !== false) {
                $unitPrice = round((float) $listedPrice, 2);
            }
        }
    }

    if ($status === 'approved' && ($unitPrice === null || $unitPrice <= 0) && $current['unit_price'] === null) {
        failAndExit($pdo, 'Enter the price per unit before approving.');
    }

    if ($status === 'approved'
        && in_array($current['status'], $preApprovalStatuses, true)
        && !empty($current['product_id'])) {

        $qty = (float) $current['quantity'];

        $ps = $pdo->prepare("SELECT name, unit, stock_quantity, status FROM products WHERE id = ? FOR UPDATE");
        $ps->execute([(int) $current['product_id']]);
        $product = $ps->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $stock = (float) $product['stock_quantity'];

            if ($qty > $stock) {
                failAndExit(
                    $pdo,
                    'Not enough stock. Only ' . fmtQty($stock) . ' ' . $product['unit'] .
                    ' of ' . $product['name'] . ' left, but the request is for ' . fmtQty($qty) . '.'
                );
            }

            $newStock = round($stock - $qty, 2);

            if ($newStock <= 0 && $product['status'] === 'available') {
                $up = $pdo->prepare("UPDATE products SET stock_quantity = ?, status = 'out_of_stock' WHERE id = ?");
                $up->execute([$newStock, (int) $current['product_id']]);
            } else {
                $up = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $up->execute([$newStock, (int) $current['product_id']]);
            }
        }
    }

    $sets   = ['status = ?'];
    $values = [$status];

    if (array_key_exists('manager_note', $_POST)) {
        $sets[]   = 'manager_note = ?';
        $values[] = $managerNote !== '' ? $managerNote : null;
    }

    if ($unitPrice !== null) {
        $sets[]   = 'unit_price = ?';
        $values[] = $unitPrice;
    }
    if ($pickupDate !== null) {
        $sets[]   = 'pickup_date = ?';
        $values[] = $pickupDate;
    }
    $values[] = $requestId;

    $stmt = $pdo->prepare("UPDATE product_requests SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->execute($values);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error. Did you add the unit_price and pickup_date columns?']);
}