<?php
require_once 'security.php';
initSecurityProtection();

header('Content-Type: application/json');

if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
    http_response_code(403);
    echo json_encode(['error' => '禁止直接访问']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只支持POST请求']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => '没有文件上传或上传出错']);
    exit;
}

$file = $_FILES['file'];
$uploadDir = 'uploads/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo',
    'video/x-matroska', 'video/webm',
    'audio/mpeg', 'audio/wav',
    'application/pdf'
];

$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$realMimeType = finfo_file($fileInfo, $file['tmp_name']);
finfo_close($fileInfo);

if (!in_array($file['type'], $allowedTypes) || !in_array($realMimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => '不支持的文件类型']);
    exit;
}

$imageMaxSize = 20 * 1024 * 1024;
$videoMaxSize = 100 * 1024 * 1024;
$otherMaxSize = 50 * 1024 * 1024;

if (strpos($file['type'], 'image/') === 0 && $file['size'] > $imageMaxSize) {
    http_response_code(400);
    echo json_encode(['error' => '图片文件太大，最大支持20MB']);
    exit;
} elseif (strpos($file['type'], 'video/') === 0 && $file['size'] > $videoMaxSize) {
    http_response_code(400);
    echo json_encode(['error' => '视频文件太大，最大支持100MB']);
    exit;
} elseif ($file['size'] > $otherMaxSize) {
    http_response_code(400);
    echo json_encode(['error' => '文件太大，最大支持50MB']);
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mpeg', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'pdf'];
if (!in_array($extension, $allowedExts)) {
    http_response_code(400);
    echo json_encode(['error' => '不支持的文件扩展名']);
    exit;
}

$fileType = '';
if (strpos($file['type'], 'image/') === 0) {
    $fileType = 'img_';
} elseif (strpos($file['type'], 'video/') === 0) {
    $fileType = 'vid_';
} elseif (strpos($file['type'], 'audio/') === 0) {
    $fileType = 'aud_';
} else {
    $fileType = 'doc_';
}

$safeName = $fileType . uniqid() . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $safeName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    if (strpos($file['type'], 'image/') === 0) {
        generateThumbnail($targetPath, $uploadDir . 'thumbs/' . $safeName);
    }

    echo json_encode([
        'success' => true,
        'url' => $targetPath,
        'type' => $file['type'],
        'name' => $safeName,
        'thumbnail' => strpos($file['type'], 'image/') === 0 ? $uploadDir . 'thumbs/' . $safeName : null
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => '文件上传失败，请检查目录权限']);
}

function generateThumbnail($sourcePath, $thumbPath) {
    $thumbDir = dirname($thumbPath);
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    list($width, $height, $type) = $imageInfo;

    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($sourcePath); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($sourcePath); break;
        case IMAGETYPE_GIF: $source = imagecreatefromgif($sourcePath); break;
        case IMAGETYPE_WEBP: $source = imagecreatefromwebp($sourcePath); break;
        default: return false;
    }

    if (!$source) return false;

    $thumbWidth = 300;
    $thumbHeight = 200;
    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

    $srcRatio = $width / $height;
    $thumbRatio = $thumbWidth / $thumbHeight;

    if ($srcRatio > $thumbRatio) {
        $newHeight = $thumbHeight;
        $newWidth = $thumbHeight * $srcRatio;
        $srcX = ($newWidth - $thumbWidth) / 2;
        $srcY = 0;
    } else {
        $newWidth = $thumbWidth;
        $newHeight = $thumbWidth / $srcRatio;
        $srcX = 0;
        $srcY = ($newHeight - $thumbHeight) / 2;
    }

    imagecopyresampled($thumb, $source, 0, 0, $srcX, $srcY, $thumbWidth, $thumbHeight, $width, $height);

    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($thumb, $thumbPath, 85); break;
        case IMAGETYPE_PNG: imagepng($thumb, $thumbPath, 8); break;
        case IMAGETYPE_GIF: imagegif($thumb, $thumbPath); break;
        case IMAGETYPE_WEBP: imagewebp($thumb, $thumbPath, 85); break;
    }

    imagedestroy($source);
    imagedestroy($thumb);
    return true;
}
?>
