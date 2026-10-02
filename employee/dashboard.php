<?php
/**
 * HRMS - Employee Dashboard
 * Complete personal workspace with live attendance, leave balance, quick actions & notifications
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'Employee Dashboard');

$pdo = getDBConnection();
$empId = getCurrentEmployeeId();
$userId = getCurrentUserId();

// Get employee & department details
$empStmt = $pdo->prepare("SELECT e.*, d.name AS department_name 
                           FROM employees e 
                           LEFT JOIN departments d ON e.department_id = d.id 
                           WHERE e.id = ?");
$empStmt->execute([$empId]);
$employee = $empStmt->fetch();

// Attendance this month
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$attStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance 
                           WHERE employee_id = ? AND date BETWEEN ? AND ?
                           GROUP BY status");
$attStmt->execute([$empId, $monthStart, $monthEnd]);
$attStats = [];
while ($row = $attStmt->fetch()) {
    $attStats[$row['status']] = (int)$row['count'];
}
$presentDays = ($attStats['present'] ?? 0) + ($attStats['late'] ?? 0);
$absentDays = $attStats['absent'] ?? 0;
$onLeaveDays = $attStats['on-leave'] ?? 0;

// Today's attendance record
$todayAtt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
$todayAtt->execute([$empId]);
$todayAttendance = $todayAtt->fetch();

// Accurate Annual Leave Balances (Quota - Approved)
$usedLeavesStmt = $pdo->prepare("SELECT leave_type, COALESCE(SUM(total_days), 0) as used_days 
                                 FROM leaves 
                                 WHERE employee_id = ? AND status = 'approved' AND YEAR(start_date) = ?
                                 GROUP BY leave_type");
$usedLeavesStmt->execute([$empId, $currentYear]);
$usedMap = [];
while ($r = $usedLeavesStmt->fetch()) {
    $usedMap[$r['leave_type']] = (int)$r['used_days'];
}
$quotas = ['casual' => 12, 'sick' => 12, 'earned' => 15];
$totalQuota = 39;
$totalUsed = array_sum($usedMap);
$remainingLeaves = max(0, $totalQuota - $totalUsed);

// Pending leaves
$pendingLeaves = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE employee_id = ? AND status = 'pending'");
$pendingLeaves->execute([$empId]);
$pendingLeaveCount = (int)$pendingLeaves->fetchColumn();

// Latest Payroll record
$lastPayroll = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY year DESC, month DESC LIMIT 1");
$lastPayroll->execute([$empId]);
$lastPay = $lastPayroll->fetch();

// Recent notifications (latest 5)
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$userId]);
$notifications = $notifStmt->fetchAll();

// Upcoming approved/pending leaves
$upcomingLeaves = $pdo->prepare("SELECT * FROM leaves WHERE employee_id = ? AND start_date >= CURDATE() AND status IN ('approved','pending') ORDER BY start_date ASC LIMIT 3");
$upcomingLeaves->execute([$empId]);
$upLeaves = $upcomingLeaves->fetchAll();

// Teammates in same department
$teamStmt = $pdo->prepare("SELECT e.id, e.first_name, e.last_name, e.designation, e.employee_id 
                           FROM employees e 
                           WHERE e.department_id = ? AND e.id != ? AND e.status = 'active' 
                           ORDER BY e.first_name ASC LIMIT 4");
$teamStmt->execute([$employee['department_id'] ?? 0, $empId]);
$teammates = $teamStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner with Live Shift Clock & Punch Action -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>
            Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, 
            <?= sanitize($employee['first_name'] ?? 'there') ?>! 👋
        </h2>
        <p style="margin-top: 4px; opacity: 0.9;">
            <i class='bx bx-calendar'></i> <?= date('l, F j, Y') ?> &bull; 
            <i class='bx bx-time'></i> Shift: <strong>09:00 AM &ndash; 06:00 PM</strong>
        </p>
        <div style="font-family: 'JetBrains Mono', monospace; font-size: 1.15rem; font-weight: 700; margin-top: 10px; background: rgba(0,0,0,0.2); display: inline-block; padding: 4px 12px; border-radius: 6px;" id="liveClock">
            --:--:--
        </div>
    </div>

    <div class="welcome-actions" style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
        <?php if (!$todayAttendance || empty($todayAttendance['check_in'])): ?>
            <a href="<?= APP_URL ?>/employee/attendance.php?action=checkin" class="btn btn-primary btn-lg" style="background: #10b981; border-color: #10b981; font-weight: 700;">
                <i class='bx bx-log-in-circle'></i> Punch Check In
            </a>
            <small style="opacity: 0.85;">Standard punch before 09:30 AM</small>
        <?php elseif ($todayAttendance && empty($todayAttendance['check_out'])): ?>
            <div style="text-align: right;">
                <a href="<?= APP_URL ?>/employee/attendance.php?action=checkout" class="btn btn-warning btn-lg" style="font-weight: 700;">
                    <i class='bx bx-log-out-circle'></i> Punch Check Out
                </a>
                <div style="font-size: 0.82rem; margin-top: 6px; opacity: 0.9;" id="activeTimer">
                    Checked in: <?= date('h:i A', strtotime($todayAttendance['check_in'])) ?>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: right;">
                <span class="badge badge-success" style="padding: 10px 18px; font-size: 0.95rem; font-weight: 700;">
                    <i class='bx bx-check-double'></i> Shift Completed (<?= number_format($todayAttendance['work_hours'] ?? 0, 1) ?> hrs)
                </span>
                <div style="font-size: 0.8rem; margin-top: 4px; opacity: 0.85;">
                    <?= date('h:i A', strtotime($todayAttendance['check_in'])) ?> &ndash; <?= date('h:i A', strtotime($todayAttendance['check_out'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Navigation Action Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 14px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class='bx bx-bolt-circle' style="color: var(--primary); font-size: 1.25rem;"></i> Quick Actions:
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="<?= APP_URL ?>/employee/attendance.php" class="btn btn-outline btn-sm">
                    <i class='bx bx-calendar-check'></i> Attendance Log
                </a>
                <a href="<?= APP_URL ?>/employee/leaves.php" class="btn btn-outline btn-sm">
                    <i class='bx bx-paper-plane'></i> Apply for Leave
                </a>
                <a href="<?= APP_URL ?>/employee/payroll.php" class="btn btn-outline btn-sm">
                    <i class='bx bx-wallet'></i> My Payslips
                </a>
                <?php if ($lastPay): ?>
                <a href="<?= APP_URL ?>/payslip.php?id=<?= $lastPay['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                    <i class='bx bx-printer'></i> Latest Payslip (<?= date('M Y', mktime(0,0,0,(int)$lastPay['month'],10,(int)$lastPay['year'])) ?>)
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/employee/profile.php" class="btn btn-outline btn-sm">
                    <i class='bx bx-user'></i> Update Profile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Key Performance Stats Grid -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Present This Month</h4>
            <div class="stat-number"><?= $presentDays ?></div>
            <div class="stat-change up">
                <i class='bx bx-calendar-check'></i> Days (<?= $attStats['late'] ?? 0 ?> late)
            </div>
        </div>
        <div class="stat-icon blue">
            <i class='bx bxs-calendar-check'></i>
        </div>
    </div>
    
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Remaining Leaves</h4>
            <div class="stat-number"><?= $remainingLeaves ?></div>
            <div class="stat-change up">
                <i class='bx bx-pie-chart-alt'></i> of <?= $totalQuota ?> Annual Quota
            </div>
        </div>
        <div class="stat-icon green">
            <i class='bx bxs-calendar-minus'></i>
        </div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Pending Leaves</h4>
            <div class="stat-number"><?= $pendingLeaveCount ?></div>
            <div class="stat-change <?= $pendingLeaveCount > 0 ? 'down' : 'up' ?>">
                <i class='bx bx-time'></i> Awaiting HR Decision
            </div>
        </div>
        <div class="stat-icon orange">
            <i class='bx bxs-hourglass-top'></i>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-info">
            <h4>Last Disbursed Salary</h4>
            <div class="stat-number">
                <?= $lastPay ? '₹' . number_format($lastPay['net_salary'], 0) : '—' ?>
            </div>
            <?php if ($lastPay): ?>
            <div class="stat-change up">
                <i class='bx bx-check-circle'></i> 
                <?= date('F Y', mktime(0, 0, 0, (int)$lastPay['month'], 10, (int)$lastPay['year'])) ?> (<?= ucfirst($lastPay['payment_status']) ?>)
            </div>
            <?php else: ?>
            <div class="stat-change down"><i class='bx bx-info-circle'></i> No records yet</div>
            <?php endif; ?>
        </div>
        <div class="stat-icon red">
            <i class='bx bxs-wallet'></i>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Employee Info & Leave Quota Overview -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Employee Profile Card -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class='bx bxs-user-badge' style="color: var(--primary-500); margin-right: 8px;"></i>My Profile Summary</h3>
                <a href="<?= APP_URL ?>/employee/profile.php" class="btn btn-sm btn-outline">Edit Profile</a>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Employee ID</span>
                        <span class="info-value" style="font-family: monospace;"><?= sanitize($employee['employee_id'] ?? '') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Department</span>
                        <span class="info-value"><?= sanitize($employee['department_name'] ?? 'General') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Designation</span>
                        <span class="info-value"><?= sanitize($employee['designation'] ?? 'Staff') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Joining Date</span>
                        <span class="info-value"><?= $employee['date_of_joining'] ? date('d M Y', strtotime($employee['date_of_joining'])) : 'N/A' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Employment Type</span>
                        <span class="info-value"><span class="badge badge-primary"><?= ucfirst(str_replace('-', ' ', $employee['employment_type'] ?? 'full-time')) ?></span></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Official Status</span>
                        <span class="info-value"><span class="badge badge-success">Active</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Annual Leave Balance Quotas -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class='bx bxs-pie-chart-alt-2' style="color: var(--success); margin-right: 8px;"></i>Annual Leave Quota Status</h3>
                <a href="<?= APP_URL ?>/employee/leaves.php" class="btn btn-sm btn-outline">Apply Leave</a>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; text-align: center;">
                    <div style="background: var(--bg-hover); padding: 14px; border-radius: var(--border-radius); border-left: 3px solid var(--primary-500);">
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Casual Leave</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: var(--primary-600); margin: 4px 0;">
                            <?= max(0, 12 - ($usedMap['casual'] ?? 0)) ?> <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);">/ 12</span>
                        </div>
                        <small style="color: var(--text-muted);"><?= ($usedMap['casual'] ?? 0) ?> days used</small>
                    </div>

                    <div style="background: var(--bg-hover); padding: 14px; border-radius: var(--border-radius); border-left: 3px solid var(--danger);">
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Sick Leave</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: var(--danger); margin: 4px 0;">
                            <?= max(0, 12 - ($usedMap['sick'] ?? 0)) ?> <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);">/ 12</span>
                        </div>
                        <small style="color: var(--text-muted);"><?= ($usedMap['sick'] ?? 0) ?> days used</small>
                    </div>

                    <div style="background: var(--bg-hover); padding: 14px; border-radius: var(--border-radius); border-left: 3px solid var(--success);">
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Earned Leave</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: var(--success); margin: 4px 0;">
                            <?= max(0, 15 - ($usedMap['earned'] ?? 0)) ?> <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);">/ 15</span>
                        </div>
                        <small style="color: var(--text-muted);"><?= ($usedMap['earned'] ?? 0) ?> days used</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Leaves & Department Teammates -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Upcoming Leaves -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class='bx bxs-calendar-event' style="color: var(--warning); margin-right: 8px;"></i>Upcoming Leaves</h3>
                <a href="<?= APP_URL ?>/employee/leaves.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($upLeaves)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <i class='bx bx-calendar-check' style="font-size: 2.5rem; color: var(--text-muted);"></i>
                    <h4 style="margin: 8px 0 2px 0;">No Upcoming Leaves</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">You do not have any scheduled leave requests.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Schedule</th>
                                <th>Days</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upLeaves as $lv): ?>
                            <tr>
                                <td><span class="badge badge-info"><?= ucfirst($lv['leave_type']) ?></span></td>
                                <td><?= date('d M', strtotime($lv['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($lv['end_date'])) ?></td>
                                <td><strong><?= $lv['total_days'] ?></strong></td>
                                <td>
                                    <span class="badge <?= $lv['status'] === 'approved' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($lv['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Department Teammates -->
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bxs-group' style="color: var(--info); margin-right: 8px;"></i>Department Colleagues</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($teammates)): ?>
                <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.88rem;">
                    No other colleagues currently assigned to this department.
                </div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($teammates as $t): 
                        $tInitials = strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1));
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="user-avatar-sm" style="width: 34px; height: 34px; font-weight: 700; font-size: 0.82rem;">
                                <?= $tInitials ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                    <?= sanitize($t['first_name'] . ' ' . $t['last_name']) ?>
                                </div>
                                <small style="color: var(--text-muted);"><?= sanitize($t['designation'] ?: 'Associate') ?></small>
                            </div>
                        </div>
                        <span class="badge badge-secondary" style="font-family: monospace; font-size: 0.75rem;">
                            <?= sanitize($t['employee_id']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Notifications Feed -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class='bx bxs-bell' style="color: var(--primary); margin-right: 8px;"></i>Recent Notifications</h3>
        <a href="<?= APP_URL ?>/employee/notifications.php" class="btn btn-sm btn-outline">View All Notifications</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($notifications)): ?>
        <div class="empty-state" style="padding: 40px;">
            <i class='bx bx-bell-off' style="font-size: 3rem; color: var(--text-muted);"></i>
            <h4 style="margin: 8px 0 2px 0;">No Recent Notifications</h4>
            <p style="font-size: 0.85rem; color: var(--text-muted);">You're all caught up with your updates.</p>
        </div>
        <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                <div class="notif-icon-wrapper notif-<?= $notif['type'] ?>">
                    <i class='bx <?php
                        echo match($notif['type']) {
                            'success' => 'bx-check-circle',
                            'warning' => 'bx-error',
                            'danger' => 'bx-error-circle',
                            default => 'bx-info-circle'
                        };
                    ?>'></i>
                </div>
                <div class="notif-content" style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><?= sanitize($notif['title']) ?></strong>
                        <small style="color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></small>
                    </div>
                    <p style="margin: 4px 0 0 0;"><?= sanitize($notif['message']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Live Clock
function updateClock() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour12: true });
    const el = document.getElementById('liveClock');
    if (el) el.textContent = timeStr;
}
setInterval(updateClock, 1000);
updateClock();

<?php if ($todayAttendance && empty($todayAttendance['check_out']) && !empty($todayAttendance['check_in'])): ?>
// Live Duty Timer
const checkInTime = new Date('<?= date('Y-m-d') ?>T<?= $todayAttendance['check_in'] ?>').getTime();
function updateDutyTimer() {
    const now = new Date().getTime();
    const diff = Math.max(0, now - checkInTime);
    const hrs = Math.floor(diff / (1000 * 60 * 60));
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const secs = Math.floor((diff % (1000 * 60)) / 1000);
    const timerEl = document.getElementById('activeTimer');
    if (timerEl) {
        timerEl.innerHTML = `Active on duty: <strong>${String(hrs).padStart(2,'0')}h ${String(mins).padStart(2,'0')}m ${String(secs).padStart(2,'0')}s</strong>`;
    }
}
setInterval(updateDutyTimer, 1000);
updateDutyTimer();
<?php endif; ?>
</script>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, var(--primary-700), var(--primary-900));
        color: #fff;
        border-radius: var(--border-radius-xl);
        padding: 28px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        box-shadow: 0 10px 25px -5px rgba(37,99,235,0.25);
    }
    .welcome-content h2 {
        font-size: 1.45rem;
        font-weight: 800;
        margin-bottom: 4px;
        color: #fff;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .info-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    .info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .notification-list {
        padding: 8px;
    }
    .notif-item {
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border-radius: var(--border-radius);
        transition: background var(--transition-fast);
        border-bottom: 1px solid var(--border-color);
    }
    .notif-item:last-child {
        border-bottom: none;
    }
    .notif-item:hover { background: var(--bg-hover); }
    .notif-item.unread { background: var(--primary-50); }
    [data-theme="dark"] .notif-item.unread { background: rgba(59,130,246,0.08); }
    .notif-icon-wrapper {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .notif-icon-wrapper.notif-info { background: var(--info-light); color: var(--info); }
    .notif-icon-wrapper.notif-success { background: var(--success-light); color: var(--success); }
    .notif-icon-wrapper.notif-warning { background: var(--warning-light); color: var(--warning); }
    .notif-icon-wrapper.notif-danger { background: var(--danger-light); color: var(--danger); }
    @media (max-width: 768px) {
        .welcome-banner { flex-direction: column; gap: 16px; align-items: flex-start; }
        .welcome-actions { align-items: flex-start !important; }
        .info-grid { grid-template-columns: 1fr; }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
