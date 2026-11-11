<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');

$products = getAllProducts($pdo);
$productRequests = getAllProductRequests($pdo);
$pendingRequestCount = count(array_filter($productRequests, fn($r) => in_array($r['status'], ['pending', 'under_review'], true)));
$stats = getProductPortfolioStats($pdo);

// Price lookup so each request can show the amount to pay (price x quantity)
$priceById = [];
$priceByName = [];
foreach ($products as $p) {
    $priceById[(int) $p['id']] = (float) $p['price'];
    $priceByName[mb_strtolower($p['name'])] = (float) $p['price'];
}

// Price saved on each request (set by the manager, or locked when the member requested)
$unitPriceByRequest = [];
$requestedUnitByRequest = [];
try {
    foreach ($pdo->query("SELECT id, unit_price, requested_unit FROM product_requests") as $row) {
        if ($row['unit_price'] !== null) {
            $unitPriceByRequest[(int) $row['id']] = (float) $row['unit_price'];
        }
        if (!empty($row['requested_unit'])) {
            $requestedUnitByRequest[(int) $row['id']] = $row['requested_unit'];
        }
    }
} catch (PDOException $e) {
    // The unit_price / requested_unit columns are not added yet.
}

// Price per unit of a request: its own saved price first, then the product's current price
function svcRequestUnitPrice($r, $unitPriceByRequest, $priceById, $priceByName) {
    $rid = (int) ($r['id'] ?? 0);
    if (isset($unitPriceByRequest[$rid])) {
        return $unitPriceByRequest[$rid];
    }
    if (!empty($r['product_id']) && isset($priceById[(int) $r['product_id']])) {
        return $priceById[(int) $r['product_id']];
    }
    $nm = mb_strtolower((string) ($r['product_name'] ?? $r['requested_product_name'] ?? ''));
    if ($nm !== '' && isset($priceByName[$nm])) {
        return $priceByName[$nm];
    }
    return null;
}

// Member avatar (same colors and initials logic as the Payments page)
if (!function_exists('svcSimpleHash')) {
    function svcSimpleHash($str) {
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash << 5) - $hash + ord($str[$i]);
            $hash = $hash & 0xFFFFFFFF;
            if ($hash > 0x7FFFFFFF) {
                $hash -= 0x100000000;
            }
        }
        return abs($hash);
    }
}
if (!function_exists('svcAvatarColor')) {
    function svcAvatarColor($seed) {
        $palette = [
            ['bg' => '#534AB7', 'text' => '#F4F3FE'],
            ['bg' => '#0F6E56', 'text' => '#E1F5EE'],
            ['bg' => '#993C1D', 'text' => '#FAECE7'],
            ['bg' => '#993556', 'text' => '#FBEAF0'],
            ['bg' => '#185FA5', 'text' => '#E6F1FB'],
            ['bg' => '#3B6D11', 'text' => '#EAF3DE'],
            ['bg' => '#854F0B', 'text' => '#FAEEDA'],
            ['bg' => '#791F1F', 'text' => '#FCEBEB'],
        ];
        return $palette[svcSimpleHash((string) $seed) % count($palette)];
    }
}
if (!function_exists('svcInitials')) {
    function svcInitials($firstName, $lastName) {
        return mb_strtoupper(mb_substr(trim($lastName), 0, 1) . mb_substr(trim($firstName), 0, 1));
    }
}

renderHeader('Products');
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* Inter font, same as the Equipment rental page (top part only) */
.svc-page-head, .svc-stat-grid {
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
}
.svc-page-head h1 { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; color: #222; font-size: 26px; font-weight: 600; }

/* Page header: same layout as the Payments page */
.svc-page-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; gap: 16px; position: relative; width: 100%;
}
.svc-page-head h1 { margin: 0; flex: 1 1 auto; min-width: 0; position: static !important; }

/* Button on the right: Add product */
.svc-head-actions { display: flex; align-items: center; gap: 10px; margin-left: auto; flex: 0 0 auto; }

