<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('manager');

$filterType = $_GET['payment_type'] ?? '';

$query = "
    SELECT p.*, m.first_name, m.last_name, m.membership_id, u.email as recorded_by_email
    FROM payments p
    JOIN members m ON p.member_id = m.id
    LEFT JOIN users u ON p.recorded_by = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filterType)) {
    $query .= " AND p.payment_type = ?";
    $params[] = $filterType;
}

$query .= " ORDER BY (p.status = 'pending') DESC, p.payment_date DESC, p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("SELECT id, membership_id, last_name, first_name FROM members ORDER BY last_name, first_name");
$members = $stmt2->fetchAll(PDO::FETCH_ASSOC);

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

// ============================================================
// SUMMARY TOTALS (confirmed payments only, respects current filters)
// ============================================================
$totals = ['registration' => 0, 'investment' => 0, 'rental' => 0, 'loan_repayment' => 0];

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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 18px; gap: 16px; position: relative; width: 100%; border-bottom: 2px solid #E4DCC8;">
    <h1 style="margin: 0; flex: 1 1 auto; min-width: 0; position: static !important;">Payments</h1>
    <button id="openModalBtn"
            style="background: #1976d2 !important; color: #fff !important; border: none !important; padding: 10px 18px !important; border-radius: 6px !important; cursor: pointer; font-size: 14px !important; display: inline-block !important; width: auto !important; max-width: 200px !important; flex: 0 0 auto !important; white-space: nowrap !important; position: static !important; top: auto !important; left: auto !important; right: auto !important; float: none !important; margin-left: auto !important;">
        + Add payment
    </button>
</div>

<!-- ============================================================
     OVERALL PAYMENT TOTALS
     ============================================================ -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 28px;">
    <div style="border-radius: 10px; padding: 16px 18px; background: #33502F;">
        <p style="margin: 0 0 6px; font-size: 12.5px; color: #E1EFDE;">Registration</p>
        <p style="margin: 0; font-size: 22px; font-weight: 600; color: #fff;">₱<?php echo number_format($totals['registration'], 2); ?></p>
    </div>
    <div style="border-radius: 10px; padding: 16px 18px; background: #274B81;">
        <p style="margin: 0 0 6px; font-size: 12.5px; color: #DCE7F5;">Investment</p>
        <p style="margin: 0; font-size: 22px; font-weight: 600; color: #fff;">₱<?php echo number_format($totals['investment'], 2); ?></p>
    </div>
    <div style="border-radius: 10px; padding: 16px 18px; background: #4A3F7A;">
        <p style="margin: 0 0 6px; font-size: 12.5px; color: #E4E1F5;">Rental</p>
        <p style="margin: 0; font-size: 22px; font-weight: 600; color: #fff;">₱<?php echo number_format($totals['rental'], 2); ?></p>
    </div>
    <div style="border-radius: 10px; padding: 16px 18px; background: #C1892B;">
        <p style="margin: 0 0 6px; font-size: 12.5px; color: #F6E4C3;">Loan Repayment</p>
        <p style="margin: 0; font-size: 22px; font-weight: 600; color: #fff;">₱<?php echo number_format($totals['loan_repayment'], 2); ?></p>
    </div>
    <div style="border-radius: 10px; padding: 16px 18px; background: #B54A3C;">
        <p style="margin: 0 0 6px; font-size: 12.5px; color: #F6E1DC;">Grand total</p>
        <p style="margin: 0; font-size: 22px; font-weight: 600; color: #fff;">₱<?php echo number_format($grandTotal, 2); ?></p>
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
    </select>
</div>

