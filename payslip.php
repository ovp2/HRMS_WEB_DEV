<?php
/**
 * HRMS - Printable Salary Slip View
 * Accessible by Admin and the slip's Employee
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDBConnection();
$payrollId = (int)($_GET['id'] ?? 0);

if ($payrollId <= 0) {
    redirectWith(isAdmin() ? APP_URL . '/admin/payroll.php' : APP_URL . '/employee/payroll.php', 'Invalid payslip reference ID.', 'danger');
}

// Fetch Payroll Record joined with Employee, Department, and User Details
$stmt = $pdo->prepare("SELECT p.*, 
                              e.employee_id AS emp_code, e.first_name, e.last_name, e.email, e.phone,
                              e.designation, e.date_of_joining, e.bank_name, e.bank_account, e.ifsc_code, e.pan_number,
                              d.name AS department_name
                       FROM payroll p
                       JOIN employees e ON p.employee_id = e.id
                       LEFT JOIN departments d ON e.department_id = d.id
                       WHERE p.id = ?");
$stmt->execute([$payrollId]);
$slip = $stmt->fetch();

if (!$slip) {
    redirectWith(isAdmin() ? APP_URL . '/admin/payroll.php' : APP_URL . '/employee/payroll.php', 'Payslip record not found.', 'danger');
}

// Security Check: If employee, ensure the slip belongs to them
if (isEmployee()) {
    $myEmpId = getCurrentEmployeeId();
    if ((int)$slip['employee_id'] !== (int)$myEmpId) {
        http_response_code(403);
        echo "<div style='font-family: sans-serif; text-align: center; padding: 50px;'><h2>403 Forbidden</h2><p>You are not authorized to view this payslip.</p><a href='" . APP_URL . "/employee/payroll.php'>Return to My Payslips</a></div>";
        exit;
    }
}

$monthNum = (int)$slip['month'];
$yearNum = (int)$slip['year'];
$monthName = date('F', mktime(0, 0, 0, $monthNum, 10, $yearNum));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $yearNum);

// Attendance & Leaves Summary for the month
$attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance 
                          WHERE employee_id = ? 
                          AND MONTH(date) = ? AND YEAR(date) = ? 
                          AND status IN ('present', 'late', 'half-day')");
$attStmt->execute([$slip['employee_id'], $monthNum, $yearNum]);
$attendedDays = (int)$attStmt->fetchColumn();

// Approved leaves in that month
$leaveStmt = $pdo->prepare("SELECT leave_type, COALESCE(SUM(total_days), 0) AS days 
                            FROM leaves 
                            WHERE employee_id = ? 
                            AND status = 'approved' 
                            AND MONTH(start_date) = ? AND YEAR(start_date) = ? 
                            GROUP BY leave_type");
$leaveStmt->execute([$slip['employee_id'], $monthNum, $yearNum]);
$leaveRows = $leaveStmt->fetchAll();
$paidLeaveDays = 0;
$lwpDays = 0;
foreach ($leaveRows as $lr) {
    if ($lr['leave_type'] === 'unpaid') {
        $lwpDays += (int)$lr['days'];
    } else {
        $paidLeaveDays += (int)$lr['days'];
    }
}

// Total Earnings breakdown
$earnings = [
    ['label' => 'Basic Salary', 'amount' => (float)$slip['basic_salary']],
    ['label' => 'House Rent Allowance (HRA)', 'amount' => (float)$slip['hra']],
    ['label' => 'Dearness Allowance (DA)', 'amount' => (float)$slip['da']],
    ['label' => 'Transport Allowance', 'amount' => (float)$slip['transport_allowance']],
    ['label' => 'Medical Allowance', 'amount' => (float)$slip['medical_allowance']],
    ['label' => 'Other Allowances / Bonus', 'amount' => (float)$slip['other_allowances']],
];

// Total Deductions breakdown
$deductions = [
    ['label' => 'Provident Fund (PF - 12%)', 'amount' => (float)$slip['pf_deduction']],
    ['label' => 'Tax Deducted at Source (TDS)', 'amount' => (float)$slip['tax_deduction']],
    ['label' => 'Other Deductions / LWP', 'amount' => (float)$slip['other_deductions']],
];

$netInWords = amountToWordsINR((float)$slip['net_salary']);
$slipCode = 'HRMS-PAY-' . str_pad((string)$slip['id'], 6, '0', STR_PAD_LEFT);
$backUrl = isAdmin() ? APP_URL . "/admin/payroll.php?month={$monthNum}&year={$yearNum}" : APP_URL . "/employee/payroll.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?= sanitize($slip['emp_code']) ?> - <?= $monthName ?> <?= $yearNum ?> | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --bg-light: #f8fafc;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            color: var(--text-main);
            line-height: 1.5;
            padding: 30px 16px;
        }

        /* Action Toolbar */
        .toolbar {
            max-width: 860px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-outline {
            background: #fff;
            color: var(--text-main);
            border-color: var(--border-light);
        }

        .btn-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        /* Payslip Sheet */
        .payslip-sheet {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            padding: 40px;
            position: relative;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 6rem;
            font-weight: 900;
            color: rgba(226, 232, 240, 0.35);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 10px;
            z-index: 0;
            white-space: nowrap;
        }

        .content-relative {
            position: relative;
            z-index: 1;
        }

        /* Header */
        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .company-branding h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-branding p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .slip-meta {
            text-align: right;
        }

        .slip-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .slip-period {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
            margin-top: 2px;
        }

        .slip-ref {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }

        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-hold { background: #fee2e2; color: #b91c1c; }

        /* Employee Details Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 24px;
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            font-size: 0.88rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 4px;
        }

        .info-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .info-val {
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
        }

        /* Attendance Row */
        .attendance-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
            text-align: center;
        }

        .att-box {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 10px;
            background: #fff;
        }

        .att-box .val {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .att-box .lbl {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Breakdown Table */
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            overflow: hidden;
        }

        .breakdown-table th {
            background: #0f172a;
            color: #fff;
            padding: 10px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .breakdown-table th.amount-col {
            text-align: right;
            width: 150px;
        }

        .breakdown-table td {
            padding: 8px 14px;
            font-size: 0.88rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .breakdown-table td.amount {
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
        }

        .breakdown-table tr.total-row {
            background: var(--bg-light);
            font-weight: 700;
            border-top: 2px solid var(--border-light);
        }

        .breakdown-table tr.total-row td {
            padding: 12px 14px;
            font-size: 0.92rem;
        }

        /* Net Pay Box */
        .net-pay-banner {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
            border-radius: 10px;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .net-pay-banner .label-block .title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.85;
            font-weight: 600;
        }

        .net-pay-banner .label-block .words {
            font-size: 0.95rem;
            font-weight: 600;
            margin-top: 4px;
            color: #dbeafe;
        }

        .net-pay-banner .amount-figure {
            font-size: 2rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            text-align: right;
            letter-spacing: -0.5px;
        }

        /* Disbursal & Remarks */
        .disbursal-notes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            font-size: 0.82rem;
            color: var(--text-muted);
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 30px;
        }

        /* Signatures Section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 30px;
            margin-top: 20px;
            border-top: 1px solid var(--border-light);
        }

        .sign-block {
            text-align: center;
            width: 200px;
        }

        .seal-stamp {
            width: 65px;
            height: 65px;
            border: 2px dashed #3b82f6;
            color: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            transform: rotate(-10deg);
        }

        .sign-line {
            border-top: 1.5px solid #0f172a;
            margin-top: 40px;
            padding-top: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .disclaimer {
            text-align: center;
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 24px;
            border-top: 1px dashed var(--border-light);
            padding-top: 12px;
        }

        /* Print Settings */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .payslip-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .watermark {
                opacity: 0.15;
            }

            .net-pay-banner {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .breakdown-table th {
                background: #0f172a !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- Action Toolbar (Hidden in Print) -->
    <div class="toolbar">
        <a href="<?= $backUrl ?>" class="btn btn-outline">
            <i class='bx bx-arrow-back'></i> Back to <?= isAdmin() ? 'Payroll Register' : 'My Payslips' ?>
        </a>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-outline" onclick="window.print()">
                <i class='bx bx-download'></i> Save as PDF
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class='bx bx-printer'></i> Print Payslip
            </button>
        </div>
    </div>

    <!-- Official Payslip Sheet -->
    <div class="payslip-sheet">
        <div class="watermark"><?= strtoupper($slip['payment_status']) ?></div>

        <div class="content-relative">
            <!-- Header -->
            <div class="slip-header">
                <div class="company-branding">
                    <h1>
                        <i class='bx bxs-buildings' style="color: var(--primary);"></i>
                        <?= APP_NAME ?> Enterprise
                    </h1>
                    <p>Corporate Human Resource Management System &bull; Payroll Division</p>
                    <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">CIN: U72200KA2026PTC123456 &bull; GSTIN: 29AAAAA0000A1Z5</p>
                </div>
                <div class="slip-meta">
                    <div class="slip-title">Salary Payslip</div>
                    <div class="slip-period"><?= $monthName ?> <?= $yearNum ?></div>
                    <div class="slip-ref"><?= $slipCode ?></div>
                    <span class="badge-status badge-<?= $slip['payment_status'] ?>">
                        <i class='bx <?= $slip['payment_status'] === 'paid' ? 'bx-check-circle' : 'bx-time' ?>'></i>
                        <?= $slip['payment_status'] ?>
                    </span>
                </div>
            </div>

            <!-- Employee & Banking Details Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Employee Name:</span>
                    <span class="info-val"><?= sanitize($slip['first_name'] . ' ' . $slip['last_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Employee Code:</span>
                    <span class="info-val" style="font-family: 'JetBrains Mono', monospace;"><?= sanitize($slip['emp_code']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Department:</span>
                    <span class="info-val"><?= sanitize($slip['department_name'] ?? 'General') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Designation:</span>
                    <span class="info-val"><?= sanitize($slip['designation'] ?: 'Associate') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date of Joining:</span>
                    <span class="info-val"><?= !empty($slip['date_of_joining']) ? date('d M Y', strtotime($slip['date_of_joining'])) : '-' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">PAN Number:</span>
                    <span class="info-val" style="font-family: 'JetBrains Mono', monospace;"><?= sanitize($slip['pan_number'] ?: 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Bank Name:</span>
                    <span class="info-val"><?= sanitize($slip['bank_name'] ?: 'State Bank of India') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Bank Account No:</span>
                    <span class="info-val" style="font-family: 'JetBrains Mono', monospace;"><?= sanitize($slip['bank_account'] ?: 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">IFSC Code:</span>
                    <span class="info-val" style="font-family: 'JetBrains Mono', monospace;"><?= sanitize($slip['ifsc_code'] ?: 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Mode:</span>
                    <span class="info-val" style="text-transform: capitalize;"><?= str_replace('_', ' ', $slip['payment_method']) ?></span>
                </div>
            </div>

            <!-- Attendance Strip -->
            <div class="attendance-strip">
                <div class="att-box">
                    <div class="val"><?= $daysInMonth ?></div>
                    <div class="lbl">Days in Month</div>
                </div>
                <div class="att-box">
                    <div class="val" style="color: var(--success);"><?= $attendedDays ?></div>
                    <div class="lbl">Days Attended</div>
                </div>
                <div class="att-box">
                    <div class="val" style="color: var(--primary);"><?= $paidLeaveDays ?></div>
                    <div class="lbl">Paid Leave Days</div>
                </div>
                <div class="att-box">
                    <div class="val" style="color: <?= $lwpDays > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;"><?= $lwpDays ?></div>
                    <div class="lbl">Unpaid / LWP Days</div>
                </div>
            </div>

            <!-- Earnings vs Deductions Breakdown -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Earnings Column -->
                <div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Earnings</th>
                                <th class="amount-col">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($earnings as $item): ?>
                            <tr>
                                <td><?= $item['label'] ?></td>
                                <td class="amount">₹<?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td>Gross Earnings</td>
                                <td class="amount" style="color: var(--primary);">₹<?= number_format($slip['gross_salary'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Deductions Column -->
                <div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Deductions</th>
                                <th class="amount-col">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deductions as $item): ?>
                            <tr>
                                <td><?= $item['label'] ?></td>
                                <td class="amount" style="color: var(--danger);">₹<?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Spacers to equalize height -->
                            <tr><td>&nbsp;</td><td class="amount">&nbsp;</td></tr>
                            <tr><td>&nbsp;</td><td class="amount">&nbsp;</td></tr>
                            <tr><td>&nbsp;</td><td class="amount">&nbsp;</td></tr>
                            <tr class="total-row">
                                <td>Total Deductions</td>
                                <td class="amount" style="color: var(--danger);">₹<?= number_format($slip['total_deductions'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Net Disbursed Highlight Banner -->
            <div class="net-pay-banner">
                <div class="label-block">
                    <div class="title">Net Salary Disbursable</div>
                    <div class="words"><?= $netInWords ?></div>
                </div>
                <div class="amount-figure">
                    ₹<?= number_format($slip['net_salary'], 2) ?>
                </div>
            </div>

            <!-- Disbursal Details & Remarks -->
            <div class="disbursal-notes">
                <div>
                    <strong>Disbursement Timeline:</strong> 
                    <?= $slip['payment_date'] ? date('d F Y', strtotime($slip['payment_date'])) : 'Pending Processing' ?> 
                    via <?= ucwords(str_replace('_', ' ', $slip['payment_method'])) ?>
                </div>
                <div>
                    <strong>Audit / Remarks:</strong> 
                    <?= !empty($slip['remarks']) ? sanitize($slip['remarks']) : 'Regular monthly payroll processing' ?>
                </div>
            </div>

            <!-- Signatures -->
            <div class="signature-section">
                <div class="sign-block">
                    <div class="seal-stamp">
                        HRMS<br>VERIFIED
                    </div>
                    <div class="sign-line">Authorized Signatory<br><small style="color: var(--text-muted); font-weight: 400;">HR & Accounts Department</small></div>
                </div>
                <div class="sign-block">
                    <div style="height: 65px;"></div>
                    <div class="sign-line">Employee Signature<br><small style="color: var(--text-muted); font-weight: 400;">Acknowledged & Received</small></div>
                </div>
            </div>

            <!-- Disclaimer -->
            <div class="disclaimer">
                This document is a confidential computer-generated payroll advice generated by <?= APP_NAME ?>. 
                Any unauthorized alteration or dissemination is strictly prohibited under applicable company policies.
            </div>
        </div>
    </div>

</body>
</html>
