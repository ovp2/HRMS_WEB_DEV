<?php
/**
 * HRMS - Admin Leave Management
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Leave Management');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();

// Handle Status Updates (Approve, Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/leaves.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'update_status') {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        $new_status = in_array($_POST['status'] ?? '', ['approved', 'rejected']) ? $_POST['status'] : '';
        $admin_remarks = trim($_POST['admin_remarks'] ?? '');

        if ($leave_id <= 0 || empty($new_status)) {
            redirectWith(APP_URL . '/admin/leaves.php', 'Invalid request parameters.', 'danger');
        }

        // Fetch leave with employee and user info
        $leaveStmt = $pdo->prepare("SELECT l.*, e.user_id, e.first_name, e.last_name 
                                    FROM leaves l 
                                    JOIN employees e ON l.employee_id = e.id 
                                    WHERE l.id = ?");
        $leaveStmt->execute([$leave_id]);
        $leave = $leaveStmt->fetch();

        if (!$leave) {
            redirectWith(APP_URL . '/admin/leaves.php', 'Leave application not found.', 'danger');
        }

        try {
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("UPDATE leaves SET status = ?, approved_by = ?, admin_remarks = ? WHERE id = ?");
            $updateStmt->execute([$new_status, getCurrentUserId(), $admin_remarks, $leave_id]);

            // If approved, automatically record "on-leave" in attendance table for those dates
            if ($new_status === 'approved') {
                $start = new DateTime($leave['start_date']);
                $end = (new DateTime($leave['end_date']))->modify('+1 day');
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end);

                $attInsert = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, notes) 
                                            VALUES (?, ?, 'on-leave', ?) 
                                            ON DUPLICATE KEY UPDATE status = 'on-leave', notes = VALUES(notes)");
                $noteText = 'Approved ' . ucfirst($leave['leave_type']) . ' Leave';

                foreach ($period as $dt) {
                    $dateStr = $dt->format('Y-m-d');
                    // Skip weekends (Sunday: 0, Saturday: 6) if desired, or record all leave dates
                    $attInsert->execute([$leave['employee_id'], $dateStr, $noteText]);
                }
            }

            // Create notification for employee
            $notifType = ($new_status === 'approved') ? 'success' : 'danger';
            $msg = "Your " . ucfirst($leave['leave_type']) . " leave application (" . date('M j', strtotime($leave['start_date'])) . " to " . date('M j', strtotime($leave['end_date'])) . ") was " . strtoupper($new_status) . ".";
            if (!empty($admin_remarks)) {
                $msg .= " Remarks: " . $admin_remarks;
            }

            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $notifStmt->execute([
                $leave['user_id'],
                'Leave Application ' . ucfirst($new_status),
                $msg,
                $notifType,
                APP_URL . '/employee/leaves.php'
            ]);

            $pdo->commit();
            redirectWith(APP_URL . '/admin/leaves.php', "Leave request #$leave_id has been " . $new_status . " successfully.", 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirectWith(APP_URL . '/admin/leaves.php', 'Failed to update leave: ' . $e->getMessage(), 'danger');
        }
    }
}

// Filters
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "SELECT l.*, e.employee_id AS emp_code, e.first_name, e.last_name, e.designation,
                 d.name AS department_name,
                 u.username AS approver_name
          FROM leaves l
          JOIN employees e ON l.employee_id = e.id
          LEFT JOIN departments d ON e.department_id = d.id
          LEFT JOIN users u ON l.approved_by = u.id
          WHERE 1=1";
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'])) {
    $query .= " AND l.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== '') {
    $query .= " AND l.leave_type = ?";
    $params[] = $typeFilter;
}

if ($search !== '') {
    $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR l.reason LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY CASE WHEN l.status = 'pending' THEN 0 ELSE 1 END, l.applied_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'approved'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'rejected'")->fetchColumn();
$monthDaysTaken = (int)$pdo->query("SELECT COALESCE(SUM(total_days), 0) FROM leaves WHERE status = 'approved' AND MONTH(start_date) = MONTH(CURDATE()) AND YEAR(start_date) = YEAR(CURDATE())")->fetchColumn();
// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=leave_applications_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Application ID', 'Employee Code', 'Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Total Days', 'Reason', 'Status', 'Approved By', 'Remarks', 'Applied At']);
    foreach ($leaves as $row) {
        fputcsv($output, [
            $row['id'],
            $row['emp_code'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['department_name'] ?? 'General',
            ucfirst($row['leave_type']),
            $row['start_date'],
            $row['end_date'],
            $row['total_days'],
            $row['reason'],
            ucfirst($row['status']),
            $row['approver_name'] ?? '-',
            $row['admin_remarks'] ?? '-',
            $row['applied_at']
        ]);
    }
    fclose($output);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Overview -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Pending Approvals</h4>
            <div class="stat-number"><?= $pendingCount ?></div>
            <div class="stat-change <?= $pendingCount > 0 ? 'down' : 'up' ?>">
                <i class='bx bx-<?= $pendingCount > 0 ? 'bell' : 'check' ?>'></i> <?= $pendingCount > 0 ? 'Action required' : 'Up to date' ?>
            </div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-time'></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Approved Requests</h4>
            <div class="stat-number"><?= $approvedCount ?></div>
            <div class="stat-change up"><i class='bx bx-check-circle'></i> Granted</div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-check-shield'></i></div>
    </div>
    <div class="stat-card red">
        <div class="stat-info">
            <h4>Rejected Requests</h4>
            <div class="stat-number"><?= $rejectedCount ?></div>
            <div class="stat-change down"><i class='bx bx-x-circle'></i> Declined</div>
        </div>
        <div class="stat-icon red"><i class='bx bxs-x-circle'></i></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Days Taken (This Mo.)</h4>
            <div class="stat-number"><?= $monthDaysTaken ?></div>
            <div class="stat-change up"><i class='bx bx-calendar'></i> Total days</div>
        </div>
        <div class="stat-icon blue"><i class='bx bxs-calendar-event'></i></div>
    </div>
</div>

<!-- Filters Toolbar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <input type="text" name="search" class="form-control" style="max-width: 240px;" placeholder="Search employee or reason..." value="<?= sanitize($search) ?>">

            <select name="status" class="form-control" style="width: 150px;">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>

            <select name="type" class="form-control" style="width: 150px;">
                <option value="">All Leave Types</option>
                <option value="sick" <?= $typeFilter === 'sick' ? 'selected' : '' ?>>Sick Leave</option>
                <option value="casual" <?= $typeFilter === 'casual' ? 'selected' : '' ?>>Casual Leave</option>
                <option value="earned" <?= $typeFilter === 'earned' ? 'selected' : '' ?>>Earned Leave</option>
                <option value="maternity" <?= $typeFilter === 'maternity' ? 'selected' : '' ?>>Maternity</option>
                <option value="paternity" <?= $typeFilter === 'paternity' ? 'selected' : '' ?>>Paternity</option>
                <option value="unpaid" <?= $typeFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            </select>

            <button type="submit" class="btn btn-primary" style="padding: 0 16px;">
                <i class='bx bx-filter-alt'></i> Filter
            </button>
            <?php if ($statusFilter !== '' || $typeFilter !== '' || $search !== ''): ?>
            <a href="<?= APP_URL ?>/admin/leaves.php" class="btn btn-outline" title="Reset Filters">
                <i class='bx bx-reset'></i> Reset
            </a>
            <?php endif; ?>
        </form>

        <a href="?export=csv<?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline" title="Export leaves to CSV">
            <i class='bx bx-download'></i> Export CSV
        </a>
    </div>
</div>

<!-- Leave Requests Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Leave Applications</div>
        <span class="badge badge-secondary"><?= count($leaves) ?> Records</span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Duration & Days</th>
                    <th>Reason</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Approver / Remarks</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <i class='bx bx-calendar-check' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        No leave applications match the selected criteria.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($leaves as $l): 
                        $fullName = $l['first_name'] . ' ' . $l['last_name'];
                        $initials = strtoupper(substr($l['first_name'], 0, 1) . substr($l['last_name'], 0, 1));
                    ?>
                    <tr style="<?= $l['status'] === 'pending' ? 'background: rgba(245, 158, 11, 0.04);' : '' ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar-sm" style="width: 32px; height: 32px; font-size: 0.8rem; font-weight: 700;">
                                    <?= $initials ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);"><?= sanitize($fullName) ?></div>
                                    <small style="color: var(--text-muted);"><?= sanitize($l['emp_code']) ?> • <?= sanitize($l['department_name'] ?? 'General') ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info" style="text-transform: capitalize;">
                                <?= sanitize($l['leave_type']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 600; font-size: 0.9rem;">
                                <?= date('M j, Y', strtotime($l['start_date'])) ?>
                                <?php if ($l['start_date'] !== $l['end_date']): ?>
                                &mdash; <?= date('M j, Y', strtotime($l['end_date'])) ?>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-secondary" style="margin-top: 4px;">
                                <?= $l['total_days'] ?> <?= $l['total_days'] == 1 ? 'day' : 'days' ?>
                            </span>
                        </td>
                        <td style="max-width: 220px;">
                            <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">
                                <?= nl2br(sanitize($l['reason'])) ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= date('d M Y, h:i A', strtotime($l['applied_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($l['status'] === 'pending'): ?>
                            <span class="badge badge-warning"><i class='bx bx-time'></i> Pending</span>
                            <?php elseif ($l['status'] === 'approved'): ?>
                            <span class="badge badge-success"><i class='bx bx-check'></i> Approved</span>
                            <?php elseif ($l['status'] === 'rejected'): ?>
                            <span class="badge badge-danger"><i class='bx bx-x'></i> Rejected</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($l['admin_remarks'])): ?>
                            <div style="font-size: 0.85rem; color: var(--text-primary);">
                                "<?= sanitize($l['admin_remarks']) ?>"
                            </div>
                            <small style="color: var(--text-muted);">by <?= sanitize($l['approver_name'] ?? 'Admin') ?></small>
                            <?php elseif ($l['status'] === 'approved'): ?>
                            <small style="color: var(--text-muted);">Approved by <?= sanitize($l['approver_name'] ?? 'Admin') ?></small>
                            <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if ($l['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-success btn-sm" onclick='openReviewModal(<?= json_encode($l) ?>, "approved")' title="Approve Leave">
                                <i class='bx bx-check'></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick='openReviewModal(<?= json_encode($l) ?>, "rejected")' title="Reject Leave">
                                <i class='bx bx-x'></i> Reject
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick='openReviewModal(<?= json_encode($l) ?>, "<?= $l['status'] ?>")' title="Update Decision">
                                <i class='bx bx-edit'></i> Edit
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

<!-- Review Leave Modal -->
<div class="modal-overlay" id="reviewLeaveModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="reviewModalTitle">Review Leave Application</h3>
            <button class="modal-close" onclick="closeModal('reviewLeaveModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="leave_id" id="modalLeaveId">

                <div style="background: var(--bg-hover); padding: 14px 18px; border-radius: var(--border-radius); margin-bottom: 20px;">
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem;" id="modalEmpName"></div>
                    <div style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 4px;" id="modalLeaveSummary"></div>
                    <div style="margin-top: 8px; font-style: italic; color: var(--text-secondary); font-size: 0.85rem;" id="modalLeaveReason"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Decision <span class="required">*</span></label>
                    <select name="status" id="modalDecision" class="form-control" required>
                        <option value="approved">Approve Application</option>
                        <option value="rejected">Reject Application</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Administrator Remarks</label>
                    <textarea name="admin_remarks" id="modalAdminRemarks" class="form-control" rows="3" placeholder="Provide notes or justification for the employee..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reviewLeaveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                    <i class='bx bx-check'></i> Confirm Decision
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openReviewModal(leave, targetStatus) {
    document.getElementById('modalLeaveId').value = leave.id;
    document.getElementById('modalEmpName').textContent = leave.first_name + ' ' + leave.last_name + ' (' + leave.emp_code + ')';
    document.getElementById('modalLeaveSummary').innerHTML = '<strong>' + (leave.leave_type.toUpperCase()) + ' LEAVE</strong>: ' + leave.start_date + ' to ' + leave.end_date + ' (' + leave.total_days + ' day/s)';
    document.getElementById('modalLeaveReason').textContent = '"' + leave.reason + '"';
    
    const decisionSelect = document.getElementById('modalDecision');
    decisionSelect.value = (targetStatus === 'rejected') ? 'rejected' : 'approved';
    
    document.getElementById('modalAdminRemarks').value = leave.admin_remarks || '';
    
    openModal('reviewLeaveModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
