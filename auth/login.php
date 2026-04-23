<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";
$success = "";
$contact = "";

if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])) {
    redirectByRole();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact = normalizeContact($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = getUserByContact($conn, $contact);

    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Sai email/số điện thoại hoặc mật khẩu.";
    } elseif ((int) $user['is_verified'] !== 1) {
        $verificationToken = generateVerificationToken();
        $verificationExpiresAt = getVerificationExpiryTime();

        $sql = "UPDATE users
                SET verification_token = ?, verification_expires_at = ?, verification_sent_at = NOW()
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $verificationToken, $verificationExpiresAt, $user['id']);
        mysqli_stmt_execute($stmt);

        $deliveryResult = sendVerificationEmail($user['email'], $verificationToken);
        $success = $deliveryResult['notice'];
        $error = "Tài khoản của bạn chưa xác minh email. Hệ thống đã gửi lại email xác minh.";
    } else {
        $_SESSION['user'] = createSessionUser($user);
        redirectByRole();
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="POST">
    <label>Email hoặc số điện thoại</label>
    <input type="text" name="contact" required value="<?= e($contact) ?>">

    <label>Mật khẩu</label>
    <input type="password" name="password" required>

    <br><br>
    <button type="submit">Đăng nhập</button>
</form>

<p style="margin-top:16px;" class="text-muted">
    Chưa có tài khoản? <a href="/badminton-manager/auth/register.php">Đăng ký ngay</a>
</p>

<?php require_once '../includes/footer.php'; ?>
