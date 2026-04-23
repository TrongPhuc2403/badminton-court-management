<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";
$success = "";
$email = normalizeContact($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($email === '' || $token === '') {
    $error = "Liên kết xác minh không hợp lệ.";
} else {
    $user = getUserByField($conn, 'email', $email);

    if (!$user) {
        $error = "Không tìm thấy tài khoản cần xác minh.";
    } elseif ((int) $user['is_verified'] === 1) {
        $success = "Tài khoản đã được xác minh trước đó. Bạn có thể đăng nhập.";
    } elseif (empty($user['verification_token'])) {
        $error = "Yêu cầu xác minh không còn hợp lệ.";
    } elseif (isVerificationExpired($user['verification_expires_at'])) {
        $error = "Liên kết xác minh đã hết hạn. Hãy đăng nhập để hệ thống gửi lại email xác minh.";
    } elseif (!hash_equals((string) $user['verification_token'], $token)) {
        $error = "Mã xác minh email không đúng.";
    } else {
        $sql = "UPDATE users
                SET is_verified = 1, verification_token = NULL, verification_expires_at = NULL, verification_sent_at = NULL
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user['id']);
        mysqli_stmt_execute($stmt);

        $verifiedUser = getUserByField($conn, 'email', $email);
        $_SESSION['user'] = createSessionUser($verifiedUser);
        redirectByRole();
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-title">
    <h2>Xác minh email</h2>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="form-box">
    <p class="text-muted">
        Sau khi xác minh email, bạn có thể đăng nhập bằng email hoặc số điện thoại đã đăng ký.
    </p>

    <p style="margin-top:16px;" class="text-muted">
        <a href="/badminton-manager/auth/login.php">Quay lại đăng nhập</a>
    </p>
</div>

<?php require_once '../includes/footer.php'; ?>
