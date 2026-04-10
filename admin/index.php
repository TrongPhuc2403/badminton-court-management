<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$courtCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM courts"))['total'];
$customerCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"))['total'];
$bookingCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE status = 'confirmed'"))['total'];
$todayRevenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_price),0) AS total FROM bookings WHERE booking_date = CURDATE() AND status = 'confirmed'"))['total'];

$todayBookings = mysqli_query($conn, "
    SELECT b.*, u.full_name, c.name AS court_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN courts c ON b.court_id = c.id
    WHERE b.booking_date = CURDATE() AND b.status = 'confirmed'
    ORDER BY b.start_time ASC
");
?>

<div class="page-title">
    <h2>Tổng quan hệ thống</h2>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h3>Tổng số sân</h3>
        <div class="number"><?= $courtCount ?></div>
    </div>

    <div class="card">
        <h3>Tổng số khách hàng</h3>
        <div class="number"><?= $customerCount ?></div>
    </div>

    <div class="card">
        <h3>Tổng số booking</h3>
        <div class="number"><?= $bookingCount ?></div>
    </div>

    <div class="card">
        <h3>Doanh thu hôm nay</h3>
        <div class="number" style="font-size:26px;"><?= formatMoney($todayRevenue) ?></div>
    </div>
</div>

<div class="page-title">
    <h2>Lịch đặt hôm nay</h2>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Khách hàng</th>
            <th>Sân</th>
            <th>Ngày</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
        </tr>
        <?php if (mysqli_num_rows($todayBookings) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($todayBookings)): ?>
                <tr>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['court_name']) ?></td>
                    <td><?= $row['booking_date'] ?></td>
                    <td><?= $row['start_time'] ?> - <?= $row['end_time'] ?></td>
                    <td><?= formatMoney($row['total_price']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">Hôm nay chưa có lịch đặt.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>