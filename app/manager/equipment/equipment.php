<?php
// Adjust this path if your config.php (session_start, BASE_URL, $pdo) lives elsewhere
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireLogin();

// Only manager and user roles can view this page
if (!canAccessEquipment($_SESSION['role'] ?? '')) {
    die("Access denied.");
}

$equipmentList    = getAllEquipment($pdo);
$totalEquipment   = getTotalEquipmentCount($pdo);
$rentedThisMonth  = getRentedThisMonthCount($pdo);
$currentBookings  = getCurrentEquipmentBookings($pdo);
$monthlyRentCounts = getMonthlyRentCountByEquipment($pdo);

$iconMap = [
    'Corn sheller'     => 'agriculture',
    'Harvester'        => 'agriculture',
    'Mechanical dryer' => 'wb_sunny',
];

renderHeader('Equipment Rental');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --eq-ink: #2B3A2A;
    --eq-ink-soft: #5B6B57;
    --eq-paper: #FAF7EF;
    --eq-card: #FFFFFF;
    --eq-line: #E4DCC8;
    --eq-forest: #33502F;
    --eq-forest-dark: #223A20;
    --eq-gold: #C1892B;
    --eq-gold-soft: #F4E4C1;
    --eq-danger: #B54A3C;
    --eq-danger-soft: #F6E1DC;
    --eq-success: #3E7A4B;
    --eq-success-soft: #E1EFDE;
    --eq-radius: 10px;
    --eq-shadow: 0 12px 28px rgba(43, 58, 42, 0.14);
}

.eq-page,
.eq-page *,
.eq-modal,
.eq-modal * {
    box-sizing: border-box;
}

.eq-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--eq-ink);
    width: 100%;
    max-width: 100%;
    padding: 28px 32px 60px;
    text-transform: none;
    letter-spacing: normal;
}

.eq-page h1,
.eq-page h2 {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: 600;
    color: #2c2c2a;
    text-transform: none;
    letter-spacing: normal;
    margin: 0;
}

.eq-muted { color: var(--eq-ink-soft); font-size: 14px; margin: 4px 0 0; }
.eq-text-center { text-align: center; }
.eq-text-right { text-align: right; }

/* =========================
   HEADER
   ========================= */

.eq-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--eq-line);
}

.eq-header h1 { font-size: 26px; }
.eq-header > div:first-child { flex: 1; min-width: 0; }

/* =========================
   BUTTONS
   ========================= */

.eq-btn {
    box-sizing: border-box;
    display: inline-flex;
    flex: none;
    width: auto;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1.2;
    text-transform: none;
    letter-spacing: normal;
    padding: 9px 16px;
    border-radius: var(--eq-radius);
    border: 1.5px solid var(--eq-forest);
    background: transparent;
    color: var(--eq-forest);
    cursor: pointer;
    box-shadow: none;
    transition: transform 0.12s ease, background 0.12s ease;
}

.eq-btn .material-icons { font-size: 17px; }
.eq-btn:hover { background: var(--eq-gold-soft); }
.eq-btn:active { transform: scale(0.97); }

.eq-btn-outline {
    border-color: var(--eq-line);
    color: var(--eq-ink);
    background: var(--eq-card);
}
.eq-btn-outline:hover { border-color: var(--eq-forest); }

.eq-btn-primary {
    background: var(--eq-forest);
    border-color: var(--eq-forest);
    color: #fff;
}
.eq-btn-primary:hover { background: var(--eq-forest-dark); }

.eq-btn-full { width: 100%; justify-content: center; padding: 11px; }
.eq-btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

/* Small pill-style button for the "Edit" action on each card.
   !important overrides used here because the site-wide style.css
   applies `width: 100%` and `text-transform: uppercase` to all
   <button> elements, which otherwise wins over our unqualified
   .eq-btn-link rules and stretches/uppercases these pills. */
.eq-btn-link {
    width: auto !important;
    background: var(--eq-card);
    border: 1px solid var(--eq-line);
    border-radius: 20px;
    box-shadow: none;
    padding: 4px 10px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    text-transform: none !important;
    letter-spacing: normal;
    color: var(--eq-forest);
    cursor: pointer;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    transition: border-color 0.12s ease;
}
.eq-btn-link .material-icons { font-size: 14px; }
.eq-btn-link:hover { border-color: var(--eq-forest); text-decoration: none; }

