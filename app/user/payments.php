<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/payment-functions.php';
require_once '../../config/rental-split.php';

requireRole('user');

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        m.id AS member_db_id,
        m.membership_id,
        m.last_name,
        m.first_name,
        m.membership_type
    FROM users u
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$myProfile = $stmt->fetch(PDO::FETCH_ASSOC);

$memberDbId = null;
$eligibility = null;
$paymentHistory = [];

if ($myProfile && !empty($myProfile['member_db_id'])) {
    $memberDbId = $myProfile['member_db_id'];
    $eligibility = getMemberEligibility($pdo, $memberDbId);
    $paymentHistory = getPaymentHistory($pdo, $memberDbId);
}

// Member rate per hectare / per sack from the Equipment page (for the auto-amount)
$memberRates = [];
foreach (rentalEquipmentRates($pdo) as $key => $r) {
    $memberRates[$key] = $r['member'];
}

renderHeader('Payments');
?>

<style>
.payment-card {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
    box-sizing: border-box;
}
.history-card {
    background: #ffffff;
    padding: 18px 20px;
    box-sizing: border-box;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.history-card h3 { margin: 0; }
.history-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.payment-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 16px; flex-wrap: wrap; }
.payment-card-header h1 { margin: 0; color: #2e7d32; font-size: 25px; font-weight: 500; }
.payment-card h3 { color: #333; font-size: 18px; font-weight: 500; }
.date-short { display: none; }

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
.page-back:hover { background: #eef5e4; }
.page-back svg { width: 18px; height: 18px; stroke: currentColor; }

.add-payment-btn {
    background: #2e7d32 !important;
    color: #fff !important;
    border: none !important;
    padding: 8px 14px !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    line-height: 1.4 !important;
    white-space: nowrap !important;
    width: auto !important;
    flex: 0 0 auto !important;
    box-sizing: border-box !important;
}

/* Summary cards: label on top, big number, small note below */
.stat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
.stat-card {
    min-height: 92px;
    padding: 16px 18px;
    box-sizing: border-box;
    border-radius: 12px;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 4px;
}
.stat-label { font-size: 13px; opacity: 0.95; }
.stat-number { font-size: 22px; font-weight: 700; line-height: 1.15; overflow-wrap: anywhere; }
.stat-sub { font-size: 12px; opacity: 0.85; }
.stat-green { background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%); }
.stat-gray { background: linear-gradient(135deg, #bdbdbd 0%, #9e9e9e 100%); }
.stat-blue { background: linear-gradient(135deg, #42a5f5 0%, #1976d2 100%); }

.payment-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.payment-table th { padding: 10px 12px; background: #f5f5f5; text-align: left; }
.payment-table td { padding: 10px 12px; border-top: 1px solid #eee; }
.payment-table .amount { font-weight: 600; }
.status-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; }
.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.type-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }

/* Add payment modal: GCash info box + proof of payment upload */
.gcash-box { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 6px; padding: 12px; margin-bottom: 14px; }
.gcash-box-title { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #0d47a1; margin-bottom: 8px; }
.gcash-box-title .material-icons { font-size: 18px; }
.gcash-box-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.gcash-box-key { font-size: 12px; color: #546e7a; }
.gcash-box-val { font-size: 15px; font-weight: 600; color: #1a1a1a; word-break: break-word; }

.proof-drop { border: 1.5px dashed #90caf9; border-radius: 6px; padding: 14px; text-align: center; cursor: pointer; background: #f5faff; margin-bottom: 14px; }
.proof-drop:hover { border-color: #1976d2; }
.proof-drop .material-icons { font-size: 24px; color: #0d47a1; }
.proof-drop-text { font-size: 14px; margin-top: 2px; color: #0d47a1; }
.proof-drop-hint { font-size: 12px; color: #78909c; }
.proof-done { display: none; align-items: center; gap: 10px; text-align: left; }
.proof-done-icon { width: 40px; height: 40px; border-radius: 6px; background: #d4edda; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.proof-done-icon .material-icons { font-size: 22px; color: #155724; }
.proof-done-name { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.proof-done-ok { font-size: 12px; color: #155724; }
.proof-remove { font-size: 12px; color: #c62828; cursor: pointer; flex-shrink: 0; }

@media (max-width: 480px) {
    .history-card { padding: 12px 10px; }
    .history-card h3 { font-size: 15px; margin-left: 4px; }
    .payment-table { table-layout: fixed; font-size: 12px; }
    .payment-table th, .payment-table td { padding: 8px 6px; }
    .payment-table .col-type { width: 36%; }
    .payment-table .col-amount { width: 24%; }
    .payment-table .col-date { width: 18%; }
    .payment-table .col-status { width: 22%; }
    .date-full { display: none; }
    .date-short { display: inline; }
    .type-badge { font-size: 11px; padding: 2px 6px; }
    .status-badge { font-size: 10.5px; padding: 2px 5px; }
    .payment-card-header {
        flex-wrap: nowrap !important;
        gap: 8px !important;
    }
    .payment-card-header h1 {
        font-size: 18px !important;
        white-space: nowrap;
    }
    .add-payment-btn {
        padding: 7px 12px !important;
        font-size: 12.5px !important;
    }

    .stat-grid { gap: 10px; }
    .stat-card { min-height: 84px; padding: 12px 12px; border-radius: 10px; }
    .stat-label { font-size: 12px; }
    .stat-number { font-size: 18px; }
    .stat-sub { font-size: 11px; }
}

/* ============================================================
   ADD PAYMENT MODAL
   Same look as the Update Account modal on My Account: fixed title,
   only the fields scroll, button always visible, 2 columns on
   desktop and one compact column on phones.
   (display is controlled by the inline style so the JS can toggle it)
   ============================================================ */
.pay-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}

.pay-modal-box {
    background: #fff;
    border-radius: 14px;
    padding: 28px 24px 24px;
    width: 100%;
    max-width: 480px;
    box-sizing: border-box;
    position: relative;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 32px);
    overflow: hidden;
}

.pay-modal-box h2 {
    background: none !important;
    color: #1b3a24 !important;
    margin: 0 0 14px 0 !important;
    padding: 0 40px 0 0 !important;
    font-size: 20px !important;
    font-weight: 600 !important;
    text-align: left !important;
    flex: 0 0 auto;
}

/* Close (x): a <span role="button"> so no global "button { }" rule can stretch it */
#closePayModalBtn {
    position: absolute !important;
    top: 16px !important;
    right: 16px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 28px !important;
    height: 28px !important;
    min-width: 0 !important;
    max-width: 28px !important;
    padding: 0 !important;
    margin: 0 !important;
    background: #fff !important;
    border: 1px solid #cfd8e3 !important;
    border-radius: 50% !important;
    font-size: 16px !important;
    line-height: 1 !important;
    cursor: pointer !important;
    color: #274b81 !important;
    box-shadow: none !important;
    text-transform: none !important;
    z-index: 10;
    user-select: none;
}
#closePayModalBtn:hover {
    background: #eef3fb !important;
    border-color: #274b81 !important;
}

#payModalErrors,
#payModalSuccess { flex: 0 0 auto; }

.pay-form {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding-right: 4px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 12px;
    text-align: left;
}
.pay-form .form-group { margin: 0 !important; min-width: 0; }
.pay-form .span-2 { grid-column: 1 / -1; }
.pay-form .gcash-box,
.pay-form .proof-drop { margin-bottom: 0; }

.pay-form .form-group label {
    display: block;
    font-size: 13px;
    margin-bottom: 3px !important;
}
.pay-form .form-group input:not([type="file"]),
.pay-form .form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 9px !important;
    font-size: 13px !important;
}

.pay-form button[type="submit"] {
    grid-column: 1 / -1;
    position: sticky;
    bottom: 0;
    z-index: 2;
    width: 100% !important;
    box-sizing: border-box;
    margin: 0 !important;
    padding: 10px !important;
    background: #639a20 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 6px !important;
    cursor: pointer;
    font-size: 14px !important;
    box-shadow: 0 -8px 8px -4px #fff;
}

@media (max-width: 560px) {
    /* Wider side margins = narrower modal */
    .pay-modal-overlay { padding: 10px 34px; }
    .pay-modal-box {
        padding: 18px 16px 14px;
        max-width: 100%;
        border-radius: 12px;
        max-height: calc(100vh - 20px);
    }
    .pay-modal-box h2 {
        font-size: 17px !important;
        margin: 0 0 10px 0 !important;
    }
    #closePayModalBtn { top: 12px !important; right: 12px !important; }

    /* One field per row, stacked top to bottom */
    .pay-form {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .pay-form .form-group label { font-size: 12px; }
    .pay-form .form-group input:not([type="file"]),
    .pay-form .form-group select {
        padding: 7px 9px !important;
        font-size: 13px !important;
    }
    .pay-form button[type="submit"] { padding: 10px !important; font-size: 13px !important; }

    .gcash-box { padding: 10px; }
    .gcash-box-val { font-size: 13px; }
    .proof-drop { padding: 10px; }
    .proof-drop-hint { display: none; }
    #payModalErrors, #payModalSuccess { margin-bottom: 8px !important; font-size: 12.5px !important; }
}
</style>

<div class="payment-card">
    <div class="payment-card-header">
        <h1>Payments</h1>

        <!-- Back to dashboard: adjust the href if your user dashboard file is named differently -->
        <a href="<?php echo BASE_URL; ?>/app/user/dashboard.php" class="page-back" aria-label="Back to dashboard" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
    </div>

    <?php if (empty($memberDbId)): ?>
        <p style="color: #666;">No member profile is linked to your account yet.</p>
    <?php else: ?>

        <div class="stat-grid">
            <div class="stat-card <?php echo $eligibility['registration_paid'] ? 'stat-green' : 'stat-gray'; ?>">
                <div class="stat-label">Registration Fee</div>
                <div class="stat-number">
                    <?php echo $eligibility['registration_paid'] ? '₱' . number_format($eligibility['registration_amount'], 0) : 'Not yet paid'; ?>
                </div>
                <div class="stat-sub">
                    <?php echo $eligibility['registration_paid'] ? 'Paid' : 'Required: ₱' . number_format(REGISTRATION_FEE_REQUIRED, 0); ?>
                </div>
            </div>
            <div class="stat-card stat-blue">
                <div class="stat-label">Total Investment</div>
                <div class="stat-number">₱<?php echo number_format($eligibility['total_investment'], 0); ?></div>
                <div class="stat-sub">of ₱<?php echo number_format($eligibility['investment_target'], 0); ?> target</div>
            </div>
        </div>

        <div class="history-card">
        <div class="history-card-header">
            <h3>Payment History</h3>
            <button id="openPayModalBtn" class="add-payment-btn" type="button">+ Add payment</button>
        </div>
        <?php if (empty($paymentHistory)): ?>
            <p style="color: #666;">Wala pang payment history.</p>
        <?php else: ?>
            <?php
                // Same type badge colors as the manager-side Payments page,
                // so a "Rental" or "Loan Repayment" entry looks consistent everywhere.
                $typeBadgeMeta = [
                    'registration'   => ['label' => 'Registration',   'bg' => '#d4edda', 'text' => '#155724'],
                    'investment'     => ['label' => 'Investment',     'bg' => '#cce5ff', 'text' => '#004085'],
                    'rental'         => ['label' => 'Rental',          'bg' => '#eeedfe', 'text' => '#3c3489'],
                    'loan_repayment' => ['label' => 'Loan Repayment', 'bg' => '#faeeda', 'text' => '#633806'],
                    'product'        => ['label' => 'Product',        'bg' => '#DDEAF6', 'text' => '#185FA5'],
                ];
            ?>
            <div style="overflow-x:auto;">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th class="col-type">Type</th>
                            <th class="col-amount">Amount</th>
                            <th class="col-date">Date</th>
                            <th class="col-status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $payment): ?>
                            <?php
                                $tMeta = $typeBadgeMeta[$payment['payment_type']] ?? ['label' => ucfirst($payment['payment_type']), 'bg' => '#eee', 'text' => '#555'];
                            ?>
                            <tr>
                                <td>
                                    <span class="type-badge" style="background: <?php echo $tMeta['bg']; ?>; color: <?php echo $tMeta['text']; ?>;">
                                        <?php echo htmlspecialchars($tMeta['label']); ?>
                                    </span>
                                    <?php if ($payment['payment_type'] === 'product' && !empty($payment['product_name'])): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 3px;"><?php echo htmlspecialchars($payment['product_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_type'] === 'rental' && !empty($payment['equipment'])): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                            <?php
                                                echo htmlspecialchars(rentalEquipmentLabel($payment['equipment']));
                                                if (!empty($payment['quantity'])) {
                                                    echo ' &middot; ' . htmlspecialchars(rtrim(rtrim(number_format((float) $payment['quantity'], 2), '0'), '.')) . ' ' . rentalEquipmentUnit($payment['equipment']);
                                                }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="amount">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <span class="date-full"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                    <span class="date-short"><?php echo date('M d', strtotime($payment['payment_date'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php else: ?>
                                        <span class="status-badge status-confirmed">Confirmed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<!-- ============================================================
     MODAL - Add payment
     ============================================================ -->
<div id="payModalBackdrop" class="pay-modal-overlay" style="display: none;">
    <div class="pay-modal-box">
        <span id="closePayModalBtn" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Add payment</h2>

        <div id="payModalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>
        <div id="payModalSuccess" style="display: none; background: #d4edda; color: #155724; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;">
            Na-submit na para sa confirmation ng manager.
        </div>

        <form id="payForm" class="pay-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="payTypeSelect">Payment type</label>
                <select name="payment_type" id="payTypeSelect" required>
                    <option value="">-- Piliin --</option>
                    <option value="registration">Registration Fee (₱150)</option>
                    <option value="investment">Investment</option>
                    <option value="rental">Rental</option>
                    <option value="loan_repayment">Loan Repayment</option>
                    <option value="product">Product</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payAmountInput">Amount (₱)</label>
                <input type="number" name="amount" id="payAmountInput" step="0.01" min="0" required>
            </div>

            <div class="form-group span-2" id="payProductGroup" style="display: none;">
                <label for="payProductInput">Product <span style="color:#c62828;">*</span></label>
                <input type="text" name="product_name" id="payProductInput" maxlength="150" placeholder="e.g. Pork meat">
            </div>

            <!-- Rental only: which equipment, and how many hectares / sacks -->
            <div class="form-group span-2" id="payEquipGroup" style="display: none;">
                <label for="payEquipSelect">Equipment <span style="color:#c62828;">*</span></label>
                <select name="equipment" id="payEquipSelect">
                    <option value="">-- Piliin --</option>
                    <?php foreach (rentalEquipmentOptions() as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group span-2" id="payQtyGroup" style="display: none;">
                <label for="payQtyInput" id="payQtyLabel">Hectares</label>
                <input type="number" name="quantity" id="payQtyInput" min="0" step="0.5">
            </div>

            <div class="gcash-box span-2">
                <div class="gcash-box-title"><span class="material-icons">smartphone</span>Pay via GCash to</div>
                <div class="gcash-box-grid">
                    <div>
                        <div class="gcash-box-key">Account name</div>
                        <div class="gcash-box-val"><?php echo htmlspecialchars(COOP_GCASH_ACCOUNT_NAME); ?></div>
                    </div>
                    <div>
                        <div class="gcash-box-key">GCash number</div>
                        <div class="gcash-box-val"><?php echo htmlspecialchars(COOP_GCASH_NUMBER); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="payDateInput">Payment date</label>
                <input type="date" name="payment_date" id="payDateInput" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="payNotesInput">Notes (optional)</label>
                <input type="text" name="notes" id="payNotesInput" placeholder="e.g. paid via GCash">
            </div>

            <div class="form-group span-2">
                <label>Proof of payment (screenshot) <span style="color:#c62828;">*</span></label>
                <input type="file" name="proof_of_payment" id="payProofInput" accept="image/png,image/jpeg" style="display: none;">
                <div class="proof-drop" id="payProofDrop" role="button" tabindex="0">
                    <div id="payProofEmpty">
                        <span class="material-icons">upload</span>
                        <div class="proof-drop-text">I-tap para mag-upload</div>
                        <div class="proof-drop-hint">PNG o JPG, hanggang 5MB</div>
                    </div>
                    <div class="proof-done" id="payProofDone">
                        <div class="proof-done-icon"><span class="material-icons">photo</span></div>
                        <div style="min-width: 0; flex: 1;">
                            <div class="proof-done-name" id="payProofName"></div>
                            <div class="proof-done-ok">Na-upload na</div>
                        </div>
                        <span class="proof-remove" id="payProofRemove">Alisin</span>
                    </div>
                </div>
            </div>

            <button type="submit" id="paySubmitBtn">Submit for confirmation</button>
        </form>
    </div>
</div>

<script>
(function() {
    const openBtn = document.getElementById('openPayModalBtn');
    const closeBtn = document.getElementById('closePayModalBtn');
    const backdrop = document.getElementById('payModalBackdrop');
    const form = document.getElementById('payForm');
    const submitBtn = document.getElementById('paySubmitBtn');
    const errorsEl = document.getElementById('payModalErrors');
    const successEl = document.getElementById('payModalSuccess');
    const typeSelect = document.getElementById('payTypeSelect');
    const amountInput = document.getElementById('payAmountInput');
    const productGroup = document.getElementById('payProductGroup');
    const productInput = document.getElementById('payProductInput');

    const equipGroup = document.getElementById('payEquipGroup');
    const equipSelect = document.getElementById('payEquipSelect');
    const qtyGroup = document.getElementById('payQtyGroup');
    const qtyLabel = document.getElementById('payQtyLabel');
    const qtyInput = document.getElementById('payQtyInput');

    const proofInput = document.getElementById('payProofInput');
    const proofDrop = document.getElementById('payProofDrop');
    const proofEmpty = document.getElementById('payProofEmpty');
    const proofDone = document.getElementById('payProofDone');
    const proofName = document.getElementById('payProofName');
    const proofRemove = document.getElementById('payProofRemove');

    if (!openBtn) return; // walang member profile, wala ring button

    const MAX_PROOF_BYTES = 5 * 1024 * 1024;
    // Member rate per hectare / per sack, from the Equipment page
    const EQUIP_RATES = <?php echo json_encode($memberRates); ?>;
    // true kapag ang Amount ay galing sa auto-fill (hindi tinype ng member)
    let amountAuto = false;

    function showError(msg) {
        errorsEl.innerHTML = msg;
        errorsEl.style.display = 'block';
    }

    function resetProofUI() {
        proofInput.value = '';
        proofDone.style.display = 'none';
        proofEmpty.style.display = 'block';
    }

    function toggleProductField() {
        const isProduct = typeSelect.value === 'product';
        productGroup.style.display = isProduct ? 'block' : 'none';
        productInput.required = isProduct;
        if (!isProduct) productInput.value = '';
    }

    // Amount = rate x hectares/sacks. If there is no rate or no quantity,
    // an auto-filled amount is cleared so an old value is never left behind.
    function autofillAmount() {
        const rate = EQUIP_RATES[equipSelect.value] || 0;
        const qty = parseFloat(qtyInput.value) || 0;
        if (typeSelect.value === 'rental' && rate > 0 && qty > 0) {
            amountInput.value = (rate * qty).toFixed(2);
            amountAuto = true;
        } else if (amountAuto) {
            amountInput.value = '';
            amountAuto = false;
        }
    }

    function toggleRentalFields() {
        const isRental = typeSelect.value === 'rental';
        const eq = equipSelect.value;

        equipGroup.style.display = isRental ? 'block' : 'none';
        equipSelect.required = isRental;
        qtyGroup.style.display = (isRental && eq) ? 'block' : 'none';

        if (eq === 'corn_harvester') {
            qtyLabel.textContent = 'Hectares';
            qtyInput.step = '0.5';
            qtyInput.required = true;
        } else {
            qtyLabel.textContent = 'Sacks (optional)';
            qtyInput.step = '1';
            qtyInput.required = false;
        }

        if (!isRental) {
            equipSelect.value = '';
            qtyInput.value = '';
            qtyInput.required = false;
        }
        autofillAmount();
    }

    function resetFormState() {
        form.reset();
        amountAuto = false;
        toggleProductField();
        toggleRentalFields();
        resetProofUI();
    }

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        resetFormState();
        errorsEl.style.display = 'none';
        successEl.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    // The close (x) is a <span role="button">, so it needs a manual keyboard handler
    closeBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); closeModal(); }
    });
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    typeSelect.addEventListener('change', function() {
        toggleProductField();
        toggleRentalFields();
        // Auto-fill ng ₱150 kapag "Registration Fee" ang pinili
        if (typeSelect.value === 'registration') {
            amountInput.value = '150';
            amountAuto = true;
        }
    });
    equipSelect.addEventListener('change', toggleRentalFields);
    qtyInput.addEventListener('input', autofillAmount);
    amountInput.addEventListener('input', function() { amountAuto = false; });

    // ---- Proof of payment upload ----
    proofDrop.addEventListener('click', function() { proofInput.click(); });
    proofDrop.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); proofInput.click(); }
    });
    proofRemove.addEventListener('click', function(e) {
        e.stopPropagation(); // huwag mag-open ulit ang file picker
        resetProofUI();
    });
    proofInput.addEventListener('change', function() {
        const file = proofInput.files && proofInput.files[0];
        if (!file) return;

        if (file.type !== 'image/png' && file.type !== 'image/jpeg') {
            resetProofUI();
            showError('PNG o JPG lang ang pwedeng i-upload.');
            return;
        }
        if (file.size > MAX_PROOF_BYTES) {
            resetProofUI();
            showError('Hanggang 5MB lang ang pwedeng i-upload.');
            return;
        }

        errorsEl.style.display = 'none';
        proofName.textContent = file.name;
        proofEmpty.style.display = 'none';
        proofDone.style.display = 'flex';
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsEl.style.display = 'none';
        successEl.style.display = 'none';

        // Hindi puwedeng mag-submit kung wala pang proof of payment.
        if (!proofInput.files || !proofInput.files.length) {
            showError('Mag-upload muna ng screenshot ng payment.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        try {
            const formData = new FormData(form);
            const response = await fetch('<?php echo BASE_URL; ?>/app/user/api/submit-pending-payment.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                showError('Server error. Tingnan ang console (F12).');
                return;
            }

            if (data.success) {
                successEl.style.display = 'block';
                resetFormState();
                setTimeout(() => { window.location.reload(); }, 1200);
            } else {
                showError((data.errors || ['May error, subukan ulit.']).join('<br>'));
            }
        } catch (err) {
            showError('Hindi makonekta sa server: ' + err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit for confirmation';
        }
    });
})();
</script>

<?php renderFooter(); ?>