<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lí sân cầu lông</title>
    <?php $styleVersion = filemtime(dirname(__DIR__) . '/assets/style.css'); ?>
    <link rel="stylesheet" href="/badminton-manager/assets/style.css?v=<?= $styleVersion ?>">
</head>
<body>

<?php if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])): ?>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>Badminton</h2>
            <p>Manager</p>
        </div>

        <?php if ($_SESSION['user']['role'] === 'customer'): ?>
            <a class="sidebar-user sidebar-user-link" href="/badminton-manager/customer/profile.php">
                <div class="avatar"><?= strtoupper(substr($_SESSION['user']['full_name'], 0, 1)) ?></div>
                <div>
                    <strong><?= e($_SESSION['user']['full_name']) ?></strong>
                    <p>Khách hàng</p>
                </div>
            </a>
        <?php else: ?>
            <div class="sidebar-user">
                <div class="avatar"><?= strtoupper(substr($_SESSION['user']['full_name'], 0, 1)) ?></div>
                <div>
                    <strong><?= e($_SESSION['user']['full_name']) ?></strong>
                    <p>Quản trị viên</p>
                </div>
            </div>
        <?php endif; ?>

        <nav class="sidebar-menu">
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <a class="<?= isActiveMenu('/badminton-manager/admin/index.php') ?>" href="/badminton-manager/admin/index.php">Trang chủ</a>
                <a class="<?= isActiveMenu('/badminton-manager/admin/bookings.php') ?>" href="/badminton-manager/admin/bookings.php">Quản lí đặt sân</a>
                <a class="<?= isActiveMenu('/badminton-manager/admin/customers.php') ?>" href="/badminton-manager/admin/customers.php">Khách hàng</a>
                <a class="<?= isActiveMenu('/badminton-manager/admin/reports.php') ?>" href="/badminton-manager/admin/reports.php">Báo cáo</a>
                <a class="<?= isActiveMenu('/badminton-manager/admin/support.php') ?>" href="/badminton-manager/admin/support.php">Hỗ trợ</a>
            <?php else: ?>
                <a class="<?= isActiveMenu('/badminton-manager/customer/home.php') ?>" href="/badminton-manager/customer/home.php">Trang chủ</a>
                <a class="<?= isActiveMenu('/badminton-manager/customer/booking.php') ?>" href="/badminton-manager/customer/booking.php">Đặt sân</a>
                <a class="<?= isActiveMenu('/badminton-manager/customer/my_bookings.php') ?>" href="/badminton-manager/customer/my_bookings.php">Lịch sử đặt sân</a>
                <a class="<?= isActiveMenu('/badminton-manager/customer/support.php') ?>" href="/badminton-manager/customer/support.php">Hỗ trợ</a>
                <a class="<?= isActiveMenu('/badminton-manager/customer/profile.php') ?>" href="/badminton-manager/customer/profile.php">Thông tin tài khoản</a>
                <a class="<?= isActiveMenu('/badminton-manager/auth/change_password.php') ?>" href="/badminton-manager/auth/change_password.php">Đổi mật khẩu</a>
            <?php endif; ?>
            <a href="/badminton-manager/auth/logout.php" class="logout-link">Đăng xuất</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Hệ thống quản lí sân cầu lông</h1>
        </div>
        <div class="page-content">
<?php else: ?>
    <div class="login-page">
        <div class="login-box">
            <h1>Quản lí sân cầu lông</h1>
            <p>Đăng nhập để tiếp tục</p>
<?php endif; ?>
