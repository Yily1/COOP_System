<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireRole('manager');

date_default_timezone_set('Asia/Manila');

$GLOBALS['dash_errors'] = [];

function dash_query(PDO $pdo, string $sql) {
    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            $GLOBALS['dash_errors'][] = implode(' | ', $pdo->errorInfo()) . '  =>  ' . trim(preg_replace('/\s+/', ' ', $sql));
        }
        return $stmt;
    } catch (PDOException $e) {
        $GLOBALS['dash_errors'][] = $e->getMessage() . '  =>  ' . trim(preg_replace('/\s+/', ' ', $sql));
        return false;
    }
}
function dash_rows(PDO $pdo, string $sql): array {
    $stmt = dash_query($pdo, $sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}
function dash_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dash_peso0($v) { return '₱' . number_format((float)$v, 0); }
function dash_peso0_signed($v) {
    $v = (float) $v;
    return ($v < 0 ? '-' : '') . '₱' . number_format(abs($v), 0);
}

/* ============================================================
   DISPLAY ONLY
   Ang mga numero sa ibaba ay NAKAPIRMI at hindi galing sa database.
   Pwede mo silang palitan dito mismo. Ang tanging totoong data
   sa dashboard na ito ay ang "Recent activity" sa pinakababa.
   ============================================================ */

// Mga label ng huling 6 na buwan at huling 5 taon (para sa mga chart lang)
$monthLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime('first day of this month');
    $d->modify("-$i months");
    $monthLabels[] = $d->format('M');
}
$years = [];
for ($i = 4; $i >= 0; $i--) {
    $years[] = (string) ((int) date('Y') - $i);
}

// Total income
$totalIncome  = 125000;
$deltaUp      = true;
$deltaText    = '₱8,500 more than last month';
$incomeByMonth = [12000, 15500, 14000, 18000, 21500, 30000];
$incomeByType = [
    'investment'     => 62000,
    'loan_repayment' => 28000,
    'rental'         => 21000,
    'product'        => 14000,
];

// Net income
$netIncome    = 98000;
$netDeltaUp   = true;
$netDeltaText = '₱12,000 more than last year';
$netByYear    = [42000, 55000, 71000, 86000, 98000];

// Reserve fund (Registration fees)
$reserveTotal   = 15000;
$reserveCount   = 30;
$reserveByMonth = [1500, 2000, 2500, 3000, 3000, 3000];

// Operational expenses
$totalOperational = 27000;
$expenseByMonth   = [3000, 4500, 4000, 5500, 5000, 5000];

// Repair and maintenance
$totalRepair   = 12000;
$repairByMonth = [1500, 2000, 1800, 2200, 2300, 2200];

// Services utilized
$services = [
    'Products'  => 24,
    'Equipment' => 18,
    'Resources' => 12,
    'Loans'     => 15,
    'Crops'     => 9,
];
$serviceColors = ['Products' => '#2a78d6', 'Equipment' => '#eb6834', 'Resources' => '#1baf7a', 'Loans' => '#eda100', 'Crops' => '#6250d6'];
$servicesTotal = array_sum($services);

// Active members
$totalMembers  = 52;
$activeMembers = 48;
$memberGrowth  = [38, 41, 44, 47, 50, 52];

$typeMeta = [
    'investment'     => ['Investment',     '#2a78d6'],
    'loan_repayment' => ['Loan repayment', '#1baf7a'],
    'rental'         => ['Rental',         '#eda100'],
    'product'        => ['Product',        '#7f77dd'],
];

/* ============================================================
   TOTOONG DATA: RECENT ACTIVITY
   ============================================================ */
