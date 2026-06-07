<?php
// get_posts.php - 文章列表 API，供 index.html 调用
header('Content-Type: application/json; charset=utf-8');

$postsDir = 'posts/';
if (!is_dir($postsDir)) {
    echo json_encode([]);
    exit;
}

$files = glob($postsDir . '*.html');
if (empty($files)) {
    echo json_encode([]);
    exit;
}

// 按文件修改时间倒序排序
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$posts = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    // 提取标题（优先取 <title> 标签，否则用文件名）
    $title = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/<title>(.*?)<\/title>/i', $content, $matches)) {
        $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
    }
    
    // 日期
    $date = date('Y年m月d日', filemtime($file));
    
    // 摘要：去除 HTML 标签后截取前 150 字符
    $plainText = strip_tags($content);
    $excerpt = mb_substr($plainText, 0, 150, 'UTF-8') . '...';
    
    $posts[] = [
        'filename' => $filename,
        'title'    => $title,
        'date'     => $date,
        'excerpt'  => $excerpt
    ];
}

echo json_encode($posts, JSON_UNESCAPED_UNICODE);
?>