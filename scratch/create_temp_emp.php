<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();
$hash = password_hash('Pass@123', PASSWORD_DEFAULT);
$pdo->query("INSERT INTO users (username, password, email, role, status) VALUES ('browsertest', '$hash', 'browsertest@example.com', 'employee', 'active')");
$uid = $pdo->lastInsertId();
$pdo->query("INSERT INTO employees (user_id, employee_id, first_name, last_name, email, phone, designation, salary, status) VALUES ($uid, 'EMP999', 'Browser', 'DeleteTest', 'browsertest@example.com', '+91 99999 88888', 'QA Automation', 50000, 'active')");
$empId = $pdo->lastInsertId();

// Add related test attendance, leave, and payroll to verify safe cleanup
$pdo->query("INSERT INTO attendance (employee_id, date, status, check_in, check_out, work_hours) VALUES ($empId, CURDATE(), 'present', '09:00:00', '18:00:00', 9)");
$pdo->query("INSERT INTO leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status) VALUES ($empId, 'casual', CURDATE(), CURDATE(), 1, 'Test Leave', 'pending')");
$pdo->query("INSERT INTO payroll (employee_id, month, year, basic_salary, gross_salary, net_salary) VALUES ($empId, 9, 2026, 30000, 50000, 45000)");

echo "Created test employee: id=$empId, user_id=$uid, name='Browser DeleteTest' with attendance, leave, and payroll records.\n";
