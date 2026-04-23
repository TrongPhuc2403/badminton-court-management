<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

function bindStatementParams($stmt, $types, array &$params)
{
    $bindParams = [$stmt, $types];
    foreach ($params as $key => &$value) {
        $bindParams[] = &$value;
    }

    return call_user_func_array('mysqli_stmt_bind_param', $bindParams);
}

function dbSelectAll($conn, $sql, $types = '', array $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        bindStatementParams($stmt, $types, $params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        mysqli_stmt_close($stmt);
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function dbSelectOne($conn, $sql, $types = '', array $params = [])
{
    $rows = dbSelectAll($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function formatPercent($value)
{
    return number_format((float) $value, 1, ',', '.') . '%';
}

function formatCompactMoney($number)
{
    $number = (float) $number;

    if ($number >= 1000000) {
        return number_format($number / 1000000, 1, ',', '.') . ' tr';
    }

    if ($number >= 1000) {
        return number_format($number / 1000, 0, ',', '.') . 'k';
    }

    return number_format($number, 0, ',', '.');
}

function safeRatio($numerator, $denominator)
{
    if ((float) $denominator <= 0) {
        return 0;
    }

    return ((float) $numerator / (float) $denominator) * 100;
}

function buildLineChartGeometry(array $values, $width = 640, $height = 220, $padding = 24)
{
    if ($values === []) {
        return ['line' => '', 'area' => '', 'dots' => []];
    }

    $maxValue = max($values);
    if ($maxValue <= 0) {
        $maxValue = 1;
    }

    $usableWidth = $width - ($padding * 2);
    $usableHeight = $height - ($padding * 2);
    $stepX = count($values) > 1 ? $usableWidth / (count($values) - 1) : 0;

    $linePoints = [];
    $areaPoints = [];
    $dots = [];

    foreach (array_values($values) as $index => $value) {
        $x = $padding + ($stepX * $index);
        $y = $height - $padding - (($value / $maxValue) * $usableHeight);

        $linePoints[] = round($x, 2) . ',' . round($y, 2);
        $areaPoints[] = round($x, 2) . ',' . round($y, 2);
        $dots[] = [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'value' => $value,
        ];
    }

    $firstX = $padding;
    $lastX = $padding + ($stepX * max(count($values) - 1, 0));
    $baselineY = $height - $padding;

    array_unshift($areaPoints, round($firstX, 2) . ',' . round($baselineY, 2));
    $areaPoints[] = round($lastX, 2) . ',' . round($baselineY, 2);

    return [
        'line' => implode(' ', $linePoints),
        'area' => implode(' ', $areaPoints),
        'dots' => $dots,
    ];
}

function normalizeReportType($type)
{
    return in_array($type, ['day', 'month', 'year'], true) ? $type : 'day';
}

function normalizeDateInput($value)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1 ? $value : date('Y-m-d');
}

function normalizeMonthInput($value)
{
    return preg_match('/^\d{4}-\d{2}$/', (string) $value) === 1 ? $value : date('Y-m');
}

function normalizeYearInput($value)
{
    return preg_match('/^\d{4}$/', (string) $value) === 1 ? $value : date('Y');
}

$reportType = normalizeReportType($_GET['type'] ?? 'day');
$selectedDate = normalizeDateInput($_GET['date'] ?? date('Y-m-d'));
$selectedMonth = normalizeMonthInput($_GET['month'] ?? date('Y-m'));
$selectedYear = normalizeYearInput($_GET['year'] ?? date('Y'));

if ($reportType === 'month') {
    $periodStart = $selectedMonth . '-01';
    $periodEnd = date('Y-m-t', strtotime($periodStart));
    $periodLabel = 'Tháng ' . date('m/Y', strtotime($periodStart));
    $trendTitle = 'Doanh thu theo ngày';
    $trendSubtitle = 'Biến động doanh thu trong từng ngày của tháng đã chọn.';
} elseif ($reportType === 'year') {
    $periodStart = $selectedYear . '-01-01';
    $periodEnd = $selectedYear . '-12-31';
    $periodLabel = 'Năm ' . $selectedYear;
    $trendTitle = 'Doanh thu theo tháng';
    $trendSubtitle = 'So sánh doanh thu giữa các tháng trong năm đã chọn.';
} else {
    $periodStart = $selectedDate;
    $periodEnd = $selectedDate;
    $periodLabel = 'Ngày ' . date('d/m/Y', strtotime($selectedDate));
    $trendTitle = 'Doanh thu theo giờ';
    $trendSubtitle = 'Diễn biến doanh thu theo từng khung giờ trong ngày.';
}

$dateStart = new DateTimeImmutable($periodStart);
$dateEnd = new DateTimeImmutable($periodEnd);
$daysInPeriod = (int) $dateStart->diff($dateEnd)->format('%a') + 1;

$currentDay = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

$todayRevenueRow = dbSelectOne(
    $conn,
    "SELECT COALESCE(SUM(total_price), 0) AS total
     FROM bookings
     WHERE booking_date = ? AND status = 'confirmed'",
    's',
    [$currentDay]
);

$monthRevenueRow = dbSelectOne(
    $conn,
    "SELECT COALESCE(SUM(total_price), 0) AS total
     FROM bookings
     WHERE DATE_FORMAT(booking_date, '%Y-%m') = ? AND status = 'confirmed'",
    's',
    [$currentMonth]
);

$yearRevenueRow = dbSelectOne(
    $conn,
    "SELECT COALESCE(SUM(total_price), 0) AS total
     FROM bookings
     WHERE YEAR(booking_date) = ? AND status = 'confirmed'",
    's',
    [$currentYear]
);

$courtCountRow = dbSelectOne(
    $conn,
    "SELECT
        COUNT(*) AS total_courts,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_courts
     FROM courts"
);

$availableCourtCount = (int) ($courtCountRow['active_courts'] ?? 0);
if ($availableCourtCount <= 0) {
    $availableCourtCount = (int) ($courtCountRow['total_courts'] ?? 0);
}

$summaryRow = dbSelectOne(
    $conn,
    "SELECT
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_bookings,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN TIMESTAMPDIFF(HOUR, start_time, end_time) ELSE 0 END), 0) AS booked_hours
     FROM bookings
     WHERE booking_date BETWEEN ? AND ?",
    'ss',
    [$periodStart, $periodEnd]
);

$totalBookings = (int) ($summaryRow['total_bookings'] ?? 0);
$confirmedBookings = (int) ($summaryRow['confirmed_bookings'] ?? 0);
$cancelledBookings = (int) ($summaryRow['cancelled_bookings'] ?? 0);
$totalRevenue = (float) ($summaryRow['total_revenue'] ?? 0);
$bookedHours = (int) ($summaryRow['booked_hours'] ?? 0);
$totalAvailableHours = $availableCourtCount * 18 * $daysInPeriod;
$occupancyRate = safeRatio($bookedHours, $totalAvailableHours);
$cancellationRate = safeRatio($cancelledBookings, $totalBookings);

$confirmedBookingsRaw = dbSelectAll(
    $conn,
    "SELECT
        b.booking_date,
        b.start_time,
        b.end_time,
        b.total_price,
        c.name AS court_name,
        u.full_name,
        u.phone,
        u.email
     FROM bookings b
     JOIN courts c ON b.court_id = c.id
     JOIN users u ON b.user_id = u.id
     WHERE b.booking_date BETWEEN ? AND ?
       AND b.status = 'confirmed'
     ORDER BY b.booking_date ASC, b.start_time ASC",
    'ss',
    [$periodStart, $periodEnd]
);

$allBookings = dbSelectAll(
    $conn,
    "SELECT
        b.booking_date,
        b.start_time,
        b.end_time,
        b.total_price,
        b.status,
        b.payment_status,
        c.name AS court_name,
        u.full_name
     FROM bookings b
     JOIN courts c ON b.court_id = c.id
     JOIN users u ON b.user_id = u.id
     WHERE b.booking_date BETWEEN ? AND ?
     ORDER BY b.booking_date DESC, b.start_time DESC",
    'ss',
    [$periodStart, $periodEnd]
);

$topCourtRows = dbSelectAll(
    $conn,
    "SELECT
        c.name AS court_name,
        COUNT(*) AS booking_count,
        COALESCE(SUM(TIMESTAMPDIFF(HOUR, b.start_time, b.end_time)), 0) AS booked_hours,
        COALESCE(SUM(b.total_price), 0) AS revenue
     FROM bookings b
     JOIN courts c ON b.court_id = c.id
     WHERE b.booking_date BETWEEN ? AND ?
       AND b.status = 'confirmed'
     GROUP BY b.court_id, c.name
     ORDER BY booking_count DESC, booked_hours DESC, revenue DESC, c.name ASC",
    'ss',
    [$periodStart, $periodEnd]
);

$loyalCustomerRows = dbSelectAll(
    $conn,
    "SELECT
        u.full_name,
        COALESCE(NULLIF(u.phone, ''), NULLIF(u.email, ''), 'Chưa cập nhật') AS contact,
        COUNT(*) AS booking_count,
        COALESCE(SUM(TIMESTAMPDIFF(HOUR, b.start_time, b.end_time)), 0) AS booked_hours,
        COALESCE(SUM(b.total_price), 0) AS revenue
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     WHERE b.booking_date BETWEEN ? AND ?
       AND b.status = 'confirmed'
     GROUP BY b.user_id, u.full_name, u.phone, u.email
     ORDER BY booking_count DESC, revenue DESC, booked_hours DESC, u.full_name ASC
     LIMIT 5",
    'ss',
    [$periodStart, $periodEnd]
);

$topCourt = $topCourtRows[0] ?? null;
$topCustomer = $loyalCustomerRows[0] ?? null;

$hourUsage = [];
for ($hour = 4; $hour < 22; $hour++) {
    $hourUsage[$hour] = 0;
}

$revenueTrendLabels = [];
$revenueTrendValues = [];

if ($reportType === 'day') {
    foreach ($hourUsage as $hour => $count) {
        $revenueTrendLabels[] = sprintf('%02d:00', $hour);
        $revenueTrendValues[$hour] = 0;
    }

    foreach ($confirmedBookingsRaw as $booking) {
        $startHour = (int) substr($booking['start_time'], 0, 2);
        $endHour = (int) substr($booking['end_time'], 0, 2);
        $bookingDate = $booking['booking_date'];

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            if (!array_key_exists($hour, $hourUsage)) {
                continue;
            }

            $hourUsage[$hour]++;
            $revenueTrendValues[$hour] += getHourPrice($bookingDate, $hour);
        }
    }

    $revenueTrendValues = array_values($revenueTrendValues);
} else {
    $trendRows = [];

    if ($reportType === 'month') {
        $trendRows = dbSelectAll(
            $conn,
            "SELECT DAY(booking_date) AS point_label, COALESCE(SUM(total_price), 0) AS revenue
             FROM bookings
             WHERE booking_date BETWEEN ? AND ?
               AND status = 'confirmed'
             GROUP BY DAY(booking_date)
             ORDER BY DAY(booking_date) ASC",
            'ss',
            [$periodStart, $periodEnd]
        );

        $daysOfMonth = (int) date('t', strtotime($periodStart));
        $trendMap = [];
        foreach ($trendRows as $row) {
            $trendMap[(int) $row['point_label']] = (float) $row['revenue'];
        }

        for ($day = 1; $day <= $daysOfMonth; $day++) {
            $revenueTrendLabels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $revenueTrendValues[] = $trendMap[$day] ?? 0;
        }
    } else {
        $trendRows = dbSelectAll(
            $conn,
            "SELECT MONTH(booking_date) AS point_label, COALESCE(SUM(total_price), 0) AS revenue
             FROM bookings
             WHERE booking_date BETWEEN ? AND ?
               AND status = 'confirmed'
             GROUP BY MONTH(booking_date)
             ORDER BY MONTH(booking_date) ASC",
            'ss',
            [$periodStart, $periodEnd]
        );

        $trendMap = [];
        foreach ($trendRows as $row) {
            $trendMap[(int) $row['point_label']] = (float) $row['revenue'];
        }

        for ($monthIndex = 1; $monthIndex <= 12; $monthIndex++) {
            $revenueTrendLabels[] = 'T' . $monthIndex;
            $revenueTrendValues[] = $trendMap[$monthIndex] ?? 0;
        }
    }

    foreach ($confirmedBookingsRaw as $booking) {
        $startHour = (int) substr($booking['start_time'], 0, 2);
        $endHour = (int) substr($booking['end_time'], 0, 2);

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            if (array_key_exists($hour, $hourUsage)) {
                $hourUsage[$hour]++;
            }
        }
    }
}

