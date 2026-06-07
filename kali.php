<?php
require_once 'security.php';
initSecurityProtection();

session_start();

define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 300);
define('SESSION_TIMEOUT', 3600);

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['title'])) {
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS && time() - $_SESSION['lockout_time'] < LOCKOUT_TIME) {
        $remaining = ceil((LOCKOUT_TIME - (time() - $_SESSION['lockout_time'])) / 60);
        die('账户已锁定，请 ' . $remaining . ' 分钟后再试');
    }

    if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['login_attempts'] = 0;
        $_SESSION['lockout_time'] = 0;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: kali.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['lockout_time'] = time();
        }
        header('Location: kali.php?error=1');
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: kali.php');
    exit;
}

$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
$isTimedOut = isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT);

if (!$isAuthenticated || $isTimedOut) {
    if ($isTimedOut) session_destroy();
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录 - 三中万能墙</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
        <div class="login-page">
            <div class="login-card">
                <div class="login-header">
                    <h2>管理员登录</h2>
                    <p>请使用管理员密码登录系统</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="msg-error">
                        <span>⚠️</span>
                        <div>
                            <strong>密码错误</strong>
                            <p>还剩 <?= max(0, MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts']) ?> 次尝试机会</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: var(--on-surface); margin-bottom: 8px;">管理员密码</label>
                        <input type="password" name="password" class="field" placeholder="请输入管理员密码" required style="margin-bottom: 0;">
                    </div>

                    <button type="submit" class="tile active" style="width: 100%; text-align: center;">
                        <span class="tile-label">登录系统</span>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--outline-variant);">
                    <p style="font-size: 12px; color: var(--on-surface-variant);">安全提示：请确保在安全的环境中操作</p>
                    <p style="font-size: 12px; color: var(--error); margin-top: 8px;">默认密码: admin123，登录后请修改源码</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['content'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token 验证失败');
    }

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        die('标题和内容不能为空');
    }

    $allowed = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><div><span><img><video><audio><source><a><figure><figcaption><hr><table><thead><tbody><tr><th><td>';
    $content = strip_tags($content, $allowed);
    $content = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
    $content = preg_replace('/\s*javascript\s*:\s*[^"\']*/i', '', $content);
    $content = preg_replace('/style\s*=\s*["\'][^"\']*expression[^"\']*["\']/i', '', $content);

    $postsDir = 'posts/';
    if (!file_exists($postsDir)) mkdir($postsDir, 0755, true);

    $asciiTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $title);
    if (strlen($asciiTitle) > 30) $asciiTitle = substr($asciiTitle, 0, 30);
    $asciiTitle = trim($asciiTitle, '_');
    if (empty($asciiTitle)) $asciiTitle = 'post';

    $fileName = $asciiTitle . '_' . time() . '.html';
    $filePath = $postsDir . $fileName;

    while (file_exists($filePath)) {
        $fileName = $asciiTitle . '_' . time() . '_' . rand(100, 999) . '.html';
        $filePath = $postsDir . $fileName;
    }

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
        <div class="post-content">' . $content . '</div>
    </article>
</body>
</html>';

    if (file_put_contents($filePath, $htmlContent) !== false) {
        header('Location: kali.php?success=1');
        exit;
    } else {
        die('文件写入失败');
    }
}