/* Add product button: same size as "+ Add payment" */
.svc-add-btn {
    background: #3B6D11 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
    font-weight: 600 !important; color: #fff !important; border: none !important;
    padding: 11px 22px !important; border-radius: 10px !important; cursor: pointer;
    font-size: 14px !important; font-family: 'Inter', 'Segoe UI', Arial, sans-serif !important;
    display: inline-block !important; width: auto !important;
    max-width: 220px !important; flex: 0 0 auto !important; white-space: nowrap !important;
    position: static !important; top: auto !important; left: auto !important; right: auto !important;
    float: none !important; margin: 0 !important;
    text-transform: none !important; letter-spacing: normal !important;
}
.svc-add-btn:hover { background: #2e5a0d !important; }

/* Summary cards: same style and size as the Payments page */
.svc-stat-grid { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 28px; }
.svc-stat-card {
    border-radius: 12px; padding: 0 18px; min-width: 0; height: 92px; flex: 0 0 160px; width: 160px;
    box-sizing: border-box; box-shadow: none;
    display: flex; flex-direction: column; justify-content: center;
}
.svc-stat-card p.svc-stat-label { margin: 0 0 6px; font-size: 13px; }
.svc-stat-card p.svc-stat-value {
    margin: 0; font-size: 28px; font-weight: 700; line-height: 1.1;
    font-family: 'Fraunces', 'Times New Roman', Times, serif;
    font-variant-numeric: lining-nums tabular-nums;
    white-space: nowrap;
}
.svc-stat-total { background: #33502F; }
.svc-stat-total p { color: #fff; }
.svc-stat-available { background: #E1EFDE; }
.svc-stat-available .svc-stat-label { color: #3F7D52; }
.svc-stat-available .svc-stat-value { color: #2F6B43; }
.svc-stat-outofstock { background: #F6E1DC; }
.svc-stat-outofstock p { color: #B54A3C; }
.svc-stat-pending { background: #C1892B; }
.svc-stat-pending p { color: #3E2A07; }

.svc-section-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin: 28px 0 14px; }
.svc-section-head:first-of-type { margin-top: 0; }
.svc-section-head h2 { font-size: 17px; margin: 0; color: #2c2c2a; }

.svc-btn {
    display: inline-flex !important; align-items: center; gap: 6px; width: auto !important; flex: 0 0 auto !important;
    border-radius: 6px !important; padding: 9px 16px !important; font-size: 13px !important; font-weight: 600 !important;
    cursor: pointer !important; border: none !important; text-transform: none !important; letter-spacing: normal !important;
}
.svc-btn-primary { background: #3B6D11 !important; color: #fff !important; }
.svc-btn-primary:hover { background: #2e5a0d !important; }
.svc-btn-danger { background: #c62828 !important; color: #fff !important; }
.svc-btn-danger:hover { background: #a31f1f !important; }

/* ===== PRODUCT CARDS ===== */
.svc-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 14px;
    margin-bottom: 8px;
}
.svc-product-card {
    background: #fff; border: 1px solid #e2e0d5; border-radius: 12px; overflow: hidden;
    display: flex; flex-direction: column;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.svc-product-card:hover { border-color: #3B6D11; box-shadow: 0 2px 8px rgba(59, 109, 17, 0.12); }
.svc-product-photo {
    height: 140px; background: #f3f1e8; display: flex; align-items: center; justify-content: center; color: #b4b2a6;
}
.svc-product-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.svc-product-photo .material-icons { font-size: 38px; }
.svc-product-body { padding: 14px 14px 12px; flex: 1; }
.svc-product-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin: 0 0 3px; }
.svc-product-name { font-weight: 600; font-size: 15px; margin: 0; color: #2c2c2a; overflow-wrap: anywhere; }
.svc-product-cat { font-size: 12px; color: #6b7280; margin: 0 0 12px; }
.svc-product-price-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.svc-product-price { font-size: 17px; font-weight: 600; color: #2c2c2a; }
.svc-product-stock { font-size: 12px; color: #6b7280; }
.svc-product-stock.is-out { color: #c62828; }
.svc-product-footer { display: flex; gap: 8px; padding: 10px 14px; border-top: 1px solid #f0f0ea; }

.svc-status-pill { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 20px; white-space: nowrap; flex: none; }
.svc-status-available { background: #e8f5e9; color: #2e7d32; }
.svc-status-out { background: #fdecea; color: #c62828; }
.svc-status-other { background: #eee; color: #666; }

.svc-action-cell { display: flex; gap: 6px; flex-wrap: nowrap; }
.svc-mini-btn {
    display: inline-flex !important; align-items: center; justify-content: center; gap: 4px;
    background: #fff !important; color: #444 !important; border: 1px solid #d8d2c4 !important; border-radius: 6px !important;
    padding: 5px 10px !important; font-size: 12px !important; font-weight: 600 !important; cursor: pointer !important;
    width: auto !important; white-space: nowrap !important;
}
.svc-mini-btn-reject { color: #c62828 !important; }
.svc-mini-btn-approve { background: #3B6D11 !important; color: #fff !important; border-color: #3B6D11 !important; }
.svc-mini-btn-approve:hover { background: #2e5a0d !important; }

.svc-table-wrap { overflow-x: auto; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; background: #fff; }
.svc-table { width: 100%; border-collapse: collapse; }
.svc-table th { text-align: left; padding: 10px 12px; background: #fff; border-bottom: 1px solid #eee; color: #222; font-weight: 700; white-space: nowrap; }
.svc-table td { padding: 10px 12px; border-top: 1px solid #eee; color: #222; vertical-align: middle; }
.svc-table tbody tr:first-child td { border-top: none; }
.svc-member-cell { display: flex; align-items: center; gap: 10px; }
.svc-avatar { width: 32px; height: 32px; min-width: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; }
.svc-member-name { margin: 0; font-weight: 600; }
.svc-member-id { margin: 0; font-size: 12px; color: #888; }
.svc-muted { color: #666; }
.svc-empty { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }

/* Green flash for a new or changed item (auto-update) */
@keyframes svcFlashCard {
    from { background: #e3f4e1; border-color: #2e7d32; }
    to   { background: #fff;    border-color: #e2e0d5; }
}
@keyframes svcFlashRow {
    from { background: #e3f4e1; }
    to   { background: transparent; }
}
.svc-product-card.svc-flash { animation: svcFlashCard 2.2s ease-out; }
.svc-table tr.svc-flash td { animation: svcFlashRow 2.2s ease-out; }

#svcModalBackdrop, #svcRequestModalBackdrop, #svcApproveModalBackdrop, #svcRejectModalBackdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5);
    z-index: 100; align-items: center; justify-content: center; padding: 16px;
}
.svc-modal-box { background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; box-sizing: border-box; position: relative; max-height: 90vh; overflow-y: auto; }
.svc-form-label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
.svc-form-input { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px; font-size: 14px; font-family: inherit; }
#svcModalErrors, #svcRequestModalErrors, #svcApproveErrors, #svcRejectErrors { display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
.svc-close-btn {
    position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important;
    font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    color: #333 !important; line-height: 1 !important;
}
.svc-modal-summary { font-size: 13px; color: #667066; text-align: center; margin: -8px 0 16px; }

/* Photo upload (Add / Edit product) */
.svc-photo-preview { display: none; width: 100%; height: 120px; border-radius: 8px; overflow: hidden; margin-bottom: 10px; }
.svc-photo-preview.svc-show { display: block; }
.svc-photo-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.svc-photo-drop {
    border: 1.5px dashed #c9c5b4; border-radius: 8px; padding: 14px 12px; text-align: center; cursor: pointer;
    background: #faf9f4; margin-bottom: 14px;
}
.svc-photo-drop:hover { border-color: #3B6D11; }
.svc-photo-drop .material-icons { color: #6b7280; font-size: 22px; }
.svc-photo-drop p { font-size: 12.5px; color: #6b7280; margin: 4px 0 0; word-break: break-all; }

@media (max-width: 700px) {
    .svc-table thead { display: none; }
    .svc-table, .svc-table tbody, .svc-table tr, .svc-table td { display: block; width: 100%; }
    .svc-table tr { border-bottom: 1px solid #f0f0ea; padding: 10px 0; }
    .svc-table tbody tr:last-child { border-bottom: none; }
    .svc-table td { border-top: none !important; border-bottom: none !important; padding: 4px 14px; }
    .svc-table td::before {
        content: attr(data-label); display: block; font-size: 11px; font-weight: 600;
        color: #6b7280; text-transform: uppercase; margin-bottom: 2px;
    }
}

@media (max-width: 560px) {
    .svc-page-head { flex-wrap: wrap; }
    .svc-head-actions { width: 100%; justify-content: flex-end; }
    .svc-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .svc-product-photo { height: 100px; }
    .svc-product-body { padding: 10px 10px 8px; }
    .svc-product-top { flex-direction: column; gap: 4px; }
    .svc-product-name { font-size: 13.5px; }
    .svc-product-cat { margin-bottom: 8px; }
    .svc-product-price { font-size: 15px; }
    .svc-product-footer { padding: 8px 10px; gap: 6px; }
}
</style>

<div class="svc-page-head">
    <h1>Products</h1>
    <div class="svc-head-actions">
        <button type="button" class="svc-add-btn" id="openAddProductBtn">+ Add product</button>
    </div>
</div>

<div class="svc-stat-grid" id="svcStatLive">
    <div class="svc-stat-card svc-stat-total">
        <p class="svc-stat-label">Total Products</p>
        <p class="svc-stat-value"><?php echo $stats['total']; ?></p>
    </div>
    <div class="svc-stat-card svc-stat-available">
        <p class="svc-stat-label">Available</p>
        <p class="svc-stat-value"><?php echo $stats['available']; ?></p>
    </div>
    <div class="svc-stat-card svc-stat-outofstock">
        <p class="svc-stat-label">Out of Stock</p>
        <p class="svc-stat-value"><?php echo $stats['out_of_stock']; ?></p>
    </div>
    <div class="svc-stat-card svc-stat-pending">
        <p class="svc-stat-label">Pending Requests</p>
        <p class="svc-stat-value"><?php echo $stats['pending_requests']; ?></p>
    </div>
</div>

<!-- ============================================================
     PRODUCTS (cards with photos)
     ============================================================ -->
<div class="svc-section-head">
    <h2>Products</h2>
</div>
<div id="svcProductsLive">
<?php if (empty($products)): ?>
    <p class="svc-empty">No products added yet.</p>
<?php else: ?>
    <div class="svc-product-grid" id="productGrid">
        <?php foreach ($products as $p): ?>
            <?php
                $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                $hasPhoto = !empty($p['photo_path']);
                $photoUrl = $hasPhoto ? BASE_URL . '/' . ltrim($p['photo_path'], '/') : '';
                if ($p['status'] === 'available') {
                    $pillClass = 'svc-status-available';
                } elseif ($p['status'] === 'out_of_stock') {
                    $pillClass = 'svc-status-out';
                } else {
                    $pillClass = 'svc-status-other';
                }
            ?>
            <div class="svc-product-card" data-id="<?php echo (int) $p['id']; ?>" data-product='<?php echo $pJson; ?>'>
                <div class="svc-product-photo">
                    <?php if ($hasPhoto): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                    <?php else: ?>
                        <span class="material-icons">inventory_2</span>
                    <?php endif; ?>
                </div>
                <div class="svc-product-body">
                    <div class="svc-product-top">
                        <p class="svc-product-name"><?php echo htmlspecialchars($p['name']); ?></p>
                        <span class="svc-status-pill <?php echo $pillClass; ?>">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $p['status']))); ?>
                        </span>
                    </div>
                    <p class="svc-product-cat"><?php echo htmlspecialchars($p['category']); ?></p>
                    <div class="svc-product-price-row">
                        <span class="svc-product-price">₱<?php echo number_format($p['price'], 2); ?> / <?php echo htmlspecialchars($p['unit']); ?></span>
                        <span class="svc-product-stock <?php echo ((float)$p['stock_quantity'] <= 0) ? 'is-out' : ''; ?>">
                            <?php echo number_format($p['stock_quantity'], 0); ?> in stock
                        </span>
                    </div>
                </div>
                <div class="svc-product-footer">
                    <button type="button" class="svc-mini-btn edit-product-btn">Edit</button>
                    <button type="button" class="svc-mini-btn svc-mini-btn-reject delete-product-btn">Delete</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<!-- ============================================================
     PRODUCT REQUESTS (stays a table)
     Action by status:
       pending / under review  -> Approve and Reject
       approved / processing / ready for pickup -> Update
       claimed / rejected      -> nothing (finished)
     ============================================================ -->
<div class="svc-section-head">
    <h2>List of Avail Products and Product Requests</h2>
</div>
<div id="svcRequestsLive">
<?php if (empty($productRequests)): ?>
    <p class="svc-empty">No product requests yet.</p>
<?php else: ?>
    <div class="svc-table-wrap">
        <table class="svc-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Amount</th>
                    <th>Pickup</th>
                    <th>Notes</th>
                    <th>Manager Note</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productRequests as $r): ?>
                    <?php
                        $productLabel = $r['product_name'] ?? ($r['requested_product_name'] . ' (not yet carried)');
                        $reqUnit = $r['product_unit'] ?? ($requestedUnitByRequest[(int) $r['id']] ?? '');
                        $avatar = svcAvatarColor($r['membership_id']);
                        $initials = svcInitials($r['first_name'], $r['last_name']);
                        $reqQtyText = rtrim(rtrim(number_format((float) $r['quantity'], 2, '.', ''), '0'), '.');
                        $reqSummary = ($r['product_name'] ?? $r['requested_product_name']) . ' · ' . trim($reqQtyText . ' ' . $reqUnit) . ' · ' . $r['last_name'] . ', ' . $r['first_name'];

                        // Pickup date: empty / 0000-00-00 means the manager has not set it yet
                        $hasPickup = !empty($r['pickup_date']) && $r['pickup_date'] !== '0000-00-00';
                        $pickupTs = $hasPickup ? strtotime($r['pickup_date']) : false;
                        $pickupValue = $pickupTs ? date('Y-m-d', $pickupTs) : '';
                    ?>
                    <tr data-request-id="<?php echo (int) $r['id']; ?>">
                        <td data-label="Member">
                            <div class="svc-member-cell">
                                <div class="svc-avatar" style="background: <?php echo $avatar['bg']; ?>; color: <?php echo $avatar['text']; ?>;"><?php echo htmlspecialchars($initials); ?></div>
                                <div>
                                    <p class="svc-member-name"><?php echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></p>
                                    <p class="svc-member-id"><?php echo htmlspecialchars($r['membership_id']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td data-label="Product"><?php echo htmlspecialchars($productLabel); ?></td>
                        <td data-label="Qty"><?php echo htmlspecialchars(rtrim(rtrim(number_format($r['quantity'], 2), '0'), '.')); ?> <?php echo htmlspecialchars($reqUnit); ?></td>
                        <td data-label="Amount" style="white-space:nowrap; font-weight:600;">
                            <?php
                                $unitPrice = svcRequestUnitPrice($r, $unitPriceByRequest, $priceById, $priceByName);
                                $amount = $unitPrice === null ? null : round($unitPrice * (float) $r['quantity'], 2);
                            ?>
                            <?php if ($amount === null): ?>
                                <span class="svc-muted" style="font-weight:400;" title="Click Approve to set the price">-</span>
                            <?php else: ?>
                                ₱<?php echo number_format($amount, 2); ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Pickup">
                            <?php if ($pickupTs): ?>
                                <?php echo date('M d, Y', $pickupTs); ?>
                            <?php else: ?>
                                <span class="svc-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Notes" class="svc-muted"><?php echo htmlspecialchars($r['notes'] ?: '-'); ?></td>
                        <td data-label="Manager Note" class="svc-muted"><?php echo htmlspecialchars($r['manager_note'] ?: '-'); ?></td>
                        <td data-label="Status"><?php echo productRequestStatusBadge($r['status']); ?></td>
                        <td data-label="Action">
                            <?php if (in_array($r['status'], ['pending', 'under_review'], true)): ?>
                                <div class="svc-action-cell">
                                    <button type="button" class="svc-mini-btn svc-mini-btn-approve approve-request-btn"
                                            data-id="<?php echo (int) $r['id']; ?>"
                                            data-summary="<?php echo htmlspecialchars($reqSummary); ?>"
                                            data-qty="<?php echo (float) $r['quantity']; ?>"
                                            data-unit="<?php echo htmlspecialchars($reqUnit); ?>"
                                            data-price="<?php echo $unitPrice === null ? '' : $unitPrice; ?>">
                                        Approve
                                    </button>
                                    <button type="button" class="svc-mini-btn svc-mini-btn-reject reject-request-btn"
                                            data-id="<?php echo (int) $r['id']; ?>"
                                            data-summary="<?php echo htmlspecialchars($reqSummary); ?>">
                                        Reject
                                    </button>
                                </div>
                            <?php elseif (in_array($r['status'], ['approved', 'processing', 'ready_for_pickup'], true)): ?>
                                <button type="button" class="svc-mini-btn update-request-btn"
                                        data-id="<?php echo (int) $r['id']; ?>"
                                        data-summary="<?php echo htmlspecialchars($reqSummary); ?>"
                                        data-status="<?php echo htmlspecialchars($r['status']); ?>"
                                        data-pickup="<?php echo htmlspecialchars($pickupValue); ?>"
                                        data-note="<?php echo htmlspecialchars($r['manager_note'] ?? ''); ?>">
                                    Update
                                </button>
                            <?php else: ?>
                                <span class="svc-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<!-- ============================================================
     MODAL - Add / Edit Product
     ============================================================ -->
<div id="svcModalBackdrop">
    <div class="svc-modal-box">
        <h2 id="svcModalTitle" style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Add product</h2>
        <button type="button" id="closeSvcModalBtn" class="svc-close-btn">&times;</button>
        <div id="svcModalErrors"></div>
        <form id="svcProductForm" enctype="multipart/form-data">
            <input type="hidden" name="id" id="svcProductId">

            <label class="svc-form-label">Photo (optional)</label>
            <div class="svc-photo-preview" id="svcPhotoPreview">
                <img id="svcPhotoPreviewImg" src="" alt="Preview">
            </div>
            <div class="svc-photo-drop" id="svcPhotoDrop" role="button" tabindex="0">
                <span class="material-icons">add_a_photo</span>
                <p id="svcPhotoDropText">Click to upload a photo</p>
            </div>
            <input type="file" name="photo" id="svcPhotoInput" accept="image/png,image/jpeg,image/webp" style="display:none;">

            <label class="svc-form-label">Product name</label>
            <input type="text" name="name" id="svcProductName" class="svc-form-input" required>

            <label class="svc-form-label">Category</label>
            <select name="category" id="svcProductCategory" class="svc-form-input" required>
                <option value="">-- Select category --</option>
                <option value="Grains">Grains</option>
                <option value="Vegetables">Vegetables</option>
                <option value="Meat & Poultry">Meat & Poultry</option>
                <option value="Farm Inputs">Farm Inputs</option>
                <option value="Others">Others</option>
            </select>

            <div style="display:flex; gap:12px;">
                <div style="flex:1;">
                    <label class="svc-form-label">Price (₱)</label>
                    <input type="number" name="price" id="svcProductPrice" class="svc-form-input" step="0.01" min="0" required>
                </div>
                <div style="flex:1;">
                    <label class="svc-form-label">Unit</label>
                    <input type="text" name="unit" id="svcProductUnit" class="svc-form-input" placeholder="Kg, Sack, Pc" required>
                </div>
            </div>

            <label class="svc-form-label">Stock quantity</label>
            <input type="number" name="stock_quantity" id="svcProductStock" class="svc-form-input" step="0.01" min="0" required>

            <label class="svc-form-label">Status</label>
            <select name="status" id="svcProductStatus" class="svc-form-input">
                <option value="auto">Automatic (based on stock)</option>
                <option value="discontinued">Discontinued</option>
            </select>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center; margin-top:6px;">Save product</button>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL - Approve request (sets the price per unit and pickup date)
     ============================================================ -->
<div id="svcApproveModalBackdrop">
    <div class="svc-modal-box">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Approve request</h2>
        <button type="button" id="closeSvcApproveModalBtn" class="svc-close-btn">&times;</button>
        <div class="svc-modal-summary" id="svcApproveSummary"></div>
        <div id="svcApproveErrors"></div>
        <form id="svcApproveForm">
            <input type="hidden" name="request_id" id="svcApproveId">
            <input type="hidden" name="status" value="approved">

            <label class="svc-form-label" id="svcApprovePriceLabel" for="svcApprovePrice">Price per unit (₱)</label>
            <input type="number" name="unit_price" id="svcApprovePrice" class="svc-form-input" step="0.01" min="0.01" placeholder="e.g. 280" style="margin-bottom:8px;" required>
            <div id="svcApproveTotal" style="display:none; background:#f1f6ea; color:#3B6D11; border-radius:6px; padding:8px 10px; margin-bottom:14px; font-size:13px;">
                Amount to pay: <strong id="svcApproveTotalValue">₱0.00</strong>
            </div>
            <div style="height:6px;"></div>

            <label class="svc-form-label" for="svcApprovePickup">Pickup date (optional)</label>
            <input type="date" name="pickup_date" id="svcApprovePickup" class="svc-form-input">

            <label class="svc-form-label">Manager note (optional)</label>
            <textarea name="manager_note" id="svcApproveNote" rows="3" class="svc-form-input" placeholder="e.g. Ready Friday"></textarea>

            <button type="submit" class="svc-btn svc-btn-primary" id="svcApproveSubmit" style="width:100%; justify-content:center;">Approve</button>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL - Reject request
     ============================================================ -->
<div id="svcRejectModalBackdrop">
    <div class="svc-modal-box">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Reject request</h2>
        <button type="button" id="closeSvcRejectModalBtn" class="svc-close-btn">&times;</button>
        <div class="svc-modal-summary" id="svcRejectSummary"></div>
        <div id="svcRejectErrors"></div>
        <form id="svcRejectForm">
            <input type="hidden" name="request_id" id="svcRejectId">
            <input type="hidden" name="status" value="rejected">

            <label class="svc-form-label">Reason (optional)</label>
            <textarea name="manager_note" id="svcRejectNote" rows="3" class="svc-form-input" placeholder="e.g. Out of stock"></textarea>

            <button type="submit" class="svc-btn svc-btn-danger" id="svcRejectSubmit" style="width:100%; justify-content:center;">Reject</button>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL - Update request (after it was approved)
     ============================================================ -->
<div id="svcRequestModalBackdrop">
    <div class="svc-modal-box">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Update Request</h2>
        <button type="button" id="closeSvcRequestModalBtn" class="svc-close-btn">&times;</button>
        <div class="svc-modal-summary" id="svcRequestSummary"></div>
        <div id="svcRequestModalErrors"></div>
        <form id="svcRequestForm">
            <input type="hidden" name="request_id" id="svcRequestId">

            <label class="svc-form-label">Status</label>
            <select name="status" id="svcRequestStatus" class="svc-form-input">
                <option value="ready_for_pickup">Ready for Pickup</option>
                <option value="claimed">Claimed</option>
            </select>

            <label class="svc-form-label" for="svcRequestPickup">Pickup date</label>
            <input type="date" name="pickup_date" id="svcRequestPickup" class="svc-form-input">

            <label class="svc-form-label">Manager note (optional)</label>
            <textarea name="manager_note" id="svcRequestNote" rows="3" class="svc-form-input" placeholder="e.g. Ready Friday"></textarea>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center;">Save</button>
        </form>
    </div>
</div>

<script>
(function() {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/products/api/';
    const SITE_BASE = '<?php echo BASE_URL; ?>/';
    const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    /* ---------- Product modal (add / edit) ---------- */
    const productBackdrop = document.getElementById('svcModalBackdrop');
    const productForm = document.getElementById('svcProductForm');
    const productErrors = document.getElementById('svcModalErrors');
    const productModalTitle = document.getElementById('svcModalTitle');

    const photoInput = document.getElementById('svcPhotoInput');
    const photoDrop = document.getElementById('svcPhotoDrop');
    const photoDropText = document.getElementById('svcPhotoDropText');
    const photoPreview = document.getElementById('svcPhotoPreview');
    const photoPreviewImg = document.getElementById('svcPhotoPreviewImg');

    function resetPhotoUI(existingPath) {
        photoInput.value = '';
        if (existingPath) {
            photoPreviewImg.src = SITE_BASE + existingPath.replace(/^\/+/, '');
            photoPreview.classList.add('svc-show');
            photoDropText.textContent = 'Click to change photo';
        } else {
            photoPreviewImg.src = '';
            photoPreview.classList.remove('svc-show');
            photoDropText.textContent = 'Click to upload a photo';
        }
    }

    photoDrop.addEventListener('click', function() { photoInput.click(); });
    photoDrop.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); photoInput.click(); }
    });
    photoInput.addEventListener('change', function() {
        const file = photoInput.files && photoInput.files[0];
        if (!file) return;
        if (!/^image\/(png|jpeg|webp)$/.test(file.type)) {
            photoInput.value = '';
            productErrors.textContent = 'Photo must be a PNG, JPG or WEBP image.';
            productErrors.style.display = 'block';
            return;
        }
        if (file.size > MAX_PHOTO_BYTES) {
            photoInput.value = '';
            productErrors.textContent = 'Photo must be 5MB or smaller.';
            productErrors.style.display = 'block';
            return;
        }
        productErrors.style.display = 'none';
        const reader = new FileReader();
        reader.onload = function(e) {
            photoPreviewImg.src = e.target.result;
            photoPreview.classList.add('svc-show');
            photoDropText.textContent = file.name;
        };
        reader.readAsDataURL(file);
    });

    function openAddProduct() {
        productForm.reset();
        document.getElementById('svcProductId').value = '';
        resetPhotoUI('');
        productModalTitle.textContent = 'Add product';
        productErrors.style.display = 'none';
        productBackdrop.style.display = 'flex';
    }
    function openEditProduct(p) {
        productForm.reset();
        document.getElementById('svcProductId').value = p.id;
        document.getElementById('svcProductName').value = p.name;
        document.getElementById('svcProductCategory').value = p.category;
        document.getElementById('svcProductPrice').value = p.price;
        document.getElementById('svcProductUnit').value = p.unit;
        document.getElementById('svcProductStock').value = p.stock_quantity;
        document.getElementById('svcProductStatus').value = (p.status === 'discontinued') ? 'discontinued' : 'auto';
        resetPhotoUI(p.photo_path || '');
        productModalTitle.textContent = 'Edit product';
        productErrors.style.display = 'none';
        productBackdrop.style.display = 'flex';
    }
    document.getElementById('openAddProductBtn').addEventListener('click', openAddProduct);
    document.getElementById('closeSvcModalBtn').addEventListener('click', function() { productBackdrop.style.display = 'none'; });
    productBackdrop.addEventListener('click', function(e) { if (e.target === productBackdrop) productBackdrop.style.display = 'none'; });

    // Event delegation: gumagana pa rin ang Edit / Delete kahit napalitan ang mga card ng auto-update
    const productsBox = document.getElementById('svcProductsLive');
    productsBox.addEventListener('click', async function(e) {
        const editBtn = e.target.closest('.edit-product-btn');
        if (editBtn) {
            const card = editBtn.closest('.svc-product-card');
            openEditProduct(JSON.parse(card.dataset.product));
            return;
        }

        const delBtn = e.target.closest('.delete-product-btn');
        if (delBtn) {
            const card = delBtn.closest('.svc-product-card');
            const p = JSON.parse(card.dataset.product);
            if (!confirm('Delete "' + p.name + '"? This cannot be undone.')) return;
            try {
                const formData = new FormData();
                formData.append('id', p.id);
                const res = await fetch(AJAX_BASE + 'delete-product.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not delete product.');
                }
            } catch (err) {
                alert('Could not connect to server.');
            }
        }
    });

    productForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        productErrors.style.display = 'none';
        const isEdit = !!document.getElementById('svcProductId').value;
        const endpoint = isEdit ? 'update-product.php' : 'add-product.php';
        try {
            const formData = new FormData(productForm);
            const res = await fetch(AJAX_BASE + endpoint, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                productErrors.textContent = data.message || 'Could not save product.';
                productErrors.style.display = 'block';
            }
        } catch (err) {
            productErrors.textContent = 'Could not connect to server.';
            productErrors.style.display = 'block';
        }
    });

    /* ---------- Product requests: Approve / Reject / Update ---------- */
    const STATUS_ENDPOINT = AJAX_BASE + 'update-product-request-status.php';

    // Sends one of the three request forms and reloads on success
    async function submitRequestForm(form, errorsEl, submitBtn) {
        errorsEl.style.display = 'none';
        submitBtn.disabled = true;
        try {
            const res = await fetch(STATUS_ENDPOINT, { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                errorsEl.textContent = data.message || 'Could not update request.';
                errorsEl.style.display = 'block';
            }
        } catch (err) {
            errorsEl.textContent = 'Could not connect to server.';
            errorsEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
        }
    }

    function wireClose(backdropId, closeBtnId) {
        const backdrop = document.getElementById(backdropId);
        document.getElementById(closeBtnId).addEventListener('click', function() { backdrop.style.display = 'none'; });
        backdrop.addEventListener('click', function(e) { if (e.target === backdrop) backdrop.style.display = 'none'; });
        return backdrop;
    }

    /* Approve: price per unit, amount to pay, pickup date, optional note */
    const approveBackdrop = wireClose('svcApproveModalBackdrop', 'closeSvcApproveModalBtn');
    const approveForm = document.getElementById('svcApproveForm');
    const approveErrors = document.getElementById('svcApproveErrors');
    const approvePrice = document.getElementById('svcApprovePrice');
    const approveTotalBox = document.getElementById('svcApproveTotal');
    const approveTotalValue = document.getElementById('svcApproveTotalValue');
    let approveQty = 0;

    function updateApproveTotal() {
        const price = parseFloat(approvePrice.value);
        if (price > 0 && approveQty > 0) {
            approveTotalValue.textContent = '₱' + (price * approveQty).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            approveTotalBox.style.display = 'block';
        } else {
            approveTotalBox.style.display = 'none';
        }
    }
    approvePrice.addEventListener('input', updateApproveTotal);

    function openApprove(btn) {
        document.getElementById('svcApproveId').value = btn.dataset.id;
        document.getElementById('svcApproveSummary').textContent = btn.dataset.summary || '';
        document.getElementById('svcApproveNote').value = '';
        document.getElementById('svcApprovePickup').value = '';
        approveQty = parseFloat(btn.dataset.qty) || 0;
        approvePrice.value = btn.dataset.price || '';
        document.getElementById('svcApprovePriceLabel').textContent =
            btn.dataset.unit ? 'Price per ' + btn.dataset.unit + ' (₱)' : 'Price per unit (₱)';
        updateApproveTotal();
        approveErrors.style.display = 'none';
        approveBackdrop.style.display = 'flex';
    }
    approveForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitRequestForm(approveForm, approveErrors, document.getElementById('svcApproveSubmit'));
    });

    /* Reject: optional reason */
    const rejectBackdrop = wireClose('svcRejectModalBackdrop', 'closeSvcRejectModalBtn');
    const rejectForm = document.getElementById('svcRejectForm');
    const rejectErrors = document.getElementById('svcRejectErrors');

    function openReject(btn) {
        document.getElementById('svcRejectId').value = btn.dataset.id;
        document.getElementById('svcRejectSummary').textContent = btn.dataset.summary || '';
        document.getElementById('svcRejectNote').value = '';
        rejectErrors.style.display = 'none';
        rejectBackdrop.style.display = 'flex';
    }
    rejectForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitRequestForm(rejectForm, rejectErrors, document.getElementById('svcRejectSubmit'));
    });

    /* Update: Ready for Pickup or Claimed, plus pickup date */
    const requestBackdrop = wireClose('svcRequestModalBackdrop', 'closeSvcRequestModalBtn');
    const requestForm = document.getElementById('svcRequestForm');
    const requestErrors = document.getElementById('svcRequestModalErrors');

    function openUpdate(btn) {
        document.getElementById('svcRequestId').value = btn.dataset.id;
        document.getElementById('svcRequestSummary').textContent = btn.dataset.summary || '';
        // Approved (or the older Processing) starts at Ready for Pickup; Ready for Pickup moves to Claimed
        document.getElementById('svcRequestStatus').value = (btn.dataset.status === 'ready_for_pickup') ? 'claimed' : 'ready_for_pickup';
        const pk = btn.dataset.pickup || '';
        document.getElementById('svcRequestPickup').value = (pk && pk !== '0000-00-00') ? pk : '';
        document.getElementById('svcRequestNote').value = btn.dataset.note || '';
        requestErrors.style.display = 'none';
        requestBackdrop.style.display = 'flex';
    }
    requestForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitRequestForm(requestForm, requestErrors, requestForm.querySelector('button[type="submit"]'));
    });

    // Event delegation: gumagana pa rin ang Approve / Reject / Update kahit napalitan ang table
    const requestsBox = document.getElementById('svcRequestsLive');
    requestsBox.addEventListener('click', function(e) {
        const approveBtn = e.target.closest('.approve-request-btn');
        if (approveBtn) { openApprove(approveBtn); return; }
        const rejectBtn = e.target.closest('.reject-request-btn');
        if (rejectBtn) { openReject(rejectBtn); return; }
        const updateBtn = e.target.closest('.update-request-btn');
        if (updateBtn) { openUpdate(updateBtn); }
    });

    // ============================================================
    // AUTO-UPDATE (AJAX POLLING)
    // ============================================================
    const LIVE_CHECK_MS = 3000;   // gawing 5000 o 10000 para mas kaunti ang requests
    const MODAL_IDS = ['svcModalBackdrop', 'svcRequestModalBackdrop', 'svcApproveModalBackdrop', 'svcRejectModalBackdrop'];

    // Mapa ng bawat item (id -> HTML) para malaman kung alin ang bago o nagbago
    function itemMap(root, sel, attr) {
        const m = new Map();
        root.querySelectorAll(sel).forEach(el => m.set(el.getAttribute(attr), el.outerHTML));
        return m;
    }

    const liveParts = [
        { id: 'svcStatLive',     sel: null,                  attr: null },
        { id: 'svcProductsLive', sel: '.svc-product-card',   attr: 'data-id' },
        { id: 'svcRequestsLive', sel: 'tr[data-request-id]', attr: 'data-request-id' }
    ].map(function(p) {
        p.el = document.getElementById(p.id);
        p.html = p.el.innerHTML;
        p.items = p.sel ? itemMap(p.el, p.sel, p.attr) : null;
        return p;
    });

    let busy = false;
    let fails = 0;
    let timer = null;

    function anyModalOpen() {
        return MODAL_IDS.some(id => document.getElementById(id).style.display === 'flex');
    }

    async function liveCheck() {
        // Hindi gagana habang nakatago ang tab, may nakabukas na modal, o may pinoproseso
        if (document.hidden || busy || anyModalOpen()) return;
        busy = true;
        try {
            const r = await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            const fresh = liveParts.map(p => doc.getElementById(p.id));

            // Kung wala ang inaasahang elemento (hal. login page na), huwag galawin ang page
            if (fresh.every(Boolean)) {
                liveParts.forEach(function(p, i) {
                    const f = fresh[i];
                    if (f.innerHTML === p.html) return;   // walang nagbago
                    p.html = f.innerHTML;
                    const newItems = p.sel ? itemMap(f, p.sel, p.attr) : null;
                    p.el.innerHTML = p.html;
                    if (p.sel) {
                        p.el.querySelectorAll(p.sel).forEach(function(el) {
                            const id = el.getAttribute(p.attr);
                            if (p.items.get(id) !== newItems.get(id)) el.classList.add('svc-flash');
                        });
                        p.items = newItems;
                    }
                });
            }
            fails = 0;
        } catch (e) {
            fails++;   // backoff kapag nag-error
        } finally {
            busy = false;
        }
    }

    function schedule() {
        clearTimeout(timer);
        const delay = Math.min(LIVE_CHECK_MS * Math.pow(2, fails), 60000);
        timer = setTimeout(async function() { await liveCheck(); schedule(); }, delay);
    }
    document.addEventListener('visibilitychange', function() { if (!document.hidden) liveCheck(); });
    schedule();
})();
</script>

<?php renderFooter(); ?>