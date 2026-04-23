<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($full_name === '' || $phone === '' || $password === '' || $confirm_password === '') {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        $checkSql = "SELECT id FROM users WHERE phone = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $phone);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if (mysqli_num_rows($checkResult) > 0) {
            $error = "Số điện thoại đã tồn tại.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertSql = "INSERT INTO users (full_name, phone, password, role) VALUES (?, ?, ?, 'customer')";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param($insertStmt, "sss", $full_name, $phone, $hashedPassword);
            mysqli_stmt_execute($insertStmt);

            $userId = mysqli_insert_id($conn);

            $_SESSION['user'] = [
                'id' => $userId,
                'full_name' => $full_name,
                'phone' => $phone,
                'role' => 'customer'
            ];

            header("Location: /badminton-manager/customer/booking.php");
            exit();
        }
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-title">
    <h2>Đăng ký tài khoản khách hàng</h2>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-box">
    <form method="POST">
        <label>Họ và tên</label>
        <input type="text" name="full_name" required>

        <label>Số điện thoại</label>
        <input type="text" name="phone" required>

        <label>Mật khẩu</label>
        <input type="password" name="password" required>

        <label>Xác nhận mật khẩu</label>
        <input type="password" name="confirm_password" required>

        <br><br>
        <button type="submit">Tạo tài khoản</button>
    </form>

    <p style="margin-top:16px;" class="text-muted">
        Đã có tài khoản? <a href="/badminton-manager/auth/login.php">Đăng nhập</a>
    </p>
</div>

<?php require_once '../includes/footer.php'; ?>