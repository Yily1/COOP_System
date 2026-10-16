<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');
date_default_timezone_set('Asia/Manila');

$tableMissing = false;
$expenses = [];
$total = 0.0;
$thisMonth = 0.0;

try {
    $stmt = $pdo->query("
        SELECT e.*, u.email AS recorded_by_email
        FROM expenses e
        LEFT JOIN users u ON u.id = e.recorded_by
        ORDER BY e.expense_date DESC, e.id DESC
    ");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentMonth = date('Y-m');
    foreach ($expenses as $e) {
        $total += (float) $e['amount'];
        if (substr($e['expense_date'], 0, 7) === $currentMonth) {
            $thisMonth += (float) $e['amount'];
        }
    }
} catch (PDOException $ex) {
    $tableMissing = true;
}

renderHeader('Expenses');
?>

<style>
.ex-head { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
.ex-head h1 { font-size: 24px; color: #2c2c2a; margin: 0; font-weight: 600; }

.ex-btn {
    display: inline-flex !important; align-items: center; gap: 6px; width: auto !important; flex: 0 0 auto !important;
    border-radius: 6px !important; padding: 9px 16px !important; font-size: 13px !important; font-weight: 600 !important;
    cursor: pointer !important; border: none !important; text-transform: none !important; letter-spacing: normal !important;
    background: #3B6D11 !important; color: #fff !important;
}
.ex-btn:hover { background: #2e5a0d !important; }

.ex-stats { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 24px; }
.ex-stat { border-radius: 10px; padding: 16px 18px; color: #fff; flex: 0 0 170px; width: 170px; box-sizing: border-box; }
.ex-stat p { margin: 0; }
.ex-stat .l { font-size: 12.5px; opacity: .9; margin-bottom: 6px; }
.ex-stat .v { font-size: 22px; font-weight: 600; }
.ex-stat-total { background: #a84438; }
.ex-stat-month { background: #C1892B; }

.ex-toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.ex-toolbar input {
    padding: 8px 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 13px; width: 240px !important; max-width: 100%; box-sizing: border-box;
}

.ex-table-wrap { overflow-x: auto; border: 1px solid #e2e0d5; border-radius: 10px; background: #fff; }
.ex-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ex-table th { text-align: left; padding: 12px 14px; background: #fff; border-bottom: 1px solid #e2e0d5; color: #6b7280; font-weight: 600; white-space: nowrap; }
.ex-table td { padding: 12px 14px; border-bottom: 1px solid #f0f0ea; color: #2c2c2a; vertical-align: top; }
.ex-table tbody tr:last-child td { border-bottom: none; }
.ex-amount { font-weight: 600; white-space: nowrap; }
.ex-empty { padding: 28px; text-align: center; color: #6b7280; font-size: 13px; }

.ex-setup { background: #FAEEDA; border-left: 3px solid #EF9F27; color: #854F0B; border-radius: 6px; padding: 14px 16px; font-size: 13.5px; line-height: 1.6; }
.ex-setup pre { background: #fff; border-radius: 6px; padding: 10px; margin: 10px 0 0; font-size: 12px; white-space: pre-wrap; color: #333; }

/* ===== Add modal ===== */
#exBackdrop {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1000;
    align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;
}
.ex-modal {
    background: #fff; border-radius: 14px; padding: 28px 24px 24px; width: 100%; max-width: 400px; box-sizing: border-box;
    position: relative; display: flex; flex-direction: column; max-height: calc(100vh - 32px); overflow: hidden;
}
.ex-modal h2 {
    background: none !important; color: #1b3a24 !important; margin: 0 0 14px !important; padding: 0 40px 0 0 !important;
    font-size: 20px !important; font-weight: 600 !important; text-align: left !important; flex: 0 0 auto;
}
#exClose {
    position: absolute !important; top: 16px !important; right: 16px !important; display: flex !important;
    align-items: center !important; justify-content: center !important; width: 28px !important; height: 28px !important;
    min-width: 0 !important; max-width: 28px !important; padding: 0 !important; margin: 0 !important; background: #fff !important;
    border: 1px solid #cfd8e3 !important; border-radius: 50% !important; font-size: 16px !important; line-height: 1 !important;
    cursor: pointer !important; color: #274b81 !important; box-shadow: none !important; z-index: 10; user-select: none;
}
#exClose:hover { background: #eef3fb !important; border-color: #274b81 !important; }
#exErrors { display: none; background: #fbe6e6; color: #a6322f; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; flex: 0 0 auto; }

/* Straight (single-column) form */
.ex-form { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 4px; display: flex; flex-direction: column; gap: 10px; }
.ex-form .form-group { margin: 0 !important; min-width: 0; }
.ex-form .form-group label { display: block; font-size: 13px; margin-bottom: 3px !important; }
.ex-form .form-group input {
    width: 100%; box-sizing: border-box; padding: 7px 9px !important; font-size: 13px !important;
}
.ex-form button[type="submit"] {
    position: sticky; bottom: 0; z-index: 2; width: 100% !important; box-sizing: border-box;
    margin: 4px 0 0 !important; padding: 10px !important; background: #639a20 !important; color: #fff !important; border: none !important;
    border-radius: 6px !important; cursor: pointer; font-size: 14px !important; box-shadow: 0 -8px 8px -4px #fff;
}

@media (max-width: 700px) {
    .ex-table thead { display: none; }
    .ex-table, .ex-table tbody, .ex-table tr, .ex-table td { display: block; width: 100%; box-sizing: border-box; }
    .ex-table tr { border-bottom: 1px solid #f0f0ea; padding: 10px 0; }
    .ex-table tbody tr:last-child { border-bottom: none; }
    .ex-table td { border-bottom: none !important; padding: 4px 14px; }
    .ex-table td::before {
        content: attr(data-label); display: block; font-size: 11px; font-weight: 600; color: #6b7280;
        text-transform: uppercase; margin-bottom: 2px;
    }
}
@media (max-width: 560px) {
    #exBackdrop { padding: 10px 34px; }
    .ex-modal { padding: 18px 16px 14px; max-width: 100%; border-radius: 12px; max-height: calc(100vh - 20px); }
    .ex-modal h2 { font-size: 17px !important; margin: 0 0 10px !important; }
    #exClose { top: 12px !important; right: 12px !important; }
    .ex-form { gap: 8px; }
    .ex-form .form-group label { font-size: 12px; }
    .ex-stat { flex: 1 1 140px; width: auto; }
}
</style>

<div class="ex-head">
    <h1>Expenses</h1>
    <?php if (!$tableMissing): ?>
        <button type="button" class="ex-btn" id="exOpenAdd">+ Add expense</button>
    <?php endif; ?>
</div>

<?php if ($tableMissing): ?>

    <div class="ex-setup">
        <strong>The expenses table hasn&rsquo;t been created yet.</strong><br>
        Run this in phpMyAdmin (SQL tab), then refresh this page:
        <pre>CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  recorded_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expense_date (expense_date)
);</pre>
    </div>

<?php else: ?>

    <div class="ex-stats">
        <div class="ex-stat ex-stat-total">
            <p class="l">Total expenses</p>
            <p class="v">₱<?php echo number_format($total, 2); ?></p>
        </div>
        <div class="ex-stat ex-stat-month">
            <p class="l">This month</p>
            <p class="v">₱<?php echo number_format($thisMonth, 2); ?></p>
        </div>
    </div>

    <div class="ex-toolbar">
        <input type="search" id="exSearch" placeholder="Search expenses">
    </div>

    <?php if (empty($expenses)): ?>
        <div class="ex-table-wrap"><p class="ex-empty">No expenses recorded yet. Click &ldquo;+ Add expense&rdquo; to add the first one.</p></div>
    <?php else: ?>
        <div class="ex-table-wrap">
            <table class="ex-table" id="exTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr data-search="<?php echo htmlspecialchars(mb_strtolower($e['description'])); ?>">
                            <td data-label="Date" style="white-space:nowrap;"><?php echo date('M j, Y', strtotime($e['expense_date'])); ?></td>
                            <td data-label="Description"><?php echo htmlspecialchars($e['description']); ?></td>
                            <td data-label="Amount" class="ex-amount">₱<?php echo number_format($e['amount'], 2); ?></td>
                            <td data-label="Recorded by" style="color:#6b7280;"><?php echo htmlspecialchars($e['recorded_by_email'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============ ADD MODAL ============ -->
    <div id="exBackdrop">
        <div class="ex-modal">
            <span id="exClose" role="button" tabindex="0" aria-label="Close">&times;</span>
            <h2>Add expense</h2>
            <div id="exErrors"></div>

            <form id="exForm" class="ex-form">
                <div class="form-group">
                    <label for="exDate">Date</label>
                    <input type="date" name="expense_date" id="exDate" required>
                </div>

                <div class="form-group">
                    <label for="exDesc">Description</label>
                    <input type="text" name="description" id="exDesc" maxlength="255" placeholder="e.g. Fuel for delivery truck" required>
                </div>

                <div class="form-group">
                    <label for="exAmount">Amount (₱)</label>
                    <input type="number" name="amount" id="exAmount" step="0.01" min="0.01" required>
                </div>

                <button type="submit" id="exSubmit">Save expense</button>
            </form>
        </div>
    </div>

    <script>
    (function() {
        const API = '<?php echo BASE_URL; ?>/app/manager/expenses/api/';
        const today = '<?php echo date('Y-m-d'); ?>';

        const backdrop = document.getElementById('exBackdrop');
        const form = document.getElementById('exForm');
        const errors = document.getElementById('exErrors');
        const submitBtn = document.getElementById('exSubmit');

        function openModal() { backdrop.style.display = 'flex'; }
        function closeModal() { backdrop.style.display = 'none'; errors.style.display = 'none'; }

        document.getElementById('exOpenAdd').addEventListener('click', function() {
            form.reset();
            document.getElementById('exDate').value = today;
            errors.style.display = 'none';
            openModal();
        });

        document.getElementById('exClose').addEventListener('click', closeModal);
        document.getElementById('exClose').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); closeModal(); }
        });
        backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            errors.style.display = 'none';
            submitBtn.disabled = true;
            try {
                const res = await fetch(API + 'add-expense.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    errors.textContent = data.message || 'Could not save the expense.';
                    errors.style.display = 'block';
                }
            } catch (err) {
                errors.textContent = 'Could not connect to the server.';
                errors.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
            }
        });

        // Search (client-side)
        const search = document.getElementById('exSearch');
        if (search) {
            search.addEventListener('input', function() {
                const q = search.value.trim().toLowerCase();
                document.querySelectorAll('#exTable tbody tr').forEach(function(row) {
                    row.style.display = (!q || row.dataset.search.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }
    })();
    </script>

<?php endif; ?>

<?php renderFooter(); ?>