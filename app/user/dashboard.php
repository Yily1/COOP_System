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
        m.id AS member_db_id,
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
// UPCOMING MEETINGS - NEXT 2
// ============================================================

$stmt = $pdo->prepare("
    SELECT 
        id,
        title,
        location,
        agenda,
        meeting_date,
        meeting_time
    FROM assembly_meetings
    WHERE meeting_date >= CURDATE()
    ORDER BY meeting_date ASC, meeting_time ASC
");

$stmt->execute();

$upcomingMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// DAILY ACTIVITY - LAST 7 DAYS
// (currently hidden on the dashboard - see .hidden-section below)
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

$totalActivities = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

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

// ============================================================
// DISPLAY NAME + GREETING (used in header)
// ============================================================

if ($myProfile && !empty($myProfile['membership_id'])) {
    $displayName = $myProfile['first_name'] ?: ($_SESSION['email'] ?? 'User');
} else {
    $displayName = $_SESSION['email'] ?? 'User';
}

$hour = (int) date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$hasProfile = $myProfile && !empty($myProfile['membership_id']);

// ============================================================
// PAGE TITLE
// ============================================================

$title = 'User Dashboard';

renderHeader($title);
?>


<style>

/* ============================================================
   USER DASHBOARD
============================================================ */

.user-dashboard {
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
}


/* ============================================================
   GREETING HEADER
============================================================ */

.dash-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}

.dash-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dash-avatar svg {
    width: 22px;
    height: 22px;
    fill: #2e7d32;
}

.dash-greeting-name {
    margin: 0;
    color: #1b3a24;
    font-size: 19px;
    font-weight: 600;
}

.dash-greeting-sub {
    margin: 2px 0 0 0;
    color: #667066;
    font-size: 14px;
}


/* ============================================================
   AGENDA / SECTION INTRO
============================================================ */

.agenda-intro {
    margin: 0 0 18px 0;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.agenda-intro h2 {
    margin: 0;
    color: #1b3a24;
    font-size: 18px;
    font-weight: 600;
}

.agenda-intro p {
    margin: 4px 0 0 0;
    color: #667066;
    font-size: 14px;
}

.agenda-intro .view-all-link {
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 600;
    color: #2e7d32;
    text-decoration: none;
    white-space: nowrap;
}

.agenda-intro .view-all-link:hover {
    text-decoration: underline;
}


/* ============================================================
   NAV CARDS
============================================================ */

.nav-card-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: 12px;
    margin-bottom: 22px;
}

.nav-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 72px;
    padding: 16px;
    box-sizing: border-box;
    border-radius: 14px;
    border: 1px solid #eceae4;
    background: #ffffff;
    text-decoration: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.nav-card:hover {
    border-color: #cfe8d1;
    box-shadow: 0 2px 8px rgba(46, 125, 50, 0.08);
}

.nav-card.blank {
    border-style: dashed;
    background: transparent;
    cursor: default;
}

.nav-card.blank:hover {
    border-color: #eceae4;
    box-shadow: none;
}

.nav-card.full-width {
    grid-column: 1 / -1;
}

.nav-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.nav-card-icon svg {
    width: 19px;
    height: 19px;
}