$posts = glob('posts/*.html');
usort($posts, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文章管理 - 三中万能墙</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-bar" id="navbar">
        <a href="index.php" class="app-bar-title">文章管理</a>
        <nav class="app-bar-nav">
            <a href="index.php" class="chip">返回博客</a>
            <a href="kali.php?logout=1" class="chip">退出</a>
            <button class="chip" id="themeToggle" title="切换主题">🌙</button>
        </nav>
    </header>

    <main class="main" style="margin-top: 80px;">
        <?php if (isset($_GET['success'])): ?>
            <div class="msg-success" style="margin-bottom: 16px;">
                文章创建成功！
            </div>
        <?php endif; ?>

        <div class="admin-grid">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 500; color: var(--on-surface); margin-bottom: 16px;">创建新文章</h2>
                <form method="post" id="postForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: var(--on-surface); margin-bottom: 8px;">文章标题</label>
                        <input type="text" name="title" class="field" placeholder="文章标题" required style="margin-bottom: 0;">
                    </div>

                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: var(--on-surface); margin-bottom: 8px;">文章内容（支持HTML）</label>
                        <div style="border: 1px solid var(--outline); border-radius: 16px; padding: 12px; background: var(--surface-variant); margin-bottom: 8px;">
                            <input type="file" id="fileUpload" multiple accept="image/*,video/*,audio/*" 
                                   style="width: 100%; margin-bottom: 8px; font-size: 14px;">
                            <div id="uploadProgress" style="display: none;">
                                <div style="background: var(--outline-variant); height: 4px; border-radius: 2px; overflow: hidden;">
                                    <div id="progressBar" style="height: 100%; background: var(--primary); width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <div id="progressText" style="font-size: 12px; color: var(--on-surface-variant); margin-top: 4px;"></div>
                            </div>
                            <div id="uploadedFiles" style="margin-top: 8px;"></div>
                        </div>
                        <textarea name="content" class="field" style="min-height: 160px;" placeholder="文章内容（支持HTML标签）" required></textarea>
                    </div>

                    <button type="submit" class="tile active" style="width: 100%; text-align: center;">
                        <span class="tile-label">创建文章</span>
                    </button>
                </form>
            </div>

            <div>
                <h2 style="font-size: 1.25rem; font-weight: 500; color: var(--on-surface); margin-bottom: 16px;">现有文章</h2>
                <div>
                    <?php if (count($posts) > 0): ?>
                        <?php foreach ($posts as $post): ?>
                            <?php
                            $pContent = file_get_contents($post);
                            $pTitle = pathinfo($post, PATHINFO_FILENAME);
                            if (preg_match('/<title>(.*?)</title>/i', $pContent, $m)) {
                                $pTitle = htmlspecialchars_decode(trim($m[1]));
                            }
                            $pDate = date('Y-m-d H:i', filemtime($post));
                            ?>
                            <div class="list-item">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 500; color: var(--on-surface); font-size: 14px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($pTitle) ?></div>
                                    <div style="font-size: 12px; color: var(--on-surface-variant);"><?= $pDate ?></div>
                                </div>
                                <div style="display: flex; gap: 8px; flex-shrink: 0;">
                                    <a href="post.php?file=<?= urlencode(basename($post)) ?>" class="chip" style="height: 32px; line-height: 32px; padding: 0 12px; font-size: 13px;">查看</a>
                                    <button onclick="deletePost('<?= basename($post) ?>')" class="chip" style="height: 32px; line-height: 32px; padding: 0 12px; font-size: 13px; color: var(--error);">删除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--on-surface-variant); padding: 16px;">暂无文章</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    function deletePost(filename) {
        if (!confirm('确定要删除这篇文章吗？')) return;

        fetch('delete_post.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'filename=' + encodeURIComponent(filename) + '&csrf_token=<?= $_SESSION['csrf_token'] ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('删除失败: ' + (data.error || '未知错误'));
            }
        })
        .catch(() => alert('网络错误'));
    }
    </script>

    <script>
    document.getElementById('fileUpload').addEventListener('change', function(e) {
        const files = e.target.files;
        if (files.length === 0) return;

        const progressDiv = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const uploadedFilesDiv = document.getElementById('uploadedFiles');

        progressDiv.style.display = 'block';

        Array.from(files).forEach((file, index) => {
            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(event) {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressText.textContent = `上传中: ${percent}% - ${file.name}`;
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        const fileElement = document.createElement('div');
                        fileElement.style.cssText = 'margin-bottom: 6px; padding: 8px; background: var(--primary-container); border-radius: 8px; font-size: 13px; color: var(--on-primary-container);';

                        let icon = '📄';
                        if (response.type.startsWith('image/')) icon = '📷';
                        else if (response.type.startsWith('video/')) icon = '🎥';

                        fileElement.innerHTML = `${icon} ${file.name} <a href="${response.url}" target="_blank" style="color: var(--primary); margin-left: 8px;">查看</a>`;
                        uploadedFilesDiv.appendChild(fileElement);

                        const editor = document.querySelector('textarea[name="content"]');
                        if (response.type.startsWith('image/')) {
                            editor.value += `\n<div class="post-image"><img src="${response.url}" alt="" style="max-width: 100%; height: auto;"></div>\n`;
                        } else if (response.type.startsWith('video/')) {
                            editor.value += `\n<div class="post-video"><video src="${response.url}" controls style="max-width: 100%;"></video></div>\n`;
                        }
                    } else {
                        progressText.textContent = '上传失败: ' + (response.error || '未知错误');
                        progressText.style.color = 'var(--error)';
                    }
                }

                if (index === files.length - 1) {
                    setTimeout(() => {
                        progressDiv.style.display = 'none';
                        progressBar.style.width = '0%';
                        progressText.textContent = '';
                        progressText.style.color = '';
                    }, 2000);
                }
            });

            xhr.addEventListener('error', function() {
                alert('文件上传失败: ' + file.name);
            });

            xhr.open('POST', 'upload.php');
            xhr.send(formData);
        });
    });
    </script>
    <script src="js/script.js"></script>
</body>
</html>
