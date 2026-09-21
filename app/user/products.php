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

renderHeader('Products');
?>

<style>
.svc-page-head {
    display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    margin-bottom: 20px; padding-bottom: 18px; border-bottom: 2px solid #E4DCC8;
}
.svc-page-head h1 { font-size: 24px; color: #2c2c2a; margin: 0 0 4px; font-weight: 600; }
.svc-page-head p { font-size: 13px; color: #6b7280; margin: 0; }

.svc-section-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin: 28px 0 14px; }
.svc-section-head:first-of-type { margin-top: 0; }
.svc-section-head h2 { font-size: 17px; margin: 0; color: #2c2c2a; }

.svc-btn {
    display: inline-flex !important; align-items: center; gap: 6px; width: auto !important; flex: 0 0 auto !important;
    border-radius: 6px !important; padding: 9px 16px !important; font-size: 13px !important; font-weight: 600 !important;
    cursor: pointer !important; border: none !important; text-transform: none !important; letter-spacing: normal !important;
}
.svc-btn-outline { background: #fff !important; color: #444 !important; border: 1px solid #d8d2c4 !important; }
.svc-btn-primary { background: #3B6D11 !important; color: #fff !important; }

.svc-product-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 8px; }
@media (max-width: 560px) { .svc-product-grid { grid-template-columns: 1fr; } }
.svc-product-card { background: #fff; border: 1px solid #e2e0d5; border-radius: 10px; overflow: hidden; }
.svc-product-photo { height: 110px; background: #f3f1e8; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 12px; }
.svc-product-photo img { width: 100%; height: 100%; object-fit: cover; }
.svc-product-body { padding: 12px 14px; }
.svc-product-name { font-weight: 600; font-size: 14px; margin: 0 0 4px; }
.svc-product-meta { font-size: 12.5px; color: #6b7280; margin: 0 0 10px; }

.svc-request-btn {
    display: inline-flex !important; width: auto !important; align-items: center; justify-content: center;
    background: #3B6D11 !important; color: #fff !important; border: none !important; border-radius: 6px !important;
    padding: 7px 12px !important; font-size: 12px !important; font-weight: 600 !important; cursor: pointer !important; white-space: nowrap !important;
}
.svc-request-btn:disabled { background: #cbd5c8 !important; cursor: not-allowed !important; }

.svc-table-wrap { overflow-x: auto; border: 1px solid #e2e0d5; border-radius: 10px; margin-bottom: 8px; }
.svc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.svc-table th { text-align: left; padding: 12px 14px; background: #fff; border-bottom: 1px solid #e2e0d5; color: #6b7280; font-weight: 600; }
.svc-table td { padding: 12px 14px; border-bottom: 1px solid #f0f0ea; color: #2c2c2a; vertical-align: top; }
.svc-table tbody tr:last-child td { border-bottom: none; }
.svc-empty { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }

/* Available Products: real <table> on wider screens, a plain div-based
   card grid on narrow screens (CSS Grid inside <table>/<tbody> renders
   inconsistently across mobile browsers, so we avoid that entirely by
   using two separate markup blocks toggled with display, not one). */
.svc-desktop-only { display: block; }
.svc-mobile-only { display: none; }

.svc-mobile-product-card {
    background: #fff; border: 1px solid #e2e0d5; border-radius: 8px; padding: 10px 12px;
}
.svc-mobile-product-name { font-weight: 600; font-size: 12.5px; margin: 0 0 4px; color: #2c2c2a; }
.svc-mobile-product-meta { font-size: 11px; color: #6b7280; margin: 0 0 8px; line-height: 1.5; }

/* My Requests: a proper card design (name + status up top, quantity /
   pickup date side by side, manager note called out at the bottom)
   instead of a plain stacked label/value list. */
.svc-request-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.svc-request-card { background: #fff; border: 1px solid #e2e0d5; border-radius: 10px; padding: 14px 16px; }
.svc-request-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.svc-request-card-name { font-weight: 600; font-size: 14px; color: #2c2c2a; margin: 0; }
.svc-request-card-meta { display: flex; gap: 20px; margin-bottom: 8px; }
.svc-request-card-meta div p:first-child { font-size: 11px; color: #9ca3af; text-transform: uppercase; margin: 0 0 2px; }
.svc-request-card-meta div p:last-child { font-size: 13px; color: #2c2c2a; margin: 0; font-weight: 500; }
.svc-request-card-note { font-size: 12.5px; color: #6b7280; background: #f7f6f0; border-radius: 6px; padding: 8px 10px; margin-top: 4px; }
.svc-request-card-note strong { color: #2c2c2a; }

#svcProductReqModalBackdrop, #svcNewProductModalBackdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5);
    z-index: 100; align-items: center; justify-content: center; padding: 16px;
}
.svc-modal-box { background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; box-sizing: border-box; position: relative; max-height: 90vh; overflow-y: auto; }
.svc-form-label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
.svc-form-input { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px; font-size: 14px; font-family: inherit; }
.svc-modal-errors, .svc-modal-success { display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
.svc-modal-errors { background: #f8d7da; color: #721c24; }
.svc-modal-success { background: #d4edda; color: #155724; }
.svc-close-btn {
    position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important;
    font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    color: #333 !important; line-height: 1 !important;
}

@media (max-width: 700px) {
    .svc-desktop-only { display: none; }
    .svc-mobile-only { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .svc-request-grid { grid-template-columns: 1fr; }
}
@media (max-width: 380px) {
    .svc-mobile-only { grid-template-columns: 1fr; }
}
</style>

<div class="svc-page-head">
    <div>
        <h1>Products</h1>
        <p>Browse cooperative products and track your requests.</p>
    </div>
</div>

<!-- ============================================================
     AVAILABLE PRODUCTS
     ============================================================ -->
<div class="svc-section-head">
    <h2>Available Products</h2>
</div>

<?php if (empty($products)): ?>
    <p class="svc-empty">No products available right now.</p>
<?php else: ?>
    <div class="svc-table-wrap svc-desktop-only">
        <table class="svc-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price / Unit</th>
                    <th>Availability</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <?php $isAvailable = $p['status'] === 'available' && (float)$p['stock_quantity'] > 0; ?>
                    <tr>
                        <td data-label="Product" style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td data-label="Price / Unit">₱<?php echo number_format($p['price'], 2); ?> / <?php echo htmlspecialchars($p['unit']); ?></td>
                        <td data-label="Availability"><?php echo $isAvailable ? number_format($p['stock_quantity'], 0) . ' ' . htmlspecialchars($p['unit']) . ' available' : 'Out of stock'; ?></td>
                        <td data-label="Action">
                            <button type="button" class="svc-request-btn request-product-btn"
                                    data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-unit="<?php echo htmlspecialchars($p['unit']); ?>" data-stock="<?php echo $p['stock_quantity']; ?>"
                                    <?php echo $isAvailable ? '' : 'disabled'; ?>>
                                <?php echo $isAvailable ? 'Avail' : 'Unavailable'; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="svc-mobile-only">
        <?php foreach ($products as $p): ?>
            <?php $isAvailable = $p['status'] === 'available' && (float)$p['stock_quantity'] > 0; ?>
            <div class="svc-mobile-product-card">
                <p class="svc-mobile-product-name"><?php echo htmlspecialchars($p['name']); ?></p>
                <p class="svc-mobile-product-meta">
                    ₱<?php echo number_format($p['price'], 2); ?> / <?php echo htmlspecialchars($p['unit']); ?><br>
                    <?php echo $isAvailable ? number_format($p['stock_quantity'], 0) . ' ' . htmlspecialchars($p['unit']) . ' avail.' : 'Out of stock'; ?>
                </p>
                <button type="button" class="svc-request-btn request-product-btn" style="width:100%;"
                        data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"
                        data-unit="<?php echo htmlspecialchars($p['unit']); ?>" data-stock="<?php echo $p['stock_quantity']; ?>"
                        <?php echo $isAvailable ? '' : 'disabled'; ?>>
                    <?php echo $isAvailable ? 'Avail' : 'Unavailable'; ?>
                </button>
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
                $qtyDisplay = rtrim(rtrim(number_format($r['quantity'], 2), '0'), '.') . ' ' . ($r['product_unit'] ?? '');
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
                        <p>Pickup Date</p>
                        <p><?php echo date('M j, Y', strtotime($r['pickup_date'])); ?></p>
                    </div>
                </div>
                <?php if (!empty($r['manager_note'])): ?>
                    <div class="svc-request-card-note"><strong>Manager note:</strong> <?php echo htmlspecialchars($r['manager_note']); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============================================================
     MODAL - Request Product (listed product)
     ============================================================ -->
<div id="svcProductReqModalBackdrop">
    <div class="svc-modal-box">
        <h2 id="productReqTitle" style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Request Product</h2>
        <button type="button" id="closeProductReqModalBtn" class="svc-close-btn">&times;</button>
        <div id="productReqErrors" class="svc-modal-errors"></div>
        <div id="productReqSuccess" class="svc-modal-success">Request submitted. The manager will review it shortly.</div>

        <form id="productReqForm">
            <input type="hidden" name="product_id" id="productReqProductId">

            <label class="svc-form-label" id="productReqQtyLabel">Quantity</label>
            <input type="number" name="quantity" id="productReqQuantity" class="svc-form-input" step="0.01" min="0.01" required>

            <label class="svc-form-label">Pickup date</label>
            <input type="date" name="pickup_date" id="productReqPickupDate" class="svc-form-input" required>

            <label class="svc-form-label">Notes (optional)</label>
            <textarea name="notes" rows="2" class="svc-form-input" placeholder="e.g. for our farm's corn harvest"></textarea>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center;">Submit Request</button>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL - Request a Product we don't carry yet
     ============================================================ -->
<div id="svcNewProductModalBackdrop">
    <div class="svc-modal-box">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Request a Product</h2>
        <button type="button" id="closeNewProductModalBtn" class="svc-close-btn">&times;</button>
        <div id="newProductErrors" class="svc-modal-errors"></div>
        <div id="newProductSuccess" class="svc-modal-success">Request submitted. The manager will review it shortly.</div>

        <form id="newProductForm">
            <label class="svc-form-label">Product name</label>
            <input type="text" name="requested_product_name" class="svc-form-input" placeholder="e.g. Rice, Fertilizer" required>

            <label class="svc-form-label">Quantity</label>
            <input type="number" name="quantity" class="svc-form-input" step="0.01" min="0.01" required>

            <label class="svc-form-label">Pickup date</label>
            <input type="date" name="pickup_date" class="svc-form-input" required>

            <label class="svc-form-label">Notes (optional)</label>
            <textarea name="notes" rows="2" class="svc-form-input" placeholder="e.g. 50kg preferred, any brand"></textarea>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center;">Submit Request</button>
        </form>
    </div>
</div>

<script>
(function() {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/products/ajax/';
    const today = new Date().toISOString().slice(0, 10);

    /* ---------- Request listed product modal ---------- */
    const productReqBackdrop = document.getElementById('svcProductReqModalBackdrop');
    const productReqForm = document.getElementById('productReqForm');
    const productReqErrors = document.getElementById('productReqErrors');
    const productReqSuccess = document.getElementById('productReqSuccess');

    document.querySelectorAll('.request-product-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            productReqForm.reset();
            productReqErrors.style.display = 'none';
            productReqSuccess.style.display = 'none';
            document.getElementById('productReqProductId').value = btn.dataset.id;
            document.getElementById('productReqTitle').textContent = 'Request: ' + btn.dataset.name;
            document.getElementById('productReqQtyLabel').textContent = 'Quantity (' + btn.dataset.unit + ')';
            document.getElementById('productReqQuantity').max = btn.dataset.stock;
            document.getElementById('productReqPickupDate').min = today;
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

    document.getElementById('openNewProductBtn').addEventListener('click', function() {
        newProductForm.reset();
        newProductErrors.style.display = 'none';
        newProductSuccess.style.display = 'none';
        newProductBackdrop.style.display = 'flex';
    });
    document.getElementById('closeNewProductModalBtn').addEventListener('click', function() { newProductBackdrop.style.display = 'none'; });
    newProductBackdrop.addEventListener('click', function(e) { if (e.target === newProductBackdrop) newProductBackdrop.style.display = 'none'; });

    newProductForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        newProductErrors.style.display = 'none';
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