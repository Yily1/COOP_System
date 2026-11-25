<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');

// Para sa printout (palitan dito kung magbago)
$coopName        = 'San Jose Farmers Multipurpose Cooperative';
$preparedByName  = 'Lielane Marie F. Dagdagan';
$preparedByTitle = 'Cooperative President';

$crops = getAllCrops($pdo);

// Growing > Ready to harvest > Harvested > Rejected. PHP sort, hindi SQL.
// Sa loob ng bawat grupo: pinakabagong na-add (pinakamalaking id) sa taas.
$stageRank = [
    'pending'          => 0,
    'growing'          => 1,
    'ready_to_harvest' => 2,
    'harvested'        => 3,
    'rejected'         => 4,
];
usort($crops, function ($a, $b) use ($stageRank) {
    $ra = $stageRank[$a['status']] ?? 4;
    $rb = $stageRank[$b['status']] ?? 4;

    if ($ra !== $rb) {
        return $ra <=> $rb;
    }

    return ((int) $b['id']) <=> ((int) $a['id']);
});

$stats = getCropStats($pdo);
$cropTypes = getCropTypes($pdo);
$todayManila = cropTodayManila();

$memberStmt = $pdo->query("
    SELECT id, membership_id, first_name, last_name
    FROM members
    ORDER BY last_name, first_name
");
$members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

function cmInitials($lastName, $firstName) {
    $l = trim((string) $lastName);
    $f = trim((string) $firstName);
    if ($l === '' && $f === '') {
        return '?';
    }
    return strtoupper(mb_substr($l, 0, 1) . mb_substr($f, 0, 1));
}

/**
 * Iisang listahan ng avatar color para sa lahat ng page. Puting text ang nakalagay
 * sa ibabaw nito, at lahat ay pasado sa 4.5:1. Pareho ang kulay ng isang member
 * saan mang page (batay sa membership ID).
 * TODO: ilipat sa config/functions.php; may function_exists guard na kaya
 * walang conflict kapag nailipat na.
 */
if (!function_exists('avatarColor')) {
    function avatarColor($key) {
        static $palette = ['#274b81', '#2e7d32', '#8a5a0b', '#6b3fa0', '#993c1d', '#0F6E56'];
        return $palette[abs(crc32((string) $key)) % count($palette)];
    }
}

renderHeader('Crops Management');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    /* Ang mga halaga ay tugma sa shared theme (--c-*). Lahat ng text ay 4.5:1+ sa background nito. */
    --cm-ink: #1b3a24;           /* headings, numbers, body text */
    --cm-ink-soft: #5a645a;      /* secondary text (5.8:1 sa paper) */
    --cm-paper: #FAF7EF;
    --cm-card: #FFFFFF;
    --cm-line: #E4DCC8;          /* decorative divider lang */
    --cm-field-border: #7a857a;  /* border ng inputs (3.6:1+, kailangan ng 3:1) */
    --cm-forest: #3B6D11;        /* brand: primary buttons */
    --cm-forest-dark: #2e5a0d;
    --cm-brand-soft: #EAF3DE;
    --cm-brand-ink: #27500A;     /* text sa ibabaw ng brand-soft (8.2:1) */
    --cm-gold-soft: #F4E4C1;
    --cm-gold: #633806;          /* TEXT sa ibabaw ng gold-soft (8:1) */
    --cm-danger: #a6322f;
    --cm-danger-soft: #F6E1DC;
    --cm-success: #1f5c24;       /* TEXT sa ibabaw ng success-soft (6.7:1) */
    --cm-success-soft: #E1EFDE;
    --cm-info: #274b81;
    --cm-info-soft: #DCEAF2;
    --cm-placeholder: #667066;
    --cm-focus-ring: rgba(59, 109, 17, 0.18);
    --cm-radius: 10px;
    --cm-shadow: 0 12px 28px rgba(43, 58, 42, 0.14);
}

.cm-page,
.cm-page *,
.cm-modal,
.cm-modal * {
    box-sizing: border-box;
}

