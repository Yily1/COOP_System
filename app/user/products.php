<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$memberId = $stmt->fetchColumn();

if (!$memberId) {
    renderHeader('Products');
    echo '<p style="color:#666;">No member profile is linked to your account yet.</p>';
    renderFooter();
    exit;
}

$products = getAllProducts($pdo);
$myProductRequests = getMemberProductRequests($pdo, $memberId);

// Price lookup so each request can show the amount to pay (price x quantity)
$priceById = [];
$priceByName = [];
foreach ($products as $p) {
    $priceById[(int) $p['id']] = (float) $p['price'];
    $priceByName[mb_strtolower($p['name'])] = (float) $p['price'];
}

// Price saved on each of this member's requests (set by the manager, or locked when requested)
$unitPriceByRequest = [];
$requestedUnitByRequest = [];
try {
    $stmt = $pdo->prepare("SELECT id, unit_price, requested_unit FROM product_requests WHERE member_id = ?");
    $stmt->execute([$memberId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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

// Amount to pay for a request: its own saved price first, then the product's current price
if (!function_exists('svcRequestAmount')) {
    function svcRequestAmount($r, $unitPriceByRequest, $priceById, $priceByName) {
        $price = null;
        $rid = (int) ($r['id'] ?? 0);
        if (isset($unitPriceByRequest[$rid])) {
            $price = $unitPriceByRequest[$rid];
        } elseif (!empty($r['product_id']) && isset($priceById[(int) $r['product_id']])) {
            $price = $priceById[(int) $r['product_id']];
        } else {
            $nm = mb_strtolower((string) ($r['product_name'] ?? $r['requested_product_name'] ?? ''));
            if ($nm !== '' && isset($priceByName[$nm])) {
                $price = $priceByName[$nm];
            }
        }
        return $price === null ? null : round($price * (float) $r['quantity'], 2);
    }
}

renderHeader('Products');
?>

<style>
.page-shell {
    width: 100%;
    max-width: 780px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #eae6fb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-icon svg {
    width: 22px;
    height: 22px;
    stroke: #4b2f9c;
}

.page-title {
    margin: 0;
    color: #1b3a24;
    font-size: 19px;
    font-weight: 600;
}

.page-sub {
    margin: 2px 0 0 0;
    color: #667066;
    font-size: 13.5px;
}

/* Back to dashboard (round arrow, right side of the header row).
   An <a> link, not a <button>, so the site-wide button rules can't touch it. */
.page-back {
    margin-left: auto;
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #3B6D11;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    color: #3B6D11 !important;
    text-decoration: none !important;
    box-shadow: none;
    transition: background 0.12s ease;
}

.page-back:hover {
    background: #eef5e4;
}

.page-back svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
}

.svc-section-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin: 26px 0 14px; }
.svc-section-head:first-of-type { margin-top: 0; }
.svc-section-head h2 { font-size: 16px; margin: 0; color: #1b3a24; font-weight: 600; }

.svc-btn {
    display: inline-flex !important; align-items: center; gap: 6px; width: auto !important; flex: 0 0 auto !important;
    border-radius: 10px !important; padding: 9px 16px !important; font-size: 13px !important; font-weight: 600 !important;
    cursor: pointer !important; border: none !important; text-transform: none !important; letter-spacing: normal !important;
}
.svc-btn-outline { background: #fff !important; color: #1b3a24 !important; border: 1px solid #eceae4 !important; }
.svc-btn-primary { background: #2e7d32 !important; color: #fff !important; }

/* ===== AVAILABLE PRODUCTS (photo cards) ===== */
.svc-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 12px;
    margin-bottom: 8px;
}
.svc-product-card {
    background: #fff; border: 1px solid #eceae4; border-radius: 14px; overflow: hidden;
    display: flex; flex-direction: column;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.svc-product-card:hover { border-color: #cfe8d1; box-shadow: 0 2px 8px rgba(46, 125, 50, 0.08); }
.svc-product-photo {
    height: 110px; background: #f7f8f5; display: flex; align-items: center; justify-content: center; color: #a9a79f;
}
.svc-product-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.svc-product-photo .material-icons { font-size: 34px; }
.svc-product-body { padding: 10px 14px 8px; flex: 1; }
.svc-product-name { font-weight: 600; font-size: 15px; margin: 0 0 2px; color: #1b3a24; overflow-wrap: anywhere; }
.svc-product-cat { font-size: 12px; color: #667066; margin: 0 0 6px; }
.svc-product-price { font-size: 16px; font-weight: 600; color: #1b3a24; margin: 0 0 2px; }
.svc-product-stock { font-size: 12px; color: #2e7d32; margin: 0; font-weight: 500; }
.svc-product-stock.is-out { color: #a6322f; }
.svc-product-footer { padding: 0 14px 12px; }

.svc-request-btn {
    display: inline-flex !important; width: auto !important; align-items: center; justify-content: center;
    background: #2e7d32 !important; color: #fff !important; border: none !important; border-radius: 8px !important;
    padding: 7px 12px !important; font-size: 12px !important; font-weight: 600 !important; cursor: pointer !important; white-space: nowrap !important;
}
.svc-request-btn.svc-request-btn-full { width: 100% !important; padding: 8px 12px !important; font-size: 13px !important; }
.svc-request-btn:disabled { background: #cbd5c8 !important; cursor: not-allowed !important; }

.svc-empty { padding: 20px; text-align: center; color: #667066; font-size: 13px; }

/* My Requests: a proper card design (name + status up top, quantity /
   amount / pickup date side by side, manager note called out at the bottom)
   instead of a plain stacked label/value list. */
.svc-request-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.svc-request-card { background: #fff; border: 1px solid #eceae4; border-radius: 14px; padding: 14px 16px; }
.svc-request-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.svc-request-card-name { font-weight: 600; font-size: 14px; color: #1b3a24; margin: 0; }
.svc-request-card-meta { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-bottom: 8px; }
.svc-request-card-meta div p:first-child { font-size: 11px; color: #a9a79f; text-transform: uppercase; margin: 0 0 2px; }
.svc-request-card-meta div p:last-child { font-size: 13px; color: #1b3a24; margin: 0; font-weight: 500; }
.svc-request-card-meta .svc-amount-value { font-weight: 600 !important; color: #2e7d32 !important; }
.svc-request-card-meta .svc-amount-none { font-weight: 400 !important; color: #a9a79f !important; }
.svc-request-card-note { font-size: 12.5px; color: #667066; background: #f7f8f5; border-radius: 8px; padding: 8px 10px; margin-top: 4px; }
.svc-request-card-note strong { color: #1b3a24; }

#svcProductReqModalBackdrop, #svcNewProductModalBackdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5);
    z-index: 100; align-items: center; justify-content: center; padding: 16px;
}
.svc-modal-box { background: #fff; border-radius: 14px; padding: 24px; width: 100%; max-width: 420px; box-sizing: border-box; position: relative; max-height: 90vh; overflow-y: auto; }
.svc-form-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066; }
.svc-form-input { width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-size: 14px; font-family: inherit; }
.svc-modal-errors, .svc-modal-success { display: none; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; }
.svc-modal-errors { background: #fbe6e6; color: #a6322f; }
.svc-modal-success { background: #e8f5e9; color: #2e7d32; }
.svc-close-btn {
    position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important;
    font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    color: #667066 !important; line-height: 1 !important;
}

@media (max-width: 700px) {
    .svc-request-grid { grid-template-columns: 1fr; }
}

@media (max-width: 560px) {
    .svc-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .svc-product-photo { height: 80px; }
    .svc-product-photo .material-icons { font-size: 26px; }
    .svc-product-body { padding: 8px 10px 6px; }
    .svc-product-name { font-size: 13px; }
    .svc-product-cat { margin-bottom: 4px; font-size: 11px; }
    .svc-product-price { font-size: 14px; margin-bottom: 2px; }
    .svc-product-stock { font-size: 11px; }
    .svc-product-footer { padding: 0 10px 8px; }
    .svc-request-btn.svc-request-btn-full { padding: 7px 10px !important; font-size: 12px !important; }
}
</style>

<div class="page-shell">

    <div class="page-header">
        <div class="page-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        </div>
        <div>
            <p class="page-title">Products</p>
            <p class="page-sub">Browse and request coop products</p>
        </div>

        <!-- Back to dashboard: adjust the href if your user dashboard file is named differently -->
        <a href="<?php echo BASE_URL; ?>/app/user/dashboard.php" class="page-back" aria-label="Back to dashboard" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
    </div>

    <!-- ============================================================
         AVAILABLE PRODUCTS (photo cards)
         ============================================================ -->
    <div class="svc-section-head">
        <h2>Available Products</h2>
    </div>

    <?php if (empty($products)): ?>
        <p class="svc-empty">No products available right now.</p>
    <?php else: ?>
        <div class="svc-product-grid">
            <?php foreach ($products as $p): ?>
                <?php
                    $isAvailable = $p['status'] === 'available' && (float)$p['stock_quantity'] > 0;
                    $hasPhoto = !empty($p['photo_path']);
                    $photoUrl = $hasPhoto ? BASE_URL . '/' . ltrim($p['photo_path'], '/') : '';
                ?>
                <div class="svc-product-card">
                    <div class="svc-product-photo">
                        <?php if ($hasPhoto): ?>
                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                        <?php else: ?>
                            <span class="material-icons">inventory_2</span>
                        <?php endif; ?>
                    </div>
                    <div class="svc-product-body">
                        <p class="svc-product-name"><?php echo htmlspecialchars($p['name']); ?></p>
                        <p class="svc-product-cat"><?php echo htmlspecialchars($p['category']); ?></p>
                        <p class="svc-product-price">₱<?php echo number_format($p['price'], 2); ?> / <?php echo htmlspecialchars($p['unit']); ?></p>
                        <p class="svc-product-stock <?php echo $isAvailable ? '' : 'is-out'; ?>">
                            <?php echo $isAvailable ? number_format($p['stock_quantity'], 0) . ' ' . htmlspecialchars($p['unit']) . ' available' : 'Out of stock'; ?>
                        </p>
                    </div>
                    <div class="svc-product-footer">
                        <button type="button" class="svc-request-btn svc-request-btn-full request-product-btn"
                                data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                data-unit="<?php echo htmlspecialchars($p['unit']); ?>" data-stock="<?php echo $p['stock_quantity']; ?>"
                                data-price="<?php echo (float) $p['price']; ?>"
                                <?php echo $isAvailable ? '' : 'disabled'; ?>>
                            <?php echo $isAvailable ? 'Avail' : 'Unavailable'; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         MY REQUESTS
         ============================================================ -->
    <div class="svc-section-head">
        <h2>My Requests</h2>
        <button type="button" class="svc-btn svc-btn-outline" id="openNewProductBtn">+ Request a Product</button>
    </div>
    <?php if (empty($myProductRequests)): ?>
        <p class="svc-empty">You haven't requested any products yet.</p>
    <?php else: ?>
        <div class="svc-request-grid">
            <?php foreach ($myProductRequests as $r): ?>
                <?php
                    $productLabel = $r['product_name'] ?? ($r['requested_product_name'] . ' (not yet carried)');
                    $reqUnit = $r['product_unit'] ?? ($requestedUnitByRequest[(int) $r['id']] ?? '');
                    $qtyDisplay = rtrim(rtrim(number_format($r['quantity'], 2), '0'), '.') . ' ' . $reqUnit;
                    $amount = svcRequestAmount($r, $unitPriceByRequest, $priceById, $priceByName);

                    // Pickup date is set by the manager, so it may still be empty
                    $pd = $r['pickup_date'] ?? '';
                    $hasPickup = !empty($pd) && strpos($pd, '0000') !== 0;
                ?>
                <div class="svc-request-card">
                    <div class="svc-request-card-head">
                        <p class="svc-request-card-name"><?php echo htmlspecialchars($productLabel); ?></p>
                        <?php echo productRequestStatusBadge($r['status']); ?>
                    </div>
                    <div class="svc-request-card-meta">
                        <div>
                            <p>Quantity</p>
                            <p><?php echo htmlspecialchars($qtyDisplay); ?></p>
                        </div>
                        <div>
                            <p>Amount to pay</p>
                            <?php if ($amount === null): ?>
                                <p class="svc-amount-none" title="The manager will set the price">Waiting for price</p>
                            <?php else: ?>
                                <p class="svc-amount-value">₱<?php echo number_format($amount, 2); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p>Pickup Date</p>
                            <?php if ($hasPickup): ?>
                                <p><?php echo date('M j, Y', strtotime($pd)); ?></p>
                            <?php else: ?>
                                <p class="svc-amount-none" title="The manager will set the pickup date">Waiting for manager</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($r['manager_note'])): ?>
                        <div class="svc-request-card-note"><strong>Manager note:</strong> <?php echo htmlspecialchars($r['manager_note']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================================
     MODAL - Request Product (listed product)
     ============================================================ -->
<div id="svcProductReqModalBackdrop">
    <div class="svc-modal-box">
        <h2 id="productReqTitle" style="margin: 0 0 16px 0; font-size: 18px; text-align: center; color: #1b3a24;">Request Product</h2>
        <button type="button" id="closeProductReqModalBtn" class="svc-close-btn">&times;</button>
        <div id="productReqErrors" class="svc-modal-errors"></div>
        <div id="productReqSuccess" class="svc-modal-success">Request submitted. The manager will review it shortly.</div>

        <form id="productReqForm">
            <input type="hidden" name="product_id" id="productReqProductId">

            <label class="svc-form-label" id="productReqQtyLabel">Quantity</label>
            <input type="number" name="quantity" id="productReqQuantity" class="svc-form-input" step="0.01" min="0.01" required>

            <label class="svc-form-label">Notes (optional)</label>
            <textarea name="notes" rows="2" class="svc-form-input" placeholder="e.g. for our farm's corn harvest"></textarea>

            <div id="productReqTotal" style="display:none; background:#f7f8f5; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:13px; color:#667066;">
                Amount to pay: <strong id="productReqTotalValue" style="color:#2e7d32; font-size:15px;">₱0.00</strong>
            </div>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center;">Submit Request</button>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL - Request a Product we don't carry yet
     ============================================================ -->
<div id="svcNewProductModalBackdrop">
    <div class="svc-modal-box">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center; color: #1b3a24;">Request a Product</h2>
        <button type="button" id="closeNewProductModalBtn" class="svc-close-btn">&times;</button>
        <div id="newProductErrors" class="svc-modal-errors"></div>
        <div id="newProductSuccess" class="svc-modal-success">Request submitted. The manager will review it shortly.</div>

        <form id="newProductForm">
            <label class="svc-form-label">Product name</label>
            <input type="text" name="requested_product_name" class="svc-form-input" placeholder="e.g. Rice, Fertilizer" required>

            <div style="display:flex; gap:12px;">
                <div style="flex:1; min-width:0;">
                    <label class="svc-form-label">Quantity</label>
                    <input type="number" name="quantity" class="svc-form-input" step="0.01" min="0.01" required>
                </div>
                <div style="flex:1; min-width:0;">
                    <label class="svc-form-label" for="newProductUnitSel">Unit</label>
                    <select id="newProductUnitSel" class="svc-form-input">
                        <option value="Kg">Kg</option>
                        <option value="Sack">Sack</option>
                        <option value="Pc">Pc</option>
                        <option value="Liter">Liter</option>
                        <option value="Bag">Bag</option>
                        <option value="__other">Others (type the unit)</option>
                    </select>
                    <input type="text" id="newProductUnitOther" class="svc-form-input" maxlength="50"
                           placeholder="Type the unit (Esc to go back)" style="display:none;">
                    <input type="hidden" name="requested_unit" id="newProductUnitValue" value="Kg">
                </div>
            </div>

            <label class="svc-form-label">Notes (optional)</label>
            <textarea name="notes" rows="2" class="svc-form-input" placeholder="e.g. 50kg preferred, any brand"></textarea>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center;">Submit Request</button>
        </form>
    </div>
</div>

<script>
(function() {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/products/api/';

    /* ---------- Request listed product modal ---------- */
    const productReqBackdrop = document.getElementById('svcProductReqModalBackdrop');
    const productReqForm = document.getElementById('productReqForm');
    const productReqErrors = document.getElementById('productReqErrors');
    const productReqSuccess = document.getElementById('productReqSuccess');
    const productReqQty = document.getElementById('productReqQuantity');
    const productReqTotal = document.getElementById('productReqTotal');
    const productReqTotalValue = document.getElementById('productReqTotalValue');
    let currentPrice = 0;

    // Shows the amount to pay while the member types the quantity
    function updateReqTotal() {
        const qty = parseFloat(productReqQty.value);
        if (currentPrice > 0 && qty > 0) {
            const total = qty * currentPrice;
            productReqTotalValue.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            productReqTotal.style.display = 'block';
        } else {
            productReqTotal.style.display = 'none';
        }
    }
    productReqQty.addEventListener('input', updateReqTotal);

    document.querySelectorAll('.request-product-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            productReqForm.reset();
            productReqErrors.style.display = 'none';
            productReqSuccess.style.display = 'none';
            currentPrice = parseFloat(btn.dataset.price) || 0;
            updateReqTotal();
            document.getElementById('productReqProductId').value = btn.dataset.id;
            document.getElementById('productReqTitle').textContent = 'Request: ' + btn.dataset.name;
            document.getElementById('productReqQtyLabel').textContent = 'Quantity (' + btn.dataset.unit + ')';
            productReqQty.max = btn.dataset.stock;
            productReqBackdrop.style.display = 'flex';
        });
    });
    document.getElementById('closeProductReqModalBtn').addEventListener('click', function() { productReqBackdrop.style.display = 'none'; });
    productReqBackdrop.addEventListener('click', function(e) { if (e.target === productReqBackdrop) productReqBackdrop.style.display = 'none'; });

    productReqForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        productReqErrors.style.display = 'none';
        try {
            const formData = new FormData(productReqForm);
            const res = await fetch(AJAX_BASE + 'submit-product-request.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                productReqSuccess.style.display = 'block';
                setTimeout(() => { window.location.reload(); }, 1000);
            } else {
                productReqErrors.textContent = data.message || 'Could not submit request.';
                productReqErrors.style.display = 'block';
            }
        } catch (err) {
            productReqErrors.textContent = 'Could not connect to server.';
            productReqErrors.style.display = 'block';
        }
    });

    /* ---------- Request a product we don't carry yet ---------- */
    const newProductBackdrop = document.getElementById('svcNewProductModalBackdrop');
    const newProductForm = document.getElementById('newProductForm');
    const newProductErrors = document.getElementById('newProductErrors');
    const newProductSuccess = document.getElementById('newProductSuccess');

    // Unit: choosing "Others" turns the dropdown into a typing field, same place
    const unitSel = document.getElementById('newProductUnitSel');
    const unitOther = document.getElementById('newProductUnitOther');
    const unitValue = document.getElementById('newProductUnitValue');

    function syncUnit() {
        const typing = (unitSel.value === '__other');
        unitSel.style.display = typing ? 'none' : '';
        unitOther.style.display = typing ? 'block' : 'none';
        unitValue.value = typing ? unitOther.value.trim() : unitSel.value;
    }
    function unitBackToList() {
        unitOther.value = '';
        unitSel.selectedIndex = 0;
        syncUnit();
    }
    unitSel.addEventListener('change', function() {
        syncUnit();
        if (unitSel.value === '__other') unitOther.focus();
    });
    unitOther.addEventListener('input', syncUnit);
    unitOther.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || (e.key === 'Backspace' && !e.repeat && unitOther.value === '')) {
            e.preventDefault();
            e.stopPropagation();
            unitBackToList();
            unitSel.focus();
        }
    });

    document.getElementById('openNewProductBtn').addEventListener('click', function() {
        newProductForm.reset();
        unitOther.value = '';
        syncUnit();
        newProductErrors.style.display = 'none';
        newProductSuccess.style.display = 'none';
        newProductBackdrop.style.display = 'flex';
    });
    document.getElementById('closeNewProductModalBtn').addEventListener('click', function() { newProductBackdrop.style.display = 'none'; });
    newProductBackdrop.addEventListener('click', function(e) { if (e.target === newProductBackdrop) newProductBackdrop.style.display = 'none'; });

    newProductForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        newProductErrors.style.display = 'none';
        syncUnit();
        if (!unitValue.value) {
            newProductErrors.textContent = 'Type the unit.';
            newProductErrors.style.display = 'block';
            return;
        }
        try {
            const formData = new FormData(newProductForm);
            const res = await fetch(AJAX_BASE + 'submit-product-request.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                newProductSuccess.style.display = 'block';
                setTimeout(() => { window.location.reload(); }, 1000);
            } else {
                newProductErrors.textContent = data.message || 'Could not submit request.';
                newProductErrors.style.display = 'block';
            }
        } catch (err) {
            newProductErrors.textContent = 'Could not connect to server.';
            newProductErrors.style.display = 'block';
        }
    });
})();
</script>

<?php renderFooter(); ?>