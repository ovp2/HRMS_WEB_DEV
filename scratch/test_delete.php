<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();

$baseUrl = 'http://localhost/hrms';
$cookieFile = __DIR__ . '/cookie_admin_del.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpReq($url, $method = 'GET', $data = [], $cookie = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['code' => $info['http_code'], 'url' => $info['url'], 'body' => $res];
}

function extractCsrf($html) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) return $m[1];
    if (preg_match('/value="([^"]+)"\s+name="csrf_token"/', $html, $m)) return $m[1];
    return '';
}

// 1. Admin login
$loginGet = httpReq("$baseUrl/login.php", 'GET', [], $cookieFile);
$csrf = extractCsrf($loginGet['body']);
httpReq("$baseUrl/login.php", 'POST', [
    'csrf_token' => $csrf,
    'username' => 'admin',
    'password' => 'admin123'
], $cookieFile);

// 2. Add temporary employee
$empPage = httpReq("$baseUrl/admin/employees.php", 'GET', [], $cookieFile);
$empCsrf = extractCsrf($empPage['body']);

httpReq("$baseUrl/admin/employees.php", 'POST', [
    'csrf_token' => $empCsrf,
    'action' => 'create',
    'first_name' => 'TempDelete',
    'last_name' => 'Tester',
    'email' => 'temp.delete@example.com',
    'designation' => 'QA Temp',
    'salary' => '10000',
    'status' => 'active'
], $cookieFile);

$tempEmp = $pdo->query("SELECT id, user_id FROM employees WHERE email = 'temp.delete@example.com'")->fetch();
if (!$tempEmp) {
    echo "Failed to create temp employee for delete test.\n";
    exit(1);
}
echo "Created temp employee ID: " . $tempEmp['id'] . ", User ID: " . $tempEmp['user_id'] . "\n";

// 3. Delete temporary employee
$empPage2 = httpReq("$baseUrl/admin/employees.php", 'GET', [], $cookieFile);
$delCsrf = extractCsrf($empPage2['body']);

$delRes = httpReq("$baseUrl/admin/employees.php", 'POST', [
    'csrf_token' => $delCsrf,
    'action' => 'delete',
    'id' => $tempEmp['id'],
    'user_id' => $tempEmp['user_id']
], $cookieFile);

// 4. Verify in DB
$checkEmp = $pdo->query("SELECT id FROM employees WHERE id = " . $tempEmp['id'])->fetch();
$checkUser = $pdo->query("SELECT id FROM users WHERE id = " . $tempEmp['user_id'])->fetch();

if (!$checkEmp && !$checkUser) {
    echo "DELETE TEST PASSED: Both employee record and user account removed from MySQL successfully.\n";
} else {
    echo "DELETE TEST FAILED!\n";
    exit(1);
}

if (file_exists($cookieFile)) unlink($cookieFile);
