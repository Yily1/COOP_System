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

.stat-number {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 2px;
}

.stat-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.85);
}

.res-card {
    background: #ffffff;
    border: 1px solid #eceae4;
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 10px;
}

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

@media (max-width: 480px) {
    .page-title { font-size: 17px; }
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
    </div>

    <div class="stat-card">
        <p class="stat-number"><?php echo count($distributions); ?></p>
        <p class="stat-label">Total distributions</p>
    </div>

    <div class="section-intro">
        <h2>Distribution history</h2>
    </div>

    <?php if (empty($distributions)): ?>
        <p class="empty-note">No resources have been given to you yet.</p>
    <?php else: ?>
        <?php foreach ($distributions as $d): ?>
            <?php $isReleased = $d['status'] === 'released'; ?>
            <div class="res-card">
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

<?php renderFooter(); ?>