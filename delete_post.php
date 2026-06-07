<?php
require_once 'security.php';
initSecurityProtection();

session_start();
header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'CSRF token 验证失败']);
    exit;
}

if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    echo json_encode(['success' => false, 'error' => '未认证']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['filename'])) {
    echo json_encode(['success' => false, 'error' => '无效的请求']);
    exit;
}

$filename = basename($_POST['filename']);

if (!preg_match('/^[a-zA-Z0-9_-]+\.html$/', $filename)) {
    echo json_encode(['success' => false, 'error' => '无效的文件名格式']);
    exit;
}

$filepath = 'posts/' . $filename;

$realFilePath = realpath($filepath);
$realPostsDir = realpath('posts');

if ($realFilePath === false || strpos($realFilePath, $realPostsDir) !== 0) {
    echo json_encode(['success' => false, 'error' => '无效的文件路径']);
    exit;
}

if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'error' => '文件不存在']);
    exit;
}

if (unlink($filepath)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => '删除文件失败']);
}
?>
