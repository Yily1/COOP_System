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

renderHeader('Admin Dashboard');
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

<style>

/* ============================================================
   DEFENSIVE WIDTH FIX (page-level)
   Forces the box/main/container chain to fill the viewport
   regardless of whether functions.php has the global fix yet.
============================================================ */
.box {
    width: 100% !important;
    min-height: 100vh;
}
.main {
    flex: 1 1 auto !important;
    min-width: 0 !important;
}
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
.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    min-width: 0;
    box-sizing: border-box;
}
.stat-card h3 {
    margin: 0;
    font-size: 32px;
}
.stat-card p {
    margin: 5px 0 0;
    opacity: 0.9;
}
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
table.dataTable {
    width: 100% !important;
}
.chart-container canvas {
    max-width: 100%;
}
.badge-success { background: #28a745; }
.badge-failed { background: #dc3545; }
</style>

<h1>Admin Dashboard</h1>

<div class="info-box">
    <strong>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></strong><br>
    Role: <span class="badge badge-admin">Admin</span>
</div>

<h2>System Statistics</h2>

<div class="stat-grid">
    <div class="stat-card" style="background: #3F51B5; color: #fff;">
        <h3><?php echo $totalUsers; ?></h3>
        <p>Total Users</p>
    </div>

    <div class="stat-card" style="background: #E53935; color: #fff;">
        <h3><?php echo $totalAdmins; ?></h3>
        <p>Admins</p>
    </div>

    <div class="stat-card" style="background: #FB8C00; color: #fff;">
        <h3><?php echo $totalManagers; ?></h3>
        <p>Managers</p>
    </div>

    <div class="stat-card" style="background: #43A047; color: #fff;">
        <h3><?php echo $totalRegularUsers; ?></h3>
        <p>Regular Users</p>
    </div>

    <div class="stat-card" style="background: #FBC02D; color: #212121;">
        <h3><?php echo $unverifiedUsers; ?></h3>
        <p>Unverified</p>
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

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Daily Activity Chart
const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
const dailyData = <?php echo json_encode($dailyActivity); ?>;
new Chart(dailyCtx, {
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
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Action Distribution Chart
const actionCtx = document.getElementById('actionChart').getContext('2d');
const actionData = <?php echo json_encode($actionStats); ?>;
new Chart(actionCtx, {
    type: 'bar',
    data: {
        labels: actionData.map(d => d.action),
        datasets: [{
            label: 'Count',
            data: actionData.map(d => d.count),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(199, 199, 199, 0.7)',
                'rgba(83, 102, 255, 0.7)',
                'rgba(255, 99, 255, 0.7)',
                'rgba(99, 255, 132, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Role Distribution Chart
const roleCtx = document.getElementById('roleChart').getContext('2d');
const roleData = <?php echo json_encode($roleStats); ?>;
new Chart(roleCtx, {
    type: 'doughnut',
    data: {
        labels: roleData.map(d => d.role.toUpperCase()),
        datasets: [{
            data: roleData.map(d => d.count),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// DataTable for Activity Logs
$(document).ready(function() {
    $('#activityTable').DataTable({
        processing: true,
        serverSide: false,
        responsive: true,
        ajax: {
            url: '../../api/get-activity-logs.php',
            type: 'POST',
            data: { role: 'admin' }
        },
        columns: [
            { data: 'id' },
            { data: 'email' },
            { 
                data: 'user_role',
                render: function(data) {
                    if (!data) return '<span class="badge" style="background: #999;">Unknown</span>';
                    return '<span class="badge badge-' + data + '">' + data.toUpperCase() + '</span>';
                }
            },
            { data: 'action' },
            { 
                data: 'status',
                render: function(data) {
                    const badgeClass = data === 'success' ? 'badge-success' : 'badge-failed';
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { data: 'ip_address' },
            { 
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleString();
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
    });
});
</script>

<?php renderFooter(); ?>