.cm-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--cm-ink);
    width: 100%;
    max-width: 100%;
    padding: 0 0 40px;
    text-transform: none;
    letter-spacing: normal;
}

.cm-page h1,
.cm-page h2 {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: 600;
    color: var(--cm-ink);
    text-transform: none;
    letter-spacing: normal;
    margin: 0;
}

.cm-muted { color: var(--cm-ink-soft); font-size: 14px; margin: 4px 0 0; }
.cm-text-center { text-align: center; }
.cm-text-right { text-align: right; }

.cm-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: none;
}
.cm-header h1 { font-size: 26px; }
.cm-header > div:first-child { flex: 1; min-width: 0; }
.cm-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: none;
}

.cm-btn {
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
    border-radius: var(--cm-radius);
    border: 1.5px solid var(--cm-forest);
    background: transparent;
    color: var(--cm-forest);
    cursor: pointer;
    box-shadow: none;
    transition: transform 0.12s ease, background 0.12s ease;
}
.cm-btn .material-icons { font-size: 17px; }
.cm-btn:hover { background: var(--cm-gold-soft); }
.cm-btn:active { transform: scale(0.97); }

.cm-btn-outline {
    border-color: var(--cm-line);
    color: var(--cm-ink);
    background: var(--cm-card);
}
.cm-btn-outline:hover { border-color: var(--cm-forest); }

.cm-btn-primary {
    background: var(--cm-forest);
    border-color: var(--cm-forest);
    color: #fff;
}
.cm-btn-primary:hover { background: var(--cm-forest-dark); }
.cm-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.cm-btn-full { width: 100%; justify-content: center; padding: 11px; }

/* Keyboard focus: nakikita sa lahat ng button */
.cm-btn:focus-visible,
.cm-icon-btn:focus-visible {
    outline: 2px solid var(--cm-forest);
    outline-offset: 2px;
}

/* Print at Add planting: kapareho ng laki ng Print at + Add payment sa Payments */
.cm-header-actions .cm-btn {
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    border-width: 1px;
    gap: 6px;
}
.cm-header-actions .cm-btn .material-icons { font-size: 18px; }

.cm-summary-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 28px;
}
.cm-metric-card { border: none; border-radius: var(--cm-radius); box-shadow: none; padding: 16px 18px; flex: 0 0 160px; width: 160px; }
.cm-metric-label { font-size: 12.5px; text-transform: none; letter-spacing: normal; margin: 0 0 6px; }
.cm-metric-value { font-family: 'Fraunces', serif; font-size: 30px; font-weight: 600; margin: 0; }

.cm-metric-card.cm-tone-forest { background: var(--cm-forest); }
.cm-metric-card.cm-tone-forest .cm-metric-label { color: #fff; }
.cm-metric-card.cm-tone-forest .cm-metric-value { color: #fff; }

.cm-metric-card.cm-tone-gold { background: var(--cm-gold-soft); }
.cm-metric-card.cm-tone-gold .cm-metric-label,
.cm-metric-card.cm-tone-gold .cm-metric-value { color: var(--cm-gold); }

.cm-metric-card.cm-tone-info { background: var(--cm-info-soft); }
.cm-metric-card.cm-tone-info .cm-metric-label,
.cm-metric-card.cm-tone-info .cm-metric-value { color: var(--cm-info); }

.cm-metric-card.cm-tone-success { background: var(--cm-success-soft); }
.cm-metric-card.cm-tone-success .cm-metric-label,
.cm-metric-card.cm-tone-success .cm-metric-value { color: var(--cm-success); }

.cm-section {
    background: var(--cm-card);
    border: 1px solid var(--cm-line);
    border-radius: var(--cm-radius);
    box-shadow: none;
    padding: 22px 22px 8px;
}
.cm-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.cm-section-header h2 { font-size: 19px; }

.cm-table-wrap { overflow-x: auto; }
.cm-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.cm-page thead th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--cm-ink-soft);
    padding: 10px 8px;
    border-bottom: 1.5px solid var(--cm-line);
}
.cm-page tbody td { padding: 12px 8px; border-bottom: 1px solid var(--cm-line); }
.cm-page tbody tr:last-child td { border-bottom: none; }

