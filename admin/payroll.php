<?php
/**
 * HRMS - Admin Payroll Management
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Payroll Management');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();

$currentMonth = (int)(!empty($_GET['month']) ? $_GET['month'] : date('n'));
$currentYear = (int)(!empty($_GET['year']) ? $_GET['year'] : date('Y'));
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');

// Handle Actions (Generate, Update Status, Edit Structure, Mark All Paid, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . "/admin/payroll.php?month=$currentMonth&year=$currentYear", 'Invalid security token.', 'danger');
    }

    if ($action === 'create_single') {
        $empId = (int)($_POST['employee_id'] ?? 0);
        $m = (int)($_POST['month'] ?? $currentMonth);
        $y = (int)($_POST['year'] ?? $currentYear);
        $basic = max(0, (float)($_POST['basic_salary'] ?? 0));
        $allowances = max(0, (float)($_POST['allowances'] ?? 0));
        $deductions = max(0, (float)($_POST['deductions'] ?? 0));
        $paymentStatus = in_array($_POST['payment_status'] ?? '', ['pending', 'paid', 'hold']) ? $_POST['payment_status'] : 'pending';
        $paymentMethod = in_array($_POST['payment_method'] ?? '', ['bank_transfer', 'cheque', 'cash']) ? $_POST['payment_method'] : 'bank_transfer';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($empId <= 0) {
            redirectWith(APP_URL . "/admin/payroll.php?month=$m&year=$y", 'Please select an employee.', 'danger');
        }

        $eStmt = $pdo->prepare("SELECT id, user_id, first_name, last_name, salary FROM employees WHERE id = ?");
        $eStmt->execute([$empId]);
        $empRec = $eStmt->fetch();
        if (!$empRec) {
            redirectWith(APP_URL . "/admin/payroll.php?month=$m&year=$y", 'Selected employee was not found.', 'danger');
        }

        // Calculate: Net Salary = Basic Salary + Allowances - Deductions
        $gross = round($basic + $allowances, 2);
        $net = round(max(0, $basic + $allowances - $deductions), 2);

        // Check if payroll record already exists for this employee and period
        $chk = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND month = ? AND year = ?");
        $chk->execute([$empId, $m, $y]);
        $existingId = $chk->fetchColumn();

        if ($existingId) {
            $stmt = $pdo->prepare("UPDATE payroll SET 
                basic_salary = ?, other_allowances = ?, gross_salary = ?, 
                other_deductions = ?, total_deductions = ?, net_salary = ?, 
                payment_status = ?, payment_method = ?, remarks = ? 
                WHERE id = ?");
            $stmt->execute([$basic, $allowances, $gross, $deductions, $deductions, $net, $paymentStatus, $paymentMethod, $remarks, $existingId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO payroll (
                employee_id, month, year, basic_salary, other_allowances, gross_salary,
                other_deductions, total_deductions, net_salary, payment_status, payment_method,
                remarks, generated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $m, $y, $basic, $allowances, $gross, $deductions, $deductions, $net, $paymentStatus, $paymentMethod, $remarks, getCurrentUserId()]);
        }

        if (!empty($empRec['user_id'])) {
            $monthName = date('F', mktime(0, 0, 0, $m, 10, $y));
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', ?)");
            $notif->execute([
                $empRec['user_id'],
                "Payroll Slip {$monthName} {$y}",
                "Salary slip of ₹" . number_format($net, 2) . " has been prepared for {$monthName} {$y}.",
                APP_URL . '/employee/payroll.php'
            ]);
        }

        redirectWith(APP_URL . "/admin/payroll.php?month=$m&year=$y", 'Payroll record for ' . $empRec['first_name'] . ' ' . $empRec['last_name'] . ' saved successfully.', 'success');
    }

    if ($action === 'generate') {
        $m = (int)($_POST['month'] ?? $currentMonth);
        $y = (int)($_POST['year'] ?? $currentYear);

        // Fetch all active employees who don't already have payroll for this month/year
        $empStmt = $pdo->prepare("SELECT e.* FROM employees e 
                                  WHERE e.status = 'active' 
                                  AND e.id NOT IN (SELECT employee_id FROM payroll WHERE month = ? AND year = ?)");
        $empStmt->execute([$m, $y]);
        $eligible = $empStmt->fetchAll();

        if (empty($eligible)) {
            redirectWith(APP_URL . "/admin/payroll.php?month=$m&year=$y", 'All active employees already have payroll generated for this period.', 'info');
        }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $m, $y);
        $insertStmt = $pdo->prepare("INSERT INTO payroll (
            employee_id, month, year, basic_salary, hra, da, transport_allowance, medical_allowance, 
            other_allowances, gross_salary, pf_deduction, tax_deduction, other_deductions, 
            total_deductions, net_salary, payment_status, payment_method, remarks, generated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'bank_transfer', ?, ?)");

        // Unpaid leave query for LWP deduction
        $unpaidStmt = $pdo->prepare("SELECT COALESCE(SUM(total_days), 0) FROM leaves 
                                     WHERE employee_id = ? 
                                     AND status = 'approved' 
                                     AND leave_type = 'unpaid' 
                                     AND MONTH(start_date) = ? AND YEAR(start_date) = ?");

        $count = 0;
        foreach ($eligible as $emp) {
            $gross = (float)$emp['salary'];
            if ($gross <= 0) $gross = 30000.00;

            // Structure breakdown
            $basic = round($gross * 0.50, 2);
            $hra = round($gross * 0.20, 2);
            $da = round($gross * 0.10, 2);
            $transport = round($gross * 0.10, 2);
            $medical = round($gross - ($basic + $hra + $da + $transport), 2);
            $otherAllowances = 0.00;

            // Deductions
            $pf = round($basic * 0.12, 2);
            $tax = 0.00;
            if ($gross > 75000) {
                $tax = round($gross * 0.10, 2);
            } elseif ($gross > 40000) {
                $tax = round($gross * 0.05, 2);
            }

            // Unpaid Leave (LWP) check
            $unpaidStmt->execute([$emp['id'], $m, $y]);
            $unpaidDays = (int)$unpaidStmt->fetchColumn();
            $lwpDeduction = 0.00;
            $remarks = 'Automated monthly payroll generation.';
            if ($unpaidDays > 0) {
                $lwpDeduction = round(($gross / $daysInMonth) * $unpaidDays, 2);
                $remarks = "Includes {$unpaidDays} day(s) unpaid leave deduction of ₹" . number_format($lwpDeduction, 2);
            }

            $otherDeductions = $lwpDeduction;
            $totalDeductions = round($pf + $tax + $otherDeductions, 2);
            $net = round($gross - $totalDeductions, 2);

            $insertStmt->execute([
                $emp['id'], $m, $y, $basic, $hra, $da, $transport, $medical,
                $otherAllowances, $gross, $pf, $tax, $otherDeductions,
                $totalDeductions, $net, $remarks, getCurrentUserId()
            ]);

            // Notify employee
            if (!empty($emp['user_id'])) {
                $monthName = date('F', mktime(0, 0, 0, $m, 10, $y));
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', ?)");
                $notif->execute([
                    $emp['user_id'],
                    "Payroll Generated for {$monthName} {$y}",
                    "Your salary slip for {$monthName} {$y} has been generated with Net Payable ₹" . number_format($net, 2),
                    APP_URL . '/employee/payroll.php'
                ]);
            }

            $count++;
        }

        redirectWith(APP_URL . "/admin/payroll.php?month=$m&year=$y", "Successfully generated payroll records for $count employee(s).", 'success');
    }

    if ($action === 'edit_salary') {
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $basic = max(0, (float)($_POST['basic_salary'] ?? 0));
        $hra = max(0, (float)($_POST['hra'] ?? 0));
        $da = max(0, (float)($_POST['da'] ?? 0));
        $transport = max(0, (float)($_POST['transport_allowance'] ?? 0));
        $medical = max(0, (float)($_POST['medical_allowance'] ?? 0));
        $otherAllowances = max(0, (float)($_POST['other_allowances'] ?? 0));

        $pf = max(0, (float)($_POST['pf_deduction'] ?? 0));
        $tax = max(0, (float)($_POST['tax_deduction'] ?? 0));
        $otherDeductions = max(0, (float)($_POST['other_deductions'] ?? 0));
        $remarks = trim($_POST['remarks'] ?? '');

        $gross = round($basic + $hra + $da + $transport + $medical + $otherAllowances, 2);
        $totalDeductions = round($pf + $tax + $otherDeductions, 2);
        $net = round(max(0, $gross - $totalDeductions), 2);

        $stmt = $pdo->prepare("UPDATE payroll SET 
            basic_salary = ?, hra = ?, da = ?, transport_allowance = ?, medical_allowance = ?, other_allowances = ?,
            gross_salary = ?, pf_deduction = ?, tax_deduction = ?, other_deductions = ?, total_deductions = ?,
            net_salary = ?, remarks = ?
            WHERE id = ?");
        $stmt->execute([
            $basic, $hra, $da, $transport, $medical, $otherAllowances,
            $gross, $pf, $tax, $otherDeductions, $totalDeductions,
            $net, $remarks, $payroll_id
        ]);

        redirectWith(APP_URL . "/admin/payroll.php?month=$currentMonth&year=$currentYear", 'Salary structure adjusted and recalculated successfully.', 'success');
    }

    if ($action === 'update_payment') {
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $status = in_array($_POST['payment_status'] ?? '', ['pending', 'paid', 'hold']) ? $_POST['payment_status'] : 'pending';
        $method = in_array($_POST['payment_method'] ?? '', ['bank_transfer', 'cheque', 'cash']) ? $_POST['payment_method'] : 'bank_transfer';
        $payDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : ($status === 'paid' ? date('Y-m-d') : null);
        $remarks = trim($_POST['remarks'] ?? '');

        if ($payroll_id <= 0) {
            redirectWith(APP_URL . "/admin/payroll.php?month=$currentMonth&year=$currentYear", 'Invalid payroll record.', 'danger');
        }

        $stmt = $pdo->prepare("UPDATE payroll SET payment_status = ?, payment_method = ?, payment_date = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$status, $method, $payDate, $remarks, $payroll_id]);

        // If marked as paid, notify employee
        if ($status === 'paid') {
            $pInfo = $pdo->prepare("SELECT p.month, p.year, p.net_salary, e.user_id 
                                    FROM payroll p 
                                    JOIN employees e ON p.employee_id = e.id 
                                    WHERE p.id = ?");
            $pInfo->execute([$payroll_id]);
            $rec = $pInfo->fetch();
            if ($rec && !empty($rec['user_id'])) {
                $monthName = date('F', mktime(0, 0, 0, $rec['month'], 10));
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'success', ?)");
                $notif->execute([
                    $rec['user_id'],
                    'Salary Disbursed for ' . $monthName . ' ' . $rec['year'],
                    'Your salary of ₹' . number_format($rec['net_salary'], 2) . ' for ' . $monthName . ' has been processed.',
                    APP_URL . '/employee/payroll.php'
                ]);
            }
        }

        redirectWith(APP_URL . "/admin/payroll.php?month=$currentMonth&year=$currentYear", 'Payment status updated successfully!', 'success');
    }

    if ($action === 'mark_all_paid') {
        $m = (int)($_POST['month'] ?? $currentMonth);
        $y = (int)($_POST['year'] ?? $currentYear);
        $payDate = date('Y-m-d');

        // Fetch all pending to notify them
        $pendingQuery = $pdo->prepare("SELECT p.id, p.month, p.year, p.net_salary, e.user_id 
                                       FROM payroll p 
                                       JOIN employees e ON p.employee_id = e.id 
                                       WHERE p.month = ? AND p.year = ? AND p.payment_status != 'paid'");
        $pendingQuery->execute([$m, $y]);
        $pendingRecords = $pendingQuery->fetchAll();

        $stmt = $pdo->prepare("UPDATE payroll SET payment_status = 'paid', payment_date = ? WHERE month = ? AND year = ? AND payment_status != 'paid'");
        $stmt->execute([$payDate, $m, $y]);
        $affected = $stmt->rowCount();

        // Notify each employee
        $monthName = date('F', mktime(0, 0, 0, $m, 10));
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'success', ?)");
        foreach ($pendingRecords as $rec) {
            if (!empty($rec['user_id'])) {
                $notifStmt->execute([
                    $rec['user_id'],
                    "Salary Disbursed for {$monthName} {$y}",
                    "Your salary of ₹" . number_format($rec['net_salary'], 2) . " for {$monthName} has been disbursed.",
                    APP_URL . '/employee/payroll.php'
                ]);
            }
        }

        redirectWith(APP_URL . "/admin/payroll.php?month=$m&year=$y", "Marked $affected payroll record(s) as Paid.", 'success');
    }

    if ($action === 'delete') {
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM payroll WHERE id = ? AND payment_status != 'paid'");
        $del->execute([$payroll_id]);

        redirectWith(APP_URL . "/admin/payroll.php?month=$currentMonth&year=$currentYear", 'Payroll item deleted.', 'success');
    }
}

// Fetch Payroll records for selected Month & Year
$query = "SELECT p.*, e.employee_id AS emp_code, e.first_name, e.last_name, e.designation,
                 e.bank_name, e.bank_account, e.ifsc_code, e.pan_number,
                 d.name AS department_name
          FROM payroll p
          JOIN employees e ON p.employee_id = e.id
          LEFT JOIN departments d ON e.department_id = d.id
          WHERE p.month = ? AND p.year = ?";
$params = [$currentMonth, $currentYear];

if ($deptFilter > 0) {
    $query .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'paid', 'hold'])) {
    $query .= " AND p.payment_status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY e.first_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payrollList = $stmt->fetchAll();

// CSV Export Stream Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hrms_payroll_' . $currentYear . '_' . str_pad((string)$currentMonth, 2, '0', STR_PAD_LEFT) . '_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Emp Code', 'Employee Name', 'Department', 'Designation',
        'Month', 'Year', 'Basic (INR)', 'HRA (INR)', 'DA (INR)', 'Transport (INR)', 'Medical (INR)', 'Other Allowances (INR)',
        'Gross Salary (INR)', 'PF (INR)', 'Tax / TDS (INR)', 'Other Deductions (INR)', 'Total Deductions (INR)', 'Net Salary (INR)',
        'Status', 'Payment Method', 'Payment Date', 'Bank Name', 'Account No', 'IFSC Code', 'PAN', 'Remarks'
    ]);
    foreach ($payrollList as $row) {
        fputcsv($output, [
            $row['id'],
            $row['emp_code'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['department_name'] ?? 'General',
            $row['designation'],
            date('F', mktime(0, 0, 0, (int)$row['month'], 10)),
            $row['year'],
            $row['basic_salary'],
            $row['hra'],
            $row['da'],
            $row['transport_allowance'],
            $row['medical_allowance'],
            $row['other_allowances'],
            $row['gross_salary'],
            $row['pf_deduction'],
            $row['tax_deduction'],
            $row['other_deductions'],
            $row['total_deductions'],
            $row['net_salary'],
            ucfirst($row['payment_status']),
            ucwords(str_replace('_', ' ', $row['payment_method'])),
            $row['payment_date'] ?? 'N/A',
            $row['bank_name'] ?? 'N/A',
            $row['bank_account'] ?? 'N/A',
            $row['ifsc_code'] ?? 'N/A',
            $row['pan_number'] ?? 'N/A',
            $row['remarks'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Aggregated Summary for Selected Month
$summaryStmt = $pdo->prepare("SELECT 
    COUNT(id) AS total_records,
    COALESCE(SUM(gross_salary), 0) AS total_gross,
    COALESCE(SUM(total_deductions), 0) AS total_deductions,
    COALESCE(SUM(net_salary), 0) AS total_net,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_count
FROM payroll 
WHERE month = ? AND year = ?");
$summaryStmt->execute([$currentMonth, $currentYear]);
$summary = $summaryStmt->fetch();

$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$allEmployees = $pdo->query("SELECT e.id, e.employee_id, e.first_name, e.last_name, e.salary, d.name AS department_name 
                             FROM employees e 
                             LEFT JOIN departments d ON e.department_id = d.id 
                             WHERE e.status = 'active' 
                             ORDER BY e.first_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Period Selector & Controls Toolbar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; flex: 1;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.9rem;">Month:</label>
                    <select name="month" class="form-control" style="width: 140px;" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $currentMonth === $m ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.9rem;">Year:</label>
                    <select name="year" class="form-control" style="width: 110px;" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $currentYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <select name="department_id" class="form-control" style="width: 160px;" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= sanitize($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control" style="width: 130px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="hold" <?= $statusFilter === 'hold' ? 'selected' : '' ?>>On Hold</option>
                </select>
            </form>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <!-- Create Payslip Button -->
                <button type="button" class="btn btn-outline" onclick="openCreatePayslipModal()" title="Create individual employee payslip">
                    <i class='bx bx-plus-circle'></i> Create Payslip
                </button>

                <!-- CSV Export -->
                <a href="?export=csv&month=<?= $currentMonth ?>&year=<?= $currentYear ?><?= $deptFilter ? '&department_id=' . $deptFilter : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-outline" title="Download Payroll Register CSV">
                    <i class='bx bx-export'></i> Export CSV
                </a>

                <!-- Generate Payroll Button -->
                <form method="POST" action="" onsubmit="return confirm('Generate payroll for all active employees for <?= date('F Y', mktime(0,0,0,$currentMonth,10,$currentYear)) ?>?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="month" value="<?= $currentMonth ?>">
                    <input type="hidden" name="year" value="<?= $currentYear ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-calculator'></i> Generate Payroll
                    </button>
                </form>

                <!-- Mark All Paid Button -->
                <?php if (($summary['pending_count'] ?? 0) > 0): ?>
                <form method="POST" action="" onsubmit="return confirm('Mark all pending payroll records as Paid for <?= date('F Y', mktime(0,0,0,$currentMonth,10,$currentYear)) ?>?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="mark_all_paid">
                    <input type="hidden" name="month" value="<?= $currentMonth ?>">
                    <input type="hidden" name="year" value="<?= $currentYear ?>">
                    <button type="submit" class="btn btn-success">
                        <i class='bx bx-check-double'></i> Mark All Paid
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payroll Financial Summary Cards -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Total Gross Salary</h4>
            <div class="stat-number">₹<?= number_format($summary['total_gross'], 2) ?></div>
            <div class="stat-change up"><i class='bx bx-briefcase'></i> Total Allocated</div>
        </div>
        <div class="stat-icon blue"><i class='bx bxs-wallet'></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Total Deductions (PF/Tax/LWP)</h4>
            <div class="stat-number">₹<?= number_format($summary['total_deductions'], 2) ?></div>
            <div class="stat-change down"><i class='bx bx-minus'></i> Statutory & Leave Deductions</div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-pie-chart-alt-2'></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Total Net Disbursed</h4>
            <div class="stat-number">₹<?= number_format($summary['total_net'], 2) ?></div>
            <div class="stat-change up"><i class='bx bx-check-circle'></i> Net Take-Home</div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-badge-check'></i></div>
    </div>
    <div class="stat-card red">
        <div class="stat-info">
            <h4>Payment Status</h4>
            <div class="stat-number"><?= (int)($summary['paid_count'] ?? 0) ?> / <?= (int)($summary['total_records'] ?? 0) ?></div>
            <div class="stat-change <?= ($summary['pending_count'] ?? 0) > 0 ? 'down' : 'up' ?>">
                <i class='bx bx-time'></i> <?= (int)($summary['pending_count'] ?? 0) ?> Pending
            </div>
        </div>
        <div class="stat-icon red"><i class='bx bxs-credit-card'></i></div>
    </div>
</div>

<!-- Payroll Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            Payroll Register &mdash; <?= date('F Y', mktime(0, 0, 0, $currentMonth, 10, $currentYear)) ?>
        </div>
        <span class="badge badge-secondary"><?= count($payrollList) ?> Employees</span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Basic</th>
                    <th>Allowances</th>
                    <th>Gross</th>
                    <th>Deductions</th>
                    <th>Net Salary</th>
                    <th>Status</th>
                    <th>Payment Details</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payrollList)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <i class='bx bx-calculator' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        No payroll records found for this period. Click <strong>"Generate Payroll"</strong> to initialize salary computations.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($payrollList as $p): 
                        $fullName = $p['first_name'] . ' ' . $p['last_name'];
                        $initials = strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1));
                        $totalAllowances = (float)$p['hra'] + (float)$p['da'] + (float)$p['transport_allowance'] + (float)$p['medical_allowance'] + (float)$p['other_allowances'];
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar-sm" style="width: 32px; height: 32px; font-size: 0.8rem; font-weight: 700;">
                                    <?= $initials ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);"><?= sanitize($fullName) ?></div>
                                    <small style="color: var(--text-muted); font-family: monospace;"><?= sanitize($p['emp_code']) ?> • <?= sanitize($p['department_name'] ?? 'General') ?></small>
                                </div>
                            </div>
                        </td>
                        <td>₹<?= number_format($p['basic_salary'], 2) ?></td>
                        <td>₹<?= number_format($totalAllowances, 2) ?></td>
                        <td><strong>₹<?= number_format($p['gross_salary'], 2) ?></strong></td>
                        <td style="color: var(--danger);">₹<?= number_format($p['total_deductions'], 2) ?></td>
                        <td>
                            <strong style="color: var(--success); font-size: 0.95rem;">
                                ₹<?= number_format($p['net_salary'], 2) ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($p['payment_status'] === 'paid'): ?>
                            <span class="badge badge-success"><i class='bx bx-check'></i> Paid</span>
                            <?php elseif ($p['payment_status'] === 'hold'): ?>
                            <span class="badge badge-danger"><i class='bx bx-pause'></i> Hold</span>
                            <?php else: ?>
                            <span class="badge badge-warning"><i class='bx bx-time'></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); text-transform: capitalize;">
                                <?= str_replace('_', ' ', $p['payment_method']) ?>
                            </div>
                            <?php if ($p['payment_date']): ?>
                            <small style="color: var(--text-muted);"><?= date('d M Y', strtotime($p['payment_date'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- Dedicated Printable Slip in New Tab -->
                            <a href="<?= APP_URL ?>/payslip.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="Open Full Printable Salary Slip">
                                <i class='bx bx-printer'></i> Slip
                            </a>
                            <!-- Adjust Breakdown Button -->
                            <button type="button" class="btn btn-outline btn-sm" onclick='editSalaryModal(<?= json_encode($p) ?>)' title="Adjust Salary Components">
                                <i class='bx bx-slider-alt'></i> Adjust
                            </button>
                            <!-- Update Status Button -->
                            <button type="button" class="btn btn-outline btn-sm" onclick='updatePaymentModal(<?= json_encode($p) ?>)' title="Update Payment Status">
                                <i class='bx bx-edit'></i> Status
                            </button>
                            <?php if ($p['payment_status'] !== 'paid'): ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeletePayroll(<?= $p['id'] ?>)" title="Delete Entry">
                                <i class='bx bx-trash'></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Quick Payslip View & Print -->
<div class="modal-overlay" id="payslipModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Salary Payslip Preview</h3>
            <button class="modal-close" onclick="closeModal('payslipModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body" id="payslipContent">
            <!-- Dynamically populated via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('payslipModal')">Close</button>
            <a href="#" id="dedicatedSlipLink" target="_blank" class="btn btn-outline">
                <i class='bx bx-link-external'></i> Open Full Slip
            </a>
            <button type="button" class="btn btn-primary" onclick="printModalSlip()"><i class='bx bx-printer'></i> Print Now</button>
        </div>
    </div>
</div>

<!-- Modal: Edit / Adjust Salary Components -->
<div class="modal-overlay" id="editSalaryModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Adjust Salary Structure & Deductions</h3>
            <button class="modal-close" onclick="closeModal('editSalaryModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="" id="editSalaryForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit_salary">
                <input type="hidden" name="payroll_id" id="editPayrollId">

                <div style="background: var(--bg-hover); padding: 12px 16px; border-radius: var(--border-radius); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 700; font-size: 1rem;" id="editEmpName"></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);" id="editEmpMeta"></div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge badge-info" id="editPeriod"></span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Earnings Column -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 16px;">
                        <h4 style="color: var(--success); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
                            <i class='bx bx-plus-circle'></i> Earnings / Allowances (₹)
                        </h4>
                        
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Basic Salary</label>
                            <input type="number" step="0.01" name="basic_salary" id="editBasic" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">House Rent Allowance (HRA)</label>
                            <input type="number" step="0.01" name="hra" id="editHra" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Dearness Allowance (DA)</label>
                            <input type="number" step="0.01" name="da" id="editDa" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Transport Allowance</label>
                            <input type="number" step="0.01" name="transport_allowance" id="editTransport" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Medical Allowance</label>
                            <input type="number" step="0.01" name="medical_allowance" id="editMedical" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Other Allowances / Bonus</label>
                            <input type="number" step="0.01" name="other_allowances" id="editOtherAllow" class="form-control" oninput="calcSalary()">
                        </div>
                        <div style="border-top: 1px solid var(--border-color); padding-top: 8px; margin-top: 8px; display: flex; justify-content: space-between; font-weight: 700;">
                            <span>Computed Gross:</span>
                            <span id="previewGross" style="color: var(--primary);">₹0.00</span>
                        </div>
                    </div>

                    <!-- Deductions Column -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 16px;">
                        <h4 style="color: var(--danger); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
                            <i class='bx bx-minus-circle'></i> Deductions (₹)
                        </h4>

                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Provident Fund (PF)</label>
                            <input type="number" step="0.01" name="pf_deduction" id="editPf" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Tax / TDS</label>
                            <input type="number" step="0.01" name="tax_deduction" id="editTax" class="form-control" oninput="calcSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.85rem;">Other Deductions (LWP / Recovery)</label>
                            <input type="number" step="0.01" name="other_deductions" id="editOtherDeduct" class="form-control" oninput="calcSalary()">
                        </div>

                        <div style="border-top: 1px solid var(--border-color); padding-top: 8px; margin-top: 8px; display: flex; justify-content: space-between; font-weight: 700;">
                            <span>Total Deductions:</span>
                            <span id="previewDeductions" style="color: var(--danger);">₹0.00</span>
                        </div>

                        <div class="form-group" style="margin-top: 20px;">
                            <label class="form-label" style="font-size: 0.85rem;">Adjustment Remarks</label>
                            <textarea name="remarks" id="editRemarks" class="form-control" rows="3" placeholder="Reason for salary adjustments..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Net Take Home Highlight Box -->
                <div style="background: linear-gradient(135deg, var(--primary-50), var(--primary-100)); border: 1px solid var(--primary-200); border-radius: var(--border-radius); padding: 14px 20px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 700; color: var(--primary-900);">Calculated Net Salary</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">Gross Earnings minus Total Deductions</div>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary);" id="previewNet">
                        ₹0.00
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editSalaryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Payment Status -->
<div class="modal-overlay" id="paymentStatusModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Update Disbursement Status</h3>
            <button class="modal-close" onclick="closeModal('paymentStatusModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="payroll_id" id="statusPayrollId">

                <div style="background: var(--bg-hover); padding: 12px 16px; border-radius: var(--border-radius); margin-bottom: 16px;">
                    <div style="font-weight: 700;" id="statusEmpName"></div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);" id="statusNetSalary"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Status <span class="required">*</span></label>
                    <select name="payment_status" id="statusSelect" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="hold">On Hold</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" id="methodSelect" class="form-control">
                        <option value="bank_transfer">Bank Transfer (NEFT/RTGS/IMPS)</option>
                        <option value="cheque">Cheque</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" id="dateInput" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Remarks / Transaction Ref</label>
                    <input type="text" name="remarks" id="remarksInput" class="form-control" placeholder="e.g. UTR / Ref # 987654321">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('paymentStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Save Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Individual Payslip Modal -->
<div class="modal-overlay" id="createPayslipModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Employee Payslip</h3>
            <button class="modal-close" onclick="closeModal('createPayslipModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create_single">

                <div class="form-group">
                    <label class="form-label">Select Employee <span class="required">*</span></label>
                    <select name="employee_id" id="createEmpId" class="form-control" required onchange="handleEmpSelect(this.value)">
                        <option value="">-- Choose Employee from Database --</option>
                        <?php foreach ($allEmployees as $ae): ?>
                        <option value="<?= $ae['id'] ?>" data-salary="<?= $ae['salary'] ?>">
                            <?= sanitize($ae['employee_id']) ?> - <?= sanitize($ae['first_name'] . ' ' . $ae['last_name']) ?> (<?= sanitize($ae['department_name'] ?: 'General') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Month <span class="required">*</span></label>
                        <select name="month" class="form-control" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $currentMonth === $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Year <span class="required">*</span></label>
                        <input type="number" name="year" class="form-control" value="<?= $currentYear ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Basic Salary (₹) <span class="required">*</span></label>
                        <input type="number" step="0.01" name="basic_salary" id="createBasic" class="form-control" required oninput="calcCreateNet()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Allowances (₹)</label>
                        <input type="number" step="0.01" name="allowances" id="createAllowances" class="form-control" value="0.00" oninput="calcCreateNet()">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Deductions (₹)</label>
                    <input type="number" step="0.01" name="deductions" id="createDeductions" class="form-control" value="0.00" oninput="calcCreateNet()">
                </div>

                <!-- Live Net Calculation Preview -->
                <div style="background: var(--bg-hover); padding: 12px 16px; border-radius: var(--border-radius); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Net Payable Salary</div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">(Basic + Allowances - Deductions)</div>
                    </div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: var(--primary-600);" id="createNetDisplay">₹0.00</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="hold">On Hold</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" class="form-control" placeholder="e.g. Regular monthly payroll">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createPayslipModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-check'></i> Save Payslip</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deletePayrollForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="payroll_id" id="deletePayrollId">
</form>

<script>
const monthNames = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

function editSalaryModal(p) {
    document.getElementById('editPayrollId').value = p.id;
    document.getElementById('editEmpName').textContent = p.first_name + ' ' + p.last_name + ' (' + p.emp_code + ')';
    document.getElementById('editEmpMeta').textContent = (p.designation || 'Associate') + ' • ' + (p.department_name || 'General');
    document.getElementById('editPeriod').textContent = monthNames[p.month] + ' ' + p.year;

    document.getElementById('editBasic').value = parseFloat(p.basic_salary).toFixed(2);
    document.getElementById('editHra').value = parseFloat(p.hra).toFixed(2);
    document.getElementById('editDa').value = parseFloat(p.da).toFixed(2);
    document.getElementById('editTransport').value = parseFloat(p.transport_allowance).toFixed(2);
    document.getElementById('editMedical').value = parseFloat(p.medical_allowance).toFixed(2);
    document.getElementById('editOtherAllow').value = parseFloat(p.other_allowances).toFixed(2);

    document.getElementById('editPf').value = parseFloat(p.pf_deduction).toFixed(2);
    document.getElementById('editTax').value = parseFloat(p.tax_deduction).toFixed(2);
    document.getElementById('editOtherDeduct').value = parseFloat(p.other_deductions).toFixed(2);
    document.getElementById('editRemarks').value = p.remarks || '';

    calcSalary();
    openModal('editSalaryModal');
}

function calcSalary() {
    const basic = parseFloat(document.getElementById('editBasic').value) || 0;
    const hra = parseFloat(document.getElementById('editHra').value) || 0;
    const da = parseFloat(document.getElementById('editDa').value) || 0;
    const transport = parseFloat(document.getElementById('editTransport').value) || 0;
    const medical = parseFloat(document.getElementById('editMedical').value) || 0;
    const otherAllow = parseFloat(document.getElementById('editOtherAllow').value) || 0;

    const pf = parseFloat(document.getElementById('editPf').value) || 0;
    const tax = parseFloat(document.getElementById('editTax').value) || 0;
    const otherDeduct = parseFloat(document.getElementById('editOtherDeduct').value) || 0;

    const gross = basic + hra + da + transport + medical + otherAllow;
    const deductions = pf + tax + otherDeduct;
    const net = Math.max(0, gross - deductions);

    document.getElementById('previewGross').textContent = '₹' + gross.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('previewDeductions').textContent = '₹' + deductions.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('previewNet').textContent = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function updatePaymentModal(p) {
    document.getElementById('statusPayrollId').value = p.id;
    document.getElementById('statusEmpName').textContent = p.first_name + ' ' + p.last_name + ' (' + p.emp_code + ')';
    document.getElementById('statusNetSalary').textContent = 'Net Salary: ₹' + parseFloat(p.net_salary).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('statusSelect').value = p.payment_status;
    document.getElementById('methodSelect').value = p.payment_method || 'bank_transfer';
    document.getElementById('dateInput').value = p.payment_date || '<?= date('Y-m-d') ?>';
    document.getElementById('remarksInput').value = p.remarks || '';
    openModal('paymentStatusModal');
}

function confirmDeletePayroll(id) {
    if (confirm('Are you sure you want to delete this pending payroll record?')) {
        document.getElementById('deletePayrollId').value = id;
        document.getElementById('deletePayrollForm').submit();
    }
}

function openCreatePayslipModal() {
    document.getElementById('createEmpId').value = '';
    document.getElementById('createBasic').value = '0.00';
    document.getElementById('createAllowances').value = '0.00';
    document.getElementById('createDeductions').value = '0.00';
    calcCreateNet();
    openModal('createPayslipModal');
}

function handleEmpSelect(empId) {
    const sel = document.getElementById('createEmpId');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.salary) {
        const sal = parseFloat(opt.dataset.salary) || 0;
        const basic = Math.round(sal * 0.6);
        const allowances = Math.round(sal * 0.4);
        const pf = Math.round(basic * 0.12);
        document.getElementById('createBasic').value = basic.toFixed(2);
        document.getElementById('createAllowances').value = allowances.toFixed(2);
        document.getElementById('createDeductions').value = pf.toFixed(2);
        calcCreateNet();
    }
}

function calcCreateNet() {
    const b = parseFloat(document.getElementById('createBasic').value) || 0;
    const a = parseFloat(document.getElementById('createAllowances').value) || 0;
    const d = parseFloat(document.getElementById('createDeductions').value) || 0;
    const net = Math.max(0, b + a - d);
    document.getElementById('createNetDisplay').textContent = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function printModalSlip() {
    window.print();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
