<?php
/**
 * Test deleting Browser DeleteTest (EMP999, id=5) via POST to admin/employees.php
 */
require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();

$baseUrl = 'http://localhost/hrms';
$cookieFile = __DIR__ . '/cookie_del_test.txt';
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
echo "[1] Logging in as Admin...\n";
$loginGet = httpReq("$baseUrl/login.php", 'GET', [], $cookieFile);
$csrf = extractCsrf($loginGet['body']);
$loginPost = httpReq("$baseUrl/login.php", 'POST', [
    'csrf_token' => $csrf,
    'username' => 'admin',
    'password' => 'admin123'
], $cookieFile);

// 2. Locate employee EMP999
echo "[2] Locating EMP999 in database...\n";
$emp = $pdo->query("SELECT * FROM employees WHERE employee_id = 'EMP999'")->fetch();
if (!$emp) {
    echo "  [FAIL] EMP999 not found in DB!\n";
    exit(1);
}
echo "  Found EMP999: ID=" . $emp['id'] . ", User ID=" . $emp['user_id'] . "\n";

// 3. Check related records before delete
$attCount = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE employee_id = " . $emp['id'])->fetchColumn();
$leaveCount = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE employee_id = " . $emp['id'])->fetchColumn();
$payCount = (int)$pdo->query("SELECT COUNT(*) FROM payroll WHERE employee_id = " . $emp['id'])->fetchColumn();
echo "  Related records before delete: Attendance=$attCount, Leaves=$leaveCount, Payroll=$payCount\n";

// 4. Send Delete POST to admin/employees.php
echo "[3] Sending Delete POST request to $baseUrl/admin/employees.php...\n";
$empPage = httpReq("$baseUrl/admin/employees.php", 'GET', [], $cookieFile);
$delCsrf = extractCsrf($empPage['body']);

$delRes = httpReq("$baseUrl/admin/employees.php", 'POST', [
    'csrf_token' => $delCsrf,
    'action' => 'delete',
    'id' => $emp['id'],
    'user_id' => $emp['user_id']
], $cookieFile);

echo "  Response HTTP Code: " . $delRes['code'] . "\n";
echo "  Final URL: " . $delRes['url'] . "\n";

// 5. Verify no 404 and success message present
if ($delRes['code'] === 200 && strpos($delRes['url'], '404') === false) {
    echo "  [PASS] No 404 error! Redirected successfully.\n";
} else {
    echo "  [FAIL] Got 404 or bad status: " . $delRes['code'] . "\n";
    exit(1);
}

if (strpos($delRes['body'], 'Employee deleted successfully') !== false) {
    echo "  [PASS] Flash message 'Employee deleted successfully.' displayed.\n";
} else {
    echo "  [FAIL] Expected success flash message not found in HTML response!\n";
}

// 6. Verify records deleted from MySQL
$checkEmp = $pdo->query("SELECT id FROM employees WHERE id = " . $emp['id'])->fetch();
$checkUser = $pdo->query("SELECT id FROM users WHERE id = " . $emp['user_id'])->fetch();
$checkAtt = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE employee_id = " . $emp['id'])->fetchColumn();
$checkLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE employee_id = " . $emp['id'])->fetchColumn();
$checkPay = (int)$pdo->query("SELECT COUNT(*) FROM payroll WHERE employee_id = " . $emp['id'])->fetchColumn();

if (!$checkEmp && !$checkUser && $checkAtt === 0 && $checkLeaves === 0 && $checkPay === 0) {
    echo "  [PASS] All records (Employee, User, Attendance, Leaves, Payroll) safely cleaned up from MySQL!\n";
} else {
    echo "  [FAIL] Orphaned records remain in MySQL!\n";
    exit(1);
}

// 7. Verify table view on web no longer contains EMP999
if (strpos($delRes['body'], 'Browser DeleteTest') === false && strpos($delRes['body'], 'EMP999') === false) {
    echo "  [PASS] EMP999 is completely removed from the Employees table web view.\n";
} else {
    echo "  [FAIL] EMP999 still visible in table HTML!\n";
}

if (file_exists($cookieFile)) unlink($cookieFile);
echo "\nALL DELETE VERIFICATIONS COMPLETED SUCCESSFULLY!\n";