.cm-member-cell { display: flex; align-items: center; gap: 10px; }
.cm-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--cm-forest); /* fallback lang; ang totoong kulay ay galing sa avatarColor() */
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    flex: none;
}
.cm-member-name { margin: 0; font-weight: 600; font-size: 13.5px; color: var(--cm-ink); }
.cm-member-id { margin: 0; font-size: 11.5px; color: var(--cm-ink-soft); }

.cm-status-badge {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: none;
    padding: 3px 10px;
    border-radius: 20px;
}
.cm-status-badge.cm-pending  { background: var(--cm-gold-soft); color: var(--cm-gold); }
.cm-status-badge.cm-rejected { background: var(--cm-danger-soft); color: var(--cm-danger); }
.cm-status-badge.cm-growing  { background: var(--cm-info-soft); color: var(--cm-info); }
.cm-status-badge.cm-ready    { background: var(--cm-gold-soft); color: var(--cm-gold); }
.cm-status-badge.cm-harvested{ background: var(--cm-success-soft); color: var(--cm-success); }

@keyframes cmFlashRow {
    from { background: var(--cm-success-soft); }
    to   { background: transparent; }
}
.cm-page tr.cm-flash td { animation: cmFlashRow 2.2s ease-out; }

.cm-modal {
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
.cm-modal.cm-open { display: flex; }

.cm-modal-card {
    background: var(--cm-card);
    border-radius: 14px;
    box-shadow: var(--cm-shadow);
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 22px 24px 24px;
    animation: cm-pop-in 0.16s ease;
}

@keyframes cm-pop-in {
    from { opacity: 0; transform: translateY(8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.cm-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 16px;
    margin-bottom: 16px;
}
.cm-modal-header h2 { flex: 1; min-width: 0; margin: 0; font-size: 18px; line-height: 1.2; }

.cm-icon-btn {
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
    color: var(--cm-ink-soft);
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.cm-icon-btn svg { width: 20px; height: 20px; display: block; }
.cm-icon-btn:hover { background: var(--cm-paper); color: var(--cm-ink); }
.cm-icon-btn:active { transform: scale(0.94); }

.cm-modal-card label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--cm-ink-soft);
    margin: 12px 0 5px;
}
.cm-modal-card label:first-of-type { margin-top: 0; }

.cm-modal-card input,
.cm-modal-card select {
    width: 100%;
    font-family: inherit;
    font-size: 14px;
    padding: 9px 11px;
    border: 1.5px solid var(--cm-field-border);
    border-radius: 8px;
    background: var(--cm-paper);
    color: var(--cm-ink);
    box-shadow: none;
    box-sizing: border-box;
}
.cm-modal-card input::placeholder { color: var(--cm-placeholder); opacity: 1; }
.cm-modal-card input:focus,
.cm-modal-card select:focus {
    outline: none;
    border-color: var(--cm-forest);
    box-shadow: 0 0 0 3px var(--cm-focus-ring);
    background: #fff;
}

.cm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.cm-crop-info {
    display: none;
    background: var(--cm-brand-soft);
    border-radius: 8px;
    padding: 8px 12px;
    margin-top: 6px;
    font-size: 12.5px;
    color: var(--cm-brand-ink);
    line-height: 1.5;
}
.cm-crop-info.cm-show { display: block; }

.cm-search-wrap { position: relative; }
.cm-search-results {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 5;
    background: var(--cm-card);
    border: 1.5px solid var(--cm-line);
    border-radius: 8px;
    box-shadow: var(--cm-shadow);
    max-height: 210px;
    overflow-y: auto;
}
.cm-search-results.cm-show { display: block; }
.cm-search-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid var(--cm-line);
}
.cm-search-item:last-child { border-bottom: none; }
.cm-search-item:hover,
.cm-search-item.cm-active { background: var(--cm-paper); }
.cm-search-item-name { margin: 0; font-size: 13.5px; font-weight: 600; color: var(--cm-ink); }
.cm-search-item-id { margin: 0; font-size: 12px; color: var(--cm-ink-soft); }
.cm-search-empty { padding: 10px 12px; font-size: 13px; color: var(--cm-ink-soft); }

.cm-error-box,
.cm-success-box {
    display: none;
    font-size: 13px;
    padding: 9px 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}
.cm-error-box.cm-show { display: block; background: var(--cm-danger-soft); color: var(--cm-danger); }
.cm-success-box.cm-show { display: block; background: var(--cm-success-soft); color: var(--cm-success); }

@media (max-width: 560px) {
    .cm-page { padding: 0 0 40px; }
    .cm-header { flex-direction: column; gap: 12px; }
    .cm-header > div:first-child { width: 100%; }
    .cm-header-actions { width: 100%; }
    .cm-header .cm-btn { width: 100%; }
    .cm-form-row { grid-template-columns: 1fr; }
    .cm-section-header { flex-direction: column; align-items: flex-start; }
    .cm-modal { padding: 12px; }
    .cm-modal-card { max-width: 100%; max-height: 92vh; padding: 20px 18px 20px; }
}

/* ============================================================
   PRINT REPORT
   Hidden on screen. Columns: Membership ID, Name, Crop, Location,
   Hectare, Date Planted, Expected Harvest.
   Layout: logo + buong pangalan ng coop sa itaas, "Prepared by"
   (may petsa) sa ibabang bahagi ng page, "Page X of Y" sa footer.
   ============================================================ */
#cmPrintReport { display: none; }

@page {
    size: A4 landscape;
    margin: 14mm 12mm 16mm;
    @top-left   { content: ""; }
    @top-center { content: ""; }
    @top-right  { content: ""; }
    @bottom-left { content: ""; }
    @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        color: #000;
    }
}