$peakHourCount = max($hourUsage ?: [0]);
$peakHours = [];
if ($peakHourCount > 0) {
    foreach ($hourUsage as $hour => $count) {
        if ($count === $peakHourCount) {
            $peakHours[] = sprintf('%02d:00 - %02d:00', $hour, $hour + 1);
        }
    }
}

$peakHourLabel = $peakHours !== [] ? implode(', ', array_slice($peakHours, 0, 2)) : 'Chưa có dữ liệu';
$peakHourNote = $peakHourCount > 0
    ? $peakHourCount . ' lượt sử dụng trong kỳ'
    : 'Chưa phát sinh lượt đặt nào trong kỳ';

$lineChart = buildLineChartGeometry($revenueTrendValues);
$trendMaxValue = max($revenueTrendValues ?: [0]);
$courtBarMax = max(array_map(static function ($row) {
    return (int) $row['booking_count'];
}, $topCourtRows ?: [['booking_count' => 0]]));

require_once '../includes/header.php';
?>

<div class="page-title report-page-title">
    <div>
        <h2>Báo cáo và thống kê</h2>
        <p class="page-subtitle">Theo dõi doanh thu, hiệu suất khai thác sân, hành vi đặt sân và nhóm khách hàng nổi bật.</p>
    </div>
</div>

