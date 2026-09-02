<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';

requireRole('manager');

// Get all meetings, most recent first
$stmt = $pdo->query("SELECT * FROM assembly_meetings ORDER BY id DESC");
$allMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all members for the "Attendance summary per member" search
$memberStmt = $pdo->query("SELECT id, membership_id, last_name, first_name FROM members ORDER BY last_name, first_name");
$members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// STATS
// ============================================================

$totalMeetingsCount = count($allMeetings);

$upcomingMeetingsCount = (int) $pdo->query("
    SELECT COUNT(*) FROM assembly_meetings WHERE meeting_date >= CURDATE()
")->fetchColumn();

$thisMonthMeetingsCount = (int) $pdo->query("
    SELECT COUNT(*) FROM assembly_meetings
    WHERE YEAR(meeting_date) = YEAR(CURDATE()) AND MONTH(meeting_date) = MONTH(CURDATE())
")->fetchColumn();

renderHeader('Assembly Meetings');
?>

<style>
.meetings-table-wrap {
    border: 1px solid #eceae4;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 8px;
}
.meetings-table-scroll {
    max-height: 320px;
    overflow-y: auto;
    overflow-x: auto;
}
.meetings-table {
    width: 100%;
    min-width: 640px;
    border-collapse: collapse;
    font-size: 13px;
}
.meetings-table thead th {
    position: sticky;
    top: 0;
    text-align: left;
    padding: 10px 14px;
    background: #f7f8f5;
    color: #667066;
    font-weight: 600;
    font-size: 12px;
    border-bottom: 1px solid #eceae4;
    white-space: nowrap;
}
.meetings-table thead th.actions-col { text-align: right; }
.meetings-table tbody tr.meeting-row { transition: background-color .15s ease; }
.meetings-table tbody tr.meeting-row:not(:first-child) td { border-top: 1px solid #eee; }
.meetings-table tbody tr.meeting-row.selected { background: #eef4fb; }
.meetings-table td {
    padding: 10px 14px;
    color: #333;
    vertical-align: middle;
}
.meetings-table td.title-col { font-weight: 600; color: #1b3a24; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.meetings-table td.meta-col { color: #667066; white-space: nowrap; }
.meetings-table td.actions-col { text-align: right; white-space: nowrap; }
.meetings-table td.actions-col button {
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    width: auto !important;
    max-width: none !important;
    flex: 0 0 auto !important;
    padding: 5px 9px !important;
    font-size: 12px !important;
    line-height: 1.4 !important;
    border-radius: 5px !important;
    cursor: pointer;
    margin-left: 4px;
    white-space: nowrap;
    background: #fff !important;
    border: 1px solid #eceae4 !important;
    color: #444 !important;
}
.meetings-table td.actions-col button:first-child { margin-left: 0; }
.meetings-table td.actions-col button .material-icons {
    font-size: 14px !important;
}
.meetings-table td.actions-col button:hover {
    background: #f7f8f5 !important;
    border-color: #d6d3c7 !important;
}
.meetings-table td.actions-col button.view-details-btn {
    color: #444 !important;
}
.meetings-table td.actions-col button.take-attendance-btn {
    color: #2e7d32 !important;
}
.meetings-table td.actions-col button.edit-meeting-btn {
    color: #274b81 !important;
}
.meetings-table td.actions-col button.delete-meeting-btn {
    color: #a6322f !important;
}

@media (max-width: 500px) {
    #statsGrid { justify-content: center; }
    .attendance-stat-grid { grid-template-columns: 1fr !important; }
}

.member-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
}
.member-row:not(:first-child) { border-top: 1px solid #eee; }
.member-row p.name { margin: 0; font-size: 14px; }
.member-row p.id { margin: 0; font-size: 12px; color: #888; }

.status-toggle-btn {
    font-size: 12px;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    background: #f0f0f0;
    color: #666;
}
.status-toggle-btn.active-present { background: #d4edda; color: #155724; }
.status-toggle-btn.active-absent { background: #f8d7da; color: #721c24; }

/* ============================================================
   ATTENDANCE SUMMARY - professional layout
   ============================================================ */
.attendance-summary-toolbar {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin: 32px 0 16px;
}
.attendance-summary-toolbar h2 { margin: 0; }

#attSummarySearchWrap { position: relative; width: 240px; max-width: 240px; }
#attSummarySearchWrap input {
    width: 100%;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}
#attendanceSuggestions {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    max-height: 240px;
    overflow-y: auto;
    z-index: 20;
}
.att-sug-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}
.att-sug-item:hover { background: #f5f5f5; }
.att-sug-item .avatar {
    width: 28px;
    height: 28px;
    min-width: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 11px;
    flex-shrink: 0;
}
.att-sug-item p.name { margin: 0; font-weight: 600; font-size: 13px; }
.att-sug-item p.id { margin: 0; font-size: 11px; color: #888; }

.attendance-summary-card {
    background: #fff;
    border: 1px solid #eceae4;
    border-radius: 12px;
    padding: 20px;
    max-width: 660px;
}
.attendance-summary-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eceae4;
}
.attendance-summary-header .member-identity { display: flex; align-items: center; gap: 12px; }
.attendance-summary-header .member-identity .avatar {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}
.attendance-summary-header .member-identity p.name { margin: 0; font-weight: 600; font-size: 15px; color: #1b3a24; }
.attendance-summary-header .member-identity p.id { margin: 0; font-size: 12px; color: #888; }
.attendance-summary-header .rate-block { text-align: right; }
.attendance-summary-header .rate-label {
    margin: 0 0 2px;
    font-size: 13px;
    color: #667066;
}
.attendance-summary-header .rate-value {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #1b3a24;
}

.attendance-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.attendance-stat-card {
    text-align: left;
    border-radius: 8px;
    padding: 16px 18px;
    background: #f5f5f5;
    border: none;
    border-left: 4px solid transparent;
    cursor: default;
}
.attendance-stat-card.clickable { cursor: pointer; }
.attendance-stat-card p.label { font-size: 13px; color: #667066; margin: 0 0 4px; }
.attendance-stat-card p.count { font-size: 22px; font-weight: 700; margin: 0; color: #1b3a24; }
.attendance-stat-card.present-card { border-left-color: #ccc; }
.attendance-stat-card.present-card.active-present { border-left-color: #28a745; background: #eef8f0; }
.attendance-stat-card.present-card.active-present p.label { color: #155724; }
.attendance-stat-card.absent-card { border-left-color: #ccc; }
.attendance-stat-card.absent-card.active-absent { border-left-color: #dc3545; background: #fbeeee; }
.attendance-stat-card.absent-card.active-absent p.label { color: #721c24; }

.attendance-filter-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    max-width: 620px;
}
.attendance-filter-row p.title { margin: 0; font-size: 14px; font-weight: 600; color: #1b3a24; }
.attendance-filter-row .filter-btns { display: flex; gap: 4px; }
.attendance-filter-btn {
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background: #fff;
    color: #555;
    cursor: pointer;
}
.attendance-filter-btn.active {
    background: #274b81;
    color: #fff;
    border-color: #274b81;
}

.attendance-table-wrap {
    max-width: 620px;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}
.attendance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.attendance-table th {
    text-align: left;
    padding: 10px 12px;
    background: #f7f8f5;
    color: #667066;
    font-weight: 600;
    font-size: 12px;
}
.attendance-table th.status-col, .attendance-table td.status-col { text-align: right; }
.attendance-table td {
    padding: 10px 12px;
    border-top: 1px solid #eee;
}
.attendance-status-pill {
    font-size: 11px;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 600;
}
.attendance-status-pill.present { background: #d4edda; color: #155724; }
.attendance-status-pill.absent { background: #f8d7da; color: #721c24; }
.attendance-table-empty { padding: 16px; color: #667066; margin: 0; font-size: 13px; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 16px; width: 100%;">
    <h1 style="margin: 0; flex: 1 1 auto; min-width: 0;">Meetings Record</h1>
    <button id="openModalBtn"
            style="background: #1976d2 !important; color: #fff !important; border: none !important; padding: 10px 18px !important; border-radius: 6px !important; cursor: pointer; font-size: 14px !important; display: inline-block !important; width: auto !important; max-width: 220px !important; flex: 0 0 auto !important; white-space: nowrap !important; position: static !important;">
        + Create meeting
    </button>
</div>

<!-- Stats overview -->
<div id="statsGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; max-width: 620px;">
    <div style="background: #3B6D11; border-radius: 10px; padding: 16px 18px;">
        <p style="margin: 0 0 6px; font-size: 13px; color: #EAF3DE;">Total meetings</p>
        <p style="margin: 0; font-size: 22px; font-weight: 700; color: #fff;"><?php echo $totalMeetingsCount; ?></p>
    </div>
    <div style="background: #185FA5; border-radius: 10px; padding: 16px 18px;">
        <p style="margin: 0 0 6px; font-size: 13px; color: #E6F1FB;">Upcoming</p>
        <p style="margin: 0; font-size: 22px; font-weight: 700; color: #fff;"><?php echo $upcomingMeetingsCount; ?></p>
    </div>
    <div style="background: #854F0B; border-radius: 10px; padding: 16px 18px;">
        <p style="margin: 0 0 6px; font-size: 13px; color: #FAEEDA;">This month</p>
        <p style="margin: 0; font-size: 22px; font-weight: 700; color: #fff;"><?php echo $thisMonthMeetingsCount; ?></p>
    </div>
</div>

<!-- Meetings table -->
<?php if (!empty($allMeetings)): ?>
<div class="meetings-table-wrap">
    <div class="meetings-table-scroll">
        <table class="meetings-table" id="meetingsTable">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allMeetings as $meeting): ?>
                    <tr class="meeting-row"
                        data-id="<?php echo $meeting['id']; ?>"
                        data-title="<?php echo htmlspecialchars($meeting['title']); ?>"
                        data-location="<?php echo htmlspecialchars($meeting['location'] ?? ''); ?>"
                        data-agenda="<?php echo htmlspecialchars($meeting['agenda'] ?? ''); ?>"
                        data-date="<?php echo htmlspecialchars($meeting['meeting_date']); ?>"
                        data-time="<?php echo htmlspecialchars($meeting['meeting_time']); ?>">
                        <td class="title-col"><?php echo htmlspecialchars($meeting['title']); ?></td>
                        <td class="meta-col"><?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?></td>
                        <td class="meta-col"><?php echo date('g:i A', strtotime($meeting['meeting_time'])); ?></td>
                        <td class="actions-col">
                            <button type="button" class="view-details-btn"><span class="material-icons">visibility</span>View</button>
                            <button type="button" class="take-attendance-btn"><span class="material-icons">assignment_turned_in</span>Attendance</button>
                            <button type="button" class="edit-meeting-btn"><span class="material-icons">edit</span>Edit</button>
                            <button type="button" class="delete-meeting-btn"><span class="material-icons">delete</span>Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<p id="emptyState" style="color: #666; margin-top: 4px;">
    <?php if (empty($allMeetings)): ?>
        No meetings recorded yet. Click "+ Create meeting" to add the first one.
    <?php else: ?>
        Click a meeting card above to view its attendance.
    <?php endif; ?>
</p>

<!-- MODAL - Take Attendance -->
<div id="attendanceModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 480px; max-height: 80vh; box-sizing: border-box; position: relative; display: flex; flex-direction: column;">
        <h2 style="margin: 0 0 4px 0; font-size: 18px;">Take attendance</h2>
        <button id="closeAttendanceModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <p id="attendanceLabel" style="font-size: 13px; color: #666; margin: 0 0 14px;"></p>

        <div id="memberListContainer" style="border: 1px solid #ddd; border-radius: 8px; overflow-y: auto; margin-bottom: 14px;"></div>

        <div id="attendanceErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px;"></div>
        <div id="attendanceSuccess" style="display: none; background: #d4edda; color: #155724; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px;">
            Attendance saved.
        </div>

        <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button type="button" id="cancelAttendanceBtn" style="padding: 10px 18px !important; border-radius: 6px; border: 1px solid #ccc; background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                Cancel
            </button>
            <button type="button" id="saveAttendanceBtn"
                    style="background: #1976d2 !important; color: #fff !important; border: none !important; padding: 10px 18px !important; border-radius: 6px !important; cursor: pointer; font-size: 14px !important; width: auto !important; flex: 0 0 auto !important;">
                Save attendance
            </button>
        </div>
    </div>
</div>

<!-- MODAL - View Meeting Details -->
<div id="viewDetailsModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 14px; width: 100%; max-width: 400px; max-height: 80vh; overflow-y: auto; box-sizing: border-box; position: relative;">
        <button id="closeViewDetailsModalBtn"
                style="position: absolute !important; top: 14px !important; right: 14px !important; background: rgba(255,255,255,0.2) !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #fff !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 50% !important;">&times;</button>

        <div style="background: #2e7d32; padding: 28px 28px 20px;">
            <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: .06em; color: rgba(255,255,255,0.75); text-transform: uppercase;">Meeting details</p>
            <h2 id="viewDetailsTitle" style="margin: 0; font-size: 21px; font-weight: 700; color: #fff;"></h2>
        </div>

        <div style="padding: 24px 28px 28px;">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                <div style="background: #f7f8f5; border-radius: 10px; padding: 12px;">
                    <p style="margin: 0 0 3px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Date</p>
                    <p id="viewDetailsDate" style="margin: 0; font-size: 15px; font-weight: 600; color: #000;"></p>
                </div>
                <div style="background: #f7f8f5; border-radius: 10px; padding: 12px;">
                    <p style="margin: 0 0 3px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Time</p>
                    <p id="viewDetailsTime" style="margin: 0; font-size: 15px; font-weight: 600; color: #000;"></p>
                </div>
            </div>

            <div style="background: #f7f8f5; border-radius: 10px; padding: 12px; margin-bottom: 18px;">
                <p style="margin: 0 0 3px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Location</p>
                <p id="viewDetailsLocation" style="margin: 0; font-size: 15px; font-weight: 600; color: #000;"></p>
            </div>

            <div>
                <p style="margin: 0 0 8px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Agenda</p>
                <p id="viewDetailsAgenda" style="margin: 0; font-size: 14px; color: #000; line-height: 1.65; white-space: pre-line;"></p>
            </div>

        </div>
    </div>
</div>

<!-- ============================================================
     ATTENDANCE SUMMARY PER MEMBER (search bar, live suggestions)
     ============================================================ -->
<div class="attendance-summary-toolbar">
    <h2>Member Attendance Summary</h2>
    <div id="attSummarySearchWrap">
        <input type="text" id="attendanceMemberSearch" placeholder="Search member name or ID" autocomplete="off">
        <div id="attendanceSuggestions"></div>
    </div>
</div>

<script type="application/json" id="attMembersJson"><?php
    echo json_encode(array_map(function($m) {
        return [
            'id' => $m['id'],
            'name' => $m['last_name'] . ', ' . $m['first_name'],
            'membership_id' => $m['membership_id'],
        ];
    }, $members));
?></script>

<div id="attendanceSummaryContent" style="display: none;">

    <div class="attendance-summary-header">
        <div class="member-identity">
            <div class="avatar" id="attSummaryAvatar">-</div>
            <div>
                <p class="name" id="attSummaryMemberName">-</p>
                <p class="id" id="attSummaryMembershipId">-</p>
            </div>
        </div>
        <div class="rate-block">
            <p class="rate-label">Attendance rate</p>
            <p class="rate-value" id="attendanceRateText">-</p>
        </div>
    </div>

    <div class="attendance-stat-grid">
        <div class="attendance-stat-card">
            <p class="label">Total meetings</p>
            <p class="count" id="totalCountText">-</p>
        </div>
        <button type="button" id="presentSummaryCard" class="attendance-stat-card clickable present-card active-present">
            <p class="label">&#10003; Present</p>
            <p class="count" id="presentCountText">-</p>
        </button>
        <button type="button" id="absentSummaryCard" class="attendance-stat-card clickable absent-card">
            <p class="label">&#10007; Absent</p>
            <p class="count" id="absentCountText">-</p>
        </button>
    </div>

    <div class="attendance-filter-row">
        <p class="title">Meeting history</p>
        <div class="filter-btns">
            <button type="button" class="attendance-filter-btn" data-filter="all">All</button>
            <button type="button" class="attendance-filter-btn active" data-filter="present">Present</button>
            <button type="button" class="attendance-filter-btn" data-filter="absent">Absent</button>
        </div>
    </div>

    <div class="attendance-table-wrap">
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Meeting</th>
                    <th>Date</th>
                    <th class="status-col">Status</th>
                </tr>
            </thead>
            <tbody id="summaryMeetingsList"></tbody>
        </table>
    </div>
</div>

<p id="attendanceSummaryEmptyState" style="color: #667066;">Select a member above to view their attendance summary.</p>

<!-- MODAL - Create Meeting -->
<div id="createModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; max-height: 85vh; overflow-y: auto; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Create meeting</h2>
        <button id="closeModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="modalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px;"></div>

        <form id="meetingForm">
            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Meeting title</label>
                <input type="text" name="title" required placeholder="e.g. September 2026 General Assembly"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Date</label>
                    <input type="date" name="meeting_date" required value="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Time</label>
                    <input type="time" name="meeting_time" required value="14:00"
                           style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Location</label>
                <input type="text" name="location" required placeholder="e.g. Coop main hall"
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Agenda</label>
                <textarea name="agenda" required placeholder="e.g. Approval of coop budget, election of new board members"
                          style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; min-height: 70px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" id="cancelBtn" style="padding: 10px 18px !important; border-radius: 6px; border: 1px solid #ccc; background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" style="padding: 10px 18px !important; border-radius: 6px; border: none; background: #1976d2 !important; color: #fff !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                    Create
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL - Edit Meeting -->
<div id="editModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; max-height: 85vh; overflow-y: auto; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Edit meeting</h2>
        <button id="closeEditModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="editModalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px;"></div>

        <form id="editMeetingForm">
            <input type="hidden" name="id" id="editMeetingId">

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Meeting title</label>
                <input type="text" name="title" id="editTitle" required
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Date</label>
                    <input type="date" name="meeting_date" id="editDate" required
                           style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Time</label>
                    <input type="time" name="meeting_time" id="editTime" required
                           style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Location</label>
                <input type="text" name="location" id="editLocation" required
                       style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Agenda</label>
                <textarea name="agenda" id="editAgenda" required
                          style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; min-height: 70px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" id="cancelEditBtn" style="padding: 10px 18px !important; border-radius: 6px; border: 1px solid #ccc; background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                    Cancel
                </button>
                <button type="submit" id="editSubmitBtn" style="padding: 10px 18px !important; border-radius: 6px; border: none; background: #274b81 !important; color: #fff !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL - Delete Meeting Confirmation -->
<div id="deleteModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 380px; box-sizing: border-box; text-align: center;">
        <p style="margin: 0 0 6px; font-size: 16px; font-weight: 600; color: #1b3a24;">Delete this meeting?</p>
        <p id="deleteMeetingLabel" style="margin: 0 0 18px; font-size: 13px; color: #667066;"></p>

        <div id="deleteModalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>

        <div style="display: flex; gap: 8px; justify-content: center;">
            <button type="button" id="cancelDeleteBtn" style="padding: 10px 18px !important; border-radius: 6px; border: 1px solid #ccc; background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                Cancel
            </button>
            <button type="button" id="confirmDeleteBtn" style="padding: 10px 18px !important; border-radius: 6px; border: none; background: #a6322f !important; color: #fff !important; cursor: pointer; font-size: 14px; width: auto !important; flex: 0 0 auto !important;">
                Delete
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const openBtn = document.getElementById('openModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const backdrop = document.getElementById('createModalBackdrop');
    const form = document.getElementById('meetingForm');
    const submitBtn = document.getElementById('submitBtn');
    const modalErrors = document.getElementById('modalErrors');

    const meetingsTable = document.getElementById('meetingsTable');
    const attendanceModalBackdrop = document.getElementById('attendanceModalBackdrop');
    const closeAttendanceModalBtn = document.getElementById('closeAttendanceModalBtn');
    const cancelAttendanceBtn = document.getElementById('cancelAttendanceBtn');
    const attendanceLabel = document.getElementById('attendanceLabel');
    const memberListContainer = document.getElementById('memberListContainer');
    const emptyState = document.getElementById('emptyState');
    const saveAttendanceBtn = document.getElementById('saveAttendanceBtn');
    const attendanceErrors = document.getElementById('attendanceErrors');
    const attendanceSuccess = document.getElementById('attendanceSuccess');

    // Edit meeting modal
    const editModalBackdrop = document.getElementById('editModalBackdrop');
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const editMeetingForm = document.getElementById('editMeetingForm');
    const editSubmitBtn = document.getElementById('editSubmitBtn');
    const editModalErrors = document.getElementById('editModalErrors');
    const editMeetingId = document.getElementById('editMeetingId');
    const editTitle = document.getElementById('editTitle');
    const editDate = document.getElementById('editDate');
    const editTime = document.getElementById('editTime');
    const editLocation = document.getElementById('editLocation');
    const editAgenda = document.getElementById('editAgenda');

    // Delete meeting modal
    const deleteModalBackdrop = document.getElementById('deleteModalBackdrop');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteMeetingLabel = document.getElementById('deleteMeetingLabel');
    const deleteModalErrors = document.getElementById('deleteModalErrors');
    let meetingIdPendingDelete = null;

    let currentMeetingId = null;

    function openAttendanceModal() { attendanceModalBackdrop.style.display = 'flex'; }
    function closeAttendanceModal() {
        attendanceModalBackdrop.style.display = 'none';
        currentMeetingId = null;
        document.querySelectorAll('.meeting-row').forEach(c => c.classList.remove('selected'));
        emptyState.style.display = 'block';
    }

    closeAttendanceModalBtn.addEventListener('click', closeAttendanceModal);
    cancelAttendanceBtn.addEventListener('click', closeAttendanceModal);
    attendanceModalBackdrop.addEventListener('click', function(e) {
        if (e.target === attendanceModalBackdrop) closeAttendanceModal();
    });

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        form.reset();
        modalErrors.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function formatTime(timeStr) {
        const [h, m] = timeStr.split(':');
        const d = new Date();
        d.setHours(parseInt(h), parseInt(m));
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    // ============================================================
    // CREATE MEETING (modal submit)
    // ============================================================
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        modalErrors.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const formData = new FormData(form);
            const response = await fetch('<?php echo BASE_URL; ?>/app/meetings/api/save-meeting.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                modalErrors.textContent = 'Server error. Check the console (F12).';
                modalErrors.style.display = 'block';
                return;
            }

            if (data.success) {
                window.location.reload();
            } else {
                modalErrors.innerHTML = (data.errors || ['Something went wrong, please try again.']).join('<br>');
                modalErrors.style.display = 'block';
            }
        } catch (err) {
            console.error('Fetch failed:', err);
            modalErrors.textContent = 'Could not connect to the server: ' + err.message;
            modalErrors.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create';
        }
    });

    // ============================================================
    // Toggle a member row between present / absent (checklist style)
    // ============================================================
    function buildEditableRow(member) {
        const isPresent = !!member.is_present;
        return `
            <div class="member-row" data-member-id="${member.id}" data-present="${isPresent ? '1' : '0'}">
                <div>
                    <p class="name">${member.last_name}, ${member.first_name}</p>
                    <p class="id">${member.membership_id}</p>
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="status-toggle-btn present-btn ${isPresent ? 'active-present' : ''}">Present</button>
                    <button type="button" class="status-toggle-btn absent-btn ${!isPresent ? 'active-absent' : ''}">Absent</button>
                </div>
            </div>
        `;
    }

    memberListContainer.addEventListener('click', function(e) {
        const presentBtn = e.target.closest('.present-btn');
        const absentBtn = e.target.closest('.absent-btn');
        if (!presentBtn && !absentBtn) return;

        const row = e.target.closest('.member-row');
        if (!row) return;

        const nowPresent = !!presentBtn;
        row.dataset.present = nowPresent ? '1' : '0';

        const pBtn = row.querySelector('.present-btn');
        const aBtn = row.querySelector('.absent-btn');
        pBtn.classList.toggle('active-present', nowPresent);
        aBtn.classList.toggle('active-absent', !nowPresent);
    });

    // ============================================================
    // LOAD ATTENDANCE FOR A MEETING
    // ============================================================
    async function loadAttendance(meetingId, title, date, time) {
        currentMeetingId = meetingId;

        attendanceSuccess.style.display = 'none';
        attendanceErrors.style.display = 'none';
        emptyState.style.display = 'none';

        memberListContainer.innerHTML = '<p style="padding: 16px; color: #666; margin: 0;">Loading...</p>';
        openAttendanceModal();
        saveAttendanceBtn.style.display = 'inline-block';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>/app/meetings/api/get-attendance.php?meeting_id=' + meetingId);
            const data = await response.json();

            if (!data.success) {
                memberListContainer.innerHTML = '<p style="padding: 16px; color: #721c24;">Error loading members.</p>';
                saveAttendanceBtn.style.display = 'none';
                return;
            }
            if (data.members.length === 0) {
                memberListContainer.innerHTML = '<p style="padding: 16px; color: #666; margin: 0;">No members found.</p>';
                saveAttendanceBtn.style.display = 'none';
                return;
            }

            const isFinalized = data.finalized;

            if (isFinalized) {
                attendanceLabel.innerHTML = 'Attendance for ' + title + ' (' + formatDate(date) + ', ' + formatTime(time) + ') <span style="background: #e0e0e0; color: #555; padding: 2px 10px; border-radius: 4px; font-size: 12px; margin-left: 6px;">Saved &bull; view only</span>';
                saveAttendanceBtn.style.display = 'none';
            } else {
                attendanceLabel.textContent = 'Attendance for ' + title + ' (' + formatDate(date) + ', ' + formatTime(time) + ') - mark each member as present or absent';
                saveAttendanceBtn.style.display = 'inline-block';
            }

            memberListContainer.innerHTML = data.members.map(member => {
                if (isFinalized) {
                    const icon = member.is_present
                        ? '<span style="color: #28a745; font-weight: 600;">&#10003; Present</span>'
                        : '<span style="color: #999;">&#10007; Absent</span>';
                    return `
                        <div class="member-row">
                            <div>
                                <p class="name">${member.last_name}, ${member.first_name}</p>
                                <p class="id">${member.membership_id}</p>
                            </div>
                            <div style="font-size: 13px;">${icon}</div>
                        </div>
                    `;
                }
                return buildEditableRow(member);
            }).join('');
        } catch (err) {
            console.error('Failed to load attendance:', err);
            memberListContainer.innerHTML = '<p style="padding: 16px; color: #721c24;">Could not load members.</p>';
            saveAttendanceBtn.style.display = 'none';
        }
    }

    // View Details modal
    const viewDetailsModalBackdrop = document.getElementById('viewDetailsModalBackdrop');
    const closeViewDetailsModalBtn = document.getElementById('closeViewDetailsModalBtn');
    const viewDetailsTitle = document.getElementById('viewDetailsTitle');
    const viewDetailsDate = document.getElementById('viewDetailsDate');
    const viewDetailsTime = document.getElementById('viewDetailsTime');
    const viewDetailsLocation = document.getElementById('viewDetailsLocation');
    const viewDetailsAgenda = document.getElementById('viewDetailsAgenda');

    function closeViewDetailsModal() { viewDetailsModalBackdrop.style.display = 'none'; }
    closeViewDetailsModalBtn.addEventListener('click', closeViewDetailsModal);
    viewDetailsModalBackdrop.addEventListener('click', function(e) {
        if (e.target === viewDetailsModalBackdrop) closeViewDetailsModal();
    });

    // View / Attendance / Edit / Delete buttons on each row
    if (meetingsTable) {
        meetingsTable.addEventListener('click', function(e) {
            const row = e.target.closest('.meeting-row');
            if (!row) return;

            if (e.target.closest('.view-details-btn')) {
                viewDetailsTitle.textContent = row.dataset.title;
                viewDetailsDate.textContent = formatDate(row.dataset.date);
                viewDetailsTime.textContent = formatTime(row.dataset.time);
                viewDetailsLocation.textContent = row.dataset.location || 'No location set';
                viewDetailsAgenda.textContent = row.dataset.agenda || 'No agenda provided.';
                viewDetailsModalBackdrop.style.display = 'flex';
                return;
            }

            if (e.target.closest('.take-attendance-btn')) {
                document.querySelectorAll('.meeting-row').forEach(c => c.classList.remove('selected'));
                row.classList.add('selected');
                loadAttendance(row.dataset.id, row.dataset.title, row.dataset.date, row.dataset.time);
                return;
            }

            if (e.target.closest('.edit-meeting-btn')) {
                openEditModal(row);
                return;
            }

            if (e.target.closest('.delete-meeting-btn')) {
                meetingIdPendingDelete = row.dataset.id;
                deleteMeetingLabel.textContent = '"' + row.dataset.title + '" and its attendance records will be permanently removed.';
                deleteModalErrors.style.display = 'none';
                deleteModalBackdrop.style.display = 'flex';
                return;
            }
        });
    }

    // ============================================================
    // EDIT MEETING
    // ============================================================
    function openEditModal(row) {
        editModalErrors.style.display = 'none';
        editMeetingId.value = row.dataset.id;
        editTitle.value = row.dataset.title;
        editDate.value = row.dataset.date;
        editTime.value = row.dataset.time;
        editLocation.value = row.dataset.location;
        editAgenda.value = row.dataset.agenda;
        editModalBackdrop.style.display = 'flex';
    }

    function closeEditModal() {
        editModalBackdrop.style.display = 'none';
        editModalErrors.style.display = 'none';
    }

    closeEditModalBtn.addEventListener('click', closeEditModal);
    cancelEditBtn.addEventListener('click', closeEditModal);
    editModalBackdrop.addEventListener('click', function(e) { if (e.target === editModalBackdrop) closeEditModal(); });

    editMeetingForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        editModalErrors.style.display = 'none';
        editSubmitBtn.disabled = true;
        editSubmitBtn.textContent = 'Saving...';

        try {
            const formData = new FormData(editMeetingForm);
            const response = await fetch('<?php echo BASE_URL; ?>/app/meetings/api/update-meeting.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                editModalErrors.textContent = 'Server error. Check the console (F12).';
                editModalErrors.style.display = 'block';
                return;
            }

            if (data.success) {
                window.location.reload();
            } else {
                editModalErrors.innerHTML = (data.errors || ['Something went wrong, please try again.']).join('<br>');
                editModalErrors.style.display = 'block';
            }
        } catch (err) {
            console.error('Fetch failed:', err);
            editModalErrors.textContent = 'Could not connect to the server: ' + err.message;
            editModalErrors.style.display = 'block';
        } finally {
            editSubmitBtn.disabled = false;
            editSubmitBtn.textContent = 'Save changes';
        }
    });

    // ============================================================
    // DELETE MEETING
    // ============================================================
    function closeDeleteModal() {
        deleteModalBackdrop.style.display = 'none';
        meetingIdPendingDelete = null;
    }

    cancelDeleteBtn.addEventListener('click', closeDeleteModal);
    deleteModalBackdrop.addEventListener('click', function(e) { if (e.target === deleteModalBackdrop) closeDeleteModal(); });

    confirmDeleteBtn.addEventListener('click', async function() {
        if (!meetingIdPendingDelete) return;

        deleteModalErrors.style.display = 'none';
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.textContent = 'Deleting...';

        try {
            const formData = new FormData();
            formData.append('id', meetingIdPendingDelete);

            const response = await fetch('<?php echo BASE_URL; ?>/app/meetings/api/delete-meeting.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                deleteModalErrors.textContent = 'Server error. Check the console (F12).';
                deleteModalErrors.style.display = 'block';
                return;
            }

            if (data.success) {
                window.location.reload();
            } else {
                deleteModalErrors.innerHTML = (data.errors || ['Something went wrong, please try again.']).join('<br>');
                deleteModalErrors.style.display = 'block';
            }
        } catch (err) {
            console.error('Fetch failed:', err);
            deleteModalErrors.textContent = 'Could not connect to the server: ' + err.message;
            deleteModalErrors.style.display = 'block';
        } finally {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.textContent = 'Delete';
        }
    });

    // ============================================================
    // SAVE ATTENDANCE
    // ============================================================
    saveAttendanceBtn.addEventListener('click', async function() {
        if (!currentMeetingId) return;

        const rows = document.querySelectorAll('.member-row[data-member-id]');
        const attendedIds = Array.from(rows).filter(r => r.dataset.present === '1').map(r => r.dataset.memberId);

        attendanceErrors.style.display = 'none';
        attendanceSuccess.style.display = 'none';
        saveAttendanceBtn.disabled = true;
        saveAttendanceBtn.textContent = 'Saving...';

        try {
            const formData = new FormData();
            formData.append('meeting_id', currentMeetingId);
            attendedIds.forEach(id => formData.append('attended[]', id));

            const response = await fetch('<?php echo BASE_URL; ?>/app/meetings/api/save-attendance.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                attendanceSuccess.style.display = 'block';
                setTimeout(closeAttendanceModal, 900);
            } else {
                attendanceErrors.innerHTML = (data.errors || ['Something went wrong, please try again.']).join('<br>');
                attendanceErrors.style.display = 'block';
            }
        } catch (err) {
            attendanceErrors.textContent = 'Could not connect to the server.';
            attendanceErrors.style.display = 'block';
        } finally {
            saveAttendanceBtn.disabled = false;
            saveAttendanceBtn.textContent = 'Save attendance';
        }
    });

    // ============================================================
    // ATTENDANCE SUMMARY PER MEMBER
    // Search bar with live suggestions replaces the old <select>.
    // Typing filters matches by name or membership ID; picking one
    // (or auto-selecting when only one match remains) loads the
    // summary card immediately, same pattern as the Payments page.
    // ============================================================
    const allMeetingsData = <?php echo json_encode(array_map(function ($m) {
        return [
            'id' => $m['id'],
            'title' => $m['title'],
            'meeting_date' => $m['meeting_date'],
        ];
    }, $allMeetings)); ?>;

    const attMembers = JSON.parse(document.getElementById('attMembersJson').textContent);
    const attSearchInput = document.getElementById('attendanceMemberSearch');
    const attSuggestions = document.getElementById('attendanceSuggestions');
    const attSummaryContent = document.getElementById('attendanceSummaryContent');
    const attSummaryEmptyState = document.getElementById('attendanceSummaryEmptyState');
    const attSummaryAvatar = document.getElementById('attSummaryAvatar');
    const attSummaryMemberName = document.getElementById('attSummaryMemberName');
    const attSummaryMembershipId = document.getElementById('attSummaryMembershipId');
    const presentSummaryCard = document.getElementById('presentSummaryCard');
    const absentSummaryCard = document.getElementById('absentSummaryCard');
    const presentCountText = document.getElementById('presentCountText');
    const absentCountText = document.getElementById('absentCountText');
    const totalCountText = document.getElementById('totalCountText');
    const attendanceRateText = document.getElementById('attendanceRateText');
    const summaryMeetingsList = document.getElementById('summaryMeetingsList');
    const filterBtns = document.querySelectorAll('.attendance-filter-btn');

    let presentMeetingsCache = [];
    let absentMeetingsCache = [];
    let currentFilter = 'present';

    // Same palette + hash used on the Payments page, so a given member
    // shows up in the same color across both pages.
    const AVATAR_PALETTE = [
        { bg: '#534AB7', text: '#F4F3FE' }, // purple
        { bg: '#0F6E56', text: '#E1F5EE' }, // teal
        { bg: '#993C1D', text: '#FAECE7' }, // coral
        { bg: '#993556', text: '#FBEAF0' }, // pink
        { bg: '#185FA5', text: '#E6F1FB' }, // blue
        { bg: '#3B6D11', text: '#EAF3DE' }, // green
        { bg: '#854F0B', text: '#FAEEDA' }, // amber
        { bg: '#791F1F', text: '#FCEBEB' }  // red
    ];

    function simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash = hash | 0;
        }
        return Math.abs(hash);
    }

    function avatarColorFor(seed) {
        return AVATAR_PALETTE[simpleHash(seed) % AVATAR_PALETTE.length];
    }

    function initialsFor(name) {
        const parts = name.split(',').map(s => s.trim()).filter(Boolean);
        return parts.map(p => p.charAt(0).toUpperCase()).slice(0, 2).join('');
    }

    function renderSummaryTable(filter) {
        let rows = [];
        if (filter === 'present') {
            rows = presentMeetingsCache.map(m => ({ ...m, status: 'present' }));
        } else if (filter === 'absent') {
            rows = absentMeetingsCache.map(m => ({ ...m, status: 'absent' }));
        } else {
            rows = presentMeetingsCache.map(m => ({ ...m, status: 'present' }))
                .concat(absentMeetingsCache.map(m => ({ ...m, status: 'absent' })))
                .sort((a, b) => new Date(b.meeting_date) - new Date(a.meeting_date));
        }

        if (rows.length === 0) {
            summaryMeetingsList.innerHTML = '<tr><td colspan="3" class="attendance-table-empty">No meetings to show.</td></tr>';
            return;
        }

        summaryMeetingsList.innerHTML = rows.map(m => `
            <tr>
                <td>${m.title}</td>
                <td>${formatDate(m.meeting_date)}</td>
                <td class="status-col">
                    <span class="attendance-status-pill ${m.status}">${m.status === 'present' ? 'Present' : 'Absent'}</span>
                </td>
            </tr>
        `).join('');
    }

    function setActiveFilter(filter) {
        currentFilter = filter;
        filterBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.filter === filter));
        presentSummaryCard.classList.toggle('active-present', filter === 'present');
        absentSummaryCard.classList.toggle('active-absent', filter === 'absent');
        renderSummaryTable(filter);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => setActiveFilter(btn.dataset.filter));
    });
    presentSummaryCard.addEventListener('click', () => setActiveFilter('present'));
    absentSummaryCard.addEventListener('click', () => setActiveFilter('absent'));

    async function loadMemberSummary(memberId, memberName, membershipId) {
        attSuggestions.style.display = 'none';
        attSummaryEmptyState.style.display = 'none';
        attSummaryContent.style.display = 'block';

        const av = avatarColorFor(membershipId || memberName);
        attSummaryAvatar.style.background = av.bg;
        attSummaryAvatar.style.color = av.text;
        attSummaryAvatar.textContent = initialsFor(memberName);
        attSummaryMemberName.textContent = memberName;
        attSummaryMembershipId.textContent = membershipId || '';

        presentCountText.textContent = '...';
        absentCountText.textContent = '...';
        totalCountText.textContent = '...';
        attendanceRateText.textContent = '...';
        summaryMeetingsList.innerHTML = '<tr><td colspan="3" class="attendance-table-empty">Loading...</td></tr>';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>/app/meetings/api/get-member-attendance.php?member_id=' + memberId);
            const data = await response.json();

            if (!data.success) {
                presentCountText.textContent = 'Error';
                absentCountText.textContent = 'Error';
                totalCountText.textContent = 'Error';
                attendanceRateText.textContent = '-';
                summaryMeetingsList.innerHTML = '<tr><td colspan="3" class="attendance-table-empty">Error loading data.</td></tr>';
                return;
            }

            presentMeetingsCache = data.meetings;
            const attendedIds = new Set(data.meetings.map(m => String(m.id)));
            absentMeetingsCache = allMeetingsData.filter(m => !attendedIds.has(String(m.id)));

            presentCountText.textContent = data.attended_count;
            absentCountText.textContent = absentMeetingsCache.length;
            totalCountText.textContent = data.total_meetings;

            const rate = data.total_meetings > 0
                ? Math.round((data.attended_count / data.total_meetings) * 100)
                : 0;
            attendanceRateText.textContent = rate + '%';

            setActiveFilter('present');
        } catch (err) {
            console.error('Failed to load attendance summary:', err);
            presentCountText.textContent = 'Error';
            absentCountText.textContent = 'Error';
            totalCountText.textContent = 'Error';
            attendanceRateText.textContent = '-';
            summaryMeetingsList.innerHTML = '<tr><td colspan="3" class="attendance-table-empty">Could not load the data.</td></tr>';
        }
    }

    function renderSuggestions(matches) {
        if (matches.length === 0) {
            attSuggestions.innerHTML = '<div style="padding: 10px 12px; font-size: 13px; color: #888;">No matching member.</div>';
            attSuggestions.style.display = 'block';
            return;
        }

        attSuggestions.innerHTML = matches.map(m => {
            const av = avatarColorFor(m.membership_id);
            return `
                <div class="att-sug-item" data-id="${m.id}" data-name="${m.name.replace(/"/g, '&quot;')}" data-membership-id="${m.membership_id}">
                    <div class="avatar" style="background:${av.bg};color:${av.text};">${initialsFor(m.name)}</div>
                    <div>
                        <p class="name">${m.name}</p>
                        <p class="id">${m.membership_id}</p>
                    </div>
                </div>
            `;
        }).join('');
        attSuggestions.style.display = 'block';

        attSuggestions.querySelectorAll('.att-sug-item').forEach(function(item) {
            item.addEventListener('click', function() {
                attSearchInput.value = this.dataset.name;
                loadMemberSummary(this.dataset.id, this.dataset.name, this.dataset.membershipId);
            });
        });
    }

    attSearchInput.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        if (!q) {
            attSuggestions.style.display = 'none';
            attSummaryContent.style.display = 'none';
            attSummaryEmptyState.style.display = 'block';
            return;
        }

        const matches = attMembers.filter(m =>
            m.name.toLowerCase().includes(q) || m.membership_id.toLowerCase().includes(q)
        );

        // Auto-load when exactly one match remains, same as the
        // Payments page member search.
        if (matches.length === 1) {
            attSuggestions.style.display = 'none';
            loadMemberSummary(matches[0].id, matches[0].name, matches[0].membership_id);
            return;
        }

        renderSuggestions(matches);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#attSummarySearchWrap')) {
            attSuggestions.style.display = 'none';
        }
    });
})();
</script>

<?php renderFooter(); ?>