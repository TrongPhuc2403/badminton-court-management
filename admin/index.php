<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ensureReviewTables($conn);
require_once '../includes/header.php';

$courtCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM courts"))['total'];
$customerCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"))['total'];
$bookingCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE status = 'confirmed'"))['total'];
$todayRevenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_price),0) AS total FROM bookings WHERE booking_date = CURDATE() AND status = 'confirmed'"))['total'];

$favoriteCourts = mysqli_query($conn, "
    SELECT
        c.name,
        ROUND(AVG(cr.overall_rating), 1) AS avg_rating,
        COUNT(cr.id) AS review_count
    FROM court_reviews cr
    JOIN courts c ON c.id = cr.court_id
    GROUP BY cr.court_id, c.name
    HAVING COUNT(cr.id) > 0
    ORDER BY avg_rating DESC, review_count DESC, c.name ASC
    LIMIT 5
");

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
    <h2>Top sân được yêu thích</h2>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Sân</th>
            <th>Điểm trung bình</th>
            <th>Sao</th>
            <th>Lượt đánh giá</th>
        </tr>
        <?php if (mysqli_num_rows($favoriteCourts) > 0): ?>
            <?php while ($court = mysqli_fetch_assoc($favoriteCourts)): ?>
                <tr>
                    <td><?= e($court['name']) ?></td>
                    <td><?= formatRating($court['avg_rating']) ?>/5</td>
                    <td><?= e(renderStars($court['avg_rating'])) ?></td>
                    <td><?= (int) $court['review_count'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">Chưa có đánh giá nào từ khách hàng.</td>
            </tr>
        <?php endif; ?>
    </table>
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