<div class="report-period-strip">
    <div class="card report-period-card <?= $reportType === 'day' ? 'report-period-card-active' : '' ?>">
        <span>Doanh thu hôm nay</span>
        <strong><?= formatMoney($todayRevenueRow['total'] ?? 0) ?></strong>
        <small><?= date('d/m/Y', strtotime($currentDay)) ?></small>
    </div>
    <div class="card report-period-card <?= $reportType === 'month' ? 'report-period-card-active' : '' ?>">
        <span>Doanh thu tháng này</span>
        <strong><?= formatMoney($monthRevenueRow['total'] ?? 0) ?></strong>
        <small><?= date('m/Y') ?></small>
    </div>
    <div class="card report-period-card <?= $reportType === 'year' ? 'report-period-card-active' : '' ?>">
        <span>Doanh thu năm nay</span>
        <strong><?= formatMoney($yearRevenueRow['total'] ?? 0) ?></strong>
        <small><?= $currentYear ?></small>
    </div>
</div>

<div class="form-box report-filter-box">
    <form method="GET" class="report-filter-grid">
        <div class="report-filter-field">
            <label>Kiểu báo cáo</label>
            <select name="type">
                <option value="day" <?= $reportType === 'day' ? 'selected' : '' ?>>Theo ngày</option>
                <option value="month" <?= $reportType === 'month' ? 'selected' : '' ?>>Theo tháng</option>
                <option value="year" <?= $reportType === 'year' ? 'selected' : '' ?>>Theo năm</option>
            </select>
        </div>

        <div class="report-filter-field">
            <label>Ngày</label>
            <input type="date" name="date" value="<?= e($selectedDate) ?>">
        </div>

        <div class="report-filter-field">
            <label>Tháng</label>
            <input type="month" name="month" value="<?= e($selectedMonth) ?>">
        </div>

        <div class="report-filter-field">
            <label>Năm</label>
            <input type="number" name="year" min="2020" max="2100" step="1" value="<?= e($selectedYear) ?>">
        </div>

        <div class="report-filter-actions">
            <button type="submit">Xem báo cáo</button>
        </div>
    </form>
    <p class="report-filter-note">Dữ liệu đang hiển thị cho <strong><?= e($periodLabel) ?></strong>, từ <?= date('d/m/Y', strtotime($periodStart)) ?> đến <?= date('d/m/Y', strtotime($periodEnd)) ?>.</p>
