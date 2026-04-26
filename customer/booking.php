<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'payment_status') {
    header('Content-Type: application/json; charset=utf-8');

    $bookingId = (int) ($_GET['booking_id'] ?? 0);
    $userId = (int) ($_SESSION['user']['id'] ?? 0);

    if ($bookingId <= 0 || $userId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Thiếu dữ liệu',
        ]);
        exit();
    }

    $statusSql = "SELECT id, payment_status, status, payment_method
                  FROM bookings
                  WHERE id = ? AND user_id = ?
                  LIMIT 1";
    $statusStmt = mysqli_prepare($conn, $statusSql);
    mysqli_stmt_bind_param($statusStmt, 'ii', $bookingId, $userId);
    mysqli_stmt_execute($statusStmt);
    $statusResult = mysqli_stmt_get_result($statusStmt);
    $statusBooking = mysqli_fetch_assoc($statusResult);

    if (!$statusBooking) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy booking',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'payment_status' => $statusBooking['payment_status'],
        'status' => $statusBooking['status'],
        'payment_method' => $statusBooking['payment_method'],
    ]);
    exit();
}

$error = '';
$success = '';
$previewPrice = null;
$paymentDueNow = null;
$qrCodeUrl = null;
$paymentReference = null;
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$paymentQrIssue = getPaymentQrConfigIssue();
$currentBooking = null;
$currentBookingId = (int) ($_GET['booking_id'] ?? 0);

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
        $paymentDueNow = getBookingImmediatePaymentAmount($previewPrice, $paymentMethod);

        if ($action === 'book') {
            if (!checkCourtAvailable($conn, $courtId, $bookingDate, $startTime, $endTime)) {
                $error = 'Sân đã được đặt trong khung giờ này.';
            } else {
                $temporaryReference = 'BK' . date('His');

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
                    $temporaryReference
                );
                mysqli_stmt_execute($insertStmt);

                $bookingId = mysqli_insert_id($conn);
                $paymentReference = 'BK' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT);

                $updateSql = "UPDATE bookings SET payment_reference = ? WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param($updateStmt, 'si', $paymentReference, $bookingId);
                mysqli_stmt_execute($updateStmt);

                header('Location: /badminton-manager/customer/booking.php?booking_id=' . $bookingId . '&created=1');
                exit();
            }
        }
    }
}

if ($currentBookingId > 0) {
    $bookingSql = "SELECT *
                   FROM bookings
                   WHERE id = ? AND user_id = ?
                   LIMIT 1";
    $bookingStmt = mysqli_prepare($conn, $bookingSql);
    mysqli_stmt_bind_param($bookingStmt, 'ii', $currentBookingId, $_SESSION['user']['id']);
    mysqli_stmt_execute($bookingStmt);
    $bookingResult = mysqli_stmt_get_result($bookingStmt);
    $currentBooking = mysqli_fetch_assoc($bookingResult);

    if (!$currentBooking) {
        $error = 'Không tìm thấy booking vừa tạo.';
        $currentBookingId = 0;
    } else {
        $previewPrice = (float) $currentBooking['total_price'];
        $paymentMethod = $currentBooking['payment_method'];
        $paymentReference = $currentBooking['payment_reference'];
        $paymentDueNow = getBookingImmediatePaymentAmount($previewPrice, $paymentMethod);

        if ($currentBooking['payment_status'] !== 'paid' && isPaymentQrConfigured()) {
            $qrCodeUrl = buildPaymentQrUrl($paymentDueNow, $paymentReference);
            $paymentQrIssue = getPaymentQrConfigIssue();
        }

        if (isset($_GET['created']) && $_GET['created'] === '1') {
            $success = 'Đặt sân thành công. Số tiền cần thanh toán ngay: '
                . formatMoney($paymentDueNow)
                . '. Phương thức: '
                . getPaymentMethodLabel($paymentMethod);
        }
    }
}

require_once '../includes/header.php';
?>

<style>
.payment-success-box {
    text-align: center;
    padding: 28px 20px;
    border-radius: 16px;
    background: #eefcf3;
    border: 1px solid #b7ebc6;
}

.payment-success-icon {
    width: 76px;
    height: 76px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: #22c55e;
    color: #fff;
    font-size: 42px;
    font-weight: 700;
    line-height: 76px;
}

.payment-success-title {
    font-size: 24px;
    font-weight: 700;
    color: #15803d;
    margin-bottom: 8px;
}

.payment-success-text {
    color: #334155;
    font-size: 15px;
}

