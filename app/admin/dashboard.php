<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireRole('admin');

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'manager'");
$totalManagers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$totalRegularUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 0");
$unverifiedUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get activity statistics for charts
$stmt = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get action distribution
$stmt = $pdo->query("
    SELECT action, COUNT(*) as count
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
");
$actionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get role-based activity
$stmt = $pdo->query("
    SELECT
        COALESCE(u.role, 'unknown') as role,
        COUNT(*) as count
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY role
");
$roleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activity logs (latest 10)
$stmt = $pdo->query("
    SELECT email, action, status, created_at
    FROM activity_logs
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");
$recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Admin Dashboard');
?>

<style>
/* Defensive width fix */
.box { width: 100% !important; min-height: 100vh; }
.main { flex: 1 1 auto !important; min-width: 0 !important; }
.main > .container {
    max-width: 1300px !important;
    width: 100% !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
    width: 100%;
    min-width: 0;
}
/* Flat cards: solid fills, no shadow, label on top, serif number below */
.stat-card {
    padding: 18px 20px;
    border-radius: 14px;
    border: none;
    box-shadow: none;
    min-width: 0;
    box-sizing: border-box;
}
.stat-card p  { margin: 0 0 6px; font-size: 14px; }
.stat-card h3 {
    margin: 0;
    font-size: 34px;
    font-weight: 700;
    line-height: 1.1;
    font-family: Georgia, 'Times New Roman', serif;
}
.stat-card,
.stat-card p,
.stat-card h3    { color: #ffffff !important; }
.stat-total      { background: #335230; }
.stat-admin      { background: #B07A1E; }
.stat-manager    { background: #4A8250; }
.stat-user       { background: #B5504A; }
.stat-unverified { background: #A8761F; }

/* ===== Activity logs list ===== */
.log-list {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 4px 16px;
    margin: 12px 0 24px;
}
.log-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}
.log-row:last-child { border-bottom: none; }
.log-row .log-main { font-weight: 500; min-width: 0; overflow-wrap: anywhere; }
.log-row .log-status { color: #888; white-space: nowrap; }
.log-empty { padding: 20px 0; text-align: center; color: #888; font-size: 14px; }

.chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 20px 0;
    min-width: 0;
    box-sizing: border-box;
}
.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
    margin: 20px 0;
    width: 100%;
    min-width: 0;
}
.chart-container canvas { max-width: 100%; }
</style>

<h1>Admin Dashboard</h1>

<div class="info-box">
    <strong>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></strong><br>
    Role: <span class="badge badge-admin">Admin</span>
</div>

<h2>System Statistics</h2>

<div class="stat-grid">
    <div class="stat-card stat-total">
        <p>Total Users</p>
        <h3><?php echo $totalUsers; ?></h3>
    </div>

    <div class="stat-card stat-admin">
        <p>Admins</p>
        <h3><?php echo $totalAdmins; ?></h3>
    </div>

    <div class="stat-card stat-manager">
        <p>Managers</p>
        <h3><?php echo $totalManagers; ?></h3>
    </div>

    <div class="stat-card stat-user">
        <p>Regular Users</p>
        <h3><?php echo $totalRegularUsers; ?></h3>
    </div>

    <div class="stat-card stat-unverified">
        <p>Unverified</p>
        <h3><?php echo $unverifiedUsers; ?></h3>
    </div>
</div>

<h2>Activity Analytics (Last 30 Days)</h2>

<div class="chart-grid">
    <div class="chart-container">
        <h3>Daily Activity Trend (Last 7 Days)</h3>
        <canvas id="dailyActivityChart"></canvas>
    </div>
    <div class="chart-container">
        <h3>Top Actions</h3>
        <canvas id="actionChart"></canvas>
    </div>
    <div class="chart-container">
        <h3>Activity by Role (Last 30 Days)</h3>
        <canvas id="roleChart"></canvas>
    </div>
</div>

<h2>Activity Logs</h2>

<div class="log-list">
    <?php if (empty($recentLogs)): ?>
        <div class="log-empty">No activity yet.</div>
    <?php else: ?>
        <?php foreach ($recentLogs as $log): ?>
            <div class="log-row" title="<?php echo htmlspecialchars($log['created_at']); ?>">
                <span class="log-main">
                    <?php echo htmlspecialchars($log['email'] ?: 'Unknown'); ?>
                    &middot; <?php echo htmlspecialchars($log['action']); ?>
                </span>
                <span class="log-status"><?php echo htmlspecialchars($log['status']); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Daily Activity Chart
const dailyData = <?php echo json_encode($dailyActivity); ?>;
new Chart(document.getElementById('dailyActivityChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.date),
        datasets: [{
            label: 'Activities',
            data: dailyData.map(d => d.count),
            borderColor: 'rgb(102, 126, 234)',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Top Actions Chart
const actionData = <?php echo json_encode($actionStats); ?>;
new Chart(document.getElementById('actionChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: actionData.map(d => d.action),
        datasets: [{
            label: 'Count',
            data: actionData.map(d => d.count),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)',
                'rgba(199, 199, 199, 0.7)', 'rgba(83, 102, 255, 0.7)',
                'rgba(255, 99, 255, 0.7)', 'rgba(99, 255, 132, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Activity by Role Chart
const roleData = <?php echo json_encode($roleStats); ?>;
new Chart(document.getElementById('roleChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: roleData.map(d => d.role.toUpperCase()),
        datasets: [{
            data: roleData.map(d => d.count),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)'
            ]
        }]
    },
    options: { responsive: true, maintainAspectRatio: true }
});
</script>

<?php renderFooter(); ?>