</div>

<div class="dashboard-grid report-kpi-grid">
    <div class="card report-kpi-card report-kpi-primary">
        <h3>Doanh thu trong kỳ</h3>
        <div class="number"><?= formatMoney($totalRevenue) ?></div>
        <p><?= $confirmedBookings ?> lượt đặt đã xác nhận</p>
    </div>

    <div class="card report-kpi-card">
        <h3>Tỷ lệ sân được đặt</h3>
        <div class="number"><?= formatPercent($occupancyRate) ?></div>
        <p><?= $bookedHours ?> / <?= $totalAvailableHours ?> giờ khai thác</p>
    </div>

    <div class="card report-kpi-card">
        <h3>Tỷ lệ hủy sân</h3>
        <div class="number"><?= formatPercent($cancellationRate) ?></div>
        <p><?= $cancelledBookings ?> / <?= $totalBookings ?> đơn trong kỳ</p>
    </div>

    <div class="card report-kpi-card">
        <h3>Khung giờ đông khách nhất</h3>
        <div class="number report-kpi-text"><?= e($peakHourLabel) ?></div>
        <p><?= e($peakHourNote) ?></p>
    </div>
 </div>

<div class="report-insight-grid">
    <div class="card report-insight-card">
        <div class="report-insight-head">
            <h3>Sân được đặt nhiều nhất</h3>
            <span class="report-chip">Top sân</span>
        </div>
        <?php if ($topCourt): ?>
            <div class="report-insight-highlight"><?= e($topCourt['court_name']) ?></div>
            <div class="report-insight-meta">
                <span><?= (int) $topCourt['booking_count'] ?> lượt đặt</span>
                <span><?= (int) $topCourt['booked_hours'] ?> giờ sử dụng</span>
                <span><?= formatMoney($topCourt['revenue']) ?></span>
            </div>
        <?php else: ?>
            <p class="text-muted">Chưa có dữ liệu đặt sân trong kỳ.</p>
        <?php endif; ?>
    </div>

    <div class="card report-insight-card">
        <div class="report-insight-head">
            <h3>Khách hàng thân thiết</h3>
            <span class="report-chip">Top khách</span>
        </div>
        <?php if ($topCustomer): ?>
            <div class="report-insight-highlight"><?= e($topCustomer['full_name']) ?></div>
            <div class="report-insight-meta">
                <span><?= (int) $topCustomer['booking_count'] ?> lượt đặt</span>
                <span><?= (int) $topCustomer['booked_hours'] ?> giờ chơi</span>
                <span><?= formatMoney($topCustomer['revenue']) ?></span>
            </div>
        <?php else: ?>
            <p class="text-muted">Chưa có khách hàng nổi bật trong kỳ.</p>
        <?php endif; ?>
    </div>
