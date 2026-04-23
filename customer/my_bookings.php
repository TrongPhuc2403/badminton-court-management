<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$sql = "SELECT b.*, c.name AS court_name
        FROM bookings b
        JOIN courts c ON b.court_id = c.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, b.start_time DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user']['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<div class="page-title">
    <h2>Lịch sử đặt sân của tôi</h2>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Sân</th>
            <th>Ngày</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Thanh toán</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= e($row['court_name']) ?></td>
                <td><?= $row['booking_date'] ?></td>
                <td><?= $row['start_time'] ?> - <?= $row['end_time'] ?></td>
                <td><?= formatMoney($row['total_price']) ?></td>
                <td>
                    <span class="badge <?= $row['payment_status'] === 'paid' ? 'badge-paid' : 'badge-unpaid' ?>">
                        <?= $row['payment_status'] === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $row['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-cancelled' ?>">
                        <?= $row['status'] === 'confirmed' ? 'Đã đặt' : 'Đã hủy' ?>
                    </span>
                </td>
                <td>
                    <?php if ($row['status'] === 'confirmed' && bookingCanBeCancelled($row['booking_date'], $row['start_time'])): ?>
                        <a class="button-danger" onclick="return confirm('Bạn có chắc muốn hủy booking này?')" href="/badminton-manager/customer/cancel_booking.php?id=<?= $row['id'] ?>">Hủy</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>