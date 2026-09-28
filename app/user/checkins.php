<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
requireRole('user');

$currentUserId = $_SESSION['user_id'];
$pageTitle = 'Meetings History';

// ============================================================
// GET LINKED MEMBER ID
// ============================================================

$stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$linkedMemberId = $stmt->fetch(PDO::FETCH_ASSOC)['member_id'] ?? null;

// ============================================================
// TOTAL MEETINGS (all meetings ever created, for the "Total" card)
// ============================================================

$totalMeetings = (int) $pdo->query("SELECT COUNT(*) FROM meetings")->fetchColumn();

// ============================================================
// GET CHECK-IN HISTORY FOR THIS MEMBER
// NOTE: uses the "attendance" table - this is the same table
// app/manager/meetings/ajax/checkin.php writes to. An earlier
// version of this page queried "meeting_attendance" instead,
// a different table that check-ins never actually get saved to.
// ============================================================

$checkins = [];
if ($linkedMemberId) {
    $stmt = $pdo->prepare("
        SELECT
            me.id,
            me.title,
            me.meeting_date,
            me.`time`,
            me.location,
            me.description
        FROM attendance a
        JOIN meetings me ON me.id = a.meeting_id
        WHERE a.member_id = ?
        ORDER BY me.meeting_date DESC, me.`time` DESC
    ");
    $stmt->execute([$linkedMemberId]);
    $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$checkedInCount = count($checkins);
$notCheckedInCount = max(0, $totalMeetings - $checkedInCount);

renderHeader($pageTitle);
?>

<style>
    .page-shell {
        width: 100%;
        max-width: 480px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .page-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 22px;
    }

    .page-header-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #eae6fb;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .page-header-icon svg {
        width: 22px;
        height: 22px;
        stroke: #4b2f9c;
    }

    .page-title {
        margin: 0;
        color: #1b3a24;
        font-size: 19px;
        font-weight: 600;
    }

    .page-sub {
        margin: 2px 0 0 0;
        color: #667066;
        font-size: 13.5px;
    }

    /* ============================================================
       3-CARD SUMMARY: Total meetings / Checked in / Not checked in
       Flat solid colors, same palette used across every user page.
       ============================================================ */
    .checkins-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 22px;
    }

    .checkins-stat-card {
        border-radius: 14px;
        padding: 16px 8px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
    }

    .checkins-stat-count {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.1;
        color: #fff;
    }

    .checkins-stat-label {
        margin: 0;
        font-size: 11px;
        line-height: 1.3;
    }

    .checkins-stat-card.total { background: #274b81; }
    .checkins-stat-card.total .checkins-stat-label { color: #dce8f7; }

    .checkins-stat-card.checked-in { background: #2e7d32; }
    .checkins-stat-card.checked-in .checkins-stat-label { color: #d6ecd8; }

    .checkins-stat-card.not-checked-in { background: #a6322f; }
    .checkins-stat-card.not-checked-in .checkins-stat-label { color: #f5dcdb; }

    @media (max-width: 420px) {
        .checkins-stat-grid { grid-template-columns: 1fr; }
        .checkins-stat-card { flex-direction: row; text-align: left; justify-content: flex-start; gap: 10px; }
    }

    .meeting-preview-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
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
        background: #2e7d32;
        border-radius: 8px;
        padding: 6px 4px;
    }

    .meeting-preview-date .month {
        font-size: 10px;
        font-weight: 700;
        color: #d6ecd8;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .meeting-preview-date .day {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
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
        margin: 2px 0 0;
        color: #667066;
        font-size: 12px;
    }

    .checkin-badge {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: #2e7d32;
        padding: 4px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .meeting-preview-empty {
        padding: 20px;
        border-radius: 14px;
        border: 1px dashed #eceae4;
        text-align: center;
        color: #667066;
        font-size: 13px;
    }

    /* ============================================================
       MEETING DETAILS MODAL (compact + narrow)
       ============================================================ */
    .md-backdrop {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 100;
        align-items: center;
        justify-content: center;
        padding: 16px;
        box-sizing: border-box;
    }

    .md-modal {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 78%;
        max-width: 280px;
        max-height: 80vh;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-sizing: border-box;
    }

    .md-header {
        flex-shrink: 0;
        background: #2e7d32;
        padding: 12px 40px 10px 14px;
    }

    .md-eyebrow {
        margin: 0 0 2px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .06em;
        color: rgba(255, 255, 255, 0.8);
        text-transform: uppercase;
        line-height: 1.4;
    }

    .md-title {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        line-height: 1.3;
        word-break: break-word;
    }

    button.md-close {
        position: absolute !important;
        top: 8px !important;
        right: 8px !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 22px !important;
        height: 22px !important;
        max-width: 22px !important;
        flex: 0 0 auto !important;
        padding: 0 !important;
        background: rgba(255, 255, 255, 0.22) !important;
        border: none !important;
        border-radius: 50% !important;
        color: #fff !important;
        font-size: 16px !important;
        line-height: 1 !important;
        cursor: pointer;
        box-shadow: none !important;
    }

    .md-body {
        padding: 10px 14px 14px;
        overflow-y: auto;
    }

    .md-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        margin-bottom: 6px;
    }

    .md-box {
        background: #f7f8f5;
        border-radius: 8px;
        padding: 6px 8px;
        min-width: 0;
    }

    .md-label {
        margin: 0 0 1px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .04em;
        color: #7c877c;
        text-transform: uppercase;
        line-height: 1.4;
    }

    .md-value {
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: #1b1b1b;
        line-height: 1.4;
        word-break: break-word;
    }

    .md-desc-text {
        margin: 0;
        font-size: 12px;
        color: #1b1b1b;
        line-height: 1.5;
        white-space: pre-line;
        word-break: break-word;
    }
</style>

<div class="page-shell">

    <div class="page-header">
        <div class="page-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <p class="page-title"><?php echo htmlspecialchars($pageTitle); ?></p>
            <p class="page-sub">Your meeting attendance records</p>
        </div>
    </div>

    <div class="checkins-stat-grid">
        <div class="checkins-stat-card total">
            <p class="checkins-stat-count"><?php echo $totalMeetings; ?></p>
            <p class="checkins-stat-label">Total meetings</p>
        </div>

        <div class="checkins-stat-card checked-in">
            <p class="checkins-stat-count"><?php echo $checkedInCount; ?></p>
            <p class="checkins-stat-label">Checked in</p>
        </div>

        <div class="checkins-stat-card not-checked-in">
            <p class="checkins-stat-count"><?php echo $notCheckedInCount; ?></p>
            <p class="checkins-stat-label">Not checked in</p>
        </div>
    </div>

    <?php if (!$linkedMemberId): ?>

        <div class="meeting-preview-empty">Your account isn't linked to a member profile yet.</div>

    <?php elseif (empty($checkins)): ?>

        <div class="meeting-preview-empty">You haven't checked in to any meetings yet.</div>

    <?php else: ?>

        <div class="meeting-preview-list">
            <?php foreach ($checkins as $meeting): ?>
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
                            <?php
                            echo date('F j, Y', strtotime($meeting['meeting_date']));
                            if (!empty($meeting['time'])) {
                                echo ' &middot; ' . date('g:i A', strtotime($meeting['time']));
                            }
                            ?>
                        </p>
                    </div>
                    <span class="checkin-badge">Checked in</span>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<!-- ============================================================
     MODAL - Meeting Details (compact + narrow)
============================================================ -->
<div id="meetingDetailsBackdrop" class="md-backdrop">
    <div class="md-modal">
        <button id="closeMeetingDetailsBtn" type="button" class="md-close" aria-label="Close">&times;</button>

        <div class="md-header">
            <p class="md-eyebrow">Meeting details</p>
            <h2 id="mdTitle" class="md-title"></h2>
        </div>

        <div class="md-body">
            <div class="md-grid">
                <div class="md-box">
                    <p class="md-label">Date</p>
                    <p id="mdDate" class="md-value"></p>
                </div>
                <div class="md-box">
                    <p class="md-label">Time</p>
                    <p id="mdTime" class="md-value"></p>
                </div>
            </div>

            <div id="mdLocationWrap" class="md-box" style="margin-bottom: 10px;">
                <p class="md-label">Location</p>
                <p id="mdLocation" class="md-value"></p>
            </div>

            <div id="mdDescWrap">
                <p class="md-label" style="margin-bottom: 3px;">Description</p>
                <p id="mdAgenda" class="md-desc-text"></p>
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

            // Hide Location / Description when empty
            if (card.dataset.location) {
                mdLocation.textContent = card.dataset.location;
                mdLocationWrap.style.display = 'block';
            } else {
                mdLocationWrap.style.display = 'none';
            }

            if (card.dataset.agenda) {
                mdAgenda.textContent = card.dataset.agenda;
                mdDescWrap.style.display = 'block';
            } else {
                mdDescWrap.style.display = 'none';
            }

            backdrop.style.display = 'flex';
        });
    });
})();
</script>

<?php renderFooter(); ?>