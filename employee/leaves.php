<?php
/**
 * HRMS - Employee Leave Management
 * Apply for leave, view balances, and check status
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'Leave Management');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();
$empId = getCurrentEmployeeId();
$userId = getCurrentUserId();

// Handle Apply for Leave & Cancel Leave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/employee/leaves.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'apply') {
        $leave_type = in_array($_POST['leave_type'] ?? '', ['sick', 'casual', 'earned', 'maternity', 'paternity', 'unpaid']) ? $_POST['leave_type'] : '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
            redirectWith(APP_URL . '/employee/leaves.php', 'Please fill in all required fields.', 'danger');
        }

        if (strtotime($end_date) < strtotime($start_date)) {
            redirectWith(APP_URL . '/employee/leaves.php', 'End date cannot be earlier than start date.', 'danger');
        }

        // Calculate total days
        $d1 = new DateTime($start_date);
        $d2 = new DateTime($end_date);
        $totalDays = $d2->diff($d1)->days + 1;

        // Check for overlapping active applications
        $overlap = $pdo->prepare("SELECT id FROM leaves 
                                  WHERE employee_id = ? 
                                  AND status IN ('pending', 'approved') 
                                  AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date))");
        $overlap->execute([$empId, $start_date, $end_date, $start_date, $end_date, $start_date]);
        if ($overlap->fetch()) {
            redirectWith(APP_URL . '/employee/leaves.php', 'You already have a pending or approved leave overlapping these dates.', 'warning');
        }

        try {
            $pdo->beginTransaction();

            $insert = $pdo->prepare("INSERT INTO leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status) 
                                     VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $insert->execute([$empId, $leave_type, $start_date, $end_date, $totalDays, $reason]);
            $newLeaveId = (int)$pdo->lastInsertId();

            // Notify all admins of the new leave request
            $empName = $_SESSION['full_name'] ?? 'Employee';
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);

            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', ?)");
            foreach ($admins as $adminUserId) {
                $notif->execute([
                    $adminUserId,
                    'New Leave Application',
                    "$empName applied for $totalDays day(s) of " . ucfirst($leave_type) . " leave.",
                    APP_URL . '/admin/leaves.php'
                ]);
            }

            $pdo->commit();
            redirectWith(APP_URL . '/employee/leaves.php', 'Leave application submitted successfully! Your manager will review it.', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirectWith(APP_URL . '/employee/leaves.php', 'Failed to submit application: ' . $e->getMessage(), 'danger');
        }
    }

    if ($action === 'cancel') {
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM leaves WHERE id = ? AND employee_id = ? AND status = 'pending'");
        $check->execute([$leaveId, $empId]);
        if ($check->fetch()) {
            $update = $pdo->prepare("UPDATE leaves SET status = 'cancelled' WHERE id = ?");
            $update->execute([$leaveId]);
            redirectWith(APP_URL . '/employee/leaves.php', 'Leave application cancelled.', 'info');
        } else {
            redirectWith(APP_URL . '/employee/leaves.php', 'Only pending leave applications can be cancelled.', 'danger');
        }
    }
}

// Fetch Leave Balances & Usages this year
$currentYear = date('Y');
$usedLeavesStmt = $pdo->prepare("SELECT leave_type, SUM(total_days) as used_days 
                                 FROM leaves 
                                 WHERE employee_id = ? AND status = 'approved' AND YEAR(start_date) = ?
                                 GROUP BY leave_type");
$usedLeavesStmt->execute([$empId, $currentYear]);
$usedMap = [];
while ($r = $usedLeavesStmt->fetch()) {
    $usedMap[$r['leave_type']] = (int)$r['used_days'];
}

$quotas = [
    'casual' => 12,
    'sick' => 12,
    'earned' => 15,
];

// Fetch Employee Leave History
$historyStmt = $pdo->prepare("SELECT l.*, u.username as approver_name 
                              FROM leaves l 
                              LEFT JOIN users u ON l.approved_by = u.id 
                              WHERE l.employee_id = ? 
                              ORDER BY l.applied_at DESC");
$historyStmt->execute([$empId]);
$leaveHistory = $historyStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Leave Balances Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <?php foreach ($quotas as $type => $total): 
        $used = $usedMap[$type] ?? 0;
        $remaining = max(0, $total - $used);
    ?>
    <div class="card" style="padding: 20px; border-left: 4px solid var(--primary-500);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">
                <?= ucfirst($type) ?> Leave
            </div>
            <span class="badge badge-info" style="font-size: 0.8rem;"><?= $total ?> Quota</span>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary-600); margin: 8px 0 4px 0;">
            <?= $remaining ?> <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">left</span>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted);">
            <?= $used ?> days utilized in <?= $currentYear ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="card" style="padding: 20px; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, var(--primary-50), var(--primary-100)); border: 1px dashed var(--primary-400);">
        <button type="button" class="btn btn-primary" onclick="openModal('applyLeaveModal')" style="width: 100%; padding: 12px; font-weight: 700;">
            <i class='bx bx-plus-circle'></i> Apply for Leave
        </button>
        <small style="margin-top: 8px; color: var(--text-secondary); font-size: 0.8rem;">Quick instant approval submission</small>
    </div>
</div>

<!-- Leave Application History Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="card-title">My Leave History</div>
        <span class="badge badge-secondary"><?= count($leaveHistory) ?> Applications</span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Date Range</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Applied On</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveHistory)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <i class='bx bx-calendar' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        You haven't submitted any leave applications yet.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($leaveHistory as $l): ?>
                    <tr>
                        <td>
                            <span class="badge badge-info" style="text-transform: capitalize;">
                                <?= sanitize($l['leave_type']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= date('d M Y', strtotime($l['start_date'])) ?></strong>
                            <?php if ($l['start_date'] !== $l['end_date']): ?>
                            &mdash; <strong><?= date('d M Y', strtotime($l['end_date'])) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $l['total_days'] ?></strong> <?= $l['total_days'] == 1 ? 'day' : 'days' ?></td>
                        <td style="max-width: 220px; font-size: 0.85rem; color: var(--text-secondary);">
                            <?= nl2br(sanitize($l['reason'])) ?>
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            <?= date('d M Y, h:i A', strtotime($l['applied_at'])) ?>
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
                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?= sanitize($l['admin_remarks'] ?? '-') ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($l['status'] === 'pending'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Cancel this leave request?')" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger);">
                                    Cancel
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Apply for Leave Modal -->
<div class="modal-overlay" id="applyLeaveModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Apply for Leave</h3>
            <button class="modal-close" onclick="closeModal('applyLeaveModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="" id="applyLeaveForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="apply">

                <div class="form-group">
                    <label class="form-label">Leave Category <span class="required">*</span></label>
                    <select name="leave_type" id="applyLeaveType" class="form-control" required>
                        <option value="sick">Sick Leave</option>
                        <option value="casual">Casual Leave</option>
                        <option value="earned">Earned Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="paternity">Paternity Leave</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" id="applyStartDate" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" onchange="calcDays()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">End Date <span class="required">*</span></label>
                        <input type="date" name="end_date" id="applyEndDate" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" onchange="calcDays()">
                    </div>
                </div>

                <div style="background: var(--bg-hover); padding: 10px 14px; border-radius: var(--border-radius); margin-bottom: 16px; font-size: 0.9rem;">
                    Total Leave Duration: <strong id="calculatedDaysText">1 day</strong>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason for Leave <span class="required">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Specify why you need this leave..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('applyLeaveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-send'></i> Submit Application</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcDays() {
    const s = document.getElementById('applyStartDate').value;
    const e = document.getElementById('applyEndDate').value;
    if (s && e) {
        const d1 = new Date(s);
        const d2 = new Date(e);
        if (d2 >= d1) {
            const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('calculatedDaysText').textContent = diff + (diff === 1 ? ' day' : ' days');
        } else {
            document.getElementById('calculatedDaysText').textContent = 'Invalid date range';
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
