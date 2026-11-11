<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT m.id AS member_db_id
    FROM users u
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$myProfile = $stmt->fetch(PDO::FETCH_ASSOC);

$memberId = $myProfile['member_db_id'] ?? 0;

$distributions = getDistributionsForMember($pdo, $memberId);

renderHeader('My Resources');
?>

<style>

.page-shell {
    width: 100%;
    max-width: 420px;
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
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-icon svg {
    width: 22px;
    height: 22px;
    stroke: #2e7d32;
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

.page-back {
    margin-left: auto;
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #3B6D11;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    color: #3B6D11 !important;
    text-decoration: none !important;
    box-shadow: none;
    transition: background 0.12s ease;
}

.page-back:hover {
    background: #eef5e4;
}

.page-back svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
}

.section-intro {
    margin: 0 0 14px 0;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.section-intro h2 {
    margin: 0;
    color: #1b3a24;
    font-size: 16px;
    font-weight: 600;
}

.stat-card {
    display: inline-block;
    min-width: 130px;
    padding: 14px 18px;
    box-sizing: border-box;
    border-radius: 14px;
    background: #2e7d32;
    margin-bottom: 22px;
    text-align: center;
}

.stat-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.85);
    margin: 0 0 2px;
}

.stat-number {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.res-card {
    background: #ffffff;
    border: 1px solid #eceae4;
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 10px;
}

/* Green flash for a new or changed distribution (auto-update) */
@keyframes resFlashCard {
    from { background: #e3f4e1; border-color: #2e7d32; }
    to   { background: #ffffff; border-color: #eceae4; }
}
.res-card.res-flash { animation: resFlashCard 2.2s ease-out; }

.res-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.res-name {
    margin: 0;
    font-size: 14.5px;
    font-weight: 600;
    color: #1b3a24;
}

.status-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
    flex: none;
}
.status-badge.released      { background: #e8f5e9; color: #2e7d32; }
.status-badge.not_released  { background: #fbe6e6; color: #a6322f; }

.res-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px 10px;
    font-size: 12px;
    color: #667066;
}
.res-detail-grid b { color: #1b3a24; }

.res-notes {
    margin: 8px 0 0;
    padding-top: 8px;
    border-top: 1px dashed #eceae4;
    font-size: 12px;
    color: #667066;
}
.res-notes b { color: #1b3a24; }

.empty-note { color: #667066; font-size: 13px; text-align: center; padding: 20px 0; }

/* Long names and text wrap inside the card */
.page-header > div:nth-child(2) { min-width: 0; }
.page-sub { overflow-wrap: anywhere; }
.res-card-top { gap: 10px; }
.res-name { min-width: 0; overflow-wrap: anywhere; }
.res-detail-grid span { min-width: 0; overflow-wrap: anywhere; }
.res-notes { overflow-wrap: anywhere; }

/* ============================================================
   MOBILE (480px and below)
   ============================================================ */
@media (max-width: 480px) {
    .page-header { gap: 10px; }
    .page-title { font-size: 17px; }
    .page-back { width: 40px; height: 40px; }
}

/* ============================================================
   TABLET (600px and up): wider page, 2 columns of cards
   ============================================================ */
@media (min-width: 600px) {
    .page-shell { max-width: 720px; }
    #resListLive {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    #resListLive .res-card { margin-bottom: 0; }
    #resListLive .empty-note { grid-column: 1 / -1; }
}

/* ============================================================
   DESKTOP (992px and up)
   ============================================================ */
@media (min-width: 992px) {
    .page-shell { max-width: 1040px; }
    .page-title { font-size: 22px; }
    .page-sub { font-size: 14.5px; }
    .section-intro h2 { font-size: 18px; }

    .stat-card { min-width: 170px; padding: 18px 28px; }
    .stat-label { font-size: 13.5px; }
    .stat-number { font-size: 28px; }

    #resListLive { gap: 16px; }
    .res-card { padding: 18px 22px; }
    .res-name { font-size: 16px; }
    .res-detail-grid { font-size: 13px; gap: 5px 14px; }
    .res-notes { font-size: 13px; }
    .status-badge { font-size: 11.5px; }
}
</style>

<div class="page-shell">

    <div class="page-header">
        <div class="page-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        </div>
        <div style="flex:1;">
            <p class="page-title">My resources</p>
            <p class="page-sub">DA resources given to you by the cooperative</p>
        </div>

        <a href="<?php echo BASE_URL; ?>/app/user/dashboard.php" class="page-back" aria-label="Back to dashboard" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
    </div>

    <div id="resStatLive">
        <div class="stat-card">
            <p class="stat-label">Total distributions</p>
            <p class="stat-number"><?php echo count($distributions); ?></p>
        </div>
    </div>

    <div class="section-intro">
        <h2>Distribution history</h2>
    </div>

    <div id="resListLive">
    <?php if (empty($distributions)): ?>
        <p class="empty-note">No resources have been given to you yet.</p>
    <?php else: ?>
        <?php foreach ($distributions as $i => $d): ?>
            <?php $isReleased = $d['status'] === 'released'; ?>
            <div class="res-card" data-res-id="<?php echo (int) ($d['id'] ?? $i); ?>">
                <div class="res-card-top">
                    <p class="res-name"><?php echo htmlspecialchars($d['resource_name']); ?></p>
                    <span class="status-badge <?php echo $isReleased ? 'released' : 'not_released'; ?>">
                        <?php echo $isReleased ? 'Released' : 'Not released'; ?>
                    </span>
                </div>

                <div class="res-detail-grid">
                    <span>Quantity: <b><?php echo htmlspecialchars($d['quantity']); ?></b></span>
                    <span>Date: <b><?php echo date('M j, Y', strtotime($d['distribution_date'])); ?></b></span>
                </div>

                <?php if (!empty($d['notes'])): ?>
                    <p class="res-notes"><b>Notes:</b> <?php echo htmlspecialchars($d['notes']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

</div>

<!-- ============================================================
     AUTO-UPDATE (AJAX POLLING)
     Fetches this same page, then swaps only the parts that changed.
     Paused while the tab is hidden.
     ============================================================ -->
<script>
(function () {
    const LIVE_CHECK_MS = 3000;   // set to 5000 or 10000 for fewer requests

    function itemMap(root, sel, attr) {
        const m = new Map();
        if (!sel) return m;
        root.querySelectorAll(sel).forEach(el => m.set(el.getAttribute(attr), el.outerHTML));
        return m;
    }

    const liveParts = [
        { id: 'resStatLive' },
        { id: 'resListLive', sel: '.res-card', attr: 'data-res-id' }
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

    async function liveCheck() {
        if (document.hidden || busy) return;
        busy = true;
        try {
            const r = await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            const fresh = liveParts.map(p => doc.getElementById(p.id));

            // If an expected element is missing (e.g. login page), leave the page alone
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
                            if (p.items.get(id) !== newItems.get(id)) el.classList.add('res-flash');
                        });
                    }
                    p.items = newItems;
                });
            }
            fails = 0;
        } catch (e) {
            fails++;   // backoff on error
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