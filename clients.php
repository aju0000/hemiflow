<?php
// clients.php - Client Management & Documents Repository

require_once __DIR__ . '/auth.php';
checkAuth('client-management');

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "Client Management & Repository";
require_once __DIR__ . '/header.php';

$clients = $db->query("SELECT c.*, u.full_name as created_by_name,
                        usr.username as client_username,
                        (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id = c.id) as service_count,
                        (SELECT COUNT(*) FROM documents d WHERE d.client_id = c.id) as document_count
                        FROM clients c
                        LEFT JOIN users u ON c.created_by = u.id
                        LEFT JOIN users usr ON usr.client_id = c.id
                        ORDER BY c.id DESC")->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">Client Directory</h2>
        <p style="color:var(--text-muted); font-size:14px;">Manage accounts, portal credentials, package subscriptions, and generated document repository.</p>
    </div>
    <div style="display:flex; gap:8px;">
        <button class="btn btn-primary" onclick="openAddClientModal()">
            <i data-feather="user-plus"></i> + Add New Client
        </button>
        <a href="template_generator.php" class="btn btn-outline">
            <i data-feather="file-plus"></i> Generate Document
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Email & Phone</th>
                    <th>Portal Account</th>
                    <th>Status</th>
                    <th>Packages</th>
                    <th>Saved Docs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['company_name']) ?></strong><br>
                            <small style="color:var(--text-muted);">Tax ID: <?= htmlspecialchars($c['tax_id'] ?: 'N/A') ?></small>
                        </td>
                        <td><?= htmlspecialchars($c['contact_person']) ?></td>
                        <td>
                            <?= htmlspecialchars($c['email']) ?><br>
                            <small style="color:var(--text-muted);"><?= htmlspecialchars($c['phone'] ?: 'N/A') ?></small>
                        </td>
                        <td>
                            <?php if (!empty($c['client_username'])): ?>
                                <span class="badge badge-info" style="font-family:monospace;">@<?= htmlspecialchars($c['client_username']) ?></span><br>
                                <button class="btn btn-outline btn-sm" style="margin-top:4px; padding:2px 8px; font-size:11px;" onclick="openResetPasswordModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>', '<?= htmlspecialchars($c['client_username']) ?>')">
                                    <i data-feather="key"></i> Reset Password
                                </button>
                            <?php else: ?>
                                <span class="badge badge-secondary">No Account</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $c['status'] === 'Approved / Active' ? 'badge-success' : 'badge-primary' ?>">
                                <?= htmlspecialchars($c['status']) ?>
                            </span>
                        </td>
                        <td><span class="badge badge-secondary"><?= $c['service_count'] ?> Service(s)</span></td>
                        <td><span class="badge badge-primary"><?= $c['document_count'] ?> Document(s)</span></td>
                        <td>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <a href="template_generator.php" class="btn btn-outline btn-sm">Generate Document</a>
                                <?php if ($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteClientAccount(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>')" title="Delete Client Account">
                                        <i data-feather="trash-2"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ADD NEW CLIENT -->
<div id="addClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">👤 Add New Client & Set Portal Credentials</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateClient();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="ac_company_name" class="form-control" required placeholder="e.g. Acme Tech Solutions">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="ac_contact_person" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="ac_email" class="form-control" required placeholder="john@acme.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="ac_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Service Package Name</label>
                        <input type="text" id="ac_service_name" class="form-control" placeholder="e.g. Performance Marketing Suite">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Package Price (INR)</label>
                        <input type="number" id="ac_price" class="form-control" placeholder="50000">
                    </div>
                </div>

                <!-- Custom Portal Login Credentials Section -->
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:14px; margin-top:10px;">
                    <strong style="color:#0369a1; font-size:13px; display:block; margin-bottom:8px;">🔑 Separate Portal Login Credentials for this Client:</strong>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Portal Username (Optional)</label>
                            <input type="text" id="ac_username" class="form-control" placeholder="e.g. acme_client">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Portal Password (Optional)</label>
                            <input type="text" id="ac_password" class="form-control" placeholder="e.g. AcmePass#2026">
                        </div>
                    </div>
                    <small style="color:#0284c7; display:block; margin-top:6px;">If left blank, a unique username (@client_company) and a unique random password (e.g. Client#8942) will be auto-generated.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Client Account</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: RESET CLIENT PASSWORD -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔑 Reset Client Portal Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetClientPassword();">
            <div class="modal-body">
                <input type="hidden" id="rp_client_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new password for <strong id="rp_client_name">Client</strong> (<code id="rp_username">@username</code>)
                </p>
                <div class="form-group">
                    <label class="form-label">New Custom Password *</label>
                    <input type="text" id="rp_new_password" class="form-control" required placeholder="Enter new custom password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddClientModal() {
    document.getElementById('ac_company_name').value = '';
    document.getElementById('ac_contact_person').value = '';
    document.getElementById('ac_email').value = '';
    document.getElementById('ac_phone').value = '';
    document.getElementById('ac_service_name').value = '';
    document.getElementById('ac_price').value = '';
    document.getElementById('ac_username').value = '';
    document.getElementById('ac_password').value = '';
    document.getElementById('addClientModal').style.display = 'flex';
}

function submitCreateClient() {
    const payload = {
        company_name: document.getElementById('ac_company_name').value,
        contact_person: document.getElementById('ac_contact_person').value,
        email: document.getElementById('ac_email').value,
        phone: document.getElementById('ac_phone').value,
        service_name: document.getElementById('ac_service_name').value,
        price: document.getElementById('ac_price').value,
        username: document.getElementById('ac_username').value,
        password: document.getElementById('ac_password').value
    };

    fetch('api.php?action=create_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('addClientModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openResetPasswordModal(clientId, companyName, username) {
    document.getElementById('rp_client_id').value = clientId;
    document.getElementById('rp_client_name').textContent = companyName;
    document.getElementById('rp_username').textContent = '@' + username;
    document.getElementById('rp_new_password').value = '';
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function submitResetClientPassword() {
    const clientId = document.getElementById('rp_client_id').value;
    const newPassword = document.getElementById('rp_new_password').value;

    fetch('api.php?action=reset_client_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId, password: newPassword })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('resetPasswordModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteClientAccount(clientId, companyName) {
    if (!confirm(`Are you sure you want to permanently delete client '${companyName}'?\n\nThis will remove all linked portal data, client user accounts, and document records. This action cannot be undone.`)) {
        return;
    }

    fetch('api.php?action=delete_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
