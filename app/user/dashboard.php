<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/funds.php';

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
    SELECT id, title, location, description, meeting_date, `time`
    FROM meetings
    WHERE meeting_date >= CURDATE()
    ORDER BY meeting_date ASC, `time` ASC
");
$stmt->execute();
$upcomingMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// COOP NET INCOME (Same as Manager)
// ============================================================
$net = getNetIncomeSummary($pdo);

function udPeso0($v) {
    $v = (float) $v;
    return ($v < 0 ? '-' : '') . '₱' . number_format(abs($v), 0);
}

function udShort($v) {
    $v = (float) $v;
    $a = abs($v);
    if ($a >= 1000) {
        $s = rtrim(rtrim(number_format($a / 1000, 1), '0'), '.') . 'k';
    } else {
        $s = number_format($a, 0);
    }
    return ($v < 0 ? '-' : '') . $s;
}

$netTotal = (float) $net['total'];

// Line chart with dots and values (Option 6)
$chartW = 300; $chartH = 84;
$chartLeft = 14; $chartRight = 284;
$plotTop = 26; $plotBot = 58;
$chartBase = 62; $chartMonthY = 79;

$chartVals = array_column($net['months'], 'net');
$chartLabels = array_column($net['months'], 'label');
$chartLo = min($chartVals);
$chartHi = max($chartVals);
$chartRange = $chartHi - $chartLo;
$chartStep = ($chartRight - $chartLeft) / max(1, count($chartVals) - 1);

$chartPoints = [];
foreach ($chartVals as $i => $val) {
    $x = $chartLeft + $chartStep * $i;
    $y = $chartRange > 0
        ? $plotBot - (($val - $chartLo) / $chartRange) * ($plotBot - $plotTop)
        : ($plotTop + $plotBot) / 2;
    $chartPoints[] = ['x' => round($x, 1), 'y' => round($y, 1), 'val' => $val, 'label' => $chartLabels[$i]];
}
$chartLine = implode(' ', array_map(fn($p) => $p['x'] . ',' . $p['y'], $chartPoints));
$chartLastIndex = count($chartPoints) - 1;

// ============================================================
// DISPLAY NAME + GREETING
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

$title = 'User Dashboard';
renderHeader($title);
?>

<style>
/* ============================================================
   USER DASHBOARD STYLES
============================================================ */
.user-dashboard { width: 100%; max-width: 420px; margin: 0 auto; }

