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
.payment-status { margin-top: 10px; padding: 10px 14px; background: #d4edda; color: #155724; border-radius: 6px; }
.payment-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.payment-table th { padding: 10px 12px; background: #f5f5f5; text-align: left; }
.payment-table td { padding: 10px 12px; border-top: 1px solid #eee; }
.payment-table .amount { font-weight: 600; }
.status-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; }
.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.type-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }

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

        <?php if ($eligibility['eligible_for_associate']): ?>
            <p class="payment-status">✓ You've met the requirements to become an Associate. Talk to your manager for the upgrade.</p>
        <?php endif; ?>
        <?php if ($eligibility['eligible_for_regular']): ?>
            <p class="payment-status">✓ You've reached the ₱1,500 investment target to become a Regular member. Talk to your manager for the upgrade.</p>
        <?php endif; ?>

        <h3 style="margin-top: 20px;">Payment History</h3>
        <?php if (empty($paymentHistory)): ?>
            <p style="color: #666;">Wala pang payment history.</p>
        <?php else: ?>
            <?php
                // Same type badge colors as the manager-side Payments page,
                // so a "Rental" or "Utang" entry looks consistent everywhere.
                $typeBadgeMeta = [
                    'registration' => ['label' => 'Registration', 'bg' => '#d4edda', 'text' => '#155724'],
                    'investment'   => ['label' => 'Investment',   'bg' => '#cce5ff', 'text' => '#004085'],
                    'rental'       => ['label' => 'Rental',        'bg' => '#eeedfe', 'text' => '#3c3489'],
                    'utang'        => ['label' => 'Utang',         'bg' => '#faeeda', 'text' => '#633806'],
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
<div id="payModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 400px; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Add payment</h2>
        <button id="closePayModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="payModalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>
        <div id="payModalSuccess" style="display: none; background: #d4edda; color: #155724; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;">
            Na-submit na para sa confirmation ng manager.
        </div>

        <form id="payForm" style="text-align: left;">
            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payment type</label>
            <select name="payment_type" id="payTypeSelect" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">
                <option value="">-- Piliin --</option>
                <option value="registration">Registration Fee (₱150)</option>
                <option value="investment">Investment</option>
                <option value="rental">Rental</option>
                <option value="utang">Utang</option>
            </select>

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Amount (₱)</label>
            <input type="number" name="amount" id="payAmountInput" step="0.01" min="0" required
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Payment date</label>
            <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>"
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">

            <div style="background: #e3f2fd; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <span style="font-size: 13px; color: #0d47a1;">Pay via GCash, then submit this form.</span>
                <a href="https://gcash.com" target="_blank" rel="noopener"
                   style="flex-shrink: 0; font-size: 13px; font-weight: 600; color: #0d47a1; white-space: nowrap; text-decoration: none;">
                    Pay via GCash &rarr;
                </a>
            </div>

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Notes (optional)</label>
            <input type="text" name="notes" placeholder="e.g. paid via GCash"
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 20px;">

            <button type="submit" id="paySubmitBtn"
                    style="width: 100%; box-sizing: border-box; background: #1976d2 !important; color: #fff !important; border: none !important; padding: 10px !important; border-radius: 6px !important; cursor: pointer; font-size: 14px !important;">
                Submit for confirmation
            </button>
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

    if (!openBtn) return; // walang member profile, wala ring button

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        form.reset();
        errorsEl.style.display = 'none';
        successEl.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    // Auto-fill ng ₱150 kapag "Registration Fee" ang pinili
    typeSelect.addEventListener('change', function() {
        if (typeSelect.value === 'registration') {
            amountInput.value = '150';
        }
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsEl.style.display = 'none';
        successEl.style.display = 'none';
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
                errorsEl.textContent = 'Server error. Tingnan ang console (F12).';
                errorsEl.style.display = 'block';
                return;
            }

            if (data.success) {
                successEl.style.display = 'block';
                form.reset();
                setTimeout(() => { window.location.reload(); }, 1200);
            } else {
                errorsEl.innerHTML = (data.errors || ['May error, subukan ulit.']).join('<br>');
                errorsEl.style.display = 'block';
            }
        } catch (err) {
            errorsEl.textContent = 'Hindi makonekta sa server: ' + err.message;
            errorsEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit for confirmation';
        }
    });
})();
</script>

<?php renderFooter(); ?>