.nav-card.green .nav-card-icon { background: #e8f5e9; }
.nav-card.green .nav-card-icon svg { stroke: #2e7d32; }

.nav-card.blue .nav-card-icon { background: #e3edfb; }
.nav-card.blue .nav-card-icon svg { stroke: #274b81; }

.nav-card.purple .nav-card-icon { background: #eae6fb; }
.nav-card.purple .nav-card-icon svg { stroke: #4b2f9c; }

.nav-card.amber .nav-card-icon { background: #fdf1de; }
.nav-card.amber .nav-card-icon svg { stroke: #a06b16; }

.nav-card.rose .nav-card-icon { background: #fbe6e6; }
.nav-card.rose .nav-card-icon svg { stroke: #a6322f; }

.nav-card-title {
    margin: 0;
    color: #1b3a24;
    font-size: 14px;
    font-weight: 600;
}

.nav-card-sub {
    margin: 2px 0 0 0;
    color: #667066;
    font-size: 12px;
}


/* ============================================================
   UPCOMING MEETINGS PREVIEW
============================================================ */

.meeting-preview-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 22px;
}

.meeting-preview-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    box-sizing: border-box;
    border-radius: 14px;
    border: 1px solid #eceae4;
    background: #ffffff;
    text-decoration: none;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.meeting-preview-card:hover {
    border-color: #cfe8d1;
    box-shadow: 0 2px 8px rgba(46, 125, 50, 0.08);
}

.meeting-preview-date {
    flex-shrink: 0;
    width: 46px;
    text-align: center;
    background: #e8f5e9;
    border-radius: 8px;
    padding: 6px 4px;
}

.meeting-preview-date .month {
    font-size: 10px;
    font-weight: 700;
    color: #2e7d32;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.meeting-preview-date .day {
    font-size: 16px;
    font-weight: 700;
    color: #2e7d32;
    line-height: 1.2;
}

.meeting-preview-body {
    flex: 1;
    min-width: 0;
}

.meeting-preview-title {
    margin: 0;
    color: #1b3a24;
    font-size: 13.5px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.meeting-preview-sub {
    margin: 2px 0 0 0;
    color: #667066;
    font-size: 12px;
}

.meeting-preview-chevron {
    flex-shrink: 0;
    color: #a9a79f;
}

.meeting-preview-empty {
    padding: 16px;
    border-radius: 14px;
    border: 1px dashed #eceae4;
    text-align: center;
    color: #667066;
    font-size: 13px;
    margin-bottom: 22px;
}


/* ============================================================
   TIP BOX
============================================================ */

.tip-box {
    padding: 12px 14px;
    background: #e8f5e9;
    border-radius: 10px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.tip-box svg {
    width: 16px;
    height: 16px;
    fill: #2e7d32;
    flex-shrink: 0;
    margin-top: 1px;
}

.tip-box p {
    margin: 0;
    color: #29452f;
    font-size: 12.5px;
}


/* ============================================================
   HIDDEN SECTION (Activity Statistics / Analytics)
   Temporarily hidden per request - set display back to block
   (or remove this rule) to bring these back.
============================================================ */

.hidden-section {
    display: none;
}

.stat-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 30px;
}

.stat-card {
    min-height: 108px;
    padding: 18px 20px;
    box-sizing: border-box;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border: 1px solid #eceae4;
    background: #ffffff;
}

.stat-card.green { border-left: 4px solid #2e7d32; }
.stat-card.blue { border-left: 4px solid #274b81; }
.stat-card.purple { border-left: 4px solid #4b2f9c; }

.stat-number {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: #1b3a24;
    margin-bottom: 2px;
}

.stat-label {
    font-size: 13px;
    color: #667066;
}

.chart-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.chart-container {
    width: 100%;
    min-width: 0;
    background: #ffffff;
    border: 1px solid #eceae4;
    padding: 20px;
    box-sizing: border-box;
    border-radius: 14px;
}

.chart-container h3 {
    margin: 0 0 16px 0;
    color: #1b3a24;
    font-size: 15px;
    font-weight: 600;
}

.chart-wrapper {
    position: relative;
    width: 100%;
    height: 260px;
}

.chart-wrapper canvas {
    width: 100% !important;
    height: 100% !important;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 900px) {

    .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .chart-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 500px) {

    .nav-card-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .nav-card {
        flex-direction: column;
        align-items: flex-start;
        justify-content: flex-start;
        gap: 8px;
        min-height: 0;
        padding: 12px;
    }

    .nav-card.blank {
        display: none;
    }

    .nav-card.full-width {
        grid-column: 1 / -1;
        flex-direction: row;
        align-items: center;
    }

    .nav-card-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
    }

    .nav-card-icon svg {
        width: 13px;
        height: 13px;
    }

    .nav-card-title {
        font-size: 12.5px;
    }

    .nav-card-sub {
        font-size: 10.5px;
    }

    .dash-greeting-name {
        font-size: 17px;
    }
}

</style>


<!-- ============================================================
     USER DASHBOARD
============================================================ -->

<div class="user-dashboard">


    <!-- ========================================================
         GREETING HEADER
    ========================================================= -->

    <div class="dash-header">

        <div class="dash-avatar">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9v2.4h19.6v-2.4c0-3.3-6.5-4.9-9.8-4.9z"/></svg>
        </div>

        <div>
            <p class="dash-greeting-name">
                Hello, <?php echo htmlspecialchars($displayName); ?>!
            </p>
            <p class="dash-greeting-sub">
                <?php echo htmlspecialchars($greeting); ?>
            </p>
        </div>

    </div>


    <!-- ========================================================
         AGENDA INTRO
    ========================================================= -->

    <div class="agenda-intro">
        <div>
            <h2>Today&rsquo;s Overview</h2>
            <p>Here&rsquo;s what you can check and manage today.</p>
        </div>
    </div>


    <!-- ========================================================
         NAV CARDS
    ========================================================= -->

    <div class="nav-card-grid">

        <!-- PAYMENTS -->
        <a class="nav-card green" href="<?php echo BASE_URL; ?>/app/user/payments.php">
            <div class="nav-card-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
            </div>
            <div>
                <p class="nav-card-title">Payments</p>
                <p class="nav-card-sub">View your transactions</p>
            </div>
        </a>

        <!-- MY ACCOUNT + PROFILE INFO -->
        <a class="nav-card blue" href="<?php echo BASE_URL; ?>/app/users/dashboard.php">
            <div class="nav-card-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
            </div>
            <div>
                <p class="nav-card-title">My Account</p>
                <p class="nav-card-sub">
                    <?php
                    if ($hasProfile) {
                        echo htmlspecialchars($myProfile['membership_id']);
                    } else {
                        echo 'View and update your profile';
                    }
                    ?>
                </p>
            </div>
        </a>

        <!-- BLANK SLOTS - reserved for future features -->
        <div class="nav-card blank"></div>
        <div class="nav-card blank"></div>

    </div>


    <!-- ========================================================
         UPCOMING MEETINGS PREVIEW
    ========================================================= -->

    <div class="agenda-intro">
        <div>
            <h2>Upcoming Meetings</h2>
        </div>
    </div>

    <?php if (!empty($upcomingMeetings)): ?>

        <div class="meeting-preview-list">
            <?php foreach ($upcomingMeetings as $meeting): ?>
                <div class="meeting-preview-card"
                     data-title="<?php echo htmlspecialchars($meeting['title']); ?>"
                     data-location="<?php echo htmlspecialchars($meeting['location'] ?? ''); ?>"
                     data-agenda="<?php echo htmlspecialchars($meeting['agenda'] ?? ''); ?>"
                     data-date="<?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?>"
                     data-time="<?php echo !empty($meeting['meeting_time']) ? date('g:i A', strtotime($meeting['meeting_time'])) : ''; ?>">
                    <div class="meeting-preview-date">
                        <div class="month"><?php echo strtoupper(date('M', strtotime($meeting['meeting_date']))); ?></div>
                        <div class="day"><?php echo date('d', strtotime($meeting['meeting_date'])); ?></div>
                    </div>
                    <div class="meeting-preview-body">
                        <p class="meeting-preview-title"><?php echo htmlspecialchars($meeting['title']); ?></p>
                        <p class="meeting-preview-sub">
                            <?php
                            echo date('F j, Y', strtotime($meeting['meeting_date']));
                            if (!empty($meeting['meeting_time'])) {
                                echo ' &middot; ' . date('g:i A', strtotime($meeting['meeting_time']));
                            }
                            ?>
                        </p>
                    </div>
                    <svg class="meeting-preview-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>

        <div class="meeting-preview-empty">No upcoming meetings scheduled.</div>

    <?php endif; ?>


    <!-- ========================================================
         TIP
    ========================================================= -->

    <div class="tip-box">
        <svg viewBox="0 0 24 24"><path d="M9 21h6v-1H9v1zm3-19a7 7 0 0 0-4 12.7c.4.3.6.8.6 1.3H9v1h6v-1h-.6c0-.5.2-1 .6-1.3A7 7 0 0 0 12 2z"/></svg>
        <p>Keep your profile updated and check your pending tasks regularly.</p>
    </div>


    <!-- ========================================================
         ACTIVITY STATISTICS + ANALYTICS
         (hidden for now - remove/edit .hidden-section in the
         <style> block above to bring this back)
    ======================================================== -->

    <div class="hidden-section">

        <div class="agenda-intro" style="margin-top: 22px;">
            <h2>Your Activity Statistics</h2>
            <p>A quick look at what you've been doing lately.</p>
        </div>

        <div class="stat-grid">

            <!-- TOTAL ACTIVITIES -->
            <div class="stat-card green">
                <div class="stat-number"><?php echo $totalActivities; ?></div>
                <div class="stat-label">Total Activities</div>
            </div>

            <!-- ACTION TYPES -->
            <div class="stat-card blue">
                <div class="stat-number"><?php echo count($actionStats); ?></div>
                <div class="stat-label">Action Types</div>
            </div>

            <!-- LAST LOGIN -->
            <div class="stat-card purple">
                <div class="stat-number">
                    <?php
                    echo $lastLogin
                        ? date('M d', strtotime($lastLogin['created_at']))
                        : 'N/A';
                    ?>
                </div>
                <div class="stat-label">Last Login</div>
            </div>

        </div>

        <div class="agenda-intro">
            <h2>Your Activity Analytics</h2>
            <p>Trends from your recent account activity.</p>
        </div>

        <div class="chart-grid">

            <!-- DAILY ACTIVITY -->
            <div class="chart-container">
                <h3>Daily Activity (Last 7 Days)</h3>
                <div class="chart-wrapper">
                    <canvas id="dailyActivityChart"></canvas>
                </div>
            </div>

            <!-- ACTION BREAKDOWN -->
            <div class="chart-container">
                <h3>Actions Breakdown</h3>
                <div class="chart-wrapper">
                    <canvas id="actionChart"></canvas>
                </div>
            </div>

        </div>

    </div>


</div>


<!-- ============================================================
     MODAL - Meeting Details
============================================================ -->
<div id="meetingDetailsBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 14px; width: 100%; max-width: 400px; max-height: 80vh; overflow-y: auto; box-sizing: border-box; position: relative;">
        <button id="closeMeetingDetailsBtn"
                style="position: absolute !important; top: 14px !important; right: 14px !important; background: rgba(255,255,255,0.2) !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #fff !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 50% !important;">&times;</button>

        <div style="background: #2e7d32; padding: 28px 28px 20px;">
            <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: .06em; color: rgba(255,255,255,0.75); text-transform: uppercase;">Meeting details</p>
            <h2 id="mdTitle" style="margin: 0; font-size: 21px; font-weight: 700; color: #fff;"></h2>
        </div>

        <div style="padding: 24px 28px 28px;">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                <div style="background: #f7f8f5; border-radius: 10px; padding: 12px;">
                    <p style="margin: 0 0 3px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Date</p>
                    <p id="mdDate" style="margin: 0; font-size: 15px; font-weight: 600; color: #000;"></p>
                </div>
                <div style="background: #f7f8f5; border-radius: 10px; padding: 12px;">
                    <p style="margin: 0 0 3px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Time</p>
                    <p id="mdTime" style="margin: 0; font-size: 15px; font-weight: 600; color: #000;"></p>
                </div>
            </div>

            <div style="background: #f7f8f5; border-radius: 10px; padding: 12px; margin-bottom: 18px;">
                <p style="margin: 0 0 3px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Location</p>
                <p id="mdLocation" style="margin: 0; font-size: 15px; font-weight: 600; color: #000;"></p>
            </div>

            <div>
                <p style="margin: 0 0 8px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Agenda</p>
                <p id="mdAgenda" style="margin: 0; font-size: 14px; color: #000; line-height: 1.65; white-space: pre-line;"></p>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    const backdrop = document.getElementById('meetingDetailsBackdrop');
    const closeBtn = document.getElementById('closeMeetingDetailsBtn');
    const mdTitle = document.getElementById('mdTitle');
    const mdDate = document.getElementById('mdDate');
    const mdTime = document.getElementById('mdTime');
    const mdLocation = document.getElementById('mdLocation');
    const mdAgenda = document.getElementById('mdAgenda');

    function closeModal() { backdrop.style.display = 'none'; }
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    document.querySelectorAll('.meeting-preview-card').forEach(function(card) {
        card.addEventListener('click', function() {
            mdTitle.textContent = card.dataset.title;
            mdDate.textContent = card.dataset.date;
            mdTime.textContent = card.dataset.time || 'Not set';
            mdLocation.textContent = card.dataset.location || 'No location set';
            mdAgenda.textContent = card.dataset.agenda || 'No agenda provided.';
            backdrop.style.display = 'flex';
        });
    });
})();
</script>


<!-- ============================================================
     CHART.JS (only used by the hidden Activity Analytics section)
============================================================ -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>

const dailyCanvas = document.getElementById('dailyActivityChart');
const dailyData = <?php echo json_encode($dailyActivity); ?>;

if (dailyCanvas) {
    const dailyCtx = dailyCanvas.getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: 'Activities',
                data: dailyData.map(d => d.count),
                borderColor: 'rgb(46, 125, 50)',
                backgroundColor: 'rgba(46, 125, 50, 0.10)',
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
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

const actionCanvas = document.getElementById('actionChart');
const actionData = <?php echo json_encode($actionStats); ?>;

if (actionCanvas) {
    const actionCtx = actionCanvas.getContext('2d');
    new Chart(actionCtx, {
        type: 'doughnut',
        data: {
            labels: actionData.map(d => d.action),
            datasets: [{
                data: actionData.map(d => d.count),
                backgroundColor: [
                    'rgba(46, 125, 50, 0.85)',
                    'rgba(39, 75, 129, 0.85)',
                    'rgba(75, 47, 156, 0.85)',
                    'rgba(201, 138, 46, 0.85)',
                    'rgba(179, 39, 61, 0.85)',
                    'rgba(38, 198, 218, 0.85)',
                    'rgba(255, 202, 40, 0.85)',
                    'rgba(156, 39, 176, 0.85)',
                    'rgba(0, 150, 136, 0.85)',
                    'rgba(121, 134, 203, 0.85)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });
}

</script>


<?php

renderFooter();

?>