<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

ensureSupportTables($conn);

$success = '';
$error = '';
$selectedThreadId = isset($_GET['thread_id']) ? (int) $_GET['thread_id'] : 0;
$faqQuestion = trim($_GET['faq'] ?? '');
$faqResponse = $faqQuestion !== '' ? getSupportFaqAnswer($faqQuestion) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'faq') {
        $faqQuestion = trim($_POST['faq_question'] ?? '');
        if ($faqQuestion !== '') {
            $faqResponse = getSupportFaqAnswer($faqQuestion);
        }
    } elseif ($action === 'create_thread') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($subject === '' || $message === '') {
            $error = 'Vui lòng nhập tiêu đề và nội dung hỗ trợ.';
        } else {
            $threadSql = "INSERT INTO support_threads (user_id, subject, status, last_message_at)
                          VALUES (?, ?, 'open', NOW())";
            $threadStmt = mysqli_prepare($conn, $threadSql);
            mysqli_stmt_bind_param($threadStmt, 'is', $_SESSION['user']['id'], $subject);
            mysqli_stmt_execute($threadStmt);
            $selectedThreadId = (int) mysqli_insert_id($conn);

            $messageSql = "INSERT INTO support_messages (thread_id, sender_role, message)
                           VALUES (?, 'customer', ?)";
            $messageStmt = mysqli_prepare($conn, $messageSql);
            mysqli_stmt_bind_param($messageStmt, 'is', $selectedThreadId, $message);
            mysqli_stmt_execute($messageStmt);

            header('Location: /badminton-manager/customer/support.php?thread_id=' . $selectedThreadId . '&success=created');
            exit();
        }
    } elseif ($action === 'send_message') {
        $selectedThreadId = (int) ($_POST['thread_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        $threadCheckSql = "SELECT id FROM support_threads WHERE id = ? AND user_id = ? LIMIT 1";
        $threadCheckStmt = mysqli_prepare($conn, $threadCheckSql);
        mysqli_stmt_bind_param($threadCheckStmt, 'ii', $selectedThreadId, $_SESSION['user']['id']);
        mysqli_stmt_execute($threadCheckStmt);
        $threadResult = mysqli_stmt_get_result($threadCheckStmt);
        $thread = mysqli_fetch_assoc($threadResult);

        if (!$thread) {
            $error = 'Không tìm thấy cuộc trò chuyện hỗ trợ.';
        } elseif ($message === '') {
            $error = 'Vui lòng nhập nội dung cần gửi.';
        } else {
            $insertSql = "INSERT INTO support_messages (thread_id, sender_role, message)
                          VALUES (?, 'customer', ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param($insertStmt, 'is', $selectedThreadId, $message);
            mysqli_stmt_execute($insertStmt);

            $updateSql = "UPDATE support_threads
                          SET status = 'open', last_message_at = NOW()
                          WHERE id = ? AND user_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, 'ii', $selectedThreadId, $_SESSION['user']['id']);
            mysqli_stmt_execute($updateStmt);

            header('Location: /badminton-manager/customer/support.php?thread_id=' . $selectedThreadId . '&success=sent');
            exit();
        }
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success = 'Đã tạo cuộc trò chuyện hỗ trợ mới.';
    } elseif ($_GET['success'] === 'sent') {
        $success = 'Đã gửi tin nhắn tới admin/lễ tân.';
    }
}

$threadListSql = "SELECT
                    st.id,
                    st.subject,
                    st.status,
                    st.last_message_at,
                    st.created_at,
                    (
                        SELECT sm.message
                        FROM support_messages sm
                        WHERE sm.thread_id = st.id
                        ORDER BY sm.created_at DESC, sm.id DESC
                        LIMIT 1
                    ) AS latest_message
                  FROM support_threads st
                  WHERE st.user_id = ?
                  ORDER BY st.last_message_at DESC, st.id DESC";
$threadListStmt = mysqli_prepare($conn, $threadListSql);
mysqli_stmt_bind_param($threadListStmt, 'i', $_SESSION['user']['id']);
mysqli_stmt_execute($threadListStmt);
$threadListResult = mysqli_stmt_get_result($threadListStmt);

$threads = [];
while ($row = mysqli_fetch_assoc($threadListResult)) {
    $threads[] = $row;
}

if ($selectedThreadId === 0 && $threads !== []) {
    $selectedThreadId = (int) $threads[0]['id'];
}

$selectedThread = null;
$messages = [];

if ($selectedThreadId > 0) {
    $selectedThreadSql = "SELECT id, subject, status, created_at, last_message_at
                          FROM support_threads
                          WHERE id = ? AND user_id = ?
                          LIMIT 1";
    $selectedThreadStmt = mysqli_prepare($conn, $selectedThreadSql);
    mysqli_stmt_bind_param($selectedThreadStmt, 'ii', $selectedThreadId, $_SESSION['user']['id']);
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
    'open' => 'Đang chờ phản hồi',
    'answered' => 'Đã phản hồi',
    'closed' => 'Đã đóng',
];

require_once '../includes/header.php';
?>

<div class="page-title">
    <div>
        <h2>Hỗ trợ trực tuyến</h2>
        <p class="page-subtitle">Hỏi nhanh chatbot các câu phổ biến hoặc nhắn trực tiếp để admin/lễ tân hỗ trợ.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="support-layout">
    <div class="support-main-column">
        <div class="card support-chatbot-card">
            <div class="support-section-head">
                <div>
                    <h3>Chatbot FAQ</h3>
                    <p>Trả lời nhanh các câu hỏi phổ biến về giá sân, giờ mở cửa, chính sách hủy và cách đặt sân.</p>
                </div>
                <span class="support-badge">Tự động</span>
            </div>

            <div class="support-quick-questions">
                <?php foreach (getSupportQuickQuestions() as $question): ?>
                    <a class="support-question-chip" href="/badminton-manager/customer/support.php?faq=<?= urlencode($question) ?>">
                        <?= e($question) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="POST" class="support-faq-form">
                <input type="hidden" name="action" value="faq">
                <input type="text" name="faq_question" placeholder="Nhập câu hỏi của bạn..." value="<?= e($faqQuestion) ?>">
                <button type="submit">Hỏi ngay</button>
            </form>

            <?php if ($faqResponse): ?>
                <div class="support-bot-answer">
                    <strong><?= e($faqResponse['title']) ?></strong>
                    <p><?= e($faqResponse['answer']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card support-create-card">
            <div class="support-section-head">
                <div>
                    <h3>Tạo yêu cầu hỗ trợ</h3>
                    <p>Dùng khi bạn cần xử lý trường hợp cụ thể như thanh toán, đổi lịch hoặc xác nhận thông tin.</p>
                </div>
            </div>

            <form method="POST" class="support-create-form">
                <input type="hidden" name="action" value="create_thread">

                <label>Tiêu đề</label>
                <input type="text" name="subject" required placeholder="Ví dụ: Cần hỗ trợ xác nhận chuyển khoản">

                <label>Nội dung</label>
                <textarea name="message" rows="5" required placeholder="Mô tả rõ vấn đề bạn đang gặp phải..."></textarea>

                <button type="submit">Gửi yêu cầu hỗ trợ</button>
            </form>
        </div>
    </div>

    <div class="support-side-column">
        <div class="card support-thread-list-card">
            <div class="support-section-head">
                <div>
                    <h3>Cuộc trò chuyện của tôi</h3>
                    <p>Theo dõi phản hồi từ admin hoặc lễ tân.</p>
                </div>
            </div>

            <div class="support-thread-list">
                <?php if ($threads !== []): ?>
                    <?php foreach ($threads as $thread): ?>
                        <a class="support-thread-item <?= (int) $thread['id'] === $selectedThreadId ? 'support-thread-item-active' : '' ?>" href="/badminton-manager/customer/support.php?thread_id=<?= (int) $thread['id'] ?>">
                            <div class="support-thread-row">
                                <strong><?= e($thread['subject']) ?></strong>
                                <span class="badge <?= $thread['status'] === 'closed' ? 'badge-cancelled' : 'badge-confirmed' ?>">
                                    <?= e($statusLabels[$thread['status']] ?? 'Đang xử lý') ?>
                                </span>
                            </div>
                            <?php
                            $threadPreview = (string) ($thread['latest_message'] ?? '');
                            if (function_exists('mb_strimwidth')) {
                                $threadPreview = mb_strimwidth($threadPreview, 0, 90, '...');
                            } elseif (strlen($threadPreview) > 90) {
                                $threadPreview = substr($threadPreview, 0, 87) . '...';
                            }
                            ?>
                            <p><?= e($threadPreview) ?></p>
                            <small><?= e($thread['last_message_at']) ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="support-empty-box">Bạn chưa có cuộc trò chuyện hỗ trợ nào.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card support-thread-detail-card">
            <div class="support-section-head">
                <div>
                    <h3><?= $selectedThread ? e($selectedThread['subject']) : 'Chi tiết hỗ trợ' ?></h3>
                    <p><?= $selectedThread ? 'Trao đổi trực tiếp với admin hoặc lễ tân.' : 'Chọn một cuộc trò chuyện để xem nội dung.' ?></p>
                </div>
            </div>

            <?php if ($selectedThread): ?>
                <div class="support-message-list">
                    <?php foreach ($messages as $message): ?>
                        <div class="support-message-item <?= $message['sender_role'] === 'customer' ? 'support-message-self' : 'support-message-other' ?>">
                            <div class="support-message-meta">
                                <strong><?= $message['sender_role'] === 'customer' ? 'Bạn' : 'Admin / Lễ tân' ?></strong>
                                <span><?= e($message['created_at']) ?></span>
                            </div>
                            <p><?= nl2br(e($message['message'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($selectedThread['status'] !== 'closed'): ?>
                    <form method="POST" class="support-reply-form">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="thread_id" value="<?= (int) $selectedThread['id'] ?>">
                        <textarea name="message" rows="4" required placeholder="Nhập tin nhắn để tiếp tục trao đổi..."></textarea>
                        <button type="submit">Gửi tin nhắn</button>
                    </form>
                <?php else: ?>
                    <div class="support-empty-box">Cuộc trò chuyện này đã được đóng. Hãy tạo yêu cầu mới nếu bạn cần hỗ trợ thêm.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="support-empty-box">Chưa có cuộc trò chuyện nào được chọn.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
