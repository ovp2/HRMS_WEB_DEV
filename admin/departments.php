<?php
/**
 * HRMS - Admin Department Management
 * Phase 4: Full Department Lifecycle, Head Allocation, Workforce Breakdown, and Dashboard Integration
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Departments');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/departments.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $head_id = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            redirectWith(APP_URL . '/admin/departments.php', 'Department name is required.', 'danger');
        }

        // Check if name exists
        $check = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            redirectWith(APP_URL . '/admin/departments.php', 'Department with this name already exists.', 'warning');
        }

        $stmt = $pdo->prepare("INSERT INTO departments (name, description, head_id, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $head_id, $status]);

        redirectWith(APP_URL . '/admin/departments.php', 'Department created successfully!', 'success');
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $head_id = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($id <= 0 || empty($name)) {
            redirectWith(APP_URL . '/admin/departments.php', 'Department ID and name are required.', 'danger');
        }

        // Check name uniqueness excluding self
        $check = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
        $check->execute([$name, $id]);
        if ($check->fetch()) {
            redirectWith(APP_URL . '/admin/departments.php', 'Another department with this name already exists.', 'warning');
        }

        $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, head_id = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $description, $head_id, $status, $id]);

        redirectWith(APP_URL . '/admin/departments.php', 'Department updated successfully!', 'success');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            redirectWith(APP_URL . '/admin/departments.php', 'Invalid department ID.', 'danger');
        }

        // Check employee association
        $checkEmp = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
        $checkEmp->execute([$id]);
        $empCount = $checkEmp->fetchColumn();

        if ($empCount > 0) {
            redirectWith(APP_URL . '/admin/departments.php', "Cannot delete department: $empCount employee(s) are assigned to it. Reassign them first or set status to inactive.", 'warning');
        }

        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$id]);

        redirectWith(APP_URL . '/admin/departments.php', 'Department deleted successfully!', 'success');
    }
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$query = "SELECT d.*, 
                 CONCAT(e.first_name, ' ', e.last_name) AS head_name,
                 e.employee_id AS head_code,
                 e.email AS head_email,
                 COUNT(emp.id) AS employee_count,
                 COALESCE(SUM(emp.salary), 0) AS total_monthly_salary
          FROM departments d
          LEFT JOIN employees e ON d.head_id = e.id
          LEFT JOIN employees emp ON d.id = emp.department_id AND emp.status = 'active'
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive'])) {
    $query .= " AND d.status = ?";
    $params[] = $statusFilter;
}

$query .= " GROUP BY d.id, d.name, d.description, d.head_id, d.status, d.created_at, d.updated_at, head_name, head_code, head_email
            ORDER BY d.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$departments = $stmt->fetchAll();

// Statistics
$totalDepts = count($departments);
$activeDepts = 0;
$totalAssignedEmployees = 0;
$totalWorkforceBudget = 0;
foreach ($departments as $d) {
    if ($d['status'] === 'active') $activeDepts++;
    $totalAssignedEmployees += (int)$d['employee_count'];
    $totalWorkforceBudget += (float)$d['total_monthly_salary'];
}

// Fetch all active employees for Head of Department dropdown
$empList = $pdo->query("SELECT id, employee_id, first_name, last_name, designation FROM employees WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar & Stats -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Total Departments</h4>
            <div class="stat-number"><?= $totalDepts ?></div>
            <div class="stat-change up"><i class='bx bx-buildings'></i> Registered</div>
        </div>
        <div class="stat-icon blue"><i class='bx bxs-buildings'></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Active Units</h4>
            <div class="stat-number"><?= $activeDepts ?></div>
            <div class="stat-change up"><i class='bx bx-check-circle'></i> In Operation</div>
        </div>
        <div class="stat-icon green"><i class='bx bx-check-shield'></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Workforce Assigned</h4>
            <div class="stat-number"><?= $totalAssignedEmployees ?></div>
            <div class="stat-change up"><i class='bx bx-user'></i> Active Staff</div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-user-pin'></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Monthly Payroll</h4>
            <div class="stat-number" style="font-size: 1.4rem;">₹<?= number_format($totalWorkforceBudget, 0) ?></div>
            <div class="stat-change up"><i class='bx bx-wallet'></i> Budget</div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-wallet'></i></div>
    </div>
</div>

<!-- Header Controls -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center;">
            <form method="GET" action="" style="display: flex; gap: 12px; flex: 1; min-width: 280px; max-width: 600px;">
                <input type="text" name="search" class="form-control" placeholder="Search department name or description..." value="<?= sanitize($search) ?>">
                <select name="status" class="form-control" style="width: 140px;">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-primary" style="padding: 0 16px;">
                    <i class='bx bx-filter-alt'></i> Filter
                </button>
                <?php if ($search !== '' || $statusFilter !== ''): ?>
                <a href="<?= APP_URL ?>/admin/departments.php" class="btn btn-outline" title="Clear filters">
                    <i class='bx bx-reset'></i> Reset
                </a>
                <?php endif; ?>
            </form>

            <button type="button" class="btn btn-primary" onclick="openModal('addDeptModal')">
                <i class='bx bx-plus'></i> Add New Department
            </button>
        </div>
    </div>
</div>

<!-- Departments Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Department Directory</div>
        <span class="badge badge-secondary"><?= count($departments) ?> Found</span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Department Name</th>
                    <th>Description</th>
                    <th>Head of Dept</th>
                    <th>Workforce</th>
                    <th>Monthly Payroll</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class='bx bx-folder-open' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        No departments found matching your criteria.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($departments as $idx => $d): ?>
                    <tr>
                        <td><strong>#<?= $d['id'] ?></strong></td>
                        <td>
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">
                                <?= sanitize($d['name']) ?>
                            </div>
                        </td>
                        <td style="max-width: 280px; color: var(--text-secondary); font-size: 0.85rem;">
                            <?= !empty($d['description']) ? sanitize($d['description']) : '<span style="color:var(--text-muted);font-style:italic;">No description provided</span>' ?>
                        </td>
                        <td>
                            <?php if (!empty($d['head_name'])): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="user-avatar-sm" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                    <?= strtoupper(substr($d['head_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500; font-size: 0.85rem;"><?= sanitize($d['head_name']) ?></div>
                                    <small style="color: var(--text-muted);"><?= sanitize($d['head_code']) ?></small>
                                </div>
                            </div>
                            <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Not Assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= APP_URL ?>/admin/employees.php?department_id=<?= $d['id'] ?>" class="badge badge-info" style="font-size: 0.8rem; text-decoration: none;" title="Click to view employees in this department">
                                <i class='bx bx-user'></i> <?= $d['employee_count'] ?> Active
                            </a>
                        </td>
                        <td>
                            <strong>₹<?= number_format($d['total_monthly_salary'], 0) ?></strong>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'active'): ?>
                            <span class="badge badge-success">Active</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button type="button" class="btn btn-outline btn-sm" onclick='viewDepartment(<?= json_encode($d) ?>)' title="View Department Info">
                                <i class='bx bx-show'></i>
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick='editDepartment(<?= json_encode($d) ?>)' title="Edit Department">
                                <i class='bx bx-edit-alt'></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $d['id'] ?>, '<?= addslashes(sanitize($d['name'])) ?>', <?= $d['employee_count'] ?>)" title="Delete Department">
                                <i class='bx bx-trash'></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Department Modal -->
<div class="modal-overlay" id="viewDeptModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="viewDeptTitle">Department Overview</h3>
            <button class="modal-close" onclick="closeModal('viewDeptModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body" id="viewDeptBody">
            <!-- Dynamically populated via JS -->
        </div>
        <div class="modal-footer">
            <a href="#" id="viewDeptEmpLink" class="btn btn-primary"><i class='bx bx-user'></i> View All Staff</a>
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewDeptModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal-overlay" id="addDeptModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add New Department</h3>
            <button class="modal-close" onclick="closeModal('addDeptModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label class="form-label">Department Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Quality Assurance">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief outline of departmental responsibilities..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Department Head (Optional)</label>
                    <select name="head_id" class="form-control">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($empList as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= sanitize($emp['employee_id']) ?> - <?= sanitize($emp['designation']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-check'></i> Create Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal-overlay" id="editDeptModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Department</h3>
            <button class="modal-close" onclick="closeModal('editDeptModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editDeptId">

                <div class="form-group">
                    <label class="form-label">Department Name <span class="required">*</span></label>
                    <input type="text" name="name" id="editDeptName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editDeptDescription" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Department Head (Optional)</label>
                    <select name="head_id" id="editDeptHeadId" class="form-control">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($empList as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= sanitize($emp['employee_id']) ?> - <?= sanitize($emp['designation']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="editDeptStatus" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteDeptForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteDeptId">
</form>

<script>
function viewDepartment(dept) {
    document.getElementById('viewDeptTitle').textContent = dept.name;
    const body = document.getElementById('viewDeptBody');
    body.innerHTML = `
        <div style="background: var(--bg-hover); padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px;">
            <p style="margin: 0; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.5;">
                ${escapeHtml(dept.description || 'No description entered for this department.')}
            </p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Head of Department</label>
                <div style="font-weight: 600; margin-top: 4px;">${escapeHtml(dept.head_name || 'Not assigned')}</div>
                ${dept.head_code ? '<small style="color:var(--text-muted);">' + escapeHtml(dept.head_code) + '</small>' : ''}
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Status</label>
                <div style="margin-top: 4px;"><span class="badge ${dept.status === 'active' ? 'badge-success' : 'badge-danger'}">${escapeHtml(dept.status)}</span></div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Assigned Workforce</label>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary-600); margin-top: 4px;">${dept.employee_count} staff</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Monthly Payroll Commitment</label>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--success); margin-top: 4px;">₹${parseFloat(dept.total_monthly_salary).toLocaleString('en-IN', {minimumFractionDigits: 0})}</div>
            </div>
        </div>
    `;
    document.getElementById('viewDeptEmpLink').href = '<?= APP_URL ?>/admin/employees.php?department_id=' + dept.id;
    openModal('viewDeptModal');
}

function editDepartment(dept) {
    document.getElementById('editDeptId').value = dept.id;
    document.getElementById('editDeptName').value = dept.name;
    document.getElementById('editDeptDescription').value = dept.description || '';
    document.getElementById('editDeptHeadId').value = dept.head_id || '';
    document.getElementById('editDeptStatus').value = dept.status;
    openModal('editDeptModal');
}

function confirmDelete(id, name, employeeCount) {
    if (employeeCount > 0) {
        alert('Cannot delete "' + name + '" because it currently has ' + employeeCount + ' active employee(s) assigned. Reassign them first.');
        return;
    }
    if (confirm('Are you sure you want to delete the department "' + name + '"? This action cannot be undone.')) {
        document.getElementById('deleteDeptId').value = id;
        document.getElementById('deleteDeptForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
