<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$requestId    = $_POST['request_id'] ?? '';
$status       = trim($_POST['status'] ?? '');
$managerNote  = trim($_POST['manager_note'] ?? '');
$unitPriceRaw = trim((string) ($_POST['unit_price'] ?? ''));

$validStatuses = ['pending', 'under_review', 'approved', 'processing', 'ready_for_pickup', 'claimed', 'rejected'];

// Allowed changes: pending -> approved or rejected, approved -> ready for pickup or claimed,
// ready for pickup -> claimed. Claimed and rejected are final.
// (under_review acts like pending and processing acts like approved, for older records.)
$allowedMoves = [
    'pending'          => ['approved', 'rejected'],
    'under_review'     => ['approved', 'rejected'],
    'approved'         => ['ready_for_pickup', 'claimed'],
    'processing'       => ['ready_for_pickup', 'claimed'],
    'ready_for_pickup' => ['ready_for_pickup', 'claimed'],
];

// Statuses a request is in BEFORE it is approved. Stock is deducted only on this move.
$preApprovalStatuses = ['pending', 'under_review'];

if (!$requestId || !ctype_digit((string) $requestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request reference.']);
    exit;
}
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Price per unit is optional. If it is left empty, the saved price stays as it is.
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

// Rolls back the open transaction and sends an error to the page
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
    // Everything below happens in ONE transaction: if anything fails, nothing is changed.
    $pdo->beginTransaction();

    // FOR UPDATE locks the row, so two clicks / two managers cannot approve the same request twice.
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
    if ($status === 'approved' && ($unitPrice === null || $unitPrice <= 0) && $current['unit_price'] === null) {
        failAndExit($pdo, 'Enter the price per unit before approving.');
    }

    // ---- Deduct stock when the request is approved ----
    // Only on pending / under_review -> approved. Later moves (Ready for Pickup, Claimed)
    // never deduct again. Requests for a product the coop does not carry yet
    // (no product_id) have nothing to deduct.
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

            // When the stock reaches 0, mark the product Out of Stock
            // (a discontinued product keeps its status).
            if ($newStock <= 0 && $product['status'] === 'available') {
                $up = $pdo->prepare("UPDATE products SET stock_quantity = ?, status = 'out_of_stock' WHERE id = ?");
                $up->execute([$newStock, (int) $current['product_id']]);
            } else {
                $up = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $up->execute([$newStock, (int) $current['product_id']]);
            }
        }
    }

    // ---- Save the new status ----
    if ($unitPrice !== null) {
        $stmt = $pdo->prepare("
            UPDATE product_requests
            SET status = ?, manager_note = ?, unit_price = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $managerNote !== '' ? $managerNote : null, $unitPrice, $requestId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE product_requests
            SET status = ?, manager_note = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $managerNote !== '' ? $managerNote : null, $requestId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error. Did you add the unit_price column?']);
}