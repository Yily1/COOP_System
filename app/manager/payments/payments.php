<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('manager');

$filterType = $_GET['payment_type'] ?? '';
$filterPayer = $_GET['payer'] ?? '';

$query = "
    SELECT p.*, m.first_name, m.last_name, m.membership_id, u.email as recorded_by_email
    FROM payments p
    LEFT JOIN members m ON p.member_id = m.id
    LEFT JOIN users u ON p.recorded_by = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filterType)) {
    $query .= " AND p.payment_type = ?";
    $params[] = $filterType;
}

if ($filterPayer === 'member') {
    $query .= " AND p.member_id IS NOT NULL";
} elseif ($filterPayer === 'non_member') {
    $query .= " AND p.member_id IS NULL";
}

$query .= " ORDER BY (p.status = 'pending') DESC, p.payment_date DESC, p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("SELECT id, membership_id, last_name, first_name FROM members ORDER BY last_name, first_name");
$members = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Compact member list used by the search box in the Add payment modal
$membersForJs = array_map(function($m) {
    return [
        'id' => (int) $m['id'],
        'membership_id' => $m['membership_id'],
        'name' => $m['last_name'] . ', ' . $m['first_name'],
    ];
}, $members);

// ============================================================
// PAYMENT TYPE METADATA (label + badge colors)
// Add new types here and they'll flow through totals, filters,
// the add-payment form, and the table badges automatically.
// ============================================================
function paymentTypeMeta($type) {
    $map = [
        'registration'    => ['label' => 'Registration',    'bg' => '#d4edda', 'text' => '#155724'],
        'investment'      => ['label' => 'Investment',      'bg' => '#cce5ff', 'text' => '#004085'],
        'rental'          => ['label' => 'Rental',           'bg' => '#eeedfe', 'text' => '#3c3489'],
        'loan_repayment'  => ['label' => 'Loan Repayment',   'bg' => '#faeeda', 'text' => '#633806'],
        'product'         => ['label' => 'Product',          'bg' => '#DDEAF6', 'text' => '#185FA5'],
    ];
    return $map[$type] ?? ['label' => ucfirst($type), 'bg' => '#eee', 'text' => '#555'];
}

// ============================================================
// MEMBER AVATAR COLOR (consistent per member, not per payment type)
// Same hashing logic is mirrored in JS below so rows added via
// AJAX get the same color as rows rendered by PHP on load.
// ============================================================
function memberAvatarPalette() {
    return [
        ['bg' => '#534AB7', 'text' => '#F4F3FE'], // purple
        ['bg' => '#0F6E56', 'text' => '#E1F5EE'], // teal
        ['bg' => '#993C1D', 'text' => '#FAECE7'], // coral
        ['bg' => '#993556', 'text' => '#FBEAF0'], // pink
        ['bg' => '#185FA5', 'text' => '#E6F1FB'], // blue
        ['bg' => '#3B6D11', 'text' => '#EAF3DE'], // green
        ['bg' => '#854F0B', 'text' => '#FAEEDA'], // amber
        ['bg' => '#791F1F', 'text' => '#FCEBEB'], // red
    ];
}

