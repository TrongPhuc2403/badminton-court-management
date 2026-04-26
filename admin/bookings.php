<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';
require_once '../includes/header.php';

ensureLoyaltyTables($conn);
syncPendingLoyaltyAwards($conn);

$success = '';
$error = '';

if (isset($_GET['success']) && $_GET['success'] === 'booking_cancelled') {
    $success = 'Đã hủy lịch đặt sân của khách hàng.';
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_booking') {
        $error = 'Booking không hợp lệ.';
    } elseif ($_GET['error'] === 'booking_not_cancellable') {
        $error = 'Booking này không thể hủy.';
    } elseif ($_GET['error'] === 'payment_confirmation_disabled') {
        $error = 'Tính năng xác nhận tiền thủ công đã bị tắt.';
    }
}

$date = isset($_GET['date']) ? $_GET['date'] : '';
$courtId = isset($_GET['court_id']) ? (int) $_GET['court_id'] : 0;

$courts = mysqli_query($conn, "SELECT * FROM courts ORDER BY id ASC");

$sql = "SELECT b.*, u.full_name, u.phone, c.name AS court_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN courts c ON b.court_id = c.id
        WHERE 1=1";
$params = [];
$types = '';

if ($date !== '') {
    $sql .= " AND b.booking_date = ?";
    $params[] = $date;
    $types .= 's';
}

if ($courtId > 0) {
    $sql .= " AND b.court_id = ?";
    $params[] = $courtId;
    $types .= 'i';
}

$sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-title">
    <h2>Quản lý đặt sân</h2>
</div>

<div class="form-box">
    <form method="GET">
        <label>Lọc theo ngày</label>
        <input type="date" name="date" value="<?= e($date) ?>">

        <label>Lọc theo sân</label>
        <select name="court_id">
            <option value="0">-- Tất cả sân --</option>
            <?php while ($court = mysqli_fetch_assoc($courts)): ?>
                <option value="<?= (int) $court['id'] ?>" <?= $courtId === (int) $court['id'] ? 'selected' : '' ?>>
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
            <th>SDT</th>
            <th>Sân</th>
            <th>Ngày</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Phương thức</th>
            <th>Thanh toán</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['phone']) ?></td>
                <td><?= e($row['court_name']) ?></td>
                <td><?= e($row['booking_date']) ?></td>
                <td><?= e($row['start_time']) ?> - <?= e($row['end_time']) ?></td>
                <td><?= formatMoney($row['total_price']) ?></td>
                <td><?= e(getPaymentMethodLabel($row['payment_method'] ?? 'cash')) ?></td>
                <td>
                    <span class="badge <?= $row['payment_status'] === 'paid' ? 'badge-paid' : 'badge-unpaid' ?>">
                        <?= e(getPaymentStatusDisplayLabel($row['payment_status'], $row['payment_method'] ?? 'cash')) ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $row['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-cancelled' ?>">
                        <?= $row['status'] === 'confirmed' ? 'Da dat' : 'Da huy' ?>
                    </span>
                </td>
                <td>
                    <?php if ($row['status'] === 'confirmed'): ?>
                        <a class="button-danger" onclick="return confirm('Bạn có chắc muốn hủy booking này?')" href="/badminton-manager/admin/cancel_booking.php?id=<?= (int) $row['id'] ?>">Hủy booking</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
