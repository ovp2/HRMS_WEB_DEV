<?php
/**
 * HRMS - Employee Attendance Management
 * Allows check-in/out and viewing personal attendance history with CSV export
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'My Attendance');

$pdo = getDBConnection();
$empId = getCurrentEmployeeId();
$today = date('Y-m-d');

// Handle Check In / Check Out via GET or POST
$reqAction = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($reqAction === 'checkin') {
    // Check if attendance record already exists for today
    $check = $pdo->prepare("SELECT id, check_in FROM attendance WHERE employee_id = ? AND date = ?");
    $check->execute([$empId, $today]);
    $existing = $check->fetch();

    if ($existing && $existing['check_in']) {
        redirectWith(APP_URL . '/employee/attendance.php', 'You have already checked in today.', 'info');
    }

    $timeNow = date('H:i:s');
    // Threshold for late check-in: after 09:30:00
    $status = (strtotime($timeNow) > strtotime('09:30:00')) ? 'late' : 'present';

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE attendance SET check_in = ?, status = ? WHERE id = ?");
        $stmt->execute([$timeNow, $status, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, check_in, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$empId, $today, $timeNow, $status]);
    }

    redirectWith(APP_URL . '/employee/attendance.php', 'Check-in successful at ' . date('h:i A') . '! Have a productive day.', 'success');
}

if ($reqAction === 'checkout') {
    $check = $pdo->prepare("SELECT id, check_in, check_out FROM attendance WHERE employee_id = ? AND date = ?");
    $check->execute([$empId, $today]);
    $existing = $check->fetch();

    if (!$existing || !$existing['check_in']) {
        redirectWith(APP_URL . '/employee/attendance.php', 'Please check in first before checking out.', 'warning');
    }

    if ($existing['check_out']) {
        redirectWith(APP_URL . '/employee/attendance.php', 'You have already checked out today.', 'info');
    }

    $timeNow = date('H:i:s');
    $t1 = strtotime($existing['check_in']);
    $t2 = strtotime($timeNow);
    $hours = ($t2 > $t1) ? round(($t2 - $t1) / 3600, 2) : 0;

    $stmt = $pdo->prepare("UPDATE attendance SET check_out = ?, work_hours = ? WHERE id = ?");
    $stmt->execute([$timeNow, $hours, $existing['id']]);

    redirectWith(APP_URL . '/employee/attendance.php', 'Checked out successfully at ' . date('h:i A') . '! Total hours today: ' . $hours . ' hrs.', 'success');
}

// Fetch today's record
$todayStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$todayStmt->execute([$empId, $today]);
$todayRecord = $todayStmt->fetch();

// Month & Year Filter for History
$selMonth = (int)(!empty($_GET['month']) ? $_GET['month'] : date('n'));
$selYear = (int)(!empty($_GET['year']) ? $_GET['year'] : date('Y'));

// Attendance records for the selected month
$monthStart = sprintf('%04d-%02d-01', $selYear, $selMonth);
$monthEnd = date('Y-m-t', strtotime($monthStart));

$historyStmt = $pdo->prepare("SELECT * FROM attendance 
                              WHERE employee_id = ? AND date BETWEEN ? AND ? 
                              ORDER BY date DESC");
$historyStmt->execute([$empId, $monthStart, $monthEnd]);
$history = $historyStmt->fetchAll();

// CSV Export Stream Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=my_attendance_' . $selYear . '_' . str_pad((string)$selMonth, 2, '0', STR_PAD_LEFT) . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Day', 'Status', 'Check In', 'Check Out', 'Work Hours', 'Notes']);
    foreach ($history as $h) {
        $ts = strtotime($h['date']);
        fputcsv($output, [
            $h['date'],
            date('l', $ts),
            ucfirst($h['status']),
            $h['check_in'] ?? 'N/A',
            $h['check_out'] ?? 'N/A',
            $h['work_hours'] ?? '0.00',
            $h['notes'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Monthly statistics
$presentDays = 0;
$lateDays = 0;
$halfDays = 0;
$absentDays = 0;
$leaveDays = 0;
$totalHoursLogged = 0;

foreach ($history as $h) {
    if ($h['status'] === 'present') $presentDays++;
    elseif ($h['status'] === 'late') $lateDays++;
    elseif ($h['status'] === 'half-day') $halfDays++;
    elseif ($h['status'] === 'absent') $absentDays++;
    elseif ($h['status'] === 'on-leave') $leaveDays++;
    $totalHoursLogged += (float)($h['work_hours'] ?? 0);
}

$effectivePresent = $presentDays + $lateDays + ($halfDays * 0.5);
$totalRecorded = count($history);
$attendanceRate = $totalRecorded > 0 ? round(($effectivePresent / $totalRecorded) * 100, 1) : 100;

include __DIR__ . '/../includes/header.php';
?>

<!-- Today's Live Attendance Punch Card -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, var(--bg-card), var(--bg-hover)); border: 1px solid var(--border-color);">
    <div class="card-body" style="padding: 24px 28px;">
        <div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; align-items: center;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: var(--primary-600); letter-spacing: 0.05em;">
                        Today's Attendance Status
                    </div>
                    <span style="font-family: monospace; font-size: 0.95rem; font-weight: 700; background: var(--bg-card); border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 4px;" id="liveClock">
                        --:--:--
                    </span>
                </div>
                <h2 style="margin: 6px 0 4px 0; font-size: 1.5rem; font-weight: 800;">
                    <?= date('l, F j, Y') ?>
                </h2>
                <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; color: var(--text-secondary); font-size: 0.95rem; margin-top: 8px;">
                    <div>
                        <i class='bx bx-log-in' style="color: var(--success);"></i> 
                        Check In: <strong><?= (!empty($todayRecord['check_in'])) ? date('h:i A', strtotime($todayRecord['check_in'])) : 'Not checked in' ?></strong>
                    </div>
                    <div>
                        <i class='bx bx-log-out' style="color: var(--danger);"></i> 
                        Check Out: <strong><?= (!empty($todayRecord['check_out'])) ? date('h:i A', strtotime($todayRecord['check_out'])) : 'Not checked out' ?></strong>
                    </div>
                    <?php if (!empty($todayRecord['work_hours'])): ?>
                    <div>
                        <i class='bx bx-time-five' style="color: var(--primary-600);"></i> 
                        Worked: <strong><?= number_format($todayRecord['work_hours'], 2) ?> hrs</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <?php if (!$todayRecord || empty($todayRecord['check_in'])): ?>
                <a href="<?= APP_URL ?>/employee/attendance.php?action=checkin" class="btn btn-primary btn-lg" style="padding: 14px 28px; font-size: 1rem; font-weight: 700;">
                    <i class='bx bx-log-in-circle'></i> Punch Check In
                </a>
                <?php elseif ($todayRecord && empty($todayRecord['check_out'])): ?>
                <div style="text-align: right;">
                    <a href="<?= APP_URL ?>/employee/attendance.php?action=checkout" class="btn btn-warning btn-lg" style="padding: 14px 28px; font-size: 1rem; font-weight: 700;">
                        <i class='bx bx-log-out-circle'></i> Punch Check Out
                    </a>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 6px;" id="shiftTimer">
                        Shift active...
                    </div>
                </div>
                <?php else: ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: var(--success-light); color: var(--success); border-radius: var(--border-radius-lg); font-weight: 700;">
                    <i class='bx bxs-check-shield' style="font-size: 1.5rem;"></i> Today's Shift Completed
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Performance Metrics -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Present Days</h4>
            <div class="stat-number"><?= $presentDays + $lateDays ?></div>
            <div class="stat-change up"><i class='bx bx-check'></i> <?= $lateDays > 0 ? "$lateDays late" : "All on time" ?></div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-user-check'></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Leaves Taken</h4>
            <div class="stat-number"><?= $leaveDays ?></div>
            <div class="stat-change up"><i class='bx bx-calendar-event'></i> Approved</div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-calendar-minus'></i></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Total Hours</h4>
            <div class="stat-number"><?= number_format($totalHoursLogged, 1) ?></div>
            <div class="stat-change up"><i class='bx bx-timer'></i> Logged on duty</div>
        </div>
        <div class="stat-icon blue"><i class='bx bxs-time'></i></div>
    </div>
    <div class="stat-card red">
        <div class="stat-info">
            <h4>Attendance Rate</h4>
            <div class="stat-number"><?= $attendanceRate ?>%</div>
            <div class="stat-change <?= $attendanceRate >= 90 ? 'up' : 'down' ?>">
                <i class='bx bx-line-chart'></i> <?= $absentDays ?> unexcused absent
            </div>
        </div>
        <div class="stat-icon red"><i class='bx bxs-pie-chart-alt-2'></i></div>
    </div>
</div>

<!-- Month Filter & Attendance Table -->
<div class="card">
    <div class="card-header" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
        <div class="card-title">
            Attendance Log &mdash; <?= date('F Y', strtotime($monthStart)) ?>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Export CSV -->
            <a href="?export=csv&month=<?= $selMonth ?>&year=<?= $selYear ?>" class="btn btn-outline btn-sm">
                <i class='bx bx-export'></i> Export CSV
            </a>

            <!-- Month/Year Filter -->
            <form method="GET" action="" style="display: flex; gap: 8px; align-items: center;">
                <select name="month" class="form-control" style="width: 130px;" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $selMonth === $m ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-control" style="width: 95px;" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 1; $y <= date('Y'); $y++): ?>
                    <option value="<?= $y ?>" <?= $selYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Work Duration</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class='bx bx-calendar-x' style="font-size: 2.5rem; margin-bottom: 8px; display: block;"></i>
                        No attendance records logged for this month.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($history as $row): 
                        $ts = strtotime($row['date']);
                    ?>
                    <tr>
                        <td><strong><?= date('d M Y', $ts) ?></strong></td>
                        <td><?= date('l', $ts) ?></td>
                        <td>
                            <?php if ($row['status'] === 'present'): ?>
                            <span class="badge badge-success"><i class='bx bx-check'></i> Present</span>
                            <?php elseif ($row['status'] === 'late'): ?>
                            <span class="badge badge-warning"><i class='bx bx-time'></i> Late</span>
                            <?php elseif ($row['status'] === 'half-day'): ?>
                            <span class="badge badge-info">Half-Day</span>
                            <?php elseif ($row['status'] === 'on-leave'): ?>
                            <span class="badge badge-secondary">On Leave</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Absent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-family: monospace;">
                                <?= $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '--:--' ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-family: monospace;">
                                <?= $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '--:--' ?>
                            </span>
                        </td>
                        <td>
                            <?= $row['work_hours'] !== null ? '<strong>' . number_format($row['work_hours'], 2) . '</strong> hrs' : '-' ?>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?= sanitize($row['notes'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Live clock
function updateLiveClock() {
    const el = document.getElementById('liveClock');
    if (el) {
        el.textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

<?php if ($todayRecord && empty($todayRecord['check_out']) && !empty($todayRecord['check_in'])): ?>
// Shift duty live timer
const checkInMs = new Date('<?= date('Y-m-d') ?>T<?= $todayRecord['check_in'] ?>').getTime();
function updateShiftTimer() {
    const now = new Date().getTime();
    const diff = Math.max(0, now - checkInMs);
    const hrs = Math.floor(diff / (1000 * 60 * 60));
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const secs = Math.floor((diff % (1000 * 60)) / 1000);
    const timerEl = document.getElementById('shiftTimer');
    if (timerEl) {
        timerEl.innerHTML = `Active on duty: <strong>${String(hrs).padStart(2,'0')}h ${String(mins).padStart(2,'0')}m ${String(secs).padStart(2,'0')}s</strong>`;
    }
}
setInterval(updateShiftTimer, 1000);
updateShiftTimer();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
