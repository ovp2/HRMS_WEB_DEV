<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();
echo "Users: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . PHP_EOL;
echo "Employees: " . $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn() . PHP_EOL;
echo "Departments: " . $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn() . PHP_EOL;
echo "Admin user: " . $pdo->query("SELECT username FROM users WHERE role='admin'")->fetchColumn() . PHP_EOL;
