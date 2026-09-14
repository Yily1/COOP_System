<?php
/**
 * app/manager/meetings/meeting.php
 * Single-page meetings module, styled to match the rest of the app
 * (uses renderHeader/renderFooter from config/functions.php).
 *
 * - No ?id in URL  -> list view: search, create meeting, upcoming list, calendar
 * - ?id=<number>   -> detail view: meeting info + member search/check-in
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');

$viewingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================================
// DETAIL VIEW — a specific meeting was requested
// ============================================================
if ($viewingId) {
    $stmt = $pdo->prepare('SELECT * FROM meetings WHERE id = :id');
    $stmt->execute([':id' => $viewingId]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        die('Meeting not found.');
    }

    renderHeader('Meeting — ' . $meeting['title']);
    ?>
    <style>
        .mtg-back { display:inline-flex; align-items:center; gap:6px; color:#3B6D11; text-decoration:none; font-size:13px; margin-bottom:16px; }
        .mtg-back:hover { text-decoration:underline; }

        .mtg-card { background:#fff; border:1px solid #e2e0d5; border-radius:10px; padding:20px; margin-bottom:16px; }
        .mtg-card h1 { font-size:19px; margin:0 0 12px; color:#2c2c2a; }

        .mtg-title-center { text-align:center; padding-bottom:16px; margin-bottom:4px; border-bottom:1px solid #f0f0ea; }
        .mtg-title-center h1 { margin:0; }

        .mtg-detail-list { list-style:none; margin:0; padding:0; }
        .mtg-detail-list li {
            display:flex; padding:9px 0; font-size:13px; color:#2c2c2a;
            border-bottom:1px solid #f0f0ea;
        }
        .mtg-detail-list li:last-child { border-bottom:none; }
        .mtg-detail-list .label { width:110px; flex-shrink:0; color:#6b7280; }
        .mtg-detail-list .value { flex:1; }

        .mtg-desc { margin-top:12px; padding-top:12px; border-top:1px solid #eee; font-size:13px; color:#6b7280; line-height:1.6; }

        /* Both cards share the same compact width */
        .mtg-detail-wrap { max-width:420px; }
        .mtg-checkin-wrap .mtg-card { margin-bottom:0; }
        .mtg-checkin-card p.mtg-checkin-label { font-size:14px; font-weight:600; margin:0 0 12px; color:#2c2c2a; }

        .mtg-attendance-card p.mtg-checkin-label { font-size:14px; font-weight:600; margin:0 0 12px; color:#2c2c2a; }
        .mtg-attendance-card p.mtg-checkin-label span { color:#6b7280; font-weight:500; }
        .mtg-attendance-list { list-style:none; margin:0; padding:0; }
        .mtg-attendance-list li { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f0f0ea; }
        .mtg-attendance-list li:last-child { border-bottom:none; }
        .mtg-attendance-list .mtg-avatar { width:28px; height:28px; font-size:11px; }
        .mtg-attendance-list .att-name { font-size:13px; font-weight:600; color:#2c2c2a; margin:0; }
        .mtg-attendance-list .att-id { font-size:11px; color:#6b7280; margin:0; }

        .mtg-search-wrap { position:relative; margin-bottom:0; }
        .mtg-search-wrap input[type="text"] {
            width:100%; padding:9px 10px 9px 32px; border:1px solid #d8d2c4; border-radius:8px; font-size:13px;
        }
        .mtg-search-wrap input:focus { outline:2px solid #3B6D11; outline-offset:1px; }
        .mtg-search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px; }

        #mtg-suggestions {
            display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff;
            border:1px solid #d8d2c4; border-radius:8px; overflow:hidden; z-index:5; box-shadow:0 4px 12px rgba(0,0,0,0.06);
        }
        #mtg-suggestions .sug-row { padding:9px 10px; cursor:pointer; border-bottom:1px solid #eee; }
        #mtg-suggestions .sug-row:last-child { border-bottom:none; }
        #mtg-suggestions .sug-row:hover { background:#f3f6f1; }

        #mtg-popup {
            display:none; align-items:center; justify-content:space-between;
            background:#f3f6f1; border:1px solid #cfe0c6; border-radius:8px; padding:10px 12px; margin-top:10px; gap:8px;
        }
        .mtg-avatar { width:30px; height:30px; border-radius:50%; background:#3B6D11; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:12px; flex-shrink:0; }

        button.mtg-btn-checkin {
            display:inline-flex !important; width:auto !important; align-items:center; gap:6px;
            background:#3B6D11; color:#fff; border:none; border-radius:6px !important;
            padding:8px 14px !important; font-size:12px !important; font-weight:600; cursor:pointer;
            text-transform:none !important; letter-spacing:normal !important; box-shadow:none !important; flex-shrink:0;
        }
        button.mtg-btn-checkin:hover { background:#2e5a0d; }
        button.mtg-btn-checkin:disabled { opacity:.5; cursor:not-allowed; }

        #mtg-checkin-msg { display:none; font-size:12px; color:#2e7d32; margin-top:10px; padding:8px 10px; background:#e8f5e9; border-radius:6px; }
        #mtg-checkin-error { display:none; font-size:12px; color:#c62828; margin-top:10px; padding:8px 10px; background:#fdecea; border-radius:6px; }
    </style>

    <a href="meeting.php" class="mtg-back">&larr; Back to meetings</a>

    <div class="mtg-detail-wrap">
    <div class="mtg-card">
        <div class="mtg-title-center">
            <h1><?= htmlspecialchars($meeting['title']) ?></h1>
        </div>
        <ul class="mtg-detail-list">
            <li><span class="label">Date</span><span class="value"><?= date('F j, Y', strtotime($meeting['meeting_date'])) ?></span></li>
            <li>
                <span class="label">Time</span>
                <span class="value">
                    <?= date('g:i A', strtotime($meeting['time'])) ?>
                </span>
            </li>
            <?php if ($meeting['location']): ?>
                <li><span class="label">Location</span><span class="value"><?= htmlspecialchars($meeting['location']) ?></span></li>
            <?php endif; ?>
        </ul>
        <?php if ($meeting['description']): ?>
            <div class="mtg-desc"><?= nl2br(htmlspecialchars($meeting['description'])) ?></div>
        <?php endif; ?>
    </div>

    <div class="mtg-checkin-wrap">
        <div class="mtg-card mtg-checkin-card">
            <p class="mtg-checkin-label">Check in a member</p>

            <div class="mtg-search-wrap">
                <span class="mtg-search-icon">&#128269;</span>
                <input type="text" id="member-search" placeholder="Search by name or membership ID" autocomplete="off" />
                <div id="mtg-suggestions"></div>
            </div>

            <div id="mtg-popup">
                <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                    <div class="mtg-avatar" id="popup-avatar"></div>
                    <div style="min-width:0;">
                        <p id="popup-name" style="font-weight:600; font-size:13px; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></p>
                        <p id="popup-id" style="font-size:11px; color:#6b7280; margin:2px 0 0;"></p>
                    </div>
                </div>
                <button id="checkin-btn" type="button" class="mtg-btn-checkin">&#10003; Check-in</button>
            </div>

            <p id="mtg-checkin-msg"></p>
            <p id="mtg-checkin-error"></p>
        </div>
    </div>

    <div class="mtg-checkin-wrap">
        <div class="mtg-card mtg-attendance-card">
            <p class="mtg-checkin-label">Checked in <span id="attendance-count">(0)</span></p>
            <ul id="attendance-list" class="mtg-attendance-list"></ul>
            <p id="attendance-empty" style="font-size:13px; color:#6b7280; margin:0;">No one has checked in yet.</p>
        </div>
    </div>
    </div>

    <script>
    const meetingId = <?= (int)$meeting['id'] ?>;
    const input = document.getElementById('member-search');
    const suggestions = document.getElementById('mtg-suggestions');
    const popup = document.getElementById('mtg-popup');
    const popupName = document.getElementById('popup-name');
    const popupId = document.getElementById('popup-id');
    const popupAvatar = document.getElementById('popup-avatar');
    const checkinBtn = document.getElementById('checkin-btn');
    const checkinMsg = document.getElementById('mtg-checkin-msg');
    const checkinError = document.getElementById('mtg-checkin-error');
    const attendanceList = document.getElementById('attendance-list');
    const attendanceCount = document.getElementById('attendance-count');
    const attendanceEmpty = document.getElementById('attendance-empty');
    let currentMember = null;
    let debounceTimer = null;

    function initials(name) {
        return name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0].toUpperCase()).join('');
    }

    function loadAttendance() {
        fetch('ajax/list_checkins.php?meeting_id=' + meetingId)
            .then(r => r.json())
            .then(data => {
                attendanceCount.textContent = '(' + data.length + ')';
                if (!data.length) {
                    attendanceList.innerHTML = '';
                    attendanceEmpty.style.display = 'block';
                    return;
                }
                attendanceEmpty.style.display = 'none';
                attendanceList.innerHTML = '';
                data.forEach(m => {
                    const li = document.createElement('li');
                    li.innerHTML = '<div class="mtg-avatar">' + initials(m.name) + '</div>' +
                        '<div><p class="att-name">' + m.name + '</p><p class="att-id">' + m.membership_id + '</p></div>';
                    attendanceList.appendChild(li);
                });
            });
    }
    loadAttendance();

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        checkinMsg.style.display = 'none';
        checkinError.style.display = 'none';
        popup.style.display = 'none';
        if (!q) { suggestions.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => {
            fetch('ajax/search_member.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) { suggestions.style.display = 'none'; return; }
                    suggestions.innerHTML = '';
                    data.forEach(m => {
                        const row = document.createElement('div');
                        row.className = 'sug-row';
                        row.innerHTML = '<p style="font-size:13px;font-weight:600;margin:0;">' + m.name +
                            '</p><p style="font-size:12px;color:#6b7280;margin:2px 0 0;">' + m.membership_id + '</p>';
                        row.addEventListener('click', () => {
                            input.value = m.name;
                            suggestions.style.display = 'none';
                            currentMember = m;
                            popupName.textContent = m.name;
                            popupId.textContent = m.membership_id;
                            popupAvatar.textContent = initials(m.name);
                            popup.style.display = 'flex';
                        });
                        suggestions.appendChild(row);
                    });
                    suggestions.style.display = 'block';
                });
        }, 200);
    });

    checkinBtn.addEventListener('click', () => {
        if (!currentMember) return;
        checkinBtn.disabled = true;
        fetch('ajax/checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'meeting_id=' + meetingId + '&member_id=' + currentMember.id
        })
            .then(r => r.json())
            .then(data => {
                checkinBtn.disabled = false;
                if (data.success) {
                    checkinMsg.textContent = currentMember.name + ' checked in.';
                    checkinMsg.style.display = 'block';
                    checkinError.style.display = 'none';
                    popup.style.display = 'none';
                    input.value = '';
                    currentMember = null;
                    loadAttendance();
                } else {
                    checkinError.textContent = data.message || 'Check-in failed.';
                    checkinError.style.display = 'block';
                }
            });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.mtg-search-wrap')) suggestions.style.display = 'none';
    });
    </script>
    <?php
    renderFooter();
    exit;
}

