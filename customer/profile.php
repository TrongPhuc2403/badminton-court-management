<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);
syncPendingLoyaltyAwards($conn);

$error = '';
$success = '';

$sql = "SELECT id, full_name, phone, email, is_verified, loyalty_points FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user']['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$currentUser = mysqli_fetch_assoc($result);

if (!$currentUser) {
    header('Location: /badminton-manager/auth/logout.php');
    exit();
}

$fullName = $currentUser['full_name'];
$phone = $currentUser['phone'];
$email = $currentUser['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = normalizeContact($_POST['phone'] ?? '');
    $email = normalizeContact($_POST['email'] ?? '');

    if ($fullName === '' || $phone === '' || $email === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (detectContactType($phone) !== 'phone') {
        $error = 'Số điện thoại không hợp lệ.';
    } elseif (detectContactType($email) !== 'email') {
        $error = 'Email không hợp lệ.';
    } else {
        $phoneUser = getUserByField($conn, 'phone', $phone);
        $emailUser = getUserByField($conn, 'email', $email);

        if ($phoneUser && (int) $phoneUser['id'] !== (int) $currentUser['id']) {
            $error = 'Số điện thoại đã được sử dụng bởi tài khoản khác.';
        } elseif ($emailUser && (int) $emailUser['id'] !== (int) $currentUser['id']) {
            $error = 'Email đã được sử dụng bởi tài khoản khác.';
        } else {
            $emailChanged = $email !== $currentUser['email'];
            $verificationToken = $emailChanged ? generateVerificationToken() : $currentUser['verification_token'];
            $verificationExpiresAt = $emailChanged ? getVerificationExpiryTime() : $currentUser['verification_expires_at'];
            $isVerified = $emailChanged ? 0 : (int) $currentUser['is_verified'];

            $updateSql = "UPDATE users
                          SET full_name = ?, phone = ?, email = ?, is_verified = ?,
                              verification_token = ?, verification_expires_at = ?,
                              verification_sent_at = CASE WHEN ? = 1 THEN NOW() ELSE verification_sent_at END
                          WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param(
                $updateStmt,
                'sssissii',
                $fullName,
                $phone,
                $email,
                $isVerified,
                $verificationToken,
                $verificationExpiresAt,
                $emailChanged,
                $currentUser['id']
            );
            mysqli_stmt_execute($updateStmt);

            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['email'] = $email;

            if ($emailChanged) {
                $deliveryResult = sendVerificationEmail($email, $verificationToken);
                $success = 'Cập nhật thành công. ' . $deliveryResult['notice'];
                $currentUser['is_verified'] = 0;
            } else {
                $success = 'Cập nhật thông tin thành công.';
            }

            $currentUser['full_name'] = $fullName;
            $currentUser['phone'] = $phone;
            $currentUser['email'] = $email;
        }
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-title">
    <h2>Thông tin tài khoản</h2>
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

        <br><br>
        <button type="submit">Cập nhật thông tin</button>
    </form>

    <p style="margin-top:16px;" class="text-muted">
        Trạng thái email:
        <strong><?= (int) $currentUser['is_verified'] === 1 ? 'Đã xác minh' : 'Chưa xác minh' ?></strong>
    </p>

    <p style="margin-top:10px;" class="text-muted">
        Điểm tích lũy hiện có:
        <strong><?= (int) ($currentUser['loyalty_points'] ?? 0) ?> điểm</strong>
    </p>
</div>

<?php require_once '../includes/footer.php'; ?>