.payment-status-waiting {
    margin-top: 14px;
    color: #64748b;
    font-size: 14px;
    text-align: center;
}

.payment-status-box {
    transition: all 0.25s ease;
}

.payment-summary-lines {
    display: grid;
    gap: 8px;
    margin: 14px 0;
    color: #334155;
    font-size: 14px;
}

.payment-summary-lines strong {
    color: #0f172a;
}
</style>

<div class="booking-hero">
    <div class="booking-hero-copy">
        <span class="booking-eyebrow">Booking Center</span>
        <h2>Đặt sân cầu lông nhanh, rõ lịch, đúng khung giờ</h2>
        <p>
            Chọn sân, ngày, khung giờ và phương thức thanh toán theo nhu cầu.
            Nếu chọn tiền mặt, hệ thống yêu cầu cọc 30%. Nếu chọn chuyển khoản,
            hệ thống yêu cầu thanh toán toàn bộ giá trị booking.
        </p>

        <div class="booking-hero-points">
            <span>8 sân hoạt động</span>
            <span>Giờ mở cửa 04:00 - 22:00</span>
            <span>Hỗ trợ tiền mặt đặt cọc hoặc chuyển khoản toàn bộ</span>
        </div>
    </div>

    <div class="booking-hero-panel">
        <div class="booking-hero-stat">
            <strong>Khung giờ cao điểm</strong>
            <span>17:00 - 22:00</span>
        </div>
        <div class="booking-hero-stat">
            <strong>Giá từ</strong>
            <span>90.000 VND / gio</span>
        </div>
        <div class="booking-hero-stat">
            <strong>Thanh toán</strong>
            <span>Coc 30% / QR 100%</span>
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
                <h3>Tao don dat san</h3>
                <p>Điền đủ thông tin để xem tổng tiền, số tiền cần thanh toán ngay và tạo booking.</p>
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
                        <option value="<?= (int) $court['id'] ?>" <?= (isset($_POST['court_id']) && (int) $_POST['court_id'] === (int) $court['id']) ? 'selected' : '' ?>>
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
                            <small>Cọc 30%, phần còn lại thanh toán tại sân</small>
                        </span>
                    </label>

                    <label class="payment-method-card">
                        <input type="radio" name="payment_method" value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'checked' : '' ?>>
                        <span>
                            <strong>Chuyển khoản</strong>
                            <small>Thanh toán toàn bộ bằng QR</small>
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
            <div class="booking-summary-label">Tổng giá trị booking</div>
            <div class="booking-summary-number">
                <?= $previewPrice !== null ? formatMoney($previewPrice) : 'Chưa có dữ liệu' ?>
            </div>
            <p>
                <?= $previewPrice !== null
                    ? 'Hệ thống giữ nguyên tổng giá trị booking và tính riêng số tiền cần thanh toán ngay theo phương thức bạn chọn.'
                    : 'Chọn sân, ngày, giờ và phương thức thanh toán rồi bấm "Xem tiền".' ?>
            </p>
            <?php if ($paymentDueNow !== null): ?>
                <div class="payment-summary-lines">
                    <div>Cần thanh toán ngay: <strong><?= formatMoney($paymentDueNow) ?></strong></div>
                    <?php if ($paymentMethod === 'cash'): ?>
                        <div>Còn lại thanh toán tại sân: <strong><?= formatMoney(getBookingRemainingCashAmount($previewPrice, $paymentMethod)) ?></strong></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div
            class="card booking-payment-card payment-status-box"
            id="payment-status-box"
            <?= $currentBooking ? 'data-booking-id="' . (int) $currentBooking['id'] . '"' : '' ?>
        >
            <h3>Thanh toán</h3>

            <?php if (!isPaymentQrConfigured()): ?>
                <p class="payment-method-note"><?= e($paymentQrIssue ?? 'Chưa cấu hình thông tin nhận chuyển khoản.') ?></p>
                <p class="payment-method-note">Hãy mở <code>config/payment.php</code> và điền đúng <code>bank_id</code>, <code>account_no</code>, <code>account_name</code>.</p>

            <?php elseif ($currentBooking && $currentBooking['payment_status'] === 'paid'): ?>
                <div class="payment-success-box">
                    <div class="payment-success-icon">✓</div>
                    <div class="payment-success-title">
                        <?= $paymentMethod === 'cash' ? 'Đã nhận cọc 30%' : 'Đã nhận thanh toán' ?>
                    </div>
                    <div class="payment-success-text">
                        Booking <strong><?= e($paymentReference) ?></strong> đã được xác nhận.
                        <?php if ($paymentMethod === 'cash'): ?>
                            Phần còn lại thanh toán tại sân.
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($qrCodeUrl && $paymentReference): ?>
                <p class="payment-method-note">
                    <?php if ($paymentMethod === 'cash'): ?>
                        Quét QR để thanh toán tiền cọc 30% cho booking vừa tạo.
                    <?php else: ?>
                        Quét QR để thanh toán toàn bộ giá trị booking.
                    <?php endif; ?>
                </p>
                <div class="payment-summary-lines">
                    <div>Tổng giá trị booking: <strong><?= formatMoney($previewPrice) ?></strong></div>
                    <div>Số tiền cần thanh toán ngay: <strong><?= formatMoney($paymentDueNow) ?></strong></div>
                    <?php if ($paymentMethod === 'cash'): ?>
                        <div>Còn lại trả tại sân: <strong><?= formatMoney(getBookingRemainingCashAmount($previewPrice, $paymentMethod)) ?></strong></div>
                    <?php endif; ?>
                </div>
                <p class="payment-reference">Nội dung chuyển khoản: <strong><?= e($paymentReference) ?></strong></p>
                <div class="payment-qr-wrap">
                    <img src="<?= e($qrCodeUrl) ?>" alt="QR thanh toán booking">
                </div>
                <div class="payment-status-waiting">Đang chờ SePay xác nhận thanh toán...</div>

            <?php elseif ($previewPrice !== null): ?>
                <p class="payment-method-note">
                    Hệ thống đã tính xong tổng tiền và số tiền cần thanh toán ngay.
                    Bấm <strong>Đặt sân</strong> để tạo booking và hiện QR thanh toán.
                </p>

            <?php else: ?>
                <p class="payment-method-note">
                    Chọn phương thức thanh toán và bấm <strong>Xem tiền</strong> hoặc <strong>Đặt sân</strong> để tiếp tục.
                </p>
            <?php endif; ?>
        </div>

        <div class="card booking-price-card">
            <h3>Bảng giá</h3>
            <div class="booking-price-lines">
                <div class="booking-price-line">
                    <span>Thứ 2 - Thứ 6</span>
                    <strong>04:00 - 16:00 • 90.000 VND / giờ</strong>
                </div>
                <div class="booking-price-line">
                    <span>Thứ 2 - Thứ 6</span>
                    <strong>17:00 - 22:00 • 120.000 VND / giờ</strong>
                </div>
                <div class="booking-price-line">
                    <span>Thứ 7 - Chủ nhật</span>
                    <strong>04:00 - 22:00 • 120.000 VND / giờ</strong>
                </div>
            </div>
        </div>

        <div class="card booking-note-card">
            <h3>Lưu ý đặt sân</h3>
            <ul class="info-list compact-info-list">
                <li>Chỉ hỗ trợ khung giờ tròn từ 04:00 đến 22:00.</li>
                <li>Hệ thống sẽ từ chối nếu sân đã có người đặt.</li>
                <li>Nếu chọn tiền mặt, bạn cần cọc 30% để giữ lịch.</li>
                <li>Nếu chọn chuyển khoản, bạn cần chuyển đủ tổng tiền và đúng nội dung booking.</li>
            </ul>
        </div>
    </div>
