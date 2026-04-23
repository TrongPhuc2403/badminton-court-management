<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ensureReviewTables($conn);
require_once '../includes/header.php';

$courtId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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
    $start = (int) date('H', strtotime($row['start_time']));
    $end = (int) date('H', strtotime($row['end_time']));
    for ($h = $start; $h < $end; $h++) {
        $bookedHours[$h] = true;
    }
}
mysqli_data_seek($bookingResult, 0);

$reviewSummarySql = "SELECT
                        ROUND(AVG(overall_rating), 1) AS avg_rating,
                        ROUND(AVG(court_quality_rating), 1) AS avg_court_quality,
                        ROUND(AVG(lighting_rating), 1) AS avg_lighting,
                        ROUND(AVG(service_rating), 1) AS avg_service,
                        COUNT(*) AS review_count
                     FROM court_reviews
                     WHERE court_id = ?";
$reviewSummaryStmt = mysqli_prepare($conn, $reviewSummarySql);
mysqli_stmt_bind_param($reviewSummaryStmt, "i", $courtId);
mysqli_stmt_execute($reviewSummaryStmt);
$reviewSummary = mysqli_fetch_assoc(mysqli_stmt_get_result($reviewSummaryStmt));

$recentReviewsSql = "SELECT cr.*, u.full_name
                     FROM court_reviews cr
                     JOIN users u ON u.id = cr.user_id
                     WHERE cr.court_id = ?
                     ORDER BY cr.created_at DESC
                     LIMIT 6";
$recentReviewsStmt = mysqli_prepare($conn, $recentReviewsSql);
mysqli_stmt_bind_param($recentReviewsStmt, "i", $courtId);
mysqli_stmt_execute($recentReviewsStmt);
$recentReviews = mysqli_stmt_get_result($recentReviewsStmt);
?>

<div class="page-title">
    <div>
        <h2><?= e($court['name']) ?> - Lịch đặt</h2>
        <p class="page-subtitle">Xem khung giờ đã đặt trong ngày và tham khảo đánh giá thực tế từ người chơi.</p>
    </div>
</div>

<div class="review-overview-grid">
    <div class="card review-highlight-card">
        <h3>Điểm đánh giá trung bình</h3>
        <?php if ((int) ($reviewSummary['review_count'] ?? 0) > 0): ?>
            <div class="review-highlight-score"><?= formatRating($reviewSummary['avg_rating']) ?>/5</div>
            <div class="review-highlight-stars"><?= e(renderStars($reviewSummary['avg_rating'])) ?></div>
            <p><?= (int) $reviewSummary['review_count'] ?> lượt đánh giá từ khách đã chơi tại sân.</p>
        <?php else: ?>
            <div class="review-highlight-score">-</div>
            <p>Chưa có đánh giá nào cho sân này.</p>
        <?php endif; ?>
    </div>

    <div class="card review-metric-card">
        <h3>Chi tiết chất lượng</h3>
        <div class="review-metric-list">
            <div class="review-metric-item">
                <span>Chất lượng sân</span>
                <strong><?= (int) ($reviewSummary['review_count'] ?? 0) > 0 ? formatRating($reviewSummary['avg_court_quality']) . '/5' : '-' ?></strong>
            </div>
            <div class="review-metric-item">
                <span>Ánh sáng</span>
                <strong><?= (int) ($reviewSummary['review_count'] ?? 0) > 0 ? formatRating($reviewSummary['avg_lighting']) . '/5' : '-' ?></strong>
            </div>
            <div class="review-metric-item">
                <span>Dịch vụ</span>
                <strong><?= (int) ($reviewSummary['review_count'] ?? 0) > 0 ? formatRating($reviewSummary['avg_service']) . '/5' : '-' ?></strong>
            </div>
        </div>
    </div>
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

<div class="card review-list-card">
    <div class="support-section-head">
        <div>
            <h3>Đánh giá gần đây</h3>
            <p>Nhận xét thực tế từ người dùng sau khi hoàn thành lượt chơi.</p>
        </div>
    </div>

    <div class="review-comment-list">
        <?php if (mysqli_num_rows($recentReviews) > 0): ?>
            <?php while ($review = mysqli_fetch_assoc($recentReviews)): ?>
                <div class="review-comment-item">
                    <div class="review-comment-head">
                        <strong><?= e($review['full_name']) ?></strong>
                        <span><?= e(renderStars($review['overall_rating'])) ?> • <?= formatRating($review['overall_rating']) ?>/5</span>
                    </div>
                    <div class="review-submetrics">
                        <small>Sân: <?= formatRating($review['court_quality_rating']) ?>/5</small>
                        <small>Ánh sáng: <?= formatRating($review['lighting_rating']) ?>/5</small>
                        <small>Dịch vụ: <?= formatRating($review['service_rating']) ?>/5</small>
                    </div>
                    <p><?= $review['comment'] !== '' ? nl2br(e($review['comment'])) : 'Khách hàng không để lại bình luận.' ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="support-empty-box">Chưa có nhận xét nào cho sân này.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
