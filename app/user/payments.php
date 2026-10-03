<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/payment-functions.php';

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

renderHeader('Payments');
?>

<style>
.payment-card {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
    background: #ffffff;
    padding: 25px 28px;
    box-sizing: border-box;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.payment-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 16px; flex-wrap: wrap; }
.payment-card-header-right { display: flex; align-items: center; gap: 14px; }
.payment-card-header h1 { margin: 0; color: #2e7d32; font-size: 25px; font-weight: 500; }
.payment-card h3 { color: #333; font-size: 18px; font-weight: 500; }
.add-payment-btn {
    background: #2e7d32 !important;
    color: #fff !important;
    border: none !important;
    padding: 9px 16px !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    line-height: 1.4 !important;
    white-space: nowrap !important;
    width: auto !important;
    flex: 0 0 auto !important;
    box-sizing: border-box !important;
}
.stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; margin-bottom: 20px; }
.stat-card { min-height: 100px; padding: 20px 22px; box-sizing: border-box; border-radius: 10px; color: white; box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12); display: flex; flex-direction: column; justify-content: center; }
.stat-number { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
.stat-label { font-size: 14px; opacity: 0.95; }
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

@media (max-width: 768px) {
    .stat-grid { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .payment-card-header {
        flex-wrap: nowrap !important;
        gap: 8px !important;
    }
    .payment-card-header h1 {
        font-size: 18px !important;
        white-space: nowrap;
    }
    .add-payment-btn {
        padding: 8px 14px !important;
        font-size: 13px !important;
    }
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
        <div class="payment-card-header-right">
            <?php if (!empty($memberDbId)): ?>
                <button id="openPayModalBtn" class="add-payment-btn">+ Add payment</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($memberDbId)): ?>
        <p style="color: #666;">No member profile is linked to your account yet.</p>
    <?php else: ?>

        <div class="stat-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="stat-card" style="background: <?php echo $eligibility['registration_paid'] ? 'linear-gradient(135deg, #66bb6a 0%, #43a047 100%)' : 'linear-gradient(135deg, #bdbdbd 0%, #9e9e9e 100%)'; ?>;">
                <div class="stat-number">
                    <?php echo $eligibility['registration_paid'] ? 'Paid ₱' . number_format($eligibility['registration_amount'], 0) : 'Not yet paid'; ?>
                </div>
                <div class="stat-label">Registration Fee</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #42a5f5 0%, #1976d2 100%);">
                <div class="stat-number">
                    ₱<?php echo number_format($eligibility['total_investment'], 0); ?>
                    <span style="font-size: 13px; opacity: .85;">/ ₱<?php echo number_format($eligibility['investment_target'], 0); ?></span>
                </div>
                <div class="stat-label">Total Investment</div>
            </div>
        </div>

        <h3 style="margin-top: 20px;">Payment History</h3>
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
                ];
            ?>
            <div style="overflow-x:auto;">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
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
                                </td>
                                <td class="amount">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
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
                </select>
            </div>

            <div class="form-group">
                <label for="payAmountInput">Amount (₱)</label>
                <input type="number" name="amount" id="payAmountInput" step="0.01" min="0" required>
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

    const proofInput = document.getElementById('payProofInput');
    const proofDrop = document.getElementById('payProofDrop');
    const proofEmpty = document.getElementById('payProofEmpty');
    const proofDone = document.getElementById('payProofDone');
    const proofName = document.getElementById('payProofName');
    const proofRemove = document.getElementById('payProofRemove');

    if (!openBtn) return; // walang member profile, wala ring button

    const MAX_PROOF_BYTES = 5 * 1024 * 1024;

    function showError(msg) {
        errorsEl.innerHTML = msg;
        errorsEl.style.display = 'block';
    }

    function resetProofUI() {
        proofInput.value = '';
        proofDone.style.display = 'none';
        proofEmpty.style.display = 'block';
    }

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        form.reset();
        resetProofUI();
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

    // Auto-fill ng ₱150 kapag "Registration Fee" ang pinili
    typeSelect.addEventListener('change', function() {
        if (typeSelect.value === 'registration') {
            amountInput.value = '150';
        }
    });

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
                form.reset();
                resetProofUI();
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