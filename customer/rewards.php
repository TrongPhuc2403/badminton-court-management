<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);
syncPendingLoyaltyAwards($conn);
ensureReviewTables($conn);

$success = '';
$error = '';
$userId = (int) $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rewardType = trim((string) ($_POST['reward_type'] ?? ''));
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $rewardOption = getLoyaltyRewardOption($rewardType);

    if (!$rewardOption) {
        $error = 'Phần quà không hợp lệ.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            $userSql = "SELECT loyalty_points FROM users WHERE id = ? LIMIT 1 FOR UPDATE";
            $userStmt = mysqli_prepare($conn, $userSql);
            mysqli_stmt_bind_param($userStmt, 'i', $userId);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            $user = mysqli_fetch_assoc($userResult);
            mysqli_stmt_close($userStmt);

            if (!$user) {
                throw new RuntimeException('Không tìm thấy tài khoản khách hàng.');
            }

            $pointsCost = getLoyaltyRewardPointsCost($rewardType) * $quantity;
            $currentPoints = (int) ($user['loyalty_points'] ?? 0);

            if ($currentPoints < $pointsCost) {
                throw new RuntimeException('Bạn không đủ điểm để đổi phần quà này.');
            }

            $newBalance = $currentPoints - $pointsCost;
            $note = $rewardType === 'free_hour'
                ? 'Đổi giờ chơi miễn phí, vui lòng liên hệ chủ sân để sắp lịch.'
                : 'Đổi trái cầu, vui lòng nhận tại quầy lễ tân.';

            $insertRedemptionSql = "INSERT INTO loyalty_redemptions (
                                        user_id, reward_type, quantity, points_used, status, note
                                    ) VALUES (?, ?, ?, ?, 'pending', ?)";
            $insertRedemptionStmt = mysqli_prepare($conn, $insertRedemptionSql);
            mysqli_stmt_bind_param(
                $insertRedemptionStmt,
                'isiis',
                $userId,
                $rewardType,
                $quantity,
                $pointsCost,
                $note
            );
            mysqli_stmt_execute($insertRedemptionStmt);
            $redemptionId = mysqli_insert_id($conn);
            mysqli_stmt_close($insertRedemptionStmt);

            $updateUserSql = "UPDATE users SET loyalty_points = ? WHERE id = ?";
            $updateUserStmt = mysqli_prepare($conn, $updateUserSql);
            mysqli_stmt_bind_param($updateUserStmt, 'ii', $newBalance, $userId);
            mysqli_stmt_execute($updateUserStmt);
            mysqli_stmt_close($updateUserStmt);

            $description = 'Đổi điểm: ' . getLoyaltyRedemptionDescription($rewardType, $quantity);
            $insertTransactionSql = "INSERT INTO loyalty_point_transactions (
                                        user_id, booking_id, redemption_id, points_change, balance_after, description
                                     ) VALUES (?, NULL, ?, ?, ?, ?)";
            $insertTransactionStmt = mysqli_prepare($conn, $insertTransactionSql);
            $pointsChange = -$pointsCost;
            mysqli_stmt_bind_param(
                $insertTransactionStmt,
                'iiiis',
                $userId,
                $redemptionId,
                $pointsChange,
                $newBalance,
                $description
            );
            mysqli_stmt_execute($insertTransactionStmt);
            mysqli_stmt_close($insertTransactionStmt);

            mysqli_commit($conn);
            $success = 'Đã tạo yêu cầu đổi quà thành công.';
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

$userInfoSql = "SELECT full_name, loyalty_points FROM users WHERE id = ? LIMIT 1";
$userInfoStmt = mysqli_prepare($conn, $userInfoSql);
mysqli_stmt_bind_param($userInfoStmt, 'i', $userId);
mysqli_stmt_execute($userInfoStmt);
$userInfoResult = mysqli_stmt_get_result($userInfoStmt);
$userInfo = mysqli_fetch_assoc($userInfoResult) ?: ['full_name' => $_SESSION['user']['full_name'], 'loyalty_points' => 0];
mysqli_stmt_close($userInfoStmt);

