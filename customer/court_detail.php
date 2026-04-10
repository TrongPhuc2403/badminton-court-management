<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/header.php';

$courtId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : date('Y-m-d');

$courtStmt = mysqli_prepare($conn, "SELECT * FROM courts WHERE id = ?");
mysqli_stmt_bind_param($courtStmt, "i", $courtId);
mysqli_stmt_execute($courtStmt);
$courtResult = mysqli_stmt_get_result($courtStmt);
$court = mysqli_fetch_assoc($courtResult);

if (!$court) {
    header("Location: /badminton-manager/customer/home.php");
    exit();
}

$bookingSql = "SELECT b.*, u.full_name
               FROM bookings b
               JOIN users u ON b.user_id = u.id
               WHERE b.court_id = ? AND b.booking_date = ? AND b.status = 'confirmed'
               ORDER BY b.start_time ASC";
$bookingStmt = mysqli_prepare($conn, $bookingSql);
mysqli_stmt_bind_param($bookingStmt, "is", $courtId, $date);
mysqli_stmt_execute($bookingStmt);
$bookingResult = mysqli_stmt_get_result($bookingStmt);

$bookedHours = [];
mysqli_data_seek($bookingResult, 0);
while ($row = mysqli_fetch_assoc($bookingResult)) {
    $start = (int)date('H', strtotime($row['start_time']));
    $end = (int)date('H', strtotime($row['end_time']));
    for ($h = $start; $h < $end; $h++) {
        $bookedHours[$h] = true;
    }
}
mysqli_data_seek($bookingResult, 0);
?>

<div class="page-title">
    <h2><?= e($court['name']) ?> - Lịch đặt</h2>
</div>

<div class="form-box">
    <form method="GET">
        <input type="hidden" name="id" value="<?= $courtId ?>">
        <label>Chọn ngày</label>
        <input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>">
        <br><br>
        <button type="submit">Xem lịch</button>
    </form>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Khung giờ trong ngày</h3>
    <?php for ($hour = 4; $hour < 22; $hour++): ?>
        <span class="court-slot <?= isset($bookedHours[$hour]) ? 'booked' : '' ?>">
            <?= sprintf('%02d:00', $hour) ?> - <?= sprintf('%02d:00', $hour + 1) ?>
        </span>
    <?php endfor; ?>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Khách hàng</th>
            <th>Giờ bắt đầu</th>
            <th>Giờ kết thúc</th>
        </tr>
        <?php if (mysqli_num_rows($bookingResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($bookingResult)): ?>
                <tr>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= $row['start_time'] ?></td>
                    <td><?= $row['end_time'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3">Chưa có lịch đặt cho ngày này.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>