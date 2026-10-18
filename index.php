<?php
/**
 * FileHub File Explorer
 * A modern, responsive, and secure PHP file explorer.
 * Explores files located inside the "resource" directory.
 */

declare(strict_types=1);

// Configuration
define('RESOURCE_DIR', __DIR__ . '/resource');
define('CLIPBOARD_FILE', __DIR__ . '/.clipboard_data.json');
define('APP_NAME', 'FileHub Explorer');
define('APP_VERSION', '2.1.0');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50 MB

// Prevent browsers from serving stale cached HTML
if (!headers_sent()) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Ensure resource directory exists
if (!is_dir(RESOURCE_DIR)) {
    @mkdir(RESOURCE_DIR, 0755, true);
}

// Helper: Get shared clipboard data
function getClipboardData(): array {
    if (!file_exists(CLIPBOARD_FILE)) {
        return [
            'content' => '',
            'updated_at' => 0,
            'updated_formatted' => 'Empty (No clips yet)',
            'history' => []
        ];
    }
    $raw = @file_get_contents(CLIPBOARD_FILE);
    if ($raw === false) {
        return [
            'content' => '',
            'updated_at' => 0,
            'updated_formatted' => 'Empty',
            'history' => []
        ];
    }
    $data = @json_decode($raw, true);
    if (!is_array($data)) {
        return [
            'content' => '',
            'updated_at' => 0,
            'updated_formatted' => 'Empty',
            'history' => []
        ];
    }
    return [
        'content' => (string)($data['content'] ?? ''),
        'updated_at' => (int)($data['updated_at'] ?? 0),
        'updated_formatted' => (string)($data['updated_formatted'] ?? 'Never'),
        'history' => (array)($data['history'] ?? [])
    ];
}

// Helper: Format bytes to human-readable format
function formatFileSize(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    if ($i >= count($units)) $i = count($units) - 1;
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Helper: Validate and resolve path safely within RESOURCE_DIR
function resolveSafePath(string $subpath): ?string {
    $subpath = str_replace(["\0", "\\"], ['', '/'], $subpath);
    $subpath = trim($subpath, '/');
    
    $fullPath = RESOURCE_DIR . ($subpath !== '' ? '/' . $subpath : '');
    $realPath = realpath($fullPath);
    
    if ($realPath === false) {
        return null;
    }
    
    // Ensure realpath is RESOURCE_DIR or inside RESOURCE_DIR
    $realBase = realpath(RESOURCE_DIR);
    if ($realBase === false) {
        return null;
    }
    
    if ($realPath === $realBase || str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
        return $realPath;
    }
    
    return null;
}

// Helper: Get relative path from RESOURCE_DIR
function getRelativePath(string $absolutePath): string {
    $realBase = realpath(RESOURCE_DIR);
    if ($realBase === false || $absolutePath === $realBase) {
        return '';
    }
    if (str_starts_with($absolutePath, $realBase . DIRECTORY_SEPARATOR)) {
        return substr($absolutePath, strlen($realBase) + 1);
    }
    return '';
}

// Helper: Sanitize filename and generate unique available name in directory
function getSafeAvailableFilename(string $dir, string $filename): string {
    $filename = basename($filename);
    $filename = str_replace(["\0", "/", "\\", ".."], '', $filename);
    $filename = preg_replace('/[^\w\s\.\(\)\-_]/u', '_', $filename);
    $filename = trim($filename, '. ');
    if ($filename === '') {
        $filename = 'uploaded_file_' . time();
    }
    
    $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($fullPath)) {
        return $filename;
    }
    
    $info = pathinfo($filename);
    $name = $info['filename'];
    $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    
    $counter = 1;
    while (file_exists($dir . DIRECTORY_SEPARATOR . "{$name} ({$counter}){$ext}")) {
        $counter++;
    }
    return "{$name} ({$counter}){$ext}";
}

// Helper: Detect category and mime type
function getFileInfo(string $filePath): array {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif'];
    $pdfExts = ['pdf'];
    $textExts = ['txt', 'md', 'json', 'xml', 'csv', 'log', 'yaml', 'yml', 'html', 'htm', 'css', 'js', 'ts', 'php', 'py', 'sh', 'sql', 'env', 'ini', 'conf'];
    $audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'];
    $videoExts = ['mp4', 'webm', 'ogv', 'mov', 'mkv'];
    $archiveExts = ['zip', 'tar', 'gz', 'bz2', '7z', 'rar'];
    
    $category = 'file';
    if (in_array($ext, $imageExts, true)) {
        $category = 'image';
    } elseif (in_array($ext, $pdfExts, true)) {
        $category = 'pdf';
    } elseif (in_array($ext, $textExts, true)) {
        $category = 'text';
    } elseif (in_array($ext, $audioExts, true)) {
        $category = 'audio';
    } elseif (in_array($ext, $videoExts, true)) {
        $category = 'video';
    } elseif (in_array($ext, $archiveExts, true)) {
        $category = 'archive';
    }
    
    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($filePath);
        if ($detected) $mime = $detected;
    }
    
    return [
        'extension' => $ext,
        'category' => $category,
        'mime' => $mime,
    ];
}

// ----------------------------------------------------
// Handle Actions: Upload Chunk, Direct Upload, Download, View, Get Content
// ----------------------------------------------------
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

