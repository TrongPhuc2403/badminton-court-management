<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user']['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user || !password_verify($current_password, $user['password'])) {
        $error = "Mật khẩu hiện tại không đúng.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu mới xác nhận không khớp.";
    } elseif (strlen($new_password) < 6) {
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $updateSql = "UPDATE users SET password = ? WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, "si", $hashedPassword, $_SESSION['user']['id']);
        mysqli_stmt_execute($updateStmt);

        $success = "Đổi mật khẩu thành công.";
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-title">
    <h2>Đổi mật khẩu</h2>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="form-box">
    <form method="POST">
        <label>Mật khẩu hiện tại</label>
        <input type="password" name="current_password" required>

        <label>Mật khẩu mới</label>
        <input type="password" name="new_password" required>

        <label>Xác nhận mật khẩu mới</label>
        <input type="password" name="confirm_password" required>

        <br><br>
        <button type="submit">Cập nhật mật khẩu</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>