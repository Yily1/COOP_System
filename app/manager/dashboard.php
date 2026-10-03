<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireRole('manager');

date_default_timezone_set('Asia/Manila');

$T = [
    'members'          => 'members',
    'users'            => 'users',
    'crops'            => 'crops',
    'payments'         => 'payments',
    'loans'            => 'loans',
    'meetings'         => 'meetings',
    'equipment'        => 'equipment',
    'rentals'          => 'equipment_bookings',
    'resources'        => 'resource_distributions',
    'products'         => 'products',
    'product_requests' => 'product_requests',
];

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
function dash_scalar(PDO $pdo, string $sql, $default = 0) {
    $stmt = dash_query($pdo, $sql);
    if (!$stmt) return $default;
    $v = $stmt->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}
function dash_rows(PDO $pdo, string $sql): array {
    $stmt = dash_query($pdo, $sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}
function dash_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Welcome */
$hour  = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$managerName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Manager';

/* Counts */
$totalMembers   = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['members']}");
$linkedMembers  = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['members']} m INNER JOIN {$T['users']} u ON u.member_id = m.id");
$totalUsers     = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['users']} WHERE role = 'user'");
$activeUsers    = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['users']} WHERE role = 'user' AND LOWER(status) = 'active'");

$totalCrops     = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['crops']}");
$harvestedCrops = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['crops']} WHERE LOWER(status) = 'harvested'");

$paymentsTotal  = (float) dash_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM {$T['payments']} WHERE LOWER(status) = 'confirmed'");

$loanStats      = function_exists('getLoanPortfolioStats') ? getLoanPortfolioStats($pdo) : ['pending'=>0,'active_members'=>0,'total_outstanding'=>0];
$totalLoans     = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['loans']}");
$pendingLoans   = $loanStats['pending'] ?? 0;
$activeLoanMembers = $loanStats['active_members'] ?? 0;

$totalMeetings  = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['meetings']}");
$todayMeetings  = dash_rows($pdo, "SELECT title, meeting_time, location FROM {$T['meetings']} WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 3");

$totalEquipment = function_exists('getTotalEquipmentCount') ? getTotalEquipmentCount($pdo) : (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['equipment']}");
$rentalsMonth   = function_exists('getRentedThisMonthCount') ? getRentedThisMonthCount($pdo) : 0;

$totalResources = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['resources']}");

$productStats     = function_exists('getProductPortfolioStats') ? getProductPortfolioStats($pdo) : ['total'=>0,'out_of_stock'=>0,'pending_requests'=>0];
$totalProducts     = $productStats['total'] ?? 0;
$outOfStockCount   = $productStats['out_of_stock'] ?? 0;
$pendingProductReqs = $productStats['pending_requests'] ?? 0;
$outOfStock = dash_rows($pdo, "SELECT name FROM {$T['products']} WHERE status = 'out_of_stock' LIMIT 3");
$readyPickup    = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['product_requests']} WHERE status = 'ready_for_pickup'");

/* Collections by type */
$typeTotals = ['registration' => 0, 'investment' => 0, 'rental' => 0, 'loan_repayment' => 0];
foreach (dash_rows($pdo, "SELECT LOWER(payment_type) AS t, SUM(amount) AS total FROM {$T['payments']} WHERE LOWER(status) = 'confirmed' GROUP BY LOWER(payment_type)") as $r) {
    $key = str_replace(' ', '_', $r['t']);
    if (isset($typeTotals[$key])) $typeTotals[$key] = (float) $r['total'];
}

/* Needs attention */
$attention = [];
foreach ($todayMeetings as $m) {
    $sub = trim(($m['meeting_time'] ? date('g:i A', strtotime($m['meeting_time'])) : '') . ($m['location'] ? ', ' . $m['location'] : ''), ', ');
    $attention[] = ['purple', 'Meeting today: ' . $m['title'], $sub, ''];
}
if ($readyPickup > 0)   $attention[] = ['blue', 'Requests ready for pickup', '', $readyPickup];
if ($outOfStockCount > 0) {
    $names = implode(', ', array_column($outOfStock, 'name'));
    $attention[] = ['red', 'Out of stock', $names, $outOfStockCount];
}
if ($pendingLoans > 0)  $attention[] = ['amber', 'Loan requests waiting approval', '', $pendingLoans];
if ($pendingProductReqs > 0) $attention[] = ['amber', 'Product requests waiting review', '', $pendingProductReqs];
$attentionCount = count($attention);

