<?php
/**
 * HRMS - Employee Payroll & Payslip Access
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'My Payslips');

$pdo = getDBConnection();
$empId = getCurrentEmployeeId();
$yearFilter = !empty($_GET['year']) ? (int)$_GET['year'] : 0;

// Fetch employee info & banking details
$empStmt = $pdo->prepare("SELECT e.*, d.name AS department_name 
                          FROM employees e 
                          LEFT JOIN departments d ON e.department_id = d.id 
                          WHERE e.id = ?");
$empStmt->execute([$empId]);
$employee = $empStmt->fetch();

// Available Years for filter
$yearsStmt = $pdo->prepare("SELECT DISTINCT year FROM payroll WHERE employee_id = ? ORDER BY year DESC");
$yearsStmt->execute([$empId]);
$availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch payroll history
$query = "SELECT * FROM payroll WHERE employee_id = ?";
$params = [$empId];

if ($yearFilter > 0) {
    $query .= " AND year = ?";
    $params[] = $yearFilter;
}

$query .= " ORDER BY year DESC, month DESC";
$payStmt = $pdo->prepare($query);
$payStmt->execute($params);
$payslips = $payStmt->fetchAll();

// Summary stats across all records
$allTimeStmt = $pdo->prepare("SELECT 
    COUNT(id) AS total_slips,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN net_salary ELSE 0 END), 0) AS total_disbursed,
    (SELECT net_salary FROM payroll WHERE employee_id = ? AND payment_status = 'paid' ORDER BY year DESC, month DESC LIMIT 1) AS last_net,
    (SELECT month FROM payroll WHERE employee_id = ? AND payment_status = 'paid' ORDER BY year DESC, month DESC LIMIT 1) AS last_month,
    (SELECT year FROM payroll WHERE employee_id = ? AND payment_status = 'paid' ORDER BY year DESC, month DESC LIMIT 1) AS last_year
FROM payroll WHERE employee_id = ?");
$allTimeStmt->execute([$empId, $empId, $empId, $empId]);
$stats = $allTimeStmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<!-- Financial Summary Cards -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Base Monthly Gross</h4>
            <div class="stat-number">₹<?= number_format($employee['salary'] ?? 0, 2) ?></div>
            <div class="stat-change up"><i class='bx bx-check'></i> Contractual Salary</div>
        </div>
        <div class="stat-icon blue"><i class='bx bxs-wallet'></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Last Disbursed Salary</h4>
            <div class="stat-number">
                <?= $stats['last_net'] !== null ? '₹' . number_format($stats['last_net'], 2) : 'Pending' ?>
            </div>
            <div class="stat-change up">
                <i class='bx bx-calendar'></i> 
                <?= $stats['last_month'] ? date('F Y', mktime(0, 0, 0, (int)$stats['last_month'], 10, (int)$stats['last_year'])) : 'No processed slips' ?>
            </div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-badge-check'></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Cumulative Received</h4>
            <div class="stat-number">₹<?= number_format($stats['total_disbursed'], 2) ?></div>
            <div class="stat-change up"><i class='bx bx-coin-stack'></i> All time take-home</div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-bank'></i></div>
    </div>
</div>

<!-- Salary Slips Table Card -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div class="card-title">Salary Slips & Disbursal History</div>
        
        <!-- Filter by Year -->
        <form method="GET" action="" style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">Year:</label>
            <select name="year" class="form-control" style="width: 120px;" onchange="this.form.submit()">
                <option value="">All Years</option>
                <?php foreach ($availableYears as $y): ?>
                <option value="<?= $y ?>" <?= $yearFilter === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pay Period</th>
                    <th>Gross Salary</th>
                    <th>Deductions</th>
                    <th>Net Disbursed</th>
                    <th>Disbursement Status</th>
                    <th>Payment Method</th>
                    <th>Payment Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payslips)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <i class='bx bx-wallet-alt' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        No payslips found for this selection.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($payslips as $p): 
                        $monthName = date('F', mktime(0, 0, 0, (int)$p['month'], 10));
                        $period = $monthName . ' ' . $p['year'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= $period ?></strong>
                        </td>
                        <td>₹<?= number_format($p['gross_salary'], 2) ?></td>
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
                            <span class="badge badge-danger">Hold</span>
                            <?php else: ?>
                            <span class="badge badge-warning"><i class='bx bx-time'></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-transform: capitalize; font-size: 0.85rem;">
                            <?= str_replace('_', ' ', $p['payment_method']) ?>
                        </td>
                        <td>
                            <?= $p['payment_date'] ? date('d M Y', strtotime($p['payment_date'])) : '-' ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- Fullscreen Printable Payslip -->
                            <a href="<?= APP_URL ?>/payslip.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-primary btn-sm" title="View Official Printable Payslip">
                                <i class='bx bx-printer'></i> View Slip
                            </a>
                            <!-- Quick View Modal -->
                            <button type="button" class="btn btn-outline btn-sm" onclick='viewPayslip(<?= json_encode(array_merge($p, $employee)) ?>)' title="Quick Overview">
                                <i class='bx bx-show'></i> Quick View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Payslip Modal -->
<div class="modal-overlay" id="empPayslipModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Salary Payslip Overview</h3>
            <button class="modal-close" onclick="closeModal('empPayslipModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body" id="empPayslipContent">
            <!-- Populated via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('empPayslipModal')">Close</button>
            <a href="#" id="modalPrintLink" target="_blank" class="btn btn-primary">
                <i class='bx bx-printer'></i> Open & Print Official Slip
            </a>
        </div>
    </div>
</div>

<script>
const monthNames = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

function viewPayslip(p) {
    const period = monthNames[p.month] + ' ' + p.year;
    const content = document.getElementById('empPayslipContent');
    document.getElementById('modalPrintLink').href = '<?= APP_URL ?>/payslip.php?id=' + p.id;

    content.innerHTML = `
        <div style="padding: 16px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: var(--bg-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary-500); padding-bottom: 12px; margin-bottom: 16px;">
                <div>
                    <h2 style="margin: 0; color: var(--primary-700);"><?= APP_NAME ?></h2>
                    <p style="margin: 2px 0 0 0; color: var(--text-secondary); font-size: 0.85rem;">Confidential Salary Statement &mdash; ${period}</p>
                </div>
                <div style="text-align: right;">
                    <span class="badge ${p.payment_status === 'paid' ? 'badge-success' : 'badge-warning'}" style="font-size: 0.9rem; text-transform: uppercase;">
                        ${p.payment_status}
                    </span>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Slip Ref: HRMS-PAY-${p.id}</div>
                </div>
            </div>

            <!-- Employee Info Header -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; background: var(--bg-hover); padding: 12px; border-radius: var(--border-radius); margin-bottom: 20px;">
                <div><strong>Employee:</strong> ${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</div>
                <div><strong>Emp ID:</strong> ${escapeHtml(p.employee_id || '-')}</div>
                <div><strong>Designation:</strong> ${escapeHtml(p.designation || '-')}</div>
                <div><strong>Department:</strong> ${escapeHtml(p.department_name || '-')}</div>
                <div><strong>Bank Name:</strong> ${escapeHtml(p.bank_name || '-')}</div>
                <div><strong>Account No:</strong> ${escapeHtml(p.bank_account || '-')}</div>
                <div><strong>IFSC Code:</strong> ${escapeHtml(p.ifsc_code || '-')}</div>
                <div><strong>PAN:</strong> ${escapeHtml(p.pan_number || '-')}</div>
            </div>

            <!-- Breakdown -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4 style="border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 10px; color: var(--success);">
                        Earnings
                    </h4>
                    <table style="width: 100%; font-size: 0.9rem;">
                        <tr><td style="padding: 4px 0;">Basic Salary</td><td style="text-align: right;">₹${parseFloat(p.basic_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">House Rent Allowance (HRA)</td><td style="text-align: right;">₹${parseFloat(p.hra).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">Dearness Allowance (DA)</td><td style="text-align: right;">₹${parseFloat(p.da).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">Transport Allowance</td><td style="text-align: right;">₹${parseFloat(p.transport_allowance).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">Medical Allowance</td><td style="text-align: right;">₹${parseFloat(p.medical_allowance).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">Other Allowances / Bonus</td><td style="text-align: right;">₹${parseFloat(p.other_allowances || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr style="border-top: 1px solid var(--border-color); font-weight: 700;">
                            <td style="padding: 8px 0;">Gross Earnings</td>
                            <td style="text-align: right; padding: 8px 0;">₹${parseFloat(p.gross_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </table>
                </div>

                <div>
                    <h4 style="border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 10px; color: var(--danger);">
                        Deductions
                    </h4>
                    <table style="width: 100%; font-size: 0.9rem;">
                        <tr><td style="padding: 4px 0;">Provident Fund (PF)</td><td style="text-align: right;">₹${parseFloat(p.pf_deduction).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">TDS / Income Tax</td><td style="text-align: right;">₹${parseFloat(p.tax_deduction).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr><td style="padding: 4px 0;">Other Deductions (LWP)</td><td style="text-align: right;">₹${parseFloat(p.other_deductions).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                        <tr style="border-top: 1px solid var(--border-color); font-weight: 700;">
                            <td style="padding: 8px 0;">Total Deductions</td>
                            <td style="text-align: right; padding: 8px 0; color: var(--danger);">₹${parseFloat(p.total_deductions).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Net Disbursed Card -->
            <div style="background: linear-gradient(135deg, var(--primary-50), var(--primary-100)); border: 1px solid var(--primary-200); border-radius: var(--border-radius); padding: 16px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 700; color: var(--primary-900); font-size: 1.1rem;">Net Salary Credited</div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Method: ${p.payment_method.replace('_', ' ')} ${p.payment_date ? 'on ' + p.payment_date : ''}</div>
                </div>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary-800);">
                    ₹${parseFloat(p.net_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                </div>
            </div>
        </div>
    `;
    openModal('empPayslipModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
