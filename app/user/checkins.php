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
    .account-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 4px;
    }

    .checkins-wrap {
        width: 100%;
        max-width: 480px;
        margin: 20px auto 0;
    }

    /* ============================================================
       3-CARD SUMMARY: Total meetings / Checked in / Not checked in
       Flat solid colors, same palette as the payments stat cards.
       ============================================================ */
    .checkins-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 22px;
    }

    .checkins-stat-card {
        border-radius: 12px;
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
        font-weight: 600;
        line-height: 1.1;
        color: #fff;
    }

    .checkins-stat-label {
        margin: 0;
        font-size: 11px;
        line-height: 1.3;
    }

    .checkins-stat-card.total { background: #185FA5; }
    .checkins-stat-card.total .checkins-stat-label { color: #E6F1FB; }

    .checkins-stat-card.checked-in { background: #3B6D11; }
    .checkins-stat-card.checked-in .checkins-stat-label { color: #EAF3DE; }

    .checkins-stat-card.not-checked-in { background: #A32D2D; }
    .checkins-stat-card.not-checked-in .checkins-stat-label { color: #FCEBEB; }

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
        background: #3B6D11;
        border-radius: 8px;
        padding: 6px 4px;
    }

    .meeting-preview-date .month {
        font-size: 10px;
        font-weight: 700;
        color: #EAF3DE;
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
        background: #3B6D11;
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
</style>

<div class="account-header">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
</div>

<div class="checkins-wrap">

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
     MODAL - Meeting Details (same pattern as dashboard.php)
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
                <p style="margin: 0 0 8px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: #98a498; text-transform: uppercase;">Description</p>
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
            mdAgenda.textContent = card.dataset.agenda || 'No description provided.';
            backdrop.style.display = 'flex';
        });
    });
})();
</script>

<?php renderFooter(); ?>