<?php
// client/documents.php - Client Documents Library

$pageTitle = "Documents & Contracts";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="file-text" style="width:18px; height:18px;"></i> Proposals, Invoices & Agreements</h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Doc #</th>
                    <th>Document Type</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Generated Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="documentsList">
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Loading client documents...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/client/api.php?action=get_documents')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById('documentsList');
            if (!data.documents || data.documents.length === 0) {
                container.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No documents generated for your account.</td></tr>';
                return;
            }

            let html = '';
            data.documents.forEach(d => {
                html += `
                    <tr>
                        <td><strong>${d.document_number}</strong></td>
                        <td><span class="badge badge-primary">${d.document_type}</span></td>
                        <td><strong style="color:#0f172a;">${d.title}</strong></td>
                        <td><span class="badge badge-success">${d.status}</span></td>
                        <td>${d.created_at}</td>
                        <td>
                            <a href="/client/download.php?id=${d.id}&type=document" target="_blank" class="btn btn-outline btn-sm">
                                <i data-feather="eye"></i> View / Download
                            </a>
                        </td>
                    </tr>
                `;
            });
            container.innerHTML = html;
            if (window.feather) feather.replace();
        });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
