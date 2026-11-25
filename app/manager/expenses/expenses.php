<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');
date_default_timezone_set('Asia/Manila');

$tableMissing = false;
$expenses = [];
$thisMonth = 0.0;

// Mga category (galing sa functions.php, iisang listahan para sa page at API)
$categories = getExpenseCategories();
$totals = array_fill_keys($categories, 0.0);

// Kulay ng category pill sa table
$catClass = [
    'Operational'            => 'ex-cat-op',
    'Repair and Maintenance' => 'ex-cat-rm',
];

try {
    $in = implode(',', array_fill(0, count($categories), '?'));
    $stmt = $pdo->prepare("
        SELECT e.*, u.email AS recorded_by_email
        FROM expenses e
        LEFT JOIN users u ON u.id = e.recorded_by
        WHERE e.category IN ($in)
    ");
    $stmt->execute($categories);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sort sa PHP (hindi SQL): pinakamataas na date sa taas, pinakamababa sa baba
    // (hal. Nov 15 -> Nov 14), kapareho ng Released group sa resources.
    // Kapag pareho ang date, mas mataas na id (mas bagong naitala) ang nasa taas.
    usort($expenses, function ($a, $b) {
        $dateCmp = strcmp($b['expense_date'], $a['expense_date']);
        if ($dateCmp !== 0) return $dateCmp;
        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    $currentMonth = date('Y-m');
    foreach ($expenses as $e) {
        $totals[$e['category']] += (float) $e['amount'];
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
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
    /* Ang mga halaga ay tugma sa shared theme (--c-*). Lahat ng text ay 4.5:1+ sa background nito. */
    --ex-brand: #3B6D11;          /* primary buttons, tabs */
    --ex-brand-hover: #2e5a0d;
    --ex-brand-soft: #EAF3DE;
    --ex-brand-ink: #27500A;      /* text sa ibabaw ng brand-soft (8.2:1) */
    --ex-ink: #1b3a24;            /* headings */
    --ex-text: #2c3e2c;           /* body text */
    --ex-muted: #5a645a;          /* secondary text (6.2:1 sa puti) */
    --ex-danger: #a6322f;         /* danger text / solid (6.7:1 sa puti) */
    --ex-danger-soft: #fbe6e6;
    --ex-amber: #8a5a0b;          /* solid amber, puting text (5.9:1) */
    --ex-warn-bg: #FAEEDA;
    --ex-warn-ink: #633806;       /* text sa ibabaw ng warn-bg (8.7:1) */
    --ex-warn-line: #EF9F27;
    --ex-divider: #e8ede0;        /* decorative divider lang */
    --ex-field-border: #7a857a;   /* border ng inputs/controls (3.8:1, kailangan ng 3:1) */
    --ex-placeholder: #667066;
    --ex-focus-ring: rgba(59, 109, 17, 0.18);
}

.ex-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; gap: 16px; position: relative; width: 100%;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
}
.ex-head h1 {
    margin: 0; flex: 1 1 auto; min-width: 0; position: static !important;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif; color: var(--ex-ink); font-size: 26px; font-weight: 600;
}

.ex-btn {
    background: var(--ex-brand) !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
    font-weight: 600 !important; color: #fff !important; border: none !important;
    padding: 11px 22px !important; border-radius: 10px !important; cursor: pointer;
    font-size: 14px !important; font-family: 'Inter', 'Segoe UI', Arial, sans-serif !important;
    display: inline-block !important; width: auto !important; max-width: 220px !important;
    flex: 0 0 auto !important; white-space: nowrap !important;
    position: static !important; float: none !important; margin-left: auto !important;
    text-transform: none !important; letter-spacing: normal !important;
}
.ex-btn:hover { background: var(--ex-brand-hover) !important; }

/* Keyboard focus: nakikita sa lahat ng button */
.ex-btn:focus-visible,
.ex-tab:focus-visible,
#exClose:focus-visible,
.ex-form button[type="submit"]:focus-visible {
    outline: 2px solid var(--ex-brand) !important;
    outline-offset: 2px;
}

.ex-stats { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 28px; font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
.ex-stat {
    border-radius: 12px; padding: 0 18px; color: #fff; flex: 0 0 160px; width: 160px; height: 92px;
    box-sizing: border-box; display: flex; flex-direction: column; justify-content: center;
}
.ex-stat p { margin: 0; }
/* Tinanggal ang opacity: binababa nito ang contrast ng maliit na label */
.ex-stat .l { font-size: 13px; font-weight: 500; margin-bottom: 6px; }
.ex-stat .v {
    font-size: 28px; font-weight: 700; line-height: 1.1;
    font-family: 'Fraunces', 'Times New Roman', Times, serif;
    font-variant-numeric: lining-nums tabular-nums; white-space: nowrap;
}
.ex-stat-total  { background: var(--ex-danger); }
.ex-stat-repair { background: var(--ex-brand); }
.ex-stat-month  { background: var(--ex-amber); }

/* ===== Filter tabs (All / Operational / Repair and Maintenance) ===== */
.ex-tabs {
    display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
}
.ex-tab {
    flex: 0 0 auto !important; width: auto !important; max-width: none !important; margin: 0 !important;
    background: #fff !important; color: var(--ex-brand) !important; border: 1px solid var(--ex-field-border) !important;
    border-radius: 999px !important; padding: 6px 14px !important; font-size: 13px !important; font-weight: 600 !important;
    font-family: inherit !important; cursor: pointer; box-shadow: none !important;
    text-transform: none !important; letter-spacing: normal !important;
}
.ex-tab:hover { background: var(--ex-brand-soft) !important; }
.ex-tab.active { background: var(--ex-brand) !important; color: #fff !important; border-color: var(--ex-brand) !important; }

.ex-table-wrap { overflow-x: auto; border: 1px solid var(--ex-divider); border-radius: 10px; background: #fff; }
.ex-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ex-table th { text-align: left; padding: 12px 14px; background: #fff; border-bottom: 1px solid var(--ex-divider); color: var(--ex-muted); font-weight: 600; white-space: nowrap; }
.ex-table td { padding: 12px 14px; border-bottom: 1px solid var(--ex-divider); color: var(--ex-text); vertical-align: top; }
.ex-table tbody tr:last-child td { border-bottom: none; }
.ex-amount { font-weight: 600; white-space: nowrap; }
.ex-empty { padding: 28px; text-align: center; color: var(--ex-muted); font-size: 13px; }
.ex-recorded { color: var(--ex-muted); }

/* Category pill */
.ex-cat {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}
.ex-cat-op { background: var(--ex-brand-soft); color: var(--ex-brand-ink); }
.ex-cat-rm { background: var(--ex-warn-bg); color: var(--ex-warn-ink); }

.ex-setup { background: var(--ex-warn-bg); border-left: 3px solid var(--ex-warn-line); color: var(--ex-warn-ink); border-radius: 6px; padding: 14px 16px; font-size: 13.5px; line-height: 1.6; }
.ex-setup pre { background: #fff; border-radius: 6px; padding: 10px; margin: 10px 0 0; font-size: 12px; white-space: pre-wrap; color: var(--ex-text); }

#exBackdrop {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1000;
    align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;
}
.ex-modal {
    background: #fff; border-radius: 14px; padding: 28px 24px 24px; width: 100%; max-width: 400px; box-sizing: border-box;
    position: relative; display: flex; flex-direction: column; max-height: calc(100vh - 32px); overflow: hidden;
}
.ex-modal h2 {
    background: none !important; color: var(--ex-ink) !important; margin: 0 0 14px !important; padding: 0 40px 0 0 !important;
    font-size: 20px !important; font-weight: 600 !important; text-align: left !important; flex: 0 0 auto;
}
#exClose {
    position: absolute !important; top: 16px !important; right: 16px !important; display: flex !important;
    align-items: center !important; justify-content: center !important; width: 28px !important; height: 28px !important;
    min-width: 0 !important; max-width: 28px !important; padding: 0 !important; margin: 0 !important; background: #fff !important;
    border: 1px solid var(--ex-field-border) !important; border-radius: 50% !important; font-size: 16px !important; line-height: 1 !important;
    cursor: pointer !important; color: var(--ex-muted) !important; box-shadow: none !important; z-index: 10; user-select: none;
}
#exClose:hover { background: var(--ex-brand-soft) !important; border-color: var(--ex-brand) !important; color: var(--ex-brand-ink) !important; }
#exErrors { display: none; background: var(--ex-danger-soft); color: var(--ex-danger); padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; flex: 0 0 auto; }

.ex-form { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 4px; display: flex; flex-direction: column; gap: 10px; }
.ex-form .form-group { margin: 0 !important; min-width: 0; }
.ex-form .form-group label { display: block; font-size: 13px; margin-bottom: 3px !important; }
.ex-form .form-group input,
.ex-form .form-group select {
    width: 100%; box-sizing: border-box; padding: 7px 9px !important; font-size: 13px !important;
    border: 1px solid var(--ex-field-border);
}
.ex-form .form-group input::placeholder { color: var(--ex-placeholder); opacity: 1; }
.ex-form .form-group input:focus,
.ex-form .form-group select:focus {
    outline: none; border-color: var(--ex-brand); box-shadow: 0 0 0 3px var(--ex-focus-ring);
}
.ex-form button[type="submit"] {
    position: sticky; bottom: 0; z-index: 2; width: 100% !important; box-sizing: border-box;
    margin: 4px 0 0 !important; padding: 10px !important; background: var(--ex-brand) !important; color: #fff !important; border: none !important;
    border-radius: 6px !important; cursor: pointer; font-size: 14px !important; box-shadow: 0 -8px 8px -4px #fff;
}
.ex-form button[type="submit"]:hover { background: var(--ex-brand-hover) !important; }
.ex-form button[type="submit"]:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 700px) {
    .ex-table thead { display: none; }
    .ex-table, .ex-table tbody, .ex-table tr, .ex-table td { display: block; width: 100%; box-sizing: border-box; }
    .ex-table tr { border-bottom: 1px solid var(--ex-divider); padding: 10px 0; }
    .ex-table tbody tr:last-child { border-bottom: none; }
    .ex-table td { border-bottom: none !important; padding: 4px 14px; }
    .ex-table td::before {
        content: attr(data-label); display: block; font-size: 11px; font-weight: 600; color: var(--ex-muted);
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
  category VARCHAR(30) NOT NULL DEFAULT 'Operational',
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
            <p class="l">Operational expenses</p>
            <p class="v">₱<?php echo number_format($totals['Operational'], 2); ?></p>
        </div>
        <div class="ex-stat ex-stat-repair">
            <p class="l">Repair and Maintenance</p>
            <p class="v">₱<?php echo number_format($totals['Repair and Maintenance'], 2); ?></p>
        </div>
        <div class="ex-stat ex-stat-month">
            <p class="l">This month</p>
            <p class="v">₱<?php echo number_format($thisMonth, 2); ?></p>
        </div>
    </div>

    <?php if (empty($expenses)): ?>
        <div class="ex-table-wrap"><p class="ex-empty">No expenses recorded yet. Click &ldquo;+ Add expense&rdquo; to add the first one.</p></div>
    <?php else: ?>

        <div class="ex-tabs" id="exTabs">
            <button type="button" class="ex-tab active" data-filter="all">All</button>
            <?php foreach ($categories as $c): ?>
                <button type="button" class="ex-tab" data-filter="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></button>
            <?php endforeach; ?>
        </div>

        <div class="ex-table-wrap">
            <table class="ex-table" id="exTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr data-category="<?php echo htmlspecialchars($e['category']); ?>">
                            <td data-label="Date" style="white-space:nowrap;"><?php echo date('M j, Y', strtotime($e['expense_date'])); ?></td>
                            <td data-label="Category">
                                <span class="ex-cat <?php echo $catClass[$e['category']] ?? 'ex-cat-op'; ?>"><?php echo htmlspecialchars($e['category']); ?></span>
                            </td>
                            <td data-label="Description"><?php echo htmlspecialchars($e['description']); ?></td>
                            <td data-label="Amount" class="ex-amount">₱<?php echo number_format($e['amount'], 2); ?></td>
                            <td data-label="Recorded by" class="ex-recorded"><?php echo htmlspecialchars($e['recorded_by_email'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="ex-empty" id="exNoRows" style="display:none;">No expenses in this category yet.</p>
        </div>
    <?php endif; ?>

    <div id="exBackdrop">
        <div class="ex-modal">
            <span id="exClose" role="button" tabindex="0" aria-label="Close">&times;</span>
            <h2>Add expense</h2>
            <div id="exErrors"></div>

            <form id="exForm" class="ex-form">
                <div class="form-group">
                    <label for="exCategory">Category</label>
                    <select name="category" id="exCategory" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
        const catSel = document.getElementById('exCategory');
        const desc = document.getElementById('exDesc');

        // Placeholder ng Description depende sa category
        const hints = {
            'Operational': 'e.g. Fuel for delivery truck',
            'Repair and Maintenance': 'e.g. Corn sheller repair'
        };
        function updateHint() { desc.placeholder = hints[catSel.value] || ''; }
        catSel.addEventListener('change', updateHint);

        function openModal() { backdrop.style.display = 'flex'; }
        function closeModal() { backdrop.style.display = 'none'; errors.style.display = 'none'; }

        document.getElementById('exOpenAdd').addEventListener('click', function() {
            form.reset();
            document.getElementById('exDate').value = today;

            // Kung may category tab na napili, iyon na ang naka-select sa form
            const activeTab = document.querySelector('.ex-tab.active');
            if (activeTab && activeTab.dataset.filter !== 'all') {
                catSel.value = activeTab.dataset.filter;
            }
            updateHint();

            errors.style.display = 'none';
            openModal();
        });

        document.getElementById('exClose').addEventListener('click', closeModal);
        document.getElementById('exClose').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); closeModal(); }
        });
        backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

        // ===== Filter tabs (All / Operational / Repair and Maintenance) =====
        const tabs = document.querySelectorAll('.ex-tab');
        const rows = document.querySelectorAll('#exTable tbody tr');
        const noRows = document.getElementById('exNoRows');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) { t.classList.remove('active'); });
                tab.classList.add('active');

                let shown = 0;
                rows.forEach(function(row) {
                    const ok = tab.dataset.filter === 'all' || row.dataset.category === tab.dataset.filter;
                    row.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                if (noRows) noRows.style.display = shown ? 'none' : 'block';
            });
        });

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
    })();
    </script>

<?php endif; ?>

<?php renderFooter(); ?>