/* Recent activity */
$recentActivity = dash_rows($pdo, "
    SELECT al.created_at, al.action, u.email, u.role,
           TRIM(CONCAT(COALESCE(m.last_name,''), ', ', COALESCE(m.first_name,''))) AS full_name
    FROM activity_logs al
    LEFT JOIN {$T['users']} u ON al.user_id = u.id
    LEFT JOIN {$T['members']} m ON u.member_id = m.id
    WHERE (u.role IN ('manager','user') OR u.role IS NULL)
    ORDER BY al.created_at DESC LIMIT 8");

/* Summary cards */
$cards = [
    ['Members',   $totalMembers,   "$linkedMembers with accounts", '#2f4f2f'],
    ['Users',     $totalUsers,     "$activeUsers active accounts", '#2f4f2f'],
    ['Crops',     $totalCrops,     "$harvestedCrops harvested",    '#2f4f2f'],
    ['Payments',  '₱' . number_format($paymentsTotal, 2), 'Confirmed total', '#274c80'],
    ['Loans',     $totalLoans,     "$pendingLoans pending", '#274c80'],
    ['Meetings',  $totalMeetings,  count($todayMeetings) . ' today', '#4a3f7a'],
    ['Equipment', $totalEquipment, "$rentalsMonth rented", '#a6701c'],
    ['Resources', $totalResources, 'distributions released',   '#a6701c'],
    ['Products',  $totalProducts,  "$outOfStockCount out of stock", '#a84438'],
];

$attnColors = [
    'purple' => ['#EEEDFE', '#3C3489'], 'blue'  => ['#E6F1FB', '#0C447C'],
    'red'    => ['#FAECE7', '#712B13'], 'amber' => ['#FAEEDA', '#633806'],
];

$title = 'Manager Dashboard';
renderHeader($title);
?>

<style>
    /* =========================================================
       DASHBOARD — Manager
       ========================================================= */
    .db-wrap {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 4px;
    }

    /* ---------- HERO ---------- */
    .db-hero {
        background: linear-gradient(135deg, #2f4f2f 0%, #3d6b3d 100%);
        color: #fff;
        border-radius: 16px;
        padding: 26px 30px;
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        position: relative;
        overflow: hidden;
    }
    .db-hero::after {
        content: '';
        position: absolute;
        right: -60px;
        bottom: -60px;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        pointer-events: none;
    }
    .db-hero-left { flex: 1; min-width: 240px; position: relative; z-index: 1; }
    .db-hero h2 {
        margin: 0 0 6px;
        color: #fff !important;
        background: none !important;
        font-size: 24px !important;
        font-weight: 600 !important;
        letter-spacing: -0.3px;
    }
    .db-hero p { margin: 0; color: rgba(255,255,255,.92); font-size: 14px; }
    .db-hero small { display: block; margin-top: 6px; color: rgba(255,255,255,.7); font-size: 12px; }
    .db-hero-badge {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 12px;
        padding: 12px 20px;
        text-align: center;
        min-width: 130px;
        position: relative;
        z-index: 1;
    }
    .db-hero-badge .num { font-size: 28px; font-weight: 700; line-height: 1; display: block; color: #fff; }
    .db-hero-badge .lbl { font-size: 11px; color: rgba(255,255,255,.85); margin-top: 4px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }

    /* ---------- KPI CARDS ---------- */
    .db-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 22px;
    }
    .db-card {
        display: block;
        color: #fff;
        border-radius: 12px;
        padding: 14px 16px;
        position: relative;
        overflow: hidden;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        text-decoration: none;
    }
    .db-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0,0,0,0.12);
        color: #fff;
    }
    .db-card .l {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: rgba(255,255,255,.85);
        font-weight: 600;
        margin-bottom: 4px;
    }
    .db-card .v {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 2px;
    }
    .db-card .s {
        font-size: 11px;
        color: rgba(255,255,255,.85);
    }

    /* ---------- PANELS ---------- */
    .db-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
        gap: 16px;
        margin-bottom: 22px;
    }
    .db-panel {
        background: #fff;
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid #e8ede0;
        min-width: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .db-panel h3 {
        margin: 0 0 16px !important;
        padding: 0 0 0 12px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        color: #2c3e2c !important;
        background: none !important;
        border: none !important;
        position: relative;
    }
    .db-panel h3::before {
        content: '';
        position: absolute;
        left: 0;
        top: 3px;
        width: 4px;
        height: 16px;
        background: #2f4f2f;
        border-radius: 2px;
    }

    .db-chart-wrap {
        position: relative;
        height: 230px;
    }

    /* ---------- ATTENTION ---------- */
    .db-attn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #f0f2ea;
        font-size: 13px;
        text-decoration: none;
        color: inherit;
        transition: background 0.12s ease;
    }
    .db-attn:hover {
        background: #f8faf5;
        margin: 0 -8px;
        padding-left: 8px;
        padding-right: 8px;
        border-radius: 6px;
    }
    .db-attn:last-child { border-bottom: none; }
    .db-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
    .db-attn .txt { flex: 1; min-width: 0; }
    .db-attn .txt > div:first-child { font-weight: 500; color: #2c3e2c; }
    .db-attn .sub { font-size: 11px; color: #888; margin-top: 2px; }
    .db-pill {
        font-size: 11px;
        padding: 3px 9px;
        border-radius: 7px;
        font-weight: 600;
        white-space: nowrap;
    }
    .db-clear {
        color: #2e7d32;
        padding: 24px 0;
        font-size: 13px;
        text-align: center;
    }
    .db-clear::before {
        content: '✓ ';
        font-weight: bold;
    }

    /* ---------- TABLE ---------- */
    .db-table-wrap { overflow-x: auto; }
    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .db-table th {
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 10px;
        border-bottom: 2px solid #e8ede0;
    }
    .db-table td {
        padding: 11px 10px;
        border-bottom: 1px solid #f0f2ea;
        color: #2c3e2c;
    }
    .db-table tbody tr:hover { background: #f8faf5; }
    .db-empty {
        color: #999;
        text-align: center;
        padding: 28px 0;
        font-style: italic;
    }

    /* ---------- DEBUG ---------- */
    .db-debug {
        border-left: 4px solid #a84438 !important;
        background: #fdf6f5;
        margin-bottom: 20px;
    }
    .db-debug pre {
        white-space: pre-wrap;
        font-size: 11px;
        margin: 0 0 8px;
        color: #712b13;
        background: #fff;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #f0d5d0;
        font-family: 'Consolas', monospace;
    }

    /* ---------- RESPONSIVE ---------- */
    @media (max-width: 900px) {
        .db-grid { grid-template-columns: 1fr; }
        .db-hero { padding: 20px 22px; }
        .db-hero h2 { font-size: 20px !important; }
    }
    @media (max-width: 560px) {
        .db-cards { grid-template-columns: 1fr 1fr; gap: 8px; }
        .db-card { padding: 12px; }
        .db-card .v { font-size: 18px; }
        .db-hero-badge { display: none; }
    }
</style>

<div class="db-wrap">

    <!-- ============ HERO ============ -->
    <div class="db-hero">
        <div class="db-hero-left">
            <h2><?php echo dash_e($greet); ?>, <?php echo dash_e($managerName); ?> 👋</h2>
            <p>
                <?php if ($attentionCount > 0): ?>
                    You have <strong><?php echo $attentionCount; ?></strong> item<?php echo $attentionCount > 1 ? 's' : ''; ?> needing attention today.
                <?php else: ?>
                    Everything is up to date. Great job! 🎉
                <?php endif; ?>
            </p>
            <small><?php echo dash_e($_SESSION['email'] ?? ''); ?></small>
        </div>
        <div class="db-hero-badge">
            <span class="num"><?php echo $attentionCount; ?></span>
            <span class="lbl">Pending</span>
        </div>
    </div>

    <!-- ============ KPI CARDS ============ -->
    <div class="db-cards">
        <?php foreach ($cards as [$label, $value, $sub, $color]): ?>
            <div class="db-card" style="background-color:<?php echo $color; ?>;">
                <div class="l"><?php echo dash_e($label); ?></div>
                <div class="v"><?php echo is_int($value) ? number_format($value) : dash_e($value); ?></div>
                <div class="s"><?php echo dash_e($sub); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ CHART + ATTENTION ============ -->
    <div class="db-grid">
        <div class="db-panel">
            <h3>Collections by type</h3>
            <div class="db-chart-wrap">
                <canvas id="collectionsChart"></canvas>
            </div>
        </div>

        <div class="db-panel">
            <h3>Needs attention</h3>
            <?php if ($attention): foreach ($attention as [$color, $text, $sub, $count]): ?>
                <div class="db-attn">
                    <span class="db-dot" style="background:<?php echo $attnColors[$color][1]; ?>;"></span>
                    <div class="txt">
                        <div><?php echo dash_e($text); ?></div>
                        <?php if ($sub): ?><div class="sub"><?php echo dash_e($sub); ?></div><?php endif; ?>
                    </div>
                    <?php if ($count !== ''): ?>
                        <span class="db-pill" style="background:<?php echo $attnColors[$color][0]; ?>; color:<?php echo $attnColors[$color][1]; ?>;"><?php echo dash_e($count); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; else: ?>
                <div class="db-clear">All clear. Nothing needs your attention.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============ RECENT ACTIVITY ============ -->
    <div class="db-panel" style="margin-bottom:22px;">
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

    <!-- ============ DEBUG ============ -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="db-panel db-debug">
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
    const ctx = document.getElementById('collectionsChart');
    if (!ctx) return;

    const typeData = <?php echo json_encode(array_values($typeTotals)); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Registration', 'Investment', 'Rental', 'Loan repayment'],
            datasets: [{
                data: typeData,
                backgroundColor: ['#2f4f2f', '#274c80', '#4a3f7a', '#a6701c'],
                borderRadius: 6,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => '₱' + Number(c.raw).toLocaleString('en-PH', { minimumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') },
                    grid: { color: '#f0f2ea' }
                },
                x: { grid: { display: false } }
            }
        }
    });
})();
</script>

<?php renderFooter(); ?>