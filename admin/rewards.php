<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);
syncPendingLoyaltyAwards($conn);
require_once '../includes/header.php';

$result = mysqli_query($conn, "
    SELECT lr.*, u.full_name, u.phone, u.loyalty_points
    FROM loyalty_redemptions lr
    JOIN users u ON u.id = lr.user_id
    ORDER BY lr.id DESC
");
?>

<div class="page-title">
    <div>
        <h2>Yêu cầu đổi quà</h2>
        <p class="page-subtitle">Theo dõi các yêu cầu đổi giờ chơi miễn phí và trái cầu từ điểm thưởng khách hàng.</p>
    </div>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>ID</th>
            <th>Khách hàng</th>
            <th>SĐT</th>
            <th>Phần quà</th>
            <th>Số lượng</th>
            <th>Điểm đã dùng</th>
            <th>Trạng thái</th>
            <th>Số dư hiện tại</th>
            <th>Ghi chú</th>
            <th>Thao tác</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['phone']) ?></td>
                <td><?= e(getLoyaltyRewardLabel($row['reward_type'])) ?></td>
                <td><?= (int) $row['quantity'] ?></td>
                <td><?= (int) $row['points_used'] ?></td>
                <td><?= e(getLoyaltyRedemptionStatusLabel($row['status'])) ?></td>
                <td><?= (int) ($row['loyalty_points'] ?? 0) ?></td>
                <td><?= e($row['note'] ?? '') ?></td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <a class="button" href="/badminton-manager/admin/reward_update.php?id=<?= (int) $row['id'] ?>&action=fulfill">Hoàn tất</a>
                        <a class="button-danger" onclick="return confirm('Bạn có chắc muốn hủy yêu cầu đổi quà này?')" href="/badminton-manager/admin/reward_update.php?id=<?= (int) $row['id'] ?>&action=cancel">Hủy</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
