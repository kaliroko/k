<?php
// 评论系统处理脚本
require_once 'security.php';
initSecurityProtection();

header('Content-Type: application/json');

// 数据库文件路径
$dbFile = 'comments/comments.db';

// 创建评论目录和数据库
if (!file_exists('comments')) {
    mkdir('comments', 0755, true);
}

// 初始化SQLite数据库
function initDatabase() {
    global $dbFile;
    
    $db = new SQLite3($dbFile);
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_file TEXT NOT NULL,
        author TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT
    )");
    
    // 创建文章查看记录表
    $db->exec("CREATE TABLE IF NOT EXISTS post_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_file TEXT NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    return $db;
}

// 获取文章评论
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_comments') {
    if (!isset($_GET['post_file'])) {
        echo json_encode(['error' => '缺少文章文件名']);
        exit;
    }
    
    $postFile = $_GET['post_file'];
    $db = initDatabase();
    
    $stmt = $db->prepare("SELECT author, content, created_at FROM comments WHERE post_file = ? ORDER BY created_at DESC");
    $stmt->bindValue(1, $postFile, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $comments = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $comments[] = $row;
    }
    
    echo json_encode(['success' => true, 'comments' => $comments]);
}

// 添加评论
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (!isset($_POST['post_file']) || !isset($_POST['author']) || !isset($_POST['content'])) {
        echo json_encode(['error' => '缺少必要参数']);
        exit;
    }
    
    $postFile = $_POST['post_file'];
    $author = trim($_POST['author']);
    $content = trim($_POST['content']);
    
    if (empty($author) || empty($content)) {
        echo json_encode(['error' => '作者和内容不能为空']);
        exit;
    }
    
    // 限制评论长度
    if (strlen($author) > 50 || strlen($content) > 500) {
        echo json_encode(['error' => '作者名或评论内容过长']);
        exit;
    }
    
    $db = initDatabase();
    
    // 检查是否短时间内重复提交
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM comments WHERE ip_address = ? AND created_at > datetime('now', '-1 minute')");
    $stmt->bindValue(1, $ip, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row['count'] > 0) {
        echo json_encode(['error' => '评论太频繁，请稍后再试']);
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO comments (post_file, author, content, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bindValue(1, $postFile, SQLITE3_TEXT);
    $stmt->bindValue(2, htmlspecialchars($author), SQLITE3_TEXT);
    $stmt->bindValue(3, htmlspecialchars($content), SQLITE3_TEXT);
    $stmt->bindValue(4, $ip, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '评论发表成功']);
    } else {
        echo json_encode(['error' => '评论发表失败']);
    }
}

// 记录文章查看
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_view') {
    if (!isset($_POST['post_file'])) {
        echo json_encode(['error' => '缺少文章文件名']);
        exit;
    }
    
    $postFile = $_POST['post_file'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $db = initDatabase();
    
    // 检查同一IP在5分钟内是否已经记录过
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM post_views WHERE post_file = ? AND ip_address = ? AND viewed_at > datetime('now', '-5 minutes')");
    $stmt->bindValue(1, $postFile, SQLITE3_TEXT);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row['count'] == 0) {
        $stmt = $db->prepare("INSERT INTO post_views (post_file, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $postFile, SQLITE3_TEXT);
        $stmt->bindValue(2, $ip, SQLITE3_TEXT);
        $stmt->bindValue(3, $userAgent, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // 获取总查看人数（去重IP）
    $stmt = $db->prepare("SELECT COUNT(DISTINCT ip_address) as view_count FROM post_views WHERE post_file = ?");
    $stmt->bindValue(1, $postFile, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    echo json_encode(['success' => true, 'view_count' => $row['view_count']]);
}

// 获取文章查看人数
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_views') {
    if (!isset($_GET['post_file'])) {
        echo json_encode(['error' => '缺少文章文件名']);
        exit;
    }
    
    $postFile = $_GET['post_file'];
    $db = initDatabase();
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT ip_address) as view_count FROM post_views WHERE post_file = ?");
    $stmt->bindValue(1, $postFile, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    echo json_encode(['success' => true, 'view_count' => $row['view_count']]);
}

else {
    echo json_encode(['error' => '无效的请求']);
}
?>