</div>

<?php if ($currentBooking && $currentBooking['payment_status'] !== 'paid' && $qrCodeUrl): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentBox = document.getElementById('payment-status-box');
    if (!paymentBox) return;

    const bookingId = paymentBox.dataset.bookingId;
    if (!bookingId) return;

    let checkedPaid = false;

    async function checkPaymentStatus() {
        if (checkedPaid) return;

        try {
            const response = await fetch('booking.php?ajax=payment_status&booking_id=' + bookingId + '&_=' + Date.now());
            const data = await response.json();

            if (data.success && data.payment_status === 'paid') {
                checkedPaid = true;

                paymentBox.innerHTML = `
                    <h3>Thanh toán</h3>
                    <div class="payment-success-box">
                        <div class="payment-success-icon">✓</div>
                        <div class="payment-success-title"><?= $paymentMethod === 'cash' ? 'Đã nhận cọc 30%' : 'Đã nhận thanh toán' ?></div>
                        <div class="payment-success-text">
                            Booking <strong><?= e($paymentReference) ?></strong> đã được xác nhận<?= $paymentMethod === 'cash' ? '. Phần còn lại thanh toán tại sân.' : '.' ?>
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            console.log('Lỗi kiểm tra trạng thái thanh toán:', error);
        }
    }

    setInterval(checkPaymentStatus, 3000);
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