// ACTION: GET CLIPBOARD
if ($action === 'get_clipboard') {
    header('Content-Type: application/json; charset=utf-8');
    $data = getClipboardData();
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// ACTION: SAVE CLIPBOARD
if ($action === 'save_clipboard') {
    header('Content-Type: application/json; charset=utf-8');
    $content = (string)($_POST['content'] ?? '');
    
    // Up to 10 MB payload limit for large text / code / logs
    if (strlen($content) > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Clipboard content exceeds 10 MB limit.']);
        exit;
    }

    $data = getClipboardData();
    $oldContent = $data['content'];
    $now = time();
    $nowFormatted = date('M d, Y h:i:s A', $now);

    $history = $data['history'];
    // If text has changed and is not empty, push to history
    if (trim($content) !== '' && $content !== $oldContent) {
        $snippet = mb_substr(trim($content), 0, 100);
        if (mb_strlen(trim($content)) > 100) $snippet .= '...';
        
        array_unshift($history, [
            'id' => uniqid('clip_', true),
            'preview' => $snippet,
            'content' => $content,
            'created_at' => $now,
            'created_formatted' => $nowFormatted,
            'size' => strlen($content),
            'sizeFormatted' => formatFileSize(strlen($content))
        ]);
        // Keep up to 20 history clips
        $history = array_slice($history, 0, 20);
    }

    $newData = [
        'content' => $content,
        'updated_at' => $now,
        'updated_formatted' => $nowFormatted,
        'history' => $history
    ];
    @file_put_contents(CLIPBOARD_FILE, json_encode($newData, JSON_PRETTY_PRINT), LOCK_EX);

    echo json_encode(array_merge(['success' => true], $newData));
    exit;
}

// ACTION: CLEAR CLIPBOARD
if ($action === 'clear_clipboard') {
    header('Content-Type: application/json; charset=utf-8');
    $data = getClipboardData();
    $data['content'] = '';
    $data['updated_at'] = time();
    $data['updated_formatted'] = 'Cleared just now';
    @file_put_contents(CLIPBOARD_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode(['success' => true]);
    exit;
}

// ACTION: SAVE CLIPBOARD AS FILE
if ($action === 'save_clip_file') {
    header('Content-Type: application/json; charset=utf-8');
    $targetSubdir = $_POST['dir'] ?? '';
    $targetDir = resolveSafePath($targetSubdir);
    if (!$targetDir || !is_dir($targetDir)) {
        echo json_encode(['success' => false, 'error' => 'Target directory does not exist or access denied.']);
        exit;
    }
    
    $rawName = trim((string)($_POST['fileName'] ?? ''));
    if ($rawName === '') {
        $rawName = 'clipboard_' . date('Ymd_His') . '.txt';
    }
    // Basic sanitize filename
    $rawName = basename($rawName);
    $safeName = getSafeAvailableFilename($targetDir, $rawName);
    $finalDestination = $targetDir . DIRECTORY_SEPARATOR . $safeName;
    $content = (string)($_POST['content'] ?? '');
    
    if (@file_put_contents($finalDestination, $content, LOCK_EX) !== false) {
        echo json_encode([
            'success' => true,
            'fileName' => $safeName,
            'size' => formatFileSize(strlen($content))
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to write file.']);
    }
    exit;
}

// ACTION: CHUNKED UPLOAD (Handles files up to 50 MB seamlessly)
if ($action === 'upload_chunk') {
    header('Content-Type: application/json; charset=utf-8');
    
    $fileId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['fileId'] ?? '');
    $fileName = basename($_POST['fileName'] ?? '');
    $chunkIndex = (int)($_POST['chunkIndex'] ?? 0);
    $totalChunks = (int)($_POST['totalChunks'] ?? 1);
    $totalSize = (int)($_POST['totalSize'] ?? 0);
    $targetSubdir = $_POST['dir'] ?? '';

    if ($totalSize > MAX_UPLOAD_SIZE) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds 50 MB limit.']);
        exit;
    }

    if ($fileId === '' || $fileName === '' || $totalChunks < 1 || !isset($_FILES['chunk'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid upload payload.']);
        exit;
    }

    $targetDir = resolveSafePath($targetSubdir);
    if (!$targetDir || !is_dir($targetDir)) {
        echo json_encode(['success' => false, 'error' => 'Target directory does not exist or access denied.']);
        exit;
    }

    $tempChunkDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'filehub_chunks_' . $fileId;
    if (!is_dir($tempChunkDir)) {
        @mkdir($tempChunkDir, 0755, true);
    }

    $chunkFile = $tempChunkDir . DIRECTORY_SEPARATOR . "chunk_{$chunkIndex}";
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save upload chunk.']);
        exit;
    }

    // Last chunk reassembly
    if ($chunkIndex === $totalChunks - 1) {
        $safeName = getSafeAvailableFilename($targetDir, $fileName);
        $finalDestination = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        
        $out = @fopen($finalDestination, 'wb');
        if (!$out) {
            echo json_encode(['success' => false, 'error' => 'Failed to write final file.']);
            exit;
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $part = $tempChunkDir . DIRECTORY_SEPARATOR . "chunk_{$i}";
            if (!file_exists($part)) {
                fclose($out);
                @unlink($finalDestination);
                echo json_encode(['success' => false, 'error' => "Missing chunk {$i} during assembly."]);
                exit;
            }
            $in = fopen($part, 'rb');
            while (!feof($in)) {
                fwrite($out, fread($in, 8192));
            }
            fclose($in);
            @unlink($part);
        }
        fclose($out);
        @rmdir($tempChunkDir);

        $actualSize = filesize($finalDestination);
        if ($actualSize > MAX_UPLOAD_SIZE) {
            @unlink($finalDestination);
            echo json_encode(['success' => false, 'error' => 'File exceeds 50 MB limit after assembly.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'completed' => true,
            'fileName' => $safeName,
            'size' => formatFileSize($actualSize)
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'completed' => false,
        'chunkIndex' => $chunkIndex
    ]);
    exit;
}

// ACTION: DIRECT UPLOAD
if ($action === 'upload') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file received.']);
        exit;
    }

    $file = $_FILES['file'];
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        echo json_encode(['success' => false, 'error' => 'File exceeds 50 MB limit.']);
        exit;
    }

    $targetSubdir = $_POST['dir'] ?? '';
    $targetDir = resolveSafePath($targetSubdir);
    if (!$targetDir || !is_dir($targetDir)) {
        echo json_encode(['success' => false, 'error' => 'Target directory does not exist or access denied.']);
        exit;
    }

    $safeName = getSafeAvailableFilename($targetDir, $file['name']);
    $finalDestination = $targetDir . DIRECTORY_SEPARATOR . $safeName;
    if (move_uploaded_file($file['tmp_name'], $finalDestination)) {
        echo json_encode([
            'success' => true,
            'fileName' => $safeName,
            'size' => formatFileSize(filesize($finalDestination))
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
    }
    exit;
}

$targetFile = $_GET['file'] ?? '';

if ($action && $targetFile !== '') {
    $resolved = resolveSafePath($targetFile);
    
    if (!$resolved || !is_file($resolved)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "File not found or access denied.";
        exit;
    }

    $filename = basename($resolved);
    $filesize = filesize($resolved);
    $info = getFileInfo($resolved);

    // ACTION: DOWNLOAD
    if ($action === 'download') {
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $info['mime']);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $filesize);
        readfile($resolved);
        exit;
    }

    // ACTION: VIEW (Inline Streaming)
    if ($action === 'view') {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $mime = $info['mime'];
        if ($info['category'] === 'text') {
            $mime = 'text/plain; charset=utf-8';
        } elseif ($info['extension'] === 'svg') {
            $mime = 'image/svg+xml';
        } elseif ($info['extension'] === 'pdf') {
            $mime = 'application/pdf';
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: public, max-age=3600');
        readfile($resolved);
        exit;
    }

    // ACTION: GET_CONTENT (For previewing text / code files in modal)
    if ($action === 'get_content') {
        header('Content-Type: application/json; charset=utf-8');
        if ($filesize > 2 * 1024 * 1024) { // 2MB limit
            echo json_encode([
                'success' => false,
                'error' => 'File is too large for inline preview (' . formatFileSize($filesize) . '). Please download to view.'
            ]);
            exit;
        }

        $content = @file_get_contents($resolved);
        if ($content === false) {
            echo json_encode(['success' => false, 'error' => 'Unable to read file.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'size' => formatFileSize($filesize),
            'extension' => $info['extension'],
            'content' => $content
        ]);
        exit;
    }
}

// ----------------------------------------------------
// Directory Listing Logic (from RESOURCE_DIR)
// ----------------------------------------------------
$currentSubpath = $_GET['dir'] ?? '';
$resolvedDir = resolveSafePath($currentSubpath);

if (!$resolvedDir || !is_dir($resolvedDir)) {
    $currentSubpath = '';
    $resolvedDir = RESOURCE_DIR;
}

$relDir = getRelativePath($resolvedDir);
$showHidden = isset($_GET['show_hidden']) && $_GET['show_hidden'] === '1';

// Build breadcrumbs
$breadcrumbs = [];
$breadcrumbs[] = ['name' => 'Root', 'path' => ''];

if ($relDir !== '') {
    $segments = explode('/', $relDir);
    $accum = '';
    foreach ($segments as $seg) {
        if ($seg === '') continue;
        $accum = ($accum === '') ? $seg : $accum . '/' . $seg;
        $breadcrumbs[] = ['name' => $seg, 'path' => $accum];
    }
}

// Parent directory calculation
$parentDirParam = null;
if ($relDir !== '') {
    $parent = dirname($relDir);
    $parentDirParam = ($parent === '.' || $parent === '') ? '' : $parent;
}

// Scan items
$items = scandir($resolvedDir);
$directories = [];
$files = [];
$totalSize = 0;

if ($items !== false) {
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        // Skip hidden files unless requested
        if (!$showHidden && str_starts_with($item, '.')) {
            continue;
        }

        $fullItemPath = $resolvedDir . '/' . $item;
        $relItemPath = ($relDir !== '' ? $relDir . '/' : '') . $item;

        if (is_dir($fullItemPath)) {
            $subItems = @scandir($fullItemPath);
            $subCount = ($subItems !== false) ? count(array_filter($subItems, fn($s) => $s !== '.' && $s !== '..' && ($showHidden || !str_starts_with($s, '.')))) : 0;
            
            $directories[] = [
                'name' => $item,
                'path' => $relItemPath,
                'isDir' => true,
                'subCount' => $subCount,
                'size' => 0,
                'mtime' => filemtime($fullItemPath),
                'mtimeFormatted' => date('M d, Y h:i A', filemtime($fullItemPath)),
            ];
        } elseif (is_file($fullItemPath)) {
            $fsize = filesize($fullItemPath);
            $totalSize += $fsize;
            $finfo = getFileInfo($fullItemPath);
            
            $files[] = [
                'name' => $item,
                'path' => $relItemPath,
                'isDir' => false,
                'size' => $fsize,
                'sizeFormatted' => formatFileSize($fsize),
                'mtime' => filemtime($fullItemPath),
                'mtimeFormatted' => date('M d, Y h:i A', filemtime($fullItemPath)),
                'category' => $finfo['category'],
                'extension' => $finfo['extension'],
                'mime' => $finfo['mime'],
            ];
        }
    }
}

// Sort alphabetically by default
usort($directories, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

$totalItems = count($directories) + count($files);
$formattedTotalSize = formatFileSize($totalSize);

$typeCounts = [
    'all' => $totalItems,
    'folder' => count($directories),
    'image' => 0,
    'pdf' => 0,
    'archive' => 0,
    'text' => 0,
    'audio' => 0,
    'video' => 0,
    'file' => 0,
];
foreach ($files as $f) {
    $cat = $f['category'];
    if (isset($typeCounts[$cat])) {
        $typeCounts[$cat]++;
    } else {
        $typeCounts['file']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($relDir ? basename($relDir) . ' - ' . APP_NAME : APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= APP_VERSION ?>.<?= file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time() ?>">
</head>
<body data-current-dir="<?= htmlspecialchars($relDir) ?>">

    <!-- Header -->
    <header class="app-header">
        <!-- Top row: Brand + Tools -->
        <div class="header-top-row">
            <a href="?dir=" class="brand-section">
                <div class="brand-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="brand-title">FileHub Explorer</h1>
                    <p class="brand-subtitle">Viewing: /resource</p>
                </div>
            </a>

            <!-- Header Tools (Clipboard, View, Theme) -->
            <div class="header-tools">
                <!-- Shared Network Clipboard Button -->
                <button type="button" class="btn-action btn-clipboard" id="openClipboardBtn" title="Shared Network Clipboard (Sync text across laptops)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                    </svg>
                    <span>Clipboard</span>
                </button>

                <!-- View switcher -->
                <div class="view-toggle" title="Switch view">
                    <button type="button" class="btn-icon active" id="viewGridBtn" title="Grid View">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </button>
                    <button type="button" class="btn-icon" id="viewListBtn" title="List View">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <!-- Theme toggle -->
                <button type="button" class="btn-icon" id="themeToggleBtn" title="Toggle Dark/Light Mode">
                    <svg id="themeIconSun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                    <svg id="themeIconMoon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Search row -->
        <div class="header-search-row">
            <div class="search-box">
                <span class="search-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input type="text" id="searchInput" class="search-input" placeholder="Search files... (Press /)" autocomplete="off">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Subheader with Breadcrumbs, Parent Dir, Upload Button & Stats -->
    <div class="subheader-bar">
        <div class="breadcrumb-group">
            <?php if ($parentDirParam !== null): ?>
                <a href="?dir=<?= urlencode($parentDirParam) ?>" class="btn-action" style="padding: 0.3rem 0.65rem; font-size: 0.8rem;" title="Go to parent directory">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5"></path>
                        <path d="M12 19l-7-7 7-7"></path>
                    </svg>
                    <span>Up</span>
                </a>
            <?php endif; ?>

            <nav class="breadcrumb-trail" aria-label="Breadcrumb">
                <?php foreach ($breadcrumbs as $idx => $bc): ?>
                    <?php $isLast = ($idx === count($breadcrumbs) - 1); ?>
                    <?php if ($idx === 0): ?>
                        <a href="?dir=" class="breadcrumb-item <?= $isLast ? 'active' : '' ?>">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span>Resource</span>
                        </a>
                    <?php else: ?>
                        <span class="breadcrumb-sep">/</span>
                        <?php if ($isLast): ?>
                            <span class="breadcrumb-item active"><?= htmlspecialchars($bc['name']) ?></span>
                        <?php else: ?>
                            <a href="?dir=<?= urlencode($bc['path']) ?>" class="breadcrumb-item"><?= htmlspecialchars($bc['name']) ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="subheader-controls">
            <!-- Upload Button -->
            <button type="button" class="btn-action btn-primary" id="openUploadBtn" title="Upload files up to 50 MB">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Upload File</span>
            </button>

            <!-- Filter & Sort Controls Row -->
            <div class="subheader-filters-row">
                <!-- Filter by Type dropdown -->
                <div class="filter-select-wrap">
                    <label for="typeFilterSelect">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <span>Filter:</span>
                    </label>
                    <select id="typeFilterSelect" class="sort-select" title="Filter by file type">
                        <option value="all">All Types (<?= $typeCounts['all'] ?>)</option>
                        <?php if ($typeCounts['folder'] > 0): ?>
                            <option value="folder">Folders (<?= $typeCounts['folder'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['pdf'] > 0): ?>
                            <option value="pdf">PDF Documents (<?= $typeCounts['pdf'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['image'] > 0): ?>
                            <option value="image">Images (<?= $typeCounts['image'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['archive'] > 0): ?>
                            <option value="archive">Zip / Archives (<?= $typeCounts['archive'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['text'] > 0): ?>
                            <option value="text">Documents &amp; Code (<?= $typeCounts['text'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['audio'] > 0): ?>
                            <option value="audio">Audio (<?= $typeCounts['audio'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['video'] > 0): ?>
                            <option value="video">Video (<?= $typeCounts['video'] ?>)</option>
                        <?php endif; ?>
                        <?php if ($typeCounts['file'] > 0): ?>
                            <option value="file">Other Files (<?= $typeCounts['file'] ?>)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Sort dropdown -->
                <div class="sort-select-wrap">
                    <label for="sortSelect">Sort:</label>
                    <select id="sortSelect" class="sort-select">
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="size-desc">Size (Large to Small)</option>
                        <option value="size-asc">Size (Small to Large)</option>
                        <option value="date-desc">Date Modified (Newest)</option>
                        <option value="date-asc">Date Modified (Oldest)</option>
                    </select>
                </div>
            </div>

            <!-- Stats -->
            <div class="meta-stats">
                <span class="meta-pill"><?= count($directories) ?> <?= count($directories) === 1 ? 'folder' : 'folders' ?></span>
                <span class="meta-pill"><?= count($files) ?> <?= count($files) === 1 ? 'file' : 'files' ?></span>
                <span class="meta-pill"><?= $formattedTotalSize ?></span>
            </div>
        </div>
    </div>

    <!-- Main Explorer Content -->
    <main class="explorer-container" id="dropTargetContainer">
        
        <?php if ($totalItems === 0): ?>
            <div class="empty-state">
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    <line x1="8" y1="13" x2="16" y2="13"></line>
                </svg>
                <h3 class="empty-state-title">This folder is empty</h3>
                <p class="empty-state-desc">Drag and drop files here, or use the Upload File button (up to 50 MB).</p>
                <div style="margin-top: 1.25rem;">
                    <button type="button" class="btn-action btn-primary" onclick="openUploadModal()" style="display: inline-flex; width: auto; padding: 0.6rem 1.25rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        Upload Files
                    </button>
                </div>
            </div>
        <?php else: ?>

            <!-- Filter Chips Bar -->
            <div class="filter-chips-bar" id="filterChipsBar">
                <div class="filter-chips-scroll">
                    <button type="button" class="filter-chip active" data-type="all">
                        <span>All</span>
                        <span class="chip-count"><?= $typeCounts['all'] ?></span>
                    </button>
                    <?php if ($typeCounts['folder'] > 0): ?>
                        <button type="button" class="filter-chip chip-folder" data-type="folder" title="Filter by folders">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                            <span>Folders</span>
                            <span class="chip-count"><?= $typeCounts['folder'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['pdf'] > 0): ?>
                        <button type="button" class="filter-chip chip-pdf" data-type="pdf" title="Filter by PDF documents">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            <span>PDF</span>
                            <span class="chip-count"><?= $typeCounts['pdf'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['image'] > 0): ?>
                        <button type="button" class="filter-chip chip-image" data-type="image" title="Filter by images">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span>Images</span>
                            <span class="chip-count"><?= $typeCounts['image'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['archive'] > 0): ?>
                        <button type="button" class="filter-chip chip-archive" data-type="archive" title="Filter by Zip and Archive files">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                            <span>Zip / Archives</span>
                            <span class="chip-count"><?= $typeCounts['archive'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['text'] > 0): ?>
                        <button type="button" class="filter-chip chip-text" data-type="text" title="Filter by text &amp; code documents">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                            <span>Documents &amp; Code</span>
                            <span class="chip-count"><?= $typeCounts['text'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['audio'] > 0): ?>
                        <button type="button" class="filter-chip chip-audio" data-type="audio" title="Filter by audio">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
                            <span>Audio</span>
                            <span class="chip-count"><?= $typeCounts['audio'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['video'] > 0): ?>
                        <button type="button" class="filter-chip chip-video" data-type="video" title="Filter by videos">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line></svg>
                            <span>Video</span>
                            <span class="chip-count"><?= $typeCounts['video'] ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($typeCounts['file'] > 0): ?>
                        <button type="button" class="filter-chip chip-file" data-type="file" title="Filter by other files">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                            <span>Other</span>
                            <span class="chip-count"><?= $typeCounts['file'] ?></span>
                        </button>
                    <?php endif; ?>
                </div>

                <div id="filterActiveStatus" class="filter-active-status" style="display: none;">
                    <span id="filterStatusText">Showing all</span>
                    <button type="button" class="filter-status-clear" id="filterStatusClear" title="Clear filter">×</button>
                </div>
            </div>

            <!-- GRID VIEW -->
            <div id="gridContainer">
                <?php if (!empty($directories)): ?>
                    <div class="section-header" id="foldersSectionHeader">
                        <div class="section-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Folders (<?= count($directories) ?>)
                        </div>
                    </div>
                    <div class="explorer-grid" id="gridFolders">
                        <?php foreach ($directories as $dir): ?>
                            <div class="item-card file-item-searchable" data-type="folder" data-extension="" data-name="<?= strtolower(htmlspecialchars($dir['name'])) ?>" data-size="<?= $dir['size'] ?>" data-mtime="<?= $dir['mtime'] ?>" onclick="location.href='?dir=<?= urlencode($dir['path']) ?>'">
                                <div class="item-card-header">
                                    <div class="item-icon-wrapper icon-folder">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                    </div>
                                    <span class="type-badge badge-folder-bg">Folder</span>
                                </div>
                                <div class="item-info">
                                    <div class="item-name" title="<?= htmlspecialchars($dir['name']) ?>"><?= htmlspecialchars($dir['name']) ?></div>
                                    <div class="item-details">
                                        <span><?= $dir['subCount'] ?> items</span>
                                        <span>•</span>
                                        <span><?= $dir['mtimeFormatted'] ?></span>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <a href="?dir=<?= urlencode($dir['path']) ?>" class="btn-action btn-primary" onclick="event.stopPropagation();">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 12h14"></path>
                                            <path d="M12 5l7 7-7 7"></path>
                                        </svg>
                                        Open Folder
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($files)): ?>
                    <div class="section-header" id="filesSectionHeader">
                        <div class="section-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                <polyline points="13 2 13 9 20 9"></polyline>
                            </svg>
                            Files (<?= count($files) ?>)
                        </div>
                    </div>
                    <div class="explorer-grid" id="gridFiles">
                        <?php foreach ($files as $file): ?>
                            <div class="item-card file-item-searchable" data-type="<?= $file['category'] ?>" data-extension="<?= htmlspecialchars(strtolower($file['extension'])) ?>" data-name="<?= strtolower(htmlspecialchars($file['name'])) ?>" data-size="<?= $file['size'] ?>" data-mtime="<?= $file['mtime'] ?>">
                                
                                <?php if ($file['category'] === 'image'): ?>
                                    <!-- Image Thumbnail View -->
                                    <div class="card-thumb-container" onclick="openPreview('<?= htmlspecialchars(addslashes($file['path'])) ?>', '<?= htmlspecialchars(addslashes($file['name'])) ?>', '<?= $file['category'] ?>', '<?= $file['sizeFormatted'] ?>', '<?= $file['extension'] ?>')">
                                        <img src="?action=view&file=<?= urlencode($file['path']) ?>" alt="<?= htmlspecialchars($file['name']) ?>" loading="lazy" class="card-thumb-img">
                                    </div>
                                <?php endif; ?>

                                <div class="item-card-header">
                                    <div class="item-icon-wrapper icon-<?= $file['category'] ?>">
                                        <?php if ($file['category'] === 'pdf'): ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                                <polyline points="10 9 9 9 8 9"></polyline>
                                            </svg>
                                        <?php elseif ($file['category'] === 'image'): ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                        <?php elseif ($file['category'] === 'text'): ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="16 18 22 12 16 6"></polyline>
                                                <polyline points="8 6 2 12 8 18"></polyline>
                                            </svg>
                                        <?php elseif ($file['category'] === 'audio'): ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M9 18V5l12-2v13"></path>
                                                <circle cx="6" cy="18" r="3"></circle>
                                                <circle cx="18" cy="16" r="3"></circle>
                                            </svg>
                                        <?php elseif ($file['category'] === 'video'): ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect>
                                                <line x1="7" y1="2" x2="7" y2="22"></line>
                                                <line x1="17" y1="2" x2="17" y2="22"></line>
                                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                                <line x1="2" y1="7" x2="7" y2="7"></line>
                                                <line x1="2" y1="17" x2="7" y2="17"></line>
                                                <line x1="17" y1="17" x2="22" y2="17"></line>
                                                <line x1="17" y1="7" x2="22" y2="7"></line>
                                            </svg>
                                        <?php elseif ($file['category'] === 'archive'): ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="21 8 21 21 3 21 3 8"></polyline>
                                                <rect x="1" y="3" width="22" height="5"></rect>
                                                <line x1="10" y1="12" x2="14" y2="12"></line>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                                <polyline points="13 2 13 9 20 9"></polyline>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <span class="type-badge badge-<?= $file['category'] ?>-bg"><?= htmlspecialchars(strtoupper($file['extension'] ?: 'FILE')) ?></span>
                                </div>
                                <div class="item-info">
                                    <div class="item-name" title="<?= htmlspecialchars($file['name']) ?>"><?= htmlspecialchars($file['name']) ?></div>
                                    <div class="item-details">
                                        <span><?= $file['sizeFormatted'] ?></span>
                                        <span>•</span>
                                        <span><?= $file['mtimeFormatted'] ?></span>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <button type="button" class="btn-action btn-primary" onclick="openPreview('<?= htmlspecialchars(addslashes($file['path'])) ?>', '<?= htmlspecialchars(addslashes($file['name'])) ?>', '<?= $file['category'] ?>', '<?= $file['sizeFormatted'] ?>', '<?= $file['extension'] ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        View
                                    </button>
                                    <a href="?action=download&file=<?= urlencode($file['path']) ?>" class="btn-action" title="Download <?= htmlspecialchars($file['name']) ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        Download
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- LIST VIEW -->
            <div id="listContainer" class="explorer-list">
                <table class="explorer-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Date Modified</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($directories as $dir): ?>
                            <tr class="file-item-searchable" data-type="folder" data-extension="" data-name="<?= strtolower(htmlspecialchars($dir['name'])) ?>" data-size="<?= $dir['size'] ?>" data-mtime="<?= $dir['mtime'] ?>">
                                <td>
                                    <div class="table-item-name-cell">
                                        <div class="table-item-icon icon-folder">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                        </div>
                                        <a href="?dir=<?= urlencode($dir['path']) ?>" class="table-link"><?= htmlspecialchars($dir['name']) ?></a>
                                    </div>
                                </td>
                                <td><span class="type-badge badge-folder-bg">Folder</span></td>
                                <td><?= $dir['subCount'] ?> items</td>
                                <td><?= $dir['mtimeFormatted'] ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="?dir=<?= urlencode($dir['path']) ?>" class="btn-action btn-primary" style="padding: 0.35rem 0.65rem;">
                                            Open
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($files as $file): ?>
                            <tr class="file-item-searchable" data-type="<?= $file['category'] ?>" data-extension="<?= htmlspecialchars(strtolower($file['extension'])) ?>" data-name="<?= strtolower(htmlspecialchars($file['name'])) ?>" data-size="<?= $file['size'] ?>" data-mtime="<?= $file['mtime'] ?>">
                                <td>
                                    <div class="table-item-name-cell">
                                        <div class="table-item-icon icon-<?= $file['category'] ?>">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                                <polyline points="13 2 13 9 20 9"></polyline>
                                            </svg>
                                        </div>
                                        <span class="table-link" style="cursor: pointer;" onclick="openPreview('<?= htmlspecialchars(addslashes($file['path'])) ?>', '<?= htmlspecialchars(addslashes($file['name'])) ?>', '<?= $file['category'] ?>', '<?= $file['sizeFormatted'] ?>', '<?= $file['extension'] ?>')">
                                            <?= htmlspecialchars($file['name']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="type-badge badge-<?= $file['category'] ?>-bg"><?= strtoupper($file['extension'] ?: 'FILE') ?></span></td>
                                <td><?= $file['sizeFormatted'] ?></td>
                                <td><?= $file['mtimeFormatted'] ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn-action btn-primary" style="padding: 0.35rem 0.65rem;" onclick="openPreview('<?= htmlspecialchars(addslashes($file['path'])) ?>', '<?= htmlspecialchars(addslashes($file['name'])) ?>', '<?= $file['category'] ?>', '<?= $file['sizeFormatted'] ?>', '<?= $file['extension'] ?>')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            View
                                        </button>
                                        <a href="?action=download&file=<?= urlencode($file['path']) ?>" class="btn-action" style="padding: 0.35rem 0.65rem;" title="Download">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="7 10 12 15 17 10"></polyline>
                                                <line x1="12" y1="15" x2="12" y2="3"></line>
                                            </svg>
                                            Download
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Search No Results Box -->
            <div id="noSearchResults" class="empty-state" style="display: none;">
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <h3 class="empty-state-title" id="noResultsTitle">No matching files found</h3>
                <p class="empty-state-desc" id="noResultsDesc">Try selecting a different filter or clearing your search query.</p>
                <div style="margin-top: 1.25rem;">
                    <button type="button" class="btn-action btn-primary" id="resetFilterSearchBtn" style="display: inline-flex; width: auto; padding: 0.55rem 1.2rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Clear Filter &amp; Search
                    </button>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal-overlay" onclick="handleUploadModalOverlayClick(event)">
        <div class="modal-container upload-modal-container">
            <div class="modal-header">
                <div class="modal-title-group">
                    <span class="type-badge badge-pdf-bg" style="font-size: 0.7rem;">50 MB MAX</span>
                    <h3 class="modal-title">Upload Files</h3>
                    <span class="meta-pill" style="font-size: 0.75rem;">To: /resource/<?= htmlspecialchars($relDir ? $relDir : '') ?></span>
                </div>
                <button type="button" class="btn-icon" onclick="closeUploadModal()" title="Close (Esc)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="upload-modal-content">
                <!-- Dropzone Area -->
                <div id="dropzone" class="upload-dropzone">
                    <input type="file" id="fileUploadInput" multiple style="display: none;">
                    <div class="dropzone-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <div class="dropzone-text">
                        <strong>Click or drag files here to upload</strong>
                        <p>Upload any file type up to 50 MB</p>
                    </div>
                </div>

                <!-- Queue Container -->
                <div id="uploadQueueContainer" class="upload-queue-container" style="display: none;">
                    <div class="upload-queue-header">
                        <span id="uploadQueueSummary" style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">0 files</span>
                        <button type="button" class="btn-action" style="padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="clearUploadQueue()">Clear All</button>
                    </div>
                    <div id="uploadQueueList" class="upload-queue-list"></div>
                </div>

                <!-- Modal Actions -->
                <div class="upload-modal-footer">
                    <button type="button" class="btn-action" onclick="closeUploadModal()">Cancel</button>
                    <button type="button" class="btn-action btn-primary" id="startUploadBtn" disabled onclick="startQueuedUploads()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <span>Start Upload</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shared Network Clipboard Modal -->
    <div id="clipboardModal" class="modal-overlay" onclick="handleClipboardModalOverlayClick(event)">
        <div class="modal-container clipboard-modal-container">
            <div class="modal-header">
                <div class="modal-title-group">
                    <span class="type-badge badge-clipboard-bg">NETWORK CLIPBOARD</span>
                    <h3 class="modal-title">Shared Clipboard</h3>
                    <span id="clipboardStatusPill" class="meta-pill" style="font-size: 0.75rem;">Connecting...</span>
                </div>
                <div class="modal-controls">
                    <button type="button" class="btn-action" id="clipboardRefreshBtn" title="Fetch latest text from other laptops">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        <span>Sync</span>
                    </button>
                    <button type="button" class="btn-icon" onclick="closeClipboardModal()" title="Close (Esc)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="clipboard-modal-content">
                <!-- Toolbar with stats & actions -->
                <div class="clipboard-toolbar">
                    <div class="clipboard-meta-stats">
                        <span id="clipCharCount" class="chip-count" style="min-width:auto; padding: 0.2rem 0.6rem;">0 chars</span>
                        <span id="clipWordCount" class="chip-count" style="min-width:auto; padding: 0.2rem 0.6rem;">0 words</span>
                        <span id="clipLineCount" class="chip-count" style="min-width:auto; padding: 0.2rem 0.6rem;">0 lines</span>
                    </div>

                    <div class="clipboard-actions-group">
                        <button type="button" class="btn-action" id="clipFontToggleBtn" title="Toggle Monospace / Sans font">
                            <span id="clipFontLabel">Mono</span>
                        </button>
                        <button type="button" class="btn-action" id="clipPasteBtn" title="Paste from your device clipboard">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            </svg>
                            <span>Paste</span>
                        </button>
                        <button type="button" class="btn-action" id="clipCopyBtn" title="Copy entire text to your device clipboard">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span id="clipCopyBtnText">Copy All</span>
                        </button>
                        <button type="button" class="btn-action" id="clipClearBtn" title="Clear text area">
                            <span>Clear</span>
                        </button>
                        <button type="button" class="btn-action btn-primary" id="clipSaveBtn" title="Save to network clipboard (accessible by other laptops)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1-2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            <span id="clipSaveBtnText">Save &amp; Sync</span>
                        </button>
                    </div>
                </div>

                <!-- Live Sync notification banner if remote has newer content -->
                <div id="clipboardSyncNotice" class="clipboard-sync-notice" style="display: none;">
                    <span>⚡ New text was saved from another device!</span>
                    <button type="button" class="btn-action btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;" id="loadRemoteClipBtn">Load Latest</button>
                </div>

                <!-- Textarea for paste -->
                <div class="clipboard-editor-wrapper">
                    <textarea id="clipboardTextarea" class="clipboard-textarea font-mono" placeholder="Paste any text here (code, links, tokens, notes, json, logs)... Any laptop or device on your network can instantly access it! (Press Ctrl+S or Cmd+S to save)"></textarea>
                </div>

                <!-- Save to File Section & Recent Clips History -->
                <div class="clipboard-footer-tools">
                    <div class="clipboard-save-file-row">
                        <label for="clipSaveFileName" style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            Save clip as file to current folder:
                        </label>
                        <div style="display: flex; gap: 0.4rem; flex: 1; max-width: 380px;">
                            <input type="text" id="clipSaveFileName" class="search-input" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" placeholder="clipboard-note.txt">
                            <button type="button" class="btn-action" id="clipSaveAsFileBtn" style="white-space: nowrap; padding: 0.35rem 0.75rem; font-size: 0.8rem;">Save File</button>
                        </div>
                    </div>

                    <!-- History Drawer -->
                    <div class="clipboard-history-section">
                        <div class="clipboard-history-header" id="clipHistoryToggle">
                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Recent Clips History (<span id="clipHistoryCount">0</span>)
                            </span>
                            <span style="font-size: 0.75rem; color: var(--text-faint); cursor: pointer;" id="clipHistoryChevron">Show ▼</span>
                        </div>
                        <div id="clipHistoryList" class="clipboard-history-list" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal-overlay" onclick="handleModalOverlayClick(event)">
        <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="modalFileName">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-title-group">
                    <span id="modalTypeBadge" class="type-badge badge-file-bg">FILE</span>
                    <h3 id="modalFileName" class="modal-title">filename.ext</h3>
                    <span id="modalFileSize" class="meta-pill" style="font-size: 0.75rem;">0 MB</span>
                </div>
                <div class="modal-controls">
                    <button type="button" id="modalCopyBtn" class="btn-action" style="display: none;" onclick="copyModalContent()" title="Copy code to clipboard">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span id="modalCopyText">Copy</span>
                    </button>
                    <a id="modalExternalLink" href="#" target="_blank" rel="noopener" class="btn-action" title="Open in new tab">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        <span>Open Raw</span>
                    </a>
                    <a id="modalDownloadBtn" href="#" class="btn-action btn-primary" title="Download">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span>Download</span>
                    </a>
                    <button type="button" class="btn-icon" onclick="closePreview()" title="Close (Esc)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Content Body -->
            <div id="modalBody" class="modal-body">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <!-- Client-side Interactive Script -->
    <script src="app.js?v=<?= APP_VERSION ?>.<?= file_exists(__DIR__ . '/app.js') ? filemtime(__DIR__ . '/app.js') : time() ?>"></script>
</body>
</html>