@media print {
    .cm-page,
    .cm-modal {
        display: none !important;
    }

    body * { visibility: hidden !important; }
    #cmPrintReport,
    #cmPrintReport * { visibility: visible !important; }

    #cmPrintReport {
        display: block;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        color: #000;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
        -webkit-font-smoothing: auto;
    }

    /* Header: logo + buong pangalan ng coop (walang total) */
    .cm-pr-head {
        display: flex;
        align-items: center;
        padding-bottom: 10px;
        border-bottom: 1.5px solid #000;
    }
    .cm-pr-brand {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .cm-pr-logo {
        width: 84px;
        height: 84px;
        object-fit: contain;
        flex: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cm-pr-title { font-size: 17px; font-weight: 700; line-height: 1.2; }
    .cm-pr-sub { font-size: 12px; color: #333; margin-top: 3px; }

    .cm-pr-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        table-layout: fixed;
        margin-top: 10px;
    }
    .cm-pr-table thead { display: table-header-group; }
    .cm-pr-table th {
        text-align: left;
        padding: 8px 12px;
        border-bottom: 1px solid #000;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .cm-pr-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
        word-wrap: break-word;
        color: #000;
    }
    .cm-pr-table tr { page-break-inside: avoid; }

    /* Column widths (7 columns) */
    .cm-pr-table th:nth-child(1) { width: 14%; } /* Membership ID */
    .cm-pr-table th:nth-child(2) { width: 17%; } /* Name */
    .cm-pr-table th:nth-child(3) { width: 12%; } /* Crop */
    .cm-pr-table th:nth-child(4) { width: 14%; } /* Location */
    .cm-pr-table th:nth-child(5) { width: 10%; } /* Hectare */
    .cm-pr-table th:nth-child(6) { width: 15%; } /* Date Planted */
    .cm-pr-table th:nth-child(7) { width: 18%; } /* Expected Harvest */

    /* "Prepared by": position fixed = inuulit sa BAWAT page, sa ibabang bahagi.
       Ang tfoot spacer ng table ay nag-iiwan ng espasyo sa bawat page para
       hindi tamaan ng mga row ang "Prepared by". */
    .cm-pr-prepared {
        position: fixed;
        left: 0;
        bottom: 0;
        font-size: 12px;
    }
    .cm-pr-table tfoot { display: table-footer-group; }
    .cm-pr-table tfoot td { height: 36mm; padding: 0; border: none; }
    .cm-pr-prepared-label { margin: 0 0 40px; }
    .cm-pr-prepared-name { margin: 0; font-weight: 700; }
    .cm-pr-prepared-title { margin: 2px 0 0; font-size: 12px; }
    .cm-pr-prepared-date { margin: 6px 0 0; font-size: 12px; }
}
</style>

<div class="cm-page">

    <div class="cm-header">
        <div>
            <h1>Crops Records</h1>
        </div>

        <div class="cm-header-actions">
            <button class="cm-btn cm-btn-outline" id="cm-print-btn" type="button">
                <span class="material-icons">print</span> Print
            </button>

            <button class="cm-btn cm-btn-primary" id="mgr-add-planting-btn" type="button">
                <span class="material-icons">add</span> Add planting
            </button>
        </div>
    </div>

    <div class="cm-summary-grid" id="cmSummaryLive">
        <div class="cm-metric-card cm-tone-forest">
            <p class="cm-metric-label">Total plantings</p>
            <p class="cm-metric-value"><?php echo $stats['total']; ?></p>
        </div>
        <div class="cm-metric-card cm-tone-info">
            <p class="cm-metric-label">Growing</p>
            <p class="cm-metric-value"><?php echo $stats['growing']; ?></p>
        </div>
        <div class="cm-metric-card cm-tone-gold">
            <p class="cm-metric-label">Ready to harvest</p>
            <p class="cm-metric-value"><?php echo $stats['ready_to_harvest']; ?></p>
        </div>
        <div class="cm-metric-card cm-tone-success">
            <p class="cm-metric-label">Harvested</p>
            <p class="cm-metric-value"><?php echo $stats['harvested']; ?></p>
        </div>
    </div>

    <div class="cm-section">
        <div class="cm-section-header">
            <h2>All plantings</h2>
        </div>

        <div class="cm-table-wrap" id="cmTableLive">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Crop</th>
                        <th>Location</th>
                        <th>Area (ha)</th>
                        <th>Planted</th>
                        <th>Expected harvest</th>
                        <th>Actual harvest</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($crops)): ?>
                        <tr><td colspan="8" class="cm-muted cm-text-center">No crop plantings yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($crops as $c): ?>
                        <?php $memberName = trim($c['last_name'] . ', ' . $c['first_name']); ?>
                        <tr data-crop-id="<?php echo (int) $c['id']; ?>">
                            <td>
                                <div class="cm-member-cell">
                                    <div class="cm-avatar" style="background: <?php echo avatarColor($c['membership_id']); ?>;"><?php echo htmlspecialchars(cmInitials($c['last_name'], $c['first_name'])); ?></div>
                                    <div>
                                        <p class="cm-member-name"><?php echo htmlspecialchars($memberName); ?></p>
                                        <p class="cm-member-id"><?php echo htmlspecialchars($c['membership_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($c['crop_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['location']); ?></td>
                            <td><?php echo rtrim(rtrim(number_format($c['area_hectares'], 2), '0'), '.'); ?> ha</td>
                            <td><?php echo date('M j, Y', strtotime($c['planting_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($c['expected_harvest_date'])); ?></td>
                            <td><?php echo $c['actual_harvest_date'] ? date('M j, Y', strtotime($c['actual_harvest_date'])) : '—'; ?></td>
                            <td><?php echo cropStatusBadge($c['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<div id="cmPrintReport">
    <div class="cm-pr-head">
        <div class="cm-pr-brand">
            <!-- BAGONG LOGO: palitan ang file sa assets/img/logo.png ng bagong logo.
                 Ang ?v=2 ay para hindi gamitin ng browser ang lumang naka-cache na logo. -->
            <img class="cm-pr-logo" src="<?php echo BASE_URL; ?>/assets/img/logo.png?v=2" alt="San Jose Farmers Multipurpose Cooperative logo">
            <div>
                <div class="cm-pr-title"><?php echo htmlspecialchars($coopName); ?></div>
                <div class="cm-pr-sub">Crops list</div>
            </div>
        </div>
    </div>
    <table class="cm-pr-table">
        <thead>
            <tr>
                <th>Membership ID</th>
                <th>Name</th>
                <th>Crop</th>
                <th>Location</th>
                <th>Hectare</th>
                <th>Date Planted</th>
                <th>Expected Harvest</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($crops as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['membership_id'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars(trim(($c['last_name'] ?? '') . ', ' . ($c['first_name'] ?? ''), ' ,') ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($c['crop_name']); ?></td>
                <td><?php echo htmlspecialchars($c['location']); ?></td>
                <td><?php echo rtrim(rtrim(number_format($c['area_hectares'], 2), '0'), '.'); ?> ha</td>
                <td><?php echo date('M j, Y', strtotime($c['planting_date'])); ?></td>
                <td><?php echo !empty($c['expected_harvest_date']) ? date('M j, Y', strtotime($c['expected_harvest_date'])) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="7"></td></tr>
        </tfoot>
    </table>

    <div class="cm-pr-prepared">
        <p class="cm-pr-prepared-label">Prepared by:</p>
        <p class="cm-pr-prepared-name"><?php echo htmlspecialchars($preparedByName); ?></p>
        <p class="cm-pr-prepared-title"><?php echo htmlspecialchars($preparedByTitle); ?></p>
        <!-- Awtomatikong petsa ng pag-print. Ang PHP ang unang laman (Philippine time);
             ina-update ito ng JavaScript sa mismong oras ng pag-print. -->
        <p class="cm-pr-prepared-date">Date: <span id="cmPrintDate"><?php echo (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('F j, Y'); ?></span></p>
    </div>
</div>


<div class="cm-modal" id="mgr-add-modal">
    <div class="cm-modal-card">
        <div class="cm-modal-header">
            <h2>Add planting</h2>
            <button class="cm-icon-btn" id="mgr-add-close" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div id="mgr-add-error" class="cm-error-box"></div>

        <form id="mgr-add-form" autocomplete="off">
            <input type="hidden" name="member_id" id="mgr-member-id" value="">

            <label>Member</label>
            <div class="cm-search-wrap">
                <input type="text" id="mgr-member-search" placeholder="Search by name or membership ID">
                <div class="cm-search-results" id="mgr-member-results"></div>
            </div>

            <label>Crop</label>
            <select name="crop_type_id" id="mgr-crop-type" required>
                <option value="">-- Select crop --</option>
                <?php foreach ($cropTypes as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="cm-crop-info" id="mgr-crop-info"></div>

            <div class="cm-form-row" style="margin-top:12px;">
                <div>
                    <label style="margin-top:0;">Area (ha)</label>
                    <input type="number" name="area_hectares" placeholder="e.g. 1.5" min="0.01" step="0.01" required>
                </div>
                <div>
                    <label style="margin-top:0;">Farm location</label>
                    <input type="text" name="location" placeholder="e.g. Purok 3" required>
                </div>
            </div>

            <label>Planting date</label>
            <input type="date" name="planting_date" id="mgr-planting-date" value="<?php echo htmlspecialchars($todayManila); ?>" required>

            <button type="submit" id="mgr-add-submit" class="cm-btn cm-btn-primary cm-btn-full" style="margin-top:16px;">Save planting</button>
        </form>
    </div>
</div>


<script>
    window.CROPS_AJAX_BASE = "<?php echo BASE_URL; ?>/app/manager/crops/api/";

    // Isinusulat ang petsa ngayon (Philippine time) sa "Prepared by" bago mag-print,
    // kaya tama pa rin kahit matagal nang nakabukas ang page.
    // Kinukuha ang element sa mismong oras ng pag-print, dahil pinapalitan ng
    // auto-update ang laman ng print report.
    function cmSetPrintDate() {
        const el = document.getElementById('cmPrintDate');
        if (!el) return;
        el.textContent = new Date().toLocaleDateString('en-US', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    // Gumagana rin kapag Ctrl+P ang ginamit imbes na Print button
    window.addEventListener('beforeprint', cmSetPrintDate);

    document.getElementById('cm-print-btn')?.addEventListener('click', function() {
        cmSetPrintDate();
        window.print();
    });
</script>

<script>
(function() {
    const MEMBERS = <?php echo json_encode($members, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CROP_TYPES = <?php echo json_encode($cropTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const openBtn   = document.getElementById('mgr-add-planting-btn');
    const modal     = document.getElementById('mgr-add-modal');
    const closeBtn  = document.getElementById('mgr-add-close');
    const form      = document.getElementById('mgr-add-form');
    const errorBox  = document.getElementById('mgr-add-error');
    const submitBtn = document.getElementById('mgr-add-submit');
    const searchEl  = document.getElementById('mgr-member-search');
    const resultsEl = document.getElementById('mgr-member-results');
    const memberEl  = document.getElementById('mgr-member-id');
    const typeSel   = document.getElementById('mgr-crop-type');
    const plantEl   = document.getElementById('mgr-planting-date');
    const infoEl    = document.getElementById('mgr-crop-info');

    if (!openBtn) return;

    function findType(id) {
        for (let i = 0; i < CROP_TYPES.length; i++) {
            if (String(CROP_TYPES[i].id) === String(id)) return CROP_TYPES[i];
        }
        return null;
    }

    function addToDate(dateStr, amount, unit) {
        const p = dateStr.split('-');
        const d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
        if (unit === 'months') d.setMonth(d.getMonth() + amount);
        else d.setDate(d.getDate() + amount);
        return d;
    }

    function rangeText(t) {
        const unit = t.unit === 'months' ? 'months' : 'days';
        return (Number(t.min_value) === Number(t.max_value))
            ? t.max_value + ' ' + unit
            : t.min_value + '\u2013' + t.max_value + ' ' + unit;
    }

    function updateInfo() {
        const t = findType(typeSel.value);
        infoEl.textContent = '';
        if (!t) {
            infoEl.classList.remove('cm-show');
            return;
        }
        const line1 = document.createElement('div');
        line1.textContent = 'Takes ' + rangeText(t);
        infoEl.appendChild(line1);

        if (plantEl.value) {
            const exp = addToDate(plantEl.value, Number(t.max_value), t.unit);
            const line2 = document.createElement('div');
            line2.appendChild(document.createTextNode('Expected harvest: '));
            const b = document.createElement('b');
            b.textContent = exp.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            line2.appendChild(b);
            infoEl.appendChild(line2);
        }
        infoEl.classList.add('cm-show');
    }

    typeSel.addEventListener('change', updateInfo);
    plantEl.addEventListener('input', updateInfo);

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.add('cm-show');
    }
    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.remove('cm-show');
    }

    function openModal() { modal.classList.add('cm-open'); }
    function closeModal() {
        modal.classList.remove('cm-open');
        form.reset();
        memberEl.value = '';
        resultsEl.classList.remove('cm-show');
        updateInfo();
        clearError();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('cm-open')) closeModal();
    });

    function memberLabel(m) {
        return (m.last_name + ', ' + m.first_name).trim();
    }

    function renderResults(term) {
        const q = term.trim().toLowerCase();
        resultsEl.innerHTML = '';

        if (q === '') {
            resultsEl.classList.remove('cm-show');
            return;
        }

        const matches = MEMBERS.filter(function(m) {
            const hay = (m.first_name + ' ' + m.last_name + ' ' + m.last_name + ' ' + m.first_name + ' ' + m.membership_id).toLowerCase();
            return hay.indexOf(q) !== -1;
        }).slice(0, 8);

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'cm-search-empty';
            empty.textContent = 'No members found.';
            resultsEl.appendChild(empty);
        } else {
            matches.forEach(function(m) {
                const item = document.createElement('div');
                item.className = 'cm-search-item';

                const name = document.createElement('p');
                name.className = 'cm-search-item-name';
                name.textContent = memberLabel(m);

                const id = document.createElement('p');
                id.className = 'cm-search-item-id';
                id.textContent = m.membership_id;

                item.appendChild(name);
                item.appendChild(id);

                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    memberEl.value = m.id;
                    searchEl.value = memberLabel(m) + ' (' + m.membership_id + ')';
                    resultsEl.classList.remove('cm-show');
                    clearError();
                });

                resultsEl.appendChild(item);
            });
        }

        resultsEl.classList.add('cm-show');
    }

    searchEl.addEventListener('input', function() {
        memberEl.value = '';
        renderResults(searchEl.value);
    });
    searchEl.addEventListener('focus', function() {
        if (memberEl.value === '') renderResults(searchEl.value);
    });
    searchEl.addEventListener('blur', function() {
        setTimeout(function() { resultsEl.classList.remove('cm-show'); }, 120);
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        clearError();

        const data = Object.fromEntries(new FormData(form).entries());

        if (!data.member_id) {
            showError('Select a member from the search results.');
            return;
        }
        if (!data.crop_type_id) {
            showError('Please choose a crop.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const response = await fetch(window.CROPS_AJAX_BASE + 'add-planting.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const rawText = await response.text();
            let payload;
            try {
                payload = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                showError('Server error. Check the console (F12).');
                return;
            }

            if (response.ok && payload.success !== false) {
                window.location.reload();
            } else {
                showError(payload.message || 'Something went wrong.');
            }
        } catch (err) {
            showError('Could not reach the server: ' + err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save planting';
        }
    });
})();
</script>

<script>
(function () {
    const LIVE_CHECK_MS = 3000;

    function itemMap(root, sel, attr) {
        const m = new Map();
        if (!sel) return m;
        root.querySelectorAll(sel).forEach(el => m.set(el.getAttribute(attr), el.outerHTML));
        return m;
    }

    const liveParts = [
        { id: 'cmSummaryLive' },
        { id: 'cmPrintReport' },
        { id: 'cmTableLive', sel: 'tr[data-crop-id]', attr: 'data-crop-id' }
    ].filter(function (p) {
        p.el = document.getElementById(p.id);
        if (!p.el) return false;
        p.html = p.el.innerHTML;
        p.items = itemMap(p.el, p.sel, p.attr);
        return true;
    });

    let busy = false;
    let fails = 0;
    let timer = null;

    function anyModalOpen() {
        return !!document.querySelector('.cm-modal.cm-open');
    }

    async function liveCheck() {
        if (document.hidden || busy || anyModalOpen()) return;
        busy = true;
        try {
            const r = await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            const fresh = liveParts.map(p => doc.getElementById(p.id));

            if (fresh.every(Boolean)) {
                liveParts.forEach(function (p, i) {
                    const f = fresh[i];
                    if (f.innerHTML === p.html) return;
                    p.html = f.innerHTML;
                    const newItems = itemMap(f, p.sel, p.attr);
                    p.el.innerHTML = p.html;
                    if (p.sel) {
                        p.el.querySelectorAll(p.sel).forEach(function (el) {
                            const id = el.getAttribute(p.attr);
                            if (p.items.get(id) !== newItems.get(id)) el.classList.add('cm-flash');
                        });
                    }
                    p.items = newItems;
                });
            }
            fails = 0;
        } catch (e) {
            fails++;
        } finally {
            busy = false;
        }
    }

    function schedule() {
        clearTimeout(timer);
        const delay = Math.min(LIVE_CHECK_MS * Math.pow(2, fails), 60000);
        timer = setTimeout(async function () { await liveCheck(); schedule(); }, delay);
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) liveCheck(); });
    schedule();
})();
</script>

<?php renderFooter(); ?>