.eq-btn-link:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Reject pill uses the danger palette instead of the default forest green */
.eq-btn-link-danger {
    color: var(--eq-danger);
}
.eq-btn-link-danger:hover { border-color: var(--eq-danger); }

/* =========================
   SUMMARY CARDS
   ========================= */

.eq-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}

.eq-metric-card {
    border: none;
    border-radius: var(--eq-radius);
    box-shadow: none;
    padding: 16px 18px;
}

.eq-metric-label {
    font-size: 12.5px;
    text-transform: none;
    letter-spacing: normal;
    margin: 0 0 6px;
}

.eq-metric-value {
    font-family: 'Fraunces', serif;
    font-size: 30px;
    font-weight: 600;
    margin: 0;
}

.eq-summary-grid .eq-metric-card:nth-child(5n+1) { background: var(--eq-forest); }
.eq-summary-grid .eq-metric-card:nth-child(5n+1) .eq-metric-label { color: #EFE9D8; }
.eq-summary-grid .eq-metric-card:nth-child(5n+1) .eq-metric-value { color: #fff; }

.eq-summary-grid .eq-metric-card:nth-child(5n+2) { background: var(--eq-gold); }
.eq-summary-grid .eq-metric-card:nth-child(5n+2) .eq-metric-label { color: #4A340E; }
.eq-summary-grid .eq-metric-card:nth-child(5n+2) .eq-metric-value { color: #3A2708; }

.eq-summary-grid .eq-metric-card:nth-child(5n+3) { background: var(--eq-success-soft); }
.eq-summary-grid .eq-metric-card:nth-child(5n+3) .eq-metric-label,
.eq-summary-grid .eq-metric-card:nth-child(5n+3) .eq-metric-value { color: var(--eq-success); }

.eq-summary-grid .eq-metric-card:nth-child(5n+4) { background: var(--eq-danger-soft); }
.eq-summary-grid .eq-metric-card:nth-child(5n+4) .eq-metric-label,
.eq-summary-grid .eq-metric-card:nth-child(5n+4) .eq-metric-value { color: var(--eq-danger); }

.eq-summary-grid .eq-metric-card:nth-child(5n+5) { background: var(--eq-gold-soft); }
.eq-summary-grid .eq-metric-card:nth-child(5n+5) .eq-metric-label,
.eq-summary-grid .eq-metric-card:nth-child(5n+5) .eq-metric-value { color: var(--eq-gold); }
/* =========================
   EQUIPMENT CARDS
   ========================= */

.eq-equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 32px;
}

.eq-card {
    background: var(--eq-card);
    border: 1px solid var(--eq-line);
    border-radius: var(--eq-radius);
    box-shadow: none;
    padding: 0 0 16px;
    overflow: hidden;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.eq-card:hover { border-color: var(--eq-forest); box-shadow: var(--eq-shadow); }

.eq-card-body { padding: 16px; }

.eq-photo {
    width: 100%;
    height: 180px;
    background: var(--eq-paper);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.eq-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.eq-photo .material-icons { color: var(--eq-gold); font-size: 40px; }

.eq-name { font-weight: 600; font-size: 15px; margin: 12px 0 2px; text-transform: none; }
.eq-price { font-size: 13px; color: var(--eq-ink-soft); margin: 0 0 12px; }

.eq-card-footer {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}
.eq-card-footer > * { width: auto !important; }

/* =========================
   STATUS
   ========================= */

.eq-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex: none; }
.eq-dot-success { background: var(--eq-success); }
.eq-dot-danger { background: var(--eq-danger); }

.eq-status-btn,
.eq-status-readonly {
    width: auto !important;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: none !important;
    letter-spacing: normal;
    background: var(--eq-card);
    border-radius: 20px;
    box-shadow: none;
    padding: 4px 10px;
    color: var(--eq-ink);
    cursor: pointer;
    font-family: inherit;
}
.eq-status-btn { border: 1px solid var(--eq-line); transition: border-color 0.12s ease; }
.eq-status-readonly { border: 1px solid var(--eq-line); cursor: default; }
.eq-status-btn:hover { border-color: var(--eq-forest); }
.eq-status-btn:hover .eq-status-label { text-decoration: none; }

/* =========================
   SECTION
   ========================= */

.eq-section {
    background: var(--eq-card);
    border: 1px solid var(--eq-line);
    border-radius: var(--eq-radius);
    box-shadow: none;
    padding: 22px 22px 8px;
}

.eq-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.eq-section-header h2 { font-size: 19px; }

/* =========================
   CALENDAR (inside a modal now, not an in-page panel)
   ========================= */

.eq-cal-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.eq-cal-nav-btn {
    width: auto !important;
    background: none !important;
    border: none !important;
    box-shadow: none !important;
    padding: 6px;
    border-radius: 6px;
    cursor: pointer;
    color: var(--eq-ink-soft);
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}
.eq-cal-nav-btn:hover { background: var(--eq-paper) !important; color: var(--eq-ink); }
.eq-cal-nav-btn .material-icons { font-size: 20px; }

.eq-cal-month { font-family: 'Fraunces', serif; font-weight: 600; font-size: 16px; }

.eq-cal-weekdays,
.eq-cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.eq-cal-weekdays div {
    text-align: center;
    font-size: 11.5px;
    color: var(--eq-ink-soft);
    font-weight: 600;
    padding-bottom: 6px;
}

.eq-cal-grid .eq-cal-day {
    min-height: 64px;
    padding: 4px;
    border-radius: 6px;
    background: var(--eq-paper);
    color: var(--eq-ink-soft);
    font-size: 11.5px;
    overflow: hidden;
}
.eq-cal-grid .eq-cal-day.eq-empty { background: transparent; }
.eq-cal-grid .eq-cal-day.eq-booked { background: var(--eq-gold-soft); }

.eq-cal-day-num { font-size: 11.5px; }

.eq-cal-booking {
    margin-top: 2px;
    line-height: 1.2;
}
.eq-cal-booking-name {
    font-size: 10px;
    font-weight: 600;
    color: var(--eq-forest-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.eq-cal-booking-equipment {
    font-size: 9.5px;
    color: var(--eq-ink-soft);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.eq-cal-empty-msg {
    text-align: center;
    color: var(--eq-ink-soft);
    font-size: 13px;
    padding: 24px 0;
}

@media (max-width: 640px) {
    .eq-cal-grid .eq-cal-day { min-height: 52px; font-size: 10.5px; }
    .eq-cal-booking-name, .eq-cal-booking-equipment { font-size: 9px; }
}

/* =========================
   MODALS
   ========================= */

.eq-modal {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(43, 58, 42, 0.45);
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}
.eq-modal.eq-open { display: flex; }

.eq-modal-card {
    background: var(--eq-card);
    border-radius: 14px;
    box-shadow: var(--eq-shadow);
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 22px 24px 24px;
    animation: eq-pop-in 0.16s ease;
}

.eq-modal-card-wide { max-width: 720px; }

@keyframes eq-pop-in {
    from { opacity: 0; transform: translateY(8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Fixed flex header: X button keeps a locked 32x32 box so a two-line
   title never pushes or shrinks it (see .eq-icon-btn below). */
.eq-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 16px;
    margin-bottom: 16px;
}
.eq-modal-header h2 { flex: 1; min-width: 0; margin: 0; font-size: 18px; line-height: 1.2; }

/* Used only by the Schedule modal's title so "Book/Add/Edit
   equipment" headers elsewhere stay left-aligned as before. */
.eq-modal-header h2.eq-modal-title-center { text-align: center; }

.eq-icon-btn {
    flex: 0 0 32px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
    box-shadow: none;
    color: var(--eq-ink-soft);
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.eq-icon-btn svg { width: 20px; height: 20px; display: block; }
.eq-icon-btn:hover { background: var(--eq-paper); color: var(--eq-ink); }
.eq-icon-btn:active { transform: scale(0.94); }

/* =========================
   MODAL FORM
   ========================= */

.eq-modal-card label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--eq-ink-soft);
    margin: 12px 0 5px;
}
.eq-modal-card label:first-of-type { margin-top: 0; }

.eq-modal-card input,
.eq-modal-card select {
    width: 100%;
    font-family: inherit;
    font-size: 14px;
    padding: 9px 11px;
    border: 1.5px solid var(--eq-line);
    border-radius: 8px;
    background: var(--eq-paper);
    color: var(--eq-ink);
    box-shadow: none;
    box-sizing: border-box;
}
.eq-modal-card input:focus,
.eq-modal-card select:focus {
    outline: none;
    border-color: var(--eq-forest);
    background: #fff;
}

/* =========================
   PHOTO UPLOAD
   ========================= */

.eq-photo-drop {
    border: 1.5px dashed var(--eq-line);
    border-radius: 8px;
    padding: 18px 12px;
    text-align: center;
    cursor: pointer;
    background: var(--eq-paper);
    transition: border-color 0.12s ease;
}
.eq-photo-drop:hover { border-color: var(--eq-forest); }
.eq-photo-drop .material-icons { color: var(--eq-ink-soft); font-size: 22px; }
.eq-photo-drop p { font-size: 12.5px; color: var(--eq-ink-soft); margin: 6px 0 0; }
.eq-photo-drop input[type="file"] { display: none; }

.eq-photo-preview {
    display: none;
    width: 100%;
    height: 110px;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 12px;
}
.eq-photo-preview.eq-show { display: block; }
.eq-photo-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* =========================
   FORM ROW / COST SUMMARY
   ========================= */

.eq-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.eq-cost-summary {
    background: var(--eq-paper);
    border-radius: 8px;
    padding: 12px 14px;
    margin: 16px 0;
}
.eq-cost-row {
    display: flex;
    justify-content: space-between;
    font-size: 13.5px;
    color: var(--eq-ink-soft);
    padding: 3px 0;
}
.eq-cost-row.eq-cost-total {
    color: var(--eq-forest-dark);
    font-weight: 700;
    font-size: 15px;
    border-top: 1px solid var(--eq-line);
    margin-top: 4px;
    padding-top: 8px;
}

/* =========================
   MESSAGES
   ========================= */

.eq-error-box,
.eq-success-box {
    display: none;
    font-size: 13px;
    padding: 9px 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}
.eq-error-box.eq-show { display: block; background: var(--eq-danger-soft); color: var(--eq-danger); }
.eq-success-box.eq-show { display: block; background: var(--eq-success-soft); color: var(--eq-success); }

/* =========================
   TABLE
   ========================= */

.eq-table-wrap { overflow-x: auto; }
.eq-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }

.eq-page thead th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--eq-ink-soft);
    padding: 10px 8px;
    border-bottom: 1.5px solid var(--eq-line);
}
.eq-page thead th.eq-text-right { text-align: right; }
.eq-page tbody td { padding: 12px 8px; border-bottom: 1px solid var(--eq-line); }
.eq-page tbody tr:last-child td { border-bottom: none; }

/* =========================
   STATUS BADGES
   ========================= */

.eq-status-badge {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: none;
    padding: 3px 10px;
    border-radius: 20px;
}
.eq-status-badge.eq-rented { background: var(--eq-gold-soft); color: var(--eq-gold); }
.eq-status-badge.eq-overdue { background: var(--eq-danger-soft); color: var(--eq-danger); }
.eq-status-badge.eq-returned { background: var(--eq-success-soft); color: var(--eq-success); }

/* =========================
   RESPONSIVE
   ========================= */

@media (max-width: 560px) {
    .eq-page { padding: 20px 16px 40px; }
    .eq-header { flex-direction: column; gap: 12px; }
    .eq-header > div:first-child { width: 100%; }
    .eq-header .eq-btn { width: 100%; }
    .eq-form-row { grid-template-columns: 1fr; }
    .eq-section-header { flex-direction: column; align-items: flex-start; }
    .eq-btn-group { width: 100%; }
    .eq-btn-group .eq-btn { flex: 1; justify-content: center; }
    .eq-modal { padding: 12px; }
    .eq-modal-card { max-width: 100%; max-height: 92vh; padding: 20px 18px 20px; }
    .eq-modal-header { gap: 10px; }
    .eq-modal-header h2 { font-size: 17px; }
    .eq-icon-btn { flex: 0 0 32px; width: 32px; height: 32px; }
}
</style>

<div class="eq-page">

    <!-- PAGE HEADER -->
    <div class="eq-header">
        <div>
            <h1>Equipment rental</h1>
            <p class="eq-muted">Manage rentable coop equipment and bookings</p>
        </div>

        <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
            <button class="eq-btn eq-btn-outline" id="add-equipment-btn" type="button">
                <span class="material-icons">add</span> Add equipment
            </button>
        <?php endif; ?>
    </div>

    <!-- SUMMARY -->
    <div class="eq-summary-grid">
        <div class="eq-metric-card">
            <p class="eq-metric-label">Total equipment</p>
            <p class="eq-metric-value"><?php echo $totalEquipment; ?></p>
        </div>
        <div class="eq-metric-card">
            <p class="eq-metric-label">Rented this month</p>
            <p class="eq-metric-value"><?php echo $rentedThisMonth; ?></p>
        </div>
        <?php foreach ($equipmentList as $eq): ?>
            <div class="eq-metric-card">
                <p class="eq-metric-label"><?php echo htmlspecialchars($eq['name']); ?></p>
                <p class="eq-metric-value"><?php echo $monthlyRentCounts[$eq['id']] ?? 0; ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- EQUIPMENT CARDS -->
    <div class="eq-equipment-grid">
        <?php foreach ($equipmentList as $eq): ?>
            <?php
                $isAvailable = $eq['status'] === 'available';
                $icon = $iconMap[$eq['name']] ?? 'build';
                $unitLabel = equipmentUnitLabel($eq['unit_type']);
                $hasPhoto = !empty($eq['photo_path']);
                $photoUrl = $hasPhoto ? BASE_URL . '/' . ltrim($eq['photo_path'], '/') : '';
            ?>
            <div class="eq-card"
                 data-equipment-id="<?php echo $eq['id']; ?>"
                 data-status="<?php echo $eq['status']; ?>"
                 data-name="<?php echo htmlspecialchars($eq['name']); ?>"
                 data-rate-member="<?php echo $eq['rate_member']; ?>"
                 data-rate-nonmember="<?php echo $eq['rate_nonmember']; ?>"
                 data-unit-type="<?php echo htmlspecialchars($eq['unit_type']); ?>"
                 data-photo-url="<?php echo htmlspecialchars($photoUrl); ?>">

                <div class="eq-photo">
                    <?php if ($hasPhoto): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($eq['name']); ?>">
                    <?php else: ?>
                        <span class="material-icons"><?php echo $icon; ?></span>
                    <?php endif; ?>
                </div>

                <div class="eq-card-body">
                    <p class="eq-name"><?php echo htmlspecialchars($eq['name']); ?></p>
                    <p class="eq-price">₱<?php echo number_format($eq['rate_member'], 0); ?> member / ₱<?php echo number_format($eq['rate_nonmember'], 0); ?> non-member <br>per <?php echo htmlspecialchars($unitLabel); ?></p>

                    <div class="eq-card-footer">
                        <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                            <button class="eq-status-btn" data-id="<?php echo $eq['id']; ?>" type="button">
                                <span class="eq-dot <?php echo $isAvailable ? 'eq-dot-success' : 'eq-dot-danger'; ?>"></span>
                                <span class="eq-status-label"><?php echo $isAvailable ? 'Available' : 'Not available'; ?></span>
                            </button>

                            <button class="eq-btn-link eq-edit-btn" type="button">
                                <span class="material-icons">edit</span> Edit
                            </button>
                        <?php else: ?>
                            <div class="eq-status-readonly">
                                <span class="eq-dot <?php echo $isAvailable ? 'eq-dot-success' : 'eq-dot-danger'; ?>"></span>
                                <span><?php echo $isAvailable ? 'Available' : 'Not available'; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- CURRENTLY RENTED -->
    <div class="eq-section">
        <div class="eq-section-header">
            <h2>Currently rented</h2>
            <div class="eq-btn-group">
                <button class="eq-btn eq-btn-primary" id="book-btn" type="button">
                    <span class="material-icons">add</span> Book equipment
                </button>
                <button class="eq-btn eq-btn-outline" id="cal-toggle" type="button">
                    <span class="material-icons">calendar_today</span>
                    View schedule
                </button>
            </div>
        </div>

        <div class="eq-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Renter</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Start</th>
                        <th>Return</th>
                        <th>Status</th>
                        <th class="eq-text-right">Total</th>
                        <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                            <th class="eq-text-right">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($currentBookings)): ?>
                        <tr><td colspan="9" class="eq-muted eq-text-center">No active bookings.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($currentBookings as $b): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['equipment_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['renter_name']); ?></td>
                            <td><?php echo ($b['membership_type'] ?? 'member') === 'nonmember' ? 'Non-member' : 'Member'; ?></td>
                            <td><?php echo htmlspecialchars(rtrim(rtrim(number_format($b['quantity'], 2), '0'), '.')); ?></td>
                            <td><?php echo date('M j', strtotime($b['start_date'])); ?></td>
                            <td><?php echo date('M j', strtotime($b['end_date'])); ?></td>
                            <td><?php echo equipmentStatusBadge($b['status']); ?></td>
                            <td class="eq-text-right">₱<?php echo number_format($b['total_cost'], 0); ?></td>
                            <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                                <td class="eq-text-right">
                                    <?php if ($b['status'] === 'pending'): ?>
                                        <button class="eq-btn-link eq-approve-btn" data-id="<?php echo $b['id']; ?>" type="button">
                                            <span class="material-icons">check</span> Approve
                                        </button>
                                        <button class="eq-btn-link eq-btn-link-danger eq-reject-btn" data-id="<?php echo $b['id']; ?>" type="button">
                                            <span class="material-icons">close</span> Reject
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<!-- ============================================================
     BOOKING MODAL
     ============================================================ -->
<div class="eq-modal" id="booking-modal-wrap">
    <div class="eq-modal-card">
        <div class="eq-modal-header">
            <h2>Book equipment</h2>
            <button class="eq-icon-btn" id="close-book" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <?php if (($_SESSION['role'] ?? '') === 'user'): ?>
            <div class="eq-success-box eq-show" style="margin-bottom:14px;">
                Your booking will be sent to a manager for approval before it becomes active.
            </div>
        <?php endif; ?>

        <div id="booking-error" class="eq-error-box"></div>
        <div id="booking-success" class="eq-success-box"></div>

        <form id="booking-form">
            <label>Equipment</label>
            <select id="book-equipment" name="equipment_id" required>
                <?php foreach ($equipmentList as $eq): ?>
                    <?php if ($eq['status'] === 'available'): ?>
                        <?php $optUnit = equipmentUnitLabel($eq['unit_type']); ?>
                        <option value="<?php echo $eq['id']; ?>"
                                data-rate-member="<?php echo $eq['rate_member']; ?>"
                                data-rate-nonmember="<?php echo $eq['rate_nonmember']; ?>"
                                data-unit="<?php echo htmlspecialchars($eq['unit_type']); ?>"
                                data-unit-label="<?php echo htmlspecialchars($optUnit); ?>">
                            <?php echo htmlspecialchars($eq['name']); ?> — ₱<?php echo number_format($eq['rate_member'], 0); ?> member / ₱<?php echo number_format($eq['rate_nonmember'], 0); ?> non-member per <?php echo htmlspecialchars($optUnit); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <label>Renter type</label>
            <select id="renter-type" name="membership_type" required>
                <option value="member">Member</option>
                <option value="nonmember">Non-member</option>
            </select>

            <label id="qty-label">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="0.5" step="0.5" required>

            <div class="eq-form-row">
                <div>
                    <label>Start date</label>
                    <input type="date" id="start-date" name="start_date" required>
                </div>
                <div>
                    <label>End date</label>
                    <input type="date" id="end-date" name="end_date" required>
                </div>
            </div>

            <label>Renter (member name)</label>
            <input type="text" id="renter-name" name="renter_name"
                   value="<?php echo htmlspecialchars(($_SESSION['role'] ?? '') === 'user' ? ($_SESSION['full_name'] ?? '') : ''); ?>"
                   placeholder="Juan Dela Cruz" required>

            <div class="eq-cost-summary">
                <div class="eq-cost-row"><span id="qty-summary-label">Quantity</span><span id="sum-days">0</span></div>
                <div class="eq-cost-row eq-cost-total"><span>Total cost</span><span id="sum-total">₱0</span></div>
            </div>

            <button type="submit" class="eq-btn eq-btn-primary eq-btn-full" id="submit-btn">Submit booking</button>
        </form>
    </div>
</div>


<!-- ============================================================
     SCHEDULE MODAL (calendar view of bookings for a month)
     ============================================================ -->
<div class="eq-modal" id="schedule-modal-wrap">
    <div class="eq-modal-card eq-modal-card-wide">
        <div class="eq-modal-header">
            <h2 class="eq-modal-title-center">Booking Schedule</h2>
            <button class="eq-icon-btn" id="close-schedule" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="eq-cal-nav">
            <button class="eq-cal-nav-btn" id="cal-prev" type="button" aria-label="Previous month">
                <span class="material-icons">chevron_left</span>
            </button>
            <span id="cal-month-label" class="eq-cal-month"></span>
            <button class="eq-cal-nav-btn" id="cal-next" type="button" aria-label="Next month">
                <span class="material-icons">chevron_right</span>
            </button>
        </div>

        <div class="eq-cal-weekdays">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>
        <div class="eq-cal-grid" id="cal-grid"></div>
    </div>
</div>


<?php if (($_SESSION['role'] ?? '') === 'manager'): ?>

<!-- ============================================================
     ADD EQUIPMENT MODAL
     ============================================================ -->
<div class="eq-modal" id="add-equipment-modal-wrap">
    <div class="eq-modal-card">
        <div class="eq-modal-header">
            <h2>Add equipment</h2>
            <button class="eq-icon-btn" id="close-add-equipment" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div id="add-equipment-error" class="eq-error-box"></div>

        <form id="add-equipment-form" enctype="multipart/form-data">
            <label>Photo</label>
            <div class="eq-photo-preview" id="add-photo-preview">
                <img id="add-photo-preview-img" src="" alt="Preview">
            </div>
            <label class="eq-photo-drop" id="add-photo-drop">
                <span class="material-icons">add_a_photo</span>
                <p id="add-photo-drop-text">Click to upload a photo</p>
                <input type="file" id="add-photo-input" name="photo" accept="image/*">
            </label>

            <label>Equipment name</label>
            <input type="text" name="name" placeholder="Rice thresher" required>

            <div class="eq-form-row">
                <div>
                    <label>Member rate (₱)</label>
                    <input type="number" name="rate_member" placeholder="500" min="0" step="1" required>
                </div>
                <div>
                    <label>Non-member rate (₱)</label>
                    <input type="number" name="rate_nonmember" placeholder="600" min="0" step="1" required>
                </div>
            </div>

            <label>Billed per</label>
            <select name="unit_type" required>
                <option value="day">Day</option>
                <option value="hectare">Hectare</option>
                <option value="sack">Sack</option>
            </select>

            <button type="submit" class="eq-btn eq-btn-primary eq-btn-full" style="margin-top:16px;">Save equipment</button>
        </form>
    </div>
</div>


<!-- ============================================================
     EDIT EQUIPMENT MODAL (shared by all cards, populated by JS)
     ============================================================ -->
<div class="eq-modal" id="edit-equipment-modal-wrap">
    <div class="eq-modal-card">
        <div class="eq-modal-header">
            <h2>Edit equipment</h2>
            <button class="eq-icon-btn" id="close-edit-equipment" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div id="edit-equipment-error" class="eq-error-box"></div>

        <form id="edit-equipment-form" enctype="multipart/form-data">
            <input type="hidden" name="equipment_id" id="edit-equipment-id">

            <label>Photo</label>
            <div class="eq-photo-preview" id="edit-photo-preview">
                <img id="edit-photo-preview-img" src="" alt="Preview">
            </div>
            <label class="eq-photo-drop" id="edit-photo-drop">
                <span class="material-icons">add_a_photo</span>
                <p id="edit-photo-drop-text">Click to change photo</p>
                <input type="file" id="edit-photo-input" name="photo" accept="image/*">
            </label>

            <label>Equipment name</label>
            <input type="text" name="name" id="edit-name" required>

            <div class="eq-form-row">
                <div>
                    <label>Member rate (₱)</label>
                    <input type="number" name="rate_member" id="edit-rate-member" min="0" step="1" required>
                </div>
                <div>
                    <label>Non-member rate (₱)</label>
                    <input type="number" name="rate_nonmember" id="edit-rate-nonmember" min="0" step="1" required>
                </div>
            </div>

            <label>Billed per</label>
            <select name="unit_type" id="edit-unit-type" required>
                <option value="day">Day</option>
                <option value="hectare">Hectare</option>
                <option value="sack">Sack</option>
            </select>

            <button type="submit" class="eq-btn eq-btn-primary eq-btn-full" style="margin-top:16px;">Save changes</button>
        </form>
    </div>
</div>

<?php endif; ?>


<script>
    window.EQUIPMENT_AJAX_BASE = "<?php echo BASE_URL; ?>/app/manager/equipment/ajax/";
</script>

<script src="<?php echo BASE_URL; ?>/app/manager/equipment/equipment.js"></script>

<?php renderFooter(); ?>