/* GREETING HEADER */
.dash-header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
.dash-avatar { width: 48px; height: 48px; border-radius: 50%; background: #e8f5e9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.dash-avatar svg { width: 22px; height: 22px; fill: #2e7d32; }
.dash-greeting-name { margin: 0; color: #1b3a24; font-size: 19px; font-weight: 600; }
.dash-greeting-sub { margin: 2px 0 0 0; color: #667066; font-size: 14px; }

/* COOP NET INCOME CARD */
.net-card { padding: 10px 14px 8px; margin-bottom: 20px; box-sizing: border-box; border-radius: 14px; border: 1px solid #eceae4; background: #ffffff; }
.net-card-label { display: flex; align-items: center; gap: 5px; margin: 0; color: #667066; font-size: 12px; }
.net-card-label svg { width: 14px; height: 14px; stroke: #2e7d32; flex-shrink: 0; }
.net-card-num { margin: 2px 0 0 0; color: #1b3a24; font-size: 22px; font-weight: 700; line-height: 1.2; }
.net-card-num.neg { color: #a6322f; }
.net-card-delta { margin: 2px 0 0 0; font-size: 11px; }
.net-card-delta.flat { color: #667066; }
.net-card-chart { display: block; width: 100%; height: auto; margin-top: 6px; }

/* AGENDA */
.agenda-intro { margin: 0 0 18px 0; display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.agenda-intro h2 { margin: 0; color: #1b3a24; font-size: 18px; font-weight: 600; }
.agenda-intro p { margin: 4px 0 0 0; color: #667066; font-size: 14px; }
.agenda-intro .view-all-link { flex-shrink: 0; font-size: 13px; font-weight: 600; color: #2e7d32; text-decoration: none; white-space: nowrap; }
.agenda-intro .view-all-link:hover { text-decoration: underline; }

/* NAV CARDS */
.nav-card-grid { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch; gap: 12px; margin-bottom: 22px; }
.nav-card { display: flex; align-items: center; gap: 12px; min-height: 72px; padding: 16px; box-sizing: border-box; border-radius: 14px; border: 1px solid #eceae4; background: #ffffff; text-decoration: none; transition: border-color .15s ease, box-shadow .15s ease; }
.nav-card:hover { border-color: #cfe8d1; box-shadow: 0 2px 8px rgba(46, 125, 50, 0.08); }
.nav-card.blank { border-style: dashed; background: transparent; cursor: default; }
.nav-card.full-width { grid-column: 1 / -1; }
.nav-card-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.nav-card-icon svg { width: 19px; height: 19px; }
.nav-card.green .nav-card-icon { background: #e8f5e9; } .nav-card.green .nav-card-icon svg { stroke: #2e7d32; }
.nav-card.blue .nav-card-icon { background: #e3edfb; } .nav-card.blue .nav-card-icon svg { stroke: #274b81; }
.nav-card.purple .nav-card-icon { background: #eae6fb; } .nav-card.purple .nav-card-icon svg { stroke: #4b2f9c; }
.nav-card.amber .nav-card-icon { background: #fdf1de; } .nav-card.amber .nav-card-icon svg { stroke: #a06b16; }
.nav-card.rose .nav-card-icon { background: #fbe6e6; } .nav-card.rose .nav-card-icon svg { stroke: #a6322f; }
.nav-card-title { margin: 0; color: #1b3a24; font-size: 14px; font-weight: 600; }
.nav-card-sub { margin: 2px 0 0 0; color: #667066; font-size: 12px; }

/* MEETINGS */
.meeting-preview-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 22px; }
.meeting-preview-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; box-sizing: border-box; border-radius: 14px; border: 1px solid #eceae4; background: #ffffff; text-decoration: none; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease; }
.meeting-preview-card:hover { border-color: #cfe8d1; box-shadow: 0 2px 8px rgba(46, 125, 50, 0.08); }
.meeting-preview-date { flex-shrink: 0; width: 46px; text-align: center; background: #e8f5e9; border-radius: 8px; padding: 6px 4px; }
.meeting-preview-date .month { font-size: 10px; font-weight: 700; color: #2e7d32; text-transform: uppercase; letter-spacing: .03em; }
.meeting-preview-date .day { font-size: 16px; font-weight: 700; color: #2e7d32; line-height: 1.2; }
.meeting-preview-body { flex: 1; min-width: 0; }
.meeting-preview-title { margin: 0; color: #1b3a24; font-size: 13.5px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.meeting-preview-sub { margin: 2px 0 0 0; color: #667066; font-size: 12px; }
.meeting-preview-chevron { flex-shrink: 0; color: #a9a79f; }
.meeting-preview-empty { padding: 16px; border-radius: 14px; border: 1px dashed #eceae4; text-align: center; color: #667066; font-size: 13px; margin-bottom: 22px; }

/* TIP */
.tip-box { padding: 12px 14px; background: #e8f5e9; border-radius: 10px; display: flex; gap: 10px; align-items: flex-start; }
.tip-box svg { width: 16px; height: 16px; fill: #2e7d32; flex-shrink: 0; margin-top: 1px; }
.tip-box p { margin: 0; color: #29452f; font-size: 12.5px; }

/* MEETING MODAL */
.md-backdrop { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 100; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
.md-modal { position: relative; display: flex; flex-direction: column; width: 78%; max-width: 280px; max-height: 80vh; background: #fff; border-radius: 12px; overflow: hidden; box-sizing: border-box; }
.md-header { flex-shrink: 0; background: #2e7d32; padding: 12px 40px 10px 14px; }
.md-eyebrow { margin: 0 0 2px; font-size: 10px; font-weight: 700; letter-spacing: .06em; color: rgba(255, 255, 255, 0.8); text-transform: uppercase; line-height: 1.4; }
.md-title { margin: 0; font-size: 16px; font-weight: 700; color: #fff; line-height: 1.3; word-break: break-word; }
button.md-close { position: absolute !important; top: 8px !important; right: 8px !important; display: inline-flex !important; align-items: center; justify-content: center; width: 22px !important; height: 22px !important; max-width: 22px !important; flex: 0 0 auto !important; padding: 0 !important; background: rgba(255, 255, 255, 0.22) !important; border: none !important; border-radius: 50% !important; color: #fff !important; font-size: 16px !important; line-height: 1 !important; cursor: pointer; box-shadow: none !important; }
.md-body { padding: 10px 14px 14px; overflow-y: auto; }
.md-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 6px; }
.md-box { background: #f7f8f5; border-radius: 8px; padding: 6px 8px; min-width: 0; }
.md-label { margin: 0 0 1px; font-size: 10px; font-weight: 700; letter-spacing: .04em; color: #7c877c; text-transform: uppercase; line-height: 1.4; }
.md-value { margin: 0; font-size: 12px; font-weight: 600; color: #1b1b1b; line-height: 1.4; word-break: break-word; }
.md-desc-text { margin: 0; font-size: 12px; color: #1b1b1b; line-height: 1.5; white-space: pre-line; word-break: break-word; }

/* RESPONSIVE */
@media (max-width: 500px) {
    .nav-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .nav-card { flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: 8px; min-height: 0; padding: 12px; }
    .nav-card.full-width { grid-column: 1 / -1; flex-direction: row; align-items: center; }
    .nav-card-icon { width: 28px; height: 28px; border-radius: 8px; }
    .nav-card-icon svg { width: 13px; height: 13px; }
    .nav-card-title { font-size: 12.5px; }
    .nav-card-sub { font-size: 10.5px; }
    .dash-greeting-name { font-size: 17px; }
    .net-card-num { font-size: 20px; }
}
</style>

<!-- ============================================================
     USER DASHBOARD CONTENT
============================================================ -->
<div class="user-dashboard">

    <!-- GREETING HEADER -->
    <div class="dash-header">
        <div class="dash-avatar">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9v2.4h19.6v-2.4c0-3.3-6.5-4.9-9.8-4.9z"/></svg>
        </div>
        <div>
            <p class="dash-greeting-name">Hello, <?php echo htmlspecialchars($displayName); ?>!</p>
            <p class="dash-greeting-sub"><?php echo htmlspecialchars($greeting); ?></p>
        </div>
    </div>

    <!-- COOP NET INCOME -->
    <div class="net-card">
        <p class="net-card-label">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/></svg>
            Coop net income
        </p>
        <p class="net-card-num<?php echo $netTotal < 0 ? ' neg' : ''; ?>"><?php echo udPeso0($netTotal); ?></p>
        <p class="net-card-delta flat">Total cooperative earnings</p>

        <svg class="net-card-chart" viewBox="0 0 <?php echo $chartW; ?> <?php echo $chartH; ?>" role="img" aria-label="Net income per month for the last six months">
            <line x1="0" y1="<?php echo $chartBase; ?>" x2="<?php echo $chartW; ?>" y2="<?php echo $chartBase; ?>" stroke="#eceae4" stroke-width="1"/>
            <polyline points="<?php echo $chartLine; ?>" fill="none" stroke="#1baf7a" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
            <?php foreach ($chartPoints as $i => $p):
                $isLast = ($i === $chartLastIndex);
                $labelX = min(max($p['x'], 16), $chartW - 16);
            ?>
                <circle cx="<?php echo $p['x']; ?>" cy="<?php echo $p['y']; ?>" r="<?php echo $isLast ? 4 : 3; ?>" fill="#1baf7a"/>
                <text x="<?php echo $labelX; ?>" y="<?php echo $p['y'] - 8; ?>" text-anchor="middle" font-size="11" fill="<?php echo $isLast ? '#1b3a24' : '#667066'; ?>"<?php echo $isLast ? ' font-weight="600"' : ''; ?>><?php echo htmlspecialchars(udShort($p['val'])); ?></text>
                <text x="<?php echo $labelX; ?>" y="<?php echo $chartMonthY; ?>" text-anchor="middle" font-size="11" fill="#667066"><?php echo htmlspecialchars($p['label']); ?></text>
            <?php endforeach; ?>
        </svg>
    </div>

    <!-- AGENDA INTRO -->
    <div class="agenda-intro">
        <div>
            <h2>Today&rsquo;s Overview</h2>
            <p>Here&rsquo;s what you can check and manage today.</p>
        </div>
    </div>

    <!-- NAV CARDS -->
    <div class="nav-card-grid">
        <a class="nav-card blue" href="<?php echo BASE_URL; ?>/app/user/profile.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></div>
            <div><p class="nav-card-title">My Account</p><p class="nav-card-sub"><?php echo $hasProfile ? htmlspecialchars($myProfile['membership_id']) : 'View and update your profile'; ?></p></div>
        </a>
        <a class="nav-card purple" href="<?php echo BASE_URL; ?>/app/user/checkins.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><p class="nav-card-title">Meetings Records</p><p class="nav-card-sub">View meeting history</p></div>
        </a>
        <a class="nav-card green" href="<?php echo BASE_URL; ?>/app/user/payments.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div>
            <div><p class="nav-card-title">Transactions</p><p class="nav-card-sub">View your payments</p></div>
        </a>
        <a class="nav-card purple" href="<?php echo BASE_URL; ?>/app/user/products.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg></div>
            <div><p class="nav-card-title">Products</p><p class="nav-card-sub">Browse co-op products</p></div>
        </a>
        <a class="nav-card amber" href="<?php echo BASE_URL; ?>/app/user/equipment.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L2 19l3 3 7.3-7.3a4 4 0 0 0 5.4-5.4l-2.1 2.1-2.7-2.7z"/></svg></div>
            <div><p class="nav-card-title">Equipment</p><p class="nav-card-sub">Borrow or reserve tools</p></div>
        </a>
        <a class="nav-card blue" href="<?php echo BASE_URL; ?>/app/user/loans.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M9 9.5c0-1.4 1.3-2.5 3-2.5s3 1.1 3 2.5-1.3 2-3 2.5-3 1.1-3 2.5 1.3 2.5 3 2.5 3-1.1 3-2.5"/></svg></div>
            <div><p class="nav-card-title">Loans</p><p class="nav-card-sub">View loan status</p></div>
        </a>
        <a class="nav-card green" href="<?php echo BASE_URL; ?>/app/user/crops.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M12 12C12 6 6 5 4 5c0 6 2 9 8 7z"/><path d="M12 12c0-6 6-7 8-7 0 6-2 9-8 7z"/></svg></div>
            <div><p class="nav-card-title">Crops</p><p class="nav-card-sub">My crop plantings</p></div>
        </a>
        <a class="nav-card rose" href="<?php echo BASE_URL; ?>/app/user/resources.php">
            <div class="nav-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg></div>
            <div><p class="nav-card-title">Agriculture Resources</p><p class="nav-card-sub">View your resources</p></div>
        </a>
    </div>

    <!-- UPCOMING MEETINGS PREVIEW -->
    <div class="agenda-intro">
        <div><h2>Upcoming Meetings</h2></div>
    </div>

    <?php if (!empty($upcomingMeetings)): ?>
        <div class="meeting-preview-list">
            <?php foreach ($upcomingMeetings as $meeting): ?>
                <div class="meeting-preview-card"
                     data-title="<?php echo htmlspecialchars($meeting['title']); ?>"
                     data-location="<?php echo htmlspecialchars($meeting['location'] ?? ''); ?>"
                     data-agenda="<?php echo htmlspecialchars($meeting['description'] ?? ''); ?>"
                     data-date="<?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?>"
                     data-time="<?php echo !empty($meeting['time']) ? date('g:i A', strtotime($meeting['time'])) : ''; ?>">
                    <div class="meeting-preview-date">
                        <div class="month"><?php echo strtoupper(date('M', strtotime($meeting['meeting_date']))); ?></div>
                        <div class="day"><?php echo date('d', strtotime($meeting['meeting_date'])); ?></div>
                    </div>
                    <div class="meeting-preview-body">
                        <p class="meeting-preview-title"><?php echo htmlspecialchars($meeting['title']); ?></p>
                        <p class="meeting-preview-sub">
                            <?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?>
                            <?php if (!empty($meeting['time'])) echo ' &middot; ' . date('g:i A', strtotime($meeting['time'])); ?>
                        </p>
                    </div>
                    <svg class="meeting-preview-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="meeting-preview-empty">No upcoming meetings scheduled.</div>
    <?php endif; ?>

    <!-- TIP -->
    <div class="tip-box">
        <svg viewBox="0 0 24 24"><path d="M9 21h6v-1H9v1zm3-19a7 7 0 0 0-4 12.7c.4.3.6.8.6 1.3H9v1h6v-1h-.6c0-.5.2-1 .6-1.3A7 7 0 0 0 12 2z"/></svg>
        <p>Keep your profile updated and check your pending tasks regularly.</p>
    </div>

</div>

<!-- MEETING DETAILS MODAL -->
<div id="meetingDetailsBackdrop" class="md-backdrop">
    <div class="md-modal">
        <button id="closeMeetingDetailsBtn" type="button" class="md-close" aria-label="Close">&times;</button>
        <div class="md-header">
            <p class="md-eyebrow">Meeting details</p>
            <h2 id="mdTitle" class="md-title"></h2>
        </div>
        <div class="md-body">
            <div class="md-grid">
                <div class="md-box"><p class="md-label">Date</p><p id="mdDate" class="md-value"></p></div>
                <div class="md-box"><p class="md-label">Time</p><p id="mdTime" class="md-value"></p></div>
            </div>
            <div id="mdLocationWrap" class="md-box" style="margin-bottom: 10px;">
                <p class="md-label">Location</p><p id="mdLocation" class="md-value"></p>
            </div>
            <div id="mdDescWrap">
                <p class="md-label" style="margin-bottom: 3px;">Description</p><p id="mdAgenda" class="md-desc-text"></p>
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
    const mdLocationWrap = document.getElementById('mdLocationWrap');
    const mdAgenda = document.getElementById('mdAgenda');
    const mdDescWrap = document.getElementById('mdDescWrap');

    function closeModal() { backdrop.style.display = 'none'; }
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    document.querySelectorAll('.meeting-preview-card').forEach(function(card) {
        card.addEventListener('click', function() {
            mdTitle.textContent = card.dataset.title;
            mdDate.textContent = card.dataset.date;
            mdTime.textContent = card.dataset.time || 'Not set';
            if (card.dataset.location) { mdLocation.textContent = card.dataset.location; mdLocationWrap.style.display = 'block'; } else { mdLocationWrap.style.display = 'none'; }
            if (card.dataset.agenda) { mdAgenda.textContent = card.dataset.agenda; mdDescWrap.style.display = 'block'; } else { mdDescWrap.style.display = 'none'; }
            backdrop.style.display = 'flex';
        });
    });
})();
</script>

<?php renderFooter(); ?>