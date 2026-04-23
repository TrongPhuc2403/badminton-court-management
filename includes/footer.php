<?php
$supportPhone = '0941473515';
$supportEmail = 'hotrodatsan@gmail.com';
$supportAddress = 'Số 28 đường Tạ Quang Bửu, phường Chánh Hưng, Thành phố Hồ Chí Minh';
$currentYear = date('Y');

$discoverLinks = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'
    ? [
        ['label' => 'Tổng quan', 'href' => '/badminton-manager/admin/index.php'],
        ['label' => 'Quản lí đặt sân', 'href' => '/badminton-manager/admin/bookings.php'],
        ['label' => 'Báo cáo', 'href' => '/badminton-manager/admin/reports.php'],
        ['label' => 'Hỗ trợ khách hàng', 'href' => '/badminton-manager/admin/support.php'],
    ]
    : [
        ['label' => 'Trang chủ', 'href' => '/badminton-manager/customer/home.php'],
        ['label' => 'Đặt sân ngay', 'href' => '/badminton-manager/customer/booking.php'],
        ['label' => 'Lịch sử đặt sân', 'href' => '/badminton-manager/customer/my_bookings.php'],
        ['label' => 'Đánh giá sân', 'href' => '/badminton-manager/customer/my_bookings.php'],
    ];

$supportLinks = [
    ['label' => 'Hướng dẫn đặt sân', 'href' => '/badminton-manager/customer/booking.php'],
    ['label' => 'Hỗ trợ trực tuyến', 'href' => '/badminton-manager/customer/support.php'],
    ['label' => 'Câu hỏi thường gặp', 'href' => '/badminton-manager/customer/support.php?faq=' . urlencode('Cách đặt sân trên hệ thống?')],
    ['label' => 'Chính sách hủy sân', 'href' => '/badminton-manager/customer/support.php?faq=' . urlencode('Chính sách hủy sân ra sao?')],
];
?>
<?php if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])): ?>
        </div>

        <footer class="site-footer">
            <div class="site-footer-grid">
                <div class="site-footer-brand">
                    <a class="site-footer-logo" href="<?= $_SESSION['user']['role'] === 'admin' ? '/badminton-manager/admin/index.php' : '/badminton-manager/customer/home.php' ?>">
                        Badminton<span>Manager</span>
                    </a>
                    <p>
                        Nền tảng đặt sân cầu lông trực tuyến giúp người chơi tra cứu lịch sân,
                        đặt nhanh, thanh toán thuận tiện và nhận hỗ trợ trực tiếp khi cần.
                    </p>

                    <div class="site-footer-contact-pills">
                        <a href="tel:<?= e($supportPhone) ?>">Tổng đài: <?= e($supportPhone) ?></a>
                        <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
                    </div>
                </div>

                <div class="site-footer-column">
                    <h3>Khám phá</h3>
                    <ul>
                        <?php foreach ($discoverLinks as $link): ?>
                            <li><a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="site-footer-column">
                    <h3>Hỗ trợ</h3>
                    <ul>
                        <?php foreach ($supportLinks as $link): ?>
                            <li><a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="site-footer-column site-footer-contact">
                    <h3>Liên hệ nhanh</h3>
                    <p>Hỗ trợ đặt sân, xác nhận thanh toán, đổi lịch và giải đáp trong giờ hoạt động.</p>

                    <div class="site-footer-contact-list">
                        <a href="tel:<?= e($supportPhone) ?>">
                            <strong>Tổng đài hỗ trợ</strong>
                            <span><?= e($supportPhone) ?></span>
                        </a>
                        <a href="mailto:<?= e($supportEmail) ?>">
                            <strong>Email</strong>
                            <span><?= e($supportEmail) ?></span>
                        </a>
                        <div class="site-footer-address">
                            <strong>Địa chỉ</strong>
                            <span><?= e($supportAddress) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="site-footer-bottom">
                <span>© <?= $currentYear ?> Badminton Manager. All rights reserved.</span>
                <span>Dịch vụ đặt sân cầu lông trực tuyến, hỗ trợ nhanh và lịch sân minh bạch.</span>
            </div>
        </footer>
    </main>
</div>
<?php else: ?>
        </div>

        <footer class="site-footer site-footer-guest">
            <div class="site-footer-grid">
                <div class="site-footer-brand">
                    <a class="site-footer-logo" href="/badminton-manager/index.php">
                        Badminton<span>Manager</span>
                    </a>
                    <p>Đặt sân nhanh, xem lịch rõ ràng và nhận hỗ trợ trực tiếp từ hệ thống quản lí sân cầu lông.</p>
                </div>

                <div class="site-footer-column site-footer-contact">
                    <h3>Liên hệ</h3>
                    <div class="site-footer-contact-list">
                        <a href="tel:<?= e($supportPhone) ?>">
                            <strong>Tổng đài hỗ trợ</strong>
                            <span><?= e($supportPhone) ?></span>
                        </a>
                        <a href="mailto:<?= e($supportEmail) ?>">
                            <strong>Email</strong>
                            <span><?= e($supportEmail) ?></span>
                        </a>
                        <div class="site-footer-address">
                            <strong>Địa chỉ</strong>
                            <span><?= e($supportAddress) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
<?php endif; ?>
</body>
</html>