$recentActivity = dash_rows($pdo, "
    SELECT al.created_at, al.action, u.email, u.role,
           TRIM(CONCAT(COALESCE(m.last_name,''), ', ', COALESCE(m.first_name,''))) AS full_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN members m ON u.member_id = m.id
    WHERE (u.role IN ('manager','user') OR u.role IS NULL)
    ORDER BY al.created_at DESC LIMIT 8");

$title = 'Manager Dashboard';
renderHeader($title);
?>

<style>
    .db-wrap { max-width: 1280px; margin: 0 auto; padding: 0 4px; }
    .db-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 14px; margin-bottom: 14px; align-items: stretch; }
    .db-col { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
    .db-card { background: #fff; border: 1px solid #e8ede0; border-radius: 14px; padding: 16px 18px; min-width: 0; box-sizing: border-box; }
    .db-big { flex: 1 1 auto; display: flex; flex-direction: column; }
    .db-head { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; font-size: 13px; color: #667066; }
    .db-head .material-icons { font-size: 18px; color: #2f4f2f; }
    .db-head a { margin-left: auto; font-size: 12px; font-weight: 600; color: #3B6D11; text-decoration: none; }
    .db-head a:hover { text-decoration: underline; }
    .db-num { margin-left: auto; font-size: 11px; color: #9aa39a; border: 1px solid #e8ede0; border-radius: 10px; padding: 0 7px; }
    .db-head a + .db-num { margin-left: 8px; }
    .db-head a + .db-tag { margin-left: 8px; }
    .db-hero-num { font-family: 'Fraunces', 'Times New Roman', Times, serif; font-size: 44px; line-height: 1.1; color: #1b3a24; margin: 0; font-variant-numeric: lining-nums tabular-nums; }
    .db-small-num { font-size: 26px; font-weight: 600; color: #1b3a24; margin: 0; }
    .db-small-num.neg { color: #a6322f; }
    .db-delta { font-size: 12px; margin: 4px 0 12px; }
    .db-delta.up { color: #2e7d32; } .db-delta.down { color: #a6322f; } .db-delta.flat { color: #667066; }
    .db-sub { font-size: 12px; color: #667066; }
    .db-tag { margin-left: auto; font-size: 11px; color: #854F0B; background: #FAEEDA; border-radius: 10px; padding: 1px 8px; font-weight: 600; }
    .db-chart-big { position: relative; height: 170px; flex: 1 1 auto; min-height: 170px; }
    .db-chart-sm  { position: relative; height: 84px; margin-top: 6px; }
    .db-chart-line { position: relative; height: 70px; margin-top: 6px; }
    .db-chart-net { position: relative; height: 84px; margin-top: 6px; }
    .db-legend-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #f0f2ea; }
    .db-lg { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #667066; min-width: 0; }
    .db-lg b { width: 9px; height: 9px; border-radius: 2px; flex: none; }
    .db-lg span.v { margin-left: auto; color: #1b3a24; font-weight: 500; white-space: nowrap; }
    .db-donut-row { display: flex; align-items: center; gap: 14px; }
    .db-donut { position: relative; width: 92px; height: 92px; flex: none; }
    .db-donut-center { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: #1b3a24; }
    .db-donut-legend { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 0; }
    .db-empty-note { font-size: 12.5px; color: #667066; background: #f7f8f5; border-radius: 8px; padding: 10px 12px; margin-top: 8px; line-height: 1.5; }
    .db-empty-note a { color: #3B6D11; font-weight: 600; }
    .db-card h3 { margin: 0 0 10px !important; font-size: 15px !important; font-weight: 600 !important; color: #2c3e2c !important; background: none !important; }
    .db-table-wrap { overflow-x: auto; }
    .db-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .db-table th { text-align: left; font-weight: 600; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 10px; border-bottom: 2px solid #e8ede0; background: #fff; }
    .db-table td { padding: 11px 10px; border-bottom: 1px solid #f0f2ea; color: #2c3e2c; }
    .db-table tbody tr:hover td { background: #f8faf5; }
    .db-pill { font-size: 11px; padding: 3px 9px; border-radius: 7px; font-weight: 600; white-space: nowrap; }
    .db-empty { color: #999; text-align: center; padding: 28px 0; font-style: italic; }
    .db-debug { border-left: 4px solid #a84438 !important; background: #fdf6f5; margin-top: 14px; }
    .db-debug pre { white-space: pre-wrap; font-size: 11px; margin: 0 0 8px; color: #712b13; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #f0d5d0; font-family: Consolas, monospace; }
    @media (max-width: 760px) { .db-grid { grid-template-columns: minmax(0, 1fr); } .db-hero-num { font-size: 36px; } .db-chart-big { height: 160px; min-height: 160px; flex: none; } }
</style>

<div class="db-wrap">

    <div class="db-grid">

        <div class="db-col">

            <div class="db-card db-big">
                <div class="db-head">
                    <span class="material-icons">payments</span>Total income
                    <span class="db-tag">Coop share only</span>
                </div>
                <p class="db-hero-num"><?php echo dash_peso0($totalIncome); ?></p>
                <div class="db-delta <?php echo $deltaUp === null ? 'flat' : ($deltaUp ? 'up' : 'down'); ?>">
                    <?php echo dash_e($deltaText); ?>
                </div>
                <div class="db-chart-big">
                    <canvas id="incomeChart" role="img" aria-label="Bar chart of income per month for the last six months"></canvas>
                </div>
                <div class="db-legend-grid">
                    <?php foreach ($typeMeta as $key => [$label, $color]): ?>
                        <div class="db-lg">
                            <b style="background:<?php echo $color; ?>;"></b><?php echo dash_e($label); ?>
                            <span class="v"><?php echo dash_peso0_signed($incomeByType[$key]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="db-card">
                <div class="db-head">
                    <span class="material-icons">trending_up</span>Net income
                </div>
                <p class="db-small-num<?php echo $netIncome < 0 ? ' neg' : ''; ?>"><?php echo dash_peso0_signed($netIncome); ?></p>
                <div class="db-delta <?php echo $netDeltaUp === null ? 'flat' : ($netDeltaUp ? 'up' : 'down'); ?>" style="margin: 4px 0 2px;">
                    <?php echo dash_e($netDeltaText); ?>
                </div>
                <div class="db-chart-net">
                    <canvas id="netChart" role="img" aria-label="Line chart of net income per year for the last five years"></canvas>
                </div>
            </div>

            <div class="db-card">
                <div class="db-head">
                    <span class="material-icons">savings</span>Reserve fund
                    <a href="<?php echo BASE_URL; ?>/app/manager/payments/payments.php?payment_type=registration">View</a>
                    <span class="db-tag">Registration fees</span>
                </div>
                <div style="display:flex; align-items:baseline; gap:8px;">
                    <p class="db-small-num"><?php echo dash_peso0($reserveTotal); ?></p>
                    <span class="db-sub"><?php echo $reserveCount; ?> registration<?php echo $reserveCount === 1 ? '' : 's'; ?></span>
                </div>
                <div class="db-chart-sm">
                    <canvas id="reserveChart" role="img" aria-label="Bar chart of registration fees per month for the last six months"></canvas>
                </div>
            </div>

        </div>

        <div class="db-col">

            <div class="db-card">
                <div class="db-head">
                    <span class="material-icons">receipt_long</span>Operational expenses
                    <a href="<?php echo BASE_URL; ?>/app/manager/expenses/expenses.php">Manage</a>
                </div>
                <p class="db-small-num"><?php echo dash_peso0($totalOperational); ?></p>
                <div class="db-chart-sm">
                    <canvas id="expenseChart" role="img" aria-label="Bar chart of expenses per month for the last six months"></canvas>
                </div>
            </div>

            <div class="db-card">
                <div class="db-head">
                    <span class="material-icons">handyman</span>Repair and maintenance
                    <a href="<?php echo BASE_URL; ?>/app/manager/payments/payments.php?payment_type=rental">View</a>
                </div>
                <p class="db-small-num"><?php echo dash_peso0($totalRepair); ?></p>
                <div class="db-chart-sm">
                    <canvas id="repairChart" role="img" aria-label="Bar chart of repair and maintenance per month for the last six months"></canvas>
                </div>
            </div>

            <div class="db-card">
                <div class="db-head">
                    <span class="material-icons">build</span>Services utilized
                </div>
                <div class="db-donut-row">
                    <div class="db-donut">
                        <canvas id="servicesChart" role="img" aria-label="Donut chart of services used"></canvas>
                        <div class="db-donut-center"><?php echo $servicesTotal; ?></div>
                    </div>
                    <div class="db-donut-legend">
                        <?php foreach ($services as $name => $count): ?>
                            <div class="db-lg">
                                <b style="background:<?php echo $serviceColors[$name]; ?>;"></b><?php echo dash_e($name); ?>
                                <span class="v"><?php echo $count; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="db-card">
                <div class="db-head">
                    <span class="material-icons">groups</span>Active members
                </div>
                <div style="display:flex; align-items:baseline; gap:8px;">
                    <p class="db-small-num"><?php echo $activeMembers; ?></p>
                    <span class="db-sub">of <?php echo $totalMembers; ?> member<?php echo $totalMembers === 1 ? '' : 's'; ?></span>
                </div>
                <div class="db-chart-line">
                    <canvas id="membersChart" role="img" aria-label="Line chart of total members over the last six months"></canvas>
                </div>
            </div>

        </div>

    </div>

    <div class="db-card">
        <h3>Recent activity</h3>
        <div class="db-table-wrap">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentActivity): foreach ($recentActivity as $log):
                    $name = trim($log['full_name'] ?? '', " ,");
                    if ($name === '') $name = $log['email'] ?: 'System';
                    $role = $log['role'] ?: 'system';
                    $isMgr = ($role === 'manager');
                ?>
                    <tr>
                        <td><?php echo dash_e(date('M d, h:i A', strtotime($log['created_at']))); ?></td>
                        <td><?php echo dash_e($name); ?></td>
                        <td>
                            <span class="db-pill" style="background:<?php echo $isMgr ? '#FAEEDA' : '#EAF3DE'; ?>; color:<?php echo $isMgr ? '#633806' : '#27500A'; ?>;">
                                <?php echo dash_e(ucfirst($role)); ?>
                            </span>
                        </td>
                        <td><?php echo dash_e(ucwords(str_replace('_', ' ', $log['action']))); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4" class="db-empty">No activity logged yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (isset($_GET['debug'])): ?>
    <div class="db-card db-debug">
        <h3>Debug: failed queries</h3>
        <?php if ($GLOBALS['dash_errors']): ?>
            <?php foreach ($GLOBALS['dash_errors'] as $err): ?>
                <pre><?php echo dash_e($err); ?></pre>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No failed queries. ✓</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const TICK = '#6b7280';
    const GRID = '#e8ede0';
    const peso = v => (v < 0 ? '-' : '') + '₱' + (Math.abs(v) >= 1000 ? (Math.round(Math.abs(v) / 100) / 10) + 'k' : Math.abs(v));
    const tip  = { callbacks: { label: c => (c.raw < 0 ? '-' : '') + '₱' + Math.abs(Number(c.raw)).toLocaleString('en-PH') } };
    const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
    const monthLabels = <?php echo json_encode($monthLabels); ?>;

    const income = document.getElementById('incomeChart');
    if (income) {
        new Chart(income, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{ data: <?php echo json_encode(array_values($incomeByMonth)); ?>, backgroundColor: function (ctx) { return ctx.raw < 0 ? '#b54a3c' : '#2a78d6'; }, borderRadius: 4, maxBarThickness: 28 }]
            },
            options: Object.assign({}, base, { plugins: { legend: { display: false }, tooltip: tip }, scales: { y: { beginAtZero: true, ticks: { color: TICK, callback: peso }, grid: { color: GRID } }, x: { ticks: { color: TICK }, grid: { display: false } } } })
        });
    }

    const net = document.getElementById('netChart');
    if (net) {
        const netData = <?php echo json_encode(array_values($netByYear)); ?>;
        new Chart(net, {
            type: 'line',
            data: { labels: <?php echo json_encode(array_values($years)); ?>, datasets: [{ data: netData, borderColor: '#1baf7a', backgroundColor: 'rgba(27,175,122,0.10)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: netData.map((_, i) => i === netData.length - 1 ? 4 : 0), pointBackgroundColor: '#1baf7a' }] },
            options: Object.assign({}, base, { plugins: { legend: { display: false }, tooltip: tip }, scales: { y: { display: false }, x: { ticks: { color: TICK, font: { size: 11 } }, grid: { display: false } } } })
        });
    }

    const reserve = document.getElementById('reserveChart');
    if (reserve) {
        new Chart(reserve, {
            type: 'bar',
            data: { labels: monthLabels, datasets: [{ data: <?php echo json_encode(array_values($reserveByMonth)); ?>, backgroundColor: '#e87ba4', borderRadius: 4, maxBarThickness: 26 }] },
            options: Object.assign({}, base, { plugins: { legend: { display: false }, tooltip: tip }, scales: { y: { display: false, beginAtZero: true }, x: { ticks: { color: TICK, font: { size: 11 } }, grid: { display: false } } } })
        });
    }

    const expense = document.getElementById('expenseChart');
    if (expense) {
        new Chart(expense, {
            type: 'bar',
            data: { labels: monthLabels, datasets: [{ data: <?php echo json_encode(array_values($expenseByMonth)); ?>, backgroundColor: '#eb6834', borderRadius: 4, maxBarThickness: 26 }] },
            options: Object.assign({}, base, { plugins: { legend: { display: false }, tooltip: tip }, scales: { y: { display: false, beginAtZero: true }, x: { ticks: { color: TICK, font: { size: 11 } }, grid: { display: false } } } })
        });
    }

    const repair = document.getElementById('repairChart');
    if (repair) {
        new Chart(repair, {
            type: 'bar',
            data: { labels: monthLabels, datasets: [{ data: <?php echo json_encode(array_values($repairByMonth)); ?>, backgroundColor: '#0F6E56', borderRadius: 4, maxBarThickness: 26 }] },
            options: Object.assign({}, base, { plugins: { legend: { display: false }, tooltip: tip }, scales: { y: { display: false, beginAtZero: true }, x: { ticks: { color: TICK, font: { size: 11 } }, grid: { display: false } } } })
        });
    }

    const services = document.getElementById('servicesChart');
    if (services) {
        new Chart(services, {
            type: 'doughnut',
            data: { labels: <?php echo json_encode(array_keys($services)); ?>, datasets: [{ data: <?php echo json_encode(array_values($services)); ?>, backgroundColor: <?php echo json_encode(array_values($serviceColors)); ?>, borderWidth: 2, borderColor: '#ffffff' }] },
            options: Object.assign({}, base, { cutout: '66%' })
        });
    }

    const members = document.getElementById('membersChart');
    if (members) {
        const growth = <?php echo json_encode($memberGrowth); ?>;
        new Chart(members, {
            type: 'line',
            data: { labels: monthLabels, datasets: [{ data: growth, borderColor: '#1baf7a', backgroundColor: 'rgba(27,175,122,0.10)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: growth.map((_, i) => i === growth.length - 1 ? 4 : 0), pointBackgroundColor: '#1baf7a' }] },
            options: Object.assign({}, base, { scales: { y: { display: false, beginAtZero: true }, x: { ticks: { color: TICK, font: { size: 11 } }, grid: { display: false } } } })
        });
    }
})();
</script>

<?php renderFooter(); ?>