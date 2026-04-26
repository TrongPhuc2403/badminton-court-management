<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);
syncPendingLoyaltyAwards($conn);
require_once '../includes/header.php';

$result = mysqli_query($conn, "
    SELECT u.*,
           (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status = 'confirmed') AS total_bookings
    FROM users u
    WHERE u.role = 'customer'
    ORDER BY u.id DESC
");
?>

<div class="page-title">
    <h2>Danh sách khách hàng</h2>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>ID</th>
            <th>Họ tên</th>
            <th>Số điện thoại</th>
            <th>Số lượt đặt</th>
            <th>Điểm hiện có</th>
            <th>Ngày tạo</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['phone']) ?></td>
                <td><?= (int) $row['total_bookings'] ?></td>
                <td><?= (int) ($row['loyalty_points'] ?? 0) ?></td>
                <td><?= e($row['created_at']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
