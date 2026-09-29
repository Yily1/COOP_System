<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireRole('manager');

date_default_timezone_set('Asia/Manila');

/* ------------------------------------------------------------------
   TABLE NAMES: palitan dito kung iba ang pangalan sa database mo.
   Buksan ang dashboard.php?debug=1 para makita kung anong query ang pumalya.
------------------------------------------------------------------- */
$T = [
    'members'          => 'members',
    'users'            => 'users',
    'crops'            => 'crops',
    'payments'         => 'payments',
    'loans'            => 'loans',
    'meetings'         => 'meetings',
    'equipment'        => 'equipment',
    'rentals'          => 'equipment_bookings', // FIX: dating 'equipment_rentals', mali - wala ganitong table.
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

/* ---------------- Welcome ---------------- */
$hour  = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$managerName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Manager';

/* ---------------- Module counts ---------------- */
$totalMembers   = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['members']}");
$linkedMembers  = (int) dash_scalar($pdo, "
    SELECT COUNT(*) FROM {$T['members']} m
    INNER JOIN {$T['users']} u ON u.member_id = m.id");
$totalUsers     = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['users']} WHERE role = 'user'");
$activeUsers    = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['users']} WHERE role = 'user' AND LOWER(status) = 'active'");

$totalCrops     = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['crops']}");
$harvestedCrops = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['crops']} WHERE LOWER(status) = 'harvested'");

// FIX: dating "WHERE LOWER(status) = 'confirmed'" gamit ang 'confirmed' - tama na
// ang status check, hindi na kailangan i-touch, tama na ito.
$paymentsTotal  = (float) dash_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM {$T['payments']} WHERE LOWER(status) = 'confirmed'");

// IMPROVED: ginagamit na ang existing getLoanPortfolioStats() function
// (functions.php) imbes na duplicate/mali ang logic dito. Ito rin ang
// eksaktong parehong numero na makikita sa Loans page mo.
$loanStats      = getLoanPortfolioStats($pdo);
$totalLoans     = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['loans']}");
$pendingLoans   = $loanStats['pending'];
$activeLoanMembers = $loanStats['active_members'];
$loansOutstanding  = $loanStats['total_outstanding'];

