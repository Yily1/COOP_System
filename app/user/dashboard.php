<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';

requireRole('user');

$userId = $_SESSION['user_id'];

// ============================================================
// GET USER PROFILE
// ============================================================
$stmt = $pdo->prepare("
    SELECT 
        m.membership_id,
        m.last_name,
        m.first_name,
        m.middle_name,
        m.membership_type,
        m.farmer_type
    FROM users u
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$myProfile = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================================
// DAILY ACTIVITY - LAST 7 DAYS
// ============================================================
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) AS date,
        COUNT(*) AS count
    FROM activity_logs
    WHERE user_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$userId]);
$dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// ACTION DISTRIBUTION - LAST 30 DAYS
// ============================================================
$stmt = $pdo->prepare("
    SELECT 
        action,
        COUNT(*) AS count
    FROM activity_logs
    WHERE user_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY action
    ORDER BY count DESC
");
$stmt->execute([$userId]);
$actionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// TOTAL ACTIVITIES
// ============================================================
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM activity_logs
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$totalActivities = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// ============================================================
// LAST LOGIN
// ============================================================
$stmt = $pdo->prepare("
    SELECT created_at
    FROM activity_logs
    WHERE user_id = ?
    AND action = 'login'
    AND status = 'success'
    ORDER BY created_at DESC
    LIMIT 1, 1
");
$stmt->execute([$userId]);
$lastLogin = $stmt->fetch(PDO::FETCH_ASSOC);

$title = 'User Dashboard';
renderHeader($title);
?>


<style>


    .user-dashboard {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
    }

    .dashboard-title {
        margin: 0 0 5px 0;
        color: #2e7d32;
        font-size: 28px;
        font-weight: 500;
    }

    .dashboard-welcome {
        margin: 0 0 22px 0;
        color: #555;
        font-size: 17px;
    }



    .profile-card {
        width: 100%;
        background: #ffffff;
        border-radius: 10px;
        padding: 25px 28px;
        box-sizing: border-box;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }

    .profile-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
    }

    .profile-table tr:last-child {
        border-bottom: none;
    }

    .profile-table th {
        width: 220px;
        text-align: left;
        padding: 14px 10px 14px 0;
        font-weight: 500;
        color: #555;
        vertical-align: middle;
    }

    .profile-table td {
        padding: 14px 10px;
        color: #222;
        vertical-align: middle;
    }

    .profile-link {
        display: inline-block;
        margin-top: 18px;
        color: #2e7d32;
        text-decoration: none;
        font-weight: 500;
    }

    .profile-link:hover {
        text-decoration: underline;
    }


    .user-role-badge {
        display: inline-block;
        padding: 5px 12px;
        background: #e8f5e9;
        color: #2e7d32;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    

    .section-title {
        color: #2e7d32;
        font-size: 25px;
        font-weight: 500;
        margin: 0 0 18px 0;
    }

    .stat-grid {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 35px;
    }

    .stat-card {
        min-height: 120px;
        padding: 22px 24px;
        box-sizing: border-box;
        border-radius: 10px;
        color: white;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .stat-card.green {
        background: linear-gradient(
            135deg,
            #66bb6a 0%,
            #43a047 100%
        );
    }

    .stat-card.blue {
        background: linear-gradient(
            135deg,
            #42a5f5 0%,
            #1976d2 100%
        );
    }

    .stat-card.purple {
        background: linear-gradient(
            135deg,
            #ab47bc 0%,
            #8e24aa 100%
        );
    }

    .stat-number {
        font-size: 32px;
        font-weight: 500;
        line-height: 1.2;
        margin-bottom: 6px;
    }

    .stat-label {
        font-size: 15px;
        opacity: 0.95;
    }

    .chart-grid {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 35px;
    }

    .chart-container {
        width: 100%;
        min-width: 0;
        background: #ffffff;
        padding: 22px;
        box-sizing: border-box;
        border-radius: 10px;
        box-shadow: 0 2px 7px rgba(0, 0, 0, 0.08);
    }

    .chart-container h3 {
        margin: 0 0 18px 0;
        color: #333;
        font-size: 17px;
        font-weight: 500;
    }

    .chart-wrapper {
        position: relative;
        width: 100%;
        height: 280px;
    }

    .chart-wrapper canvas {
        width: 100% !important;
        height: 100% !important;
    }


    .account-card {
        width: 100%;
        background: #ffffff;
        padding: 25px 28px;
        box-sizing: border-box;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }

    .account-card h2 {
        margin: 0 0 18px 0;
        color: #2e7d32;
        font-size: 25px;
        font-weight: 500;
    }

    .account-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px 30px;
    }

    .account-info-item {
        padding: 14px 16px;
        background: #f7f9f7;
        border-radius: 7px;
        border-left: 4px solid #66bb6a;
    }

    .account-info-label {
        display: block;
        color: #666;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .account-info-value {
        color: #222;
        font-size: 15px;
        font-weight: 500;
    }

    .features-box {
        margin-top: 20px;
        padding: 18px;
        background: #f7f9f7;
        border-radius: 8px;
    }

    .features-box strong {
        color: #2e7d32;
    }

    .features-box ul {
        margin: 10px 0 0 20px;
        line-height: 1.8;
    }

    @media (max-width: 900px) {

        .stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .chart-grid {
            grid-template-columns: 1fr;
        }

        .account-info-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {

        .dashboard-title {
            font-size: 24px;
        }

        .profile-card,
        .account-card {
            padding: 20px;
        }

        .profile-table th {
            width: 140px;
        }

        .stat-grid {
            grid-template-columns: 1fr;
        }

        .stat-card {
            min-height: 105px;
        }

        .chart-container {
            padding: 18px;
        }

        .chart-wrapper {
            height: 250px;
        }
    }

    @media (max-width: 500px) {

        .profile-table,
        .profile-table tbody,
        .profile-table tr,
        .profile-table th,
        .profile-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .profile-table th {
            padding: 12px 0 3px;
            border: none;
        }

        .profile-table td {
            padding: 3px 0 12px;
        }

        .profile-table tr {
            padding-bottom: 5px;
        }

        .dashboard-welcome {
            font-size: 15px;
        }
    }

</style>


<!-- ============================================================
     USER DASHBOARD
============================================================ -->

<div class="user-dashboard">

    <!-- ========================================================
         PROFILE
    ========================================================= -->
    <div class="profile-card">

        <h2 class="dashboard-title">
            User Dashboard
        </h2>

        <p class="dashboard-welcome">
            <strong>
                Welcome,
                <?php
                echo (
                    $myProfile &&
                    $myProfile['membership_id']
                )
                    ? htmlspecialchars(
                        $myProfile['last_name'] .
                        ', ' .
                        $myProfile['first_name']
                    )
                    : htmlspecialchars($_SESSION['email']);
                ?>!
            </strong>
        </p>

        <table class="profile-table">

            <?php if ($myProfile && $myProfile['membership_id']): ?>

                <tr>
                    <th>Membership ID</th>
                    <td>
                        <?php
                        echo htmlspecialchars(
                            $myProfile['membership_id']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>Type of Farmer</th>
                    <td>
                        <?php
                        echo htmlspecialchars(
                            $myProfile['farmer_type'] ?? '-'
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>Membership Type</th>
                    <td>
                        <?php
                        echo htmlspecialchars(
                            $myProfile['membership_type']
                        );
                        ?>
                    </td>
                </tr>

            <?php else: ?>

                <tr>
                    <td colspan="2" style="color:#666;">
                        No member profile linked to this account yet.
                    </td>
                </tr>

            <?php endif; ?>

            <tr>
                <th>Email</th>
                <td>
                    <?php
                    echo htmlspecialchars($_SESSION['email']);
                    ?>
                </td>
            </tr>

            <tr>
                <th>Role</th>
                <td>
                    <span class="user-role-badge">
                        User
                    </span>
                </td>
            </tr>

        </table>

        <?php if ($myProfile && $myProfile['membership_id']): ?>

            <a
                class="profile-link"
                href="<?php
                    echo BASE_URL;
                ?>/app/users/user-view.php?user_id=<?php
                    echo $userId;
                ?>"
            >
                View Full Information
            </a>

        <?php endif; ?>

    </div>


    <!-- ========================================================
         ACTIVITY STATISTICS
    ========================================================= -->

    <h2 class="section-title">
        Your Activity Statistics
    </h2>

    <div class="stat-grid">

        <!-- TOTAL ACTIVITIES -->
        <div class="stat-card green">

            <div class="stat-number">
                <?php echo $totalActivities; ?>
            </div>

            <div class="stat-label">
                Total Activities
            </div>

        </div>


        <!-- ACTION TYPES -->
        <div class="stat-card blue">

            <div class="stat-number">
                <?php echo count($actionStats); ?>
            </div>

            <div class="stat-label">
                Action Types
            </div>

        </div>


        <!-- LAST LOGIN -->
        <div class="stat-card purple">

            <div class="stat-number">
                <?php
                echo $lastLogin
                    ? date(
                        'M d',
                        strtotime($lastLogin['created_at'])
                    )
                    : 'N/A';
                ?>
            </div>

            <div class="stat-label">
                Last Login
            </div>

        </div>

    </div>


    <!-- ========================================================
         ACTIVITY ANALYTICS
    ========================================================= -->

    <h2 class="section-title">
        Your Activity Analytics
    </h2>

    <div class="chart-grid">

        <!-- DAILY ACTIVITY -->
        <div class="chart-container">

            <h3>
                Your Daily Activity (Last 7 Days)
            </h3>

            <div class="chart-wrapper">
                <canvas id="dailyActivityChart"></canvas>
            </div>

        </div>


        <!-- ACTION BREAKDOWN -->
        <div class="chart-container">

            <h3>
                Your Actions Breakdown
            </h3>

            <div class="chart-wrapper">
                <canvas id="actionChart"></canvas>
            </div>

        </div>

    </div>


    <!-- ========================================================
         ACCOUNT INFORMATION
    ========================================================= -->

    <div class="account-card">

        <h2>
            Account Information
        </h2>

        <div class="account-info-grid">

            <div class="account-info-item">

                <span class="account-info-label">
                    Email
                </span>

                <span class="account-info-value">
                    <?php
                    echo htmlspecialchars($_SESSION['email']);
                    ?>
                </span>

            </div>


            <div class="account-info-item">

                <span class="account-info-label">
                    Role
                </span>

                <span class="account-info-value">
                    User
                </span>

            </div>


            <div class="account-info-item">

                <span class="account-info-label">
                    Access Level
                </span>

                <span class="account-info-value">
                    View own profile and activity only
                </span>

            </div>

        </div>


        <div class="features-box">

            <strong>
                Features:
            </strong>

            <ul>

                <li>
                    View your activity history
                </li>

                <li>
                    Update your profile
                </li>

                <li>
                    Change your password
                </li>

            </ul>

        </div>

    </div>

</div>


<!-- ============================================================
     CHART.JS
============================================================ -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>

    // ============================================================
    // DAILY ACTIVITY CHART
    // ============================================================

    const dailyCtx =
        document
            .getElementById('dailyActivityChart')
            .getContext('2d');

    const dailyData =
        <?php echo json_encode($dailyActivity); ?>;

    new Chart(dailyCtx, {

        type: 'line',

        data: {

            labels:
                dailyData.map(
                    d => d.date
                ),

            datasets: [{

                label: 'Activities',

                data:
                    dailyData.map(
                        d => d.count
                    ),

                borderColor:
                    'rgb(102, 187, 106)',

                backgroundColor:
                    'rgba(102, 187, 106, 0.12)',

                borderWidth: 2,

                tension: 0.4,

                fill: true,

                pointRadius: 4,

                pointHoverRadius: 6

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    display: false
                }

            },

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }

                }

            }

        }

    });


    // ============================================================
    // ACTION DISTRIBUTION CHART
    // ============================================================

    const actionCtx =
        document
            .getElementById('actionChart')
            .getContext('2d');

    const actionData =
        <?php echo json_encode($actionStats); ?>;

    new Chart(actionCtx, {

        type: 'doughnut',

        data: {

            labels:
                actionData.map(
                    d => d.action
                ),

            datasets: [{

                data:
                    actionData.map(
                        d => d.count
                    ),

                backgroundColor: [

                    'rgba(102, 187, 106, 0.75)',
                    'rgba(66, 165, 245, 0.75)',
                    'rgba(171, 71, 188, 0.75)',
                    'rgba(255, 167, 38, 0.75)',
                    'rgba(239, 83, 80, 0.75)',
                    'rgba(38, 198, 218, 0.75)',
                    'rgba(255, 202, 40, 0.75)',
                    'rgba(156, 39, 176, 0.75)',
                    'rgba(0, 150, 136, 0.75)',
                    'rgba(121, 134, 203, 0.75)'

                ],

                borderWidth: 1

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {

                    position: 'right'

                }

            }

        }

    });

</script>


<?php renderFooter(); ?>