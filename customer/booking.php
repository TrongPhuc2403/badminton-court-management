<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$previewPrice = null;
$qrCodeUrl = null;
$paymentReference = null;
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$paymentQrIssue = getPaymentQrConfigIssue();

$courts = mysqli_query($conn, "SELECT * FROM courts WHERE status = 'active' ORDER BY id ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courtId = (int) ($_POST['court_id'] ?? 0);
    $bookingDate = $_POST['booking_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $action = $_POST['action'] ?? 'book';

    if (!in_array($paymentMethod, ['cash', 'bank_transfer'], true)) {
        $paymentMethod = 'cash';
    }

    if (isPastDate($bookingDate)) {
        $error = 'Không thể đặt sân trong ngày đã qua.';
    } elseif (!isValidBookingTime($startTime, $endTime)) {
        $error = 'Chỉ được đặt từ 04:00 đến 22:00, theo từng giờ tròn.';
    } else {
        $previewPrice = calculateBookingPrice($bookingDate, $startTime, $endTime);
        $paymentReference = 'BOOKING-' . $_SESSION['user']['id'] . '-' . date('YmdHis');

        if ($paymentMethod === 'bank_transfer') {
            $qrCodeUrl = buildPaymentQrUrl($previewPrice, $paymentReference);
            $paymentQrIssue = getPaymentQrConfigIssue();
        }

        if ($action === 'book') {
            if (!checkCourtAvailable($conn, $courtId, $bookingDate, $startTime, $endTime)) {
                $error = 'Sân đã được đặt trong khung giờ này.';
            } else {
                $insertSql = "INSERT INTO bookings (
                                    user_id, court_id, booking_date, start_time, end_time,
                                    total_price, payment_method, payment_reference, payment_status, status
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'confirmed')";
                $insertStmt = mysqli_prepare($conn, $insertSql);
                mysqli_stmt_bind_param(
                    $insertStmt,
                    'iisssdss',
                    $_SESSION['user']['id'],
                    $courtId,
                    $bookingDate,
                    $startTime,
                    $endTime,
                    $previewPrice,
                    $paymentMethod,
                    $paymentReference
                );
                mysqli_stmt_execute($insertStmt);

                $bookingId = mysqli_insert_id($conn);
                $paymentReference = 'BOOKING-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT);

                $updateSql = "UPDATE bookings SET payment_reference = ? WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param($updateStmt, 'si', $paymentReference, $bookingId);
                mysqli_stmt_execute($updateStmt);

                if ($paymentMethod === 'bank_transfer') {
                    $qrCodeUrl = buildPaymentQrUrl($previewPrice, $paymentReference);
                    $paymentQrIssue = getPaymentQrConfigIssue();
                }

                $success = 'Đặt sân thành công. Số tiền cần thanh toán: '
                    . formatMoney($previewPrice)
                    . '. Phương thức: '
                    . getPaymentMethodLabel($paymentMethod);
            }
        }
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="booking-hero">
    <div class="booking-hero-copy">
        <span class="booking-eyebrow">Booking Center</span>
        <h2>Đặt sân cầu lông nhanh, rõ lịch, đúng khung giờ</h2>
        <p>
            Chọn sân, ngày, khung giờ và phương thức thanh toán theo nhu cầu.
            Hệ thống sẽ kiểm tra hợp lệ, báo giá và hỗ trợ thanh toán trực tiếp nếu bạn chọn chuyển khoản.
        </p>

        <div class="booking-hero-points">
            <span>8 sân hoạt động</span>
            <span>Giờ mở cửa 04:00 - 22:00</span>
            <span>Hỗ trợ tiền mặt hoặc chuyển khoản</span>
        </div>
    </div>

    <div class="booking-hero-panel">
        <div class="booking-hero-stat">
            <strong>Khung giờ cao điểm</strong>
            <span>17:00 - 22:00</span>
        </div>
        <div class="booking-hero-stat">
            <strong>Giá từ</strong>
            <span>90.000 VNĐ / giờ</span>
        </div>
        <div class="booking-hero-stat">
            <strong>Thanh toán</strong>
            <span>Tiền mặt / QR</span>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="booking-layout">
    <div class="form-box booking-form-card">
        <div class="booking-form-head">
            <div>
                <h3>Tạo đơn đặt sân</h3>
                <p>Điền đủ thông tin để xem giá, chọn cách thanh toán và xác nhận đặt sân.</p>
            </div>
            <span class="booking-head-badge">Đang nhận lịch</span>
        </div>

        <form method="POST" class="booking-form-grid">
            <div class="booking-field booking-field-full">
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
            </div>

            <div class="booking-field">
                <label>Ngày đặt</label>
                <input type="date" name="booking_date" required min="<?= date('Y-m-d') ?>" value="<?= isset($_POST['booking_date']) ? e($_POST['booking_date']) : '' ?>">
            </div>

            <div class="booking-field">
                <label>Giờ bắt đầu</label>
                <select name="start_time" required>
                    <option value="">-- Chọn giờ bắt đầu --</option>
                    <?php for ($h = 4; $h < 22; $h++): $time = sprintf('%02d:00', $h); ?>
                        <option value="<?= $time ?>" <?= (isset($_POST['start_time']) && $_POST['start_time'] === $time) ? 'selected' : '' ?>><?= $time ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="booking-field">
                <label>Giờ kết thúc</label>
                <select name="end_time" required>
                    <option value="">-- Chọn giờ kết thúc --</option>
                    <?php for ($h = 5; $h <= 22; $h++): $time = sprintf('%02d:00', $h); ?>
                        <option value="<?= $time ?>" <?= (isset($_POST['end_time']) && $_POST['end_time'] === $time) ? 'selected' : '' ?>><?= $time ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="booking-field booking-field-full">
                <label>Phương thức thanh toán</label>
                <div class="payment-methods">
                    <label class="payment-method-card">
                        <input type="radio" name="payment_method" value="cash" <?= $paymentMethod === 'cash' ? 'checked' : '' ?>>
                        <span>
                            <strong>Tiền mặt</strong>
                            <small>Thanh toán trực tiếp tại sân</small>
                        </span>
                    </label>

                    <label class="payment-method-card">
                        <input type="radio" name="payment_method" value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'checked' : '' ?>>
                        <span>
                            <strong>Chuyển khoản</strong>
                            <small>Quét QR để thanh toán nhanh</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="booking-form-actions booking-field-full">
                <button type="submit" name="action" value="preview" class="button-secondary booking-secondary-action">Xem tiền</button>
                <button type="submit" name="action" value="book">Đặt sân</button>
            </div>
        </form>
    </div>

    <div class="booking-side-panel">
        <div class="card booking-summary-card">
            <div class="booking-summary-label">Tạm tính</div>
            <div class="booking-summary-number">
                <?= $previewPrice !== null ? formatMoney($previewPrice) : 'Chưa có dữ liệu' ?>
            </div>
            <p>
                <?= $previewPrice !== null
                    ? 'Chi phí được tính theo ngày, khung giờ và sẵn sàng cho phương thức thanh toán bạn đã chọn.'
                    : 'Chọn sân, ngày, giờ và phương thức thanh toán rồi bấm "Xem tiền".' ?>
            </p>
        </div>

        <div class="card booking-payment-card">
            <h3>Thanh toán</h3>
            <?php if ($paymentMethod === 'cash'): ?>
                <p class="payment-method-note">Bạn sẽ thanh toán trực tiếp bằng tiền mặt tại sân khi đến chơi.</p>
            <?php elseif (!isPaymentQrConfigured()): ?>
                <p class="payment-method-note"><?= e($paymentQrIssue ?? 'Chưa cấu hình thông tin nhận chuyển khoản.') ?></p>
                <p class="payment-method-note">Hãy mở <code>config/payment.php</code> và điền đúng <code>bank_id</code>, <code>account_no</code>, <code>account_name</code>.</p>
            <?php else: ?>
                <p class="payment-method-note">Quét mã QR bên dưới để chuyển khoản trực tiếp theo số tiền tạm tính.</p>
                <?php if ($paymentReference): ?>
                    <p class="payment-reference">Nội dung chuyển khoản: <strong><?= e($paymentReference) ?></strong></p>
                <?php endif; ?>
                <?php if ($qrCodeUrl): ?>
                    <div class="payment-qr-wrap">
                        <img src="<?= e($qrCodeUrl) ?>" alt="QR chuyển khoản đặt sân">
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card booking-price-card">
            <h3>Bảng giá</h3>
            <div class="booking-price-lines">
                <div class="booking-price-line">
                    <span>Thứ 2 - Thứ 6</span>
                    <strong>04:00 - 16:00 • 90.000 VNĐ / giờ</strong>
                </div>
                <div class="booking-price-line">
                    <span>Thứ 2 - Thứ 6</span>
                    <strong>17:00 - 22:00 • 120.000 VNĐ / giờ</strong>
                </div>
                <div class="booking-price-line">
                    <span>Thứ 7 - Chủ nhật</span>
                    <strong>04:00 - 22:00 • 120.000 VNĐ / giờ</strong>
                </div>
            </div>
        </div>

        <div class="card booking-note-card">
            <h3>Lưu ý đặt sân</h3>
            <ul class="info-list compact-info-list">
                <li>Chỉ hỗ trợ khung giờ tròn từ 04:00 đến 22:00.</li>
                <li>Hệ thống sẽ từ chối nếu sân đã có người đặt.</li>
                <li>Nếu chọn chuyển khoản, hãy dùng đúng nội dung chuyển khoản để dễ đối soát.</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