<table style="width: 100%; border-collapse: collapse; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;" id="paymentsTable">
    <thead>
        <tr style="background: #f5f5f5; text-align: left;">
            <th style="padding: 10px 12px;">Member</th>
            <th style="padding: 10px 12px;">Type</th>
            <th style="padding: 10px 12px;">Amount</th>
            <th style="padding: 10px 12px;">Date</th>
            <th style="padding: 10px 12px;">Status</th>
            <th style="padding: 10px 12px;">Notes</th>
            <th style="padding: 10px 12px;">Recorded by</th>
            <th style="padding: 10px 12px;">Action</th>
        </tr>
    </thead>
    <tbody id="paymentsTableBody">
        <?php if (empty($payments)): ?>
            <tr id="emptyRow">
                <td colspan="8" style="padding: 20px; text-align: center; color: #666;">No payment records.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <?php
                    $meta = paymentTypeMeta($payment['payment_type']);
                    $avatar = memberAvatarColor($payment['membership_id']);
                    $initials = memberInitials($payment['first_name'], $payment['last_name']);
                ?>
                <tr style="border-top: 1px solid #eee;" data-payment-id="<?php echo $payment['id']; ?>"
                    data-search="<?php echo htmlspecialchars(strtolower($payment['last_name'] . ', ' . $payment['first_name'] . ' ' . $payment['membership_id'])); ?>">
                    <td style="padding: 10px 12px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; min-width: 32px; border-radius: 50%; background: <?php echo $avatar['bg']; ?>; color: <?php echo $avatar['text']; ?>; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px;">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <p style="margin: 0; font-weight: 600;"><?php echo htmlspecialchars($payment['last_name'] . ', ' . $payment['first_name']); ?></p>
                                <p style="margin: 0; font-size: 12px; color: #888;"><?php echo htmlspecialchars($payment['membership_id']); ?></p>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 10px 12px;">
                        <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['text']; ?>; white-space: nowrap;">
                            <?php echo htmlspecialchars($meta['label']); ?>
                        </span>
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

<!-- MODAL - Add Payment -->
<div id="paymentModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Add payment</h2>
        <button id="closeModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="modalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px;"></div>

        <form id="paymentForm">
            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Member</label>
                <select name="member_id" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                    <option value="">-- Select member --</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name'] . ' (' . $member['membership_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payment type</label>
                <select name="payment_type" id="paymentTypeInput" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                    <option value="">-- Select --</option>
                    <option value="registration">Registration fee (₱150)</option>
                    <option value="investment">Investment</option>
                    <option value="rental">Rental</option>
                    <option value="loan_repayment">Loan Repayment</option>
                </select>
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
                <button type="submit" id="submitBtn" style="padding: 10px 18px !important; border-radius: 6px; border: none; background: #1976d2 !important; color: #fff !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
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

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        form.reset();
        modalErrors.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    const TYPE_META = {
        registration:   { label: 'Registration',   bg: '#d4edda', text: '#155724' },
        investment:     { label: 'Investment',     bg: '#cce5ff', text: '#004085' },
        rental:         { label: 'Rental',          bg: '#eeedfe', text: '#3c3489' },
        loan_repayment: { label: 'Loan Repayment', bg: '#faeeda', text: '#633806' }
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

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function formatAmount(amount) {
        return parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function buildRow(payment) {
        const meta = TYPE_META[payment.payment_type] || { label: payment.payment_type, bg: '#eee', text: '#555' };
        const avatar = avatarColorFor(payment.membership_id);
        const initials = initialsFor(payment.last_name, payment.first_name);
        const tr = document.createElement('tr');
        tr.style.borderTop = '1px solid #eee';
        tr.dataset.paymentId = payment.id;
        tr.dataset.search = (payment.last_name + ', ' + payment.first_name + ' ' + payment.membership_id).toLowerCase();
        tr.innerHTML = `
            <td style="padding: 10px 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; min-width: 32px; border-radius: 50%; background: ${avatar.bg}; color: ${avatar.text}; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px;">${initials}</div>
                    <div>
                        <p style="margin: 0; font-weight: 600;">${payment.last_name}, ${payment.first_name}</p>
                        <p style="margin: 0; font-size: 12px; color: #888;">${payment.membership_id}</p>
                    </div>
                </div>
            </td>
            <td style="padding: 10px 12px;">
                <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: ${meta.bg}; color: ${meta.text}; white-space: nowrap;">${meta.label}</span>
            </td>
            <td style="padding: 10px 12px; font-weight: 600;">₱${formatAmount(payment.amount)}</td>
            <td style="padding: 10px 12px;">${formatDate(payment.payment_date)}</td>
            <td style="padding: 10px 12px;">
                <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: #d4edda; color: #155724;">Confirmed</span>
            </td>
            <td style="padding: 10px 12px; color: #666;">${payment.notes || '-'}</td>
            <td style="padding: 10px 12px; color: #666;">${payment.recorded_by_email || '-'}</td>
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
                    const actionCell = row.children[7];
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

<?php renderFooter(); ?>