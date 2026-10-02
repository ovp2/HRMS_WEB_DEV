<?php
/**
 * HRMS - Admin Reports & Visual Analytics
 * Comprehensive workforce reporting, interactive Chart.js visualizations & CSV exports
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Reports & Analytics');

$pdo = getDBConnection();

$reportType = $_GET['type'] ?? 'attendance';
$fromDate = !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$empFilter = !empty($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$reportYear = (int)(!empty($_GET['year']) ? $_GET['year'] : date('Y'));

$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$employees = $pdo->query("SELECT id, employee_id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

// ==========================================
// CSV EXPORT STREAM HANDLER
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($reportType === 'attendance') {
        $q = "SELECT a.*, e.employee_id AS emp_code, e.first_name, e.last_name, d.name AS department_name
              FROM attendance a
              JOIN employees e ON a.employee_id = e.id
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE a.date BETWEEN ? AND ?";
        $p = [$fromDate, $toDate];
        if ($deptFilter > 0) { $q .= " AND e.department_id = ?"; $p[] = $deptFilter; }
        if ($empFilter > 0) { $q .= " AND e.id = ?"; $p[] = $empFilter; }
        $q .= " ORDER BY a.date DESC, e.first_name ASC";
        $stmt = $pdo->prepare($q);
        $stmt->execute($p);
        $data = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hrms_attendance_report_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Day', 'Employee ID', 'Employee Name', 'Department', 'Status', 'Check In', 'Check Out', 'Work Hours', 'Notes']);
        foreach ($data as $r) {
            fputcsv($out, [
                $r['date'], date('l', strtotime($r['date'])), $r['emp_code'],
                $r['first_name'] . ' ' . $r['last_name'], $r['department_name'] ?? 'General',
                ucfirst($r['status']), $r['check_in'] ?? 'N/A', $r['check_out'] ?? 'N/A',
                $r['work_hours'] ?? '0.00', $r['notes'] ?? ''
            ]);
        }
        fclose($out);
        exit;
    }

    if ($reportType === 'leaves') {
        $q = "SELECT l.*, e.employee_id AS emp_code, e.first_name, e.last_name, d.name AS department_name
              FROM leaves l
              JOIN employees e ON l.employee_id = e.id
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE l.start_date >= ? AND l.end_date <= ?";
        $p = [$fromDate, $toDate];
        if ($deptFilter > 0) { $q .= " AND e.department_id = ?"; $p[] = $deptFilter; }
        $q .= " ORDER BY l.start_date DESC";
        $stmt = $pdo->prepare($q);
        $stmt->execute($p);
        $data = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hrms_leave_report_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Leave ID', 'Employee ID', 'Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Reason', 'Status', 'Remarks', 'Applied At']);
        foreach ($data as $r) {
            fputcsv($out, [
                $r['id'], $r['emp_code'], $r['first_name'] . ' ' . $r['last_name'],
                $r['department_name'] ?? 'General', ucfirst($r['leave_type']),
                $r['start_date'], $r['end_date'], $r['total_days'],
                $r['reason'], ucfirst($r['status']), $r['admin_remarks'] ?? '', $r['applied_at']
            ]);
        }
        fclose($out);
        exit;
    }

    if ($reportType === 'payroll') {
        $payStmt = $pdo->prepare("SELECT month, COUNT(id) AS total_employees, SUM(gross_salary) AS total_gross,
                                         SUM(total_deductions) AS total_deductions, SUM(net_salary) AS total_net,
                                         SUM(CASE WHEN payment_status = 'paid' THEN net_salary ELSE 0 END) AS disbursed
                                  FROM payroll WHERE year = ? GROUP BY month ORDER BY month ASC");
        $payStmt->execute([$reportYear]);
        $data = $payStmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hrms_payroll_summary_' . $reportYear . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Month', 'Year', 'Headcount', 'Gross Salary (INR)', 'Total Deductions (INR)', 'Net Payable (INR)', 'Total Disbursed (INR)']);
        foreach ($data as $r) {
            fputcsv($out, [
                date('F', mktime(0, 0, 0, (int)$r['month'], 10)), $reportYear,
                $r['total_employees'], $r['total_gross'], $r['total_deductions'],
                $r['total_net'], $r['disbursed']
            ]);
        }
        fclose($out);
        exit;
    }

    if ($reportType === 'workforce') {
        $deptStats = $pdo->query("SELECT d.name, COUNT(e.id) as emp_count, 
                                         COALESCE(AVG(e.salary), 0) as avg_salary,
                                         COALESCE(SUM(e.salary), 0) as total_salary
                                  FROM departments d
                                  LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
                                  GROUP BY d.id, d.name
                                  ORDER BY emp_count DESC")->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hrms_workforce_demographics_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Department Name', 'Active Headcount', 'Average Monthly Salary (INR)', 'Total Monthly Payroll Outflow (INR)']);
        foreach ($deptStats as $r) {
            fputcsv($out, [$r['name'], $r['emp_count'], round($r['avg_salary'], 2), round($r['total_salary'], 2)]);
        }
        fclose($out);
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Report Selection Navigation Tabs -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 12px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <a href="?type=attendance&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" 
               class="btn <?= $reportType === 'attendance' ? 'btn-primary' : 'btn-outline' ?>">
                <i class='bx bx-calendar-check'></i> Attendance Report
            </a>
            <a href="?type=leaves&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" 
               class="btn <?= $reportType === 'leaves' ? 'btn-primary' : 'btn-outline' ?>">
                <i class='bx bx-calendar-minus'></i> Leave Analytics
            </a>
            <a href="?type=payroll&year=<?= $reportYear ?>" 
               class="btn <?= $reportType === 'payroll' ? 'btn-primary' : 'btn-outline' ?>">
                <i class='bx bx-wallet'></i> Payroll & Expenses
            </a>
            <a href="?type=workforce" 
               class="btn <?= $reportType === 'workforce' ? 'btn-primary' : 'btn-outline' ?>">
                <i class='bx bx-group'></i> Workforce Demographics
            </a>
        </div>
    </div>
</div>

<?php if ($reportType === 'attendance'): 
    // Attendance Report Query
    $query = "SELECT a.*, e.employee_id AS emp_code, e.first_name, e.last_name, d.name AS department_name
              FROM attendance a
              JOIN employees e ON a.employee_id = e.id
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE a.date BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];

    if ($deptFilter > 0) {
        $query .= " AND e.department_id = ?";
        $params[] = $deptFilter;
    }
    if ($empFilter > 0) {
        $query .= " AND e.id = ?";
        $params[] = $empFilter;
    }

    $query .= " ORDER BY a.date DESC, e.first_name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attData = $stmt->fetchAll();

    // Summary counts
    $presentCount = 0;
    $lateCount = 0;
    $halfDayCount = 0;
    $absentCount = 0;
    $onLeaveCount = 0;
    $totalHours = 0;

    foreach ($attData as $row) {
        if ($row['status'] === 'present') $presentCount++;
        elseif ($row['status'] === 'late') $lateCount++;
        elseif ($row['status'] === 'half-day') $halfDayCount++;
        elseif ($row['status'] === 'absent') $absentCount++;
        elseif ($row['status'] === 'on-leave') $onLeaveCount++;
        $totalHours += (float)($row['work_hours'] ?? 0);
    }
?>
    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 16px 20px;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                <input type="hidden" name="type" value="attendance">
                
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">From:</label>
                    <input type="date" name="from_date" class="form-control" style="width: 150px;" value="<?= sanitize($fromDate) ?>">

                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">To:</label>
                    <input type="date" name="to_date" class="form-control" style="width: 150px;" value="<?= sanitize($toDate) ?>">

                    <select name="department_id" class="form-control" style="width: 160px;">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= sanitize($d['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="employee_id" class="form-control" style="width: 180px;">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $empFilter === (int)$e['id'] ? 'selected' : '' ?>>
                            <?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?> (<?= $e['employee_id'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary"><i class='bx bx-filter'></i> Generate</button>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="?type=attendance&export=csv&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?><?= $deptFilter ? '&department_id=' . $deptFilter : '' ?><?= $empFilter ? '&employee_id=' . $empFilter : '' ?>" class="btn btn-outline" title="Export report to CSV">
                        <i class='bx bx-export'></i> Export CSV
                    </a>
                    <button type="button" class="btn btn-outline" onclick="window.print()">
                        <i class='bx bx-printer'></i> Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Stats -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card blue">
            <div class="stat-info">
                <h4>Total Logs</h4>
                <div class="stat-number"><?= count($attData) ?></div>
                <div class="stat-change up"><i class='bx bx-calendar'></i> Logged records</div>
            </div>
            <div class="stat-icon blue"><i class='bx bxs-calendar'></i></div>
        </div>
        <div class="stat-card green">
            <div class="stat-info">
                <h4>Present / On-Time</h4>
                <div class="stat-number"><?= $presentCount ?></div>
                <div class="stat-change up"><i class='bx bx-check'></i> On-time punches</div>
            </div>
            <div class="stat-icon green"><i class='bx bxs-user-check'></i></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-info">
                <h4>Late Occurrences</h4>
                <div class="stat-number"><?= $lateCount ?></div>
                <div class="stat-change down"><i class='bx bx-time'></i> After 9:30 AM</div>
            </div>
            <div class="stat-icon orange"><i class='bx bxs-time-five'></i></div>
        </div>
        <div class="stat-card red">
            <div class="stat-info">
                <h4>Total Hours</h4>
                <div class="stat-number"><?= number_format($totalHours, 1) ?></div>
                <div class="stat-change up"><i class='bx bx-timer'></i> Productive hours</div>
            </div>
            <div class="stat-icon red"><i class='bx bxs-hourglass-top'></i></div>
        </div>
    </div>

    <!-- Interactive Visual Chart -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <div class="card-title"><i class='bx bx-pie-chart-alt-2'></i> Attendance Distribution Breakdown</div>
        </div>
        <div class="card-body" style="display: flex; justify-content: center; align-items: center; padding: 24px; min-height: 280px;">
            <div style="max-width: 400px; width: 100%;">
                <canvas id="attChart" height="240"></canvas>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Attendance Detailed Register (<?= date('M j, Y', strtotime($fromDate)) ?> to <?= date('M j, Y', strtotime($toDate)) ?>)</div>
            <span class="badge badge-secondary"><?= count($attData) ?> Records</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Work Hours</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attData)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">No records found for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attData as $row): ?>
                        <tr>
                            <td><strong><?= date('d M Y', strtotime($row['date'])) ?></strong></td>
                            <td><?= sanitize($row['first_name'] . ' ' . $row['last_name']) ?> <small style="color:var(--text-muted);">(<?= sanitize($row['emp_code']) ?>)</small></td>
                            <td><?= sanitize($row['department_name'] ?? 'General') ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status'] === 'present' ? 'success' : ($row['status'] === 'late' ? 'warning' : ($row['status'] === 'on-leave' ? 'secondary' : 'danger')) ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td><?= $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '--:--' ?></td>
                            <td><?= $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '--:--' ?></td>
                            <td><?= $row['work_hours'] ? number_format($row['work_hours'], 1) . ' hrs' : '-' ?></td>
                            <td style="font-size: 0.85rem; color: var(--text-secondary);"><?= sanitize($row['notes'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('attChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Late', 'Half-Day', 'Absent', 'On Leave'],
                    datasets: [{
                        data: [<?= $presentCount ?>, <?= $lateCount ?>, <?= $halfDayCount ?>, <?= $absentCount ?>, <?= $onLeaveCount ?>],
                        backgroundColor: ['#16a34a', '#d97706', '#0284c7', '#dc2626', '#64748b'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    });
    </script>

<?php elseif ($reportType === 'leaves'): 
    // Leave Analytics
    $query = "SELECT l.*, e.employee_id AS emp_code, e.first_name, e.last_name, d.name AS department_name
              FROM leaves l
              JOIN employees e ON l.employee_id = e.id
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE l.start_date >= ? AND l.end_date <= ?";
    $params = [$fromDate, $toDate];

    if ($deptFilter > 0) {
        $query .= " AND e.department_id = ?";
        $params[] = $deptFilter;
    }

    $query .= " ORDER BY l.start_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leaveData = $stmt->fetchAll();

    // Type aggregates
    $byType = ['casual' => 0, 'sick' => 0, 'earned' => 0, 'unpaid' => 0];
    $byStatus = ['approved' => 0, 'pending' => 0, 'rejected' => 0];

    foreach ($leaveData as $l) {
        $t = $l['leave_type'];
        if (!isset($byType[$t])) $byType[$t] = 0;
        $byType[$t] += (int)$l['total_days'];

        $s = $l['status'];
        if (isset($byStatus[$s])) $byStatus[$s]++;
    }
?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 16px 20px;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                <input type="hidden" name="type" value="leaves">
                
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">From:</label>
                    <input type="date" name="from_date" class="form-control" style="width: 150px;" value="<?= sanitize($fromDate) ?>">

                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">To:</label>
                    <input type="date" name="to_date" class="form-control" style="width: 150px;" value="<?= sanitize($toDate) ?>">

                    <select name="department_id" class="form-control" style="width: 180px;">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= sanitize($d['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary"><i class='bx bx-filter'></i> Generate</button>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="?type=leaves&export=csv&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?><?= $deptFilter ? '&department_id=' . $deptFilter : '' ?>" class="btn btn-outline" title="Export leaves to CSV">
                        <i class='bx bx-export'></i> Export CSV
                    </a>
                    <button type="button" class="btn btn-outline" onclick="window.print()">
                        <i class='bx bx-printer'></i> Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php foreach (['sick' => 'Sick', 'casual' => 'Casual', 'earned' => 'Earned', 'unpaid' => 'Unpaid'] as $key => $lbl): ?>
        <div class="card" style="padding: 16px; border-left: 3px solid var(--primary-500);">
            <div style="font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;"><?= $lbl ?> Leaves</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary-600); margin-top: 4px;">
                <?= $byType[$key] ?? 0 ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">days</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class='bx bx-pie-chart'></i> Leave Category Usage</div>
            </div>
            <div class="card-body" style="padding: 20px; min-height: 250px; display: flex; justify-content: center; align-items: center;">
                <div style="max-width: 320px; width: 100%;">
                    <canvas id="leaveTypeChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class='bx bx-check-shield'></i> Application Decisions</div>
            </div>
            <div class="card-body" style="padding: 20px; min-height: 250px; display: flex; justify-content: center; align-items: center;">
                <div style="max-width: 320px; width: 100%;">
                    <canvas id="leaveStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Leave History Report (<?= count($leaveData) ?> Records)</div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaveData)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leaveData as $l): ?>
                        <tr>
                            <td><?= sanitize($l['first_name'] . ' ' . $l['last_name']) ?> <small style="color:var(--text-muted);">(<?= sanitize($l['emp_code']) ?>)</small></td>
                            <td><?= sanitize($l['department_name'] ?? 'General') ?></td>
                            <td><span class="badge badge-info" style="text-transform: capitalize;"><?= sanitize($l['leave_type']) ?></span></td>
                            <td><?= date('d M Y', strtotime($l['start_date'])) ?> &mdash; <?= date('d M Y', strtotime($l['end_date'])) ?></td>
                            <td><strong><?= $l['total_days'] ?></strong></td>
                            <td style="max-width: 200px; font-size: 0.85rem;"><?= sanitize($l['reason']) ?></td>
                            <td>
                                <span class="badge badge-<?= $l['status'] === 'approved' ? 'success' : ($l['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($l['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('leaveTypeChart'), {
            type: 'doughnut',
            data: {
                labels: ['Casual', 'Sick', 'Earned', 'Unpaid'],
                datasets: [{
                    data: [<?= $byType['casual'] ?? 0 ?>, <?= $byType['sick'] ?? 0 ?>, <?= $byType['earned'] ?? 0 ?>, <?= $byType['unpaid'] ?? 0 ?>],
                    backgroundColor: ['#3b82f6', '#ef4444', '#10b981', '#f59e0b']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('leaveStatusChart'), {
            type: 'pie',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?= $byStatus['approved'] ?>, <?= $byStatus['pending'] ?>, <?= $byStatus['rejected'] ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    });
    </script>

<?php elseif ($reportType === 'payroll'): 
    // Payroll & Expenditure Analytics
    $payStmt = $pdo->prepare("SELECT 
        month,
        COUNT(id) AS total_employees,
        SUM(gross_salary) AS total_gross,
        SUM(total_deductions) AS total_deductions,
        SUM(net_salary) AS total_net,
        SUM(CASE WHEN payment_status = 'paid' THEN net_salary ELSE 0 END) AS disbursed
    FROM payroll 
    WHERE year = ? 
    GROUP BY month 
    ORDER BY month ASC");
    $payStmt->execute([$reportYear]);
    $monthlyPayroll = $payStmt->fetchAll();

    $grandGross = 0;
    $grandNet = 0;
    $grandDeductions = 0;
    $monthsMap = [];
    for ($m = 1; $m <= 12; $m++) {
        $monthsMap[$m] = ['gross' => 0, 'deductions' => 0, 'net' => 0];
    }

    foreach ($monthlyPayroll as $mp) {
        $grandGross += (float)$mp['total_gross'];
        $grandNet += (float)$mp['total_net'];
        $grandDeductions += (float)$mp['total_deductions'];
        $monthsMap[(int)$mp['month']] = [
            'gross' => (float)$mp['total_gross'],
            'deductions' => (float)$mp['total_deductions'],
            'net' => (float)$mp['total_net']
        ];
    }
?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 16px 20px;">
            <form method="GET" action="" style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <input type="hidden" name="type" value="payroll">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="font-weight: 600;">Financial Year:</label>
                    <select name="year" class="form-control" style="width: 120px;" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $reportYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="?type=payroll&export=csv&year=<?= $reportYear ?>" class="btn btn-outline" title="Export annual payroll summary">
                        <i class='bx bx-export'></i> Export CSV
                    </a>
                    <button type="button" class="btn btn-outline" onclick="window.print()">
                        <i class='bx bx-printer'></i> Print Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card blue">
            <div class="stat-info">
                <h4>Annual Gross Payroll</h4>
                <div class="stat-number">₹<?= number_format($grandGross, 2) ?></div>
                <div class="stat-change up"><i class='bx bx-wallet'></i> Total Budget</div>
            </div>
            <div class="stat-icon blue"><i class='bx bxs-wallet'></i></div>
        </div>
        <div class="stat-card green">
            <div class="stat-info">
                <h4>Net Disbursed</h4>
                <div class="stat-number">₹<?= number_format($grandNet, 2) ?></div>
                <div class="stat-change up"><i class='bx bx-badge-check'></i> Paid/Payable</div>
            </div>
            <div class="stat-icon green"><i class='bx bxs-badge-check'></i></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-info">
                <h4>Statutory Deductions</h4>
                <div class="stat-number">₹<?= number_format($grandDeductions, 2) ?></div>
                <div class="stat-change down"><i class='bx bx-minus'></i> PF & Taxes</div>
            </div>
            <div class="stat-icon orange"><i class='bx bxs-pie-chart-alt-2'></i></div>
        </div>
    </div>

    <!-- Monthly Outflow Bar Chart -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <div class="card-title"><i class='bx bx-bar-chart'></i> Monthly Salary Outflow Trend (<?= $reportYear ?>)</div>
        </div>
        <div class="card-body" style="padding: 20px;">
            <canvas id="payrollTrendChart" height="90"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Monthly Payroll Financial Summary &mdash; <?= $reportYear ?></div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Employees</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Disbursed to Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monthlyPayroll)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No payroll data for <?= $reportYear ?>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($monthlyPayroll as $mp): ?>
                        <tr>
                            <td><strong><?= date('F', mktime(0, 0, 0, (int)$mp['month'], 10)) ?></strong></td>
                            <td><?= $mp['total_employees'] ?> Employees</td>
                            <td>₹<?= number_format($mp['total_gross'], 2) ?></td>
                            <td style="color: var(--danger);">₹<?= number_format($mp['total_deductions'], 2) ?></td>
                            <td><strong>₹<?= number_format($mp['total_net'], 2) ?></strong></td>
                            <td style="color: var(--success); font-weight: 600;">₹<?= number_format($mp['disbursed'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const grossVals = <?= json_encode(array_column($monthsMap, 'gross')) ?>;
        const netVals = <?= json_encode(array_column($monthsMap, 'net')) ?>;

        new Chart(document.getElementById('payrollTrendChart'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Gross Allocated (₹)',
                        data: grossVals,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    },
                    {
                        label: 'Net Take-Home (₹)',
                        data: netVals,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return '₹' + v.toLocaleString(); }
                        }
                    }
                }
            }
        });
    });
    </script>

<?php elseif ($reportType === 'workforce'): 
    // Workforce Demographics
    $deptStats = $pdo->query("SELECT d.name, COUNT(e.id) as emp_count, 
                                     COALESCE(AVG(e.salary), 0) as avg_salary,
                                     COALESCE(SUM(e.salary), 0) as total_salary
                              FROM departments d
                              LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
                              GROUP BY d.id, d.name
                              ORDER BY emp_count DESC")->fetchAll();

    $typeStats = $pdo->query("SELECT employment_type, COUNT(*) as count 
                              FROM employees 
                              WHERE status = 'active' 
                              GROUP BY employment_type")->fetchAll();
?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">
                Workforce Distribution & Cost Analytics
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="?type=workforce&export=csv" class="btn btn-outline" title="Export demographics to CSV">
                    <i class='bx bx-export'></i> Export CSV
                </a>
                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class='bx bx-printer'></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class='bx bx-pie-chart-alt'></i> Department Headcount Share</div>
            </div>
            <div class="card-body" style="padding: 20px; min-height: 250px; display: flex; justify-content: center; align-items: center;">
                <div style="max-width: 320px; width: 100%;">
                    <canvas id="deptHeadcountChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class='bx bx-group'></i> Employment Type Ratio</div>
            </div>
            <div class="card-body" style="padding: 20px; min-height: 250px; display: flex; justify-content: center; align-items: center;">
                <div style="max-width: 320px; width: 100%;">
                    <canvas id="empTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Department Workforce & Cost Distribution</div>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Active Headcount</th>
                            <th>Avg. Salary</th>
                            <th>Total Monthly Payroll</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptStats as $ds): ?>
                        <tr>
                            <td><strong><?= sanitize($ds['name']) ?></strong></td>
                            <td><span class="badge badge-primary"><?= $ds['emp_count'] ?></span></td>
                            <td>₹<?= number_format($ds['avg_salary'], 2) ?></td>
                            <td><strong>₹<?= number_format($ds['total_salary'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Employment Types</div>
            </div>
            <div class="card-body">
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($typeStats as $ts): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: var(--bg-hover); border-radius: var(--border-radius);">
                        <span style="font-weight: 600; text-transform: capitalize;"><?= sanitize($ts['employment_type']) ?></span>
                        <span class="badge badge-info" style="font-size: 0.9rem;"><?= $ts['count'] ?> Staff</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deptLabels = <?= json_encode(array_column($deptStats, 'name')) ?>;
        const deptCounts = <?= json_encode(array_map('intval', array_column($deptStats, 'emp_count'))) ?>;

        new Chart(document.getElementById('deptHeadcountChart'), {
            type: 'doughnut',
            data: {
                labels: deptLabels,
                datasets: [{
                    data: deptCounts,
                    backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        const typeLabels = <?= json_encode(array_column($typeStats, 'employment_type')) ?>;
        const typeCounts = <?= json_encode(array_map('intval', array_column($typeStats, 'count'))) ?>;

        new Chart(document.getElementById('empTypeChart'), {
            type: 'pie',
            data: {
                labels: typeLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
                datasets: [{
                    data: typeCounts,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#6366f1']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
