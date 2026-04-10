<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/header.php';

$courts = mysqli_query($conn, "SELECT * FROM courts ORDER BY id ASC");
?>

<div class="page-title">
    <h2>Tổng quan 8 sân</h2>
    <a class="button" href="/badminton-manager/customer/booking.php">+ Đặt sân ngay</a>
</div>

<div class="court-grid">
    <?php while ($court = mysqli_fetch_assoc($courts)): ?>
        <div class="card">
            <h3><?= e($court['name']) ?></h3>
            <p style="margin: 10px 0 14px 0;">
                Trạng thái:
                <strong><?= $court['status'] === 'active' ? 'Hoạt động' : 'Bảo trì' ?></strong>
            </p>
            <a class="button" href="/badminton-manager/customer/court_detail.php?id=<?= $court['id'] ?>">Xem lịch sân</a>
        </div>
    <?php endwhile; ?>
</div>

<?php require_once '../includes/footer.php'; ?>