function simpleHash($str) {
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

function memberAvatarColor($seed) {
    $palette = memberAvatarPalette();
    $index = simpleHash($seed) % count($palette);
    return $palette[$index];
}

function memberInitials($firstName, $lastName) {
    $l = mb_substr(trim($lastName), 0, 1);
    $f = mb_substr(trim($firstName), 0, 1);
    return mb_strtoupper($l . $f);
}

// Initials for a non-member, taken from the typed name (e.g. "Maria Santos" -> "MS")
function payerInitials($name) {
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $letters = array_map(function($w) { return mb_strtoupper(mb_substr($w, 0, 1)); }, array_slice($parts, 0, 2));
    return implode('', $letters);
}

// ============================================================
// SUMMARY TOTALS (confirmed payments only, respects current filters)
// ============================================================
$totals = ['registration' => 0, 'investment' => 0, 'rental' => 0, 'loan_repayment' => 0, 'product' => 0];

foreach ($payments as $payment) {
    if ($payment['status'] !== 'confirmed') {
        continue;
    }
    if (isset($totals[$payment['payment_type']])) {
        $totals[$payment['payment_type']] += $payment['amount'];
    }
}

$grandTotal = array_sum($totals);

renderHeader('Payments');
?>

<style>
/* Summary cards: flat colors, same style as the Equipment rental page */
.pay-summary-grid { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 28px; }
.pay-summary-card {
    border-radius: 12px; padding: 0 18px; flex: 0 0 160px; width: 160px; height: 92px;
    box-sizing: border-box; box-shadow: none;
    display: flex; flex-direction: column; justify-content: center;
}
.pay-summary-card .pay-card-label { margin: 0 0 6px; font-size: 13px; }
.pay-summary-card .pay-card-value {
    margin: 0; font-size: 22px; font-weight: 700; line-height: 1.15;
    font-family: 'Fraunces', 'Times New Roman', Times, serif;
    font-variant-numeric: lining-nums tabular-nums;
    white-space: nowrap;   /* never wrap the amount to a 2nd line */
}
.pay-card-dark   { background: #33502F; }
.pay-card-dark   .pay-card-label, .pay-card-dark   .pay-card-value { color: #fff; }
.pay-card-amber  { background: #C1892B; }
.pay-card-amber  .pay-card-label, .pay-card-amber  .pay-card-value { color: #3E2A07; }
.pay-card-green  { background: #E1EFDE; }
.pay-card-green  .pay-card-label { color: #3F7D52; }
.pay-card-green  .pay-card-value { color: #2F6B43; }
.pay-card-red    { background: #F6E1DC; }
.pay-card-red    .pay-card-label, .pay-card-red    .pay-card-value { color: #B54A3C; }
.pay-card-sand   { background: #F6E4C3; }
.pay-card-sand   .pay-card-label, .pay-card-sand   .pay-card-value { color: #C1892B; }
.pay-card-blue   { background: #DDEAF6; }
.pay-card-blue   .pay-card-label, .pay-card-blue   .pay-card-value { color: #185FA5; }

/* Bright green buttons (same as the "Add product" button) */
#openModalBtn:hover,
#submitBtn:hover { background: #2e5a0d !important; }

/* Proof of payment popup */
#proofModalBackdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7); z-index: 200; align-items: center; justify-content: center; padding: 16px;
}
#proofModalBox {
    position: relative; background: #fff; border-radius: 10px; padding: 16px;
    max-width: 90vw; max-height: 90vh; box-sizing: border-box; display: flex; flex-direction: column;
}
#proofModalBox h3 { margin: 0 36px 10px 0; font-size: 16px; font-weight: 500; color: #1b3a24; }
#proofModalImg { display: block; max-width: 100%; max-height: calc(90vh - 90px); margin: 0 auto; border-radius: 6px; object-fit: contain; }
#closeProofModalBtn {
    position: absolute !important; top: 12px !important; right: 12px !important;
    width: 28px !important; height: 28px !important; max-width: 28px !important; padding: 0 !important; margin: 0 !important;
    display: inline-flex !important; align-items: center; justify-content: center;
    background: none !important; border: none !important; font-size: 22px !important; line-height: 1 !important;
    color: #333 !important; cursor: pointer; box-shadow: none !important;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; position: relative; width: 100%;">
    <h1 style="margin: 0; flex: 1 1 auto; min-width: 0; position: static !important;">Payments</h1>
    <button id="openModalBtn"
            style="background: #3B6D11 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important; font-weight: 600 !important; color: #fff !important; border: none !important; padding: 10px 18px !important; border-radius: 6px !important; cursor: pointer; font-size: 14px !important; display: inline-block !important; width: auto !important; max-width: 200px !important; flex: 0 0 auto !important; white-space: nowrap !important; position: static !important; top: auto !important; left: auto !important; right: auto !important; float: none !important; margin-left: auto !important;">
        + Add payment
    </button>
</div>

<!-- ============================================================
     OVERALL PAYMENT TOTALS
     ============================================================ -->
<div class="pay-summary-grid">
    <div class="pay-summary-card pay-card-dark">
        <p class="pay-card-label">Grand total</p>
        <p class="pay-card-value">₱<?php echo number_format($grandTotal, 2); ?></p>
    </div>
    <div class="pay-summary-card pay-card-amber">
        <p class="pay-card-label">Registration</p>
        <p class="pay-card-value">₱<?php echo number_format($totals['registration'], 2); ?></p>
    </div>
    <div class="pay-summary-card pay-card-green">
        <p class="pay-card-label">Investment</p>
        <p class="pay-card-value">₱<?php echo number_format($totals['investment'], 2); ?></p>
    </div>
    <div class="pay-summary-card pay-card-red">
        <p class="pay-card-label">Rental</p>
        <p class="pay-card-value">₱<?php echo number_format($totals['rental'], 2); ?></p>
    </div>
    <div class="pay-summary-card pay-card-sand">
        <p class="pay-card-label">Loan Repayment</p>
        <p class="pay-card-value">₱<?php echo number_format($totals['loan_repayment'], 2); ?></p>
    </div>
    <div class="pay-summary-card pay-card-blue">
        <p class="pay-card-label">Products</p>
        <p class="pay-card-value">₱<?php echo number_format($totals['product'], 2); ?></p>
    </div>
</div>

<h2 style="margin: 0 0 12px 0;">Payment history</h2>

<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <input type="text" id="memberSearchInput" placeholder="Search by name or membership ID"
           style="padding: 8px; border-radius: 6px; border: 1px solid #ccc; width: 240px; max-width: 240px; flex: 0 0 auto; box-sizing: border-box;">

    <select id="paymentTypeSelect" style="padding: 8px; border-radius: 6px; border: 1px solid #ccc; width: 160px !important; max-width: 160px !important; flex: 0 0 auto !important;">
        <option value="">All types</option>
        <option value="registration" <?php echo ($filterType === 'registration') ? 'selected' : ''; ?>>Registration</option>
        <option value="investment" <?php echo ($filterType === 'investment') ? 'selected' : ''; ?>>Investment</option>
        <option value="rental" <?php echo ($filterType === 'rental') ? 'selected' : ''; ?>>Rental</option>
        <option value="loan_repayment" <?php echo ($filterType === 'loan_repayment') ? 'selected' : ''; ?>>Loan Repayment</option>
        <option value="product" <?php echo ($filterType === 'product') ? 'selected' : ''; ?>>Products</option>
    </select>

    <select id="payerFilterSelect" style="padding: 8px; border-radius: 6px; border: 1px solid #ccc; width: 160px !important; max-width: 160px !important; flex: 0 0 auto !important;">
        <option value="">All payers</option>
        <option value="member" <?php echo ($filterPayer === 'member') ? 'selected' : ''; ?>>Members</option>
        <option value="non_member" <?php echo ($filterPayer === 'non_member') ? 'selected' : ''; ?>>Non-members</option>
    </select>
</div>

<div style="overflow-x: auto;">
<table style="width: 100%; border-collapse: collapse; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;" id="paymentsTable">
    <thead>
        <tr style="background: #f5f5f5; text-align: left;">
            <th style="padding: 10px 12px;">Member</th>
            <th style="padding: 10px 12px;">Type</th>
            <th style="padding: 10px 12px;">Amount</th>
            <th style="padding: 10px 12px;">Date</th>
            <th style="padding: 10px 12px;">Status</th>
            <th style="padding: 10px 12px;">Notes</th>
            <th style="padding: 10px 12px;">Proof</th>
            <th style="padding: 10px 12px;">Recorded by</th>
            <th style="padding: 10px 12px;">Action</th>
        </tr>
    </thead>
    <tbody id="paymentsTableBody">
        <?php if (empty($payments)): ?>
            <tr id="emptyRow">
                <td colspan="9" style="padding: 20px; text-align: center; color: #666;">No payment records.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <?php
                    $meta = paymentTypeMeta($payment['payment_type']);
                    $isNonMember = empty($payment['member_id']);
                    if ($isNonMember) {
                        $displayName = $payment['payer_name'] ?: '-';
                        $avatar = ['bg' => '#888780', 'text' => '#FFFFFF'];
                        $initials = payerInitials($displayName);
                        $searchText = strtolower($displayName . ' non-member');
                    } else {
                        $displayName = $payment['last_name'] . ', ' . $payment['first_name'];
                        $avatar = memberAvatarColor($payment['membership_id']);
                        $initials = memberInitials($payment['first_name'], $payment['last_name']);
                        $searchText = strtolower($displayName . ' ' . $payment['membership_id']);
                    }
                    
                    $proofUrl = !empty($payment['proof_of_payment'])
                        ? BASE_URL . '/' . ltrim($payment['proof_of_payment'], '/')
                        : '';
                ?>
                <tr style="border-top: 1px solid #eee;" data-payment-id="<?php echo $payment['id']; ?>"
                    data-search="<?php echo htmlspecialchars($searchText); ?>">
                    <td style="padding: 10px 12px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; min-width: 32px; border-radius: 50%; background: <?php echo $avatar['bg']; ?>; color: <?php echo $avatar['text']; ?>; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px;">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <p style="margin: 0; font-weight: 600;"><?php echo htmlspecialchars($displayName); ?></p>
                                <?php if ($isNonMember): ?>
                                    <span style="display: inline-block; font-size: 10px; font-weight: 600; background: #F1EFE8; color: #5F5E5A; border-radius: 4px; padding: 1px 6px;">Non-member</span>
                                <?php else: ?>
                                    <p style="margin: 0; font-size: 12px; color: #888;"><?php echo htmlspecialchars($payment['membership_id']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 10px 12px;">
                        <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['text']; ?>; white-space: nowrap;">
                            <?php echo htmlspecialchars($meta['label']); ?>
                        </span>
                        <?php if ($payment['payment_type'] === 'product' && !empty($payment['product_name'])): ?>
                            <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                <?php echo htmlspecialchars($payment['product_name']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px 12px; font-weight: 600;">₱<?php echo number_format($payment['amount'], 2); ?></td>
                    <td style="padding: 10px 12px;"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                    <td style="padding: 10px 12px;">
                        <?php if ($payment['status'] === 'pending'): ?>
                            <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: #fff3cd; color: #856404;">Pending</span>
                        <?php else: ?>
                            <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: #d4edda; color: #155724;">Confirmed</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px 12px; color: #666;"><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                    <td style="padding: 10px 12px;">
                        <?php if ($proofUrl !== ''): ?>
                            <a href="<?php echo htmlspecialchars($proofUrl); ?>" class="viewProofLink" data-src="<?php echo htmlspecialchars($proofUrl); ?>"
                               style="color: #1976d2; text-decoration: underline; white-space: nowrap; cursor: pointer;">View image</a>
                        <?php else: ?>
                            <span style="color: #ccc;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px 12px; color: #666;"><?php echo htmlspecialchars($payment['recorded_by_email'] ?? '-'); ?></td>
                    <td style="padding: 10px 12px;">
                        <?php if ($payment['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 6px;">
                                <button class="confirmBtn" data-id="<?php echo $payment['id']; ?>"
                                        style="display: inline-flex !important; align-items: center; justify-content: center; width: auto !important; max-width: none !important; flex: 0 0 auto !important; font-size: 12px; padding: 6px 10px; margin: 0 !important; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                                    Confirm
                                </button>
                                <button class="rejectBtn" data-id="<?php echo $payment['id']; ?>"
                                        style="display: inline-flex !important; align-items: center; justify-content: center; width: auto !important; max-width: none !important; flex: 0 0 auto !important; font-size: 12px; padding: 6px 10px; margin: 0 !important; background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                                    Reject
                                </button>
                            </div>
                        <?php else: ?>
                            <span style="color: #ccc;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- MODAL - Proof of payment image -->
<div id="proofModalBackdrop">
    <div id="proofModalBox">
        <h3>Proof of payment</h3>
        <span id="closeProofModalBtn" role="button" tabindex="0" aria-label="Close">&times;</span>
        <img id="proofModalImg" src="" alt="Proof of payment">
    </div>
</div>

<!-- MODAL - Add Payment -->
<div id="paymentModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Add payment</h2>
        <button id="closeModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="modalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px;"></div>

        <form id="paymentForm">
            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payer</label>
                <input type="hidden" name="payer_type" id="payerTypeInput" value="member">
                <div style="display: flex; border: 1px solid #3B6D11; border-radius: 8px; overflow: hidden;">
                    <button type="button" id="tabMember"
                            style="flex: 1 1 50% !important; width: auto !important; max-width: none !important; padding: 8px !important; margin: 0 !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; font-size: 14px; font-weight: 600; text-transform: none !important; letter-spacing: normal; cursor: pointer;">Member</button>
                    <button type="button" id="tabNonMember"
                            style="flex: 1 1 50% !important; width: auto !important; max-width: none !important; padding: 8px !important; margin: 0 !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; font-size: 14px; font-weight: 600; text-transform: none !important; letter-spacing: normal; cursor: pointer;">Non-member</button>
                </div>
            </div>

            <div id="memberBox" style="margin-bottom: 14px;">
                <label for="memberSearchBox" style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Search member</label>
                <input type="hidden" name="member_id" id="memberIdInput" value="">
                <input type="text" id="memberSearchBox" autocomplete="off" placeholder="Type membership ID or name"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                <div id="memberResults" style="display: none; border: 1px solid #ddd; border-radius: 6px; margin-top: 4px; max-height: 180px; overflow-y: auto; background: #fff;"></div>
                <div id="memberChosen" style="display: none; margin-top: 6px; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid #C0DD97; border-radius: 6px; background: #EAF3DE; font-size: 13px;"></div>
            </div>

            <div id="nonMemberBox" style="display: none; margin-bottom: 14px;">
                <label for="payerNameInput" style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payer name</label>
                <input type="text" name="payer_name" id="payerNameInput" maxlength="150" placeholder="e.g. Maria Santos"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payment type</label>
                <select name="payment_type" id="paymentTypeInput" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;"></select>
                
            </div>

            <div id="productFields" style="display: none; margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Product</label>
                <input type="text" name="product_name" id="productNameInput" maxlength="150" placeholder="e.g. Pork meat"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Amount (₱)</label>
                <input type="number" name="amount" step="0.01" min="0" required
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payment date</label>
                <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Notes (optional)</label>
                <input type="text" name="notes" placeholder="e.g. cash, GCash ref #123"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" id="cancelBtn" style="padding: 10px 18px !important; border-radius: 6px; border: 1px solid #ccc; background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" style="padding: 10px 18px !important; border-radius: 6px; border: none; background: #3B6D11 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important; font-weight: 600 !important; color: #fff !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                    Save payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const openBtn = document.getElementById('openModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const backdrop = document.getElementById('paymentModalBackdrop');
    const form = document.getElementById('paymentForm');
    const submitBtn = document.getElementById('submitBtn');
    const modalErrors = document.getElementById('modalErrors');
    const tableBody = document.getElementById('paymentsTableBody');
    const typeInput = document.getElementById('paymentTypeInput');
    const productFields = document.getElementById('productFields');
    const productNameInput = document.getElementById('productNameInput');
    const payerTypeInput = document.getElementById('payerTypeInput');
    const tabMember = document.getElementById('tabMember');
    const tabNonMember = document.getElementById('tabNonMember');
    const memberBox = document.getElementById('memberBox');
    const nonMemberBox = document.getElementById('nonMemberBox');
    const memberIdInput = document.getElementById('memberIdInput');
    const memberSearchBox = document.getElementById('memberSearchBox');
    const memberResults = document.getElementById('memberResults');
    const memberChosen = document.getElementById('memberChosen');
    const payerNameInput = document.getElementById('payerNameInput');

    const MEMBERS = <?php echo json_encode($membersForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const MEMBER_TYPES = [
        ['registration', 'Registration fee (₱150)'],
        ['investment', 'Investment'],
        ['rental', 'Rental'],
        ['loan_repayment', 'Loan Repayment'],
        ['product', 'Product']
    ];
    const NON_MEMBER_TYPES = [
        ['rental', 'Rental']
    ];

    function toggleProductFields() {
        const isProduct = typeInput.value === 'product';
        productFields.style.display = isProduct ? 'block' : 'none';
        productNameInput.required = isProduct;
        if (!isProduct) productNameInput.value = '';
    }

    typeInput.addEventListener('change', toggleProductFields);

    function styleTab(btn, active) {
        btn.style.setProperty('background', active ? '#3B6D11' : '#fff', 'important');
        btn.style.setProperty('color', active ? '#fff' : '#3B6D11', 'important');
    }

    function clearMember() {
        memberIdInput.value = '';
        memberSearchBox.value = '';
        memberChosen.style.display = 'none';
        memberResults.style.display = 'none';
        memberResults.textContent = '';
    }

    function setPayerMode(mode) {
        const isMember = mode === 'member';
        payerTypeInput.value = isMember ? 'member' : 'non_member';
        styleTab(tabMember, isMember);
        styleTab(tabNonMember, !isMember);
        memberBox.style.display = isMember ? 'block' : 'none';
        nonMemberBox.style.display = isMember ? 'none' : 'block';
        payerNameInput.required = !isMember;
        clearMember();
        if (isMember) payerNameInput.value = '';

        typeInput.textContent = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Select --';
        typeInput.appendChild(placeholder);
        (isMember ? MEMBER_TYPES : NON_MEMBER_TYPES).forEach(function(t) {
            const opt = document.createElement('option');
            opt.value = t[0];
            opt.textContent = t[1];
            typeInput.appendChild(opt);
        });
        toggleProductFields();
    }

    function chooseMember(m) {
        memberIdInput.value = m.id;
        memberResults.style.display = 'none';
        memberChosen.textContent = '';
        memberChosen.style.display = 'flex';

        const label = document.createElement('span');
        label.style.flex = '1';
        const strong = document.createElement('strong');
        strong.textContent = m.name;
        const idSpan = document.createElement('span');
        idSpan.style.color = '#667066';
        idSpan.textContent = ' ' + (m.membership_id || '');
        label.appendChild(strong);
        label.appendChild(idSpan);

        const clear = document.createElement('span');
        clear.setAttribute('role', 'button');
        clear.setAttribute('tabindex', '0');
        clear.setAttribute('aria-label', 'Clear selected member');
        clear.style.cssText = 'cursor: pointer; font-size: 16px; color: #3B6D11;';
        clear.textContent = '\u00d7';
        clear.addEventListener('click', clearMember);

        memberChosen.appendChild(label);
        memberChosen.appendChild(clear);
    }

    function renderMemberResults() {
        const q = memberSearchBox.value.trim().toLowerCase();
        memberIdInput.value = '';
        memberChosen.style.display = 'none';
        memberResults.textContent = '';
        if (!q) {
            memberResults.style.display = 'none';
            return;
        }
        const found = MEMBERS.filter(function(m) {
            return (m.membership_id || '').toLowerCase().indexOf(q) !== -1 ||
                   m.name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);

        memberResults.style.display = 'block';
        if (!found.length) {
            const none = document.createElement('div');
            none.style.cssText = 'padding: 9px 10px; font-size: 13px; color: #667066;';
            none.textContent = 'No member found';
            memberResults.appendChild(none);
            return;
        }
        found.forEach(function(m, idx) {
            const row = document.createElement('div');
            row.style.cssText = 'padding: 9px 10px; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; gap: 8px;' + (idx > 0 ? ' border-top: 1px solid #f0f0f0;' : '');
            const nameSpan = document.createElement('span');
            nameSpan.textContent = m.name;
            const idSpan = document.createElement('span');
            idSpan.style.color = '#667066';
            idSpan.textContent = m.membership_id || '';
            row.appendChild(nameSpan);
            row.appendChild(idSpan);
            row.addEventListener('mouseenter', function() { row.style.background = '#F4F9EC'; });
            row.addEventListener('mouseleave', function() { row.style.background = '#fff'; });
            row.addEventListener('click', function() { chooseMember(m); });
            memberResults.appendChild(row);
        });
    }

    tabMember.addEventListener('click', function() { setPayerMode('member'); });
    tabNonMember.addEventListener('click', function() { setPayerMode('non_member'); });
    memberSearchBox.addEventListener('input', renderMemberResults);
    setPayerMode('member');

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        form.reset();
        setPayerMode('member');
        modalErrors.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    const proofBackdrop = document.getElementById('proofModalBackdrop');
    const proofImg = document.getElementById('proofModalImg');
    const closeProofBtn = document.getElementById('closeProofModalBtn');

    function openProofModal(src) {
        proofImg.src = src;
        proofBackdrop.style.display = 'flex';
    }
    function closeProofModal() {
        proofBackdrop.style.display = 'none';
        proofImg.src = '';
    }

    tableBody.addEventListener('click', function(e) {
        const link = e.target.closest('.viewProofLink');
        if (!link) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
        e.preventDefault();
        openProofModal(link.dataset.src || link.href);
    });
    closeProofBtn.addEventListener('click', closeProofModal);
    closeProofBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); closeProofModal(); }
    });
    proofBackdrop.addEventListener('click', function(e) { if (e.target === proofBackdrop) closeProofModal(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && proofBackdrop.style.display === 'flex') closeProofModal();
    });

    const TYPE_META = {
        registration:   { label: 'Registration',   bg: '#d4edda', text: '#155724' },
        investment:     { label: 'Investment',     bg: '#cce5ff', text: '#004085' },
        rental:         { label: 'Rental',          bg: '#eeedfe', text: '#3c3489' },
        loan_repayment: { label: 'Loan Repayment', bg: '#faeeda', text: '#633806' },
        product:        { label: 'Product',        bg: '#DDEAF6', text: '#185FA5' }
    };

    const AVATAR_PALETTE = [
        { bg: '#534AB7', text: '#F4F3FE' },
        { bg: '#0F6E56', text: '#E1F5EE' },
        { bg: '#993C1D', text: '#FAECE7' },
        { bg: '#993556', text: '#FBEAF0' },
        { bg: '#185FA5', text: '#E6F1FB' },
        { bg: '#3B6D11', text: '#EAF3DE' },
        { bg: '#854F0B', text: '#FAEEDA' },
        { bg: '#791F1F', text: '#FCEBEB' }
    ];

    function simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash = hash | 0;
        }
        return Math.abs(hash);
    }

    function avatarColorFor(membershipId) {
        return AVATAR_PALETTE[simpleHash(membershipId) % AVATAR_PALETTE.length];
    }

    function initialsFor(lastName, firstName) {
        const l = (lastName || '').trim().charAt(0);
        const f = (firstName || '').trim().charAt(0);
        return (l + f).toUpperCase();
    }

    function payerInitials(name) {
        const parts = (name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
        return parts.map(function(w) { return w.charAt(0).toUpperCase(); }).join('');
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function formatAmount(amount) {
        return parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function buildRow(payment) {
        const meta = TYPE_META[payment.payment_type] || { label: payment.payment_type, bg: '#eee', text: '#555' };
        const isNonMember = !payment.member_id;
        const displayName = isNonMember ? (payment.payer_name || '-') : (payment.last_name + ', ' + payment.first_name);
        const avatar = isNonMember ? { bg: '#888780', text: '#FFFFFF' } : avatarColorFor(payment.membership_id);
        const initials = isNonMember ? payerInitials(displayName) : initialsFor(payment.last_name, payment.first_name);
        const subLine = isNonMember
            ? '<span style="display: inline-block; font-size: 10px; font-weight: 600; background: #F1EFE8; color: #5F5E5A; border-radius: 4px; padding: 1px 6px;">Non-member</span>'
            : `<p style="margin: 0; font-size: 12px; color: #888;">${escapeHtml(payment.membership_id)}</p>`;
        
        const productLine = (payment.payment_type === 'product' && payment.product_name)
            ? `<div style="font-size: 11px; color: #666; margin-top: 3px;">${escapeHtml(payment.product_name)}</div>`
            : '';
        const tr = document.createElement('tr');
        tr.style.borderTop = '1px solid #eee';
        tr.dataset.paymentId = payment.id;
        tr.dataset.search = (isNonMember ? displayName + ' non-member' : displayName + ' ' + payment.membership_id).toLowerCase();
        // Payments the manager records here are confirmed on the spot
        // and never have a member-uploaded proof, so Proof is always "-".
        tr.innerHTML = `
            <td style="padding: 10px 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; min-width: 32px; border-radius: 50%; background: ${avatar.bg}; color: ${avatar.text}; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px;">${initials}</div>
                    <div>
                        <p style="margin: 0; font-weight: 600;">${escapeHtml(displayName)}</p>
                        ${subLine}
                    </div>
                </div>
            </td>
            <td style="padding: 10px 12px;">
                <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: ${meta.bg}; color: ${meta.text}; white-space: nowrap;">${meta.label}</span>
                ${productLine}
            </td>
            <td style="padding: 10px 12px; font-weight: 600;">₱${formatAmount(payment.amount)}</td>
            <td style="padding: 10px 12px;">${formatDate(payment.payment_date)}</td>
            <td style="padding: 10px 12px;">
                <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: #d4edda; color: #155724;">Confirmed</span>
            </td>
            <td style="padding: 10px 12px; color: #666;">${escapeHtml(payment.notes || '-')}</td>
            <td style="padding: 10px 12px;"><span style="color: #ccc;">-</span></td>
            <td style="padding: 10px 12px; color: #666;">${escapeHtml(payment.recorded_by_email || '-')}</td>
            <td style="padding: 10px 12px;"><span style="color: #ccc;">-</span></td>
        `;
        return tr;
    }

    function prependRow(payment) {
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();
        tableBody.insertBefore(buildRow(payment), tableBody.firstChild);
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        modalErrors.style.display = 'none';

        if (payerTypeInput.value === 'member' && !memberIdInput.value) {
            modalErrors.textContent = 'Search and select a member from the list.';
            modalErrors.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const formData = new FormData(form);
            const response = await fetch('<?php echo BASE_URL; ?>/app/manager/payments/api/save-payment.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                modalErrors.innerHTML = 'Server error. Status: ' + response.status + '<br><small>Check the console (F12).</small>';
                modalErrors.style.display = 'block';
                return;
            }

            if (data.success) {
                prependRow(data.payment);
                closeModal();
            } else {
                modalErrors.innerHTML = (data.errors || ['An error occurred, please try again.']).join('<br>');
                modalErrors.style.display = 'block';
            }
        } catch (err) {
            console.error('Fetch failed:', err);
            modalErrors.textContent = 'Could not connect to the server: ' + err.message;
            modalErrors.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save payment';
        }
    });

    tableBody.addEventListener('click', async function(e) {
        const confirmBtn = e.target.closest('.confirmBtn');
        const rejectBtn = e.target.closest('.rejectBtn');
        if (!confirmBtn && !rejectBtn) return;

        const btn = confirmBtn || rejectBtn;
        const action = confirmBtn ? 'confirm' : 'reject';
        const paymentId = btn.dataset.id;
        const row = btn.closest('tr');

        if (action === 'reject' && !confirm('Are you sure you want to reject/remove this payment?')) {
            return;
        }

        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('payment_id', paymentId);
            formData.append('action', action);

            const response = await fetch('<?php echo BASE_URL; ?>/app/manager/payments/api/update-payment-status.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                if (action === 'confirm') {
                    const statusCell = row.children[4];
                    statusCell.innerHTML = '<span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: #d4edda; color: #155724;">Confirmed</span>';
                    // Action is now the 9th column (Proof was added before "Recorded by").
                    const actionCell = row.children[8];
                    actionCell.innerHTML = '<span style="color: #ccc;">-</span>';
                } else {
                    row.remove();
                }
            } else {
                alert((data.errors || ['An error occurred, please try again.']).join('\n'));
                btn.disabled = false;
            }
        } catch (err) {
            alert('Could not connect to the server.');
            btn.disabled = false;
        }
    });

    document.getElementById('payerFilterSelect').addEventListener('change', function() {
        const url = new URL(window.location.href);
        if (this.value) {
            url.searchParams.set('payer', this.value);
        } else {
            url.searchParams.delete('payer');
        }
        window.location.href = url.toString();
    });

    document.getElementById('paymentTypeSelect').addEventListener('change', function() {
        const url = new URL(window.location.href);
        if (this.value) {
            url.searchParams.set('payment_type', this.value);
        } else {
            url.searchParams.delete('payment_type');
        }
        window.location.href = url.toString();
    });

    const memberSearchInput = document.getElementById('memberSearchInput');
    memberSearchInput.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        tableBody.querySelectorAll('tr[data-search]').forEach(function(row) {
            const matches = !q || (row.dataset.search || '').indexOf(q) !== -1;
            row.style.display = matches ? '' : 'none';
        });
    });
})();
</script>

<script>
// Shrink an amount's font just enough to fit inside its card (all cards stay the same size)
(function() {
    document.querySelectorAll('.pay-card-value').forEach(function(el) {
        var size = 22;
        el.style.fontSize = size + 'px';
        while (el.scrollWidth > el.clientWidth && size > 12) {
            size--;
            el.style.fontSize = size + 'px';
        }
    });
})();
</script>

<?php renderFooter(); ?>