// ============================================================
// LIST VIEW — no ?id in URL
// ============================================================

$createError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_meeting') {
    $title       = trim($_POST['title'] ?? '');
    $date        = trim($_POST['meeting_date'] ?? '');
    $time        = trim($_POST['time'] ?? '');
    $location    = trim($_POST['location'] ?? '') ?: null;
    $description = trim($_POST['description'] ?? '') ?: null;

    if ($title === '' || $date === '' || $time === '') {
        $createError = 'Title, date, and time are required.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO meetings (title, meeting_date, `time`, location, description)
             VALUES (:title, :date, :time, :location, :description)'
        );
        $stmt->execute([
            ':title' => $title, ':date' => $date, ':time' => $time,
            ':location' => $location, ':description' => $description,
        ]);
        header('Location: meeting.php?created=1');
        exit;
    }
}

$query   = trim($_GET['q'] ?? '');
$showAll = isset($_GET['all']);

if ($query !== '') {
    $stmt = $pdo->prepare('SELECT * FROM meetings WHERE title LIKE :q ORDER BY meeting_date DESC, `time` DESC');
    $stmt->execute([':q' => '%' . $query . '%']);
} elseif ($showAll) {
    $stmt = $pdo->query('SELECT * FROM meetings ORDER BY meeting_date DESC, `time` DESC');
} else {
    $stmt = $pdo->query('SELECT * FROM meetings ORDER BY meeting_date DESC, `time` DESC LIMIT 10');
}
$meetings = $stmt->fetchAll();

