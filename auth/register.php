<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = "";
$success = "";
$fullName = "";
$phone = "";
$email = "";

if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])) {
    redirectByRole();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = normalizeContact($_POST['phone'] ?? '');
    $email = normalizeContact($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $phone === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    } elseif (detectContactType($phone) !== 'phone') {
        $error = "Số điện thoại không hợp lệ.";
    } elseif (detectContactType($email) !== 'email') {
        $error = "Email không hợp lệ.";
    } elseif ($password !== $confirmPassword) {
        $error = "Mật khẩu xác nhận không khớp.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        $phoneUser = getUserByField($conn, 'phone', $phone);
        $emailUser = getUserByField($conn, 'email', $email);

        if ($phoneUser && $emailUser && (int) $phoneUser['id'] !== (int) $emailUser['id']) {
            $error = "Số điện thoại và email đang thuộc về hai tài khoản khác nhau.";
        } elseif ($phoneUser && (int) $phoneUser['is_verified'] === 1 && (!$emailUser || (int) $phoneUser['id'] !== (int) $emailUser['id'])) {
            $error = "Số điện thoại đã tồn tại.";
        } elseif ($emailUser && (int) $emailUser['is_verified'] === 1 && (!$phoneUser || (int) $phoneUser['id'] !== (int) $emailUser['id'])) {
            $error = "Email đã tồn tại.";
        } else {
            $existingUser = $phoneUser ?: $emailUser;
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $verificationToken = generateVerificationToken();
            $verificationExpiresAt = getVerificationExpiryTime();

            if ($existingUser) {
                $updateSql = "UPDATE users
                              SET full_name = ?, phone = ?, email = ?, password = ?, is_verified = 0,
                                  verification_token = ?, verification_expires_at = ?, verification_sent_at = NOW(), role = 'customer'
                              WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param(
                    $updateStmt,
                    "ssssssi",
                    $fullName,
                    $phone,
                    $email,
                    $hashedPassword,
                    $verificationToken,
                    $verificationExpiresAt,
                    $existingUser['id']
                );
                mysqli_stmt_execute($updateStmt);
            } else {
                $insertSql = "INSERT INTO users (
                                    full_name, phone, email, password, role, is_verified,
                                    verification_token, verification_expires_at, verification_sent_at
                              ) VALUES (?, ?, ?, ?, 'customer', 0, ?, ?, NOW())";
                $insertStmt = mysqli_prepare($conn, $insertSql);
                mysqli_stmt_bind_param(
                    $insertStmt,
                    "ssssss",
                    $fullName,
                    $phone,
                    $email,
                    $hashedPassword,
                    $verificationToken,
                    $verificationExpiresAt
                );
                mysqli_stmt_execute($insertStmt);
            }

            $deliveryResult = sendVerificationEmail($email, $verificationToken);

            if ($deliveryResult['success']) {
                $success = $deliveryResult['notice'];
                $fullName = "";
                $phone = "";
                $email = "";
            } else {
                $error = $deliveryResult['notice'];
            }
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

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="form-box">
    <form method="POST">
        <label>Họ và tên</label>
        <input type="text" name="full_name" required value="<?= e($fullName) ?>">

        <label>Số điện thoại</label>
        <input type="text" name="phone" required value="<?= e($phone) ?>">

        <label>Email</label>
        <input type="email" name="email" required value="<?= e($email) ?>">

        <label>Mật khẩu</label>
        <input type="password" name="password" required>

        <label>Xác nhận mật khẩu</label>
        <input type="password" name="confirm_password" required>

        <br><br>
        <button type="submit">Đăng ký và gửi email xác minh</button>
    </form>

    <p style="margin-top:16px;" class="text-muted">
        Đã có tài khoản? <a href="/badminton-manager/auth/login.php">Đăng nhập</a>
    </p>
</div>

<?php require_once '../includes/footer.php'; ?>
