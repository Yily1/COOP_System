<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
requireLogin();

$currentRole = $_SESSION['role'];

if ($currentRole !== 'manager') {
    die("Access denied. Only managers can view the member list.");
}

// Show every member, plus whether they already have a linked account
$stmt = $pdo->query("
    SELECT m.*, u.id as user_id, u.email, u.account_status
    FROM members m
    LEFT JOIN users u ON u.member_id = m.id
    ORDER BY m.created_at DESC
");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Member List');
?>

<h1>Member List</h1>

<div class="info-box">
    Showing all cooperative members (<?php echo count($members); ?> total).
    "Not Linked" means an Admin has not yet created a login account for that member.
</div>

<table>
    <thead>
        <tr>
            <th>Membership ID</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Contact Number</th>
            <th>Membership Type</th>
            <th>Type of Farmer</th>
            <th>Date Joined</th>
            <th>Account</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
            <td><?php echo htmlspecialchars($m['membership_id']); ?></td>
            <td><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name'] . ' ' . $m['middle_name']); ?></td>
            <td><?php echo htmlspecialchars($m['gender']); ?></td>
            <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
            <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
            <td>
                <?php echo htmlspecialchars($m['farmer_type'] ?? '-'); ?>
                <?php if ($m['farmer_type']): ?>
                    <br><small style="color:#666;">
                        <?php
                        $details = [];
                        if (!empty($m['livestock_details'])) $details[] = 'Livestock: ' . $m['livestock_details'];
                        if (!empty($m['crops_details'])) $details[] = 'Crops: ' . $m['crops_details'];
                        echo htmlspecialchars(implode(' | ', $details));
                        ?>
                    </small>
                <?php endif; ?>
            </td>
            <td><?php echo date('Y-m-d', strtotime($m['date_joined'])); ?></td>
            <td>
                <?php if ($m['user_id']): ?>
                    <span class="badge badge-verified">Linked</span>
                    <small style="display:block; color:#666;"><?php echo htmlspecialchars($m['email']); ?></small>
                <?php else: ?>
                    <span class="badge" style="background:#999;">Not Linked</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="member-update.php?member_id=<?php echo $m['id']; ?>">Edit</a>
                <?php if (!$m['user_id']): ?>
                    | <a href="member-delete.php?member_id=<?php echo $m['id']; ?>"
                         onclick="return confirm('Are you sure you want to delete this member profile? This cannot be undone.')"
                         style="color: #dc3545;">Delete</a>
                <?php else: ?>
                    | <span style="color: #6c757d;" title="Has a linked account - cannot be deleted">Delete</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($members)): ?>
    <p style="text-align: center; padding: 40px; color: #6c757d;">
        No members yet. <a href="member-create.php">Create the first one.</a>
    </p>
<?php endif; ?>

<?php renderFooter(); ?>