$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startOffset = (int)date('w', $firstDay);
$monthLabel = date('F Y', $firstDay);

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$stmt = $pdo->prepare(
    'SELECT DAY(meeting_date) AS d, title FROM meetings
     WHERE YEAR(meeting_date) = :y AND MONTH(meeting_date) = :m
     ORDER BY `time` ASC'
);
$stmt->execute([':y' => $year, ':m' => $month]);
$meetingsByDay = [];
foreach ($stmt->fetchAll() as $row) {
    $meetingsByDay[(int)$row['d']][] = $row['title'];
}

$today = date('Y-m-d');

renderHeader('Meetings');
?>
<style>
    .mtg-page-head {
        display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;
        margin-bottom:20px; padding-bottom:18px; border-bottom:2px solid #E4DCC8;
    }
    .mtg-page-head h1 { display:flex; align-items:center; gap:8px; font-size:24px; color:#2c2c2a; margin:0 0 4px; }
    .mtg-page-head p { font-size:13px; color:#6b7280; margin:0; }
    button.mtg-btn-create,
    .mtg-page-head button.mtg-btn-create {
        display:inline-flex !important; width:auto !important; max-width:none !important;
        align-items:center; gap:6px; background:#3B6D11; color:#fff; border:none;
        border-radius:6px !important; padding:11px 18px !important; font-size:13px !important;
        font-weight:600; cursor:pointer; white-space:nowrap; text-transform:none !important;
        letter-spacing:normal !important; line-height:normal;
    }
    button.mtg-btn-create:hover { background:#2e5a0d; }

    .mtg-layout { display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start; }
    @media (max-width:880px) { .mtg-layout { grid-template-columns:1fr; } }

    .mtg-search-wrap { position:relative; margin-bottom:20px; max-width:480px; }
    @media (max-width:880px) { .mtg-search-wrap { max-width:none; } }
    .mtg-search-wrap input[type="text"] { width:100%; padding:11px 12px 11px 36px; border:1px solid #d8d2c4; border-radius:8px; font-size:14px; }
    .mtg-search-wrap input:focus { outline:2px solid #3B6D11; outline-offset:1px; }
    .mtg-search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:14px; }

    .mtg-section-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
    .mtg-section-label { font-size:15px; font-weight:600; color:#2c2c2a; margin:0; }
    .mtg-viewall { font-size:13px; color:#3B6D11; text-decoration:none; font-weight:600; }
    .mtg-viewall:hover { text-decoration:underline; }

    .mtg-event-card { display:flex; align-items:center; gap:14px; background:#fff; border:1px solid #e2e0d5; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
    .mtg-event-info { flex:1; min-width:0; }
    .mtg-event-title { font-size:14px; font-weight:600; color:#2c2c2a; margin:0; }
    .mtg-event-meta { font-size:12px; color:#6b7280; margin:3px 0 0; display:flex; flex-wrap:wrap; gap:10px; }
    .mtg-event-meta strong { color:#2c2c2a; font-weight:600; }
    .mtg-event-meta span:not(:last-child)::after { content:'•'; margin-left:10px; color:#d3d1c7; }
    button.mtg-btn-open {
        display:inline-flex !important; width:auto !important; max-width:none !important;
        align-items:center; gap:6px; background:#3B6D11; color:#fff; border:none !important;
        border-radius:6px !important; padding:8px 16px !important; font-size:13px !important; font-weight:600;
        cursor:pointer; text-decoration:none; flex-shrink:0; text-transform:none !important; letter-spacing:normal !important;
        box-shadow:none !important; line-height:normal;
    }
    button.mtg-btn-open:hover { background:#2e5a0d; color:#fff; text-decoration:none; }

    button.mtg-btn-view {
        display:inline-flex !important; width:auto !important; max-width:none !important;
        align-items:center; gap:6px; background:transparent !important; color:#3B6D11;
        border:1px solid #3B6D11 !important; border-radius:6px !important; padding:8px 14px !important;
        font-size:13px !important; font-weight:600; cursor:pointer; text-transform:none !important;
        letter-spacing:normal !important; box-shadow:none !important; flex-shrink:0; line-height:normal;
    }
    button.mtg-btn-view:hover { background:#f3f6f1 !important; }
    #il-suggestions {
        display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff;
        border:1px solid #d8d2c4; border-radius:8px; overflow:hidden; z-index:5; box-shadow:0 4px 12px rgba(0,0,0,0.06);
    }
    #il-suggestions .sug-row { padding:9px 10px; cursor:pointer; border-bottom:1px solid #eee; }
    #il-suggestions .sug-row:last-child { border-bottom:none; }
    #il-suggestions .sug-row:hover { background:#f3f6f1; }
    #il-popup {
        display:none; align-items:center; justify-content:space-between;
        background:#f3f6f1; border:1px solid #cfe0c6; border-radius:8px; padding:10px 12px; margin-top:10px; gap:8px;
    }
    .mtg-avatar { width:30px; height:30px; border-radius:50%; background:#3B6D11; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:12px; flex-shrink:0; }
    button.mtg-btn-checkin {
        display:inline-flex !important; width:auto !important; align-items:center; gap:6px;
        background:#3B6D11; color:#fff; border:none; border-radius:6px !important;
        padding:8px 14px !important; font-size:12px !important; font-weight:600; cursor:pointer;
        text-transform:none !important; letter-spacing:normal !important; box-shadow:none !important; flex-shrink:0;
    }
    button.mtg-btn-checkin:hover { background:#2e5a0d; }
    button.mtg-btn-checkin:disabled { opacity:.5; cursor:not-allowed; }
    #il-checkin-msg { display:none; font-size:12px; color:#2e7d32; margin-top:10px; padding:8px 10px; background:#e8f5e9; border-radius:6px; }
    #il-checkin-error { display:none; font-size:12px; color:#c62828; margin-top:10px; padding:8px 10px; background:#fdecea; border-radius:6px; }

    .mtg-attendance-list { list-style:none; margin:0; padding:0; }
    .mtg-attendance-list li { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f0f0ea; }
    .mtg-attendance-list li:last-child { border-bottom:none; }
    .mtg-attendance-list .mtg-avatar { width:28px; height:28px; font-size:11px; }
    .mtg-attendance-list .att-name { font-size:13px; font-weight:600; color:#2c2c2a; margin:0; }
    .mtg-attendance-list .att-id { font-size:11px; color:#6b7280; margin:0; }

    .mtg-calendar { background:#fff; border:1px solid #e2e0d5; border-radius:10px; overflow:hidden; }
    .mtg-cal-header { background:#fff; border-bottom:1px solid #eee; color:#2c2c2a; display:flex; align-items:center; justify-content:space-between; padding:14px 16px; }
    .mtg-cal-header .month { font-size:14px; font-weight:700; }
    .mtg-cal-header a { color:#9ca3af; text-decoration:none; font-size:16px; padding:2px 8px; border-radius:4px; }
    .mtg-cal-header a:hover { color:#3B6D11; background:#f3f6f1; }
    .mtg-cal-body { padding:14px 16px; }
    .mtg-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
    .mtg-cal-dow { text-align:center; font-size:11px; color:#9ca3af; padding-bottom:6px; }
    .mtg-cal-day { min-height:64px; background:#fff; display:flex; flex-direction:column; align-items:center; border-radius:6px; padding:4px 2px; font-size:12px; color:#2c2c2a; }
    .mtg-cal-day.faded { color:#d3d1c7; }
    .mtg-cal-day .num { width:22px; height:22px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:12px; }
    .mtg-cal-day .num.today-num { background:#3B6D11; color:#fff; font-weight:600; }
    .mtg-cal-day .title-pill {
        display:block; width:100%; margin-top:3px; font-size:9px; line-height:1.3; color:#27500a;
        background:#c0dd97; border-radius:3px; padding:1px 3px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;
    }
    .mtg-cal-day .title-more { font-size:9px; color:#6b7280; margin-top:1px; }
    .mtg-cal-legend { display:flex; align-items:center; gap:6px; font-size:12px; color:#6b7280; margin-top:12px; padding-top:12px; border-top:1px solid #f0f0ea; }
    .mtg-cal-legend .dot { width:6px; height:6px; border-radius:50%; background:#3B6D11; }

    .mtg-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); align-items:flex-start; justify-content:center; z-index:50; overflow-y:auto; padding:40px 20px; }
    .mtg-modal { background:#fff; border-radius:10px; padding:24px; width:100%; max-width:420px; margin:auto; }
    .mtg-modal h2 { margin:0 0 16px; font-size:17px; color:#2c2c2a; }

    .mtg-checkin-modal { position:relative; }
    button.mtg-inline-close {
        position:absolute !important; top:14px !important; right:14px !important;
        display:inline-flex !important; width:24px !important; height:24px !important; max-width:none !important;
        align-items:center; justify-content:center; background:none !important; border:none !important;
        border-radius:50% !important; font-size:20px !important; line-height:1; color:#9ca3af; cursor:pointer;
        padding:0 !important; text-transform:none !important; letter-spacing:normal !important; box-shadow:none !important;
    }
    button.mtg-inline-close:hover { color:#2c2c2a; background:#f3f6f1 !important; }

    .mtg-title-center { text-align:center; padding-bottom:16px; margin-bottom:12px; border-bottom:1px solid #f0f0ea; }
    .mtg-title-center h1 { margin:0; font-size:19px; color:#2c2c2a; }

    .mtg-detail-list { list-style:none; margin:0; padding:0; }
    .mtg-detail-list li { display:flex; padding:9px 0; font-size:13px; color:#2c2c2a; border-bottom:1px solid #f0f0ea; }
    .mtg-detail-list li:last-child { border-bottom:none; }
    .mtg-detail-list .label { width:100px; flex-shrink:0; color:#6b7280; }
    .mtg-detail-list .value { flex:1; }

    .mtg-desc { margin-top:12px; padding-top:12px; border-top:1px solid #eee; font-size:13px; color:#6b7280; line-height:1.6; }
    .mtg-checkin-label { font-size:14px; font-weight:600; margin:0 0 12px; color:#2c2c2a; }
    .mtg-field { margin-bottom:14px; }
    .mtg-field label { display:block; font-size:13px; margin-bottom:6px; color:#2c2c2a; }
    .mtg-field input, .mtg-field textarea { width:100%; padding:9px 11px; border:1px solid #d8d2c4; border-radius:6px; font-size:14px; font-family:inherit; }
    .mtg-field input:focus, .mtg-field textarea:focus { outline:2px solid #3B6D11; outline-offset:1px; }
    button.mtg-btn-secondary {
        display:inline-flex !important; width:auto !important; align-items:center; justify-content:center;
        background:transparent !important; color:#6b7280; border:1px solid #d8d2c4; border-radius:6px !important;
        padding:9px 16px !important; font-size:13px !important; font-weight:500; cursor:pointer;
        text-transform:none !important; letter-spacing:normal !important; box-shadow:none !important;
    }
    button.mtg-btn-secondary:hover { background:#f3f6f1 !important; }
</style>

<div class="mtg-page-head">
    <div>
        <h1>Meetings</h1>
        <p>View upcoming meetings and manage your participation.</p>
    </div>
    <button type="button" class="mtg-btn-create" onclick="document.getElementById('create-modal').style.display='flex'">+ Create meeting</button>
</div>

<div class="mtg-layout">
    <div>
        <form method="get" class="mtg-search-wrap">
            <span class="mtg-search-icon">&#128269;</span>
            <input type="text" name="q" placeholder="Search meetings..." value="<?= htmlspecialchars($query) ?>" />
        </form>

        <div class="mtg-section-row">
            <p class="mtg-section-label">Upcoming meetings</p>
            <?php if (!$showAll): ?>
                <a href="meeting.php?all=1" class="mtg-viewall">View All &rsaquo;</a>
            <?php else: ?>
                <a href="meeting.php" class="mtg-viewall">Show recent</a>
            <?php endif; ?>
        </div>

        <?php if (empty($meetings)): ?>
            <p style="font-size:13px; color:#6b7280;">No meetings found.</p>
        <?php else: ?>
            <?php foreach ($meetings as $m): ?>
                <div class="mtg-event-card" id="mtg-row-<?= (int)$m['id'] ?>"
                     data-meeting-id="<?= (int)$m['id'] ?>"
                     data-meeting-name="<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>"
                     data-meeting-date="<?= htmlspecialchars(date('F j, Y', strtotime($m['meeting_date'])), ENT_QUOTES) ?>"
                     data-meeting-time="<?= htmlspecialchars(date('g:i A', strtotime($m['time'])), ENT_QUOTES) ?>"
                     data-meeting-location="<?= htmlspecialchars($m['location'] ?? '', ENT_QUOTES) ?>"
                     data-meeting-desc="<?= htmlspecialchars($m['description'] ?? '', ENT_QUOTES) ?>">
                    <div class="mtg-event-info">
                        <p class="mtg-event-title"><?= htmlspecialchars($m['title']) ?></p>
                        <div class="mtg-event-meta">
                            <span><strong>Date:</strong> <?= date('M j, Y', strtotime($m['meeting_date'])) ?></span>
                            <span><strong>Time:</strong> <?= date('g:i A', strtotime($m['time'])) ?></span>
                            <?php if ($m['location']): ?><span><strong>Location:</strong> <?= htmlspecialchars($m['location']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; flex-shrink:0;">
                        <button type="button" class="mtg-btn-view" onclick="mtgViewAttendance(<?= (int)$m['id'] ?>)">View</button>
                        <button type="button" class="mtg-btn-open" onclick="mtgToggleCheckin(<?= (int)$m['id'] ?>)">&#10003; Check-in</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div id="mtg-checkin-overlay" class="mtg-modal-overlay">
            <div class="mtg-modal mtg-checkin-modal">
                <button type="button" class="mtg-inline-close" onclick="mtgCloseCheckin()" aria-label="Close">&times;</button>

                <div class="mtg-title-center">
                    <h1 id="cm-title"></h1>
                </div>
                <ul class="mtg-detail-list">
                    <li><span class="label">Date</span><span class="value" id="cm-date"></span></li>
                    <li><span class="label">Time</span><span class="value" id="cm-time"></span></li>
                    <li id="cm-location-row"><span class="label">Location</span><span class="value" id="cm-location"></span></li>
                </ul>
                <div class="mtg-desc" id="cm-desc" style="display:none;"></div>

                <p class="mtg-checkin-label" style="margin-top:20px;">Check in a member</p>

                <div class="mtg-search-wrap">
                    <span class="mtg-search-icon">&#128269;</span>
                    <input type="text" id="il-member-search" placeholder="Search by name or membership ID" autocomplete="off" />
                    <div id="il-suggestions"></div>
                </div>

                <div id="il-popup">
                    <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                        <div class="mtg-avatar" id="il-popup-avatar"></div>
                        <div style="min-width:0;">
                            <p id="il-popup-name" style="font-weight:600; font-size:13px; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></p>
                            <p id="il-popup-id" style="font-size:11px; color:#6b7280; margin:2px 0 0;"></p>
                        </div>
                    </div>
                    <button id="il-checkin-btn" type="button" class="mtg-btn-checkin">&#10003; Check-in</button>
                </div>

                <p id="il-checkin-msg"></p>
                <p id="il-checkin-error"></p>
            </div>
        </div>
    </div>

    <div id="mtg-view-overlay" class="mtg-modal-overlay">
        <div class="mtg-modal mtg-checkin-modal">
            <button type="button" class="mtg-inline-close" onclick="mtgCloseView()" aria-label="Close">&times;</button>
            <div class="mtg-title-center">
                <h1 id="va-title"></h1>
            </div>
            <p class="mtg-checkin-label">Checked in <span id="va-count">(0)</span></p>
            <ul id="va-list" class="mtg-attendance-list"></ul>
            <p id="va-empty" style="font-size:13px; color:#6b7280; margin:0;">No one has checked in yet.</p>
        </div>
    </div>

    <div class="mtg-calendar">
        <div class="mtg-cal-header">
            <a href="?m=<?= $prevMonth ?>&y=<?= $prevYear ?>">&lsaquo;</a>
            <span class="month"><?= $monthLabel ?></span>
            <a href="?m=<?= $nextMonth ?>&y=<?= $nextYear ?>">&rsaquo;</a>
        </div>
        <div class="mtg-cal-body">
            <div class="mtg-cal-grid">
                <div class="mtg-cal-dow">Su</div><div class="mtg-cal-dow">Mo</div><div class="mtg-cal-dow">Tu</div>
                <div class="mtg-cal-dow">We</div><div class="mtg-cal-dow">Th</div><div class="mtg-cal-dow">Fr</div><div class="mtg-cal-dow">Sa</div>

                <?php for ($i = 0; $i < $startOffset; $i++): ?>
                    <div class="mtg-cal-day faded"></div>
                <?php endfor; ?>

                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $cellDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $isToday = $cellDate === $today;
                    $dayTitles = $meetingsByDay[$d] ?? [];
                    $visibleTitles = array_slice($dayTitles, 0, 2);
                    $extraCount = count($dayTitles) - count($visibleTitles);
                ?>
                    <div class="mtg-cal-day">
                        <span class="num <?= $isToday ? 'today-num' : '' ?>"><?= $d ?></span>
                        <?php foreach ($visibleTitles as $t): ?>
                            <span class="title-pill" title="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></span>
                        <?php endforeach; ?>
                        <?php if ($extraCount > 0): ?>
                            <span class="title-more">+<?= $extraCount ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="mtg-cal-legend"><span class="dot"></span> Meeting day</div>
        </div>
    </div>
</div>

<script>
const cmOverlay = document.getElementById('mtg-checkin-overlay');
const cmTitle = document.getElementById('cm-title');
const cmDate = document.getElementById('cm-date');
const cmTime = document.getElementById('cm-time');
const cmLocationRow = document.getElementById('cm-location-row');
const cmLocation = document.getElementById('cm-location');
const cmDesc = document.getElementById('cm-desc');
const ilInput = document.getElementById('il-member-search');
const ilSuggestions = document.getElementById('il-suggestions');
const ilPopup = document.getElementById('il-popup');
const ilPopupName = document.getElementById('il-popup-name');
const ilPopupId = document.getElementById('il-popup-id');
const ilPopupAvatar = document.getElementById('il-popup-avatar');
const ilCheckinBtn = document.getElementById('il-checkin-btn');
const ilMsg = document.getElementById('il-checkin-msg');
const ilError = document.getElementById('il-checkin-error');
let ilMeetingId = null;
let ilCurrentMember = null;
let ilDebounce = null;

function ilInitials(name) {
    return name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0].toUpperCase()).join('');
}

function mtgToggleCheckin(meetingId) {
    const row = document.getElementById('mtg-row-' + meetingId);
    if (!row) return;

    ilMeetingId = meetingId;
    cmTitle.textContent = row.dataset.meetingName || '';
    cmDate.textContent = row.dataset.meetingDate || '';
    cmTime.textContent = row.dataset.meetingTime || '';
    if (row.dataset.meetingLocation) {
        cmLocation.textContent = row.dataset.meetingLocation;
        cmLocationRow.style.display = 'flex';
    } else {
        cmLocationRow.style.display = 'none';
    }
    if (row.dataset.meetingDesc) {
        cmDesc.textContent = row.dataset.meetingDesc;
        cmDesc.style.display = 'block';
    } else {
        cmDesc.style.display = 'none';
    }

    ilInput.value = '';
    ilSuggestions.style.display = 'none';
    ilPopup.style.display = 'none';
    ilMsg.style.display = 'none';
    ilError.style.display = 'none';
    ilCurrentMember = null;

    cmOverlay.style.display = 'flex';
    ilInput.focus();
}

function mtgCloseCheckin() {
    cmOverlay.style.display = 'none';
    ilMeetingId = null;
}

cmOverlay.addEventListener('click', (e) => {
    if (e.target === cmOverlay) mtgCloseCheckin();
});

const vaOverlay = document.getElementById('mtg-view-overlay');
const vaTitle = document.getElementById('va-title');
const vaCount = document.getElementById('va-count');
const vaList = document.getElementById('va-list');
const vaEmpty = document.getElementById('va-empty');

function vaInitials(name) {
    return name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0].toUpperCase()).join('');
}

function mtgViewAttendance(meetingId) {
    const row = document.getElementById('mtg-row-' + meetingId);
    if (!row) return;

    vaTitle.textContent = row.dataset.meetingName || '';
    vaList.innerHTML = '';
    vaCount.textContent = '(0)';
    vaEmpty.style.display = 'none';
    vaOverlay.style.display = 'flex';

    fetch('ajax/list_checkins.php?meeting_id=' + meetingId)
        .then(r => r.json())
        .then(data => {
            vaCount.textContent = '(' + data.length + ')';
            if (!data.length) {
                vaEmpty.style.display = 'block';
                return;
            }
            data.forEach(m => {
                const li = document.createElement('li');
                li.innerHTML = '<div class="mtg-avatar">' + vaInitials(m.name) + '</div>' +
                    '<div><p class="att-name">' + m.name + '</p><p class="att-id">' + m.membership_id + '</p></div>';
                vaList.appendChild(li);
            });
        });
}

function mtgCloseView() {
    vaOverlay.style.display = 'none';
}

vaOverlay.addEventListener('click', (e) => {
    if (e.target === vaOverlay) mtgCloseView();
});

ilInput.addEventListener('input', () => {
    clearTimeout(ilDebounce);
    const q = ilInput.value.trim();
    ilMsg.style.display = 'none';
    ilError.style.display = 'none';
    ilPopup.style.display = 'none';
    if (!q) { ilSuggestions.style.display = 'none'; return; }

    ilDebounce = setTimeout(() => {
        fetch('ajax/search_member.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) { ilSuggestions.style.display = 'none'; return; }
                ilSuggestions.innerHTML = '';
                data.forEach(m => {
                    const row = document.createElement('div');
                    row.className = 'sug-row';
                    row.innerHTML = '<p style="font-size:13px;font-weight:600;margin:0;">' + m.name +
                        '</p><p style="font-size:12px;color:#6b7280;margin:2px 0 0;">' + m.membership_id + '</p>';
                    row.addEventListener('click', () => {
                        ilInput.value = m.name;
                        ilSuggestions.style.display = 'none';
                        ilCurrentMember = m;
                        ilPopupName.textContent = m.name;
                        ilPopupId.textContent = m.membership_id;
                        ilPopupAvatar.textContent = ilInitials(m.name);
                        ilPopup.style.display = 'flex';
                    });
                    ilSuggestions.appendChild(row);
                });
                ilSuggestions.style.display = 'block';
            });
    }, 200);
});

ilCheckinBtn.addEventListener('click', () => {
    if (!ilCurrentMember || !ilMeetingId) return;
    ilCheckinBtn.disabled = true;
    fetch('ajax/checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'meeting_id=' + ilMeetingId + '&member_id=' + ilCurrentMember.id
    })
        .then(r => r.json())
        .then(data => {
            ilCheckinBtn.disabled = false;
            if (data.success) {
                ilMsg.textContent = ilCurrentMember.name + ' checked in.';
                ilMsg.style.display = 'block';
                ilError.style.display = 'none';
                ilPopup.style.display = 'none';
                ilInput.value = '';
                ilCurrentMember = null;
            } else {
                ilError.textContent = data.message || 'Check-in failed.';
                ilError.style.display = 'block';
            }
        });
});

document.addEventListener('click', (e) => {
    if (cmOverlay.style.display === 'flex' && !e.target.closest('.mtg-search-wrap')) {
        ilSuggestions.style.display = 'none';
    }
});
</script>

<div id="create-modal" class="mtg-modal-overlay">
    <div class="mtg-modal">
        <h2>Create meeting</h2>
        <?php if ($createError): ?><p style="color:#c62828; font-size:13px;"><?= htmlspecialchars($createError) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="create_meeting" />
            <div class="mtg-field"><label>Title</label><input type="text" name="title" required /></div>
            <div class="mtg-field"><label>Date</label><input type="date" name="meeting_date" required /></div>
            <div class="mtg-field"><label>Time</label><input type="time" name="time" required /></div>
            <div class="mtg-field"><label>Location</label><input type="text" name="location" /></div>
            <div class="mtg-field"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="mtg-btn-create">Save meeting</button>
                <button type="button" class="mtg-btn-secondary" onclick="document.getElementById('create-modal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php renderFooter(); ?>