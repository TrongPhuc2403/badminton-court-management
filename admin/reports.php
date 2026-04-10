<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$reportType = isset($_GET['type']) ? $_GET['type'] : 'day';
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$summary = 0;
$result = false;

if ($reportType === 'month') {
    $sql = "SELECT b.booking_date, c.name AS court_name, u.full_name, b.start_time, b.end_time, b.total_price, b.payment_status
            FROM bookings b
            JOIN courts c ON b.court_id = c.id
            JOIN users u ON b.user_id = u.id
            WHERE DATE_FORMAT(b.booking_date, '%Y-%m') = ? AND b.status = 'confirmed'
            ORDER BY b.booking_date DESC, b.start_time DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $selectedMonth);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $sumSql = "SELECT COALESCE(SUM(total_price),0) AS total
               FROM bookings
               WHERE DATE_FORMAT(booking_date, '%Y-%m') = ? AND status = 'confirmed'";
    $sumStmt = mysqli_prepare($conn, $sumSql);
    mysqli_stmt_bind_param($sumStmt, "s", $selectedMonth);
    mysqli_stmt_execute($sumStmt);
    $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($sumStmt))['total'];
} else {
    $sql = "SELECT b.booking_date, c.name AS court_name, u.full_name, b.start_time, b.end_time, b.total_price, b.payment_status
            FROM bookings b
            JOIN courts c ON b.court_id = c.id
            JOIN users u ON b.user_id = u.id
            WHERE b.booking_date = ? AND b.status = 'confirmed'
            ORDER BY b.start_time DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $selectedDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $sumSql = "SELECT COALESCE(SUM(total_price),0) AS total
               FROM bookings
               WHERE booking_date = ? AND status = 'confirmed'";
    $sumStmt = mysqli_prepare($conn, $sumSql);
    mysqli_stmt_bind_param($sumStmt, "s", $selectedDate);
    mysqli_stmt_execute($sumStmt);
    $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($sumStmt))['total'];
}
?>

<div class="page-title">
    <h2>Báo cáo doanh thu</h2>
</div>

<div class="form-box">
    <form method="GET">
        <label>Kiểu báo cáo</label>
        <select name="type">
            <option value="day" <?= $reportType === 'day' ? 'selected' : '' ?>>Theo ngày</option>
            <option value="month" <?= $reportType === 'month' ? 'selected' : '' ?>>Theo tháng</option>
        </select>

        <label>Ngày</label>
        <input type="date" name="date" value="<?= e($selectedDate) ?>">

        <label>Tháng</label>
        <input type="month" name="month" value="<?= e($selectedMonth) ?>">

        <br><br>
        <button type="submit">Xem báo cáo</button>
    </form>
</div>

<div class="card">
    <h3>Tổng doanh thu</h3>
    <div class="number" style="font-size:30px;"><?= formatMoney($summary) ?></div>
</div>

<div class="table-wrapper" style="margin-top: 24px;">
    <table>
        <tr>
            <th>Ngày</th>
            <th>Khách hàng</th>
            <th>Sân</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Thanh toán</th>
        </tr>
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $row['booking_date'] ?></td>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['court_name']) ?></td>
                    <td><?= $row['start_time'] ?> - <?= $row['end_time'] ?></td>
                    <td><?= formatMoney($row['total_price']) ?></td>
                    <td><?= $row['payment_status'] === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Không có dữ liệu.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>