<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";
$success = "";
$previewPrice = null;

$courts = mysqli_query($conn, "SELECT * FROM courts WHERE status = 'active' ORDER BY id ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courtId = (int)$_POST['court_id'];
    $bookingDate = $_POST['booking_date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $action = $_POST['action'] ?? 'book';

    if (isPastDate($bookingDate)) {
        $error = "Không thể đặt sân trong ngày đã qua.";
    } elseif (!isValidBookingTime($startTime, $endTime)) {
        $error = "Chỉ được đặt từ 04:00 đến 22:00, theo từng giờ tròn.";
    } else {
        $previewPrice = calculateBookingPrice($bookingDate, $startTime, $endTime);

        if ($action === 'book') {
            if (!checkCourtAvailable($conn, $courtId, $bookingDate, $startTime, $endTime)) {
                $error = "Sân đã được đặt trong khung giờ này.";
            } else {
                $sql = "INSERT INTO bookings (user_id, court_id, booking_date, start_time, end_time, total_price, payment_status, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'unpaid', 'confirmed')";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param(
                    $stmt,
                    "iisssd",
                    $_SESSION['user']['id'],
                    $courtId,
                    $bookingDate,
                    $startTime,
                    $endTime,
                    $previewPrice
                );
                mysqli_stmt_execute($stmt);

                $success = "Đặt sân thành công. Số tiền cần thanh toán: " . formatMoney($previewPrice);
            }
        }
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-title">
    <h2>Đặt sân cầu lông</h2>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="form-box">
    <form method="POST">
        <label>Chọn sân</label>
        <select name="court_id" required>
            <option value="">-- Chọn sân --</option>
            <?php mysqli_data_seek($courts, 0); ?>
            <?php while ($court = mysqli_fetch_assoc($courts)): ?>
                <option value="<?= $court['id'] ?>" <?= (isset($_POST['court_id']) && $_POST['court_id'] == $court['id']) ? 'selected' : '' ?>>
                    <?= e($court['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Ngày đặt</label>
        <input type="date" name="booking_date" required min="<?= date('Y-m-d') ?>" value="<?= isset($_POST['booking_date']) ? e($_POST['booking_date']) : '' ?>">

        <label>Giờ bắt đầu</label>
        <select name="start_time" required>
            <option value="">-- Chọn giờ bắt đầu --</option>
            <?php for ($h = 4; $h < 22; $h++): $time = sprintf('%02d:00', $h); ?>
                <option value="<?= $time ?>" <?= (isset($_POST['start_time']) && $_POST['start_time'] === $time) ? 'selected' : '' ?>><?= $time ?></option>
            <?php endfor; ?>
        </select>

        <label>Giờ kết thúc</label>
        <select name="end_time" required>
            <option value="">-- Chọn giờ kết thúc --</option>
            <?php for ($h = 5; $h <= 22; $h++): $time = sprintf('%02d:00', $h); ?>
                <option value="<?= $time ?>" <?= (isset($_POST['end_time']) && $_POST['end_time'] === $time) ? 'selected' : '' ?>><?= $time ?></option>
            <?php endfor; ?>
        </select>

        <br><br>
        <button type="submit" name="action" value="preview">Xem tiền</button>
        <button type="submit" name="action" value="book">Đặt sân</button>
    </form>
</div>

<?php if ($previewPrice !== null): ?>
    <div class="card">
        <h3>Số tiền cần thanh toán</h3>
        <div class="number"><?= formatMoney($previewPrice) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:20px;">
    <h3>Bảng giá</h3>
    <p>Thứ 2 - Thứ 6, 04:00 - 16:00: 90.000 VNĐ / giờ</p>
    <p>Thứ 2 - Thứ 6, 17:00 - 22:00: 120.000 VNĐ / giờ</p>
    <p>Thứ 7 - Chủ nhật, 04:00 - 22:00: 120.000 VNĐ / giờ</p>
</div>

<?php require_once '../includes/footer.php'; ?>