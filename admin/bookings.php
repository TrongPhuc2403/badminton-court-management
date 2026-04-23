<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$date = isset($_GET['date']) ? $_GET['date'] : '';
$courtId = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;

$courts = mysqli_query($conn, "SELECT * FROM courts ORDER BY id ASC");

$sql = "SELECT b.*, u.full_name, u.phone, c.name AS court_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN courts c ON b.court_id = c.id
        WHERE 1=1";
$params = [];
$types = "";

if ($date !== '') {
    $sql .= " AND b.booking_date = ?";
    $params[] = $date;
    $types .= "s";
}

if ($courtId > 0) {
    $sql .= " AND b.court_id = ?";
    $params[] = $courtId;
    $types .= "i";
}

$sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<div class="page-title">
    <h2>Quản lí đặt sân</h2>
</div>

<div class="form-box">
    <form method="GET">
        <label>Lọc theo ngày</label>
        <input type="date" name="date" value="<?= e($date) ?>">

        <label>Lọc theo sân</label>
        <select name="court_id">
            <option value="0">-- Tất cả sân --</option>
            <?php while ($court = mysqli_fetch_assoc($courts)): ?>
                <option value="<?= $court['id'] ?>" <?= $courtId === (int)$court['id'] ? 'selected' : '' ?>>
                    <?= e($court['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <br><br>
        <button type="submit">Lọc dữ liệu</button>
    </form>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Khách hàng</th>
            <th>SĐT</th>
            <th>Sân</th>
            <th>Ngày</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Thanh toán</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['phone']) ?></td>
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
                    <?php if ($row['status'] === 'confirmed' && $row['payment_status'] === 'unpaid'): ?>
                        <a class="button" href="/badminton-manager/admin/booking_payment.php?id=<?= $row['id'] ?>">Xác nhận tiền</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>