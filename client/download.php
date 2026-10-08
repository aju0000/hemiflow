<?php
// client/download.php - Secure File Download Handler with Ownership Verification

require_once __DIR__ . '/includes/client_auth.php';
requireClientAuth();

$id = intval($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'document';
$clientId = intval($_SESSION['client_id']);

$db = getDbConnection();

if ($type === 'task_attachment') {
    // 1. Verify Attachment Ownership via task_id -> task.client_id
    $stmtAtt = $db->prepare("
        SELECT ta.*, t.client_id
        FROM task_attachments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.id = ? AND t.client_id = ?
    ");
    $stmtAtt->execute([$id, $clientId]);
    $att = $stmtAtt->fetch();

    if (!$att) {
        denyClientAccess("403 Access Denied: Attachment does not belong to your client account.");
    }

    $filePath = $att['file_url'];
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($att['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        // If file URL is remote or missing locally, redirect safely
        header("Location: " . $filePath);
        exit;
    }
} else {
    // 2. View / Download Document
    verifyClientOwnership('documents', $id);

    $stmtDoc = $db->prepare("SELECT d.*, c.company_name FROM documents d JOIN clients c ON d.client_id = c.id WHERE d.id = ? AND d.client_id = ?");
    $stmtDoc->execute([$id, $clientId]);
    $doc = $stmtDoc->fetch();

    if (!$doc) {
        denyClientAccess("Document not found.");
    }

    // Render clean printable HTML view of client document
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($doc['title']) . ' (' . htmlspecialchars($doc['document_number']) . ')</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&family=Google+Sans+Text:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
        <style>
            body { font-family: "Google Sans", "Google Sans Text", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; margin: 0; padding: 40px 20px; color: #0f172a; }
            .doc-container { max-width: 800px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
            @media print { body { background: #fff; padding: 0; } .doc-container { border: none; box-shadow: none; padding: 0; } .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="no-print" style="max-width:800px; margin:0 auto 20px auto; display:flex; justify-content:space-between; align-items:center;">
            <button onclick="window.print()" style="background:#2563eb; color:#ffffff; border:none; padding:10px 18px; border-radius:6px; font-weight:700; cursor:pointer;">Print / Save as PDF</button>
            <a href="/client/documents.php" style="color:#64748b; text-decoration:none; font-weight:600;">&larr; Back to Documents</a>
        </div>
        <div class="doc-container">
            ' . $doc['content_html'] . '
        </div>
    </body>
    </html>';
    exit;
}
