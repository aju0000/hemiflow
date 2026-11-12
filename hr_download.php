<?php
// hr_download.php - Secure Employee Document Download & Delivery Endpoint

require_once __DIR__ . '/auth.php';
checkAuth('hr-management');

$user = getCurrentUser();
$db = getDbConnection();

$docId = intval($_GET['id'] ?? 0);
if (!$docId) {
    http_response_code(400);
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'><h3>Invalid Request</h3><p>Document ID is missing.</p></div>");
}

$stmt = $db->prepare("SELECT ed.*, e.user_id as emp_user_id, e.full_name as emp_name 
                      FROM employee_documents ed 
                      JOIN employees e ON ed.employee_id = e.id 
                      WHERE ed.id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'><h3>Document Not Found</h3><p>The requested document record does not exist.</p></div>");
}

// Server-side Authorization Enforcement
$isHr = isHrAdmin();
$isOwner = ($doc['emp_user_id'] && $doc['emp_user_id'] == $user['id']);

if (!$isHr && !$isOwner) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'><h3>Access Denied</h3><p>You do not have permission to view this employee document.</p></div>");
}

// Confidential document protection
if ($doc['is_confidential'] && !$isHr) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'><h3>Confidential Document</h3><p>This file is classified as HR-confidential and cannot be accessed by employees directly.</p></div>");
}

// Resolve real file path and prevent directory traversal
$baseDir = realpath(__DIR__ . '/uploads/employee_docs');
$targetPath = realpath(__DIR__ . '/' . ltrim($doc['file_path'], '/'));

if (!$targetPath || strpos($targetPath, $baseDir) !== 0 || !file_exists($targetPath)) {
    http_response_code(404);
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'><h3>File Missing</h3><p>The physical file cannot be located on the server.</p></div>");
}

// Log download activity in audit trail
logActivity($user['id'], 'hr-management', 'DOWNLOAD_DOCUMENT', 'employee_documents', $docId, "Downloaded document: {$doc['document_title']} for {$doc['emp_name']}");

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $targetPath) ?: 'application/octet-stream';
finfo_close($finfo);

// Send clean headers
$filename = basename($doc['file_name']);
$isInline = (stripos($mimeType, 'pdf') !== false || stripos($mimeType, 'image/') !== false);
$disposition = $isInline ? 'inline' : 'attachment';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . filesize($targetPath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Stream file
ob_clean();
flush();
readfile($targetPath);
exit;
