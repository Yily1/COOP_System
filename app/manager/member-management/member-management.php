<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

// Para sa printout (palitan dito kung magbago)
$coopName        = 'San Jose Farmers Multipurpose Cooperative';
$preparedByName  = 'Lielane Marie F. Dagdagan';
$preparedByTitle = 'Cooperative President';

// Show every member, plus whether they already have a linked account
$stmt = $pdo->query("
    SELECT m.*, u.id as user_id, u.email
    FROM members m
    LEFT JOIN users u ON u.member_id = m.id
    ORDER BY m.created_at DESC
");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMembers = count($members);
$linkedCount = count(array_filter($members, fn($m) => !empty($m['user_id'])));

// ============================================================
// CROP / LIVESTOCK OPTIONS + IMAGE MAPPING
// Same option lists used by api/get-member.php.
// Add a new crop or livestock type here and it flows through the
// Add/Edit checkboxes AND the View-mode image badges automatically.
// One image file per type - reused across every member who has it.
// ============================================================
$livestockOptions = ['Chicken', 'Duck', 'Pig', 'Goat', 'Cow', 'Carabao'];
$cropOptions = ['Corn', 'Cassava', 'Cucumber', 'Eggplant', 'Squash', 'Chayote', 'Bitter Melon', 'String Beans'];

function livestockImageUrl($type) {
    $key = strtolower(trim($type));
    $map = [
        'chicken' => 'chicken.jpg',
        'duck'    => 'duck.jpg',
        'pig'     => 'pig.jpg',
        'goat'    => 'goat.jpg',
        'cow'     => 'cow.jpg',
        'carabao' => 'carabao.jpg',
    ];
    $file = $map[$key] ?? 'default-livestock.jpg';
    return BASE_URL . '/assets/img/livestock/' . $file;
}

function cropImageUrl($crop) {
    $key = strtolower(trim($crop));
    $map = [
        'corn'          => 'corn.jpg',
        'cassava'       => 'cassava.jpg',
        'cucumber'      => 'cucumber.jpg',
        'eggplant'      => 'eggplant.jpg',
        'squash'        => 'squash.jpg',
        'chayote'       => 'chayote.jpg',
        'bitter melon'  => 'bitter-melon.jpg',
        'string beans'  => 'string-beans.jpg',
    ];
    $file = $map[$key] ?? 'default-crop.jpg';
    return BASE_URL . '/assets/img/crops/' . $file;
}

// Build { "Chicken": "https://.../chicken.jpg", ..., "__default__": "..." }
// so the JS side can look up an image for any known option, plus a
// fallback for whatever gets typed into "Others (specify)".
$livestockImageMap = ['__default__' => BASE_URL . '/assets/img/livestock/default-livestock.jpg'];
foreach ($livestockOptions as $opt) {
    $livestockImageMap[$opt] = livestockImageUrl($opt);
}
$cropImageMap = ['__default__' => BASE_URL . '/assets/img/crops/default-crop.jpg'];
foreach ($cropOptions as $opt) {
    $cropImageMap[$opt] = cropImageUrl($opt);
}

renderHeader('Member Management');
?>

<style>
/* ============================================================
   SHARED COLOR THEME
   (Kapag may global stylesheet na, ilipat ang :root block na ito doon
    at tanggalin dito. Pareho ang block sa 3 pages.)
   ============================================================ */
:root {
    --c-brand:        #3B6D11;
    --c-brand-hover:  #2e5a0d;
    --c-brand-soft:   #EAF3DE;
    --c-brand-ink:    #27500A;
    --c-ink:          #1b3a24;
    --c-text:         #2c3e2c;
    --c-muted:        #5a645a;
    --c-success:      #2e7d32;
    --c-danger:       #a6322f;
    --c-danger-solid: #b02a37;
    --c-warn-bg:      #FAEEDA;
    --c-warn-ink:     #633806;
    --c-info:         #274b81;
    --c-surface:      #fff;
    --c-divider:      #e8ede0;
    --c-field-border: #7a857a;
}

.mm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    gap: 16px;
    width: 100%;
    flex-wrap: wrap;
}
.mm-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.mm-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.mm-search-wrap .material-icons {
    position: absolute;
    left: 12px;
    font-size: 18px;
    color: var(--c-muted);
    pointer-events: none;
}
.mm-search-wrap input[type="text"] {
    padding: 9px 14px 9px 38px;
    border-radius: 999px;
    border: 1px solid var(--c-field-border);
    box-sizing: border-box;
    font-size: 13px;
    width: 240px;
}
.mm-type-select {
    padding: 9px 12px;
    border-radius: 999px;
    border: 1px solid var(--c-field-border);
    box-sizing: border-box;
    font-size: 13px;
}
/* Placeholder at focus: pareho sa lahat ng fields */
.mm-search-wrap input::placeholder,
.mm-field input::placeholder { color: #6b756b; opacity: 1; }
.mm-search-wrap input[type="text"]:focus,
.mm-field input:focus,
.mm-field select:focus {
    outline: none;
    border-color: var(--c-brand);
    box-shadow: 0 0 0 3px rgba(59, 109, 17, 0.18);
}
.mm-modal-box button:focus-visible,
.mm-add-btn:focus-visible,
.mm-print-btn:focus-visible,
.mm-actions a:focus-visible {
    outline: 2px solid var(--c-brand);
    outline-offset: 2px;
}

/* Brand green, same as the "Add product" button */
.mm-add-btn {
    background: var(--c-brand) !important;
    color: #fff !important;
    border: none !important;
    padding: 10px 18px !important;
    border-radius: 6px !important;
    cursor: pointer;
    font-size: 14px !important;
    font-weight: 600 !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    width: auto !important;
    max-width: 220px !important;
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}
.mm-add-btn:hover { background: var(--c-brand-hover) !important; }

/* Print Report button: outlined green so "Add member" stays the main action */
.mm-print-btn {
    background: #fff !important;
    color: var(--c-brand) !important;
    border: 1px solid var(--c-brand) !important;
    padding: 10px 18px !important;
    border-radius: 6px !important;
    cursor: pointer;
    font-size: 14px !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    width: auto !important;
    max-width: 220px !important;
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}
.mm-print-btn:hover { background: #f1f6ea !important; }

.mm-summary-text {
    color: var(--c-muted);
    margin: 0 0 16px;
    font-size: 14px;
}

.mm-name-cell { display: flex; align-items: center; gap: 10px; }
.mm-avatar {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 12px; color: #fff;
}
.mm-table-wrap {
    overflow: hidden;
    border: 1px solid var(--c-divider);
    border-radius: 14px;
}
.mm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.mm-table th {
    text-align: left; padding: 12px 14px; background: #fff;
    border-bottom: 1px solid var(--c-divider);
    color: var(--c-muted); font-size: 13px;
}
.mm-table td { padding: 12px 14px; border-bottom: 1px solid #eee; vertical-align: middle; color: var(--c-text); }
.mm-table tbody tr:last-child td { border-bottom: none; }
.mm-table tr.mm-hidden { display: none; }

.mm-badge { font-size: 11px; padding: 2px 9px; border-radius: 20px; font-weight: 600; white-space: nowrap; }
.mm-badge.linked { background: #e8f5e9; color: var(--c-success); }
.mm-badge.not-linked { background: #eee; color: #555; }

.mm-actions { font-size: 13px; white-space: nowrap; }

.mm-empty { text-align: center; padding: 40px; color: var(--c-muted); }

/* ===== Member modal (Add / Edit / View) =====
   The backdrop never scrolls. The modal box is capped to the screen
   height, and only the middle part (.mm-form-body) scrolls, so the
   title stays at the top and the buttons stay at the bottom. */
.mm-modal-backdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 100; align-items: center;
    justify-content: center; padding: 20px 16px; overflow: hidden;
    box-sizing: border-box;
}
.mm-modal-box {
    background: #fff; border-radius: 10px; padding: 22px 26px 18px; width: 100%;
    max-width: 640px; box-sizing: border-box; position: relative;
    display: flex; flex-direction: column;
    max-height: calc(100vh - 40px);
    overflow: hidden;
}
.mm-modal-close {
    position: absolute !important; top: 16px !important; right: 16px !important;
    background: none !important; border: none !important; font-size: 20px !important;
    cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important;
    line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;
}
.mm-modal-box h2 { margin: 0 0 4px; font-size: 19px; padding-right: 30px; flex: 0 0 auto; color: var(--c-ink); }
.mm-modal-subtext { margin: 0 0 14px; font-size: 13px; color: var(--c-muted); flex: 0 0 auto; }
.mm-modal-box h3 { font-size: 14px; color: #444; margin: 18px 0 10px; }
.mm-modal-box h3:first-of-type { margin-top: 0; }

/* The form fills the remaining space; body scrolls, footer stays put */
#mmForm {
    display: flex; flex-direction: column;
    flex: 1 1 auto; min-height: 0;
}
.mm-form-body {
    flex: 1 1 auto; min-height: 0;
    overflow-y: auto;
    padding: 2px 8px 4px 0;
}

.mm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
.mm-form-grid .span-2 { grid-column: span 2; }
@media (max-width: 600px) {
    .mm-form-grid { grid-template-columns: 1fr; }
    .mm-form-grid .span-2 { grid-column: span 1; }
}
.mm-field label { display: block; margin-bottom: 4px; font-weight: 500; font-size: 13px; }
.mm-field input[type="text"],
.mm-field input[type="date"],
.mm-field select {
    width: 100%; padding: 7px 8px; border-radius: 6px; border: 1px solid var(--c-field-border); box-sizing: border-box; font-size: 14px;
}
.mm-field input:disabled, .mm-field select:disabled {
    background: #f5f5f5; color: #666;
}

/* Auto-built address preview */
.mm-address-preview {
    background: #f5f8f0; border: 1px solid var(--c-divider); border-radius: 6px;
    padding: 9px 12px; font-size: 13px; color: var(--c-text); min-height: 18px;
}
.mm-address-note { margin: 4px 0 0; font-size: 12px; color: var(--c-warn-ink); display: none; }

.mm-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-top: 4px; margin-bottom: 8px; }
.mm-checkbox-group label { display: flex; align-items: center; font-weight: normal; font-size: 13px; cursor: pointer; }
.mm-checkbox-group input[type="checkbox"] { width: auto; margin-right: 6px; }

/* View-mode image badges (Crops Grown / Livestock Raised) */
.mm-badge-view { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 8px; }
.mm-badge-view .mm-crop-card {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    width: 90px; text-align: center;
}
.mm-badge-view .mm-crop-card img {
    width: 84px; height: 84px; border-radius: 14px; object-fit: cover;
    border: 1px solid var(--c-divider); flex-shrink: 0;
}
.mm-badge-view .mm-crop-card span {
    font-size: 13px; font-weight: 500; color: #333; line-height: 1.3;
}

#mmErrors, #mmSuccess {
    display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 12px;
    font-size: 13px; flex: 0 0 auto;
}
#mmErrors { background: #f8d7da; color: #721c24; }
#mmSuccess { background: #d4edda; color: #155724; }

.mm-modal-footer {
    display: flex; gap: 8px; justify-content: flex-end;
    margin-top: 0; padding-top: 14px; flex: 0 0 auto;
    border-top: 1px solid #eee;
}
.mm-btn-secondary {
    padding: 10px 18px !important; border-radius: 6px !important; border: 1px solid var(--c-field-border) !important;
    background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px !important;
    width: auto !important; flex: 0 0 auto !important;
}
/* Brand green, same as the "Add product" button
   (Register Member / Update Member; the Delete button overrides the color inline) */
.mm-btn-primary {
    padding: 10px 18px !important; border-radius: 6px !important; border: none !important;
    background: var(--c-brand) !important; color: #fff !important; cursor: pointer; font-size: 14px !important;
    font-weight: 600 !important; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    width: auto !important; flex: 0 0 auto !important;
}
.mm-btn-primary:hover { background: var(--c-brand-hover) !important; }
.mm-btn-danger { background: var(--c-danger-solid) !important; }
.mm-btn-danger:hover { background: #8f1f2b !important; }

/* ===== Delete confirmation modal ===== */
.mm-confirm-box {
    background: #fff; border-radius: 10px; padding: 24px; width: 100%; max-width: 380px; box-sizing: border-box;
}
.mm-confirm-box h3 { margin: 0 0 8px; font-size: 17px; }
.mm-confirm-box p { margin: 0 0 18px; font-size: 14px; color: #444; }
.mm-confirm-box .mm-modal-footer { border-top: none; padding-top: 0; margin-top: 16px; }

/* ============================================================
   PRINT REPORT
   Hidden on screen. When the Print Report button is clicked, only
   #mmPrintReport is shown in the printout.
   ============================================================ */
#mmPrintReport { display: none; }

/* Footer sa bawat page: "System generated by SJFMC" (kaliwa) at
   "Page X of Y" (kanan).
   Works in Chrome / Edge. Firefox may not show the margin boxes. */
@page {
    size: A4 landscape;
    margin: 14mm 12mm 16mm;
    /* Blank top margin boxes so the browser's own header
       (date/time and page title) is not printed */
    @top-left   { content: ""; }
    @top-center { content: ""; }
    @top-right  { content: ""; }
    @bottom-left {
        content: "System generated by SJFMC";
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        color: #000;
    }
    @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        color: #000;
    }
}

@media print {
    /* Remove this page's own content so it doesn't create blank pages */
    .mm-header,
    .mm-summary-text,
    .mm-table-wrap,
    #mmNoResults,
    .mm-modal-backdrop {
        display: none !important;
    }

    /* Hide everything else (sidebar, top bar, etc.), show only the report */
    body * { visibility: hidden !important; }
    #mmPrintReport,
    #mmPrintReport * { visibility: visible !important; }

    /* Flex column + min-height = isang buong page (A4 landscape:
       210mm - 14mm top - 16mm bottom margin = 180mm).
       Dahil dito, naitutulak ng "Prepared by" (margin-top: auto)
       ang sarili niya sa ibabang bahagi ng page. */
    #mmPrintReport {
        display: flex;
        flex-direction: column;
        min-height: 180mm;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        color: #000;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
        -webkit-font-smoothing: auto;
    }

    /* Header: logo + buong pangalan ng coop sa kaliwa */
    .pr-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 10px;
        border-bottom: 1.5px solid #000;
    }
    .pr-brand {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    /* Bagong logo (square, may text sa paligid kaya mas malaki para mabasa) */
    .pr-logo {
        width: 84px;
        height: 84px;
        object-fit: contain;
        flex: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .pr-title { font-size: 17px; font-weight: 700; line-height: 1.2; }
    .pr-sub { font-size: 12px; color: #333; margin-top: 3px; }

    .pr-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        table-layout: fixed;
        margin-top: 10px;
    }
    .pr-table thead { display: table-header-group; } /* repeats on every page */
    .pr-table th {
        text-align: left;
        padding: 8px 6px;
        border-bottom: 1px solid #000;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .pr-table td {
        padding: 7px 6px;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
        word-wrap: break-word;
        color: #000;
    }
    .pr-table tr { page-break-inside: avoid; }

    /* "Prepared by" nasa ibabang bahagi ng page (margin-top: auto).
       Kung mahaba ang table, susunod ito sa dulo ng table. */
    .pr-prepared {
        margin-top: auto;
        padding-top: 20px;
        font-size: 12px;
        page-break-inside: avoid;
    }
    .pr-prepared-label { margin: 0 0 40px; }
    .pr-prepared-name { margin: 0; font-weight: 700; }
    .pr-prepared-title { margin: 2px 0 0; font-size: 12px; }
    .pr-prepared-date { margin: 6px 0 0; font-size: 12px; }

    /* Column widths (8 columns) */
    .pr-table th:nth-child(1) { width: 13%; } /* Membership ID */
    .pr-table th:nth-child(2) { width: 16%; } /* Name */
    .pr-table th:nth-child(3) { width: 8%; }  /* Gender */
    .pr-table th:nth-child(4) { width: 10%; } /* Type */
    .pr-table th:nth-child(5) { width: 10%; } /* Farmer type */
    .pr-table th:nth-child(6) { width: 13%; } /* Contact number */
    .pr-table th:nth-child(7) { width: 20%; } /* Address */
    .pr-table th:nth-child(8) { width: 10%; } /* Date joined */
}
</style>

<div class="mm-header">
    <h1 style="margin: 0;">List of Registered Members</h1>
    <div class="mm-header-actions">
        <div class="mm-search-wrap">
            <span class="material-icons" aria-hidden="true">search</span>
            <input type="text" id="mmSearchInput" placeholder="Search by name or membership ID" aria-label="Search by name or membership ID">
        </div>
        <button type="button" class="mm-print-btn" id="mmPrintBtn">
            <span class="material-icons" style="font-size: 18px;">print</span>
            Print Report
        </button>
        <button type="button" class="mm-add-btn" id="mmAddBtn">
            <span class="material-icons" style="font-size: 18px;">person_add</span>
            Add member
        </button>
    </div>
</div>

<p class="mm-summary-text">Showing all cooperative members (<?php echo $totalMembers; ?> total, <?php echo $linkedCount; ?> linked)</p>

<?php if (!empty($members)): ?>
<div class="mm-table-wrap">
    <table class="mm-table">
        <thead>
            <tr>
                <th>Membership ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Farmer Type</th>
                <th>Date Joined</th>
                <th>Account</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="mmTableBody">
            <?php foreach ($members as $m): ?>
            <tr class="mm-row"
                data-id="<?php echo $m['id']; ?>"
                data-search="<?php echo htmlspecialchars(strtolower($m['membership_id'] . ' ' . $m['last_name'] . ' ' . $m['first_name'])); ?>"
                data-type="<?php echo htmlspecialchars($m['membership_type']); ?>">
                <td><?php echo htmlspecialchars($m['membership_id']); ?></td>
                <?php
                // Isang shared na listahan ng avatar colors (pareho sa User management);
                // lahat ay pasado sa puting text.
                $avatarColors = ['#274b81', '#2e7d32', '#8a5a0b', '#6b3fa0', '#993c1d', '#0F6E56'];
                $avatarColor = $avatarColors[$m['id'] % count($avatarColors)];
                $initials = strtoupper(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1));
                ?>
                <td>
                    <div class="mm-name-cell">
                        <div class="mm-avatar" style="background: <?php echo $avatarColor; ?>;"><?php echo htmlspecialchars($initials); ?></div>
                        <span><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?></span>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                <td><?php echo htmlspecialchars($m['farmer_type'] ?? '-'); ?></td>
                <td><?php echo date('M j, Y', strtotime($m['date_joined'])); ?></td>
                <td>
                    <?php if ($m['user_id']): ?>
                        <span class="mm-badge linked">Linked</span>
                    <?php else: ?>
                        <span class="mm-badge not-linked">Not linked</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <div class="mm-actions">
                        <a class="mm-view-link" data-action="view"
                           style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:var(--c-info) !important; text-decoration:none !important; margin-left:4px !important; cursor:pointer;">
                            <span class="material-icons" style="font-size:14px !important;">visibility</span>View
                        </a>
                        <a class="mm-edit-link" data-action="edit"
                           style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:var(--c-success) !important; text-decoration:none !important; margin-left:4px !important; cursor:pointer;">
                            <span class="material-icons" style="font-size:14px !important;">edit</span>Edit
                        </a>
                        <?php if ($m['user_id']): ?>
                        <span class="mm-action-disabled" title="Has a linked account - cannot be deleted"
                              style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#767676 !important; margin-left:4px !important;">
                            <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                        </span>
                        <?php else: ?>
                        <a class="mm-delete-link" data-action="delete"
                           style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:var(--c-danger) !important; text-decoration:none !important; margin-left:4px !important; cursor:pointer;">
                            <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p id="mmNoResults" class="mm-empty" style="display: none;">No members match your search.</p>
<?php else: ?>
<p class="mm-empty">No members yet. Click "Add member" to create the first one.</p>
<?php endif; ?>

<!-- ============================================================
     MEMBER MODAL (Add / Edit / View - same form, different mode)
     Layout: title + messages (fixed) / form body (scrolls) / buttons (fixed)
     ============================================================ -->
<div class="mm-modal-backdrop" id="mmModalBackdrop">
    <div class="mm-modal-box">
        <button type="button" class="mm-modal-close" id="mmModalClose" aria-label="Close">&times;</button>
        <h2 id="mmModalTitle">Add Member</h2>
        <p class="mm-modal-subtext" id="mmModalSubtext">Create a new cooperative member profile. Membership ID is auto-generated.</p>

        <div id="mmErrors"></div>
        <div id="mmSuccess"></div>

        <form id="mmForm" autocomplete="off">
            <input type="hidden" id="mm_member_id" name="member_id" value="">

            <div class="mm-form-body">
                <h3>Personal Information</h3>
                <div class="mm-form-grid">
                    <div class="mm-field span-2">
                        <label>Membership ID</label>
                        <input type="text" id="mm_membership_id" disabled value="Auto-generated (e.g. SJFMC-0001)">
                    </div>
                    <div class="mm-field">
                        <label for="mm_last_name">Last Name</label>
                        <input type="text" id="mm_last_name" name="last_name" placeholder="Dela Cruz" maxlength="50">
                    </div>
                    <div class="mm-field">
                        <label for="mm_first_name">First Name</label>
                        <input type="text" id="mm_first_name" name="first_name" placeholder="Juan" maxlength="50">
                    </div>
                    <div class="mm-field">
                        <label for="mm_middle_name">Middle Name</label>
                        <input type="text" id="mm_middle_name" name="middle_name" placeholder="Santos" maxlength="50">
                    </div>
                    <div class="mm-field">
                        <label for="mm_gender">Gender</label>
                        <select id="mm_gender" name="gender">
                            <option value="">-- Select Gender --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="mm-field">
                        <label for="mm_date_of_birth">Date of Birth</label>
                        <input type="date" id="mm_date_of_birth" name="date_of_birth">
                    </div>
                    <div class="mm-field">
                        <label for="mm_occupation">Occupation</label>
                        <input type="text" id="mm_occupation" name="occupation" placeholder="Farmer" maxlength="60">
                    </div>
                    <div class="mm-field span-2">
                        <label for="mm_contact_number">Contact Number</label>
                        <input type="text" id="mm_contact_number" name="contact_number" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric">
                    </div>
                </div>

                <h3>Address</h3>
                <div class="mm-form-grid">
                    <div class="mm-field">
                        <label for="mm_region">Region</label>
                        <select id="mm_region" name="region_code"><option value="">-- Select Region --</option></select>
                    </div>
                    <div class="mm-field">
                        <label for="mm_province">Province</label>
                        <select id="mm_province" name="province_code"><option value="">-- Select Province --</option></select>
                    </div>
                    <div class="mm-field">
                        <label for="mm_city">Municipality / City</label>
                        <select id="mm_city" name="city_code"><option value="">-- Select Municipality / City --</option></select>
                    </div>
                    <div class="mm-field">
                        <label for="mm_barangay">Barangay</label>
                        <select id="mm_barangay" name="barangay_code"><option value="">-- Select Barangay --</option></select>
                    </div>
                    <div class="mm-field span-2">
                        <label for="mm_purok">Purok / Street</label>
                        <input type="text" id="mm_purok" name="purok" placeholder="Purok 3, Rizal St." maxlength="100">
                    </div>
                    <div class="mm-field span-2">
                        <label>Complete Address (auto-built)</label>
                        <div class="mm-address-preview" id="mm_address_preview">—</div>
                        <p class="mm-address-note" id="mm_address_note">This member's address was saved in the old format. Please select the address above, then save.</p>
                    </div>
                    <input type="hidden" id="mm_region_name" name="region_name">
                    <input type="hidden" id="mm_province_name" name="province_name">
                    <input type="hidden" id="mm_city_name" name="city_name">
                    <input type="hidden" id="mm_barangay_name" name="barangay_name">
                </div>

                <h3>Membership Information</h3>
                <div class="mm-form-grid">
                    <div class="mm-field">
                        <label for="mm_membership_type">Membership Type</label>
                        <select id="mm_membership_type" name="membership_type">
                            <option value="">-- Select Type --</option>
                            <option value="Regular">Regular</option>
                            <option value="Associate">Associate</option>
                        </select>
                    </div>
                    <div class="mm-field">
                        <label for="mm_date_joined">Date Joined</label>
                        <input type="date" id="mm_date_joined" name="date_joined">
                    </div>
                    <div class="mm-field span-2">
                        <label for="mm_hectares_cultivated">Number of Hectares Cultivated</label>
                        <select id="mm_hectares_cultivated" name="hectares_cultivated">
                            <option value="">-- Select Number of Hectares --</option>
                            <?php for ($h = 1; $h <= 10; $h++): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?> hectare<?php echo $h !== 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <h3>Farming Profile</h3>
                <div class="mm-form-grid">
                    <div class="mm-field span-2">
                        <label for="mm_farmer_type">Type of Farmer</label>
                        <select id="mm_farmer_type" name="farmer_type">
                            <option value="">-- Select Type --</option>
                            <option value="Livestock">Livestock</option>
                            <option value="Crops">Crops</option>
                            <option value="Both">Both</option>
                        </select>
                    </div>

                    <div class="mm-field span-2" id="mm_livestock_field" style="display: none;">
                        <label>Livestock Raised</label>
                        <div class="mm-checkbox-group" id="mm_livestock_checkboxes">
                            <?php foreach ($livestockOptions as $opt): ?>
                                <label><input type="checkbox" name="livestock[]" value="<?php echo $opt; ?>"><?php echo $opt; ?></label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" id="mm_livestock_others" name="livestock_others" placeholder="Others (specify)" maxlength="100">
                        <div class="mm-badge-view" id="mm_livestock_badges" style="display: none;"></div>
                    </div>

                    <div class="mm-field span-2" id="mm_crops_field" style="display: none;">
                        <label>Crops Grown</label>
                        <div class="mm-checkbox-group" id="mm_crops_checkboxes">
                            <?php foreach ($cropOptions as $opt): ?>
                                <label><input type="checkbox" name="crops[]" value="<?php echo $opt; ?>"><?php echo $opt; ?></label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" id="mm_crops_others" name="crops_others" placeholder="Others (specify)" maxlength="100">
                        <div class="mm-badge-view" id="mm_crops_badges" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <div class="mm-modal-footer">
                <button type="button" class="mm-btn-secondary" id="mmCancelBtn">Close</button>
                <button type="submit" class="mm-btn-primary" id="mmSubmitBtn">Register Member</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     DELETE CONFIRMATION MODAL
     ============================================================ -->
<div class="mm-modal-backdrop" id="mmDeleteBackdrop">
    <div class="mm-confirm-box">
        <h3>Delete member?</h3>
        <p id="mmDeleteText">Are you sure you want to delete this member profile? This cannot be undone.</p>
        <div id="mmDeleteErrors" style="display:none; background:#f8d7da; color:#721c24; padding:10px 14px; border-radius:6px; margin-bottom:14px; font-size:13px;"></div>
        <div class="mm-modal-footer">
            <button type="button" class="mm-btn-secondary" id="mmDeleteCancelBtn">Cancel</button>
            <button type="button" class="mm-btn-primary mm-btn-danger" id="mmDeleteConfirmBtn">Delete</button>
        </div>
    </div>
</div>

<?php
// ============================================================
// PRINT REPORT (hidden on screen, shown only when printing)
// All members, sorted by Membership ID.
// ============================================================
$printMembers = $members;
usort($printMembers, fn($a, $b) => strcmp($a['membership_id'], $b['membership_id']));
?>
<div id="mmPrintReport">
    <div class="pr-head">
        <div class="pr-brand">
            <!-- BAGONG LOGO: palitan ang file sa assets/img/logo.png ng bagong logo.
                 Ang ?v=2 ay para hindi gamitin ng browser ang lumang naka-cache na logo.
                 (Kung may bago kang file name, e.g. logo-new.png, palitan dito.) -->
            <img class="pr-logo" src="<?php echo BASE_URL; ?>/assets/img/logo.png?v=2" alt="San Jose Farmers Multipurpose Cooperative logo">
            <div>
                <div class="pr-title"><?php echo htmlspecialchars($coopName); ?></div>
                <div class="pr-sub">List of Registered Members</div>
            </div>
        </div>
    </div>
    <table class="pr-table">
        <thead>
            <tr>
                <th>Membership ID</th>
                <th>Name</th>
                <th>Gender</th>
                <th>Type</th>
                <th>Farmer Type</th>
                <th>Contact number</th>
                <th>Address</th>
                <th>Date joined</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($printMembers as $m):
                $mi = !empty($m['middle_name']) ? ' ' . strtoupper(mb_substr($m['middle_name'], 0, 1)) . '.' : '';
            ?>
            <tr>
                <td><?php echo htmlspecialchars($m['membership_id']); ?></td>
                <td><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name'] . $mi); ?></td>
                <td><?php echo htmlspecialchars($m['gender'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                <td><?php echo htmlspecialchars($m['farmer_type'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($m['contact_number'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($m['address'] ?? '-'); ?></td>
                <td><?php echo date('M j, Y', strtotime($m['date_joined'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pr-prepared">
        <p class="pr-prepared-label">Prepared by:</p>
        <!-- All caps ang pangalan sa printout -->
        <p class="pr-prepared-name"><?php echo htmlspecialchars(mb_strtoupper($preparedByName)); ?></p>
        <p class="pr-prepared-title"><?php echo htmlspecialchars($preparedByTitle); ?></p>
        <!-- Awtomatikong petsa ng pag-print. Ang PHP ang unang laman (Philippine time);
             ina-update ito ng JavaScript sa mismong oras ng pag-print. -->
        <p class="pr-prepared-date">Date: <span id="mmPrintDate"><?php echo (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('F j, Y'); ?></span></p>
    </div>
</div>

<script type="application/json" id="mmLivestockImageMap"><?php echo json_encode($livestockImageMap); ?></script>
<script type="application/json" id="mmCropImageMap"><?php echo json_encode($cropImageMap); ?></script>

<script>
(function() {
    const BASE = '<?php echo BASE_URL; ?>';
    const API = `${BASE}/app/manager/member-management/api`;
    const livestockImageMap = JSON.parse(document.getElementById('mmLivestockImageMap').textContent);
    const cropImageMap = JSON.parse(document.getElementById('mmCropImageMap').textContent);
    const $ = id => document.getElementById(id);

    // ---------- Print ----------
    // Isinusulat ang petsa ngayon (Philippine time) sa printout bago mag-print,
    // kaya tama pa rin kahit matagal nang nakabukas ang page.
    function setPrintDate() {
        const el = $('mmPrintDate');
        if (!el) return;
        el.textContent = new Date().toLocaleDateString('en-US', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    // Gumagana rin kapag Ctrl+P ang ginamit imbes na Print Report button
    window.addEventListener('beforeprint', setPrintDate);

    $('mmPrintBtn')?.addEventListener('click', function() {
        setPrintDate();
        window.print();
    });

    // ---------- Search / filter ----------
    const searchInput = $('mmSearchInput');
    const rows = document.querySelectorAll('.mm-row');
    const noResults = $('mmNoResults');

    function applyFilters() {
        const q = (searchInput?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
            const show = !q || row.dataset.search.includes(q);
            row.classList.toggle('mm-hidden', !show);
            if (show) visibleCount++;
        });

        if (noResults) noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    }
    searchInput?.addEventListener('input', applyFilters);

    // ---------- Member modal (Add / Edit / View) ----------
    const modalBackdrop = $('mmModalBackdrop');
    const modalTitle = $('mmModalTitle');
    const modalSubtext = $('mmModalSubtext');
    const form = $('mmForm');
    const formBody = form.querySelector('.mm-form-body');
    const errorsBox = $('mmErrors');
    const successBox = $('mmSuccess');
    const submitBtn = $('mmSubmitBtn');
    const cancelBtn = $('mmCancelBtn');
    const closeBtn = $('mmModalClose');
    const membershipIdField = $('mm_membership_id');
    const memberIdField = $('mm_member_id');

    const livestockCheckboxes = $('mm_livestock_checkboxes');
    const livestockOthersInput = $('mm_livestock_others');
    const livestockBadges = $('mm_livestock_badges');
    const cropsCheckboxes = $('mm_crops_checkboxes');
    const cropsOthersInput = $('mm_crops_others');
    const cropsBadges = $('mm_crops_badges');

    const allFormControls = () => form.querySelectorAll('input, select, textarea, button[type="submit"]');

    function showError(msg) {
        errorsBox.textContent = msg;
        errorsBox.style.display = 'block';
    }

    // ---------- Input rules: letters only / numbers only ----------
    // (Server-side validation pa rin ang final na bantay.)
    const RE_NAME   = /[^\p{L} .'’\-]/gu;          // letters, space . ' -
    const RE_OTHERS = /[^\p{L} ,.'’\-]/gu;         // letters, space , . ' -
    const RE_DIGITS = /[^0-9]/g;                   // numbers only
    const RE_PURok  = /[^\p{L}\p{N} .,#\/\-]/gu;   // letters + numbers (Purok 3)

    function bindFilter(id, re) {
        const el = $(id);
        el.addEventListener('input', () => {
            const v = el.value.replace(re, '');
            if (v !== el.value) el.value = v;
        });
    }
    bindFilter('mm_last_name', RE_NAME);
    bindFilter('mm_first_name', RE_NAME);
    bindFilter('mm_middle_name', RE_NAME);
    bindFilter('mm_occupation', RE_NAME);
    bindFilter('mm_contact_number', RE_DIGITS);
    bindFilter('mm_purok', RE_PURok);
    bindFilter('mm_livestock_others', RE_OTHERS);
    bindFilter('mm_crops_others', RE_OTHERS);

    $('mm_date_of_birth').max = new Date().toISOString().slice(0, 10);

    // ---------- Philippine address (PSGC) ----------
    const regionSel = $('mm_region');
    const provinceSel = $('mm_province');
    const citySel = $('mm_city');
    const brgySel = $('mm_barangay');
    const purokInput = $('mm_purok');
    const addrPreview = $('mm_address_preview');
    const addrNote = $('mm_address_note');

    let addrLocked = false;     // true sa View mode
    let regionsCache = null;
    let legacyAddress = '';     // lumang address na walang codes
    let hasProvinces = true;    // false sa mga region na walang province (hal. NCR)

    async function psgc(params) {
        const res = await fetch(`${API}/psgc.php?` + new URLSearchParams(params));
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not load address list.');
        return json.data;
    }

    function setLoading(sel) {
        sel.innerHTML = '';
        const o = document.createElement('option');
        o.value = ''; o.textContent = 'Loading...';
        sel.appendChild(o);
        sel.disabled = true;
    }

    function fillSelect(sel, items, label, selected) {
        sel.innerHTML = '';
        const first = document.createElement('option');
        first.value = ''; first.textContent = `-- Select ${label} --`;
        sel.appendChild(first);
        items.forEach(i => {
            const o = document.createElement('option');
            o.value = i.code; o.textContent = i.name;
            sel.appendChild(o);
        });
        if (selected) sel.value = selected;
        sel.disabled = addrLocked || items.length === 0;
    }

    function refreshAddrDisabled() {
        [regionSel, provinceSel, citySel, brgySel].forEach(s => {
            s.disabled = addrLocked || s.options.length <= 1;
        });
    }

    async function loadRegions(selected) {
        if (!regionsCache) {
            setLoading(regionSel);
            regionsCache = await psgc({ type: 'regions' });
        }
        fillSelect(regionSel, regionsCache, 'Region', selected);
    }
    async function loadProvinces(regionCode, selected) {
        if (!regionCode) { hasProvinces = true; fillSelect(provinceSel, [], 'Province'); return; }
        setLoading(provinceSel);
        const list = await psgc({ type: 'provinces', code: regionCode });
        hasProvinces = list.length > 0;
        fillSelect(provinceSel, list, 'Province', selected);
        if (!hasProvinces) provinceSel.options[0].textContent = '-- No province for this region --';
    }
    async function loadCities(regionCode, provinceCode, selected) {
        if (!regionCode || (hasProvinces && !provinceCode)) { fillSelect(citySel, [], 'Municipality / City'); return; }
        setLoading(citySel);
        const list = hasProvinces
            ? await psgc({ type: 'cities_by_province', code: provinceCode })
            : await psgc({ type: 'cities_by_region', code: regionCode });
        fillSelect(citySel, list, 'Municipality / City', selected);
    }
    async function loadBarangays(cityCode, selected) {
        if (!cityCode) { fillSelect(brgySel, [], 'Barangay'); return; }
        setLoading(brgySel);
        const list = await psgc({ type: 'barangays', code: cityCode });
        fillSelect(brgySel, list, 'Barangay', selected);
    }

    function selName(sel) {
        const o = sel.options[sel.selectedIndex];
        return sel.value && o ? o.textContent : '';
    }

    // Isinusulat ang pangalan sa hidden fields at ina-update ang preview
    function updateAddress() {
        const rn = selName(regionSel), pn = selName(provinceSel), cn = selName(citySel), bn = selName(brgySel);
        $('mm_region_name').value = rn;
        $('mm_province_name').value = pn;
        $('mm_city_name').value = cn;
        $('mm_barangay_name').value = bn;

        const brgy = bn ? (/^(brgy|barangay)\b/i.test(bn) ? bn : 'Brgy. ' + bn) : '';
        const built = [purokInput.value.trim(), brgy, cn, pn, rn].filter(Boolean).join(', ');

        if (built) {
            addrPreview.textContent = built;
            addrNote.style.display = 'none';
        } else if (legacyAddress) {
            addrPreview.textContent = legacyAddress;
            addrNote.style.display = addrLocked ? 'none' : 'block';
        } else {
            addrPreview.textContent = '—';
            addrNote.style.display = 'none';
        }
    }

    regionSel.addEventListener('change', async () => {
        fillSelect(provinceSel, [], 'Province');
        fillSelect(citySel, [], 'Municipality / City');
        fillSelect(brgySel, [], 'Barangay');
        updateAddress();
        try {
            await loadProvinces(regionSel.value);
            if (regionSel.value && !hasProvinces) await loadCities(regionSel.value, '');
        } catch (e) { showError(e.message); }
        updateAddress();
    });
    provinceSel.addEventListener('change', async () => {
        fillSelect(citySel, [], 'Municipality / City');
        fillSelect(brgySel, [], 'Barangay');
        updateAddress();
        try { await loadCities(regionSel.value, provinceSel.value); } catch (e) { showError(e.message); }
        updateAddress();
    });
    citySel.addEventListener('change', async () => {
        fillSelect(brgySel, [], 'Barangay');
        updateAddress();
        try { await loadBarangays(citySel.value); } catch (e) { showError(e.message); }
        updateAddress();
    });
    brgySel.addEventListener('change', updateAddress);
    purokInput.addEventListener('input', updateAddress);

    function resetAddress() {
        legacyAddress = '';
        hasProvinces = true;
        fillSelect(regionSel, regionsCache || [], 'Region');
        fillSelect(provinceSel, [], 'Province');
        fillSelect(citySel, [], 'Municipality / City');
        fillSelect(brgySel, [], 'Barangay');
        updateAddress();
    }

    // Para sa Edit / View: i-load nang sunud-sunod ang bawat dropdown
    async function prefillAddress(member) {
        legacyAddress = member.region_code ? '' : (member.address || '');
        await loadRegions(member.region_code || '');
        if (member.region_code) {
            await loadProvinces(member.region_code, member.province_code || '');
            await loadCities(member.region_code, member.province_code || '', member.city_code || '');
            if (member.city_code) await loadBarangays(member.city_code, member.barangay_code || '');
        }
        updateAddress();
    }

    // ---------- Farming profile ----------
    function toggleFarmerFields() {
        const type = $('mm_farmer_type').value;
        $('mm_livestock_field').style.display = (type === 'Livestock' || type === 'Both') ? 'block' : 'none';
        $('mm_crops_field').style.display = (type === 'Crops' || type === 'Both') ? 'block' : 'none';
    }
    $('mm_farmer_type').addEventListener('change', toggleFarmerFields);

    // Builds the read-only "picture + label" pills shown in View mode.
    // Falls back to the __default__ image for anything typed into
    // "Others (specify)" that isn't one of the known checkbox options.
    function buildBadgeHtml(selected, othersText, imageMap) {
        const items = [...selected];
        if (othersText) {
            othersText.split(',').map(s => s.trim()).filter(Boolean).forEach(o => items.push(o));
        }
        if (items.length === 0) {
            return '<p style="color:#5a645a; font-size:13px; margin:0;">None specified.</p>';
        }
        const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        return items.map(item => {
            const src = imageMap[item] || imageMap['__default__'];
            return `<div class="mm-crop-card"><img src="${src}" alt="${esc(item)}"><span>${esc(item)}</span></div>`;
        }).join('');
    }

    function resetForm() {
        form.reset();
        memberIdField.value = '';
        membershipIdField.value = 'Auto-generated (e.g. SJFMC-0001)';
        errorsBox.style.display = 'none';
        successBox.style.display = 'none';
        livestockBadges.innerHTML = '';
        cropsBadges.innerHTML = '';
        formBody.scrollTop = 0; // always start at the top of the form
        resetAddress();
        toggleFarmerFields();
    }

    function setMode(mode) {
        // mode: 'add' | 'edit' | 'view'
        const readOnly = mode === 'view';
        addrLocked = readOnly;
        allFormControls().forEach(el => {
            if (el === submitBtn) return;
            if (el.id === 'mm_membership_id') return; // always disabled
            el.disabled = readOnly;
        });
        refreshAddrDisabled();
        submitBtn.style.display = readOnly ? 'none' : 'inline-flex';

        // View mode: show picture badges instead of the checkbox grid
        livestockCheckboxes.style.display = readOnly ? 'none' : 'flex';
        livestockOthersInput.style.display = readOnly ? 'none' : 'block';
        livestockBadges.style.display = readOnly ? 'flex' : 'none';
        cropsCheckboxes.style.display = readOnly ? 'none' : 'flex';
        cropsOthersInput.style.display = readOnly ? 'none' : 'block';
        cropsBadges.style.display = readOnly ? 'flex' : 'none';

        if (mode === 'add') {
            modalTitle.textContent = 'Add Member';
            modalSubtext.textContent = 'Create a new cooperative member profile. Membership ID is auto-generated.';
            submitBtn.textContent = 'Register Member';
        } else if (mode === 'edit') {
            modalTitle.textContent = 'Edit Member';
            modalSubtext.textContent = 'Update this cooperative member\'s profile.';
            submitBtn.textContent = 'Update Member';
        } else {
            modalTitle.textContent = 'Member Details';
            modalSubtext.textContent = 'Viewing this member\'s profile (read-only).';
        }
        updateAddress();
    }

    function openModal() { modalBackdrop.style.display = 'flex'; }
    function closeModal() { modalBackdrop.style.display = 'none'; resetForm(); }

    $('mmAddBtn').addEventListener('click', async function() {
        resetForm();
        setMode('add');
        openModal();
        try {
            await loadRegions('');
        } catch (e) { showError(e.message); }
    });
    cancelBtn.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', function(e) { if (e.target === modalBackdrop) closeModal(); });

    function populateForm(member) {
        memberIdField.value = member.id;
        membershipIdField.value = member.membership_id;
        $('mm_last_name').value = member.last_name || '';
        $('mm_first_name').value = member.first_name || '';
        $('mm_middle_name').value = member.middle_name || '';
        $('mm_gender').value = member.gender || '';
        $('mm_date_of_birth').value = member.date_of_birth || '';
        $('mm_occupation').value = member.occupation || '';
        $('mm_purok').value = member.purok || '';
        $('mm_contact_number').value = member.contact_number || '';
        $('mm_membership_type').value = member.membership_type || '';
        $('mm_date_joined').value = member.date_joined || '';
        $('mm_hectares_cultivated').value = member.hectares_cultivated || '';
        $('mm_farmer_type').value = member.farmer_type || '';

        form.querySelectorAll('input[name="livestock[]"]').forEach(cb => {
            cb.checked = (member.livestock_selected || []).includes(cb.value);
        });
        livestockOthersInput.value = member.livestock_others || '';
        livestockBadges.innerHTML = buildBadgeHtml(member.livestock_selected || [], member.livestock_others || '', livestockImageMap);

        form.querySelectorAll('input[name="crops[]"]').forEach(cb => {
            cb.checked = (member.crops_selected || []).includes(cb.value);
        });
        cropsOthersInput.value = member.crops_others || '';
        cropsBadges.innerHTML = buildBadgeHtml(member.crops_selected || [], member.crops_others || '', cropImageMap);

        toggleFarmerFields();
    }

    async function loadMemberIntoModal(memberId, mode) {
        resetForm();
        openModal();
        setMode(mode);
        modalTitle.textContent = mode === 'edit' ? 'Loading edit form...' : 'Loading member...';

        try {
            const res = await fetch(`${API}/get-member.php?member_id=${memberId}`);
            const data = await res.json();
            if (!data.success) {
                showError(data.message || 'Could not load member.');
                return;
            }
            populateForm(data.member);
            try {
                await prefillAddress(data.member);
            } catch (e) {
                showError(e.message);
            }
            setMode(mode);
        } catch (err) {
            showError('Could not connect to the server.');
        }
    }

    $('mmTableBody')?.addEventListener('click', function(e) {
        const link = e.target.closest('[data-action]');
        if (!link) return;
        const row = link.closest('.mm-row');
        const memberId = row.dataset.id;
        const action = link.dataset.action;

        if (action === 'view') loadMemberIntoModal(memberId, 'view');
        if (action === 'edit') loadMemberIntoModal(memberId, 'edit');
        if (action === 'delete') openDeleteModal(memberId, row);
    });

    // ---------- Add / Edit submit ----------
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsBox.style.display = 'none';
        successBox.style.display = 'none';
        updateAddress();
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';

        const isEdit = !!memberIdField.value;
        const endpoint = isEdit ? `${API}/update-member.php` : `${API}/save-member.php`;

        try {
            const formData = new FormData(form);
            const res = await fetch(endpoint, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                successBox.textContent = data.message || 'Saved successfully.';
                successBox.style.display = 'block';
                setTimeout(() => { window.location.reload(); }, 900);
            } else {
                errorsBox.innerHTML = (data.errors || ['Something went wrong.'])
                    .map(s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')).join('<br>');
                errorsBox.style.display = 'block';
                errorsBox.scrollIntoView({ block: 'nearest' });
            }
        } catch (err) {
            showError('Could not connect to the server.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // ---------- Delete confirmation ----------
    const deleteBackdrop = $('mmDeleteBackdrop');
    const deleteText = $('mmDeleteText');
    const deleteErrors = $('mmDeleteErrors');
    const deleteCancelBtn = $('mmDeleteCancelBtn');
    const deleteConfirmBtn = $('mmDeleteConfirmBtn');
    let memberIdToDelete = null;

    function openDeleteModal(memberId, row) {
        memberIdToDelete = memberId;
        const name = row.children[1]?.textContent?.trim() || 'this member';
        deleteText.textContent = `Are you sure you want to delete ${name}'s profile? This cannot be undone.`;
        deleteErrors.style.display = 'none';
        deleteBackdrop.style.display = 'flex';
    }
    function closeDeleteModal() {
        deleteBackdrop.style.display = 'none';
        memberIdToDelete = null;
    }
    deleteCancelBtn.addEventListener('click', closeDeleteModal);
    deleteBackdrop.addEventListener('click', function(e) { if (e.target === deleteBackdrop) closeDeleteModal(); });

    deleteConfirmBtn.addEventListener('click', async function() {
        if (!memberIdToDelete) return;
        deleteErrors.style.display = 'none';
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.textContent = 'Deleting...';

        try {
            const formData = new FormData();
            formData.append('member_id', memberIdToDelete);
            const res = await fetch(`${API}/delete-member.php`, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                deleteErrors.textContent = data.message || 'Could not delete this member.';
                deleteErrors.style.display = 'block';
            }
        } catch (err) {
            deleteErrors.textContent = 'Could not connect to the server.';
            deleteErrors.style.display = 'block';
        } finally {
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.textContent = 'Delete';
        }
    });
})();
</script>

<?php renderFooter(); ?>