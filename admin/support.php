<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ensureSupportTables($conn);

$success = '';
$error = '';
$selectedThreadId = isset($_GET['thread_id']) ? (int) $_GET['thread_id'] : 0;
$statusFilter = $_GET['status'] ?? 'all';

if (!in_array($statusFilter, ['all', 'open', 'answered', 'closed'], true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selectedThreadId = (int) ($_POST['thread_id'] ?? 0);

    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');

        if ($selectedThreadId <= 0 || $message === '') {
            $error = 'Vui lòng chọn cuộc trò chuyện và nhập nội dung phản hồi.';
        } else {
            $insertSql = "INSERT INTO support_messages (thread_id, sender_role, message)
                          VALUES (?, 'admin', ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param($insertStmt, 'is', $selectedThreadId, $message);
            mysqli_stmt_execute($insertStmt);

            $updateSql = "UPDATE support_threads
                          SET status = 'answered', last_message_at = NOW()
                          WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, 'i', $selectedThreadId);
            mysqli_stmt_execute($updateStmt);

            header('Location: /badminton-manager/admin/support.php?thread_id=' . $selectedThreadId . '&success=replied');
            exit();
        }
    } elseif ($action === 'status') {
        $newStatus = $_POST['status'] ?? 'open';

        if (!in_array($newStatus, ['open', 'answered', 'closed'], true) || $selectedThreadId <= 0) {
            $error = 'Trạng thái hỗ trợ không hợp lệ.';
        } else {
            $updateSql = "UPDATE support_threads SET status = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $selectedThreadId);
            mysqli_stmt_execute($updateStmt);

            header('Location: /badminton-manager/admin/support.php?thread_id=' . $selectedThreadId . '&success=status');
            exit();
        }
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'replied') {
        $success = 'Đã gửi phản hồi cho khách hàng.';
    } elseif ($_GET['success'] === 'status') {
        $success = 'Đã cập nhật trạng thái hỗ trợ.';
    }
}

$threadSql = "SELECT
                st.id,
                st.subject,
                st.status,
                st.last_message_at,
                st.created_at,
                u.full_name,
                COALESCE(NULLIF(u.phone, ''), u.email) AS contact,
                (
                    SELECT sm.message
                    FROM support_messages sm
                    WHERE sm.thread_id = st.id
                    ORDER BY sm.created_at DESC, sm.id DESC
                    LIMIT 1
                ) AS latest_message
              FROM support_threads st
              JOIN users u ON u.id = st.user_id";

$threadParams = [];
$threadTypes = '';
if ($statusFilter !== 'all') {
    $threadSql .= " WHERE st.status = ?";
    $threadParams[] = $statusFilter;
    $threadTypes = 's';
}

$threadSql .= " ORDER BY
                    CASE st.status
                        WHEN 'open' THEN 1
                        WHEN 'answered' THEN 2
                        ELSE 3
                    END,
                    st.last_message_at DESC,
                    st.id DESC";

$threadStmt = mysqli_prepare($conn, $threadSql);
if ($threadTypes !== '') {
    mysqli_stmt_bind_param($threadStmt, $threadTypes, ...$threadParams);
}
mysqli_stmt_execute($threadStmt);
$threadResult = mysqli_stmt_get_result($threadStmt);

$threads = [];
while ($row = mysqli_fetch_assoc($threadResult)) {
    $threads[] = $row;
}

if ($selectedThreadId === 0 && $threads !== []) {
    $selectedThreadId = (int) $threads[0]['id'];
}

$selectedThread = null;
$messages = [];

if ($selectedThreadId > 0) {
    $selectedThreadSql = "SELECT
                            st.id,
                            st.subject,
                            st.status,
                            st.created_at,
                            st.last_message_at,
                            u.full_name,
                            COALESCE(NULLIF(u.phone, ''), u.email) AS contact
                          FROM support_threads st
                          JOIN users u ON u.id = st.user_id
                          WHERE st.id = ?
                          LIMIT 1";
    $selectedThreadStmt = mysqli_prepare($conn, $selectedThreadSql);
    mysqli_stmt_bind_param($selectedThreadStmt, 'i', $selectedThreadId);
    mysqli_stmt_execute($selectedThreadStmt);
    $selectedThreadResult = mysqli_stmt_get_result($selectedThreadStmt);
    $selectedThread = mysqli_fetch_assoc($selectedThreadResult) ?: null;

    if ($selectedThread) {
        $messageSql = "SELECT sender_role, message, created_at
                       FROM support_messages
                       WHERE thread_id = ?
                       ORDER BY created_at ASC, id ASC";
        $messageStmt = mysqli_prepare($conn, $messageSql);
        mysqli_stmt_bind_param($messageStmt, 'i', $selectedThreadId);
        mysqli_stmt_execute($messageStmt);
        $messageResult = mysqli_stmt_get_result($messageStmt);

        while ($row = mysqli_fetch_assoc($messageResult)) {
            $messages[] = $row;
        }
    }
}

$statusLabels = [
    'open' => 'Đang chờ xử lý',
    'answered' => 'Đã phản hồi',
    'closed' => 'Đã đóng',
];

