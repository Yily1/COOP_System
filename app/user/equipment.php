<?php
// app/user/equipment.php - member-facing Equipment Rental page.
// Uses the SAME ajax scripts and equipment.js as the manager page
// (app/manager/equipment/), since those already branch on role
// server-side (e.g. book-equipment.php sets 'pending' vs 'ongoing').
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$equipmentList = getAllEquipment($pdo);
$availableCount = count(array_filter($equipmentList, fn($eq) => $eq['status'] === 'available'));
$myBookings = getMyEquipmentBookings($pdo, $_SESSION['user_id']);

$iconMap = [
    'Corn Sheller'     => 'agriculture',
    'Harvester'        => 'agriculture',
    'Mechanical Dryer' => 'wb_sunny',
];

renderHeader('Equipment Rental');
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
.eq-page, .eq-page *, .eq-modal, .eq-modal * { box-sizing: border-box; }

.eq-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #2B3A2A;
    width: 100%;
    max-width: 100%;
    padding: 28px 32px 60px;
}

.eq-page h1, .eq-page h2 {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: 600;
    color: #2c2c2a;
    margin: 0;
}

.eq-muted { color: #5B6B57; font-size: 14px; margin: 4px 0 0; }
.eq-text-center { text-align: center; }
.eq-text-right { text-align: right; }

.eq-header {
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid #E4DCC8;
}
.eq-header h1 { font-size: 24px; }

.eq-btn {
    box-sizing: border-box;
    display: inline-flex;
    width: auto;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 600;
    padding: 9px 16px;
    border-radius: 10px;
    border: 1.5px solid #33502F;
    background: transparent;
    color: #33502F;
    cursor: pointer;
    transition: transform 0.12s ease, background 0.12s ease;
}
.eq-btn .material-icons { font-size: 17px; flex-shrink: 0; }
.eq-btn:hover { background: #F4E4C1; }
.eq-btn:active { transform: scale(0.97); }
.eq-btn-outline { border-color: #E4DCC8; color: #2B3A2A; background: #fff; }
.eq-btn-outline:hover { border-color: #33502F; }
.eq-btn-primary { background: #33502F; border-color: #33502F; color: #fff; }
.eq-btn-primary:hover { background: #223A20; }
.eq-btn-full { width: 100%; justify-content: center; padding: 11px; }
.eq-btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

.eq-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}
.eq-metric-card { background: #fff; border: 1px solid #E4DCC8; border-radius: 10px; padding: 16px 18px; }
.eq-metric-label { font-size: 12.5px; color: #5B6B57; margin: 0 0 6px; }
.eq-metric-value { font-size: 28px; font-weight: 700; color: #223A20; margin: 0; }

.eq-equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 32px;
}
.eq-card {
    background: #fff;
    border: 1px solid #E4DCC8;
    border-radius: 10px;
    padding: 0 0 16px;
    overflow: hidden;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.eq-card:hover { border-color: #33502F; box-shadow: 0 12px 28px rgba(43, 58, 42, 0.14); }
.eq-card-body { padding: 16px; }
.eq-photo {
    width: 100%; height: 160px; background: #FAF7EF;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.eq-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.eq-photo .material-icons { color: #C1892B; font-size: 40px; }
.eq-name { font-weight: 600; font-size: 15px; margin: 12px 0 2px; }
.eq-price { font-size: 13px; color: #5B6B57; margin: 0 0 12px; }
.eq-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex: none; }
.eq-dot-success { background: #3E7A4B; }
.eq-dot-danger { background: #B54A3C; }
.eq-status-readonly {
    width: auto !important; display: inline-flex !important; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600; background: #fff; border: 1px solid #E4DCC8;
    border-radius: 20px; padding: 4px 10px; color: #2B3A2A; font-family: inherit;
}

.eq-section { background: #fff; border: 1px solid #E4DCC8; border-radius: 10px; padding: 22px 22px 8px; }
.eq-section-header {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px; margin-bottom: 18px;
}
.eq-section-header h2 { font-size: 18px; }

.eq-modal {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    display: none; position: fixed; inset: 0; background: rgba(43, 58, 42, 0.45);
    align-items: center; justify-content: center; z-index: 1000; padding: 20px;
}
.eq-modal.eq-open { display: flex; }
.eq-modal-card {
    background: #fff; border-radius: 14px; box-shadow: 0 12px 28px rgba(43, 58, 42, 0.14);
    width: 100%; max-width: 420px; max-height: 90vh; overflow-y: auto; padding: 22px 24px 24px;
}
.eq-modal-card-wide { max-width: 720px; }
.eq-modal-header { display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 16px; margin-bottom: 16px; }
.eq-modal-header h2 { flex: 1; min-width: 0; margin: 0; font-size: 18px; line-height: 1.2; }
.eq-modal-header h2.eq-modal-title-center { text-align: center; }
.eq-icon-btn {
    flex: 0 0 32px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    margin: 0; padding: 0; background: transparent; border: none; color: #5B6B57; cursor: pointer; border-radius: 6px;
}
.eq-icon-btn svg { width: 20px; height: 20px; display: block; }
.eq-icon-btn:hover { background: #FAF7EF; color: #2B3A2A; }

.eq-modal-card label { display: block; font-size: 12.5px; font-weight: 600; color: #5B6B57; margin: 12px 0 5px; }
.eq-modal-card label:first-of-type { margin-top: 0; }
.eq-modal-card input, .eq-modal-card select {
    width: 100%; font-family: inherit; font-size: 14px; padding: 9px 11px;
    border: 1.5px solid #E4DCC8; border-radius: 8px; background: #FAF7EF; color: #2B3A2A; box-sizing: border-box;
}
.eq-modal-card input:focus, .eq-modal-card select:focus { outline: none; border-color: #33502F; background: #fff; }

.eq-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.eq-cost-summary { background: #FAF7EF; border-radius: 8px; padding: 12px 14px; margin: 16px 0; }
.eq-cost-row { display: flex; justify-content: space-between; font-size: 13.5px; color: #5B6B57; padding: 3px 0; }
.eq-cost-row.eq-cost-total {
    color: #223A20; font-weight: 700; font-size: 15px; border-top: 1px solid #E4DCC8; margin-top: 4px; padding-top: 8px;
}

.eq-error-box, .eq-success-box { display: none; font-size: 13px; padding: 9px 12px; border-radius: 8px; margin-bottom: 12px; }
.eq-error-box.eq-show { display: block; background: #F6E1DC; color: #B54A3C; }
.eq-success-box.eq-show { display: block; background: #E1EFDE; color: #3E7A4B; }

.eq-table-wrap { overflow-x: auto; }
.eq-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.eq-page thead th { text-align: left; font-size: 12px; font-weight: 600; color: #5B6B57; padding: 10px 8px; border-bottom: 1.5px solid #E4DCC8; }
.eq-page thead th.eq-text-right { text-align: right; }
.eq-page tbody td { padding: 12px 8px; border-bottom: 1px solid #E4DCC8; }
.eq-page tbody tr:last-child td { border-bottom: none; }

.eq-status-badge { display: inline-block; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
.eq-status-badge.eq-rented { background: #F4E4C1; color: #C1892B; }
.eq-status-badge.eq-overdue { background: #F6E1DC; color: #B54A3C; }
.eq-status-badge.eq-returned { background: #E1EFDE; color: #3E7A4B; }

.eq-cal-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.eq-cal-nav-btn {
    width: auto !important; background: none !important; border: none !important; padding: 6px; border-radius: 6px;
    cursor: pointer; color: #5B6B57; display: inline-flex !important; align-items: center; justify-content: center;
}
.eq-cal-nav-btn:hover { background: #FAF7EF !important; color: #2B3A2A; }
.eq-cal-nav-btn .material-icons { font-size: 20px; }
.eq-cal-month { font-weight: 600; font-size: 16px; }
.eq-cal-weekdays, .eq-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.eq-cal-weekdays div { text-align: center; font-size: 11.5px; color: #5B6B57; font-weight: 600; padding-bottom: 6px; }
.eq-cal-grid .eq-cal-day { min-height: 64px; padding: 4px; border-radius: 6px; background: #FAF7EF; color: #5B6B57; font-size: 11.5px; overflow: hidden; }
.eq-cal-grid .eq-cal-day.eq-empty { background: transparent; }
.eq-cal-grid .eq-cal-day.eq-booked { background: #F4E4C1; }
.eq-cal-day-num { font-size: 11.5px; }
.eq-cal-booking { margin-top: 2px; line-height: 1.2; }
.eq-cal-booking-name { font-size: 10px; font-weight: 600; color: #223A20; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eq-cal-booking-equipment { font-size: 9.5px; color: #5B6B57; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eq-cal-empty-msg { text-align: center; color: #5B6B57; font-size: 13px; padding: 24px 0; }

@media (max-width: 700px) {
    .eq-page table thead { display: none; }
    .eq-page table, .eq-page table tbody { display: block; width: 100%; }
    .eq-page table tr {
        display: flex; flex-wrap: wrap; align-items: baseline;
        column-gap: 8px; row-gap: 4px;
        border-bottom: 1px solid #E4DCC8; padding: 12px 0;
    }
    .eq-page table tbody tr:last-child { border-bottom: none; }
    .eq-page table td { display: block; padding: 0; border-bottom: none !important; }
    .eq-page table td::before { content: none; }

    .eq-cell-equipment { width: 100%; order: 1; font-weight: 600; font-size: 14px; color: #2B3A2A; }
    .eq-cell-qty { order: 2; font-size: 12.5px; color: #5B6B57; }
    .eq-cell-qty::after { content: '\00a0\00b7\00a0'; }
    .eq-cell-start, .eq-cell-return { order: 3; font-size: 12.5px; color: #5B6B57; }
    .eq-cell-start::after { content: '\00a0\2013\00a0'; }
    .eq-cell-status { order: 4; width: auto; margin-top: 2px; }
    .eq-cell-total { order: 5; width: auto; margin-left: auto; margin-top: 2px; font-weight: 700; font-size: 14px; color: #223A20; }
}
@media (max-width: 640px) {
    .eq-cal-grid .eq-cal-day { min-height: 52px; font-size: 10.5px; }
    .eq-cal-booking-name, .eq-cal-booking-equipment { font-size: 9px; }
}
@media (max-width: 560px) {
    .eq-page { padding: 20px 16px 40px; }
    .eq-form-row { grid-template-columns: 1fr; }
    .eq-summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .eq-metric-card { padding: 12px 14px; }
    .eq-metric-label { font-size: 11.5px; }
    .eq-metric-value { font-size: 22px; }
    .eq-equipment-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .eq-photo { height: 100px; }
    .eq-card-body { padding: 12px; }
    .eq-name { font-size: 13px; margin: 8px 0 2px; }
    .eq-price { font-size: 11.5px; margin: 0 0 8px; }
    .eq-status-readonly { font-size: 10.5px; padding: 3px 8px; }
    .eq-section-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        flex-wrap: nowrap;
    }
    .eq-section-header h2 { font-size: 16px; }
    .eq-btn-group { width: auto; gap: 8px; flex-wrap: nowrap; }
    .eq-btn-group .eq-btn {
        width: 38px;
        height: 38px;
        padding: 0;
        border-radius: 50%;
        justify-content: center;
    }
    .eq-btn-group .eq-btn span:not(.material-icons) { display: none; }
    .eq-btn-group .eq-btn .material-icons { font-size: 18px; }
    .eq-modal { padding: 20px 16px; align-items: center; }
    .eq-modal-card { max-width: 92%; max-height: 82vh; width: 92%; margin: 0 auto; padding: 14px 14px 14px; border-radius: 12px; }
    .eq-modal-header { margin-bottom: 10px; }
    .eq-modal-header h2 { font-size: 16px; }
    .eq-modal-card label { font-size: 11.5px; margin: 8px 0 4px; }
    .eq-modal-card label:first-of-type { margin-top: 0; }
    .eq-modal-card input, .eq-modal-card select { font-size: 13px; padding: 7px 9px; }
    .eq-success-box, .eq-error-box { font-size: 12px; padding: 7px 10px; margin-bottom: 8px !important; }
    .eq-form-row { gap: 8px; }
    .eq-cost-summary { padding: 9px 11px; margin: 10px 0; }
    .eq-cost-row { font-size: 12.5px; padding: 2px 0; }
    .eq-cost-row.eq-cost-total { font-size: 13.5px; }
    #submit-btn { padding: 9px; font-size: 13px; margin-top: 2px; }
}
</style>

<div class="eq-page">

    <div class="eq-header">
        <h1>Equipment rental</h1>
        <p class="eq-muted">Browse and book coop equipment</p>
    </div>

    <div class="eq-summary-grid">
        <div class="eq-metric-card">
            <p class="eq-metric-label">Available now</p>
            <p class="eq-metric-value"><?php echo $availableCount; ?> of <?php echo count($equipmentList); ?></p>
        </div>
        <div class="eq-metric-card">
            <p class="eq-metric-label">Your active bookings</p>
            <p class="eq-metric-value"><?php echo count($myBookings); ?></p>
        </div>
    </div>

    <div class="eq-equipment-grid">
        <?php foreach ($equipmentList as $eq): ?>
            <?php
                $isAvailable = $eq['status'] === 'available';
                $icon = $iconMap[$eq['name']] ?? 'build';
                $unitLabel = equipmentUnitLabel($eq['unit_type']);
                $hasPhoto = !empty($eq['photo_path']);
                $photoUrl = $hasPhoto ? BASE_URL . '/' . ltrim($eq['photo_path'], '/') : '';
            ?>
            <div class="eq-card" data-status="<?php echo $eq['status']; ?>">
                <div class="eq-photo">
                    <?php if ($hasPhoto): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($eq['name']); ?>">
                    <?php else: ?>
                        <span class="material-icons"><?php echo $icon; ?></span>
                    <?php endif; ?>
                </div>
                <div class="eq-card-body">
                    <p class="eq-name"><?php echo htmlspecialchars($eq['name']); ?></p>
                    <p class="eq-price">₱<?php echo number_format($eq['rate_member'], 0); ?> per <?php echo htmlspecialchars($unitLabel); ?></p>
                    <div class="eq-status-readonly">
                        <span class="eq-dot <?php echo $isAvailable ? 'eq-dot-success' : 'eq-dot-danger'; ?>"></span>
                        <span><?php echo $isAvailable ? 'Available' : 'Not available'; ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="eq-section">
        <div class="eq-section-header">
            <h2>My bookings</h2>
            <div class="eq-btn-group">
                <button class="eq-btn eq-btn-outline" id="cal-toggle" type="button" aria-label="View schedule" title="View schedule">
                    <span class="material-icons">calendar_today</span>
                    <span>View schedule</span>
                </button>
                <button class="eq-btn eq-btn-primary" id="book-btn" type="button" aria-label="Book equipment" title="Book equipment">
                    <span class="material-icons">add</span> <span>Book equipment</span>
                </button>
            </div>
        </div>

        <div class="eq-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Qty</th>
                        <th>Start</th>
                        <th>Return</th>
                        <th>Status</th>
                        <th class="eq-text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myBookings)): ?>
                        <tr><td colspan="6" class="eq-muted eq-text-center">You have no active bookings.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($myBookings as $b): ?>
                        <tr>
                            <td data-label="Equipment" class="eq-cell-equipment"><?php echo htmlspecialchars($b['equipment_name']); ?></td>
                            <td data-label="Qty" class="eq-cell-qty"><?php echo htmlspecialchars(rtrim(rtrim(number_format($b['quantity'], 2), '0'), '.')); ?></td>
                            <td data-label="Start" class="eq-cell-start"><?php echo date('M j', strtotime($b['start_date'])); ?></td>
                            <td data-label="Return" class="eq-cell-return"><?php echo date('M j', strtotime($b['end_date'])); ?></td>
                            <td data-label="Status" class="eq-cell-status"><?php echo equipmentStatusBadge($b['status']); ?></td>
                            <td data-label="Total" class="eq-text-right eq-cell-total">₱<?php echo number_format($b['total_cost'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


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

        <div class="eq-success-box eq-show" style="margin-bottom:14px;">
            Your booking will be sent to a manager for approval before it becomes active.
        </div>

        <div id="booking-error" class="eq-error-box"></div>
        <div id="booking-success" class="eq-success-box"></div>

        <form id="booking-form">
            <!-- Portal users are always coop members, so this is fixed
                 rather than a dropdown (book-equipment.php still requires
                 membership_type in the POST body). -->
            <input type="hidden" name="membership_type" value="member">

            <label>Equipment</label>
            <select id="book-equipment" name="equipment_id" required>
                <?php foreach ($equipmentList as $eq): ?>
                    <?php if ($eq['status'] === 'available'): ?>
                        <?php $optUnit = equipmentUnitLabel($eq['unit_type']); ?>
                        <option value="<?php echo $eq['id']; ?>"
                                data-rate-member="<?php echo $eq['rate_member']; ?>"
                                data-rate-nonmember="<?php echo $eq['rate_member']; ?>"
                                data-unit="<?php echo htmlspecialchars($eq['unit_type']); ?>"
                                data-unit-label="<?php echo htmlspecialchars($optUnit); ?>">
                            <?php echo htmlspecialchars($eq['name']); ?> — ₱<?php echo number_format($eq['rate_member'], 0); ?>/<?php echo htmlspecialchars($optUnit); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
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
                   value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>"
                   placeholder="Juan Dela Cruz" required>

            <div class="eq-cost-summary">
                <div class="eq-cost-row"><span id="qty-summary-label">Quantity</span><span id="sum-days">0</span></div>
                <div class="eq-cost-row eq-cost-total"><span>Total cost</span><span id="sum-total">₱0</span></div>
            </div>

            <button type="submit" class="eq-btn eq-btn-primary eq-btn-full" id="submit-btn">Submit booking</button>
        </form>
    </div>
</div>


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


<script>
    window.EQUIPMENT_AJAX_BASE = "<?php echo BASE_URL; ?>/app/manager/equipment/ajax/";
</script>

<script src="<?php echo BASE_URL; ?>/app/manager/equipment/equipment.js"></script>

<?php renderFooter(); ?>