<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";

if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])) {
    redirectByRole();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE phone = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $phone);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'role' => $user['role']
        ];

        redirectByRole();
    } else {
        $error = "Sai số điện thoại hoặc mật khẩu.";
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST">
    <label>Số điện thoại</label>
    <input type="text" name="phone" required>

    <label>Mật khẩu</label>
    <input type="password" name="password" required>

    <br><br>
    <button type="submit">Đăng nhập</button>
</form>

<p style="margin-top:16px;" class="text-muted">
    Chưa có tài khoản? <a href="/badminton-manager/auth/register.php">Đăng ký ngay</a>
</p>

<?php require_once '../includes/footer.php'; ?>