<?php
require_once 'security.php';
initSecurityProtection();

if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$requestedFile = basename($_GET['file']);
if (!preg_match('/^[a-zA-Z0-9_-]+\.html$/', $requestedFile)) {
    header('HTTP/1.1 400 Bad Request');
    exit('无效的文件名');
}

$postFile = 'posts/' . $requestedFile;
if (!file_exists($postFile)) {
    header('Location: index.php');
    exit;
}

$content = file_get_contents($postFile);
$title = pathinfo($postFile, PATHINFO_FILENAME);
$date = date('Y年m月d日', filemtime($postFile));

if (preg_match('/<title>(.*?)</title>/i', $content, $matches)) {
    $title = htmlspecialchars_decode(trim($matches[1]));
}
if (preg_match('/<time>(.*?)</time>/i', $content, $matches)) {
    $date = htmlspecialchars_decode(trim($matches[1]));
}

$articleContent = '';
if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $content, $matches)) {
    $articleContent = $matches[1];
} else {
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $matches)) {
        $articleContent = $matches[1];
    } else {
        $articleContent = $content;
    }
}

function sanitizeOutput($html) {
    $allowed = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><div><span><img><video><audio><source><a><figure><figcaption><hr><table><thead><tbody><tr><th><td>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s*javascript\s*:\s*[^"\']*/i', '', $html);
    $html = preg_replace('/style\s*=\s*["\'][^"\']*expression[^"\']*["\']/i', '', $html);
    return $html;
}

$articleContent = sanitizeOutput($articleContent);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - 三中万能墙</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-bar" id="navbar">
        <a href="index.php" class="app-bar-title">三中万能墙</a>
        <nav class="app-bar-nav">
            <a href="index.php" class="chip">首页</a>
            <a href="create_post.php" class="chip">发布</a>
            <a href="kali.php" class="chip">管理</a>
            <button class="chip" id="themeToggle" title="切换主题">🌙</button>
        </nav>
    </header>

    <main class="main" style="margin-top: 80px;">
        <article class="post-detail">
            <header class="post-header">
                <h1 class="post-title"><?= htmlspecialchars($title) ?></h1>
                <div class="post-meta">
                    <time><?= $date ?></time>
                </div>
            </header>

            <div class="stats">
                <span>👁</span>
                <span>已有 <span id="viewCount">0</span> 人查看</span>
            </div>

            <div class="post-body">
                <?= $articleContent ?>
            </div>

            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--outline-variant);">
                <h2 style="font-size: 1.25rem; font-weight: 500; color: var(--on-surface); margin-bottom: 16px;">评论</h2>

                <div class="comment" style="background: var(--surface-variant); margin-bottom: 16px;">
                    <div id="commentMessage"></div>
                    <input type="text" id="commentAuthor" class="field" placeholder="您的昵称" maxlength="50" style="margin-bottom: 8px;">
                    <textarea id="commentContent" class="field" placeholder="请输入您的评论内容..." rows="3" maxlength="500" style="margin-bottom: 8px;"></textarea>
                    <button type="button" class="tile active" onclick="submitComment()" style="width: auto; display: inline-flex; padding: 10px 24px;">
                        <span class="tile-label">发表评论</span>
                    </button>
                </div>

                <div id="commentsList">
                    <div style="text-align: center; color: var(--on-surface-variant); padding: 32px;">加载评论中...</div>
                </div>
            </div>

            <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--outline-variant);">
                <a href="index.php" class="chip">← 返回首页</a>
            </div>
        </article>
    </main>

    <footer class="footer">
        <p>&copy; 2025 新宁县第三中学 万能墙. 保留所有权利.</p>
    </footer>

    <script src="js/script.js"></script>
    <script>
        const postFile = <?= json_encode(basename($postFile)) ?>;

        document.addEventListener('DOMContentLoaded', function() {
            recordView();
            loadComments();
            loadViewCount();
        });

        function recordView() {
            fetch('comments.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=record_view&post_file=' + encodeURIComponent(postFile)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) document.getElementById('viewCount').textContent = data.view_count;
            })
            .catch(console.error);
        }

        function loadViewCount() {
            fetch('comments.php?action=get_views&post_file=' + encodeURIComponent(postFile))
            .then(r => r.json())
            .then(data => {
                if (data.success) document.getElementById('viewCount').textContent = data.view_count;
            })
            .catch(console.error);
        }

        function loadComments() {
            fetch('comments.php?action=get_comments&post_file=' + encodeURIComponent(postFile))
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('commentsList');
                if (data.success && data.comments.length > 0) {
                    list.innerHTML = '';
                    data.comments.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'comment';
                        div.innerHTML = `
                            <div class="comment-header">
                                <span class="comment-author">${escapeHtml(c.author)}</span>
                                <span class="comment-time">${formatTime(c.created_at)}</span>
                            </div>
                            <div class="comment-content">${escapeHtml(c.content)}</div>
                        `;
                        list.appendChild(div);
                    });
                } else {
                    list.innerHTML = '<div style="text-align:center;color:var(--on-surface-variant);padding:32px;">暂无评论，快来发表第一条评论吧！</div>';
                }
            })
            .catch(() => {
                document.getElementById('commentsList').innerHTML = '<div style="text-align:center;color:var(--error);padding:16px;">加载评论失败</div>';
            });
        }

        function submitComment() {
            const author = document.getElementById('commentAuthor').value.trim();
            const content = document.getElementById('commentContent').value.trim();

            if (!author || !content) {
                showMessage('作者和内容不能为空', 'error');
                return;
            }
            if (author.length > 50 || content.length > 500) {
                showMessage('内容长度超出限制', 'error');
                return;
            }

            fetch('comments.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=add_comment&post_file=' + encodeURIComponent(postFile) + 
                      '&author=' + encodeURIComponent(author) + 
                      '&content=' + encodeURIComponent(content)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showMessage('评论发表成功！', 'success');
                    document.getElementById('commentContent').value = '';
                    loadComments();
                } else {
                    showMessage(data.error || '评论发表失败', 'error');
                }
            })
            .catch(() => showMessage('网络错误，请稍后重试', 'error'));
        }

        function showMessage(message, type) {
            const msg = document.getElementById('commentMessage');
            msg.innerHTML = `<div class="msg-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => msg.innerHTML = '', 5000);
        }

        setInterval(loadViewCount, 30000);
    </script>
</body>
</html>