$openCount = 0;
$answeredCount = 0;
$closedCount = 0;
foreach ($threads as $thread) {
    if ($thread['status'] === 'open') {
        $openCount++;
    } elseif ($thread['status'] === 'answered') {
        $answeredCount++;
    } elseif ($thread['status'] === 'closed') {
        $closedCount++;
    }
}

require_once '../includes/header.php';
?>

<div class="page-title">
    <div>
        <h2>Hỗ trợ khách hàng</h2>
        <p class="page-subtitle">Quản lý cuộc trò chuyện với khách, trả lời câu hỏi và cập nhật trạng thái hỗ trợ.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="support-admin-stats">
    <div class="card">
        <h3>Tổng hội thoại</h3>
        <div class="number"><?= count($threads) ?></div>
    </div>
    <div class="card">
        <h3>Đang chờ xử lý</h3>
        <div class="number"><?= $openCount ?></div>
    </div>
    <div class="card">
        <h3>Đã phản hồi</h3>
        <div class="number"><?= $answeredCount ?></div>
    </div>
    <div class="card">
        <h3>Đã đóng</h3>
        <div class="number"><?= $closedCount ?></div>
    </div>
</div>

<div class="card support-admin-filter-card">
    <form method="GET" class="support-admin-filter-form">
        <input type="hidden" name="thread_id" value="<?= $selectedThreadId ?>">
        <label>Trạng thái</label>
        <select name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
            <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Đang chờ xử lý</option>
            <option value="answered" <?= $statusFilter === 'answered' ? 'selected' : '' ?>>Đã phản hồi</option>
            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
        </select>
        <button type="submit">Lọc</button>
    </form>
</div>

<div class="support-layout support-admin-layout">
    <div class="support-side-column">
        <div class="card support-thread-list-card">
            <div class="support-section-head">
                <div>
                    <h3>Danh sách hội thoại</h3>
                    <p>Ưu tiên các yêu cầu đang chờ xử lý.</p>
                </div>
            </div>

            <div class="support-thread-list">
                <?php if ($threads !== []): ?>
                    <?php foreach ($threads as $thread): ?>
                        <a class="support-thread-item <?= (int) $thread['id'] === $selectedThreadId ? 'support-thread-item-active' : '' ?>" href="/badminton-manager/admin/support.php?thread_id=<?= (int) $thread['id'] ?>&status=<?= e($statusFilter) ?>">
                            <div class="support-thread-row">
                                <strong><?= e($thread['subject']) ?></strong>
                                <span class="badge <?= $thread['status'] === 'closed' ? 'badge-cancelled' : 'badge-confirmed' ?>">
                                    <?= e($statusLabels[$thread['status']] ?? 'Đang xử lý') ?>
                                </span>
                            </div>
                            <p><strong><?= e($thread['full_name']) ?></strong> • <?= e((string) $thread['contact']) ?></p>
                            <small><?= e((string) ($thread['latest_message'] ?? 'Chưa có nội dung')) ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="support-empty-box">Chưa có cuộc trò chuyện phù hợp với bộ lọc này.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="support-main-column">
        <div class="card support-thread-detail-card">
            <div class="support-section-head">
                <div>
                    <h3><?= $selectedThread ? e($selectedThread['subject']) : 'Chi tiết hội thoại' ?></h3>
                    <p><?= $selectedThread ? e($selectedThread['full_name'] . ' • ' . $selectedThread['contact']) : 'Chọn một hội thoại để xem chi tiết.' ?></p>
                </div>
                <?php if ($selectedThread): ?>
                    <span class="support-badge"><?= e($statusLabels[$selectedThread['status']] ?? 'Đang xử lý') ?></span>
                <?php endif; ?>
            </div>

            <?php if ($selectedThread): ?>
                <div class="support-message-list">
                    <?php foreach ($messages as $message): ?>
                        <div class="support-message-item <?= $message['sender_role'] === 'admin' ? 'support-message-self' : 'support-message-other' ?>">
                            <div class="support-message-meta">
                                <strong><?= $message['sender_role'] === 'admin' ? 'Admin / Lễ tân' : 'Khách hàng' ?></strong>
                                <span><?= e($message['created_at']) ?></span>
                            </div>
                            <p><?= nl2br(e($message['message'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="support-admin-actions">
                    <form method="POST" class="support-status-form">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="thread_id" value="<?= (int) $selectedThread['id'] ?>">
                        <select name="status">
                            <option value="open" <?= $selectedThread['status'] === 'open' ? 'selected' : '' ?>>Đang chờ xử lý</option>
                            <option value="answered" <?= $selectedThread['status'] === 'answered' ? 'selected' : '' ?>>Đã phản hồi</option>
                            <option value="closed" <?= $selectedThread['status'] === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
                        </select>
                        <button type="submit">Cập nhật trạng thái</button>
                    </form>

                    <?php if ($selectedThread['status'] !== 'closed'): ?>
                        <form method="POST" class="support-reply-form">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="thread_id" value="<?= (int) $selectedThread['id'] ?>">
                            <textarea name="message" rows="4" required placeholder="Nhập phản hồi cho khách hàng..."></textarea>
                            <button type="submit">Gửi phản hồi</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="support-empty-box">Chưa có hội thoại nào được chọn.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
