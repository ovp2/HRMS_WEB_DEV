<?php
/**
 * HRMS - Admin Dashboard
 * Phase 2: Comprehensive Dashboard with Real-Time Statistics, Quick Actions, and Responsive Layout
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Dashboard');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();

// Handle Quick Leave Approval / Rejection directly from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_leave_action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/dashboard.php', 'Invalid security token.', 'danger');
    }

    $leaveId = (int)($_POST['leave_id'] ?? 0);
    $decision = in_array($_POST['decision'] ?? '', ['approved', 'rejected']) ? $_POST['decision'] : '';

    if ($leaveId > 0 && !empty($decision)) {
        // Fetch leave details for notification
        $lStmt = $pdo->prepare("SELECT l.*, e.user_id, e.first_name, e.last_name FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE l.id = ?");
        $lStmt->execute([$leaveId]);
        $leaveData = $lStmt->fetch();

        if ($leaveData) {
            $stmt = $pdo->prepare("UPDATE leaves SET status = ?, approved_by = ?, admin_remarks = 'Quick review from Dashboard' WHERE id = ?");
            $stmt->execute([$decision, getCurrentUserId(), $leaveId]);

            // If approved, mark on-leave in attendance
            if ($decision === 'approved') {
                $start = new DateTime($leaveData['start_date']);
                $end = (new DateTime($leaveData['end_date']))->modify('+1 day');
                $period = new DatePeriod($start, new DateInterval('P1D'), $end);

                $attInsert = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, notes) 
                                            VALUES (?, ?, 'on-leave', 'Approved Leave') 
                                            ON DUPLICATE KEY UPDATE status = 'on-leave', notes = VALUES(notes)");
                foreach ($period as $dt) {
                    $attInsert->execute([$leaveData['employee_id'], $dt->format('Y-m-d')]);
                }
            }

            // Send notification to employee
            $notifType = ($decision === 'approved') ? 'success' : 'danger';
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $notif->execute([
                $leaveData['user_id'],
                'Leave Application ' . ucfirst($decision),
                'Your ' . ucfirst($leaveData['leave_type']) . ' leave request was ' . $decision . ' by HR.',
                $notifType,
                APP_URL . '/employee/leaves.php'
            ]);

            redirectWith(APP_URL . '/admin/dashboard.php', "Leave request #$leaveId was successfully $decision.", 'success');
        }
    }
}

// 1. Core Workforce Statistics
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$totalDepartments = (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'active'")->fetchColumn();
$pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();

// 2. Attendance Metrics for Today
$presentToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status IN ('present', 'late')")->fetchColumn();
$lateToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'late'")->fetchColumn();
$halfDayToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'half-day'")->fetchColumn();

// 3. Employees On Leave Today
$onLeaveTodayStmt = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM leaves WHERE status = 'approved' AND CURDATE() BETWEEN start_date AND end_date");
$onLeaveToday = (int)$onLeaveTodayStmt->fetchColumn();

// Calculate absent (active staff minus present, half-day, and on-leave)
$absentToday = max(0, $totalEmployees - ($presentToday + $halfDayToday + $onLeaveToday));
$attendanceRate = ($totalEmployees > 0) ? round(($presentToday / $totalEmployees) * 100) : 0;

// 4. Monthly Payroll Budget Commitment
$monthlyBudget = (float)$pdo->query("SELECT COALESCE(SUM(salary), 0) FROM employees WHERE status = 'active'")->fetchColumn();

// 5. Recent Leave Requests (with employee code, name, designation)
$recentLeaves = $pdo->query("SELECT l.*, e.first_name, e.last_name, e.employee_id AS emp_code, e.designation, d.name AS department_name
                              FROM leaves l 
                              JOIN employees e ON l.employee_id = e.id 
                              LEFT JOIN departments d ON e.department_id = d.id
                              ORDER BY CASE WHEN l.status = 'pending' THEN 0 ELSE 1 END, l.applied_at DESC 
                              LIMIT 6")->fetchAll();

// 6. Recent Attendance Punches Today
$recentAttendance = $pdo->query("SELECT a.*, e.first_name, e.last_name, e.employee_id AS emp_code, e.designation, d.name as department_name
                                  FROM attendance a 
                                  JOIN employees e ON a.employee_id = e.id 
                                  LEFT JOIN departments d ON e.department_id = d.id
                                  WHERE a.date = CURDATE()
                                  ORDER BY a.check_in DESC 
                                  LIMIT 6")->fetchAll();

// 7. Department-wise Headcount Distribution
$deptStats = $pdo->query("SELECT d.id, d.name, COUNT(e.id) as count 
                           FROM departments d 
                           LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
                           WHERE d.status = 'active'
                           GROUP BY d.id, d.name
                           ORDER BY count DESC")->fetchAll();

// 8. Employees Out of Office Today (Approved leaves covering today)
$outOfOffice = $pdo->query("SELECT l.leave_type, l.start_date, l.end_date, e.first_name, e.last_name, e.employee_id AS emp_code, d.name as department_name 
                             FROM leaves l 
                             JOIN employees e ON l.employee_id = e.id 
                             LEFT JOIN departments d ON e.department_id = d.id
                             WHERE l.status = 'approved' AND CURDATE() BETWEEN l.start_date AND l.end_date 
                             LIMIT 5")->fetchAll();
// 9. 7-Day Attendance Trend for Visual Analytics
$sevenDaysTrend = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $sevenDaysTrend[$d] = ['label' => date('D, M j', strtotime($d)), 'present' => 0, 'late' => 0, 'absent' => 0];
}
$trendStmt = $pdo->query("SELECT date, status, COUNT(*) as cnt 
                          FROM attendance 
                          WHERE date >= CURDATE() - INTERVAL 6 DAY 
                          GROUP BY date, status");
while ($tr = $trendStmt->fetch()) {
    $dt = $tr['date'];
    if (isset($sevenDaysTrend[$dt])) {
        if ($tr['status'] === 'present') $sevenDaysTrend[$dt]['present'] = (int)$tr['cnt'];
        elseif ($tr['status'] === 'late') $sevenDaysTrend[$dt]['late'] = (int)$tr['cnt'];
        elseif ($tr['status'] === 'absent') $sevenDaysTrend[$dt]['absent'] = (int)$tr['cnt'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner with Dynamic Quick Actions -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= sanitize($currentUser) ?>! 👋</h2>
        <p><i class='bx bx-calendar'></i> <?= date('l, F j, Y') ?> &bull; System Status: <strong>Operational</strong></p>
    </div>
    <div class="welcome-actions">
        <a href="<?= APP_URL ?>/admin/employees.php" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff;">
            <i class='bx bx-user-plus'></i> Add Employee
        </a>
        <a href="<?= APP_URL ?>/admin/attendance.php" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff;">
            <i class='bx bx-calendar-check'></i> Attendance
        </a>
        <a href="<?= APP_URL ?>/admin/leaves.php" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff; position: relative;">
            <i class='bx bx-calendar-minus'></i> Leaves
            <?php if ($pendingLeaves > 0): ?>
            <span style="background: var(--danger); color: #fff; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; margin-left: 4px;">
                <?= $pendingLeaves ?>
            </span>
            <?php endif; ?>
        </a>
        <a href="<?= APP_URL ?>/admin/payroll.php" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff;">
            <i class='bx bx-wallet'></i> Payroll
        </a>
        <a href="<?= APP_URL ?>/admin/notifications.php" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff;">
            <i class='bx bx-broadcast'></i> Broadcast
        </a>
    </div>
</div>

<!-- 6-Metric Stat Cards Grid -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <!-- Total Employees -->
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Total Staff</h4>
            <div class="stat-number"><?= $totalEmployees ?></div>
            <div class="stat-change up">
                <i class='bx bx-group'></i> Active Workforce
            </div>
        </div>
        <div class="stat-icon blue">
            <i class='bx bxs-user-detail'></i>
        </div>
    </div>
    
    <!-- Active Departments -->
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Departments</h4>
            <div class="stat-number"><?= $totalDepartments ?></div>
            <div class="stat-change up">
                <i class='bx bx-buildings'></i> Operational Units
            </div>
        </div>
        <div class="stat-icon green">
            <i class='bx bxs-buildings'></i>
        </div>
    </div>
    
    <!-- Today's Attendance -->
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Present Today</h4>
            <div class="stat-number"><?= $presentToday ?> <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">/ <?= $totalEmployees ?></span></div>
            <div class="stat-change <?= $attendanceRate >= 75 ? 'up' : 'down' ?>">
                <i class='bx bx-pie-chart-alt'></i> <?= $attendanceRate ?>% Turnout
            </div>
        </div>
        <div class="stat-icon blue">
            <i class='bx bxs-calendar-check'></i>
        </div>
    </div>

    <!-- Pending Leaves -->
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Pending Leaves</h4>
            <div class="stat-number"><?= $pendingLeaves ?></div>
            <div class="stat-change <?= $pendingLeaves > 0 ? 'down' : 'up' ?>">
                <i class='bx bx-<?= $pendingLeaves > 0 ? 'time-five' : 'check' ?>'></i> 
                <?= $pendingLeaves > 0 ? 'Needs Attention' : 'All Clear' ?>
            </div>
        </div>
        <div class="stat-icon orange">
            <i class='bx bxs-calendar-minus'></i>
        </div>
    </div>

    <!-- Monthly Payroll Budget -->
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Monthly Payroll</h4>
            <div class="stat-number" style="font-size: 1.45rem;">₹<?= number_format($monthlyBudget, 0) ?></div>
            <div class="stat-change up">
                <i class='bx bx-credit-card'></i> Budget Commitment
            </div>
        </div>
        <div class="stat-icon green">
            <i class='bx bxs-wallet'></i>
        </div>
    </div>

    <!-- Out of Office Today -->
    <div class="stat-card red">
        <div class="stat-info">
            <h4>On Leave Today</h4>
            <div class="stat-number"><?= $onLeaveToday ?></div>
            <div class="stat-change down">
                <i class='bx bx-sun'></i> Out of Office
            </div>
        </div>
        <div class="stat-icon red">
            <i class='bx bxs-plane-alt'></i>
        </div>
    </div>
</div>

<!-- Attendance Shift Pulse Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title">
            <i class='bx bx-pulse' style="color: var(--primary-600); margin-right: 6px;"></i>
            Today's Attendance Pulse &mdash; <?= date('d M Y') ?>
        </div>
        <a href="<?= APP_URL ?>/admin/attendance.php" class="btn btn-outline btn-sm">
            Manage Attendance <i class='bx bx-right-arrow-alt'></i>
        </a>
    </div>
    <div class="card-body">
        <!-- Progress Bar Breakdown -->
        <div style="height: 12px; border-radius: 6px; background: var(--bg-hover); overflow: hidden; display: flex; margin-bottom: 14px;">
            <?php 
                $onTimeCount = max(0, $presentToday - $lateToday);
                $pctOnTime = $totalEmployees > 0 ? round(($onTimeCount / $totalEmployees) * 100) : 0;
                $pctLate = $totalEmployees > 0 ? round(($lateToday / $totalEmployees) * 100) : 0;
                $pctHalf = $totalEmployees > 0 ? round(($halfDayToday / $totalEmployees) * 100) : 0;
                $pctLeave = $totalEmployees > 0 ? round(($onLeaveToday / $totalEmployees) * 100) : 0;
                $pctAbsent = max(0, 100 - ($pctOnTime + $pctLate + $pctHalf + $pctLeave));
            ?>
            <div style="width: <?= $pctOnTime ?>%; background: var(--success);" title="On Time: <?= $onTimeCount ?>"></div>
            <div style="width: <?= $pctLate ?>%; background: var(--warning);" title="Late: <?= $lateToday ?>"></div>
            <div style="width: <?= $pctHalf ?>%; background: var(--info);" title="Half-Day: <?= $halfDayToday ?>"></div>
            <div style="width: <?= $pctLeave ?>%; background: #a855f7;" title="On Leave: <?= $onLeaveToday ?>"></div>
            <div style="width: <?= $pctAbsent ?>%; background: var(--slate-300);" title="Not Checked In / Absent: <?= $absentToday ?>"></div>
        </div>

        <!-- Legend -->
        <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 0.85rem;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--success);"></span>
                <span>On-Time: <strong><?= $onTimeCount ?></strong></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--warning);"></span>
                <span>Late Arrival: <strong><?= $lateToday ?></strong></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--info);"></span>
                <span>Half-Day: <strong><?= $halfDayToday ?></strong></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #a855f7;"></span>
                <span>On Leave: <strong><?= $onLeaveToday ?></strong></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--slate-300);"></span>
                <span>Unrecorded / Absent: <strong><?= $absentToday ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Visual Analytics Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">
    <!-- 7-Day Attendance Trend -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class='bx bx-line-chart'></i> 7-Day Attendance Turnout Trend</div>
        </div>
        <div class="card-body" style="padding: 20px;">
            <canvas id="weeklyAttendanceChart" height="110"></canvas>
        </div>
    </div>

    <!-- Department Workforce Distribution -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class='bx bx-pie-chart-alt'></i> Department Workforce</div>
        </div>
        <div class="card-body" style="padding: 20px; display: flex; justify-content: center; align-items: center;">
            <div style="max-width: 260px; width: 100%;">
                <canvas id="deptDistributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Dual Column Layout -->
<div class="grid-2">
    <!-- Recent Leave Requests with Instant Action -->
    <div class="card">
        <div class="card-header">
            <h3><i class='bx bxs-calendar-minus' style="color:var(--warning); margin-right:8px;"></i>Recent Leave Requests</h3>
            <a href="<?= APP_URL ?>/admin/leaves.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recentLeaves)): ?>
            <div class="empty-state">
                <i class='bx bx-calendar-x'></i>
                <h3>No Leave Requests</h3>
                <p>There are no leave requests at the moment.</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLeaves as $leave): 
                            $initials = strtoupper(substr($leave['first_name'], 0, 1) . substr($leave['last_name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <div class="employee-info">
                                    <div class="employee-avatar">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div class="employee-name"><?= sanitize($leave['first_name'] . ' ' . $leave['last_name']) ?></div>
                                        <div class="employee-email"><?= sanitize($leave['emp_code']) ?> &bull; <?= sanitize($leave['department_name'] ?? 'General') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-info" style="text-transform: capitalize;"><?= sanitize($leave['leave_type']) ?></span></td>
                            <td>
                                <strong><?= $leave['total_days'] ?></strong> d
                                <small style="display: block; color: var(--text-muted); font-size: 0.75rem;"><?= date('M j', strtotime($leave['start_date'])) ?></small>
                            </td>
                            <td>
                                <?php
                                $statusClass = match($leave['status']) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'cancelled' => 'badge-secondary',
                                    default => 'badge-warning'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= ucfirst($leave['status']) ?></span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($leave['status'] === 'pending'): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="quick_leave_action" value="1">
                                    <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                    <input type="hidden" name="decision" value="approved">
                                    <button type="submit" class="btn btn-sm btn-success" title="Approve" style="padding: 4px 8px;">
                                        <i class='bx bx-check'></i>
                                    </button>
                                </form>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="quick_leave_action" value="1">
                                    <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                    <input type="hidden" name="decision" value="rejected">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Reject" style="padding: 4px 8px;">
                                        <i class='bx bx-x'></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <a href="<?= APP_URL ?>/admin/leaves.php" class="btn btn-sm btn-outline" style="padding: 4px 8px;">
                                    <i class='bx bx-show'></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Today's Attendance Stream -->
    <div class="card">
        <div class="card-header">
            <h3><i class='bx bxs-calendar-check' style="color:var(--success); margin-right:8px;"></i>Today's Attendance Stream</h3>
            <a href="<?= APP_URL ?>/admin/attendance.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recentAttendance)): ?>
            <div class="empty-state">
                <i class='bx bx-calendar'></i>
                <h3>No Check-ins Yet</h3>
                <p>No employees have clocked in for today yet.</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Check In</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAttendance as $att): 
                            $initials = strtoupper(substr($att['first_name'], 0, 1) . substr($att['last_name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <div class="employee-info">
                                    <div class="employee-avatar">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div class="employee-name"><?= sanitize($att['first_name'] . ' ' . $att['last_name']) ?></div>
                                        <div class="employee-email"><?= sanitize($att['emp_code']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($att['department_name'] ?? 'General') ?></td>
                            <td>
                                <strong style="font-family: monospace;">
                                    <?= $att['check_in'] ? date('h:i A', strtotime($att['check_in'])) : '—' ?>
                                </strong>
                            </td>
                            <td>
                                <?php
                                $attClass = match($att['status']) {
                                    'present' => 'badge-success',
                                    'late' => 'badge-warning',
                                    'absent' => 'badge-danger',
                                    'half-day' => 'badge-info',
                                    default => 'badge-secondary'
                                };
                                ?>
                                <span class="badge <?= $attClass ?>"><?= ucfirst($att['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Department Stats & Out of Office Today -->
<div class="grid-2 mt-3" style="margin-top: 24px;">
    <!-- Department Overview -->
    <div class="card">
        <div class="card-header">
            <h3><i class='bx bxs-buildings' style="color:var(--primary-500); margin-right:8px;"></i>Department Distribution</h3>
            <a href="<?= APP_URL ?>/admin/departments.php" class="btn btn-sm btn-outline">Manage</a>
        </div>
        <div class="card-body">
            <div class="dept-stats-grid">
                <?php foreach ($deptStats as $dept): 
                    $pct = $totalEmployees > 0 ? round(($dept['count'] / $totalEmployees) * 100) : 0;
                ?>
                <div class="dept-stat-item">
                    <div class="dept-stat-info">
                        <span class="dept-name"><?= sanitize($dept['name']) ?></span>
                        <span class="dept-count"><strong><?= $dept['count'] ?></strong> staff (<?= $pct ?>%)</span>
                    </div>
                    <div class="dept-stat-bar">
                        <div class="dept-stat-fill" style="width: <?= $pct ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Out of Office Today Widget -->
    <div class="card">
        <div class="card-header">
            <h3><i class='bx bx-user-x' style="color:var(--danger); margin-right:8px;"></i>Out of Office Today</h3>
            <span class="badge badge-secondary"><?= count($outOfOffice) ?> Staff</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($outOfOffice)): ?>
            <div class="empty-state" style="padding: 32px 16px;">
                <i class='bx bx-check-circle' style="color: var(--success); opacity: 0.8;"></i>
                <h3>Full Attendance Expected</h3>
                <p>No employees have approved leaves scheduled for today.</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>Returns</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outOfOffice as $ooo): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($ooo['first_name'] . ' ' . $ooo['last_name']) ?></strong>
                                <small style="display: block; color: var(--text-muted);"><?= sanitize($ooo['emp_code']) ?></small>
                            </td>
                            <td><?= sanitize($ooo['department_name'] ?? 'General') ?></td>
                            <td><span class="badge badge-info" style="text-transform: capitalize;"><?= sanitize($ooo['leave_type']) ?></span></td>
                            <td><?= date('d M Y', strtotime($ooo['end_date'] . ' +1 day')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .dept-stats-grid {
        display: grid;
        gap: 16px;
    }
    .dept-stat-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .dept-stat-bar {
        height: 8px;
        background: var(--bg-hover);
        border-radius: 4px;
        overflow: hidden;
    }
    .dept-stat-fill {
        height: 100%;
        background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
        border-radius: 4px;
        min-width: 4px;
        transition: width 0.6s ease-out;
    }
    .dept-stat-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
    }
    .dept-name {
        font-weight: 600;
        color: var(--text-primary);
    }
    .dept-count {
        color: var(--text-secondary);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Weekly Attendance Bar Chart
    const trendLabels = <?= json_encode(array_column($sevenDaysTrend, 'label')) ?>;
    const presentData = <?= json_encode(array_column($sevenDaysTrend, 'present')) ?>;
    const lateData = <?= json_encode(array_column($sevenDaysTrend, 'late')) ?>;
    const absentData = <?= json_encode(array_column($sevenDaysTrend, 'absent')) ?>;

    const ctxTrend = document.getElementById('weeklyAttendanceChart');
    if (ctxTrend) {
        new Chart(ctxTrend, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    { label: 'Present', data: presentData, backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Late', data: lateData, backgroundColor: '#f59e0b', borderRadius: 4 },
                    { label: 'Absent', data: absentData, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // 2. Department Doughnut Chart
    const deptLabels = <?= json_encode(array_column($deptStats, 'name')) ?>;
    const deptData = <?= json_encode(array_map('intval', array_column($deptStats, 'count'))) ?>;
    const ctxDept = document.getElementById('deptDistributionChart');
    if (ctxDept) {
        new Chart(ctxDept, {
            type: 'doughnut',
            data: {
                labels: deptLabels,
                datasets: [{
                    data: deptData,
                    backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
