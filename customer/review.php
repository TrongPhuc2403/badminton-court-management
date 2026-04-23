<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ensureReviewTables($conn);

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$error = '';
$success = '';

$sql = "SELECT
            b.*,
            c.name AS court_name,
            cr.id AS review_id,
            cr.overall_rating,
            cr.court_quality_rating,
            cr.lighting_rating,
            cr.service_rating,
            cr.comment
        FROM bookings b
        JOIN courts c ON c.id = b.court_id
        LEFT JOIN court_reviews cr ON cr.booking_id = b.id
        WHERE b.id = ? AND b.user_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $_SESSION['user']['id']);
mysqli_stmt_execute($stmt);
$bookingResult = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($bookingResult);

if (!$booking) {
    header('Location: /badminton-manager/customer/my_bookings.php');
    exit();
}

$overallRating = (int) ($booking['overall_rating'] ?: 5);
$courtQualityRating = (int) ($booking['court_quality_rating'] ?: 5);
$lightingRating = (int) ($booking['lighting_rating'] ?: 5);
$serviceRating = (int) ($booking['service_rating'] ?: 5);
$comment = (string) ($booking['comment'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($booking['status'] !== 'confirmed') {
        $error = 'Chỉ booking đã xác nhận mới được đánh giá.';
    } elseif (!bookingCanBeReviewed($booking['booking_date'], $booking['end_time'])) {
        $error = 'Bạn chỉ có thể đánh giá sau khi khung giờ chơi đã kết thúc.';
    } else {
        $overallRating = normalizeRatingValue($_POST['overall_rating'] ?? 5);
        $courtQualityRating = normalizeRatingValue($_POST['court_quality_rating'] ?? 5);
        $lightingRating = normalizeRatingValue($_POST['lighting_rating'] ?? 5);
        $serviceRating = normalizeRatingValue($_POST['service_rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');

        if ($booking['review_id']) {
            $updateSql = "UPDATE court_reviews
                          SET overall_rating = ?, court_quality_rating = ?, lighting_rating = ?, service_rating = ?, comment = ?
                          WHERE booking_id = ? AND user_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param(
                $updateStmt,
                'iiiisii',
                $overallRating,
                $courtQualityRating,
                $lightingRating,
                $serviceRating,
                $comment,
                $bookingId,
                $_SESSION['user']['id']
            );
            mysqli_stmt_execute($updateStmt);
        } else {
            $insertSql = "INSERT INTO court_reviews (
                            booking_id, user_id, court_id, overall_rating,
                            court_quality_rating, lighting_rating, service_rating, comment
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param(
                $insertStmt,
                'iiiiiiis',
                $bookingId,
                $_SESSION['user']['id'],
                $booking['court_id'],
                $overallRating,
                $courtQualityRating,
                $lightingRating,
                $serviceRating,
                $comment
            );
            mysqli_stmt_execute($insertStmt);
        }

        header('Location: /badminton-manager/customer/my_bookings.php?success=review_saved');
        exit();
    }
}

require_once '../includes/header.php';
?>

<div class="page-title">
    <div>
        <h2>Đánh giá sân và dịch vụ</h2>
        <p class="page-subtitle"><?= e($booking['court_name']) ?> • <?= e($booking['booking_date']) ?> • <?= e($booking['start_time']) ?> - <?= e($booking['end_time']) ?></p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($booking['status'] !== 'confirmed' || !bookingCanBeReviewed($booking['booking_date'], $booking['end_time'])): ?>
    <div class="card">
        <h3>Chưa thể đánh giá</h3>
        <p class="text-muted">Đánh giá chỉ mở sau khi lượt chơi kết thúc và booking vẫn ở trạng thái đã xác nhận.</p>
    </div>
<?php else: ?>
    <div class="form-box review-form-box">
        <form method="POST" class="review-form-grid">
            <input type="hidden" name="booking_id" value="<?= (int) $bookingId ?>">

            <div class="review-rating-card">
                <label>Chấm sao tổng thể</label>
                <select name="overall_rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $overallRating === $i ? 'selected' : '' ?>><?= $i ?> sao</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="review-rating-card">
                <label>Chất lượng sân</label>
                <select name="court_quality_rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $courtQualityRating === $i ? 'selected' : '' ?>><?= $i ?> sao</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="review-rating-card">
                <label>Ánh sáng</label>
                <select name="lighting_rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $lightingRating === $i ? 'selected' : '' ?>><?= $i ?> sao</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="review-rating-card">
                <label>Dịch vụ</label>
                <select name="service_rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $serviceRating === $i ? 'selected' : '' ?>><?= $i ?> sao</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="review-comment-field">
                <label>Bình luận</label>
                <textarea name="comment" rows="6" placeholder="Chia sẻ trải nghiệm thực tế của bạn về sân, ánh sáng và chất lượng phục vụ..."><?= e($comment) ?></textarea>
            </div>

            <div class="review-actions">
                <button type="submit"><?= $booking['review_id'] ? 'Cập nhật đánh giá' : 'Gửi đánh giá' ?></button>
                <a class="button-secondary" href="/badminton-manager/customer/my_bookings.php">Quay lại</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
