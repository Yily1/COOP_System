<?php
// app/manager/expenses/expenses.php  (3 levels up to the project root)
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');

// Keep this list in sync with api/add-expenses.php
$categories = ['Supplies', 'Repairs and maintenance', 'Transport', 'Utilities', 'Salaries', 'Others'];

// Badge colors per category (same palette as the Payments page)
function expenseCategoryMeta($category) {
    $map = [
        'Supplies'                => ['bg' => '#faeeda', 'text' => '#633806'],
        'Repairs and maintenance' => ['bg' => '#cce5ff', 'text' => '#004085'],
        'Transport'               => ['bg' => '#eeedfe', 'text' => '#3c3489'],
        'Utilities'               => ['bg' => '#d4edda', 'text' => '#155724'],
        'Salaries'                => ['bg' => '#DDEAF6', 'text' => '#185FA5'],
        'Others'                  => ['bg' => '#eeeeee', 'text' => '#555555'],
    ];
    return $map[$category] ?? ['bg' => '#eeeeee', 'text' => '#555555'];
}

$stmt = $pdo->query("
    SELECT e.*, u.email AS recorded_by_email
    FROM expenses e
    LEFT JOIN users u ON u.id = e.recorded_by
    ORDER BY e.expense_date DESC, e.id DESC
");
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAll = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
$totalMonth = (float) $pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM expenses
    WHERE expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND expense_date <  DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')
")->fetchColumn();

renderHeader('Expenses');
?>

<style>
/* Summary cards: same style as the Payments page */
.ex-stat-row { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 28px; }
.ex-stat-card {
    border-radius: 12px; padding: 0 18px; flex: 0 0 160px; width: 160px; height: 92px;
    box-sizing: border-box; color: #fff;
    display: flex; flex-direction: column; justify-content: center;
}
.ex-stat-total { background: #274b81; }
.ex-stat-month { background: #a06b16; }
.ex-stat-label { margin: 0 0 6px; font-size: 13px; }
.ex-stat-value {
    margin: 0; font-size: 22px; font-weight: 700; line-height: 1.15;
    font-family: 'Fraunces', 'Times New Roman', Times, serif;
    font-variant-numeric: lining-nums tabular-nums;
    white-space: nowrap;
}

/* Buttons (global button styles override plain CSS, so !important) */
#exOpenBtn, #exSubmitBtn {
    background: #3B6D11 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important; font-weight: 600 !important;
    color: #fff !important; border: none !important; padding: 10px 18px !important; border-radius: 6px !important;
    cursor: pointer; font-size: 14px !important; width: auto !important; flex: 0 0 auto !important; white-space: nowrap !important;
}
#exOpenBtn { display: inline-block !important; max-width: 200px !important; position: static !important; float: none !important; margin-left: auto !important; }
#exSubmitBtn { display: block !important; width: 100% !important; }
#exOpenBtn:hover, #exSubmitBtn:hover { background: #2e5a0d !important; }

.ex-del-btn {
    display: inline-flex !important; align-items: center; justify-content: center; width: auto !important; max-width: none !important;
    flex: 0 0 auto !important; font-size: 12px; padding: 6px 10px; margin: 0 !important;
    background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;
}

/* Modal */
#exModalBackdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100;
    align-items: center; justify-content: center; padding: 16px;
}
.ex-modal-box { background: #fff; border-radius: 14px; padding: 24px; width: 100%; max-width: 400px; box-sizing: border-box; position: relative; max-height: 90vh; overflow-y: auto; }
.ex-modal-box h2 { margin: 0 0 16px; font-size: 18px; text-align: center; color: #1b3a24; }
.ex-close {
    position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important;
    font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    color: #667066 !important; line-height: 1 !important;
}
.ex-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066; }
.ex-input { width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-size: 14px; font-family: inherit; background: #f7f8f5; }
.ex-input:focus { outline: none; border-color: #2e7d32; background: #fff; }
#exErrors { display: none; background: #fbe6e6; color: #a6322f; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; position: relative; width: 100%;">
    <h1 style="margin: 0; flex: 1 1 auto; min-width: 0; position: static !important;">Expenses</h1>
    <button type="button" id="exOpenBtn">+ Add expense</button>
</div>

<div class="ex-stat-row">
    <div class="ex-stat-card ex-stat-total">
        <p class="ex-stat-label">Total expenses</p>
        <p class="ex-stat-value">₱<?php echo number_format($totalAll, 2); ?></p>
    </div>
    <div class="ex-stat-card ex-stat-month">
        <p class="ex-stat-label">This month</p>
        <p class="ex-stat-value">₱<?php echo number_format($totalMonth, 2); ?></p>
    </div>
</div>

<h2 style="margin: 0 0 12px 0;">Expense history</h2>

<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <input type="text" id="exSearchInput" placeholder="Search category or description"
           style="padding: 8px; border-radius: 6px; border: 1px solid #ccc; width: 240px; max-width: 240px; flex: 0 0 auto; box-sizing: border-box;">
</div>

<div style="overflow-x: auto;">
<table style="width: 100%; border-collapse: collapse; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;" id="expensesTable">
    <thead>
        <tr style="background: #f5f5f5; text-align: left;">
            <th style="padding: 10px 12px;">Date</th>
            <th style="padding: 10px 12px;">Category</th>
            <th style="padding: 10px 12px;">Description</th>
            <th style="padding: 10px 12px;">Amount</th>
            <th style="padding: 10px 12px;">Recorded by</th>
            <th style="padding: 10px 12px;">Action</th>
        </tr>
    </thead>
    <tbody id="expensesTableBody">
        <?php if (empty($expenses)): ?>
            <tr>
                <td colspan="6" style="padding: 20px; text-align: center; color: #666;">No expenses recorded yet.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($expenses as $e): ?>
                <?php $meta = expenseCategoryMeta($e['category']); ?>
                <tr style="border-top: 1px solid #eee;"
                    data-search="<?php echo htmlspecialchars(strtolower($e['category'] . ' ' . $e['description'])); ?>">
                    <td style="padding: 10px 12px;"><?php echo date('M d, Y', strtotime($e['expense_date'])); ?></td>
                    <td style="padding: 10px 12px;">
                        <span style="padding: 2px 10px; border-radius: 4px; font-size: 12px; background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['text']; ?>; white-space: nowrap;">
                            <?php echo htmlspecialchars($e['category']); ?>
                        </span>
                    </td>
                    <td style="padding: 10px 12px; color: #666;"><?php echo htmlspecialchars($e['description']); ?></td>
                    <td style="padding: 10px 12px; font-weight: 600;">₱<?php echo number_format($e['amount'], 2); ?></td>
                    <td style="padding: 10px 12px; color: #666;"><?php echo htmlspecialchars($e['recorded_by_email'] ?? '-'); ?></td>
                    <td style="padding: 10px 12px;">
                        <button type="button" class="ex-del-btn" data-id="<?php echo (int) $e['id']; ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- ============================================================
     MODAL - Add expense
     ============================================================ -->
<div id="exModalBackdrop">
    <div class="ex-modal-box">
        <h2>Add expense</h2>
        <button type="button" class="ex-close" id="exCloseBtn">&times;</button>
        <div id="exErrors"></div>

        <form id="exForm">
            <label class="ex-label">Date</label>
            <input type="date" name="expense_date" id="exDate" class="ex-input" required>

            <label class="ex-label">Category</label>
            <select name="category" class="ex-input" required>
                <option value="">-- Select category --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="ex-label">Description</label>
            <input type="text" name="description" class="ex-input" maxlength="255" placeholder="e.g. Fuel for corn sheller" required>

            <label class="ex-label">Amount (₱)</label>
            <input type="number" name="amount" class="ex-input" step="0.01" min="0.01" required>

            <button type="submit" id="exSubmitBtn">Save expense</button>
        </form>
    </div>
</div>

<script>
(function() {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/expenses/api/';

    const backdrop = document.getElementById('exModalBackdrop');
    const form = document.getElementById('exForm');
    const errors = document.getElementById('exErrors');
    const submitBtn = document.getElementById('exSubmitBtn');

    function todayLocal() {
        const d = new Date();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    document.getElementById('exOpenBtn').addEventListener('click', function() {
        form.reset();
        document.getElementById('exDate').value = todayLocal();
        errors.style.display = 'none';
        backdrop.style.display = 'flex';
    });
    document.getElementById('exCloseBtn').addEventListener('click', function() { backdrop.style.display = 'none'; });
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) backdrop.style.display = 'none'; });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errors.style.display = 'none';
        submitBtn.disabled = true;
        try {
            const res = await fetch(AJAX_BASE + 'add-expenses.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                errors.textContent = data.message || 'Could not save the expense.';
                errors.style.display = 'block';
            }
        } catch (err) {
            errors.textContent = 'Could not connect to server.';
            errors.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
        }
    });

    document.querySelectorAll('.ex-del-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!confirm('Delete this expense? This cannot be undone.')) return;
            try {
                const fd = new FormData();
                fd.append('id', btn.dataset.id);
                const res = await fetch(AJAX_BASE + 'delete-expenses.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not delete the expense.');
                }
            } catch (err) {
                alert('Could not connect to server.');
            }
        });
    });

    document.getElementById('exSearchInput').addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('#expensesTableBody tr[data-search]').forEach(function(row) {
            row.style.display = (!q || row.dataset.search.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>

<script>
// Shrink an amount's font just enough to fit inside its card
(function() {
    document.querySelectorAll('.ex-stat-value').forEach(function(el) {
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