$rewardCatalog = getLoyaltyRewardCatalog();

$historySql = "SELECT points_change, balance_after, description, created_at
               FROM loyalty_point_transactions
               WHERE user_id = ?
               ORDER BY id DESC
               LIMIT 12";
$historyStmt = mysqli_prepare($conn, $historySql);
mysqli_stmt_bind_param($historyStmt, 'i', $userId);
mysqli_stmt_execute($historyStmt);
$historyResult = mysqli_stmt_get_result($historyStmt);

$redemptionSql = "SELECT reward_type, quantity, points_used, status, note, created_at, updated_at
                  FROM loyalty_redemptions
                  WHERE user_id = ?
                  ORDER BY id DESC
                  LIMIT 12";
$redemptionStmt = mysqli_prepare($conn, $redemptionSql);
mysqli_stmt_bind_param($redemptionStmt, 'i', $userId);
mysqli_stmt_execute($redemptionStmt);
$redemptionResult = mysqli_stmt_get_result($redemptionStmt);

require_once '../includes/header.php';
?>

<div class="page-title">
    <div>
        <h2>Điểm thưởng</h2>
        <p class="page-subtitle">Mỗi 1.000 đồng thanh toán hợp lệ được cộng 1 điểm. Điểm được tính theo số tiền đã thực trả.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="card">
        <h3>Khách hàng</h3>
        <div class="number" style="font-size:26px;"><?= e($userInfo['full_name']) ?></div>
    </div>
    <div class="card">
        <h3>Điểm hiện có</h3>
        <div class="number"><?= (int) ($userInfo['loyalty_points'] ?? 0) ?></div>
    </div>
    <div class="card">
        <h3>Quy đổi 1 giờ chơi</h3>
        <div class="number" style="font-size:26px;">1.000 điểm</div>
    </div>
    <div class="card">
        <h3>Quy đổi 1 trái cầu</h3>
        <div class="number" style="font-size:26px;">400 điểm</div>
    </div>
</div>

<div class="form-box">
    <h3>Đổi điểm</h3>
    <form method="POST">
        <label>Phần quà</label>
        <select name="reward_type" required>
            <?php foreach ($rewardCatalog as $rewardType => $reward): ?>
                <option value="<?= e($rewardType) ?>">
                    <?= e($reward['label']) ?> - <?= (int) $reward['points_cost'] ?> điểm
                </option>
            <?php endforeach; ?>
        </select>

        <label>Số lượng</label>
        <input type="number" name="quantity" min="1" max="20" value="1" required>

        <br><br>
        <button type="submit">Tạo yêu cầu đổi quà</button>
    </form>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Phần quà</th>
            <th>Số lượng</th>
            <th>Điểm đã dùng</th>
            <th>Trạng thái</th>
            <th>Ghi chú</th>
            <th>Ngày tạo</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($redemptionResult)): ?>
            <tr>
                <td><?= e(getLoyaltyRewardLabel($row['reward_type'])) ?></td>
                <td><?= (int) $row['quantity'] ?></td>
                <td><?= (int) $row['points_used'] ?></td>
                <td><?= e(getLoyaltyRedemptionStatusLabel($row['status'])) ?></td>
                <td><?= e($row['note'] ?? '') ?></td>
                <td><?= e($row['created_at']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<div class="table-wrapper">
    <table>
        <tr>
            <th>Biến động</th>
            <th>Mô tả</th>
            <th>Số dư sau giao dịch</th>
            <th>Thời gian</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($historyResult)): ?>
            <tr>
                <td><?= (int) $row['points_change'] > 0 ? '+' . (int) $row['points_change'] : (int) $row['points_change'] ?></td>
                <td><?= e($row['description']) ?></td>
                <td><?= (int) $row['balance_after'] ?></td>
                <td><?= e($row['created_at']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
