<?php
/**
 * HRMS - Admin Attendance Management
 * Phase 4: Daily Shift Roster, Punch Modification, Batch Status Marking, CSV Export, and MySQL Integration
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Attendance Management');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();

// Selected date and filters
$selectedDate = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');

// Handle Attendance Save / Update / Bulk Mark
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/attendance.php?date=' . urlencode($selectedDate), 'Invalid security token.', 'danger');
    }

    if ($action === 'save_single') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $date = $_POST['date'] ?? $selectedDate;
        $status = in_array($_POST['status'] ?? '', ['present', 'absent', 'half-day', 'late', 'on-leave']) ? $_POST['status'] : 'present';
        $check_in = !empty($_POST['check_in']) ? $_POST['check_in'] : null;
        $check_out = !empty($_POST['check_out']) ? $_POST['check_out'] : null;
        $work_hours = !empty($_POST['work_hours']) ? (float)$_POST['work_hours'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if ($employee_id <= 0) {
            redirectWith(APP_URL . '/admin/attendance.php?date=' . urlencode($date), 'Please select an employee.', 'danger');
        }

        // Validate employee exists in database
        $empCheck = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
        $empCheck->execute([$employee_id]);
        if (!$empCheck->fetch()) {
            redirectWith(APP_URL . '/admin/attendance.php?date=' . urlencode($date), 'Cannot record attendance for a nonexistent employee.', 'danger');
        }

        // Auto calculate work hours if check_in and check_out are given and work_hours is empty
        if ($check_in && $check_out && empty($work_hours)) {
            $t1 = strtotime("$date $check_in");
            $t2 = strtotime("$date $check_out");
            if ($t2 > $t1) {
                $work_hours = round(($t2 - $t1) / 3600, 2);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, check_in, check_out, work_hours, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               status = VALUES(status), 
                               check_in = VALUES(check_in), 
                               check_out = VALUES(check_out), 
                               work_hours = VALUES(work_hours), 
                               notes = VALUES(notes)");
        $stmt->execute([$employee_id, $date, $status, $check_in, $check_out, $work_hours, $notes]);

        redirectWith(APP_URL . '/admin/attendance.php?date=' . urlencode($date), 'Attendance record updated successfully!', 'success');
    }

    if ($action === 'mark_all_present') {
        $date = $_POST['date'] ?? $selectedDate;
        $defaultIn = '09:00:00';
        $defaultOut = '18:00:00';
        $hours = 9.00;

        // Get all active employees without attendance for this date
        $missingStmt = $pdo->prepare("SELECT e.id FROM employees e 
                                      WHERE e.status = 'active' 
                                      AND e.id NOT IN (SELECT employee_id FROM attendance WHERE date = ?)");
        $missingStmt->execute([$date]);
        $missingEmps = $missingStmt->fetchAll(PDO::FETCH_COLUMN);

        $inserted = 0;
        if (!empty($missingEmps)) {
            $insertStmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, check_in, check_out, work_hours, notes) 
                                         VALUES (?, ?, 'present', ?, ?, ?, 'Batch marked by HR')");
            foreach ($missingEmps as $empId) {
                $insertStmt->execute([$empId, $date, $defaultIn, $defaultOut, $hours]);
                $inserted++;
            }
        }

        redirectWith(APP_URL . '/admin/attendance.php?date=' . urlencode($date), "Marked $inserted employee(s) as present.", 'success');
    }
}

// Fetch all active employees and their attendance record for selected date
$query = "SELECT e.id, e.employee_id AS emp_code, e.first_name, e.last_name, e.designation,
                 d.name AS department_name,
                 a.id AS att_id, a.status AS att_status, a.check_in, a.check_out, a.work_hours, a.notes
          FROM employees e
          LEFT JOIN departments d ON e.department_id = d.id
          LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
          WHERE e.status = 'active'";
$params = [$selectedDate];

if ($deptFilter > 0) {
    $query .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}

if ($statusFilter !== '') {
    if ($statusFilter === 'unrecorded') {
        $query .= " AND a.status IS NULL";
    } else {
        $query .= " AND a.status = ?";
        $params[] = $statusFilter;
    }
}

$query .= " ORDER BY d.name ASC, e.first_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceList = $stmt->fetchAll();

// CSV Export for Attendance
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_' . $selectedDate . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Employee Code', 'Employee Name', 'Department', 'Designation', 'Status', 'Check In', 'Check Out', 'Hours', 'Notes']);
    foreach ($attendanceList as $row) {
        fputcsv($output, [
            $selectedDate,
            $row['emp_code'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['department_name'] ?? 'General',
            $row['designation'],
            $row['att_status'] ?? 'Not Marked',
            $row['check_in'] ?? '--',
            $row['check_out'] ?? '--',
            $row['work_hours'] ?? '0',
            $row['notes'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Calculate Summary Statistics for this date across all active employees
$statsStmt = $pdo->prepare("SELECT 
    COUNT(e.id) AS total_active,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
    SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) AS half_day_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
    SUM(CASE WHEN a.status = 'on-leave' THEN 1 ELSE 0 END) AS on_leave_count,
    SUM(CASE WHEN a.status IS NULL THEN 1 ELSE 0 END) AS unrecorded_count
FROM employees e
LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
WHERE e.status = 'active'");
$statsStmt->execute([$selectedDate]);
$stats = $statsStmt->fetch();

$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$allEmployees = $pdo->query("SELECT e.id, e.employee_id, e.first_name, e.last_name, d.name AS department_name 
                             FROM employees e 
                             LEFT JOIN departments d ON e.department_id = d.id 
                             WHERE e.status = 'active' 
                             ORDER BY e.first_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Date & Filter Banner -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; flex: 1;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.9rem;">Date:</label>
                    <input type="date" name="date" class="form-control" style="width: 160px;" value="<?= sanitize($selectedDate) ?>" onchange="this.form.submit()">
                </div>

                <div style="display: flex; align-items: center; gap: 6px;">
                    <select name="department_id" class="form-control" style="width: 170px;" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= sanitize($d['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 6px;">
                    <select name="status" class="form-control" style="width: 140px;" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="half-day" <?= $statusFilter === 'half-day' ? 'selected' : '' ?>>Half Day</option>
                        <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="on-leave" <?= $statusFilter === 'on-leave' ? 'selected' : '' ?>>On Leave</option>
                        <option value="unrecorded" <?= $statusFilter === 'unrecorded' ? 'selected' : '' ?>>Not Logged</option>
                    </select>
                </div>

                <?php if ($deptFilter > 0 || $statusFilter !== '' || $selectedDate !== date('Y-m-d')): ?>
                <a href="<?= APP_URL ?>/admin/attendance.php" class="btn btn-outline" title="Reset Filters">
                    <i class='bx bx-reset'></i> Today
                </a>
                <?php endif; ?>
            </form>

            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn btn-primary" onclick="openNewAttendanceModal()">
                    <i class='bx bx-calendar-plus'></i> Mark Attendance
                </button>

                <a href="?export=csv&date=<?= urlencode($selectedDate) ?><?= $deptFilter ? '&department_id=' . $deptFilter : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-outline" title="Download daily attendance CSV">
                    <i class='bx bx-download'></i> Export CSV
                </a>

                <form method="POST" action="" onsubmit="return confirm('Mark all unrecorded active employees as Present with standard 9:00 AM - 6:00 PM hours?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="mark_all_present">
                    <input type="hidden" name="date" value="<?= sanitize($selectedDate) ?>">
                    <button type="submit" class="btn btn-success" <?= ($stats['unrecorded_count'] ?? 0) == 0 ? 'disabled' : '' ?>>
                        <i class='bx bx-check-double'></i> Mark Unrecorded Present
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Daily Summary Metrics -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Present / On-Time</h4>
            <div class="stat-number"><?= (int)($stats['present_count'] ?? 0) ?></div>
            <div class="stat-change up"><i class='bx bx-check'></i> On Duty</div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-user-check'></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Late Arrival</h4>
            <div class="stat-number"><?= (int)($stats['late_count'] ?? 0) ?></div>
            <div class="stat-change down"><i class='bx bx-time'></i> Past 9:30 AM</div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-time-five'></i></div>
    </div>
    <div class="stat-card red">
        <div class="stat-info">
            <h4>Absent / Leave</h4>
            <div class="stat-number"><?= (int)($stats['absent_count'] ?? 0) + (int)($stats['on_leave_count'] ?? 0) ?></div>
            <div class="stat-change down"><i class='bx bx-calendar-x'></i> Out of Office</div>
        </div>
        <div class="stat-icon red"><i class='bx bxs-calendar-x'></i></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Unrecorded</h4>
            <div class="stat-number"><?= (int)($stats['unrecorded_count'] ?? 0) ?></div>
            <div class="stat-change up"><i class='bx bx-help-circle'></i> Pending Punch</div>
        </div>
        <div class="stat-icon blue"><i class='bx bx-notepad'></i></div>
    </div>
</div>

<!-- Attendance Roster -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            Attendance Roster &mdash; <?= date('l, F j, Y', strtotime($selectedDate)) ?>
        </div>
        <span class="badge badge-secondary"><?= count($attendanceList) ?> Staff Listed</span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Work Hours</th>
                    <th>Notes</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendanceList)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <i class='bx bx-calendar-event' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        No employees found matching filter criteria.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($attendanceList as $row): 
                        $fullName = $row['first_name'] . ' ' . $row['last_name'];
                        $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                        $status = $row['att_status'];
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar-sm" style="width: 32px; height: 32px; font-size: 0.8rem; font-weight: 700;">
                                    <?= $initials ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);"><?= sanitize($fullName) ?></div>
                                    <small style="color: var(--text-muted); font-family: monospace;"><?= sanitize($row['emp_code']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= sanitize($row['department_name'] ?? 'General') ?></span>
                        </td>
                        <td>
                            <?php if ($status === 'present'): ?>
                            <span class="badge badge-success"><i class='bx bx-check'></i> Present</span>
                            <?php elseif ($status === 'late'): ?>
                            <span class="badge badge-warning"><i class='bx bx-time'></i> Late</span>
                            <?php elseif ($status === 'half-day'): ?>
                            <span class="badge badge-info"><i class='bx bx-adjust'></i> Half-Day</span>
                            <?php elseif ($status === 'absent'): ?>
                            <span class="badge badge-danger"><i class='bx bx-x'></i> Absent</span>
                            <?php elseif ($status === 'on-leave'): ?>
                            <span class="badge badge-secondary"><i class='bx bx-calendar'></i> On Leave</span>
                            <?php else: ?>
                            <span class="badge" style="background: var(--slate-200); color: var(--slate-700);">Not Marked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 500; font-family: monospace;">
                                <?= $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '--:--' ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 500; font-family: monospace;">
                                <?= $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '--:--' ?>
                            </span>
                        </td>
                        <td>
                            <?= $row['work_hours'] !== null ? '<strong>' . number_format($row['work_hours'], 1) . '</strong> hrs' : '-' ?>
                        </td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-secondary); font-size: 0.85rem;">
                            <?= sanitize($row['notes'] ?? '-') ?>
                        </td>
                        <td style="text-align: right;">
                            <button type="button" class="btn btn-outline btn-sm" onclick='openAttendanceModal(<?= json_encode($row) ?>)' title="Record or Update Attendance">
                                <i class='bx bx-edit-alt'></i> Edit
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit / Mark Attendance Modal -->
<div class="modal-overlay" id="attModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="attModalTitle">Record Attendance</h3>
            <button class="modal-close" onclick="closeModal('attModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="save_single">
                <input type="hidden" name="date" value="<?= sanitize($selectedDate) ?>">

                <div class="form-group" id="modalEmpSelectGroup">
                    <label class="form-label">Employee <span class="required">*</span></label>
                    <select name="employee_id" id="modalEmpSelect" class="form-control" required>
                        <option value="">-- Select Active Employee --</option>
                        <?php foreach ($allEmployees as $ae): ?>
                        <option value="<?= $ae['id'] ?>">
                            <?= sanitize($ae['employee_id']) ?> - <?= sanitize($ae['first_name'] . ' ' . $ae['last_name']) ?> (<?= sanitize($ae['department_name'] ?: 'Staff') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Status <span class="required">*</span></label>
                    <select name="status" id="modalStatus" class="form-control" required onchange="handleStatusChange(this.value)">
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="half-day">Half Day</option>
                        <option value="absent">Absent</option>
                        <option value="on-leave">On Leave</option>
                    </select>
                </div>

                <div id="timeFields">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Check In</label>
                            <input type="time" name="check_in" id="modalCheckIn" class="form-control" value="09:00">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Check Out</label>
                            <input type="time" name="check_out" id="modalCheckOut" class="form-control" value="18:00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Total Work Hours (Optional)</label>
                        <input type="number" step="0.1" name="work_hours" id="modalWorkHours" class="form-control" placeholder="Auto-calculated if blank">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes / Remarks</label>
                    <textarea name="notes" id="modalNotes" class="form-control" rows="2" placeholder="e.g. Approved remote work, doctor appointment, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('attModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-check'></i> Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAttendanceModal(data) {
    document.getElementById('attModalTitle').textContent = 'Update Attendance';
    document.getElementById('modalEmpSelect').value = data.id;
    
    const statusSelect = document.getElementById('modalStatus');
    statusSelect.value = data.att_status || 'present';
    
    document.getElementById('modalCheckIn').value = data.check_in ? data.check_in.substring(0, 5) : '09:00';
    document.getElementById('modalCheckOut').value = data.check_out ? data.check_out.substring(0, 5) : '18:00';
    document.getElementById('modalWorkHours').value = data.work_hours || '';
    document.getElementById('modalNotes').value = data.notes || '';
    
    handleStatusChange(statusSelect.value);
    openModal('attModal');
}

function openNewAttendanceModal() {
    document.getElementById('attModalTitle').textContent = 'Mark Employee Attendance';
    document.getElementById('modalEmpSelect').value = '';
    
    const statusSelect = document.getElementById('modalStatus');
    statusSelect.value = 'present';
    
    document.getElementById('modalCheckIn').value = '09:00';
    document.getElementById('modalCheckOut').value = '18:00';
    document.getElementById('modalWorkHours').value = '9';
    document.getElementById('modalNotes').value = '';
    
    handleStatusChange('present');
    openModal('attModal');
}

function handleStatusChange(status) {
    const timeFields = document.getElementById('timeFields');
    if (status === 'absent' || status === 'on-leave') {
        timeFields.style.opacity = '0.5';
        document.getElementById('modalCheckIn').value = '';
        document.getElementById('modalCheckOut').value = '';
        document.getElementById('modalWorkHours').value = '0';
    } else {
        timeFields.style.opacity = '1';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