$totalMeetings  = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['meetings']}");
$todayMeetings  = dash_rows($pdo, "
    SELECT title, meeting_time, location FROM {$T['meetings']}
    WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 3");

// IMPROVED: ginagamit na ang getTotalEquipmentCount() at
// getRentedThisMonthCount() (functions.php), kaya sync na ito sa
// eksaktong parehong bilang na makikita sa Equipment page mo.
$totalEquipment = getTotalEquipmentCount($pdo);
$rentalsMonth   = getRentedThisMonthCount($pdo);

$totalResources = (int) dash_scalar($pdo, "SELECT COUNT(*) FROM {$T['resources']}");

// IMPROVED: ginagamit na ang getProductPortfolioStats() (functions.php).
$productStats     = getProductPortfolioStats($pdo);
$totalProducts     = $productStats['total'];
$outOfStockCount   = $productStats['out_of_stock'];
$pendingProductReqs = $productStats['pending_requests'];
$outOfStock = dash_rows($pdo, "
    SELECT name FROM {$T['products']}
    WHERE status = 'out_of_stock' LIMIT 3");
$readyPickup    = (int) dash_scalar($pdo, "
    SELECT COUNT(*) FROM {$T['product_requests']}
    WHERE status = 'ready_for_pickup'");

/* ---------------- Collections by type ---------------- */
// FIX: dating "SELECT LOWER(type) AS t" - mali, walang column na 'type'
// sa payments table. Ang tamang column ay 'payment_type'.
$typeTotals = ['registration' => 0, 'investment' => 0, 'rental' => 0, 'loan_repayment' => 0];
foreach (dash_rows($pdo, "
    SELECT LOWER(payment_type) AS t, SUM(amount) AS total FROM {$T['payments']}
    WHERE LOWER(status) = 'confirmed' GROUP BY LOWER(payment_type)") as $r) {
    $key = str_replace(' ', '_', $r['t']);
    if (isset($typeTotals[$key])) $typeTotals[$key] = (float) $r['total'];
}

/* ---------------- Needs attention ---------------- */
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

/* ---------------- Recent activity ---------------- */
$recentActivity = dash_rows($pdo, "
    SELECT al.created_at, al.action, u.email, u.role,
           TRIM(CONCAT(COALESCE(m.last_name,''), ', ', COALESCE(m.first_name,''))) AS full_name
    FROM activity_logs al
    LEFT JOIN {$T['users']} u ON al.user_id = u.id
    LEFT JOIN {$T['members']} m ON u.member_id = m.id
    WHERE (u.role IN ('manager','user') OR u.role IS NULL)
    ORDER BY al.created_at DESC LIMIT 8");
if (!$recentActivity) {
    $recentActivity = dash_rows($pdo, "
        SELECT al.created_at, al.action, u.email, u.role, '' AS full_name
        FROM activity_logs al
        LEFT JOIN {$T['users']} u ON al.user_id = u.id
        WHERE (u.role IN ('manager','user') OR u.role IS NULL)
        ORDER BY al.created_at DESC LIMIT 8");
}

/* ---------------- Summary cards: [label, value, sub, color] ---------------- */
$cards = [
    ['Members',   $totalMembers,   "$linkedMembers with accounts", '#2f4f2f'],
    ['Users',     $totalUsers,     "$activeUsers active accounts", '#2f4f2f'],
    ['Crops',     $totalCrops,     "$harvestedCrops harvested",    '#2f4f2f'],
    ['Payments',  '₱' . number_format($paymentsTotal, 2), 'Confirmed total', '#274c80'],
    ['Loans',     $totalLoans,     "$pendingLoans pending, $activeLoanMembers active members", '#274c80'],
    ['Meetings',  $totalMeetings,  count($todayMeetings) . ' today', '#4a3f7a'],
    ['Equipment', $totalEquipment, "$rentalsMonth rented this month", '#a6701c'],
    ['Resources', $totalResources, 'distributions released',   '#a6701c'],
    ['Products',  $totalProducts,  "$outOfStockCount out of stock, $pendingProductReqs pending requests", '#a84438'],
];

$attnColors = [
    'purple' => ['#EEEDFE', '#3C3489'], 'blue'  => ['#E6F1FB', '#0C447C'],
    'red'    => ['#FAECE7', '#712B13'], 'amber' => ['#FAEEDA', '#633806'],
];

$title = 'Manager Dashboard';
renderHeader($title);
?>

<style>
.db-hero, .db-card, .db-panel { background-image:none !important; box-shadow:none !important; text-shadow:none !important; }
.db-hero { background-color:#2f4f2f; color:#fff; border-radius:12px; padding:20px 24px; margin-bottom:20px; }
.db-hero h2 { margin:0; color:#fff; font-size:24px; font-weight:500; }
.db-hero p  { margin:6px 0 0; color:rgba(255,255,255,.9); }
.db-hero small { display:block; margin-top:8px; color:rgba(255,255,255,.75); }

.db-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:20px; }
.db-card  { display:block; color:#fff; border-radius:12px; padding:14px 16px; }
.db-card .l { font-size:13px; color:rgba(255,255,255,.9); }
.db-card .v { font-size:26px; font-weight:500; line-height:1.3; }
.db-card .s { font-size:12px; color:rgba(255,255,255,.88); }

.db-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1fr); gap:16px; margin-bottom:20px; }
.db-panel { background:#fff; border-radius:12px; padding:16px 20px; border:1px solid #e3e8d8; min-width:0; }
.db-panel h3 { margin:0 0 12px; font-size:16px; font-weight:500; }

.db-attn { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid #eee; font-size:14px; }
.db-attn:last-child { border-bottom:none; }
.db-dot { width:10px; height:10px; border-radius:50%; flex:none; }
.db-attn .sub { font-size:12px; color:#777; }
.db-pill { font-size:12px; padding:2px 10px; border-radius:8px; font-weight:500; white-space:nowrap; }
.db-clear { color:#2e7d32; padding:10px 0; font-size:14px; }

.db-table { width:100%; border-collapse:collapse; font-size:14px; }
.db-table th { text-align:left; font-weight:500; font-size:13px; color:#666; padding:8px; border-bottom:1px solid #ddd; }
.db-table td { padding:10px 8px; border-bottom:1px solid #eee; }
.db-empty { color:#888; text-align:center; padding:24px 0; }

@media (max-width: 800px) { .db-grid { grid-template-columns:1fr; } }
</style>

<div class="db-hero">
    <h2><?php echo dash_e($greet); ?>, <?php echo dash_e($managerName); ?></h2>
    <p>
        <?php if ($attentionCount > 0): ?>
            Welcome back. You have <?php echo $attentionCount; ?> thing<?php echo $attentionCount > 1 ? 's' : ''; ?> that need<?php echo $attentionCount > 1 ? '' : 's'; ?> your attention today.
        <?php else: ?>
            Welcome back. Everything is up to date.
        <?php endif; ?>
    </p>
    <small><?php echo dash_e($_SESSION['email'] ?? ''); ?></small>
</div>

<div class="db-cards">
    <?php foreach ($cards as [$label, $value, $sub, $color]): ?>
        <div class="db-card" style="background-color:<?php echo $color; ?>;">
            <div class="l"><?php echo dash_e($label); ?></div>
            <div class="v"><?php echo is_int($value) ? number_format($value) : dash_e($value); ?></div>
            <div class="s"><?php echo dash_e($sub); ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="db-grid">
    <div class="db-panel">
        <h3>Collections by type</h3>
        <div style="position:relative; height:220px;"><canvas id="collectionsChart"></canvas></div>
    </div>

    <div class="db-panel">
        <h3>Needs attention</h3>
        <?php if ($attention): foreach ($attention as [$color, $text, $sub, $count]): ?>
            <div class="db-attn">
                <span class="db-dot" style="background:<?php echo $attnColors[$color][1]; ?>;"></span>
                <div style="flex:1;">
                    <div><?php echo dash_e($text); ?></div>
                    <?php if ($sub): ?><div class="sub"><?php echo dash_e($sub); ?></div><?php endif; ?>
                </div>
                <?php if ($count !== ''): ?>
                    <span class="db-pill" style="background:<?php echo $attnColors[$color][0]; ?>; color:<?php echo $attnColors[$color][1]; ?>;"><?php echo dash_e($count); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; else: ?>
            <div class="db-clear">All clear. Nothing needs your attention right now.</div>
        <?php endif; ?>
    </div>
</div>

<div class="db-panel" style="margin-bottom:20px;">
    <h3>Recent activity</h3>
    <div style="overflow-x:auto;">
    <table class="db-table">
        <thead><tr><th>When</th><th>Name</th><th>Role</th><th>Action</th></tr></thead>
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
<div class="db-panel" style="border-left:6px solid #a84438; margin-bottom:20px;">
    <h3>Debug: queries na pumalya</h3>
    <?php if ($GLOBALS['dash_errors']): ?>
        <?php foreach ($GLOBALS['dash_errors'] as $err): ?>
            <pre style="white-space:pre-wrap; font-size:12px; margin:0 0 10px;"><?php echo dash_e($err); ?></pre>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Walang pumalyang query.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const typeData = <?php echo json_encode(array_values($typeTotals)); ?>;
new Chart(document.getElementById('collectionsChart'), {
    type: 'bar',
    data: {
        labels: ['Registration', 'Investment', 'Rental', 'Loan repayment'],
        datasets: [{
            data: typeData,
            backgroundColor: ['#2f4f2f', '#274c80', '#4a3f7a', '#a6701c'],
            borderRadius: 4,
            maxBarThickness: 40
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php renderFooter(); ?>