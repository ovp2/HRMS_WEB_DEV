<?php
/**
 * HRMS - Global Quick Search AJAX Endpoint
 * Returns live matching entities and shortcuts for navbar search
 */

require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['results' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$pdo = getDBConnection();
$results = [];
$role = $_SESSION['role'] ?? 'employee';

if ($role === 'admin') {
    // 1. Search Employees
    $empStmt = $pdo->prepare("SELECT e.id, e.employee_id, e.first_name, e.last_name, e.designation, d.name AS department_name 
                              FROM employees e 
                              LEFT JOIN departments d ON e.department_id = d.id 
                              WHERE e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR e.designation LIKE ?
                              LIMIT 4");
    $param = "%$q%";
    $empStmt->execute([$param, $param, $param, $param]);
    while ($emp = $empStmt->fetch()) {
        $results[] = [
            'type' => 'Employee',
            'title' => $emp['first_name'] . ' ' . $emp['last_name'],
            'subtitle' => $emp['employee_id'] . ' • ' . ($emp['designation'] ?: 'Staff') . ' (' . ($emp['department_name'] ?: 'General') . ')',
            'icon' => 'bx-user',
            'link' => APP_URL . '/admin/employees.php?search=' . urlencode($emp['employee_id'])
        ];
    }

    // 2. Search Departments
    $deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE name LIKE ? LIMIT 2");
    $deptStmt->execute([$param]);
    while ($dept = $deptStmt->fetch()) {
        $results[] = [
            'type' => 'Department',
            'title' => $dept['name'],
            'subtitle' => 'Department Management',
            'icon' => 'bx-buildings',
            'link' => APP_URL . '/admin/departments.php'
        ];
    }

    // 3. Navigation Shortcuts
    $shortcuts = [
        ['title' => 'Attendance Management', 'subtitle' => 'Daily check-in/out records', 'icon' => 'bx-calendar-check', 'link' => APP_URL . '/admin/attendance.php'],
        ['title' => 'Leave Management', 'subtitle' => 'Approve or reject leave requests', 'icon' => 'bx-calendar-minus', 'link' => APP_URL . '/admin/leaves.php'],
        ['title' => 'Payroll Management', 'subtitle' => 'Salary generation and payslips', 'icon' => 'bx-wallet', 'link' => APP_URL . '/admin/payroll.php'],
        ['title' => 'Reports & Analytics', 'subtitle' => 'Workforce charts and stats', 'icon' => 'bx-bar-chart-alt-2', 'link' => APP_URL . '/admin/reports.php'],
        ['title' => 'System Settings', 'subtitle' => 'Company profile and preferences', 'icon' => 'bx-cog', 'link' => APP_URL . '/admin/settings.php'],
    ];

    foreach ($shortcuts as $sc) {
        if (stripos($sc['title'], $q) !== false || stripos($sc['subtitle'], $q) !== false) {
            $results[] = array_merge(['type' => 'Quick Action'], $sc);
        }
    }
} else {
    // Employee Shortcuts
    $empShortcuts = [
        ['title' => 'Punch Attendance', 'subtitle' => 'Check in / Check out today', 'icon' => 'bx-time-five', 'link' => APP_URL . '/employee/attendance.php'],
        ['title' => 'Apply for Leave', 'subtitle' => 'Submit casual or sick leave', 'icon' => 'bx-paper-plane', 'link' => APP_URL . '/employee/leaves.php'],
        ['title' => 'My Salary Slips', 'subtitle' => 'View and print payslips', 'icon' => 'bx-wallet', 'link' => APP_URL . '/employee/payroll.php'],
        ['title' => 'My Profile & Details', 'subtitle' => 'Update contact information', 'icon' => 'bx-user', 'link' => APP_URL . '/employee/profile.php'],
        ['title' => 'Notifications', 'subtitle' => 'Alerts and announcements', 'icon' => 'bx-bell', 'link' => APP_URL . '/employee/notifications.php'],
    ];

    foreach ($empShortcuts as $sc) {
        if (stripos($sc['title'], $q) !== false || stripos($sc['subtitle'], $q) !== false) {
            $results[] = array_merge(['type' => 'Quick Action'], $sc);
        }
    }
}

echo json_encode(['results' => $results]);
