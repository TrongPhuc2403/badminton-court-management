<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/header.php';

$courts = mysqli_query($conn, "SELECT * FROM courts ORDER BY id ASC");
$courtItems = [];
$index = 1;
$venueAddress = "Số 28 đường Tạ Quang Bửu, Phường Chánh Hưng, TP. Hồ Chí Minh";
$destinationQuery = urlencode($venueAddress);

while ($court = mysqli_fetch_assoc($courts)) {
    $imagePath = !empty($court['image_path'])
        ? '/badminton-manager/' . e($court['image_path'])
        : '/badminton-manager/assets/san-cau.jpg';

    ob_start();
    ?>
    <div class="card court-card-modern">
        <div class="court-thumb">
            <img src="<?= $imagePath ?>" alt="<?= e($court['name']) ?>">
        </div>

        <div class="court-card-body">
            <h4>Sân <?= $index ?></h4>

            <div class="court-mini-slots">
                <span>A</span>
                <span>B</span>
                <span>C</span>
                <span>D</span>
            </div>

            <p class="court-status-line">
                Trạng thái:
                <span class="status-pill <?= $court['status'] === 'active' ? 'status-active' : 'status-maintenance' ?>">
                    <?= $court['status'] === 'active' ? 'Hoạt động' : 'Bảo trì' ?>
                </span>
            </p>

            <a class="button full-width-button" href="/badminton-manager/customer/court_detail.php?id=<?= $court['id'] ?>">
                Xem lịch sân
            </a>
        </div>
    </div>
    <?php
    $courtItems[] = ob_get_clean();
    $index++;
}
?>

<div class="page-title booking-page-title booking-page-title-reference">
    <div>
        <h2>Đặt sân cầu lông</h2>
    </div>
    <a class="button booking-cta" href="/badminton-manager/customer/booking.php">+ Đặt sân ngay</a>
</div>

<div class="booking-overview booking-overview-reference">
    <div class="info-card">
        <h3><span class="section-title-icon">🗂</span>Hướng dẫn</h3>
        <ul class="info-list">
            <li>Chọn một sân để xem lịch và đặt sân.</li>
            <li>Mỗi sân có 4 sân cầu (A, B, C, D).</li>
        </ul>
    </div>

    <div class="info-card">
        <h3>Bảng giá</h3>
        <ul class="info-list">
            <li>Thứ 2 - Thứ 6, 04:00 - 16:00: 90.000 VNĐ / giờ</li>
            <li>Thứ 2 - Thứ 6, 17:00 - 22:00: 120.000 VNĐ / giờ</li>
            <li>Thứ 7 - Chủ nhật, 04:00 - 22:00: 120.000 VNĐ / giờ</li>
        </ul>
    </div>
</div>

<div class="section-card venue-section">
    <div class="section-header booking-list-header">
        <h3><span class="section-title-icon">📍</span>Vị trí sân</h3>
    </div>

    <div class="venue-layout">
        <div class="venue-info">
            <p class="venue-address"><?= e($venueAddress) ?></p>
            <p class="venue-note">Khách hàng có thể mở bản đồ hoặc dùng định vị hiện tại để xem đường đi nhanh đến sân.</p>

            <div class="venue-actions">
                <a class="button" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&query=<?= $destinationQuery ?>">
                    Xem trên Google Maps
                </a>
                <button type="button" class="button-secondary venue-route-button" onclick="openVenueDirections()">
                    Dẫn đường từ vị trí của tôi
                </button>
            </div>
        </div>

        <div class="venue-map-card">
            <iframe
                title="Bản đồ sân cầu lông"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps?q=<?= $destinationQuery ?>&output=embed">
            </iframe>
        </div>
    </div>
</div>

<div class="section-card booking-list-section">
    <div class="section-header booking-list-header">
        <h3><span class="section-title-icon">⊞</span>8 sân cầu lông</h3>
    </div>

    <div class="booking-court-rows">
        <?php foreach (array_chunk($courtItems, 4) as $courtRow): ?>
            <div class="booking-court-grid">
                <?php foreach ($courtRow as $courtItem): ?>
                    <?= $courtItem ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function openVenueDirections() {
  var destination = "<?= e($venueAddress) ?>";

  if (!navigator.geolocation) {
    window.open("https://www.google.com/maps/dir/?api=1&destination=" + encodeURIComponent(destination), "_blank");
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function(position) {
      var origin = position.coords.latitude + "," + position.coords.longitude;
      var url = "https://www.google.com/maps/dir/?api=1&origin=" + encodeURIComponent(origin) + "&destination=" + encodeURIComponent(destination) + "&travelmode=driving";
      window.open(url, "_blank");
    },
    function() {
      var fallbackUrl = "https://www.google.com/maps/dir/?api=1&destination=" + encodeURIComponent(destination);
      window.open(fallbackUrl, "_blank");
    },
    {
      enableHighAccuracy: true,
      timeout: 10000
    }
  );
}
</script>

<?php require_once '../includes/footer.php'; ?>