</div>

<div class="report-chart-grid">
    <div class="card report-chart-card report-chart-wide">
        <div class="report-section-head">
            <div>
                <h3><?= e($trendTitle) ?></h3>
                <p><?= e($trendSubtitle) ?></p>
            </div>
            <strong><?= formatMoney($trendMaxValue) ?></strong>
        </div>

        <?php if (array_sum($revenueTrendValues) > 0): ?>
            <div class="report-line-chart">
                <svg viewBox="0 0 640 220" role="img" aria-label="<?= e($trendTitle) ?>">
                    <defs>
                        <linearGradient id="report-line-fill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#60a5fa" stop-opacity="0.38"></stop>
                            <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.04"></stop>
                        </linearGradient>
                    </defs>
                    <line x1="24" y1="196" x2="616" y2="196" class="report-axis-line"></line>
                    <polygon points="<?= e($lineChart['area']) ?>" class="report-line-area"></polygon>
                    <polyline points="<?= e($lineChart['line']) ?>" class="report-line-path"></polyline>
                    <?php foreach ($lineChart['dots'] as $dot): ?>
                        <circle cx="<?= $dot['x'] ?>" cy="<?= $dot['y'] ?>" r="4.5" class="report-line-dot"></circle>
                    <?php endforeach; ?>
                </svg>
                <div class="report-line-labels">
                    <?php foreach ($revenueTrendLabels as $label): ?>
                        <span><?= e($label) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="report-empty-state">Chưa có doanh thu trong kỳ để hiển thị biểu đồ đường.</div>
        <?php endif; ?>
    </div>

    <div class="card report-chart-card">
        <div class="report-section-head">
            <div>
                <h3>Biểu đồ cột theo sân</h3>
                <p>Số lượt đặt của từng sân trong kỳ.</p>
            </div>
        </div>

        <?php if ($topCourtRows !== []): ?>
            <div class="report-bar-chart">
                <?php foreach ($topCourtRows as $row): ?>
                    <?php
                    $bookingCount = (int) $row['booking_count'];
                    $heightPercent = $courtBarMax > 0 ? max(12, ($bookingCount / $courtBarMax) * 100) : 0;
                    ?>
                    <div class="report-bar-item">
                        <div class="report-bar-value"><?= $bookingCount ?></div>
                        <div class="report-bar-track">
                            <div class="report-bar-fill" style="height: <?= $heightPercent ?>%;"></div>
                        </div>
                        <div class="report-bar-label"><?= e($row['court_name']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="report-empty-state">Chưa có dữ liệu sân để hiển thị biểu đồ cột.</div>
        <?php endif; ?>
    </div>

    <div class="card report-chart-card">
        <div class="report-section-head">
            <div>
                <h3>Biểu đồ tròn</h3>
                <p>So sánh sử dụng sân và tỷ lệ hủy sân.</p>
            </div>
        </div>

        <div class="report-donut-grid">
            <div class="report-donut-card">
                <div class="report-donut" style="--value: <?= max(0, min(100, $occupancyRate)) ?>%; --fill: #2563eb; --rest: #dbeafe;">
                    <span><?= formatPercent($occupancyRate) ?></span>
                </div>
                <strong>Tỷ lệ sân được đặt</strong>
                <small><?= $bookedHours ?> giờ đã bán</small>
            </div>

            <div class="report-donut-card">
                <div class="report-donut" style="--value: <?= max(0, min(100, $cancellationRate)) ?>%; --fill: #ef4444; --rest: #fee2e2;">
                    <span><?= formatPercent($cancellationRate) ?></span>
                </div>
                <strong>Tỷ lệ hủy sân</strong>
                <small><?= $cancelledBookings ?> đơn đã hủy</small>
            </div>
        </div>
    </div>
</div>

<div class="report-detail-grid">
    <div class="table-wrapper">
        <div class="report-section-head report-table-head">
            <div>
                <h3>Khách hàng thân thiết</h3>
                <p>Xếp hạng theo số lượt đặt và doanh thu mang lại.</p>
            </div>
        </div>
        <table>
            <tr>
                <th>Khách hàng</th>
                <th>Liên hệ</th>
                <th>Lượt đặt</th>
                <th>Giờ chơi</th>
                <th>Doanh thu</th>
            </tr>
            <?php if ($loyalCustomerRows !== []): ?>
                <?php foreach ($loyalCustomerRows as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['contact']) ?></td>
                        <td><?= (int) $row['booking_count'] ?></td>
                        <td><?= (int) $row['booked_hours'] ?> giờ</td>
                        <td><?= formatMoney($row['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">Chưa có dữ liệu khách hàng thân thiết trong kỳ.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="table-wrapper">
        <div class="report-section-head report-table-head">
            <div>
                <h3>Chi tiết booking trong kỳ</h3>
                <p>Danh sách đơn đặt để đối chiếu doanh thu, thanh toán và trạng thái.</p>
            </div>
        </div>
        <table>
            <tr>
                <th>Ngày</th>
                <th>Khách hàng</th>
                <th>Sân</th>
                <th>Giờ</th>
                <th>Tổng tiền</th>
                <th>Thanh toán</th>
                <th>Trạng thái</th>
            </tr>
            <?php if ($allBookings !== []): ?>
                <?php foreach ($allBookings as $row): ?>
                    <tr>
                        <td><?= e($row['booking_date']) ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['court_name']) ?></td>
                        <td><?= e($row['start_time']) ?> - <?= e($row['end_time']) ?></td>
                        <td><?= formatMoney($row['total_price']) ?></td>
                        <td>
                            <span class="badge <?= $row['payment_status'] === 'paid' ? 'badge-paid' : 'badge-unpaid' ?>">
                                <?= $row['payment_status'] === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $row['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-cancelled' ?>">
                                <?= $row['status'] === 'confirmed' ? 'Đã xác nhận' : 'Đã hủy' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">Không có dữ liệu trong kỳ đã chọn.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
