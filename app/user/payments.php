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

.page-shell {
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-icon svg {
    width: 22px;
    height: 22px;
    stroke: #2e7d32;
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

.section-intro {
    margin: 0 0 14px 0;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.section-intro h2 {
    margin: 0;
    color: #1b3a24;
    font-size: 16px;
    font-weight: 600;
}

.btn-primary {
    background: #2e7d32 !important;
    color: #fff !important;
    border: none !important;
    padding: 9px 16px !important;
    border-radius: 10px !important;
    cursor: pointer !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    line-height: 1.4 !important;
    white-space: nowrap !important;
    width: auto !important;
    flex: 0 0 auto !important;
    box-sizing: border-box !important;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 22px;
}

.stat-card {
    min-height: 88px;
    padding: 16px;
    box-sizing: border-box;
    border-radius: 14px;
    border: none;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
}

.stat-card.green { background: #2e7d32; }
.stat-card.blue { background: #274b81; }
.stat-card.muted { background: #a9a79f; }

.stat-number {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 2px;
}

.stat-number .stat-number-sub {
    font-size: 12px;
    font-weight: 500;
    color: rgba(255,255,255,0.75);
}

.stat-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.85);
}

.data-card {
    background: #ffffff;
    border: 1px solid #eceae4;
    border-radius: 14px;
    padding: 6px 16px 4px;
}

.payment-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.payment-table th {
    text-align: left;
    font-size: 11.5px;
    font-weight: 600;
    color: #667066;
    padding: 12px 6px;
    border-bottom: 1px solid #eceae4;
}
.payment-table td { padding: 12px 6px; border-bottom: 1px solid #eceae4; }
.payment-table tr:last-child td { border-bottom: none; }
.payment-table .amount { font-weight: 700; color: #1b3a24; }

.status-badge { font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
.status-pending { background: #fdf1de; color: #a06b16; }
.status-confirmed { background: #e8f5e9; color: #2e7d32; }

.type-badge { font-size: 10.5px; font-weight: 600; padding: 3px 9px; border-radius: 20px; white-space: nowrap; }

.empty-note { color: #667066; font-size: 13px; padding: 16px 4px; }

@media (max-width: 480px) {
    .page-title { font-size: 17px; }
    .btn-primary { padding: 8px 14px !important; font-size: 13px !important; }
}
</style>

<div class="page-shell">

    <div class="page-header">
        <div class="page-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
        </div>
        <div style="flex:1;">
            <p class="page-title">Payments</p>
            <p class="page-sub">Track your registration and investment payments</p>
        </div>
    </div>

    <?php if (empty($memberDbId)): ?>
        <p class="empty-note">No member profile is linked to your account yet.</p>
    <?php else: ?>

        <div class="section-intro" style="justify-content: flex-end; margin-bottom: 10px;">
            <?php if (!empty($memberDbId)): ?>
                <button id="openPayModalBtn" class="btn-primary">+ Add payment</button>
            <?php endif; ?>
        </div>

        <div class="stat-grid">
            <div class="stat-card <?php echo $eligibility['registration_paid'] ? 'green' : 'muted'; ?>">
                <div class="stat-number">
                    <?php echo $eligibility['registration_paid'] ? 'Paid ₱' . number_format($eligibility['registration_amount'], 0) : 'Not yet paid'; ?>
                </div>
                <div class="stat-label">Registration Fee</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-number">
                    ₱<?php echo number_format($eligibility['total_investment'], 0); ?>
                    <span class="stat-number-sub">/ ₱<?php echo number_format($eligibility['investment_target'], 0); ?></span>
                </div>
                <div class="stat-label">Total Investment</div>
            </div>
        </div>

        <div class="section-intro">
            <h2>Payment History</h2>
        </div>

        <div class="data-card">
            <?php if (empty($paymentHistory)): ?>
                <p class="empty-note">Wala pang payment history.</p>
            <?php else: ?>
                <?php
                    $typeBadgeMeta = [
                        'registration'   => ['label' => 'Registration',   'bg' => '#e8f5e9', 'text' => '#2e7d32'],
                        'investment'     => ['label' => 'Investment',     'bg' => '#e3edfb', 'text' => '#274b81'],
                        'rental'         => ['label' => 'Rental',         'bg' => '#eae6fb', 'text' => '#4b2f9c'],
                        'loan_repayment' => ['label' => 'Loan Repayment', 'bg' => '#fdf1de', 'text' => '#a06b16'],
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
                                    $tMeta = $typeBadgeMeta[$payment['payment_type']] ?? ['label' => ucfirst($payment['payment_type']), 'bg' => '#f0efe9', 'text' => '#667066'];
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
        </div>

    <?php endif; ?>
</div>

<!-- ============================================================
     MODAL - Add payment
     ============================================================ -->
<div id="payModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 14px; padding: 24px; width: 100%; max-width: 400px; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center; color: #1b3a24;">Add payment</h2>
        <button id="closePayModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #667066 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="payModalErrors" style="display: none; background: #fbe6e6; color: #a6322f; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>
        <div id="payModalSuccess" style="display: none; background: #e8f5e9; color: #2e7d32; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;">
            Na-submit na para sa confirmation ng manager.
        </div>

        <form id="payForm" style="text-align: left;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Payment type</label>
            <select name="payment_type" id="payTypeSelect" required style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">
                <option value="">-- Piliin --</option>
                <option value="registration">Registration Fee (₱150)</option>
                <option value="investment">Investment</option>
                <option value="rental">Rental</option>
                <option value="loan_repayment">Loan Repayment</option>
            </select>

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Amount (₱)</label>
            <input type="number" name="amount" id="payAmountInput" step="0.01" min="0" required
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Payment date</label>
            <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>"
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">

            <div style="background: #e3edfb; border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <span style="font-size: 12.5px; color: #274b81;">Pay via GCash, then submit this form.</span>
                <a href="https://gcash.com" target="_blank" rel="noopener"
                   style="flex-shrink: 0; font-size: 12.5px; font-weight: 600; color: #274b81; white-space: nowrap; text-decoration: none;">
                    Pay via GCash &rarr;
                </a>
            </div>

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Notes (optional)</label>
            <input type="text" name="notes" placeholder="e.g. paid via GCash"
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 20px; font-family: inherit; font-size: 14px;">

            <button type="submit" id="paySubmitBtn" class="btn-primary" style="width: 100% !important; padding: 11px !important; font-size: 14px !important;">
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

    if (!openBtn) return;

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