<?php
// admin/view-doc.php - Authenticated Document Stream Controller
// Securely streams applicant documents without exposing the filesystem

require_once __DIR__ . '/../includes/auth.php';
requirePpsaLogin();

$fileRel = $_GET['file'] ?? '';
if (empty($fileRel)) {
    http_response_code(400);
    die("Error: No file specified.");
}

// Security: Prevent Directory Traversal
$fileRel = str_replace(['../', '..\\'], '', $fileRel);
$fileRel = ltrim($fileRel, '/\\');

$baseDir = realpath(dirname(__DIR__) . '/uploads');
$targetFile = realpath(dirname(__DIR__) . '/' . $fileRel);

if (!$targetFile || !$baseDir || strpos($targetFile, $baseDir) !== 0 || !file_exists($targetFile)) {
    http_response_code(404);
    die("Error: Requested document not found or access denied.");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $targetFile);
finfo_close($finfo);

header("Content-Type: {$mimeType}");
header("Content-Length: " . filesize($targetFile));
header("Content-Disposition: inline; filename=\"" . basename($targetFile) . "\"");
header("Cache-Control: private, max-age=3600");

readfile($targetFile);
exit();
