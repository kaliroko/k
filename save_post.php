<?php
require_once 'security.php';
initSecurityProtection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('只支持POST请求');
}

if (!isset($_POST['title']) || !isset($_POST['content'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('缺少必要的参数');
}

$title = trim($_POST['title']);
$content = $_POST['content'];

if (empty($title)) {
    header('HTTP/1.1 400 Bad Request');
    exit('文章标题不能为空');
}

if (empty($content)) {
    header('HTTP/1.1 400 Bad Request');
    exit('文章内容不能为空');
}

$postsDir = 'posts/';
if (!file_exists($postsDir)) {
    mkdir($postsDir, 0755, true);
}

// 标题与文件名分离
$safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $title);
if (empty($safeTitle)) $safeTitle = 'post';
if (strlen($safeTitle) > 30) $safeTitle = substr($safeTitle, 0, 30);
$safeTitle = trim($safeTitle, '_');

$fileName = $safeTitle . '_' . time() . '.html';
$filePath = $postsDir . $fileName;

// 内容安全过滤
$allowed = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><div><span><img><video><audio><source><a><figure><figcaption><hr><table><thead><tbody><tr><th><td>';
$cleanContent = strip_tags($content, $allowed);
$cleanContent = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $cleanContent);
$cleanContent = preg_replace('/\s*javascript\s*:\s*[^"\']*/i', '', $cleanContent);
$cleanContent = preg_replace('/style\s*=\s*["\'][^"\']*expression[^"\']*["\']/i', '', $cleanContent);

$htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans SC", sans-serif; line-height: 1.6; color: #1C1B1F; max-width: 800px; margin: 0 auto; padding: 2rem; background: #FEF7FF; }
        .post-image img, .post-video video { max-width: 100%; height: auto; border-radius: 16px; display: block; margin: 1rem auto; }
        .post-audio audio { width: 100%; max-width: 400px; display: block; margin: 1rem auto; }
        h1, h2, h3 { color: #1C1B1F; margin-top: 1.5rem; margin-bottom: 0.75rem; }
        p { margin-bottom: 1rem; }
        code { background: rgba(0,0,0,0.05); padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace; }
        a { color: #6750A4; }
    </style>
</head>
<body>
    <article>
        <header>
            <h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
            <time>' . date('Y年m月d日 H:i:s') . '</time>
        </header>
        <div class="post-content">' . $cleanContent . '</div>
    </article>
</body>
</html>';

if (file_put_contents($filePath, $htmlContent) !== false) {
    header('Location: post.php?file=' . urlencode($fileName));
    exit;
} else {
    header('HTTP/1.1 500 Internal Server Error');
    exit('保存文章失败');
}
?>
